<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};
use App\Repositories\{SupportRepository,SettingsRepository,AcademicRepository,ReportingRepository,StudentRepository,EmployeeRepository};

/**
 * Reporting. The monthly attendance, fee collection, accounts, salary and progress reports
 * correspond to entries eSkooly locks behind its Desktop edition; they are available here.
 */
final class ReportsController extends AdminPageController
{
    private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
    private function reporting():ReportingRepository{return new ReportingRepository($this->app->database());}
    private function classes():array{return(new AcademicRepository($this->app->database()))->classes($this->i());}
    private function data(Request$r):array{$i=$this->i();$q=trim((string)$r->query('search',''));$class=(int)$r->query('class',0);$data=(new SupportRepository($this->app->database()))->students($i,$q,$class,(int)$r->query('page',1),2000);return$data+['classes'=>$this->classes(),'search'=>$q,'selectedClass'=>$class];}

    /** YYYY-MM, defaulting to the current month. */
    private function period(Request$r):string{$p=(string)$r->query('period',date('Y-m'));return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$p)?$p:date('Y-m');}
    /** A validated from/to date range, defaulting to the current month to date. */
    private function range(Request$r):array{$valid=static function(string$v):bool{$d=\DateTimeImmutable::createFromFormat('Y-m-d',$v);return$d!==false&&$d->format('Y-m-d')===$v;};$from=(string)$r->query('from',date('Y-m-01'));$to=(string)$r->query('to',date('Y-m-d'));if(!$valid($from))$from=date('Y-m-01');if(!$valid($to))$to=date('Y-m-d');if($from>$to){[$from,$to]=[$to,$from];}return[$from,$to];}

    public function admissions(Request$r):Response{return$this->renderPage('report-admissions',$this->data($r));}
    public function contacts(Request$r):Response{return$this->renderPage('report-contacts',$this->data($r));}

    public function studentAttendance(Request$r):Response{
        $period=$this->period($r);$class=(int)$r->query('class',0);
        return$this->renderPage('report-student-attendance',[
            'period'=>$period,'classes'=>$this->classes(),'selectedClass'=>$class,
            'days'=>$this->reporting()->daysInMonth($period),
            'rows'=>$this->reporting()->studentMonthlyAttendance($this->i(),$period,$class),
            'audience'=>'student',
        ]);
    }

    public function employeeAttendance(Request$r):Response{
        $period=$this->period($r);
        return$this->renderPage('report-employee-attendance',[
            'period'=>$period,'classes'=>[],'selectedClass'=>0,
            'days'=>$this->reporting()->daysInMonth($period),
            'rows'=>$this->reporting()->employeeMonthlyAttendance($this->i(),$period),
            'audience'=>'employee',
        ]);
    }

    public function feeCollection(Request$r):Response{
        [$from,$to]=$this->range($r);$class=(int)$r->query('class',0);$i=$this->i();
        return$this->renderPage('report-fee-collection',[
            'from'=>$from,'to'=>$to,'classes'=>$this->classes(),'selectedClass'=>$class,
            'rows'=>$this->reporting()->feeCollection($i,$from,$to,$class),
            'methods'=>$this->reporting()->feeCollectionByMethod($i,$from,$to),
            'institute'=>(new SettingsRepository($this->app->database()))->institute(),
        ]);
    }

    public function accounts(Request$r):Response{
        [$from,$to]=$this->range($r);
        return$this->renderPage('report-accounts',[
            'from'=>$from,'to'=>$to,
            'rows'=>$this->reporting()->accountsSummary($this->i(),$from,$to),
            'feeCharges'=>(new \App\Repositories\AccountRepository($this->app->database()))->feeChargesBilled($this->i(),substr($from,0,7),substr($to,0,7)),
            'feeCollected'=>(float)$this->app->database()->query('SELECT COALESCE(SUM(p.amount),0) FROM fee_payments p JOIN students s ON s.id=p.student_id WHERE s.institute_id=:i AND p.reversed_at IS NULL AND p.payment_date BETWEEN :f AND :t',['i'=>$this->i(),'f'=>$from,'t'=>$to])->fetchColumn(),
            'institute'=>(new SettingsRepository($this->app->database()))->institute(),
        ]);
    }

    public function salary(Request$r):Response{
        $from=(string)$r->query('from_month',date('Y-01'));$to=(string)$r->query('to_month',date('Y-m'));
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$from))$from=date('Y-01');
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$to))$to=date('Y-m');
        if($from>$to){[$from,$to]=[$to,$from];}
        return$this->renderPage('report-salary',[
            'fromMonth'=>$from,'toMonth'=>$to,
            'rows'=>$this->reporting()->salaryReport($this->i(),$from,$to),
            'institute'=>(new SettingsRepository($this->app->database()))->institute(),
        ]);
    }

    public function progress(Request$r):Response{
        $i=$this->i();$studentId=(int)$r->query('student',0);
        $student=$studentId>0?(new StudentRepository($this->app->database()))->find($studentId,$i):null;
        return$this->renderPage('report-progress',[
            'classes'=>$this->classes(),
            'students'=>(new StudentRepository($this->app->database()))->paginate($i,'',((int)$r->query('class',0))?:null),
            'selectedClass'=>(int)$r->query('class',0),
            'student'=>$student,
            'progress'=>$student?$this->reporting()->studentProgress($i,(int)$student['id']):null,
            'onlineTests'=>$student?(new \App\Repositories\OnlineTestRepository($this->app->database()))->progressSummary($i,(int)$student['id']):null,
            'subjects'=>(new SupportRepository($this->app->database()))->subjects($i,$student?(int)$student['class_id']:0),
            'filters'=>$filters=$this->progressFilters($r),
            'assessments'=>$student?$this->assessments($i,(int)$student['id'],$filters):[],
        ]);
    }

    /** Optional subject / type / date filters for the progress report; an empty date means no limit. */
    private function progressFilters(Request$r):array{
        $date=static function(string$v):string{$d=\DateTimeImmutable::createFromFormat('Y-m-d',$v);return$d!==false&&$d->format('Y-m-d')===$v?$v:'';};
        $from=$date((string)$r->query('from',''));$to=$date((string)$r->query('to',''));
        if($from!==''&&$to!==''&&$from>$to)[$from,$to]=[$to,$from];
        $type=(string)$r->query('type','');
        return['subject'=>(int)$r->query('subject',0),'type'=>in_array($type,['class_test','exam','online_test'],true)?$type:'','from'=>$from,'to'=>$to];
    }

    /**
     * Every assessment a student has a record for: class tests, exam papers and online tests, newest
     * first. Absent and not-yet-marked entries are kept and flagged, never counted as zero.
     */
    private function assessments(int$i,int$student,array$f):array{
        $db=$this->app->database();$rows=[];$p=['s'=>$student,'i'=>$i];
        if($f['type']===''||$f['type']==='class_test'){
            foreach($db->query("SELECT ct.test_date d,ct.title,s.name subject,ct.subject_id,m.obtained_marks obtained,ct.total_marks total,m.is_absent FROM class_test_marks m JOIN class_tests ct ON ct.id=m.class_test_id LEFT JOIN subjects s ON s.id=ct.subject_id WHERE m.student_id=:s AND ct.institute_id=:i",$p)->fetchAll() as$r)
                $rows[]=$r+['type'=>'class_test','state'=>(int)$r['is_absent']?'absent':'scored'];
        }
        if($f['type']===''||$f['type']==='exam'){
            foreach($db->query("SELECT e.start_date d,e.name title,s.name subject,es.subject_id,em.obtained_marks obtained,es.total_marks total,em.is_absent,e.status exam_status FROM exam_marks em JOIN exam_subjects es ON es.id=em.exam_subject_id JOIN exams e ON e.id=es.exam_id LEFT JOIN subjects s ON s.id=es.subject_id WHERE em.student_id=:s AND e.institute_id=:i",$p)->fetchAll() as$r)
                $rows[]=$r+['type'=>'exam','state'=>(int)$r['is_absent']?'absent':'scored'];
        }
        if($f['type']===''||$f['type']==='online_test'){
            foreach($db->query("SELECT DATE(COALESCE(a.submitted_at,a.started_at)) d,t.title,s.name subject,t.subject_id,a.total_score obtained,(SELECT SUM(q.marks) FROM online_test_questions q WHERE q.test_id=t.id) total,0 is_absent,a.status attempt_status,a.published_at FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id LEFT JOIN subjects s ON s.id=t.subject_id WHERE a.student_id=:s AND t.institute_id=:i",$p)->fetchAll() as$r){
                $state=$r['attempt_status']!=='marked'?'unmarked':($r['published_at']===null?'unpublished':'scored');
                if($state==='scored'&&(float)$r['total']<=0)$state='no_total';
                $rows[]=$r+['type'=>'online_test','state'=>$state];
            }
        }
        $rows=array_values(array_filter($rows,static fn(array$r):bool=>($f['subject']===0||(int)$r['subject_id']===$f['subject'])&&($f['from']===''||(string)$r['d']>=$f['from'])&&($f['to']===''||(string)$r['d']<=$f['to'])));
        foreach($rows as&$r)$r['percentage']=$r['state']==='scored'&&(float)$r['total']>0?round((float)$r['obtained']/(float)$r['total']*100,2):null;
        unset($r);
        usort($rows,static fn(array$a,array$b):int=>[(string)$b['d'],$a['type']]<=>[(string)$a['d'],$b['type']]);
        return$rows;
    }

    public function attendanceShortage(Request$r):Response{
        [$from,$to]=$this->range($r);$class=(int)$r->query('class',0);$i=$this->i();
        $minimum=$this->reporting()->minimumAttendance($i);
        $threshold=$minimum!==null?$minimum:(float)($r->query('threshold',75));
        return$this->renderPage('report-attendance-shortage',[
            'from'=>$from,'to'=>$to,'classes'=>$this->classes(),'selectedClass'=>$class,
            'rows'=>$this->reporting()->attendanceShortage($i,$from,$to,$class),
            'threshold'=>$threshold,'configured'=>$minimum!==null,
        ]);
    }

    public function testHistory(Request$r):Response{
        $i=$this->i();$studentId=(int)$r->query('student',0);$class=(int)$r->query('class',0);
        [$from,$to]=$this->range($r);
        $student=$studentId>0?(new StudentRepository($this->app->database()))->find($studentId,$i):null;
        return$this->renderPage('report-test-history',[
            'classes'=>$this->classes(),'selectedClass'=>$class,
            'students'=>(new StudentRepository($this->app->database()))->paginate($i,'',$class?:null),
            'student'=>$student,'from'=>$from,'to'=>$to,
            'rows'=>$student?$this->reporting()->studentTestHistory($i,(int)$student['id'],$from,$to):[],
        ]);
    }

    public function studentRegister(Request$r):Response{
        $i=$this->i();$status=(string)$r->query('status','');$class=(int)$r->query('class',0);
        return$this->renderPage('report-student-register',[
            'classes'=>$this->classes(),'selectedClass'=>$class,'status'=>$status,
            'rows'=>$this->reporting()->studentRegister($i,$status,$class),
        ]);
    }

    public function families(Request$r):Response{
        return$this->renderPage('report-families',[
            'families'=>$this->reporting()->familyReport($this->i()),
            'institute'=>(new SettingsRepository($this->app->database()))->institute(),
        ]);
    }

    public function academic(Request$r):Response{
        [$from,$to]=$this->range($r);$class=(int)$r->query('class',0);$i=$this->i();
        return$this->renderPage('report-academic',[
            'from'=>$from,'to'=>$to,'classes'=>$this->classes(),'selectedClass'=>$class,
            'homework'=>$this->reporting()->homeworkReport($i,$from,$to,$class),
            'notes'=>$this->reporting()->notesReport($i,$class),
            'assignments'=>$this->reporting()->teacherAssignmentReport($i),
        ]);
    }
}
