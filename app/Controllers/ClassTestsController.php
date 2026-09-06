<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{WorkflowRepository,SettingsRepository,AcademicRepository,SupportRepository,StudentRepository};use App\Support\Flash;

/**
 * Class tests: create a test with marks, list and edit existing tests, re-enter marks, delete.
 * Previously only the create-with-marks path existed, so a test could never be corrected.
 */
final class ClassTestsController extends AdminPageController{
private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
private function repo():WorkflowRepository{return new WorkflowRepository($this->app->database());}
private function data(int$class=0):array{$i=$this->i();return['classes'=>(new AcademicRepository($this->app->database()))->classes($i),'subjects'=>(new SupportRepository($this->app->database()))->subjects($i,$class),'students'=>(new StudentRepository($this->app->database()))->paginate($i,'',$class?:null,100),'selectedClass'=>$class];}

public function marks(Request$r):Response{return$this->renderPage('test-marks',$this->data((int)$r->query('class',0)));}

public function store(Request$r):Response{try{$w=$this->repo();$id=$w->createClassTest($this->i(),(int)$r->input('class'),(int)$r->input('subject'),(string)$r->input('date'),(string)$r->input('title'),(float)$r->input('total_marks'),(int)user()['id']);$w->saveClassTestMarks($this->i(),$id,(array)$r->input('marks',[]),(int)user()['id']);}catch(\DomainException$e){return Response::html(e($e->getMessage()),422);}Flash::add('success','Class test recorded.');return Response::redirect(url('/admin/class-tests'));}

/** Manage list: every recorded test with how many students are marked. */
public function index(Request$r):Response{$class=(int)$r->query('class',0);return$this->renderPage('test-manage',$this->data($class)+['tests'=>$this->repo()->classTests($this->i(),$class,(int)$r->query('subject',0))]);}

/** Mark-entry grid for an existing test, prefilled with any marks already saved. */
public function edit(Request$r):Response{$test=$this->repo()->classTest($this->i(),(int)$r->route('id'));if(!$test)return Response::html('Class test not found',404);return$this->renderPage('test-edit',['test'=>$test,'sheet'=>$this->repo()->classTestMarkSheet($this->i(),(int)$test['id'])]);}

public function update(Request$r):Response{$id=(int)$r->route('id');try{$this->repo()->updateClassTest($this->i(),$id,(string)$r->input('title'),(string)$r->input('date'),(float)$r->input('total_marks'));Flash::add('success','Class test updated.');}catch(\DomainException$e){Flash::add('error',$e->getMessage());}return Response::redirect(url('/admin/class-tests/'.$id.'/edit'));}

public function saveMarks(Request$r):Response{$id=(int)$r->route('id');try{$this->repo()->saveClassTestMarks($this->i(),$id,(array)$r->input('marks',[]),(int)user()['id']);Flash::add('success','Test marks saved.');}catch(\DomainException$e){Flash::add('error',$e->getMessage());}return Response::redirect(url('/admin/class-tests/'.$id.'/edit'));}

public function delete(Request$r):Response{$deleted=$this->repo()->deleteClassTest($this->i(),(int)$r->route('id'));Flash::add($deleted?'success':'error',$deleted?'Class test deleted.':'Class test not found.');return Response::redirect(url('/admin/class-tests'));}

public function results(Request$r):Response{$class=(int)$r->query('class',0);$rows=$this->repo()->classTestResults($this->i(),$class,(int)$r->query('subject',0),(int)$r->query('student',0),(string)$r->query('from')?:null,(string)$r->query('to')?:null);return$this->renderPage('test-results',$this->data($class)+['rows'=>$rows]);}
}
