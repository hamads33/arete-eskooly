<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{WorkflowRepository,SettingsRepository,EmployeeRepository};use App\Support\Flash;
final class SalaryController extends AdminPageController
{
    private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
    public function pay(Request $r): Response { $w=new WorkflowRepository($this->app->database());return $this->renderPage('salary-pay',['employees'=>(new EmployeeRepository($this->app->database()))->all($this->i()),'payments'=>$w->salaryPayments($this->i(),(int)$r->query('employee_id',0))]); }
    public function store(Request$r):Response{try{$id=(new WorkflowRepository($this->app->database()))->paySalary($this->i(),(int)$r->input('employee_id'),(string)$r->input('salary_month'),(float)$r->input('paid_amount'),(string)$r->input('payment_date'),(int)user()['id'],(float)$r->input('adjustment_amount',0),(string)$r->input('reference')?:null,(string)$r->input('remarks')?:null);}catch(\DomainException$e){return Response::html(e($e->getMessage()),422);}Flash::add('success','Salary payment saved.');return Response::redirect(url('/admin/salary/receipt/'.$id));}
    public function receipt(Request $r): Response {$id=(int)$r->route('id',(int)$r->query('id',0));$w=new WorkflowRepository($this->app->database());$slip=$id?$w->salarySlip($id,$this->i()):null;if($id&&!$slip)return Response::html('Salary payment not found',404);return $this->renderPage('salary-receipt',['slip'=>$slip,'payments'=>$w->salaryPayments($this->i())]); }
}
