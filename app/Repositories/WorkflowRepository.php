<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DomainException;
use PDO;

final class WorkflowRepository
{
    public function __construct(private readonly Database $db) {}

    public function promote(int $instituteId, int $fromClass, int $toClass, array $studentIds, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if (!$ids || $fromClass === $toClass) throw new DomainException('Select students and a different target class.');
        foreach ([$fromClass, $toClass] as $class) if (!$this->owned('classes', $class, $instituteId)) throw new DomainException('Invalid class.');
        $pdo = $this->db->connection(); $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s'); $count = 0;
            foreach ($ids as $id) {
                $student = $this->db->query('SELECT class_id FROM students WHERE id=:id AND institute_id=:i FOR UPDATE', ['id'=>$id,'i'=>$instituteId])->fetch();
                if (!$student || (int)$student['class_id'] !== $fromClass) throw new DomainException('Student does not belong to the source class.');
                $this->db->query('UPDATE students SET class_id=:to,updated_at=:now WHERE id=:id', ['to'=>$toClass,'now'=>$now,'id'=>$id]);
                $this->db->query('INSERT INTO student_promotions(student_id,from_class_id,to_class_id,promoted_at,promoted_by) VALUES(:s,:f,:t,:at,:u)', ['s'=>$id,'f'=>$fromClass,'t'=>$toClass,'at'=>$now,'u'=>$userId]);
                $count++;
            }
            $pdo->commit(); return $count;
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    /**
     * $adjustment is a bonus (positive) or a plain manual adjustment (may be negative, kept for
     * backward compatibility). $deduction and $advanceRecovery are always positive and are
     * subtracted; adjustment_amount keeps storing the net figure everything else already reads.
     */
    public function paySalary(int $instituteId, int $employeeId, string $month, float $paid, string $date, int $userId, float $adjustment=0, ?string $reference=null, ?string $remarks=null, float $deduction=0, float $advanceRecovery=0): int
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || !$this->date($date) || $paid <= 0) throw new DomainException('Invalid salary payment.');
        if ($deduction < 0 || $advanceRecovery < 0) throw new DomainException('Deduction and advance recovery cannot be negative.');
        $employee=$this->db->query('SELECT e.monthly_salary FROM employees e WHERE e.id=:id AND e.institute_id=:i AND '.\App\Support\PersonStatus::visibleSql($this->db,$instituteId,'employee','e','billing',':since'),['id'=>$employeeId,'i'=>$instituteId,'since'=>$month.'-01 00:00:00'])->fetch();
        if(!$employee) throw new DomainException('This employee is not on the payroll for that month.');
        if ($advanceRecovery > 0 && $advanceRecovery > $this->advanceBalance($employeeId) + 0.004) {
            throw new DomainException('Advance recovery cannot exceed the employee\'s outstanding advance balance.');
        }
        $net = $adjustment - $deduction - $advanceRecovery;
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            try {
                $this->db->query('INSERT INTO salary_payments(employee_id,salary_month,base_salary,adjustment_amount,deduction_amount,advance_recovered,paid_amount,payment_date,reference,remarks,paid_by,created_at) VALUES(:e,:m,:b,:a,:d,:av,:p,:d2,:r,:x,:u,:c)',
                    ['e'=>$employeeId,'m'=>$month,'b'=>$employee['monthly_salary'],'a'=>$net,'d'=>$deduction,'av'=>$advanceRecovery,'p'=>$paid,'d2'=>$date,'r'=>$reference,'x'=>$remarks,'u'=>$userId,'c'=>date('Y-m-d H:i:s')]);
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') throw new DomainException('Salary already paid for this period.');
                throw $e;
            }
            $paymentId = (int) $pdo->lastInsertId();
            if ($advanceRecovery > 0) {
                $this->recoverAdvance($employeeId, $paymentId, $advanceRecovery);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return $paymentId;
    }

    // ---- salary advances --------------------------------------------------------------------

