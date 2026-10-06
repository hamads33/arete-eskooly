<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;

/**
 * Pop-up alerts for the student portal: newly published lectures and homework, and unpaid fees.
 * Lectures and homework alert once. An unpaid fee alerts again each day until it is cleared,
 * so dismissing the pop-up does not silence a real due.
 */
final class StudentNotifications
{
    private const RECENT_DAYS = 14;

    public function __construct(private readonly Database$db){}

    /** @return list<array{key:string,type:string,title:string,detail:string,url:string}> */
    public function unread(int$userId,array$student,string$currency='Rs'):array
    {
        $items=[];$since=date('Y-m-d',strtotime('-'.self::RECENT_DAYS.' days'));$today=date('Y-m-d');
        $class=(int)$student['class_id'];$id=(int)$student['id'];$institute=(int)$student['institute_id'];
        try{
            $rows=$this->db->query("SELECT l.id,l.title,l.subject_id,COALESCE(s.name,'General') subject FROM recorded_lectures l LEFT JOIN subjects s ON s.id=l.subject_id WHERE l.institute_id=:i AND l.status='published' AND l.publish_date BETWEEN :since AND :today AND (l.audience_type='all' OR (l.audience_type='class' AND EXISTS(SELECT 1 FROM recorded_lecture_classes lc WHERE lc.lecture_id=l.id AND lc.class_id=:class)) OR (l.audience_type='student' AND l.student_id=:student)) ORDER BY l.publish_date DESC,l.id DESC LIMIT 20",['i'=>$institute,'since'=>$since,'today'=>$today,'class'=>$class,'student'=>$id])->fetchAll();
            foreach($rows as$r)$items[]=['key'=>'lecture:'.$r['id'],'type'=>'lecture','title'=>'New lecture: '.$r['title'],'detail'=>$r['subject'],'url'=>url('/portal/student/recorded-lectures/'.$r['id'])];
        }catch(\PDOException){/* recorded lectures not installed yet */}
        $rows=$this->db->query("SELECT h.id,h.homework_date,h.homework_text,COALESCE(s.name,'General') subject FROM homework h LEFT JOIN subjects s ON s.id=h.subject_id WHERE h.teacher_id IN(SELECT id FROM employees WHERE institute_id=:i) AND h.status='published' AND h.homework_date>=:since AND (h.audience_type='all' OR (h.audience_type='class' AND EXISTS(SELECT 1 FROM homework_classes hc WHERE hc.homework_id=h.id AND hc.class_id=:class)) OR (h.audience_type='student' AND h.student_id=:student)) ORDER BY h.homework_date DESC,h.id DESC LIMIT 20",['i'=>$institute,'since'=>$since,'class'=>$class,'student'=>$id])->fetchAll();
        foreach($rows as$r)$items[]=['key'=>'homework:'.$r['id'],'type'=>'homework','title'=>'New homework: '.$r['subject'],'detail'=>mb_strimwidth(trim((string)$r['homework_text']),0,90,'…'),'url'=>url('/portal/student/homework')];
        $rows=$this->db->query("SELECT id,fee_month,remaining_amount,due_date FROM fee_invoices WHERE student_id=:s AND voided_at IS NULL AND status IN('unpaid','partial') AND remaining_amount>0 ORDER BY fee_month",['s'=>$id])->fetchAll();
        foreach($rows as$r){$due=$r['due_date']?' · due '.date('d M Y',strtotime($r['due_date'])):'';$overdue=$r['due_date']&&$r['due_date']<$today;$items[]=['key'=>'fee:'.$r['id'].':'.$today,'type'=>'fee','title'=>($overdue?'Fee overdue: ':'Fee due: ').date('F Y',strtotime($r['fee_month'].'-01')),'detail'=>$currency.' '.number_format((float)$r['remaining_amount']).' remaining'.$due,'url'=>url('/portal/student/fees/'.$r['id'])];}
        if(!$items)return[];
        $read=$this->db->query('SELECT item_key FROM portal_notification_reads WHERE user_id=:u',['u'=>$userId])->fetchAll(\PDO::FETCH_COLUMN);
        $read=array_flip($read);
        return array_values(array_filter($items,static fn($n)=>!isset($read[$n['key']])));
    }

    public function markRead(int$userId,array$keys):void
    {
        $stmt=$this->db->connection()->prepare('INSERT IGNORE INTO portal_notification_reads(user_id,item_key,read_at) VALUES(?,?,?)');$now=date('Y-m-d H:i:s');
        foreach(array_slice($keys,0,100) as$k){$k=(string)$k;if(preg_match('/^(lecture|homework):\d+$|^fee:\d+:\d{4}-\d{2}-\d{2}$/',$k))$stmt->execute([$userId,$k,$now]);}
        // Keep the table small: day-stamped fee keys are useless once the day has passed.
        $this->db->query("DELETE FROM portal_notification_reads WHERE user_id=:u AND item_key LIKE 'fee:%' AND read_at<:cut",['u'=>$userId,'cut'=>date('Y-m-d 00:00:00')]);
    }
}
