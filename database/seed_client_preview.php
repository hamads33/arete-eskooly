<?php
declare(strict_types=1);

use App\Repositories\{AccountRepository,AcademicRepository,AttendanceRepository,EmployeeRepository,FeeRepository,SettingsRepository,StudentRepository,WorkflowRepository};
use App\Services\{AttendanceService,FeeService,StudentService};

if (($argv[1] ?? '') !== '--seed') { fwrite(STDERR, "Run with --seed\n"); exit(1); }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/vendor/autoload.php';
$app=require BASE_PATH.'/bootstrap/app.php';
$db=$app->database();$pdo=$db->connection();$now=date('Y-m-d H:i:s');$today=date('Y-m-d');
$institute=(int)(new SettingsRepository($db))->institute()['id'];
$admin=(int)$db->query('SELECT user_id FROM institute_users WHERE institute_id=:i ORDER BY user_id LIMIT 1',['i'=>$institute])->fetchColumn();
if(!$admin)$admin=(int)$db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
$class=$db->query('SELECT * FROM classes WHERE institute_id=:i ORDER BY monthly_tuition DESC,id LIMIT 1',['i'=>$institute])->fetch();
if(!$admin)throw new RuntimeException('An institute user account is required.');
if(!$class){$db->query("INSERT INTO classes(institute_id,name,monthly_tuition,teacher_reference,created_at,updated_at) VALUES(:i,'Demo Grade 6',1000,NULL,:c,:u)",['i'=>$institute,'c'=>$now,'u'=>$now]);$class=$db->query('SELECT * FROM classes WHERE id=:id',['id'=>(int)$pdo->lastInsertId()])->fetch();}

$students=new StudentRepository($db);$student=$students->byRegistration('DEMO-2026-001',$institute);
if(!$student){$service=new StudentService($students);$id=$service->create($service->data(['studentname'=>'Areeba Khan (Demo)','reg'=>'DEMO-2026-001','doa'=>$today,'class'=>$class['id'],'discount'=>10,'Gphone'=>'0300 1234567','dob'=>'2013-04-18','gender'=>'Female','blood'=>'B+','scnic'=>'61101-DEMO-001','religion'=>'Islam','address'=>'Islamabad, Pakistan','fname'=>'Imran Khan (Demo)','fmobile'=>'0300 7654321','fo'=>'Engineer','mname'=>'Sara Khan (Demo)','mmobile'=>'0301 7654321','mo'=>'Teacher'],$institute));$student=$students->find($id,$institute);}

$employees=new EmployeeRepository($db);$employee=$db->query("SELECT * FROM employees WHERE institute_id=:i AND employee_no='DEMO-EMP-001'",['i'=>$institute])->fetch();
if(!$employee){$id=$employees->create(['institute_id'=>$institute,'employee_no'=>'DEMO-EMP-001','name'=>'Hassan Ali (Demo)','picture_path'=>null,'mobile'=>'0312 1234567','joining_date'=>$today,'role'=>'Teacher','monthly_salary'=>55000,'show_on_profile'=>1,'father_husband_name'=>null,'national_id'=>'61101-DEMO-002','education'=>'MSc Mathematics','gender'=>'Male','religion'=>'Islam','blood_group'=>'O+','experience'=>'5 years','email'=>'hassan.demo@example.test','date_of_birth'=>'1992-08-12','home_address'=>'Islamabad, Pakistan','status'=>'active','created_at'=>$now,'updated_at'=>$now]);$employee=$employees->find($id,$institute);}

$attendance=new AttendanceService(new AttendanceRepository($db));
$attendance->saveStudents($today,[(int)$student['id']],[(int)$student['id']=>'Present'],[(int)$student['id']=>'Demo attendance record'],$admin);
$attendance->saveEmployees($today,[(int)$employee['id']],[(int)$employee['id']=>'Present'],[(int)$employee['id']=>'08:30'],[(int)$employee['id']=>'16:30'],[(int)$employee['id']=>'Demo attendance record'],$admin);

$fees=new FeeRepository($db);$feeService=new FeeService($fees,$students);$month=date('Y-m');$invoice=$feeService->ensureInvoice($student,$institute,$month,$admin);
if((float)$invoice['remaining_amount']>0&&!$fees->payments((int)$invoice['id']))$feeService->collect($student,$institute,$month,min(500,(float)$invoice['remaining_amount']),'Cash','DEMO-PAYMENT',$admin);

