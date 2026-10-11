<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\OnlineTestRepository;use App\Support\{Flash,SecureAttachment};
final class StudentOnlineTestsController extends PortalController{
private function r():OnlineTestRepository{return new OnlineTestRepository($this->app->database());}
public function index(Request$r):Response{if(!$s=$this->repo()->studentByUser($this->userId()))return$this->forbidden();return$this->portalView('portal/student/online-tests','student',['pageTitle'=>'Online Tests','activeMenu'=>'portal-online-tests','instituteId'=>(int)$s['institute_id'],'student'=>$s,'tests'=>$tests=$this->r()->available($s),'submittedTestIds'=>array_column(array_filter($tests,static fn($t)=>in_array($t['attempt_status']??'',['submitted','marked'],true)),'id'),'styles'=>['css/submission-pages.css?v=4'],'scripts'=>['js/submission-pages.js?v=1']]);}
public function show(Request$r):Response{
    if(!$s=$this->repo()->studentByUser($this->userId()))return$this->forbidden();
    $test=$this->r()->start((int)$r->route('id'),$s);
    if(!$test)return Response::html('Test not available',404);
    if(in_array($test['attempt']['status']??'',['submitted','marked'],true))return Response::redirect(url('/portal/student/online-tests'));
    $deadline=$test['duration_minutes']?strtotime((string)$test['attempt']['started_at'])+OnlineTestRepository::allowance($test,$test['attempt'])*60:null;
    return$this->portalView('portal/student/take-test','student',['pageTitle'=>$test['title'],'activeMenu'=>'portal-online-tests','instituteId'=>(int)$s['institute_id'],'student'=>$s,'test'=>$test,'maxPages'=>$this->r()->maxPages($test),'deadline'=>$deadline,'hideInstallBanner'=>true,
        'styles'=>['css/online-tests.css?v=3','css/upload-only-tests.css?v=1','css/online-test-mobile.css?v=1','css/test-uploads.css?v=1','css/submission-pages.css?v=4'],'scripts'=>['js/online-test-student.js?v=3','js/submission-pages.js?v=1']]);
}
/**
 * Final submission. The uploader posts here with fetch/XHR (Accept: application/json) and keeps
 * the student's pages on the device until this returns ok; a plain form post still works.
 * Nothing is stored until the attempt is confirmed submittable, and stored files are removed
 * again if the submission is refused, so a failed try never leaves orphaned photos.
 */
public function submit(Request$r):Response{
    if(!$s=$this->repo()->studentByUser($this->userId()))return$this->forbidden();
    $id=(int)$r->route('id');
    $json=str_contains((string)$r->header('Accept',''),'application/json');
    $stored=[];
    try{
        $test=$this->r()->assertSubmittable($id,$s);
        $files=$r->file('answer_attachment');$paperFiles=$r->file('solved_paper');
        $all=[];
        foreach(($files['name']??[])as$k=>$_)foreach(SecureAttachment::nestedList($files,$k)as$f)$all[]=$f;
        foreach(($paperFiles['name']??[])as$k=>$name)if($name!=='')$all[]=SecureAttachment::nested($paperFiles,$k);
        $expected=$r->input('page_count');
        if($expected!==null&&$expected!==''&&(int)$expected!==count($all))throw new \DomainException('Only '.count($all).' of '.(int)$expected.' pages reached the server. Your pages are still on this device — please press Try again.');
        foreach($all as$n=>$f){
            $error=(int)($f['error']??UPLOAD_ERR_OK);
            if(in_array($error,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new \DomainException('Page '.($n+1).' is larger than the '.ini_get('upload_max_filesize').'B limit. Remove it and add a smaller photo.');
            if($error===UPLOAD_ERR_PARTIAL)throw new \DomainException('Page '.($n+1).' did not finish uploading. Please press Try again.');
        }
        $uploaded=[];
        foreach(($files['name']??[])as$k=>$_){$paths=[];foreach(SecureAttachment::nestedList($files,$k)as$f)$stored[]=$paths[]=SecureAttachment::store($f,'test-answers');if($paths)$uploaded[$k]=$paths;}
        $papers=[];
        foreach(($paperFiles['name']??[])as$k=>$name)if($name!=='')$stored[]=$papers[]=SecureAttachment::store(SecureAttachment::nested($paperFiles,$k),'test-papers');
        $this->r()->submit($id,$s,(array)$r->input('answer',[]),$uploaded,$papers);
        $late=OnlineTestRepository::lateMinutes($test['attempt']['started_at'],OnlineTestRepository::allowance($test,$test['attempt']));
        $pages=count($papers)+array_sum(array_map('count',$uploaded));
        $message=($pages?$pages.($pages===1?' page':' pages').' submitted to your teacher.':'Your answers were submitted to your teacher.').($late?' It was '.$late.' min after the time allowed, so your teacher will see it as late.':'');
        Flash::add('success',$message);
        return$json?Response::json(['ok'=>true,'message'=>$message,'redirect'=>url('/portal/student/online-tests')]):Response::redirect(url('/portal/student/online-tests'));
    }catch(\DomainException|\RuntimeException$e){
        foreach(array_filter($stored)as$path)@unlink(BASE_PATH.'/'.$path);
        $message=$e instanceof \DomainException?$e->getMessage():'The upload could not be saved on the server. Your pages are still on this device — please try again.';
        if($json)return Response::json(['ok'=>false,'message'=>$message],422);
        Flash::add('error',$message);
        return Response::redirect(url('/portal/student/online-tests/'.$id));
    }
}
/** Per-question marks and feedback for one published result. */
public function result(Request$r):Response{
    if(!$s=$this->repo()->studentByUser($this->userId()))return$this->forbidden();
    $id=(int)$r->route('id');
    $attempt=$this->r()->resultForStudent($id,$s);
    if($attempt)return$this->portalView('portal/student/test-result','student',['pageTitle'=>$attempt['title'],'activeMenu'=>'portal-online-tests','instituteId'=>(int)$s['institute_id'],'student'=>$s,'attempt'=>$attempt,'styles'=>['css/online-tests.css?v=3','css/online-test-mobile.css?v=1']]);
    // Not visible yet — if it's genuinely this student's own attempt, explain why instead of a bare 404.
    $own=$this->r()->ownAttempt($id,$s);
    if(!$own)return$this->forbidden();
    return$this->portalView('portal/student/test-result-pending','student',['pageTitle'=>$own['title'],'activeMenu'=>'portal-online-tests','instituteId'=>(int)$s['institute_id'],'student'=>$s,'attempt'=>$own]);
}
}
