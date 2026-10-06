<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response,Validator};use App\Repositories\{AccountRepository,SettingsRepository};use App\Support\Flash;
final class AccountsController extends AdminPageController{
private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}private function r():AccountRepository{return new AccountRepository($this->app->database());}private function denied():?Response{return can('accounts.view')?null:Response::html('Forbidden',403);}public function chart(Request$r):Response{if($x=$this->denied())return$x;return$this->renderPage('accounts-chart',['heads'=>$this->r()->heads($this->i())]);}public function incomeCreate(Request$r):Response{if($x=$this->denied())return$x;return$this->renderPage('income-add',['heads'=>$this->r()->heads($this->i())]);}public function expenseCreate(Request$r):Response{if($x=$this->denied())return$x;return$this->renderPage('expense-add',['heads'=>$this->r()->heads($this->i())]);}
public function storeHead(Request$r):Response{if($x=$this->denied())return$x;$name=trim((string)$r->input('head'));$type=strtolower((string)$r->input('type'));if($name===''||strlen($name)>120||!in_array($type,['income','expense','both'],true)){Flash::add('error','Enter a valid account head and type.');return Response::redirect(url('/admin/accounts'));}if(!$this->r()->addHead($this->i(),$name,$type)){Flash::add('error','An account head with that name already exists.');return Response::redirect(url('/admin/accounts'));}Flash::add('success','Account head created.');return Response::redirect(url('/admin/accounts'));}
public function deleteHead(Request$r):Response{if($x=$this->denied())return$x;$result=$this->r()->deleteHead((int)$r->route('id'),$this->i());Flash::add($result==='deleted'?'success':'error',$result==='deleted'?'Account head deleted.':($result==='in_use'?'This account head has transactions and cannot be deleted.':'Account head not found.'));return Response::redirect(url('/admin/accounts'));}
public function store(Request$r):Response{if($x=$this->denied())return$x;$type=(string)$r->route('type');if(!in_array($type,['income','expense'],true))return Response::html('Invalid transaction type',422);$d=['date'=>(string)$r->input('expensedate'),'description'=>trim((string)$r->input('expensedes')),'amount'=>$r->input('expenseamt'),'reference'=>trim((string)$r->input('reference')),'account_head'=>(int)$r->input('account_head')];$v=new Validator();if(!$v->validate($d,['date'=>'required|date','description'=>'required|max:255','amount'=>'required|numeric|min:0.01','reference'=>'max:50','account_head'=>'required|integer'])){$this->app->session()->flash('_errors',$v->errors());$this->app->session()->flash('_old',$d);Flash::add('error','Please correct the transaction.');return Response::redirect(url('/admin/accounts/'.$type.'/create'));}try{$this->r()->create($this->i(),$type,$d['date'],$d['description'],(float)$d['amount'],(int)user()['id'],$d['reference']?:null,$d['account_head']);}catch(\InvalidArgumentException$e){Flash::add('error',$e->getMessage());return Response::redirect(url('/admin/accounts/'.$type.'/create'));}Flash::add('success',ucfirst($type).' recorded.');return Response::redirect(url('/admin/accounts/statement'));}
public function statement(Request$r):Response{
    if($x=$this->denied())return$x;
    $from=(string)$r->query('from_date',date('Y-m-01'));$to=(string)$r->query('to_date',date('Y-m-d'));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-01');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to=date('Y-m-d');
    if($from>$to)[$from,$to]=[$to,$from];
    $q=trim((string)$r->query('search',''));
    $source=(string)$r->query('source','');if(!in_array($source,['manual','fee','salary'],true))$source='';
    $head=(int)$r->query('head',0);
    $rows=$this->r()->ledger($this->i(),$from,$to,$source,$head,$q);
    $balance=0;$income=0;$expense=0;$bySource=['manual'=>['income'=>0.0,'expense'=>0.0],'fee'=>['income'=>0.0,'expense'=>0.0],'salary'=>['income'=>0.0,'expense'=>0.0]];
    foreach($rows as&$row){
        if($row['type']==='income'){$income+=(float)$row['amount'];$balance+=(float)$row['amount'];}else{$expense+=(float)$row['amount'];$balance-=(float)$row['amount'];}
        $bySource[$row['source']][$row['type']]+=(float)$row['amount'];
        $row['balance']=$balance;
    }
    return$this->renderPage('account-statement',[
        'transactions'=>$rows,'income'=>$income,'expense'=>$expense,'balance'=>$balance,'bySource'=>$bySource,
        'outstanding'=>$this->r()->outstandingCollection($this->i()),
        'from'=>$from,'to'=>$to,'search'=>$q,'source'=>$source,'head'=>$head,'heads'=>$this->r()->heads($this->i()),
        'feeCharges'=>$this->r()->feeChargesBilled($this->i(),substr($from,0,7),substr($to,0,7)),
    ]);
}
/** Today's (or any chosen day's) collection and spending, with a 14-day trend for context. */
public function daily(Request$r):Response{
    if($x=$this->denied())return$x;
    $date=(string)$r->query('date',date('Y-m-d'));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$date>date('Y-m-d'))$date=date('Y-m-d');
    $rows=$this->r()->ledger($this->i(),$date,$date,'',0,'');
    $income=0.0;$expense=0.0;$bySource=['manual'=>['income'=>0.0,'expense'=>0.0],'fee'=>['income'=>0.0,'expense'=>0.0],'salary'=>['income'=>0.0,'expense'=>0.0]];
    foreach($rows as$row){if($row['type']==='income')$income+=(float)$row['amount'];else$expense+=(float)$row['amount'];$bySource[$row['source']][$row['type']]+=(float)$row['amount'];}
    $trend=$this->r()->dailyTotals($this->i(),date('Y-m-d',strtotime($date.' -13 days')),$date);
    return$this->renderPage('accounts-daily',[
        'date'=>$date,'transactions'=>$rows,'income'=>$income,'expense'=>$expense,'bySource'=>$bySource,
        'outstanding'=>$this->r()->outstandingCollection($this->i()),'trend'=>$trend,
        'prevDate'=>date('Y-m-d',strtotime($date.' -1 day')),
        'nextDate'=>$date<date('Y-m-d')?date('Y-m-d',strtotime($date.' +1 day')):null,
        'isToday'=>$date===date('Y-m-d'),
    ]);
}
public function expenseReport(Request$r):Response{
    if($x=$this->denied())return$x;
    $month=(string)$r->query('month','');
    if(!preg_match('/^\d{4}-\d{2}$/',$month))$month='';
    $from=(string)$r->query('from',$month?:date('Y-m-01'));
    $to=(string)$r->query('to',$month?date('Y-m-t',strtotime($month.'-01')):date('Y-m-d'));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))$from=date('Y-m-01');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))$to=date('Y-m-d');
    if($from>$to)[$from,$to]=[$to,$from];
    $head=(int)$r->query('head',0);
    $q=trim((string)$r->query('q',''));
    $rows=$this->r()->expensesInRange($this->i(),$from,$to,$head,$q);
    $total=array_sum(array_map(static fn(array$row):float=>(float)$row['amount'],$rows));
    return$this->renderPage('expense-report',[
        'rows'=>$rows,'total'=>$total,'from'=>$from,'to'=>$to,'head'=>$head,'q'=>$q,
        'heads'=>$this->r()->heads($this->i()),'months'=>$this->r()->expenseMonths($this->i()),
    ]);
}