$accounts=new AccountRepository($db);
foreach([['income','Demo admission income',25000,'DEMO-INCOME'],['expense','Demo classroom supplies',6500,'DEMO-EXPENSE']]as[$type,$description,$amount,$reference])if(!$db->query('SELECT 1 FROM account_transactions WHERE institute_id=:i AND reference_type=:r',['i'=>$institute,'r'=>$reference])->fetchColumn())$accounts->create($institute,$type,$today,$description,$amount,$admin,$reference);

$academics=new AcademicRepository($db);$academics->assign($institute,(int)$class['id'],'Mathematics (Demo)',100);
$subject=(int)$db->query("SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE s.institute_id=:i AND cs.class_id=:c AND s.name='Mathematics (Demo)'",['i'=>$institute,'c'=>$class['id']])->fetchColumn();
$workflow=new WorkflowRepository($db);
if(!$db->query("SELECT 1 FROM salary_payments WHERE employee_id=:e AND salary_month=:m",['e'=>$employee['id'],'m'=>$month])->fetchColumn())$workflow->paySalary($institute,(int)$employee['id'],$month,55000,$today,$admin,0,'DEMO-SALARY','Demo monthly salary payment');
if(!$db->query("SELECT 1 FROM homework WHERE class_id=:c AND subject_id=:s AND homework_text='Complete exercises 1–10 (Demo)'",['c'=>$class['id'],'s'=>$subject])->fetchColumn())$workflow->createHomework($institute,['teacher_id'=>$employee['id'],'class_id'=>$class['id'],'subject_id'=>$subject,'date'=>$today,'text'=>'Complete exercises 1–10 (Demo)'],$admin);
$exam=$db->query("SELECT * FROM exams WHERE institute_id=:i AND name='Demo September Assessment'",['i'=>$institute])->fetch();
if(!$exam){$examId=$workflow->createExam($institute,'Demo September Assessment',$today,date('Y-m-d',strtotime('+7 days')),$admin);}else$examId=(int)$exam['id'];
$setup=$db->query('SELECT id FROM exam_subjects WHERE exam_id=:e AND class_id=:c AND subject_id=:s',['e'=>$examId,'c'=>$class['id'],'s'=>$subject])->fetchColumn();
if(!$setup){$workflow->setupExam($institute,$examId,(int)$class['id'],$subject,100);$setup=$db->query('SELECT id FROM exam_subjects WHERE exam_id=:e AND class_id=:c AND subject_id=:s',['e'=>$examId,'c'=>$class['id'],'s'=>$subject])->fetchColumn();}
$workflow->saveExamMarks($institute,(int)$setup,[(int)$student['id']=>87],$admin);
$test=$db->query("SELECT id FROM class_tests WHERE institute_id=:i AND title='Demo Mathematics Quiz'",['i'=>$institute])->fetchColumn();
if(!$test)$test=$workflow->createClassTest($institute,(int)$class['id'],$subject,$today,'Demo Mathematics Quiz',20,$admin);
$workflow->saveClassTestMarks($institute,(int)$test,[(int)$student['id']=>17],$admin);
if(!$db->query("SELECT 1 FROM certificates WHERE institute_id=:i AND student_id=:s AND achievement='Excellent Performance (Demo)'",['i'=>$institute,'s'=>$student['id']])->fetchColumn())$workflow->createCertificate($institute,'Achievement Certificate','student',(int)$student['id'],null,$today,$admin,'Excellent Performance (Demo)');

$from=$db->query("SELECT * FROM classes WHERE institute_id=:i AND name='Demo Grade 5'",['i'=>$institute])->fetch();if(!$from){$academics->createClass($institute,'Demo Grade 5',900,null);$from=$db->query("SELECT * FROM classes WHERE institute_id=:i AND name='Demo Grade 5'",['i'=>$institute])->fetch();}
$promoted=$students->byRegistration('DEMO-2026-002',$institute);if(!$promoted){$sid=(new StudentService($students))->create((new StudentService($students))->data(['studentname'=>'Hamza Ahmed (Demo)','reg'=>'DEMO-2026-002','doa'=>$today,'class'=>$from['id'],'discount'=>0,'fname'=>'Ahmed Raza (Demo)'],$institute));$promoted=$students->find($sid,$institute);}
if(!$db->query('SELECT 1 FROM student_promotions WHERE student_id=:s',['s'=>$promoted['id']])->fetchColumn()&&(int)$promoted['class_id']===(int)$from['id'])$workflow->promote($institute,(int)$from['id'],(int)$class['id'],[(int)$promoted['id']],$admin);

echo "Complete preview data ready across academics, students, promotions, employees, attendance, fees, accounts, salary, homework, exams, class tests, reports and certificates.\n";
