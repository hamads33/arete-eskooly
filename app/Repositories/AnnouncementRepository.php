<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
/**
 * School announcements (teacher_id NULL) and teacher announcements (teacher_id set, posted by the
 * teacher or by the admin in the teacher's name). class_id, when set, limits delivery to that
 * class's students and their parents.
 */
final class AnnouncementRepository{
private const SELECT='SELECT a.*,e.name teacher_name,c.name class_name,u.name author_name,EXISTS(SELECT 1 FROM employee_accounts ea WHERE ea.user_id=a.created_by AND ea.employee_id=a.teacher_id) by_teacher FROM announcements a LEFT JOIN employees e ON e.id=a.teacher_id LEFT JOIN classes c ON c.id=a.class_id LEFT JOIN users u ON u.id=a.created_by';
public function __construct(private readonly Database$db){}
public function all(int$i):array{return$this->db->query(self::SELECT.' WHERE a.institute_id=:i ORDER BY a.publish_at DESC,a.id DESC',['i'=>$i])->fetchAll();}
public function forTeacher(int$i,int$teacher):array{return$this->db->query(self::SELECT.' WHERE a.institute_id=:i AND a.teacher_id=:t ORDER BY a.publish_at DESC,a.id DESC',['i'=>$i,'t'=>$teacher])->fetchAll();}
public function create(int$i,array$d,int$user):int{
    $audience=in_array($d['audience']??'',['all','students','parents','employees','teachers'],true)?$d['audience']:'all';$status=in_array($d['status']??'',['draft','published'],true)?$d['status']:'draft';$title=trim((string)($d['title']??''));$message=trim((string)($d['message']??''));if($title===''||$message==='')throw new \DomainException('Title and announcement message are required.');
    $teacher=(int)($d['teacher_id']??0);$class=(int)($d['class_id']??0);
    if($teacher&&!$this->db->query('SELECT 1 FROM employees WHERE id=:id AND institute_id=:i',['id'=>$teacher,'i'=>$i])->fetchColumn())throw new \DomainException('Select a valid teacher.');
    if($class&&!$this->db->query('SELECT 1 FROM classes WHERE id=:id AND institute_id=:i',['id'=>$class,'i'=>$i])->fetchColumn())throw new \DomainException('Select a valid class.');
    // A class announcement only reaches that class's students and parents.
    if($class&&!in_array($audience,['all','students','parents'],true))throw new \DomainException('A class announcement can go to students, parents, or both.');
    $publish=$this->date($d['publish_at']??null)??date('Y-m-d H:i:s');$expires=$this->date($d['expires_at']??null);if($expires&&$expires<=$publish)throw new \DomainException('Expiry must be after the publish time.');$now=date('Y-m-d H:i:s');
    $this->db->query('INSERT INTO announcements(institute_id,title,message,audience,teacher_id,class_id,image_path,show_popup,status,publish_at,expires_at,created_by,created_at,updated_at) VALUES(:i,:t,:m,:a,:te,:cl,:img,:popup,:s,:p,:e,:u,:n,:n2)',['i'=>$i,'t'=>$title,'m'=>$message,'a'=>$audience,'te'=>$teacher?:null,'cl'=>$class?:null,'img'=>$d['image_path']??null,'popup'=>!empty($d['show_popup'])?1:0,'s'=>$status,'p'=>$publish,'e'=>$expires,'u'=>$user,'n'=>$now,'n2'=>$now]);return(int)$this->db->connection()->lastInsertId();
}
/** @param list<int> $classIds classes the viewer belongs to; class announcements outside them are hidden */
public function visible(int$i,string$audience,array$classIds=[]):array{
    $targets=$audience==='teacher'?['all','employees','teachers']:['all',$audience==='student'?'students':($audience==='parent'?'parents':'employees')];
    $classIds=array_values(array_unique(array_filter(array_map('intval',$classIds))));
    $marks=implode(',',array_fill(0,count($targets),'?'));$classSql=$classIds?' OR a.class_id IN ('.implode(',',array_fill(0,count($classIds),'?')).')':'';$now=date('Y-m-d H:i:s');
    $stmt=$this->db->connection()->prepare(self::SELECT." WHERE a.institute_id=? AND a.status='published' AND a.publish_at<=? AND (a.expires_at IS NULL OR a.expires_at>=?) AND a.audience IN ($marks) AND (a.class_id IS NULL{$classSql}) ORDER BY a.publish_at DESC,a.id DESC");
    $stmt->execute(array_merge([$i,$now,$now],$targets,$classIds));return$stmt->fetchAll();
}
/** Deletes an announcement; $teacher restricts the delete to that teacher's own announcements. */
public function delete(int$i,int$id,?int$teacher=null):?string{$sql=' WHERE id=:id AND institute_id=:i'.($teacher!==null?' AND teacher_id=:t':'');$p=['id'=>$id,'i'=>$i]+($teacher!==null?['t'=>$teacher]:[]);$r=$this->db->query('SELECT image_path FROM announcements'.$sql,$p)->fetch();if(!$r)return null;$this->db->query('DELETE FROM announcements'.$sql,$p);return$r['image_path']??'';}
private function date(mixed$v):?string{if(!$v)return null;$t=strtotime((string)$v);return$t?date('Y-m-d H:i:s',$t):null;}
}
