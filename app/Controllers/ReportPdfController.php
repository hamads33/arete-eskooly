<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller,Request,Response};
use App\Repositories\{AccountRepository,AttendanceRepository,EmployeeRepository,FeeRepository,OnlineTestRepository,ReportTemplateRepository,SettingsRepository,StudentRepository,SupportRepository,WorkflowRepository};
use App\Services\Pdf\{PdfService,ReportFilenameService,ReportTemplateService};

final class ReportPdfController extends Controller
{
    private function institute():array{return(new SettingsRepository($this->app->database()))->institute();}
    private function template(int $institute,string $type):array{$service=new ReportTemplateService();return array_replace($service->defaults($type),(new ReportTemplateRepository($this->app->database()))->find($institute,$type)??[]);}
    private function pdf(Request $r,string $type,string $filename,array $data,string $view='document'):Response
    {
        if ($type === 'report-card' && str_starts_with($filename, 'class-test-')) {
            $type = 'class-test-marksheet';
        } elseif ($type === 'report-card' && str_starts_with($filename, 'exam-')) {
            $type = 'exam-gazette';
        }
        $institute=$this->institute();
        $template=$this->template((int)$institute['id'],$type);
        // The admin's choice from the print-layout dialog: portrait = classic detailed, landscape = compact list.
        $layout=(string)$r->query('layout','');
        if(in_array($layout,['portrait','landscape'],true))$template['page_orientation']=$layout==='landscape'?'L':'P';
        if ($view === 'fee-vouchers' && ($data['copies'] ?? '') === 'three_copy') $template['page_orientation'] = 'L';
        return(new PdfService())->render($view,$data+['institute'=>$institute,'type'=>$type,'templateService'=>new ReportTemplateService(),'placeholders'=>$data['placeholders']??['institute_name'=>$institute['name']]],$template,(new ReportFilenameService())->make($filename),$r->query('download')==='1');
    }
    private function missing(string $message='Record not found.'):Response{return Response::html(e($message),404);}
    private function money(mixed $value,array $institute):string{return($institute['currency_symbol']??$institute['currency']??'').' '.number_format((float)$value,2);}

