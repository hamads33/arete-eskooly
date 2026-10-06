<?php
declare(strict_types=1);
namespace App\Support;
use App\Core\Database;
final class FeeReminder{
private const DEFAULT_MESSAGE='Your fee is due. Kindly clear your outstanding dues at your earliest convenience.';
public function __construct(private readonly Database$db){}
public function forStudent(int$s,int$i):?array{$settings=$this->settings($i);if(!$settings['enabled'])return null;$amount=(float)$this->db->query("SELECT COALESCE(SUM(fi.remaining_amount),0) FROM fee_invoices fi JOIN students s ON s.id=fi.student_id WHERE fi.student_id=:s AND s.institute_id=:i AND fi.remaining_amount>0 AND fi.voided_at IS NULL",['s'=>$s,'i'=>$i])->fetchColumn();return$amount>0?['message'=>$settings['message'],'outstanding'=>$amount]:null;}
public function forGuardian(int$g,int$i):?array{$settings=$this->settings($i);if(!$settings['enabled'])return null;$row=$this->db->query("SELECT COALESCE(SUM(fi.remaining_amount),0) outstanding,GROUP_CONCAT(DISTINCT CASE WHEN fi.id IS NOT NULL THEN s.student_name END ORDER BY s.student_name SEPARATOR ', ') student_names FROM guardian_students gs JOIN guardians g ON g.id=gs.guardian_id JOIN students s ON s.id=gs.student_id LEFT JOIN fee_invoices fi ON fi.student_id=s.id AND fi.remaining_amount>0 AND fi.voided_at IS NULL WHERE gs.guardian_id=:g AND g.institute_id=:i AND s.institute_id=:i2",['g'=>$g,'i'=>$i,'i2'=>$i])->fetch();$amount=(float)($row['outstanding']??0);return$amount>0?['message'=>$settings['message'],'outstanding'=>$amount,'student_names'=>$row['student_names']??'']:null;}
private function settings(int$i):array{$row=$this->db->query('SELECT reminder_enabled,reminder_message FROM fee_settings WHERE institute_id=:i',['i'=>$i])->fetch()?:[];return['enabled'=>(int)($row['reminder_enabled']??1)===1,'message'=>trim((string)($row['reminder_message']??''))?:self::DEFAULT_MESSAGE];}
}
