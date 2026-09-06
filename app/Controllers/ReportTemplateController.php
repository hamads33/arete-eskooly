<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};
use App\Repositories\{ReportTemplateRepository,SettingsRepository};
use App\Services\Pdf\ReportTemplateService;
use App\Services\Pdf\{PdfService,ReportFilenameService};
use App\Support\Flash;

final class ReportTemplateController extends AdminPageController
{
    private function instituteId():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
    private function allowed():?Response{return can('settings.view')?null:Response::html('Forbidden',403);}
    public function index(Request $r):Response{if($x=$this->allowed())return$x;$service=new ReportTemplateService();$type=(string)$r->query('type',array_key_first(ReportTemplateService::TYPES));if(!isset(ReportTemplateService::TYPES[$type]))$type=array_key_first(ReportTemplateService::TYPES);$saved=(new ReportTemplateRepository($this->app->database()))->find($this->instituteId(),$type);return$this->renderPage('report-templates',['types'=>ReportTemplateService::TYPES,'selectedType'=>$type,'template'=>array_replace($service->defaults($type),$saved??[]),'placeholders'=>$service->placeholders($type)]);}
    public function save(Request $r):Response{if($x=$this->allowed())return$x;$type=(string)$r->route('type');try{$data=(new ReportTemplateService())->validate($type,$r->all());}catch(\InvalidArgumentException $e){Flash::add('error',$e->getMessage());return Response::redirect(url('/admin/settings/report-templates?type='.rawurlencode($type)));}(new ReportTemplateRepository($this->app->database()))->save($this->instituteId(),$type,$data,(int)user()['id']);Flash::add('success','Report template saved.');return Response::redirect(url('/admin/settings/report-templates?type='.rawurlencode($type)));}
    public function reset(Request $r):Response{if($x=$this->allowed())return$x;$type=(string)$r->route('type');if(!isset(ReportTemplateService::TYPES[$type]))return Response::html('Unknown report type',422);(new ReportTemplateRepository($this->app->database()))->reset($this->instituteId(),$type);Flash::add('success','Report template reset to default.');return Response::redirect(url('/admin/settings/report-templates?type='.rawurlencode($type)));}
    public function preview(Request $r):Response
    {
        if($x=$this->allowed())return$x;
        $type=(string)$r->route('type');$templates=new ReportTemplateService();
        if(!isset(ReportTemplateService::TYPES[$type]))return Response::html('Unknown report type',404);
        $institute=(new SettingsRepository($this->app->database()))->institute();
        $saved=(new ReportTemplateRepository($this->app->database()))->find((int)$institute['id'],$type);
        $template=array_replace($templates->defaults($type),$saved??[]);
        $data=['institute'=>$institute,'generatedAt'=>date('d M Y, h:i A'),'intro'=>'','meta'=>['Reference'=>'PREVIEW-001','Prepared for'=>'Sample Student','Session'=>date('Y')],'columns'=>['Item'=>'item','Description'=>'description','Status'=>'status'],'rows'=>[['item'=>'1','description'=>'Representative report content','status'=>'Verified'],['item'=>'2','description'=>'Official institute record','status'=>'Active']],'signatures'=>['Prepared By','Authorized Signature'],'type'=>$type,'templateService'=>$templates,'placeholders'=>['institute_name'=>$institute['name']]];
        $view=$type==='certificate'?'certificate':'document';
        if($view==='certificate')$data+=['record'=>['id'=>'PREVIEW-001','recipient_name'=>'Sample Student','achievement'=>'Presented in recognition of exemplary performance and commitment to learning.','certificate_date'=>date('Y-m-d')]];
        return(new PdfService())->render($view,$data,$template,(new ReportFilenameService())->make('preview',[$type]),(string)$r->query('download','')==='1');
    }
}