    public function admissionLetter(Request $r):Response{$i=$this->institute();$s=(new StudentRepository($this->app->database()))->find((int)$r->route('student'),(int)$i['id']);if(!$s)return$this->missing('Student not found.');$rules=(new SettingsRepository($this->app->database()))->rules((int)$i['id']);return$this->pdf($r,'admission-letter','admission-letter-'.$s['registration_no'],['meta'=>['Admission Date'=>$s['date_of_admission']??'','Registration No.'=>$s['registration_no'],'Student Name'=>$s['student_name'],'Father / Guardian'=>$s['father_name']??'','Class'=>$s['class_name'],'Contact'=>$s['mobile']??''],'intro'=>'<h2>Student Rules &amp; Regulations</h2>'.($rules['student']??'<p>No rules have been configured.</p>'),'signatures'=>['Parent / Guardian','Administrator'],'placeholders'=>['student_name'=>$s['student_name'],'registration_no'=>$s['registration_no'],'class_name'=>$s['class_name'],'father_name'=>$s['father_name']??'','institute_name'=>$i['name']]]);}
    public function jobLetter(Request $r):Response{$i=$this->institute();$e=(new EmployeeRepository($this->app->database()))->find((int)$r->route('employee'),(int)$i['id']);if(!$e)return$this->missing('Employee not found.');$rules=(new SettingsRepository($this->app->database()))->rules((int)$i['id']);return$this->pdf($r,'job-letter','job-letter-'.$e['employee_no'],['meta'=>['Employee No.'=>$e['employee_no'],'Employee Name'=>$e['name'],'Designation'=>$e['role'],'Joining Date'=>$e['joining_date']??'','Monthly Salary'=>$this->money($e['monthly_salary']??0,$i)],'intro'=>'<p>We are pleased to confirm your appointment with the institute under the terms recorded below.</p><h2>Employee Rules &amp; Regulations</h2>'.($rules['employee']??'<p>No rules have been configured.</p>'),'signatures'=>['Employee Signature','Authorized Signature'],'placeholders'=>['employee_name'=>$e['name'],'employee_no'=>$e['employee_no'],'role'=>$e['role'],'joining_date'=>$e['joining_date']??'','institute_name'=>$i['name']]]);}
    public function salarySlip(Request $r):Response{$i=$this->institute();$x=(new WorkflowRepository($this->app->database()))->salarySlip((int)$r->route('payment'),(int)$i['id']);if(!$x)return$this->missing('Salary payment not found.');return$this->pdf($r,'salary-slip','salary-slip-'.$x['employee_no'].'-'.$x['salary_month'],['meta'=>['Employee'=>$x['employee_no'].' - '.$x['name'],'Designation'=>$x['role'],'Salary Month'=>$x['salary_month'],'Base Salary'=>$this->money($x['base_salary'],$i),'Adjustment'=>$this->money($x['adjustment_amount'],$i),'Paid Amount'=>$this->money($x['paid_amount'],$i),'Payment Date'=>$x['payment_date'],'Reference'=>$x['reference']?:'-','Remarks'=>$x['remarks']?:'-'],'signatures'=>['Employee Signature','Authorized Signature'],'placeholders'=>['employee_name'=>$x['name'],'employee_no'=>$x['employee_no'],'salary_month'=>$x['salary_month'],'amount_paid'=>$this->money($x['paid_amount'],$i),'institute_name'=>$i['name']]]);}
    public function salarySheet(Request $r):Response{$i=$this->institute();$month=(string)$r->query('month',date('Y-m'));$rows=array_values(array_filter((new WorkflowRepository($this->app->database()))->salaryPayments((int)$i['id']),fn($x)=>$x['salary_month']===$month));foreach($rows as&$x){$x['base_salary']=$this->money($x['base_salary'],$i);$x['adjustment_amount']=$this->money($x['adjustment_amount'],$i);$x['paid_amount']=$this->money($x['paid_amount'],$i);}return$this->pdf($r,'salary-sheet','salary-sheet-'.$month,['meta'=>['Salary Month'=>$month],'columns'=>['Employee No.'=>'employee_no','Employee'=>'name','Base Salary'=>'base_salary','Adjustment'=>'adjustment_amount','Paid'=>'paid_amount','Payment Date'=>'payment_date'],'rows'=>$rows,'summary'=>'Payments: '.count($rows)]);}
    /** Fee Defaulters as a compact landscape list: one line per student, same filters as the screen. */
    public function feeDefaulters(Request $r):Response
    {
        $i=$this->institute();$db=$this->app->database();
        $f=FeesController::defaulterFilters($r);
        $list=(new FeeRepository($db))->defaulters((int)$i['id'],$f);
        $short=static fn(string $ym):string=>date('M y',strtotime($ym.'-01'));
        $rows=[];$due=0.0;$overdue=0.0;
        foreach($list as $n=>$x){
            $due+=$x['due'];$overdue+=$x['overdue'];
            $rows[]=['sr'=>(string)($n+1),'student'=>$x['student_name'],'reg'=>$x['registration_no'],'class'=>$x['class_name'],'guardian'=>$x['guardian']?:'',
                'phone'=>$x['phone']?:'','months'=>implode(', ',array_map(static fn($m)=>$short($m['month']).(!empty($m['not_billed'])?'*':''),$x['months'])),
                'count'=>(string)count($x['months']),'overdue'=>$x['overdue']>0?number_format($x['overdue'],0):'',
                'due'=>number_format($x['due'],0),'last'=>$x['last_payment']?date('d-m-Y',strtotime($x['last_payment'])):'Never'];
        }
        $className='';
        if($f['class'])$className=(string)$db->query('SELECT name FROM classes WHERE id=:c AND institute_id=:i',['c'=>$f['class'],'i'=>(int)$i['id']])->fetchColumn();
        $cur=trim((string)($i['currency_symbol']??$i['currency']??'Rs'));
        $meta=array_filter(['Fee Month'=>$f['month']?date('F Y',strtotime($f['month'].'-01')):'All months','Class'=>$className?:'All classes',
            'Show'=>['overdue'=>'Overdue only','partial'=>'Partly paid','unpaid'=>'Nothing paid','unbilled'=>'Not billed yet'][$f['state']]??'All defaulters',
            'Search'=>$f['q'],'Minimum Due'=>$f['minimum']>0?number_format($f['minimum'],0):'','Amounts in'=>$cur],static fn($v)=>$v!=='');
        $summary=count($rows).' defaulter'.(count($rows)===1?'':'s').'   ·   Total due '.$cur.' '.number_format($due,0).'   ·   Overdue '.$cur.' '.number_format($overdue,0)
            .($f['include_unbilled']?'   ·   * not billed yet (expected fee)':'');
        return$this->pdf($r,'fee-defaulters','fee-defaulters-'.($f['month']?:date('Y-m-d')),['meta'=>$meta,'dense'=>true,
            'columns'=>['#'=>'sr','Student'=>'student','Reg. No'=>'reg','Class'=>'class','Father / Guardian'=>'guardian','Phone'=>'phone','Months Due'=>'months','Months'=>'count','Overdue'=>'overdue','Total Due'=>'due','Last Paid'=>'last'],
            'rows'=>$rows,'summary'=>$summary]);
    }
    public function feeReceipt(Request $r):Response{$i=$this->institute();$repo=new FeeRepository($this->app->database());$x=$repo->receipt((int)$r->route('payment'),(int)$i['id']);if(!$x)return$this->missing('Fee receipt not found.');$items=$repo->items((int)$x['fee_invoice_id']);foreach($items as&$item)$item['amount']=$this->money($item['amount'],$i);$reversed=!empty($x['reversed_at']);return$this->pdf($r,'fee-receipt','fee-receipt-'.$x['receipt_no'],['notice'=>$reversed?'REVERSED on '.date('d M Y',strtotime((string)$x['reversed_at'])).(!empty($x['reversal_reason'])?' — '.$x['reversal_reason']:'').'. This receipt is cancelled and is not proof of payment.':'','meta'=>($reversed?['Status'=>'Reversed — cancelled']:[])+['Receipt No.'=>$x['receipt_no'],'Payment Date'=>date('d M Y',strtotime((string)$x['payment_date'])),'Student'=>$x['student_name'],'Registration'=>$x['registration_no'],'Class'=>$x['class_name'],'Fee Month'=>date('F Y',strtotime($x['fee_month'].'-01')),'Payment Method'=>$x['payment_method'],'Reference'=>$x['reference']?:'-','Amount Paid'=>$this->money($x['payment_amount'],$i),'Remaining Balance'=>$this->money($x['remaining_amount'],$i),'Received By'=>$x['received_by_name']],'columns'=>['Particular'=>'particular','Amount'=>'amount'],'rows'=>$items,'summary'=>'Invoice total '.$this->money($x['total_amount'],$i).'   ·   Paid on this receipt '.$this->money($x['payment_amount'],$i).'   ·   Balance '.$this->money($x['remaining_amount'],$i),'signatures'=>['Accounts Office'],'placeholders'=>['student_name'=>$x['student_name'],'registration_no'=>$x['registration_no'],'receipt_no'=>$x['receipt_no'],'amount_paid'=>$this->money($x['payment_amount'],$i),'institute_name'=>$i['name']]]);}
    public function feeInvoice(Request $r): Response
    {
        $i=$this->institute();
        $id=(int)$r->route('invoice');
        $x=$this->app->database()->query('SELECT fi.*,s.student_name,s.registration_no,s.father_name,c.name class_name FROM fee_invoices fi JOIN students s ON s.id=fi.student_id JOIN classes c ON c.id=s.class_id WHERE fi.id=:id AND s.institute_id=:i',['id'=>$id,'i'=>(int)$i['id']])->fetch();
        if(!$x)return $this->missing('Fee invoice not found.');
        $vs=new \App\Services\FeeVoucherService($this->app->database());$settings=$vs->settings((int)$i['id']);
        $copies=in_array($settings['copies'],['single','student_institute','three_copy'],true)?$settings['copies']:'single';
        $vouchers=[['student'=>$x,'invoice'=>$x,'items'=>(new FeeRepository($this->app->database()))->items($id)]];
        return FeeVouchersController::pdf($this->app,$i,$vouchers,$copies,[],$vs->banksFor((int)$i['id']),$settings['default_fine'],'fee-invoice-'.$x['invoice_no'].'.pdf',(string)$r->query('download')==='1');
    }
    public function reportCard(Request $r):Response{$i=$this->institute();$student=(new StudentRepository($this->app->database()))->find((int)$r->route('student'),(int)$i['id']);$examId=(int)$r->query('exam');$exam=$this->app->database()->query('SELECT * FROM exams WHERE id=:e AND institute_id=:i',['e'=>$examId,'i'=>(int)$i['id']])->fetch();if(!$student||!$exam)return$this->missing('Report card record not found.');$result=(new WorkflowRepository($this->app->database()))->studentResult((int)$i['id'],$examId,(int)$student['id']);return$this->pdf($r,'report-card','report-card-'.$student['registration_no'].'-'.$exam['name'],['meta'=>['Student'=>$student['student_name'],'Registration'=>$student['registration_no'],'Class'=>$student['class_name'],'Exam / Term'=>$exam['name'],'Percentage'=>number_format((float)$result['percentage'],2).' %','Grade'=>$result['grade']['grade']??'-','Status'=>$result['grade']['status']??'-'],'columns'=>['Subject'=>'subject','Obtained'=>'obtained_marks','Total'=>'total_marks'],'rows'=>$result['subjects'],'summary'=>'Total: '.$result['obtained'].' / '.$result['total'],'signatures'=>['Class Teacher','Principal']]);}
    public function certificate(Request $r):Response{$i=$this->institute();$x=$this->app->database()->query('SELECT c.*,COALESCE(s.student_name,e.name) recipient_name FROM certificates c LEFT JOIN students s ON s.id=c.student_id LEFT JOIN employees e ON e.id=c.employee_id WHERE c.id=:id AND c.institute_id=:i',['id'=>(int)$r->route('certificate'),'i'=>(int)$i['id']])->fetch();if(!$x)return$this->missing('Certificate not found.');return$this->pdf($r,'certificate','certificate-'.$x['id'],['record'=>$x,'placeholders'=>['recipient_name'=>$x['recipient_name'],'achievement'=>$x['achievement']??'','certificate_date'=>$x['certificate_date'],'institute_name'=>$i['name']]],'certificate');}
    public function studentAttendance(Request $r):Response{$i=$this->institute();$from=(string)$r->query('from',date('Y-m-01'));$to=(string)$r->query('to',date('Y-m-d'));$rows=(new AttendanceRepository($this->app->database()))->studentRecords((int)$i['id'],$from,$to,(int)$r->query('class',0));return$this->pdf($r,'student-attendance','student-attendance-'.$from.'-'.$to,['meta'=>['Period'=>$from.' to '.$to],'columns'=>['Date'=>'attendance_date','Registration'=>'registration_no','Student'=>'student_name','Class'=>'class_name','Status'=>'status','Remarks'=>'remarks'],'rows'=>$rows]);}
    public function employeeAttendance(Request $r):Response{$i=$this->institute();$from=(string)$r->query('from',date('Y-m-01'));$to=(string)$r->query('to',date('Y-m-d'));$rows=(new AttendanceRepository($this->app->database()))->employeeRecords((int)$i['id'],$from,$to);return$this->pdf($r,'employee-attendance','employee-attendance-'.$from.'-'.$to,['meta'=>['Period'=>$from.' to '.$to],'columns'=>['Date'=>'attendance_date','Employee No.'=>'employee_no','Employee'=>'name','Role'=>'role','Status'=>'status','Time In'=>'time_in','Time Out'=>'time_out'],'rows'=>$rows]);}
    public function studentBasicList(Request $r):Response{$i=$this->institute();$rows=(new StudentRepository($this->app->database()))->basicList((int)$i['id'],trim((string)$r->query('search','')),(int)$r->query('class',0)?:null);foreach($rows as$n=>&$x){$x['sr']=$n+1;$x['fee_remaining']=$this->money($x['fee_remaining'],$i);}return$this->pdf($r,'student-basic-list','student-basic-list',['columns'=>['SR'=>'sr','REG #'=>'registration_no','Student Name'=>'student_name','Father Name'=>'father_name','Class'=>'class_name','Fee Remaining'=>'fee_remaining','Phone'=>'mobile'],'rows'=>$rows]);}
    public function admissions(Request $r):Response
    {
        $i=$this->institute();
        $search=trim((string)$r->query('search',''));
        $class=(int)$r->query('class',0);
        $data=(new SupportRepository($this->app->database()))->students((int)$i['id'],$search,$class,1,500);
        foreach($data['rows'] as $n=>&$row){
            $row['sr']=$n+1;
            $row['discount_display']=number_format((float)($row['discount_in_fee']??0),2).'%';
            $row['age']=!empty($row['date_of_birth'])?(string)(new \DateTimeImmutable((string)$row['date_of_birth']))->diff(new \DateTimeImmutable('today'))->y:'—';
        }
        unset($row);
        $className='All classes';
        if($class){foreach((new AcademicRepository($this->app->database()))->classes((int)$i['id']) as $candidate){if((int)$candidate['id']===$class){$className=(string)$candidate['name'];break;}}}
        return$this->pdf($r,'admissions','admission-details',[
            'meta'=>['Class'=>$className,'Search'=>$search!==''?$search:'All students'],
            'columns'=>['SR'=>'sr','REG #'=>'registration_no','Student'=>'student_name','Father / Guardian'=>'father_name','Class'=>'class_name','Discount'=>'discount_display','Admission Date'=>'date_of_admission','Date of Birth'=>'date_of_birth','Age'=>'age','Gender'=>'gender','Status'=>'status'],
            'rows'=>$data['rows'],
            'summary'=>'Students: '.count($data['rows']),
        ]);
    }
    public function contacts(Request $r):Response{return$this->studentDirectory($r,'contacts','parent-contacts',['REG #'=>'registration_no','Student'=>'student_name','Class'=>'class_name','Father / Guardian'=>'father_name','Father Mobile'=>'father_mobile','Mother Name'=>'mother_name','Mother Mobile'=>'mother_mobile']);}
    private function studentDirectory(Request $r,string$type,string$file,array$columns):Response{$i=$this->institute();$data=(new SupportRepository($this->app->database()))->students((int)$i['id'],trim((string)$r->query('search','')),(int)$r->query('class',0),1,500);return$this->pdf($r,$type,$file,['columns'=>$columns,'rows'=>$data['rows']]);}
    public function accountStatement(Request $r):Response{$i=$this->institute();$from=(string)$r->query('from_date',date('Y-m-01'));$to=(string)$r->query('to_date',date('Y-m-d'));$rows=(new AccountRepository($this->app->database()))->statement((int)$i['id'],$from,$to,trim((string)$r->query('search','')));$balance=0;$income=0;$expense=0;foreach($rows as&$x){$amount=(float)$x['amount'];if($x['transaction_type']==='income'){$income+=$amount;$balance+=$amount;$x['income']=$this->money($amount,$i);$x['expense']='';}else{$expense+=$amount;$balance-=$amount;$x['expense']=$this->money($amount,$i);$x['income']='';}$x['balance']=$this->money($balance,$i);}return$this->pdf($r,'account-statement','account-statement-'.$from.'-'.$to,['meta'=>['Period'=>$from.' to '.$to],'columns'=>['Date'=>'transaction_date','Description'=>'description','Expense'=>'expense','Income'=>'income','Balance'=>'balance','Reference'=>'reference_type'],'rows'=>$rows,'summary'=>'Income '.$this->money($income,$i).' | Expense '.$this->money($expense,$i).' | Closing Balance '.$this->money($balance,$i)]);}
    public function classTestMarkSheet(Request$r):Response{$i=$this->institute();$repo=new WorkflowRepository($this->app->database());$test=$repo->classTest((int)$i['id'],(int)$r->route('test'));if(!$test)return$this->missing('Class test not found.');$rows=$repo->classTestMarkSheet((int)$i['id'],(int)$test['id']);$scores=[];$pass=0;$fail=0;$absent=0;$passing=$test['passing_marks']!==null?(float)$test['passing_marks']:(float)$test['total_marks']*.4;foreach($rows as$n=>&$x){$x['sr']=$n+1;if((int)$x['is_absent']){$x['display_marks']='ABS';$x['percentage']='—';$x['result']='Absent';$absent++;}elseif($x['obtained_marks']===null){$x['display_marks']='—';$x['percentage']='—';$x['result']='Not entered';}else{$score=(float)($x['obtained_marks']??0);$scores[]=$score;$pct=(float)$test['total_marks']>0?$score/(float)$test['total_marks']*100:0;$x['display_marks']=number_format($score,2);$x['percentage']=number_format($pct,2).'%';$x['result']=$score>=$passing?'Pass':'Fail';$x['result']==='Pass'?$pass++:$fail++;}}$stats=$scores?'Highest '.number_format(max($scores),2).' | Lowest '.number_format(min($scores),2).' | Average '.number_format(array_sum($scores)/count($scores),2):'No scored records';return$this->pdf($r,'report-card','class-test-'.$test['id'].'-marksheet',['meta'=>['Test'=>$test['title'],'Type'=>ucwords(str_replace('_',' ',$test['test_type']??'class_test')),'Class'=>$test['class_name'],'Subject'=>$test['subject_name'],'Date'=>$test['test_date'],'Total Marks'=>$test['total_marks']],'columns'=>['SR'=>'sr','Registration'=>'registration_no','Student'=>'student_name','Marks'=>'display_marks','Percentage'=>'percentage','Status'=>'result','Remarks'=>'remarks'],'rows'=>$rows,'summary'=>$stats.' | Pass '.$pass.' | Fail '.$fail.' | Absent '.$absent,'signatures'=>['Subject Teacher','Administrator']]);}
    /** Online test results, class-wide or for one student when ?student= is given, for the Results Center's "Download PDF". */
    public function onlineTestResults(Request $r): Response
    {
        $i = $this->institute();
        $testId = (int) $r->route('id');
        $repo = new OnlineTestRepository($this->app->database());
        $test = $repo->find((int) $i['id'], $testId);
        if (!$test) return $this->missing('Test not found.');
        $studentId = (int) $r->query('student', 0);
        $rows = $repo->submissions((int) $i['id'], $testId);
        // Students of the class who never opened the test: Absent once it has closed, otherwise not attempted yet.
        $closed = $test['status'] === 'closed' || (!empty($test['closes_at']) && $test['closes_at'] < date('Y-m-d H:i:s'));
        $attempted = array_flip(array_map('intval', array_column($rows, 'student_id')));
        foreach ($this->app->database()->query("SELECT id student_id,student_name,registration_no FROM students WHERE class_id=:c AND institute_id=:i AND status='active' ORDER BY student_name,registration_no", ['c' => (int) $test['class_id'], 'i' => (int) $i['id']])->fetchAll() as $s) {
            if (!isset($attempted[(int) $s['student_id']])) $rows[] = $s + ['status' => $closed ? 'absent' : 'not_attempted', 'total_score' => null, 'published_at' => null, 'visible_to' => null];
        }
        if ($studentId) $rows = array_values(array_filter($rows, fn($x) => (int) $x['student_id'] === $studentId));
        foreach ($rows as &$x) {
            $x['marks_display'] = $x['status'] === 'marked' && $x['total_score'] !== null ? number_format((float) $x['total_score'], 2) : ($x['status'] === 'submitted' ? 'Pending' : ($x['status'] === 'in_progress' ? 'In progress' : ($x['status'] === 'absent' ? 'Absent' : ($x['status'] === 'not_attempted' ? 'Not attempted yet' : '—'))));
            if (in_array($x['status'], ['absent', 'not_attempted'], true)) {
                $x['publish_status'] = $x['status'] === 'absent' ? 'Did not attempt' : '—';
                continue;
            }
            $x['publish_status'] = $x['published_at'] ? ('Published — ' . (['student' => 'Student', 'parent' => 'Parent', 'both' => 'Student & Parent'][$x['visible_to']] ?? 'Student')) : 'Not published';
        }
        unset($x);
        $totalMarks = $this->app->database()->query('SELECT SUM(marks) FROM online_test_questions WHERE test_id=:t', ['t' => $testId])->fetchColumn();
        $filenameSuffix = $studentId && $rows ? '-' . $rows[0]['registration_no'] : '';
        return $this->pdf($r, 'report-card', 'online-test-' . $testId . '-results' . $filenameSuffix, [
            'meta' => ['Test' => $test['title'], 'Class' => $test['class_name'], 'Subject' => $test['subject_name'] ?: '—', 'Total Marks' => $totalMarks !== false && $totalMarks !== null ? number_format((float) $totalMarks, 2) : '—'],
            'columns' => ['Registration' => 'registration_no', 'Student' => 'student_name', 'Marks' => 'marks_display', 'Status' => 'publish_status'],
            'rows' => $rows,
            'summary' => 'Students: ' . count($rows) . ' | Absent: ' . count(array_filter($rows, static fn($x) => $x['status'] === 'absent')),
            'signatures' => ['Subject Teacher', 'Administrator'],
        ]);
    }

