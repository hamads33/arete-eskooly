<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Repositories\{AnnouncementRepository,AttendanceRepository,PakistanErpRepository,PortalRepository,WorkflowRepository};
use App\Services\{AttendanceService,WhatsAppService};

return static function(App$app):array{
    $db=$app->database();$checks=[];$check=static function(string$name,bool$value)use(&$checks):void{$checks[$name]=$value;};
    $institute=(int)$db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin=(int)$db->query('SELECT iu.user_id FROM institute_users iu WHERE iu.institute_id=:i ORDER BY iu.user_id LIMIT 1',['i'=>$institute])->fetchColumn();
    $adminRow=$db->query('SELECT id,name,email,status FROM users WHERE id=:id',['id'=>$admin])->fetch();
    $app->session()->set('auth_user',array_merge($adminRow?:[],['roles'=>['admin'],'permissions'=>['*']]));
    foreach(['/admin/guardians','/admin/teacher-assignments','/admin/notes','/admin/announcements','/admin/settings/academic-profiles','/admin/fees/automation','/admin/whatsapp']as$route){$response=$app->run(new Request([],[],[],['REQUEST_METHOD'=>'GET','REQUEST_URI'=>$route]));$check('new route renders '.$route,$response->status()===200);}
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

    if($students&&$admin){$date='2099-12-30';$attendanceRepo=new AttendanceRepository($db);(new AttendanceService($attendanceRepo))->saveStudents($date,[(int)$students[0]],[(int)$students[0]=>'Late'],[(int)$students[0]=>'Scope regression test'],$admin);$saved=$db->query('SELECT status FROM student_attendance WHERE student_id=:s AND attendance_date=:d',['s'=>(int)$students[0],'d'=>$date])->fetchColumn();$check('student attendance supports Late',$saved==='Late');$check('admin all-student attendance register spans active classes',count($attendanceRepo->allStudents($date,$institute))===(int)$db->query("SELECT COUNT(*) FROM students WHERE institute_id=:i AND status='active'",['i'=>$institute])->fetchColumn());$db->query('DELETE FROM student_attendance WHERE student_id=:s AND attendance_date=:d',['s'=>(int)$students[0],'d'=>$date]);}

    $resourceIds=[];
    if($students&&$admin){$base=['subject_id'=>null,'teacher_id'=>null,'chapter'=>null,'description'=>null,'attachment_path'=>null,'publish_date'=>date('Y-m-d'),'status'=>'published','created_by'=>$admin];$resourceIds[]=$repo->saveNote($institute,$base+['audience_type'=>'all','class_id'=>null,'student_id'=>null,'title'=>'All students '.$suffix,'external_url'=>'https://example.com/all']);$resourceIds[]=$repo->saveNote($institute,$base+['audience_type'=>'student','class_id'=>null,'student_id'=>(int)$students[0],'title'=>'One student '.$suffix,'external_url'=>'https://example.com/one']);$studentRow=$db->query('SELECT id,class_id FROM students WHERE id=:id',['id'=>(int)$students[0]])->fetch();$visible=(new PortalRepository($db))->studentNotes((int)$studentRow['class_id'],$institute,60,(int)$studentRow['id']);$titles=array_column($visible,'title');$check('admin can publish external link to all students',in_array('All students '.$suffix,$titles,true));$check('admin can publish resource to one student',in_array('One student '.$suffix,$titles,true));}

    $classIds=array_map('intval',$db->query('SELECT id FROM classes WHERE institute_id=:i ORDER BY id LIMIT 2',['i'=>$institute])->fetchAll(PDO::FETCH_COLUMN));$homeworkIds=[];
    if(count($classIds)>1&&$admin&&$employee){$multi=$repo->saveNote($institute,['audience_type'=>'class','class_ids'=>$classIds,'class_id'=>null,'subject_id'=>null,'student_id'=>null,'teacher_id'=>null,'chapter'=>null,'title'=>'Multi class '.$suffix,'description'=>null,'attachment_path'=>null,'external_url'=>'https://example.com/multi','publish_date'=>date('Y-m-d'),'status'=>'published','created_by'=>$admin]);$resourceIds[]=$multi;$check('admin resource maps one item to multiple classes',(int)$db->query('SELECT COUNT(*) FROM class_note_classes WHERE note_id=:id',['id'=>$multi])->fetchColumn()===2);$workflow=new WorkflowRepository($db);$hw=$workflow->createHomework($institute,['date'=>date('Y-m-d'),'teacher_id'=>$employee,'audience_type'=>'class','class_ids'=>$classIds,'subject_id'=>null,'student_id'=>null,'text'=>'Multi class homework '.$suffix],$admin);$homeworkIds[]=$hw;$check('admin homework maps one assignment to multiple classes',(int)$db->query('SELECT COUNT(*) FROM homework_classes WHERE homework_id=:id',['id'=>$hw])->fetchColumn()===2);}

    $announcementIds=[];if($admin){$announcements=new AnnouncementRepository($db);$announcementIds[]=$announcements->create($institute,['title'=>'Student notice '.$suffix,'message'=>'Student message','audience'=>'students','show_popup'=>1,'status'=>'published','publish_at'=>date('Y-m-d H:i:s')],$admin);$check('student announcement is visible to students and popup enabled',!empty(array_filter($announcements->visible($institute,'student'),fn($a)=>(int)$a['id']===$announcementIds[0]&&(int)$a['show_popup']===1)));$check('student announcement is hidden from employees',empty(array_filter($announcements->visible($institute,'employee'),fn($a)=>(int)$a['id']===$announcementIds[0])));}

    $wa=new WhatsAppService();
    $check('Pakistan mobile normalization',$wa->normalize('0300-1234567')==='923001234567');
    $check('international mobile preserved',$wa->normalize('+447700900123')==='447700900123');
    $check('WhatsApp provider never fakes delivery',$wa->send('03001234567','Test')['status']==='not_configured');
    $check('safe WhatsApp placeholders',$wa->render('Dear {{student_name}} {{unknown}}',['student_name'=>'Ali'])==='Dear Ali ');

    foreach($resourceIds as$id)$db->query('DELETE FROM class_notes WHERE id=:id',['id'=>$id]);
    foreach($homeworkIds as$id)$db->query('DELETE FROM homework WHERE id=:id',['id'=>$id]);
    foreach($announcementIds as$id)$db->query('DELETE FROM announcements WHERE id=:id',['id'=>$id]);
    if($assignment)$db->query('DELETE FROM teacher_assignments WHERE id=:id',['id'=>$assignment]);
    $db->query('DELETE FROM guardians WHERE id=:id AND institute_id=:i',['id'=>$guardian,'i'=>$institute]);
    return$checks;
};