private function adminOnly():?Response{return can('*')?null:Response::html('Only an administrator can change or reverse posted transactions.',403);}
private function audit(string$action,string$type,int$id,array$meta):void{(new \App\Repositories\PakistanErpRepository($this->app->database()))->audit($this->i(),(int)user()['id'],$action,$type,$id,$meta);}
private function back(Request$r):Response{$to=(string)$r->input('return','');return Response::redirect(str_starts_with($to,'/admin/')?url($to):url('/admin/accounts/statement'));}
public function edit(Request$r):Response{if($x=$this->adminOnly())return$x;$t=$this->r()->find((int)$r->route('id'),$this->i());if(!$t)return Response::html('Transaction not found',404);return$this->renderPage('transaction-edit',['transaction'=>$t,'heads'=>$this->r()->heads($this->i()),'return'=>(string)$r->query('return','')]);}
public function update(Request$r):Response{if($x=$this->adminOnly())return$x;$id=(int)$r->route('id');$d=['date'=>(string)$r->input('date'),'description'=>trim((string)$r->input('description')),'amount'=>$r->input('amount'),'reference'=>trim((string)$r->input('reference')),'account_head'=>(int)$r->input('account_head')];$v=new Validator();if(!$v->validate($d,['date'=>'required|date','description'=>'required|max:255','amount'=>'required|numeric|min:0.01','reference'=>'max:50','account_head'=>'required|integer'])){Flash::add('error','Please correct the transaction: '.implode(' ',array_map(static fn($e)=>is_array($e)?implode(' ',$e):$e,$v->errors())));return Response::redirect(url('/admin/accounts/transactions/'.$id.'/edit'));}$before=$this->r()->find($id,$this->i());try{if(!$this->r()->update($id,$this->i(),$d['date'],$d['description'],(float)$d['amount'],$d['reference']?:null,$d['account_head']))return Response::html('Transaction not found',404);}catch(\InvalidArgumentException$e){Flash::add('error',$e->getMessage());return Response::redirect(url('/admin/accounts/transactions/'.$id.'/edit'));}$this->audit('account.transaction_updated','account_transaction',$id,['before'=>$before,'after'=>$d]);Flash::add('success','Transaction updated.');return$this->back($r);}
public function destroy(Request$r):Response{if($x=$this->adminOnly())return$x;$id=(int)$r->route('id');$t=$this->r()->delete($id,$this->i());if(!$t){Flash::add('error','Transaction not found.');return$this->back($r);}$this->audit('account.transaction_deleted','account_transaction',$id,['transaction'=>$t,'reason'=>trim((string)$r->input('reason'))]);Flash::add('success',ucfirst((string)$t['transaction_type']).' entry reversed and removed from the ledger.');return$this->back($r);}
public function reverseSalary(Request$r):Response{if($x=$this->adminOnly())return$x;$id=(int)$r->route('id');$reason=trim((string)$r->input('reason'));if($reason===''){Flash::add('error','Give a reason for reversing the salary payment.');return$this->back($r);}$p=$this->r()->reverseSalary($id,$this->i());if(!$p){Flash::add('error','Salary payment not found.');return$this->back($r);}$this->audit('salary.payment_reversed','salary_payment',$id,['payment'=>$p,'reason'=>$reason]);Flash::add('success','Salary payment reversed. The month can be paid again and any recovered advance is outstanding again.');return$this->back($r);}
}