    public function giveSalaryAdvance(int $instituteId, int $employeeId, float $amount, string $date, ?string $reason, int $userId): int
    {
        if ($amount <= 0 || !$this->date($date)) throw new DomainException('Enter a valid advance amount and date.');
        if (!$this->owned('employees', $employeeId, $instituteId)) throw new DomainException('Invalid employee.');
        $reason = $reason !== null ? trim($reason) : null;
        if ($reason !== null && mb_strlen($reason) > 255) throw new DomainException('Reason must be 255 characters or fewer.');
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO salary_advances(institute_id,employee_id,amount,date_given,reason,created_by,created_at,updated_at) VALUES(:i,:e,:a,:d,:r,:u,:c,:c2)',
            ['i'=>$instituteId,'e'=>$employeeId,'a'=>$amount,'d'=>$date,'r'=>$reason?:null,'u'=>$userId,'c'=>$now,'c2'=>$now]);
        return (int) $this->db->connection()->lastInsertId();
    }

    /** Outstanding balance across every advance an employee has not yet fully paid back. */
    public function advanceBalance(int $employeeId): float
    {
        return (float) $this->db->query("SELECT COALESCE(SUM(amount-recovered_amount),0) FROM salary_advances WHERE employee_id=:e AND status='outstanding'", ['e'=>$employeeId])->fetchColumn();
    }

    /** @return array<int,float> employee_id => outstanding advance balance, for every employee that has one */
    public function advanceBalances(int $instituteId): array
    {
        $rows = $this->db->query("SELECT employee_id,SUM(amount-recovered_amount) balance FROM salary_advances WHERE institute_id=:i AND status='outstanding' GROUP BY employee_id HAVING balance>0.004", ['i'=>$instituteId])->fetchAll();
        $out = [];
        foreach ($rows as $row) $out[(int) $row['employee_id']] = (float) $row['balance'];
        return $out;
    }

    public function salaryAdvances(int $instituteId, int $employee=0): array
    {
        $sql='SELECT sa.*,e.name,e.employee_no,(sa.amount-sa.recovered_amount) balance FROM salary_advances sa JOIN employees e ON e.id=sa.employee_id WHERE sa.institute_id=:i'.($employee?' AND sa.employee_id=:e':'').' ORDER BY sa.status="outstanding" DESC,sa.date_given DESC,sa.id DESC LIMIT 5000';
        $p=['i'=>$instituteId];
        if ($employee) $p['e']=$employee;
        return $this->db->query($sql,$p)->fetchAll();
    }

    /** Applies a recovery to an employee's oldest outstanding advances first (FIFO), settling each as it clears. */
    private function recoverAdvance(int $employeeId, int $paymentId, float $amount): void
    {
        $remaining = $amount;
        $advances = $this->db->query("SELECT id,amount,recovered_amount FROM salary_advances WHERE employee_id=:e AND status='outstanding' ORDER BY date_given,id FOR UPDATE", ['e'=>$employeeId])->fetchAll();
        foreach ($advances as $advance) {
            if ($remaining <= 0.004) break;
            $balance = (float) $advance['amount'] - (float) $advance['recovered_amount'];
            $take = min($balance, $remaining);
            $newRecovered = (float) $advance['recovered_amount'] + $take;
            $settled = $newRecovered >= (float) $advance['amount'] - 0.004;
            $this->db->query('UPDATE salary_advances SET recovered_amount=:r,status=:s,updated_at=:u WHERE id=:id',
                ['r'=>$newRecovered,'s'=>$settled?'settled':'outstanding','u'=>date('Y-m-d H:i:s'),'id'=>$advance['id']]);
            $this->db->query('INSERT INTO salary_advance_recoveries(salary_advance_id,salary_payment_id,amount,created_at) VALUES(:a,:p,:amt,:c)',
                ['a'=>$advance['id'],'p'=>$paymentId,'amt'=>$take,'c'=>date('Y-m-d H:i:s')]);
            $remaining -= $take;
        }
        if ($remaining > 0.004) throw new DomainException('Advance recovery cannot exceed the employee\'s outstanding advance balance.');
    }

    /** Forgives whatever balance remains on an advance (e.g. an employee who has left). */
    public function writeOffSalaryAdvance(int $instituteId, int $id): bool
    {
        return $this->db->query("UPDATE salary_advances SET status='written_off',updated_at=:u WHERE id=:id AND institute_id=:i AND status='outstanding'",
            ['u'=>date('Y-m-d H:i:s'),'id'=>$id,'i'=>$instituteId])->rowCount() > 0;
    }

    /** Employees on the payroll for $month (active, kept by the admin's rule, or deactivated during/after it) who have not been paid yet, with their base salary and any outstanding advance. */
    public function pendingSalaries(int $instituteId, string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new DomainException('Invalid month.');
        return $this->db->query(
            "SELECT e.id,e.employee_no,e.name,e.role,e.monthly_salary,COALESCE(a.balance,0) advance_balance
             FROM employees e
             LEFT JOIN (SELECT employee_id,SUM(amount-recovered_amount) balance FROM salary_advances WHERE institute_id=:i2 AND status='outstanding' GROUP BY employee_id) a ON a.employee_id=e.id
             WHERE e.institute_id=:i AND " . \App\Support\PersonStatus::visibleSql($this->db, $instituteId, 'employee', 'e', 'billing', ':since') . "
               AND NOT EXISTS (SELECT 1 FROM salary_payments sp WHERE sp.employee_id=e.id AND sp.salary_month=:m)
             ORDER BY e.name,e.employee_no",
            ['i'=>$instituteId,'i2'=>$instituteId,'m'=>$month,'since'=>$month.'-01 00:00:00']
        )->fetchAll();
    }

    public function salarySlip(int $id,int $instituteId):?array{$r=$this->db->query('SELECT sp.*,e.name,e.employee_no,e.role,i.name institute_name FROM salary_payments sp JOIN employees e ON e.id=sp.employee_id JOIN institutes i ON i.id=e.institute_id WHERE sp.id=:id AND e.institute_id=:i',['id'=>$id,'i'=>$instituteId])->fetch();return$r?:null;}
    public function salaryPayments(int$instituteId,int$employee=0):array{$sql='SELECT sp.*,e.name,e.employee_no FROM salary_payments sp JOIN employees e ON e.id=sp.employee_id WHERE e.institute_id=:i'.($employee?' AND e.id=:e':'').' ORDER BY sp.payment_date DESC,sp.id DESC LIMIT 10000';$p=['i'=>$instituteId];if($employee)$p['e']=$employee;return$this->db->query($sql,$p)->fetchAll();}

    public function createExam(int $instituteId,string $name,string $start,string $end,int $userId):int{if(trim($name)===''||!$this->date($start)||!$this->date($end)||$start>$end)throw new DomainException('Invalid exam dates.');$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO exams(institute_id,name,start_date,end_date,created_by,created_at,updated_at) VALUES(:i,:n,:s,:e,:u,:c,:c2)',['i'=>$instituteId,'n'=>trim($name),'s'=>$start,'e'=>$end,'u'=>$userId,'c'=>$now,'c2'=>$now]);return(int)$this->db->connection()->lastInsertId();}
    public function exams(int$instituteId,int$limit=100):array{return$this->db->query('SELECT * FROM exams WHERE institute_id=:i ORDER BY start_date DESC,id DESC LIMIT '.max(1,min(100,$limit)),['i'=>$instituteId])->fetchAll();}
    public function deleteExam(int$instituteId,int$id):bool{return$this->db->query('DELETE FROM exams WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$instituteId])->rowCount()>0;}
    public function examSetups(int$instituteId,int$exam=0,int$class=0):array{$sql='SELECT es.*,e.name exam_name,e.status exam_status,c.name class_name,s.name subject_name,(SELECT COUNT(*) FROM exam_marks m WHERE m.exam_subject_id=es.id) marks_count FROM exam_subjects es JOIN exams e ON e.id=es.exam_id JOIN classes c ON c.id=es.class_id JOIN subjects s ON s.id=es.subject_id WHERE e.institute_id=:i'.($exam?' AND e.id=:e':'').($class?' AND c.id=:c':'').' ORDER BY e.start_date DESC,c.name,s.name';$p=['i'=>$instituteId];if($exam)$p['e']=$exam;if($class)$p['c']=$class;return$this->db->query($sql,$p)->fetchAll();}
    public function examMarkSheet(int$instituteId,int$setup):array{return$this->db->query('SELECT st.id,st.registration_no,st.student_name,es.total_marks,em.obtained_marks,COALESCE(em.is_absent,0) is_absent FROM exam_subjects es JOIN exams e ON e.id=es.exam_id JOIN students st ON st.class_id=es.class_id LEFT JOIN exam_marks em ON em.exam_subject_id=es.id AND em.student_id=st.id WHERE es.id=:s AND e.institute_id=:i AND st.status="active" ORDER BY st.student_name ASC,st.registration_no ASC,st.id ASC',['s'=>$setup,'i'=>$instituteId])->fetchAll();}
    /** Removes one subject from an exam (and any marks entered for it). Locked exams cannot change. */
    public function deleteExamSetup(int$instituteId,int$id):bool{$row=$this->db->query('SELECT e.status FROM exam_subjects es JOIN exams e ON e.id=es.exam_id WHERE es.id=:id AND e.institute_id=:i',['id'=>$id,'i'=>$instituteId])->fetch();if(!$row)return false;if($row['status']==='locked')throw new DomainException('This exam is locked. Unlock it before removing a subject.');$this->db->query('DELETE FROM exam_subjects WHERE id=:id',['id'=>$id]);return true;}
    public function classResults(int$instituteId,int$examId,int$classId):array{return$this->db->query('SELECT st.id,st.registration_no,st.student_name,SUM(em.obtained_marks) obtained,SUM(es.total_marks) total,ROUND(SUM(em.obtained_marks)/NULLIF(SUM(es.total_marks),0)*100,2) percentage,SUM(COALESCE(em.is_absent,0)) absent_subjects,COUNT(es.id) subjects FROM students st JOIN exam_subjects es ON es.class_id=st.class_id AND es.exam_id=:e LEFT JOIN exam_marks em ON em.exam_subject_id=es.id AND em.student_id=st.id WHERE st.institute_id=:i AND st.class_id=:c GROUP BY st.id ORDER BY st.student_name ASC,st.registration_no ASC,st.id ASC',['e'=>$examId,'i'=>$instituteId,'c'=>$classId])->fetchAll();}

    public function setupExam(int $instituteId,int $examId,int $classId,int $subjectId,float $total):int
    {if($total<=0||!$this->owned('exams',$examId,$instituteId)||!$this->owned('classes',$classId,$instituteId)||!$this->subjectInClass($subjectId,$classId))throw new DomainException('Invalid exam setup.');$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO exam_subjects(exam_id,class_id,subject_id,total_marks,created_at,updated_at) VALUES(:e,:c,:s,:t,:n,:n2) ON DUPLICATE KEY UPDATE total_marks=VALUES(total_marks),updated_at=VALUES(updated_at)',['e'=>$examId,'c'=>$classId,'s'=>$subjectId,'t'=>$total,'n'=>$now,'n2'=>$now]);return(int)$this->db->connection()->lastInsertId();}

    public function saveExamMarks(int $instituteId,int $setupId,array $marks,int $userId,array $absent=[]):int
    {$setup=$this->db->query('SELECT es.total_marks,es.class_id FROM exam_subjects es JOIN exams e ON e.id=es.exam_id WHERE es.id=:id AND e.institute_id=:i',['id'=>$setupId,'i'=>$instituteId])->fetch();if(!$setup)throw new DomainException('Invalid exam setup.');$pdo=$this->db->connection();$pdo->beginTransaction();try{$now=date('Y-m-d H:i:s');$count=0;foreach($marks as $studentId=>$score){$isAbsent=isset($absent[$studentId]);if(!$isAbsent&&trim((string)$score)===''){$this->db->query('DELETE FROM exam_marks WHERE exam_subject_id=:e AND student_id=:s',['e'=>$setupId,'s'=>(int)$studentId]);continue;}$score=$isAbsent?0:(float)$score;if($score<0||$score>(float)$setup['total_marks'])throw new DomainException('Marks exceed the allowed total.');$ok=$this->db->query('SELECT 1 FROM students WHERE id=:s AND institute_id=:i AND class_id=:c',['s'=>(int)$studentId,'i'=>$instituteId,'c'=>$setup['class_id']])->fetchColumn();if(!$ok)throw new DomainException('Student does not belong to the exam class.');$this->db->query('INSERT INTO exam_marks(exam_subject_id,student_id,obtained_marks,is_absent,entered_by,created_at,updated_at) VALUES(:e,:s,:m,:a,:u,:n,:n2) ON DUPLICATE KEY UPDATE obtained_marks=VALUES(obtained_marks),is_absent=VALUES(is_absent),entered_by=VALUES(entered_by),updated_at=VALUES(updated_at)',['e'=>$setupId,'s'=>(int)$studentId,'m'=>$score,'a'=>$isAbsent?1:0,'u'=>$userId,'n'=>$now,'n2'=>$now]);$count++;}$pdo->commit();return$count;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}

    public function studentResult(int $instituteId,int $examId,int $studentId,bool $publishedOnly=false):array
    {$rows=$this->db->query('SELECT s.name subject,es.total_marks,em.obtained_marks,COALESCE(em.is_absent,0) is_absent FROM exam_marks em JOIN exam_subjects es ON es.id=em.exam_subject_id JOIN exams e ON e.id=es.exam_id JOIN subjects s ON s.id=es.subject_id JOIN students st ON st.id=em.student_id WHERE e.institute_id=:i AND e.id=:e AND st.institute_id=:i2 AND st.id=:s'.($publishedOnly?" AND (es.published_at IS NOT NULL OR e.status IN('published','locked'))":'').' ORDER BY s.name',['i'=>$instituteId,'e'=>$examId,'i2'=>$instituteId,'s'=>$studentId])->fetchAll();$obt=array_sum(array_column($rows,'obtained_marks'));$total=array_sum(array_column($rows,'total_marks'));$pct=$total>0?$obt/$total*100:0;foreach($rows as&$row)if((int)$row['is_absent'])$row['obtained_marks']='Absent';unset($row);$profile=$this->db->query('SELECT pg.label grade,pg.result_status status FROM exams e JOIN academic_rule_profiles p ON p.id=COALESCE(e.rule_profile_id,(SELECT id FROM academic_rule_profiles WHERE institute_id=:i0 AND is_default=1 LIMIT 1)) JOIN academic_profile_grades pg ON pg.profile_id=p.id WHERE e.id=:e0 AND e.institute_id=:i1 AND :p BETWEEN pg.min_percentage AND pg.max_percentage ORDER BY pg.sort_order LIMIT 1',['i0'=>$instituteId,'e0'=>$examId,'i1'=>$instituteId,'p'=>$pct])->fetch();$grade=$profile?:($this->db->query('SELECT grade,status FROM grading_rules WHERE institute_id=:i AND :p BETWEEN range_from AND range_to ORDER BY sort_order LIMIT 1',['i'=>$instituteId,'p'=>$pct])->fetch()?:null);return['subjects'=>$rows,'obtained'=>$obt,'total'=>$total,'percentage'=>$pct,'grade'=>$grade];}

    public function setExamStatus(int$instituteId,int$id,string$status):bool{if(!in_array($status,['draft','marks_entered','calculated','published','locked'],true))throw new DomainException('Invalid exam status.');$exam=$this->db->query('SELECT status FROM exams WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$instituteId])->fetch();if(!$exam)return false;$allowed=['draft'=>['marks_entered'],'marks_entered'=>['draft','calculated'],'calculated'=>['marks_entered','published'],'published'=>['calculated','locked'],'locked'=>[]];if(!in_array($status,$allowed[$exam['status']]??[],true))throw new DomainException('This exam status transition is not allowed.');$this->db->query('UPDATE exams SET status=:s,updated_at=:u WHERE id=:id AND institute_id=:i',['s'=>$status,'u'=>date('Y-m-d H:i:s'),'id'=>$id,'i'=>$instituteId]);return true;}

    public function createCertificate(int $instituteId,string $template,string $type,?int $studentId,?int $employeeId,string $date,int $userId,?string $achievement):int
    {if(!in_array($type,['student','employee'],true)||!$this->date($date))throw new DomainException('Invalid certificate.');if($type==='student'){if(!$studentId||!$this->owned('students',$studentId,$instituteId)||$employeeId)throw new DomainException('Invalid recipient.');}else{if(!$employeeId||!$this->owned('employees',$employeeId,$instituteId)||$studentId)throw new DomainException('Invalid recipient.');}$this->db->query('INSERT INTO certificates(institute_id,template,recipient_type,student_id,employee_id,achievement,certificate_date,generated_by,created_at) VALUES(:i,:t,:r,:s,:e,:a,:d,:u,:c)',['i'=>$instituteId,'t'=>$template,'r'=>$type,'s'=>$studentId,'e'=>$employeeId,'a'=>$achievement,'d'=>$date,'u'=>$userId,'c'=>date('Y-m-d H:i:s')]);return(int)$this->db->connection()->lastInsertId();}

    public function certificates(int$instituteId):array{return$this->db->query('SELECT c.*,COALESCE(s.student_name,e.name) recipient_name FROM certificates c LEFT JOIN students s ON s.id=c.student_id LEFT JOIN employees e ON e.id=c.employee_id WHERE c.institute_id=:i ORDER BY c.certificate_date DESC,c.id DESC LIMIT 2000',['i'=>$instituteId])->fetchAll();}
    public function deleteCertificate(int$instituteId,int$id):bool{return$this->db->query('DELETE FROM certificates WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$instituteId])->rowCount()>0;}

    public function createHomework(int $instituteId, array $d, int $userId): int
    {
        return $this->saveHomework($instituteId, $d, $userId);
    }

    private function saveHomework(int $instituteId, array $d, int $userId, ?int $id = null): int
{$teacher=(int)($d['teacher_id']??0);$audience=in_array($d['audience_type']??'',['all','class','student'],true)?$d['audience_type']:'class';$classes=array_values(array_unique(array_filter(array_map('intval',(array)($d['class_ids']??[])))));$legacy=(int)($d['class_id']??0);if(!$classes&&$legacy)$classes=[$legacy];$student=(int)($d['student_id']??0);$subject=(int)($d['subject_id']??0);$text=trim((string)($d['text']??''));$date=(string)($d['date']??'');if(!$this->date($date)||$text===''||!$this->owned('employees',$teacher,$instituteId))throw new DomainException('Invalid homework assignment.');if($audience==='class'){if(!$classes)throw new DomainException('Select at least one class.');foreach($classes as$c)if(!$this->owned('classes',$c,$instituteId))throw new DomainException('Invalid class selection.');}elseif($audience==='student'){if(!$student||!$this->owned('students',$student,$instituteId))throw new DomainException('Select a valid student.');$classes=[];}else{$classes=[];$student=0;}if($subject&&!$this->db->query('SELECT 1 FROM class_subjects cs JOIN classes c ON c.id=cs.class_id WHERE cs.subject_id=:s AND c.institute_id=:i',['s'=>$subject,'i'=>$instituteId])->fetchColumn())throw new DomainException('Invalid subject.');$pdo=$this->db->connection();$pdo->beginTransaction();try{if($id!==null&&!$this->db->query('SELECT h.id FROM homework h JOIN employees e ON e.id=h.teacher_id WHERE h.id=:id AND e.institute_id=:i FOR UPDATE',['id'=>$id,'i'=>$instituteId])->fetchColumn())throw new DomainException('Homework not found.');$now=date('Y-m-d H:i:s');if($id===null){$this->db->query('INSERT INTO homework(homework_date,audience_type,teacher_id,class_id,subject_id,student_id,homework_text,attachment_path,created_by,created_at,updated_at) VALUES(:d,:audience,:t,:c,:s,:student,:x,:a,:u,:n,:n2)',['d'=>$date,'audience'=>$audience,'t'=>$teacher,'c'=>$classes[0]??null,'s'=>$subject?:null,'student'=>$audience==='student'?$student:null,'x'=>$text,'a'=>$d['attachment']??null,'u'=>$userId,'n'=>$now,'n2'=>$now]);$id=(int)$pdo->lastInsertId();}else{$this->db->query('UPDATE homework SET homework_date=:d,audience_type=:audience,teacher_id=:t,class_id=:c,subject_id=:s,student_id=:student,homework_text=:x,attachment_path=COALESCE(:a,attachment_path),updated_at=:n WHERE id=:id',['d'=>$date,'audience'=>$audience,'t'=>$teacher,'c'=>$classes[0]??null,'s'=>$subject?:null,'student'=>$audience==='student'?$student:null,'x'=>$text,'a'=>$d['attachment']??null,'n'=>$now,'id'=>$id]);$this->db->query('DELETE FROM homework_classes WHERE homework_id=:id',['id'=>$id]);}$map=$pdo->prepare('INSERT INTO homework_classes(homework_id,class_id) VALUES(?,?)');foreach($classes as$c)$map->execute([$id,$c]);$pdo->commit();return$id;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}

    public function homework(int$instituteId,?string$date=null,int$class=0,int$teacher=0):array{$sql='SELECT h.*,(SELECT GROUP_CONCAT(hci.class_id) FROM homework_classes hci WHERE hci.homework_id=h.id) class_ids,e.name teacher_name,c.name class_name,s.name subject_name,st.student_name,(SELECT GROUP_CONCAT(cx.name ORDER BY cx.name SEPARATOR ", ") FROM homework_classes hc JOIN classes cx ON cx.id=hc.class_id WHERE hc.homework_id=h.id) class_names FROM homework h JOIN employees e ON e.id=h.teacher_id LEFT JOIN classes c ON c.id=h.class_id LEFT JOIN subjects s ON s.id=h.subject_id LEFT JOIN students st ON st.id=h.student_id WHERE h.teacher_id IN(SELECT id FROM employees WHERE institute_id=:i)'.($date?' AND h.homework_date=:d':'').($class?' AND EXISTS(SELECT 1 FROM homework_classes hcf WHERE hcf.homework_id=h.id AND hcf.class_id=:c)':'').($teacher?' AND h.teacher_id=:t':'').' ORDER BY h.homework_date DESC,h.id DESC LIMIT 2000';$p=['i'=>$instituteId];if($date)$p['d']=$date;if($class)$p['c']=$class;if($teacher)$p['t']=$teacher;return$this->db->query($sql,$p)->fetchAll();}
    public function updateHomework(int $instituteId, int $id, array $d): bool
    {
        $this->saveHomework($instituteId, $d, 0, $id);
        return true;
    }


    /** Class tests for the institute, with how many students have marks recorded. */
public function classTests(int$instituteId,int$class=0,int$subject=0):array{$sql='SELECT ct.*,c.name class_name,s.name subject_name,(SELECT COUNT(*) FROM class_test_marks m WHERE m.class_test_id=ct.id) marked FROM class_tests ct JOIN classes c ON c.id=ct.class_id JOIN subjects s ON s.id=ct.subject_id WHERE ct.institute_id=:i'.($class?' AND ct.class_id=:c':'').($subject?' AND ct.subject_id=:s':'').' ORDER BY ct.test_date DESC,ct.id DESC LIMIT 5000';$p=['i'=>$instituteId];if($class)$p['c']=$class;if($subject)$p['s']=$subject;return$this->db->query($sql,$p)->fetchAll();}
public function classTest(int$instituteId,int$id):?array{$row=$this->db->query('SELECT ct.*,c.name class_name,s.name subject_name FROM class_tests ct JOIN classes c ON c.id=ct.class_id JOIN subjects s ON s.id=ct.subject_id WHERE ct.id=:id AND ct.institute_id=:i LIMIT 1',['id'=>$id,'i'=>$instituteId])->fetch();return is_array($row)?$row:null;}
/** Active students of the test's class with any marks already recorded, for the entry grid. */
public function classTestMarkSheet(int$instituteId,int$testId):array{$test=$this->classTest($instituteId,$testId);if(!$test)return[];return$this->db->query('SELECT st.id,st.registration_no,st.student_name,m.obtained_marks,COALESCE(m.is_absent,0) is_absent,m.remarks FROM students st LEFT JOIN class_test_marks m ON m.student_id=st.id AND m.class_test_id=:t WHERE st.class_id=:c AND st.institute_id=:i AND st.status="active" ORDER BY st.student_name ASC,st.registration_no ASC,st.id ASC',['t'=>$testId,'c'=>(int)$test['class_id'],'i'=>$instituteId])->fetchAll();}
public function updateClassTest(int$instituteId,int$id,string$title,string$date,float$total,int$subject=0):bool{if(!$test=$this->classTest($instituteId,$id))throw new DomainException('Class test not found.');if($subject>0&&$subject!==(int)$test['subject_id']){if(!$this->subjectInClass($subject,(int)$test['class_id']))throw new DomainException('That subject is not taught in this class.');$this->db->query('UPDATE class_tests SET subject_id=:s WHERE id=:id AND institute_id=:i',['s'=>$subject,'id'=>$id,'i'=>$instituteId]);}if(trim($title)===''||!$this->date($date)||$total<=0)throw new DomainException('Enter a valid title, date and total marks.');if($this->db->query('SELECT 1 FROM class_test_marks WHERE class_test_id=:t AND obtained_marks>:m LIMIT 1',['t'=>$id,'m'=>$total])->fetchColumn())throw new DomainException('Existing marks exceed the new total. Correct the marks first.');return$this->db->query('UPDATE class_tests SET title=:t,test_date=:d,total_marks=:m WHERE id=:id AND institute_id=:i',['t'=>trim($title),'d'=>$date,'m'=>$total,'id'=>$id,'i'=>$instituteId])->rowCount()>=0;}
public function deleteClassTest(int$instituteId,int$id):bool{if(!$this->classTest($instituteId,$id))return false;$this->db->query('DELETE FROM class_tests WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$instituteId]);return true;}
/** Deletes homework, scoped through its class so the institute check still applies. */
public function deleteHomework(int$instituteId,int$id):bool{$row=$this->db->query('SELECT h.id,h.attachment_path FROM homework h JOIN employees e ON e.id=h.teacher_id WHERE h.id=:id AND e.institute_id=:i',['id'=>$id,'i'=>$instituteId])->fetch();if(!is_array($row))return false;$this->db->query('DELETE FROM homework WHERE id=:id',['id'=>$id]);if(!empty($row['attachment_path'])){$p=BASE_PATH.'/public/'.$row['attachment_path'];if(is_file($p))@unlink($p);}return true;}
/** The employee record linked to a user, used to scope a teacher to their own work. */
public function employeeIdForUser(int$userId,int$instituteId):int{return(int)$this->db->query('SELECT e.id FROM employee_accounts ea JOIN employees e ON e.id=ea.employee_id WHERE ea.user_id=:u AND e.institute_id=:i LIMIT 1',['u'=>$userId,'i'=>$instituteId])->fetchColumn();}
public function createClassTest(int$instituteId,int$class,int$subject,string$date,string$title,float$total,int$userId):int
    {if(!$this->date($date)||trim($title)===''||$total<=0||!$this->owned('classes',$class,$instituteId)||!$this->subjectInClass($subject,$class))throw new DomainException('Invalid class test.');$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO class_tests(institute_id,class_id,subject_id,test_date,title,total_marks,created_by,created_at,updated_at) VALUES(:i,:c,:s,:d,:t,:m,:u,:n,:n2)',['i'=>$instituteId,'c'=>$class,'s'=>$subject,'d'=>$date,'t'=>trim($title),'m'=>$total,'u'=>$userId,'n'=>$now,'n2'=>$now]);return(int)$this->db->connection()->lastInsertId();}

    public function classTestResults(int$instituteId,int$class=0,int$subject=0,int$student=0,?string$from=null,?string$to=null):array{$sql='SELECT ct.id,ct.test_date,ct.title,ct.total_marks,c.name class_name,s.name subject_name,st.id student_id,st.registration_no,st.student_name,ctm.obtained_marks,ctm.is_absent,ROUND(ctm.obtained_marks/ct.total_marks*100,2) percentage FROM class_test_marks ctm JOIN class_tests ct ON ct.id=ctm.class_test_id JOIN classes c ON c.id=ct.class_id JOIN subjects s ON s.id=ct.subject_id JOIN students st ON st.id=ctm.student_id WHERE ct.institute_id=:i'.($class?' AND ct.class_id=:c':'').($subject?' AND ct.subject_id=:s':'').($student?' AND st.id=:st':'').($from?' AND ct.test_date>=:f':'').($to?' AND ct.test_date<=:t':'').' ORDER BY ct.test_date DESC,st.student_name ASC,st.registration_no ASC,st.id ASC LIMIT 10000';$p=['i'=>$instituteId];if($class)$p['c']=$class;if($subject)$p['s']=$subject;if($student)$p['st']=$student;if($from)$p['f']=$from;if($to)$p['t']=$to;return$this->db->query($sql,$p)->fetchAll();}
    public function setClassTestStatus(int$instituteId,int$id,string$status):bool{if(!in_array($status,['draft','published','completed'],true))throw new DomainException('Invalid test status.');return$this->db->query('UPDATE class_tests SET status=:s WHERE id=:id AND institute_id=:i',['s'=>$status,'id'=>$id,'i'=>$instituteId])->rowCount()>0;}

    public function saveClassTestMarks(int$instituteId,int$testId,array$marks,int$userId,array$absent=[],array$remarks=[]):int
    {$test=$this->db->query('SELECT class_id,total_marks FROM class_tests WHERE id=:id AND institute_id=:i',['id'=>$testId,'i'=>$instituteId])->fetch();if(!$test)throw new DomainException('Invalid class test.');$pdo=$this->db->connection();$pdo->beginTransaction();try{$now=date('Y-m-d H:i:s');$count=0;foreach($marks as$student=>$mark){$isAbsent=isset($absent[$student]);if(!$isAbsent&&trim((string)$mark)===''){$this->db->query('DELETE FROM class_test_marks WHERE class_test_id=:t AND student_id=:s',['t'=>$testId,'s'=>(int)$student]);continue;}$mark=$isAbsent?0:(float)$mark;if($mark<0||$mark>(float)$test['total_marks']||!$this->db->query('SELECT 1 FROM students WHERE id=:s AND class_id=:c AND institute_id=:i',['s'=>(int)$student,'c'=>$test['class_id'],'i'=>$instituteId])->fetchColumn())throw new DomainException('Invalid test marks.');$this->db->query('INSERT INTO class_test_marks(class_test_id,student_id,obtained_marks,is_absent,remarks,entered_by,created_at,updated_at) VALUES(:t,:s,:m,:a,:r,:u,:n,:n2) ON DUPLICATE KEY UPDATE obtained_marks=VALUES(obtained_marks),is_absent=VALUES(is_absent),remarks=VALUES(remarks),entered_by=VALUES(entered_by),updated_at=VALUES(updated_at)',['t'=>$testId,'s'=>(int)$student,'m'=>$mark,'a'=>$isAbsent?1:0,'r'=>trim((string)($remarks[$student]??''))?:null,'u'=>$userId,'n'=>$now,'n2'=>$now]);$count++;}$this->db->query("UPDATE class_tests SET status='published' WHERE id=:id AND institute_id=:i AND status='draft'",['id'=>$testId,'i'=>$instituteId]);$pdo->commit();return$count;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}

    public function createConversation(int$instituteId,int$sender,int$recipient,string$body,?string$subject=null):int
    {if($sender===$recipient||trim($body)===''||!$this->userInInstitute($recipient,$instituteId)||!$this->userInInstitute($sender,$instituteId))throw new DomainException('Invalid conversation.');$pdo=$this->db->connection();$pdo->beginTransaction();try{$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO conversations(institute_id,subject,created_by,created_at,updated_at) VALUES(:i,:s,:u,:n,:n2)',['i'=>$instituteId,'s'=>$subject,'u'=>$sender,'n'=>$now,'n2'=>$now]);$id=(int)$pdo->lastInsertId();foreach([$sender,$recipient]as$user)$this->db->query('INSERT INTO conversation_participants(conversation_id,user_id,last_read_at) VALUES(:c,:u,:r)',['c'=>$id,'u'=>$user,'r'=>$user===$sender?$now:null]);$this->db->query('INSERT INTO messages(conversation_id,sender_id,body,created_at) VALUES(:c,:u,:b,:n)',['c'=>$id,'u'=>$sender,'b'=>trim($body),'n'=>$now]);$pdo->commit();return$id;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}

    public function conversations(int$instituteId,int$user):array{return$this->db->query('SELECT c.id,c.subject,c.updated_at,GROUP_CONCAT(CASE WHEN p2.user_id<>:u THEN u2.name END) participant_name,SUM(CASE WHEN m.sender_id<>:u2 AND (p.last_read_at IS NULL OR m.created_at>p.last_read_at) THEN 1 ELSE 0 END) unread_count FROM conversations c JOIN conversation_participants p ON p.conversation_id=c.id AND p.user_id=:u3 JOIN conversation_participants p2 ON p2.conversation_id=c.id JOIN users u2 ON u2.id=p2.user_id LEFT JOIN messages m ON m.conversation_id=c.id WHERE c.institute_id=:i GROUP BY c.id ORDER BY c.updated_at DESC LIMIT 2000',['u'=>$user,'u2'=>$user,'u3'=>$user,'i'=>$instituteId])->fetchAll();}
    public function conversation(int$instituteId,int$id,int$user):?array{$allowed=$this->db->query('SELECT 1 FROM conversations c JOIN conversation_participants p ON p.conversation_id=c.id WHERE c.id=:id AND c.institute_id=:i AND p.user_id=:u',['id'=>$id,'i'=>$instituteId,'u'=>$user])->fetchColumn();if(!$allowed)return null;$this->db->query('UPDATE conversation_participants SET last_read_at=:n WHERE conversation_id=:c AND user_id=:u',['n'=>date('Y-m-d H:i:s'),'c'=>$id,'u'=>$user]);return['id'=>$id,'messages'=>$this->db->query('SELECT m.*,u.name sender_name FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.conversation_id=:c ORDER BY m.created_at,m.id',['c'=>$id])->fetchAll()];}
    public function messageRecipients(int$instituteId,int$exclude,?array$only=null):array{if($only!==null){$only=array_values(array_unique(array_map('intval',$only)));if(!$only)return[];}$in=$only!==null?' AND u.id IN ('.implode(',',$only).')':'';return$this->db->query('SELECT DISTINCT u.id,u.name,u.email FROM users u WHERE u.id<>:x AND u.status="active"'.$in.' AND (EXISTS(SELECT 1 FROM student_accounts sa JOIN students s ON s.id=sa.student_id WHERE sa.user_id=u.id AND s.institute_id=:i) OR EXISTS(SELECT 1 FROM employee_accounts ea JOIN employees e ON e.id=ea.employee_id WHERE ea.user_id=u.id AND e.institute_id=:i2)) ORDER BY u.name LIMIT 10000',['x'=>$exclude,'i'=>$instituteId,'i2'=>$instituteId])->fetchAll();}

    public function sendMessage(int$instituteId,int$conversation,int$sender,string$body):int
    {if(trim($body)===''||!$this->db->query('SELECT 1 FROM conversations c JOIN conversation_participants p ON p.conversation_id=c.id WHERE c.id=:c AND c.institute_id=:i AND p.user_id=:u',['c'=>$conversation,'i'=>$instituteId,'u'=>$sender])->fetchColumn())throw new DomainException('Conversation access denied.');$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO messages(conversation_id,sender_id,body,created_at) VALUES(:c,:u,:b,:n)',['c'=>$conversation,'u'=>$sender,'b'=>trim($body),'n'=>$now]);$id=(int)$this->db->connection()->lastInsertId();$this->db->query('UPDATE conversations SET updated_at=:n WHERE id=:c',['n'=>$now,'c'=>$conversation]);return$id;}

    public function createLiveClass(int $instituteId,array $data,int $userId):int
    {$type=(string)($data['type']??'');if(!in_array($type,['AS','AT','SC','SS','ST'],true)||trim((string)($data['title']??''))===''||trim((string)($data['code']??''))==='')throw new DomainException('Invalid live class.');$targets=['SC'=>['classes','class'],'SS'=>['students','student'],'ST'=>['employees','teacher']];$class=$student=$employee=null;if(isset($targets[$type])){[$table,$key]=$targets[$type];$id=(int)($data[$key]??0);if(!$id||!$this->owned($table,$id,$instituteId))throw new DomainException('Invalid audience target.');if($type==='SC')$class=$id;if($type==='SS')$student=$id;if($type==='ST')$employee=$id;}$scheduled=null;if(!empty($data['schedule'])){$scheduled=trim((string)($data['date']??'').' '.(string)($data['time']??''));if(!strtotime($scheduled)||strtotime($scheduled)<=time())throw new DomainException('Scheduled time must be in the future.');}$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO live_classes(institute_id,title,room_code,audience_type,target_class_id,target_student_id,target_employee_id,scheduled_at,duration_minutes,message,created_by,created_at,updated_at) VALUES(:i,:t,:r,:a,:c,:s,:e,:at,:d,:m,:u,:n,:n2)',['i'=>$instituteId,'t'=>trim($data['title']),'r'=>trim($data['code']),'a'=>$type,'c'=>$class,'s'=>$student,'e'=>$employee,'at'=>$scheduled,'d'=>(int)($data['duration']??0)?:null,'m'=>trim((string)($data['message']??''))?:null,'u'=>$userId,'n'=>$now,'n2'=>$now]);return(int)$this->db->connection()->lastInsertId();}

    private function owned(string$table,int$id,int$institute):bool{if(!in_array($table,['classes','students','employees','exams'],true))return false;return(bool)$this->db->query("SELECT 1 FROM {$table} WHERE id=:id AND institute_id=:i",['id'=>$id,'i'=>$institute])->fetchColumn();}
    private function subjectInClass(int$subject,int$class):bool{return(bool)$this->db->query('SELECT 1 FROM class_subjects WHERE class_id=:c AND subject_id=:s',['c'=>$class,'s'=>$subject])->fetchColumn();}
    private function userInInstitute(int$user,int$institute):bool{return(bool)$this->db->query('SELECT 1 FROM users u WHERE u.id=:u AND (EXISTS(SELECT 1 FROM student_accounts sa JOIN students s ON s.id=sa.student_id WHERE sa.user_id=u.id AND s.institute_id=:i) OR EXISTS(SELECT 1 FROM employee_accounts ea JOIN employees e ON e.id=ea.employee_id WHERE ea.user_id=u.id AND e.institute_id=:i2) OR EXISTS(SELECT 1 FROM institute_users iu WHERE iu.user_id=u.id AND iu.institute_id=:i3))',['u'=>$user,'i'=>$institute,'i2'=>$institute,'i3'=>$institute])->fetchColumn();}
    // ---- monthly salary sheet ---------------------------------------------------------------

    /**
     * One row per employee on the payroll for $month (active, kept by the admin's rule, deactivated
     * during/after the month, or already paid for it): salary, attendance summary, outstanding
     * advance, a suggested absence deduction, and the payment if one exists.
     */
    public function salarySheet(int $instituteId, string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new DomainException('Invalid month.');
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $rows = $this->db->query(
            "SELECT e.id,e.employee_no,e.name,e.role,e.mobile,e.monthly_salary,e.status,e.status_changed_at,
                    COALESCE(adv.balance,0) advance_balance,
                    COALESCE(att.present,0) present_days,COALESCE(att.absent,0) absent_days,COALESCE(att.leave_days,0) leave_days,
                    COALESCE(att.late,0) late_days,COALESCE(att.half_days,0) half_days,COALESCE(att.marked,0) marked_days,
                    sp.id payment_id,sp.base_salary paid_base,sp.adjustment_amount,sp.deduction_amount,sp.advance_recovered,sp.paid_amount,sp.payment_date,sp.reference,sp.remarks
             FROM employees e
             LEFT JOIN salary_payments sp ON sp.employee_id=e.id AND sp.salary_month=:m
             LEFT JOIN (SELECT employee_id,SUM(amount-recovered_amount) balance FROM salary_advances WHERE institute_id=:i2 AND status='outstanding' GROUP BY employee_id) adv ON adv.employee_id=e.id
             LEFT JOIN (SELECT employee_id,COUNT(*) marked,SUM(status='Present') present,SUM(status='Absent') absent,SUM(status='Leave') leave_days,SUM(status='Late') late,SUM(status='Half Day') half_days
                        FROM employee_attendance WHERE attendance_date BETWEEN :f AND :t GROUP BY employee_id) att ON att.employee_id=e.id
             WHERE e.institute_id=:i AND (" . \App\Support\PersonStatus::visibleSql($this->db, $instituteId, 'employee', 'e', 'billing', ':since') . " OR sp.id IS NOT NULL)
             ORDER BY e.name,e.employee_no",
            ['i' => $instituteId, 'i2' => $instituteId, 'm' => $month, 'f' => $from, 't' => $to, 'since' => $from . ' 00:00:00']
        )->fetchAll();
        $days = (int) date('t', strtotime($from));
        foreach ($rows as &$r) {
            $base = (float) ($r['payment_id'] ? $r['paid_base'] : $r['monthly_salary']);
            $r['base'] = $base;
            $r['day_rate'] = $days > 0 ? round($base / $days, 2) : 0.0;
            // Absent = a full day, Half Day = half a day; Leave and Late are not deducted.
            $r['absence_units'] = (float) $r['absent_days'] + 0.5 * (float) $r['half_days'];
            $r['suggested_deduction'] = round($r['day_rate'] * $r['absence_units'], 2);
            if ($r['payment_id']) {
                $r['bonus'] = round((float) $r['adjustment_amount'] + (float) $r['deduction_amount'] + (float) $r['advance_recovered'], 2);
                $r['deduction'] = (float) $r['deduction_amount'];
                $r['recovery'] = (float) $r['advance_recovered'];
                $r['net'] = (float) $r['paid_amount'];
            } else {
                $r['bonus'] = 0.0;
                $r['deduction'] = 0.0;
                $r['recovery'] = round(min((float) $r['advance_balance'], $base), 2);
                $r['net'] = max(0, round($base - $r['recovery'], 2));
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Changes the bonus / deduction (and optionally date, reference, remarks) of a salary already
     * paid. The advance recovered stays as recorded; the paid amount is recomputed as
     * base + bonus - deduction - advance recovered and must stay above zero.
     * @return array{before:array,after:array}
     */
    public function adjustSalaryPayment(int $paymentId, int $instituteId, float $bonus, float $deduction, ?string $date, ?string $reference, ?string $remarks): array
    {
        if ($bonus < 0 || $deduction < 0) throw new DomainException('Bonus and deduction cannot be negative.');
        if ($date !== null && !$this->date($date)) throw new DomainException('Enter a valid payment date.');
        if ($date !== null && $date > date('Y-m-d')) throw new DomainException('The payment date cannot be in the future.');
        $p = $this->db->query('SELECT sp.* FROM salary_payments sp JOIN employees e ON e.id=sp.employee_id WHERE sp.id=:id AND e.institute_id=:i', ['id' => $paymentId, 'i' => $instituteId])->fetch();
        if (!$p) throw new DomainException('Salary payment not found.');
        $recovery = (float) $p['advance_recovered'];
        $paid = round((float) $p['base_salary'] + $bonus - $deduction - $recovery, 2);
        if ($paid <= 0) throw new DomainException('Net salary must stay above zero (salary ' . number_format((float) $p['base_salary'], 2) . ' + bonus − deduction − advance recovered ' . number_format($recovery, 2) . ').');
        $reference = $reference !== null ? (mb_substr(trim($reference), 0, 100) ?: null) : $p['reference'];
        $remarks = $remarks !== null ? (mb_substr(trim($remarks), 0, 500) ?: null) : $p['remarks'];
        $this->db->query('UPDATE salary_payments SET adjustment_amount=:a,deduction_amount=:d,paid_amount=:p,payment_date=:dt,reference=:r,remarks=:x WHERE id=:id',
            ['a' => round($bonus - $deduction - $recovery, 2), 'd' => round($deduction, 2), 'p' => $paid, 'dt' => $date ?? $p['payment_date'], 'r' => $reference, 'x' => $remarks, 'id' => $paymentId]);
        $after = $this->db->query('SELECT * FROM salary_payments WHERE id=:id', ['id' => $paymentId])->fetch();
        return ['before' => $p, 'after' => $after];
    }

    private function date(string$value):bool{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);return$d!==false&&$d->format('Y-m-d')===$value;}
}
