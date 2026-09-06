<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{WorkflowRepository,SettingsRepository,AcademicRepository,EmployeeRepository,SupportRepository};use App\Support\{UploadPolicy,Flash};

/**
 * Homework assignment. Administrators may assign on behalf of any teacher; a signed-in
 * teacher is pinned to their own employee record so they cannot post as a colleague.
 */
final class HomeworkController extends AdminPageController{
private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
private function repo():WorkflowRepository{return new WorkflowRepository($this->app->database());}

/** The employee id this user must author as, or 0 when they may choose (administrators). */
private function pinnedTeacher():int{if(can('*'))return 0;return$this->repo()->employeeIdForUser((int)(user()['id']??0),$this->i());}

public function index(Request$r):Response{$i=$this->i();$pinned=$this->pinnedTeacher();$teachers=(new EmployeeRepository($this->app->database()))->all($i,'',true);
if($pinned>0)$teachers=array_values(array_filter($teachers,static fn(array$t):bool=>(int)$t['id']===$pinned));
$teacherFilter=$pinned>0?$pinned:(int)$r->query('teacher',0);
return$this->renderPage('homework',['rows'=>$this->repo()->homework($i,(string)$r->query('date')?:null,(int)$r->query('class',0),$teacherFilter),'classes'=>(new AcademicRepository($this->app->database()))->classes($i),'teachers'=>$teachers,'subjects'=>(new SupportRepository($this->app->database()))->subjects($i),'pinnedTeacher'=>$pinned]);}

public function store(Request$r):Response{return$this->save($r);}
public function update(Request$r):Response{return$this->save($r,(int)$r->route('id'));}

public function delete(Request$r):Response{$id=(int)$r->route('id');$pinned=$this->pinnedTeacher();
if($pinned>0&&!$this->ownedByTeacher($id,$pinned)){Flash::add('error','You can only delete homework you assigned.');return Response::redirect(url('/admin/homework'));}
$deleted=$this->repo()->deleteHomework($this->i(),$id);
Flash::add($deleted?'success':'error',$deleted?'Homework deleted.':'Homework not found.');
return Response::redirect(url('/admin/homework'));}

private function ownedByTeacher(int$id,int$teacher):bool{return(bool)$this->app->database()->query('SELECT 1 FROM homework h JOIN classes c ON c.id=h.class_id WHERE h.id=:id AND h.teacher_id=:t AND c.institute_id=:i',['id'=>$id,'t'=>$teacher,'i'=>$this->i()])->fetchColumn();}

private function save(Request$r,?int$id=null):Response{$pinned=$this->pinnedTeacher();
if($id!==null&&$pinned>0&&!$this->ownedByTeacher($id,$pinned)){Flash::add('error','You can only edit homework you assigned.');return Response::redirect(url('/admin/homework'));}
$attachment=null;$file=$r->file('hwattachment');if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$policy=new UploadPolicy(['application/pdf','image/jpeg','image/png'],['pdf','jpg','jpeg','png'],5242880);if(!$policy->validate($file))return Response::html('Invalid homework attachment',422);$name=$policy->safeFilename(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$dir=BASE_PATH.'/public/uploads/homework';if(!is_dir($dir))mkdir($dir,0775,true);if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))return Response::html('Upload failed',422);$attachment='uploads/homework/'.$name;}
$data=['date'=>$r->input('hwdate'),'teacher_id'=>$pinned>0?$pinned:$r->input('hwteacher'),'class_id'=>$r->input('hwclass'),'subject_id'=>$r->input('hwsubject'),'text'=>$r->input('homework'),'attachment'=>$attachment];
try{$w=$this->repo();$id?$w->updateHomework($this->i(),$id,$data):$w->createHomework($this->i(),$data,(int)user()['id']);}catch(\DomainException$e){if($attachment&&is_file(BASE_PATH.'/public/'.$attachment))unlink(BASE_PATH.'/public/'.$attachment);return Response::html(e($e->getMessage()),422);}
Flash::add('success',$id?'Homework updated.':'Homework added.');return Response::redirect(url('/admin/homework'));}
}
