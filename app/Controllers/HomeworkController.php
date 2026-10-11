<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{WorkflowRepository,SettingsRepository,AcademicRepository,EmployeeRepository,SupportRepository,StudentRepository};use App\Support\{UploadPolicy,Flash};

/**
 * Homework permissions grant the same institute workspace to administrators and staff.
 */
final class HomeworkController extends AdminPageController{
private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
private function repo():WorkflowRepository{return new WorkflowRepository($this->app->database());}

/** Teachers keep full module access, but cannot act under another teacher's name. */
private function authorTeacher():int{
$t=\App\Support\TeacherIdentity::teacher($this->app->database(),$this->i());
return$t===null?0:((int)$t['id']?:-1);
}

public function index(Request$r):Response{$i=$this->i();$pinned=0;$author=$this->authorTeacher();$teachers=(new EmployeeRepository($this->app->database()))->all($i,'',true);
if($pinned>0)$teachers=array_values(array_filter($teachers,static fn(array$t):bool=>(int)$t['id']===$pinned));
$teacherFilter=$pinned>0?$pinned:(int)$r->query('teacher',0);
return$this->renderPage('homework',['rows'=>$this->repo()->homework($i,(string)$r->query('date')?:null,(int)$r->query('class',0),$teacherFilter),'classes'=>(new AcademicRepository($this->app->database()))->classes($i),'students'=>(new StudentRepository($this->app->database()))->basicList($i,'',null),'teachers'=>$teachers,'subjects'=>(new SupportRepository($this->app->database()))->subjects($i),'pinnedTeacher'=>$pinned,'authorLocked'=>$author!==0,'authorName'=>$author>0?(string)$this->app->database()->query('SELECT name FROM employees WHERE id=:id',['id'=>$author])->fetchColumn():'','canManage'=>can('homework.manage'),'editId'=>(int)$r->query('edit',0),'filterDate'=>(string)$r->query('date',''),'filterClass'=>(int)$r->query('class',0),'filterTeacher'=>$teacherFilter]);}

public function store(Request$r):Response{return$this->save($r);}
public function update(Request$r):Response{return$this->save($r,(int)$r->route('id'));}

public function delete(Request$r):Response{if(!can('homework.manage'))return Response::html('Access denied',403);
$deleted=$this->repo()->deleteHomework($this->i(),(int)$r->route('id'));
Flash::add($deleted?'success':'error',$deleted?'Homework deleted.':'Homework not found.');
return Response::redirect(url('/admin/homework'));}

private function save(Request$r,?int$id=null):Response{if(!can('homework.manage'))return Response::html('Access denied',403);$pinned=0;$author=$this->authorTeacher();
$teacher=$r->input('hwteacher');
if($author!==0){
if($author<0)return Response::html('Teacher account is not linked to an employee.',403);
$teacher=$author;
if($id!==null){$teacher=$this->app->database()->query('SELECT h.teacher_id FROM homework h JOIN employees e ON e.id=h.teacher_id WHERE h.id=:id AND e.institute_id=:i',['id'=>$id,'i'=>$this->i()])->fetchColumn();if(!$teacher)return Response::html('Homework not found',404);}
}
$attachment=null;$file=$r->file('hwattachment');if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$policy=new UploadPolicy(['application/pdf','image/jpeg','image/png'],['pdf','jpg','jpeg','png'],5242880);if(!$policy->validate($file))return Response::html('Invalid homework attachment',422);$name=$policy->safeFilename(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$dir=BASE_PATH.'/public/uploads/homework';if(!is_dir($dir))mkdir($dir,0775,true);if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))return Response::html('Upload failed',422);$attachment='uploads/homework/'.$name;}
$data=['date'=>$r->input('hwdate'),'teacher_id'=>$teacher,'audience_type'=>$pinned>0?'class':$r->input('audience_type','class'),'class_ids'=>$pinned>0?[(int)$r->input('hwclass')]:(array)$r->input('class_ids',[]),'class_id'=>$r->input('hwclass'),'student_id'=>$r->input('student_id'),'subject_id'=>$r->input('hwsubject'),'text'=>$r->input('homework'),'attachment'=>$attachment];
try{$w=$this->repo();$id?$w->updateHomework($this->i(),$id,$data):$w->createHomework($this->i(),$data,(int)user()['id']);}catch(\DomainException$e){if($attachment&&is_file(BASE_PATH.'/public/'.$attachment))unlink(BASE_PATH.'/public/'.$attachment);return Response::html(e($e->getMessage()),422);}
Flash::add('success',$id?'Homework updated.':'Homework added.');return Response::redirect(url('/admin/homework'));}
}
