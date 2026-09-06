<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{WorkflowRepository,SettingsRepository};use App\Services\SmsSettingsService;
final class CommunicationController extends AdminPageController
{
    public function messaging(Request $r): Response { $w=new WorkflowRepository($this->app->database());$uid=(int)user()['id'];$id=(int)$r->query('conversation',0);$active=$id?$w->conversation($this->i(),$id,$uid):null;if($id&&!$active)return Response::html('Conversation not found',404);return $this->renderPage('messaging',['conversations'=>$w->conversations($this->i(),$uid),'activeConversation'=>$active,'recipients'=>$w->messageRecipients($this->i(),$uid)]); }
    public function startConversation(Request$r):Response{$i=$this->i();try{$id=(new WorkflowRepository($this->app->database()))->createConversation($i,(int)user()['id'],(int)$r->input('recipient_id'),(string)$r->input('message'),(string)$r->input('subject')?:null);}catch(\DomainException$e){return Response::html(e($e->getMessage()),422);}return Response::redirect(url('/admin/communication/messaging?conversation='.$id));}
    public function sendMessage(Request$r):Response{try{(new WorkflowRepository($this->app->database()))->sendMessage($this->i(),(int)$r->route('id'),(int)user()['id'],(string)$r->input('message'));}catch(\DomainException$e){return Response::html(e($e->getMessage()),403);}return Response::redirect(url('/admin/communication/messaging?conversation='.$r->route('id')));}
    public function smsGateway(Request $r): Response { return $this->renderPage('sms-gateway'); }
    public function saveSms(Request$r):Response{try{(new SmsSettingsService($this->app->database(),getenv('APP_KEY')?:'local-development-key'))->save($this->i(),(string)$r->input('provider')?:null,(bool)$r->input('enabled'),['sender'=>(string)$r->input('sender')],(string)$r->input('secret')?:null,(int)user()['id']);}catch(\DomainException$e){return Response::html(e($e->getMessage()),422);}return Response::redirect(url('/admin/communication/sms-gateway'));}
    public function liveClass(Request $r): Response { return $this->renderPage('live-class'); }
    public function storeLiveClass(Request$r):Response{try{(new WorkflowRepository($this->app->database()))->createLiveClass($this->i(),$r->all(),(int)user()['id']);}catch(\DomainException$e){return Response::html(e($e->getMessage()),422);}return Response::redirect(url('/admin/communication/live-class'));}
    private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
}
