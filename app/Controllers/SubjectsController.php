<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response,Validator};
use App\Repositories\{AcademicRepository,SettingsRepository};
use App\Services\AcademicService;
use App\Support\Flash;
final class SubjectsController extends AdminPageController
{
    private function repo(): AcademicRepository{return new AcademicRepository($this->app->database());}
    private function instituteId(): int{return (int)(new SettingsRepository($this->app->database()))->institute()['id'];}
    private function denied(): ?Response{return can('subjects.view')?null:Response::html('Forbidden',403);}
    public function index(Request $r): Response {if($x=$this->denied())return $x;return $this->renderPage('class-subjects',['classes'=>$this->repo()->withSubjects($this->instituteId())]);}
    public function assign(Request $r): Response {if($x=$this->denied())return $x;return $this->renderPage('subject-assign',['classes'=>$this->repo()->classes($this->instituteId())]);}
    public function store(Request $r): Response {$data=['subject_class'=>$r->input('subject_class'),'subject_name'=>trim((string)$r->input('subject_name')),'subject_marks'=>$r->input('subject_marks')];$v=new Validator();if(!$v->validate($data,['subject_class'=>'required|integer','subject_name'=>'required|max:120','subject_marks'=>'required|numeric|min:1'])){$this->app->session()->flash('_errors',$v->errors());$this->app->session()->flash('_old',$data);Flash::add('error','Please correct the subject details.');return Response::redirect(url('/admin/subjects/assign'));}$result=(new AcademicService($this->repo()))->assignSubject($this->instituteId(),(int)$data['subject_class'],$data['subject_name'],(float)$data['subject_marks']);if($result!=='assigned'){$message=$result==='duplicate'?'That subject is already assigned to this class.':'The selected class is invalid.';$this->app->session()->flash('_errors',['subject_class'=>[$message]]);Flash::add('error',$message);return Response::redirect(url('/admin/subjects/assign'));}Flash::add('success','Subject assigned.');return Response::redirect(url('/admin/subjects'));}
}
