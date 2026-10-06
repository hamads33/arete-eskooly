<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{AcademicRepository,EmployeeRepository,RecordedLectureRepository,SettingsRepository,StudentRepository};use App\Support\{Flash,UploadPolicy};
/**
 * Admin screen for recorded lectures. AdminAreaMiddleware has already required
 * recorded-lectures.view (GET) or recorded-lectures.manage (POST) from config/permissions.php,
 * so only the admin or a role the admin granted "manage" can upload, link or delete.
 */
final class RecordedLecturesController extends AdminPageController{
private const VIDEO_MIMES=['video/mp4','video/webm','video/quicktime','video/x-m4v','video/ogg'];
private const MAX_BYTES=1073741824;
private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}private function r():RecordedLectureRepository{return new RecordedLectureRepository($this->app->database());}
public function index(Request$q):Response{return$this->view('recorded-lectures/index',['pageTitle'=>'Recorded Lectures','activeMenu'=>'recorded-lectures','lectures'=>$this->r()->all($this->i()),'canManage'=>can('*')||can('recorded-lectures.manage'),'classes'=>(new AcademicRepository($this->app->database()))->withSubjects($this->i()),'students'=>(new StudentRepository($this->app->database()))->basicList($this->i(),'',null),'teachers'=>(new EmployeeRepository($this->app->database()))->all($this->i(),'',true)]);}
public function store(Request$q):Response{$path=null;try{$path=$this->storeVideo($q->file('video'));$this->r()->save($this->i(),['audience_type'=>(string)$q->input('audience_type','class'),'class_ids'=>(array)$q->input('class_ids',[]),'subject_id'=>(int)$q->input('subject_id'),'student_id'=>(int)$q->input('student_id'),'teacher_id'=>(int)$q->input('teacher_id'),'chapter'=>trim((string)$q->input('chapter'))?:null,'title'=>trim((string)$q->input('title')),'description'=>trim((string)$q->input('description'))?:null,'video_path'=>$path,'video_url'=>trim((string)$q->input('video_url')),'publish_date'=>(string)$q->input('publish_date'),'status'=>(string)$q->input('status'),'created_by'=>(int)user()['id']]);Flash::add('success','Recorded lecture saved.');}catch(\DomainException$e){if($path&&is_file(BASE_PATH.'/'.$path))unlink(BASE_PATH.'/'.$path);Flash::add('error',$e->getMessage());}return Response::redirect(url('/admin/recorded-lectures'));}
public function delete(Request$q):Response{$path=$this->r()->delete((int)$q->route('id'),$this->i());if($path&&is_file(BASE_PATH.'/'.$path))unlink(BASE_PATH.'/'.$path);Flash::add('success','Recorded lecture deleted.');return Response::redirect(url('/admin/recorded-lectures'));}
public function show(Request$q):Response{$id=(int)$q->route('id');$lecture=null;foreach($this->r()->all($this->i())as$l)if((int)$l['id']===$id)$lecture=$l;if(!$lecture)return Response::redirect(url('/admin/recorded-lectures'));$lecture['subject']=$lecture['subject_name']??'General';return$this->view('recorded-lectures/show',['pageTitle'=>$lecture['title'],'activeMenu'=>'recorded-lectures','lecture'=>$lecture,'canManage'=>can('*')||can('recorded-lectures.manage')]);}
public function video(Request$q):Response{return self::stream($this->r()->adminVideo((int)$q->route('id'),$this->i()),$q);}
/** Serves a stored lecture video after the caller has authorised access to it. */
public static function stream(?string$relative,Request$q):Response{if(!$relative)return Response::html('Video not found',404);$root=realpath(BASE_PATH.'/storage/uploads/lectures');$path=realpath(BASE_PATH.'/'.$relative);if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path))return Response::html('Video not found',404);$mime=(string)(new \finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,self::VIDEO_MIMES,true))return Response::html('Video unavailable',415);return Response::file($path,$mime==='video/quicktime'?'video/mp4':$mime,$q->header('Range'),['Content-Disposition'=>'inline','X-Content-Type-Options'=>'nosniff']);}
private function storeVideo(?array$file):?string{
    if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if(in_array($file['error']??0,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new \DomainException('The video is larger than the server upload limit ('.ini_get('upload_max_filesize').'). Share it as a link instead, or ask your host to raise the limit.');
    $policy=new UploadPolicy(self::VIDEO_MIMES,['mp4','webm','mov','m4v','ogv'],self::MAX_BYTES);
    if(!$policy->validate($file))throw new \DomainException('Only MP4, WebM, MOV or M4V videos up to 1 GB are allowed.');
    $dir=BASE_PATH.'/storage/uploads/lectures';if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Upload folder could not be created.');
    $name=$policy->safeFilename(pathinfo((string)$file['name'],PATHINFO_EXTENSION));if(!move_uploaded_file((string)$file['tmp_name'],$dir.'/'.$name))throw new \RuntimeException('Upload failed.');return'storage/uploads/lectures/'.$name;
}
}
