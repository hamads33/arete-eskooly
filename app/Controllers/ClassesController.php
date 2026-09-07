<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response,Validator};
use App\Repositories\{AcademicRepository,SettingsRepository};
use App\Services\AcademicService;
use App\Support\Flash;
final class ClassesController extends AdminPageController
{
    private function repo(): AcademicRepository{return new AcademicRepository($this->app->database());}
    private function instituteId(): int{return (int)(new SettingsRepository($this->app->database()))->institute()['id'];}
    private function denied(): ?Response{return can('classes.view')?null:Response::html('Forbidden',403);}
    public function index(Request $r): Response {if($x=$this->denied())return $x;$institute=(new SettingsRepository($this->app->database()))->institute();return $this->renderPage('classes',['classes'=>$this->repo()->classes((int)$institute['id'],trim((string)$r->query('class_search',''))),'search'=>trim((string)$r->query('class_search','')),'institute'=>$institute]);}
    public function create(Request $r): Response {if($x=$this->denied())return $x;return $this->renderPage('class-add',['classRecord'=>null]);}
    public function edit(Request $r): Response {if($x=$this->denied())return $x;$row=$this->repo()->findClass((int)$r->route('id'),$this->instituteId());if(!$row)return Response::html('Class not found',404);return $this->renderPage('class-add',['classRecord'=>$row]);}
    public function store(Request $r): Response {
        $id=(int)$r->route('id');$editing=$id>0;$data=['classname'=>trim((string)$r->input('classname')),'fees'=>$r->input('fees'),'teacher'=>trim((string)$r->input('teacher'))];$v=new Validator();
        if(!$v->validate($data,['classname'=>'required|max:120','fees'=>'required|numeric|min:0','teacher'=>'max:120'])){$this->app->session()->flash('_errors',$v->errors());$this->app->session()->flash('_old',$data);Flash::add('error','Please correct the class details.');return Response::redirect(url($editing?'/admin/classes/'.$id.'/edit':'/admin/classes/create'));}
        if($editing){$result=$this->repo()->updateClass($id,$this->instituteId(),$data['classname'],(float)$data['fees'],$data['teacher']?:null);if($result==='missing')return Response::html('Class not found',404);if($result==='duplicate'){$this->app->session()->flash('_errors',['classname'=>['A class with this name already exists.']]);$this->app->session()->flash('_old',$data);Flash::add('error','A class with this name already exists.');return Response::redirect(url('/admin/classes/'.$id.'/edit'));}Flash::add('success','Class updated.');return Response::redirect(url('/admin/classes'));}
        $result=(new AcademicService($this->repo()))->createClass($this->instituteId(),$data['classname'],(float)$data['fees'],$data['teacher']?:null);if($result==='duplicate'){$this->app->session()->flash('_errors',['classname'=>['A class with this name already exists.']]);$this->app->session()->flash('_old',$data);Flash::add('error','A class with this name already exists.');return Response::redirect(url('/admin/classes/create'));}Flash::add('success','Class created.');return Response::redirect(url('/admin/classes'));
    }
    public function delete(Request $r): Response {if($x=$this->denied())return $x;$result=$this->repo()->deleteClass((int)$r->route('id'),$this->instituteId());if($result==='missing')return Response::html('Class not found',404);if($result==='in_use'){Flash::add('error','This class cannot be deleted while it has students or academic records.');return Response::redirect(url('/admin/classes'));}Flash::add('success','Class deleted.');return Response::redirect(url('/admin/classes'));}
    public function bulkDelete(Request $r): Response {if($x=$this->denied())return $x;$result=$this->repo()->deleteClasses((array)$r->input('class_ids',[]),$this->instituteId());if($result['deleted'])Flash::add('success',$result['deleted'].' class(es) deleted.');if($result['blocked'])Flash::add('error',$result['blocked'].' class(es) could not be deleted because they contain students or academic records.');if(!$result['deleted']&&!$result['blocked'])Flash::add('error','Select at least one class to delete.');return Response::redirect(url('/admin/classes'));}
    public function forceDelete(Request $r): Response {if($x=$this->denied())return $x;$result=$this->repo()->forceDeleteClass((int)$r->route('id'),$this->instituteId(),(int)(user()['id']??0));if($result==='missing')return Response::html('Class not found',404);Flash::add('success','Class and all dependent records permanently deleted.');return Response::redirect(url('/admin/classes'));}
    public function forceBulkDelete(Request $r): Response {if($x=$this->denied())return $x;$deleted=$this->repo()->forceDeleteClasses((array)$r->input('class_ids',[]),$this->instituteId(),(int)(user()['id']??0));if($deleted)Flash::add('success',$deleted.' class(es) and dependent records permanently deleted.');else Flash::add('error','Select at least one class to force delete.');return Response::redirect(url('/admin/classes'));}
}