    public function classExamResult(Request$r):Response{$i=$this->institute();$exam=(int)$r->route('exam');$class=(int)$r->route('class');$examRow=$this->app->database()->query('SELECT e.*,c.name class_name FROM exams e JOIN classes c ON c.id=:c AND c.institute_id=e.institute_id WHERE e.id=:e AND e.institute_id=:i',['c'=>$class,'e'=>$exam,'i'=>(int)$i['id']])->fetch();if(!$examRow)return$this->missing('Exam or class not found.');$rows=(new WorkflowRepository($this->app->database()))->classResults((int)$i['id'],$exam,$class);foreach($rows as$n=>&$x){$x['sr']=$n+1;$abs=(int)($x['absent_subjects']??0);if($abs>0&&$abs===(int)$x['subjects']){$x['obtained']='Absent';$x['percentage']='—';}else{$x['obtained']=($x['obtained']??0).($abs?' (absent in '.$abs.')':'');$x['percentage']=number_format((float)($x['percentage']??0),2).'%';}}return$this->pdf($r,'report-card','exam-'.$exam.'-class-'.$class.'-gazette',['meta'=>['Exam'=>$examRow['name'],'Class'=>$examRow['class_name'],'Session'=>$examRow['session_id']?:'Institute session'],'columns'=>['SR'=>'sr','Registration'=>'registration_no','Student'=>'student_name','Obtained'=>'obtained','Total'=>'total','Percentage'=>'percentage'],'rows'=>$rows,'summary'=>'Students: '.count($rows),'signatures'=>['Class Teacher','Principal']]);}
}
