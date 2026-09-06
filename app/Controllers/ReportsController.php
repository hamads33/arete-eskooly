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
    private function data(Request$r):array{$i=$this->i();$q=trim((string)$r->query('search',''));$class=(int)$r->query('class',0);$data=(new SupportRepository($this->app->database()))->students($i,$q,$class,(int)$r->query('page',1));return$data+['classes'=>$this->classes(),'search'=>$q,'selectedClass'=>$class];}

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
            'students'=>(new StudentRepository($this->app->database()))->paginate($i,'',((int)$r->query('class',0))?:null,300),
            'selectedClass'=>(int)$r->query('class',0),
            'student'=>$student,
            'progress'=>$student?$this->reporting()->studentProgress($i,(int)$student['id']):null,
        ]);
    }
}
