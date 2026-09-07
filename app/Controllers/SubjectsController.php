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
    public function index(Request $r): Response {if($x=$this->denied())return $x;return $this->renderPage('class-subjects',['classes'=>$this->repo()->withSubjects($this->instituteId()),'subjects'=>$this->repo()->subjects($this->instituteId())]);}
    public function assign(Request $r): Response {if($x=$this->denied())return $x;return $this->renderPage('subject-assign',['classes'=>$this->repo()->classes($this->instituteId())]);}
    public function store(Request $r): Response {if($x=$this->denied())return$x;$class=$r->input('subject_class');$names=(array)$r->input('subject_name',[]);$marks=(array)$r->input('subject_marks',[]);$v=new Validator();$errors=[];if(!$v->validate(['subject_class'=>$class],['subject_class'=>'required|integer']))$errors=$v->errors();if(!$names||count($names)!==count($marks))$errors['subject_name'][]='Add at least one complete subject row.';foreach($names as$index=>$name){$row=['name'=>trim((string)$name),'marks'=>$marks[$index]??null];if(!$v->validate($row,['name'=>'required|max:120','marks'=>'required|numeric|min:1']))$errors['subject_name'][]='Row '.($index+1).' has an invalid subject name or marks.';}if($errors){$this->app->session()->flash('_errors',$errors);Flash::add('error',reset($errors)[0]??'Please correct the subject details.');return Response::redirect(url('/admin/subjects/assign'));}$service=new AcademicService($this->repo());$assigned=0;$duplicates=0;foreach($names as$index=>$name){$result=$service->assignSubject($this->instituteId(),(int)$class,(string)$name,(float)$marks[$index]);if($result==='invalid_class'){Flash::add('error','The selected class is invalid.');return Response::redirect(url('/admin/subjects/assign'));}$result==='assigned'?$assigned++:$duplicates++;}Flash::add($assigned?'success':'error',$assigned.' subject(s) assigned'.($duplicates?' and '.$duplicates.' duplicate(s) skipped.':'.'));return Response::redirect(url('/admin/subjects'));}

    /** Rename a subject across the institute. */
    public function rename(Request $r): Response
    {
        if ($x = $this->denied()) { return $x; }
        $result = $this->repo()->renameSubject((int) $r->route('id'), $this->instituteId(), (string) $r->input('name'));
        Flash::add($result === 'updated' ? 'success' : 'error', match ($result) {
            'updated' => 'Subject renamed.',
            'duplicate' => 'Another subject already uses that name.',
            'invalid' => 'Enter a subject name of up to 120 characters.',
            default => 'Subject not found.',
        });
        return Response::redirect(url('/admin/subjects'));
    }

    /** Detach a subject from one class without deleting the subject itself. */
    public function unassign(Request $r): Response
    {
        if ($x = $this->denied()) { return $x; }
        $result = $this->repo()->unassignSubject((int) $r->input('class_id'), (int) $r->route('id'), $this->instituteId());
        Flash::add($result === 'removed' ? 'success' : 'error', match ($result) {
            'removed' => 'Subject removed from the class.',
            'in_use' => 'This subject already has exams, tests or homework in that class. Remove those first.',
            default => 'Subject or class not found.',
        });
        return Response::redirect(url('/admin/subjects'));
    }

    public function delete(Request $r): Response
    {
        if ($x = $this->denied()) { return $x; }
        $result = $this->repo()->deleteSubject((int) $r->route('id'), $this->instituteId());
        Flash::add($result === 'deleted' ? 'success' : 'error', match ($result) {
            'deleted' => 'Subject deleted.',
            'in_use' => 'This subject is still assigned to a class or has academic records, so it cannot be deleted.',
            default => 'Subject not found.',
        });
        return Response::redirect(url('/admin/subjects'));
    }
}
