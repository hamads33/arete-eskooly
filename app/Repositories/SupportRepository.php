<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
use App\Support\Paginator;
final class SupportRepository{
public function __construct(private readonly Database$db){}
public function students(int$i,string$q='',int$class=0,int$page=1,int$per=25):array{$where='s.institute_id=:i AND (s.student_name LIKE :q OR s.registration_no LIKE :q2)'.($class?' AND s.class_id=:c':'');$p=['i'=>$i,'q'=>'%'.$q.'%','q2'=>'%'.$q.'%'];if($class)$p['c']=$class;$total=(int)$this->db->query('SELECT COUNT(*) FROM students s WHERE '.$where,$p)->fetchColumn();$pager=new Paginator($total,$per,max(1,$page));$rows=$this->db->query('SELECT s.*,c.name class_name,COALESCE((SELECT SUM(fi.remaining_amount) FROM fee_invoices fi WHERE fi.student_id=s.id),0) fee_remaining FROM students s JOIN classes c ON c.id=s.class_id WHERE '.$where.' ORDER BY s.student_name LIMIT '.$per.' OFFSET '.$pager->offset(),$p)->fetchAll();return['rows'=>$rows,'paginator'=>$pager];}
public function accounts(string$type,int$i):array{$domain=$type==='student'?'students':'employees';$link=$type.'_accounts';$fk=$type.'_id';$name=$type==='student'?'student_name':'name';return$this->db->query("SELECT d.id,d.{$name} display_name,a.user_id,a.password_cipher,u.email,u.status,a.created_at FROM {$domain} d LEFT JOIN {$link} a ON a.{$fk}=d.id LEFT JOIN users u ON u.id=a.user_id WHERE d.institute_id=:i ORDER BY d.{$name} LIMIT 200",['i'=>$i])->fetchAll();}
public function promotions(int$i):array{return$this->db->query('SELECT p.*,s.registration_no,s.student_name,f.name from_class,t.name to_class,u.name promoted_by_name FROM student_promotions p JOIN students s ON s.id=p.student_id JOIN classes f ON f.id=p.from_class_id JOIN classes t ON t.id=p.to_class_id JOIN users u ON u.id=p.promoted_by WHERE s.institute_id=:i ORDER BY p.promoted_at DESC LIMIT 100',['i'=>$i])->fetchAll();}
public function subjects(int$i,int$class=0):array{$sql='SELECT s.id,s.name,cs.class_id,cs.total_marks FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id JOIN classes c ON c.id=cs.class_id WHERE c.institute_id=:i'.($class?' AND c.id=:c':'').' ORDER BY s.name';$p=['i'=>$i];if($class)$p['c']=$class;return$this->db->query($sql,$p)->fetchAll();}
public function admissionReport(int$i,string$q,int$class,int$page):array{return$this->students($i,$q,$class,$page,25);}
public function contactReport(int$i,string$q,int$class,int$page):array{return$this->students($i,$q,$class,$page,25);}
}
