<?php
declare(strict_types=1);

use App\Core\{App, CSRF, Request};
use App\Repositories\{AcademicRepository, FeeRepository, SettingsRepository, StudentRepository};
use App\Services\{FeeVoucherService, StudentService};
use App\Services\Pdf\{PdfService, ReportTemplateService};

return static function(App $app): array {
    $checks=[];
    $db=$app->database();
    $institute=(new SettingsRepository($db))->institute();
    $i=(int)$institute['id'];
    $u=$db->query('SELECT u.id,u.name,u.email,u.status FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" ORDER BY u.id LIMIT 1')->fetch();
    $app->session()->set('auth_user',$u+['roles'=>['admin'],'permissions'=>['*']]);
    $suffix=bin2hex(random_bytes(4));
    $className='Voucher QA '.$suffix;
    $academic=new AcademicRepository($db);
    $academic->createClass($i,$className,1000,null);
    $class=(int)$db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n',['i'=>$i,'n'=>$className])->fetchColumn();
    $students=new StudentRepository($db);
    $studentService=new StudentService($students);
    $service=new FeeVoucherService($db);
    $fees=new FeeRepository($db);
    $ids=[];
    $get=static function(string $uri)use($app){parse_str((string)parse_url($uri,PHP_URL_QUERY),$q);return$app->run(new Request($q,[],[],['REQUEST_METHOD'=>'GET','REQUEST_URI'=>$uri]));};
    $post=static fn(array $body)=>$app->run(new Request([],$body+['_token'=>CSRF::token()],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/admin/fees/vouchers/pdf']));
    try {
        for($n=1;$n<=102;$n++) {
            $data=$studentService->data(['studentname'=>'Voucher Student '.$n,'reg'=>'VQ-'.$suffix.'-'.$n,'doa'=>date('Y-m-d'),'class'=>$class,'discount'=>10,'fname'=>'QA Parent'],$i);
            $data['created_at']=$data['updated_at'];
            $ids[]=$students->create($data);
        }
        $students->update($ids[101],$i,['status'=>'inactive','updated_at'=>date('Y-m-d H:i:s')]);
        $selected=$service->students($i,'class',$class,0);
        $checks['voucher class includes all 101 active students without truncation']=count($selected)===101;
        $all=$service->students($i,'all',0,0);
        $checks['voucher all includes complete class and excludes inactive']=count(array_intersect($ids,array_column($all,'id')))===101;
        $checks['voucher selection is institute scoped']=$service->students($i+999999,'student',0,$ids[0])===[];
        $checks['individual voucher can include an inactive student']=count($service->students($i,'student',0,$ids[101]))===1;
        try{$service->generate($i,'2026-13',$selected,(int)$u['id']);$checks['invalid voucher month rejected']=false;}catch(InvalidArgumentException){$checks['invalid voucher month rejected']=true;}
        try{$service->generate($i,'2026-10',$selected,(int)$u['id'],'2026-02-30');$checks['invalid voucher due date rejected']=false;}catch(InvalidArgumentException){$checks['invalid voucher due date rejected']=true;}
        $batch=$service->generate($i,'2026-10',$selected,(int)$u['id'],'2026-10-15');
        $checks['all selected invoices generated']=count($batch)===101;
        $checks['new voucher due date is persisted']=$batch[0]['invoice']['due_date']==='2026-10-15';
        $first=$batch[0]['invoice'];
        $payment=$fees->addPayment((int)$first['id'],(int)$batch[0]['student']['id'],100,'Cash','QA',(int)$u['id']);
        $fees->updateInvoice((int)$first['id'],100,(float)$first['total_amount']-100);
        $before=$fees->invoice((int)$batch[0]['student']['id'],'2026-10');
        $again=$service->generate($i,'2026-10',$selected,(int)$u['id'],'2026-10-25');
        $after=$fees->invoice((int)$batch[0]['student']['id'],'2026-10');
        $keep=static fn($x)=>array_diff_key((array)$x,['due_date'=>1]);$checks['repeated voucher generation retains invoice payment and applies the new due date']=$keep($before)===$keep($after)&&$after['due_date']==='2026-10-25'&&count($fees->payments((int)$first['id']))===1;$noDate=$service->generate($i,'2026-10',[$batch[0]['student']],(int)$u['id']);$checks['generating without a due date keeps the existing one']=$fees->invoice((int)$batch[0]['student']['id'],'2026-10')['due_date']==='2026-10-25';
        $checks['individual discount retained']=(float)$first['discount']>0;
        $db->query('UPDATE fee_invoices SET voided_at=:n WHERE id=:id',['n'=>date('Y-m-d H:i:s'),'id'=>$batch[1]['invoice']['id']]);
        $checks['voided invoices excluded']=count($service->generate($i,'2026-10',$selected,(int)$u['id']))===100;
        $response=$get('/admin/fees/collect?student_id='.$batch[0]['student']['id'].'&month=2026-10');
        $html=$response->content();
        $checks['collect retains voucher whatsapp history edit reversal and amount limit']=$response->status()===200&&str_contains($html,'whatsapp')&&str_contains($html,'Payment History')&&str_contains($html,'/payments/'.$payment.'/edit')&&str_contains($html,'/payments/'.$payment.'/reverse')&&str_contains($html,'max="'.$after['remaining_amount'].'"');
        $checks['voucher page renders all option']=$get('/admin/fees/vouchers')->status()===200;
        $checks['bulk voucher route validates csrf']=$app->run(new Request([],['_token'=>'bad'],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/admin/fees/vouchers/pdf']))->status()===419;
        $checks['foreign bank account refused']=$post(['scope'=>'student','student_id'=>$ids[0],'month'=>'2026-10','copies'=>'single','bank_id'=>9999999])->status()===422;
        $pdf=$post(['scope'=>'student','student_id'=>$batch[0]['student']['id'],'month'=>'2026-10','copies'=>'single','download'=>'1']);
        $checks['voucher download route returns PDF']=$pdf->status()===200&&str_starts_with($pdf->content(),'%PDF-')&&str_starts_with($pdf->headers()['Content-Disposition']??'','attachment;');
        $templates=new ReportTemplateService();
        $data=['institute'=>$institute,'type'=>'fee-invoice','templateService'=>$templates,'placeholders'=>['institute_name'=>$institute['name']],'vouchers'=>array_slice($again,0,2),'copies'=>'three_copy','banks'=>[]];
        $template=$templates->defaults('fee-invoice');$template['page_orientation']='L';
        $pdf=(new PdfService())->render('fee-vouchers',$data,$template,'voucher-qa.pdf');
        $checks['combined voucher PDF renders multiple students and copies']=str_starts_with($pdf->content(),'%PDF-');
        if(getenv('FEE_VOUCHER_QA_OUTPUT'))file_put_contents(getenv('FEE_VOUCHER_QA_OUTPUT'),$pdf->content());
        $app->session()->set('auth_user',$u+['roles'=>['staff'],'permissions'=>['fees.view']]);
        $gate=new \App\Middleware\AdminAreaMiddleware($app);
        $checks['read only fee role cannot generate vouchers']=$gate->handle(new Request([],[],[],['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/admin/fees/vouchers/pdf']),static fn()=>\App\Core\Response::html('Allowed'))->status()===403;
    } finally {
        foreach($ids as $id){$db->query('DELETE FROM fee_payments WHERE student_id=:id',['id'=>$id]);$db->query('DELETE FROM fee_invoices WHERE student_id=:id',['id'=>$id]);$db->query('DELETE FROM students WHERE id=:id',['id'=>$id]);}
        $db->query('DELETE FROM classes WHERE id=:id',['id'=>$class]);
    }
    return $checks;
};
