<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
final class DashboardRepository
{
    public function __construct(private readonly Database $db) {}
    public function summary(int $institute): array
    {
        $students=(int)$this->db->query("SELECT COUNT(*) FROM students WHERE institute_id=:i AND status='active'",['i'=>$institute])->fetchColumn();
        $employees=(int)$this->db->query("SELECT COUNT(*) FROM employees WHERE institute_id=:i AND status='active'",['i'=>$institute])->fetchColumn();
        $months=[];$income=[];$expense=[];$start=new \DateTimeImmutable('first day of -5 months');
        for($n=0;$n<6;$n++){ $date=$start->modify("+{$n} months");$key=$date->format('Y-m');$months[]=$date->format('M');$income[$key]=0;$expense[$key]=0; }
        $rows=$this->db->query("SELECT DATE_FORMAT(transaction_date,'%Y-%m') ym,transaction_type,SUM(amount) total FROM account_transactions WHERE institute_id=:i AND transaction_date>=:from GROUP BY ym,transaction_type",['i'=>$institute,'from'=>$start->format('Y-m-01')])->fetchAll();
        foreach($rows as $row){if(!isset($income[$row['ym']]))continue;${$row['transaction_type']}[$row['ym']]=(float)$row['total'];}
        $incomes=array_values($income);$expenses=array_values($expense);$revenue=array_sum($incomes);$costs=array_sum($expenses);
        $fee=$this->db->query("SELECT COALESCE(SUM(total_amount),0) total,COALESCE(SUM(paid_amount),0) paid,COALESCE(SUM(remaining_amount),0) remaining FROM fee_invoices i JOIN students s ON s.id=i.student_id WHERE s.institute_id=:i",['i'=>$institute])->fetch()?:['total'=>0,'paid'=>0,'remaining'=>0];
        $today=date('Y-m-d');$present=$this->countAttendance('student_attendance','student_id',$institute,$today,'Present');$absent=$this->countAttendance('student_attendance','student_id',$institute,$today,'Absent');$staffPresent=$this->countAttendance('employee_attendance','employee_id',$institute,$today,'Present');
        $classes=$this->db->query("SELECT c.name,COUNT(s.id) strength FROM classes c LEFT JOIN students s ON s.class_id=c.id AND s.status='active' WHERE c.institute_id=:i GROUP BY c.id,c.name ORDER BY c.name",['i'=>$institute])->fetchAll();
        $admissions=$this->db->query("SELECT s.student_name,s.registration_no,c.name class_name,s.date_of_admission FROM students s JOIN classes c ON c.id=s.class_id WHERE s.institute_id=:i AND s.date_of_admission>=:from ORDER BY s.date_of_admission DESC,s.id DESC LIMIT 8",['i'=>$institute,'from'=>date('Y-m-01')])->fetchAll();
        return compact('students','employees','months','incomes','expenses','revenue','costs','fee','present','absent','staffPresent','classes','admissions');
    }
    private function countAttendance(string$table,string$person,int$institute,string$date,string$status):int{return(int)$this->db->query("SELECT COUNT(*) FROM {$table} a JOIN ".($table==='student_attendance'?'students':'employees')." p ON p.id=a.{$person} WHERE p.institute_id=:i AND a.attendance_date=:d AND a.status=:s",['i'=>$institute,'d'=>$date,'s'=>$status])->fetchColumn();}
}
