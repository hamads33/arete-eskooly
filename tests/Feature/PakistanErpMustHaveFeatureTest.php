<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Repositories\{AttendanceRepository,PakistanErpRepository};
use App\Services\{AttendanceService,WhatsAppService};

return static function(App$app):array{
    $db=$app->database();$checks=[];$check=static function(string$name,bool$value)use(&$checks):void{$checks[$name]=$value;};
    $institute=(int)$db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin=(int)$db->query('SELECT iu.user_id FROM institute_users iu WHERE iu.institute_id=:i ORDER BY iu.user_id LIMIT 1',['i'=>$institute])->fetchColumn();
    $adminRow=$db->query('SELECT id,name,email,status FROM users WHERE id=:id',['id'=>$admin])->fetch();
    $app->session()->set('auth_user',array_merge($adminRow?:[],['roles'=>['admin'],'permissions'=>['*']]));
    foreach(['/admin/guardians','/admin/teacher-assignments','/admin/notes','/admin/settings/academic-profiles','/admin/fees/automation','/admin/whatsapp']as$route){$response=$app->run(new Request([],[],[],['REQUEST_METHOD'=>'GET','REQUEST_URI'=>$route]));$check('new route renders '.$route,$response->status()===200);}
    $class=(int)$db->query('SELECT id FROM classes WHERE institute_id=:i ORDER BY id LIMIT 1',['i'=>$institute])->fetchColumn();
    $employee=(int)$db->query('SELECT id FROM employees WHERE institute_id=:i ORDER BY id LIMIT 1',['i'=>$institute])->fetchColumn();
    $students=$db->query('SELECT id FROM students WHERE institute_id=:i ORDER BY id LIMIT 2',['i'=>$institute])->fetchAll(PDO::FETCH_COLUMN);
    $repo=new PakistanErpRepository($db);$suffix=bin2hex(random_bytes(4));

    $guardian=$repo->saveGuardian(null,$institute,['name'=>'QA Guardian '.$suffix,'relationship'=>'Father','cnic'=>null,'mobile'=>'03001234567','email'=>null,'address'=>null,'status'=>'active']);
    if($students)$repo->syncGuardianChildren($guardian,$institute,[(int)$students[0]]);
    $check('parent guardian is institute scoped',$repo->guardian($guardian,$institute)!==null&&$repo->guardian($guardian,$institute+999999)===null);
    if(count($students)>1)$check('parent cannot access an unrelated child',$repo->guardianChild($guardian,(int)$students[1],$institute)===null);
    if($students)$check('parent can access linked child',$repo->guardianChild($guardian,(int)$students[0],$institute)!==null);

    $assignment=0;
    if($employee&&$class&&$admin){$repo->saveAssignment($institute,$employee,$class,null,null,'whole_class',$admin);$assignment=(int)$db->connection()->lastInsertId();$check('whole-class teacher scope works',$repo->teacherCan($employee,$institute,$class,null));$check('teacher cannot access unassigned class',!$repo->teacherCan($employee,$institute,$class+999999,null));}

    if($students&&$admin){$date='2099-12-30';(new AttendanceService(new AttendanceRepository($db)))->saveStudents($date,[(int)$students[0]],[(int)$students[0]=>'Late'],[(int)$students[0]=>'Scope regression test'],$admin);$saved=$db->query('SELECT status FROM student_attendance WHERE student_id=:s AND attendance_date=:d',['s'=>(int)$students[0],'d'=>$date])->fetchColumn();$check('student attendance supports Late',$saved==='Late');$db->query('DELETE FROM student_attendance WHERE student_id=:s AND attendance_date=:d',['s'=>(int)$students[0],'d'=>$date]);}

    $wa=new WhatsAppService();
    $check('Pakistan mobile normalization',$wa->normalize('0300-1234567')==='923001234567');
    $check('international mobile preserved',$wa->normalize('+447700900123')==='447700900123');
    $check('WhatsApp provider never fakes delivery',$wa->send('03001234567','Test')['status']==='not_configured');
    $check('safe WhatsApp placeholders',$wa->render('Dear {{student_name}} {{unknown}}',['student_name'=>'Ali'])==='Dear Ali ');

    if($assignment)$db->query('DELETE FROM teacher_assignments WHERE id=:id',['id'=>$assignment]);
    $db->query('DELETE FROM guardians WHERE id=:id AND institute_id=:i',['id'=>$guardian,'i'=>$institute]);
    return$checks;
};
