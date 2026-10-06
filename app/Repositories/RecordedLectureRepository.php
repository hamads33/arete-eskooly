<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
/** Recorded lectures: uploaded videos or shared video links, scoped by audience like class notes. */
final class RecordedLectureRepository{
    public function __construct(private readonly Database$db){}
    /** Matches lectures visible to one student; binds :class and :student. */
    private const STUDENT_SCOPE="(l.audience_type='all' OR (l.audience_type='class' AND EXISTS(SELECT 1 FROM recorded_lecture_classes lc WHERE lc.lecture_id=l.id AND lc.class_id=:class)) OR (l.audience_type='student' AND l.student_id=:student))";
    public function all(int$i):array{return$this->db->query('SELECT l.*,s.name subject_name,e.name teacher,st.student_name,(SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ", ") FROM recorded_lecture_classes lc JOIN classes c ON c.id=lc.class_id WHERE lc.lecture_id=l.id) class_names FROM recorded_lectures l LEFT JOIN subjects s ON s.id=l.subject_id LEFT JOIN employees e ON e.id=l.teacher_id LEFT JOIN students st ON st.id=l.student_id WHERE l.institute_id=:i ORDER BY l.publish_date DESC,l.id DESC',['i'=>$i])->fetchAll();}
    public function forStudent(int$i,int$class,int$student):array{return$this->db->query("SELECT l.id,l.subject_id,l.title,l.chapter,l.description,l.video_path,l.video_url,l.publish_date,COALESCE(s.name,'General') subject,e.name teacher FROM recorded_lectures l LEFT JOIN subjects s ON s.id=l.subject_id LEFT JOIN employees e ON e.id=l.teacher_id WHERE l.institute_id=:i AND l.status='published' AND l.publish_date<=:today AND ".self::STUDENT_SCOPE.' ORDER BY l.publish_date DESC,l.id DESC LIMIT 500',['i'=>$i,'class'=>$class,'student'=>$student,'today'=>date('Y-m-d')])->fetchAll();}
    /** Subjects assigned to a class; each becomes a lecture folder even before anything is uploaded. */
    public function classSubjects(int$class):array{return$this->db->query('SELECT s.id,s.name FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=:c ORDER BY s.name',['c'=>$class])->fetchAll();}
    public function studentVideo(int$id,int$i,int$class,int$student):?string{$p=$this->db->query("SELECT l.video_path FROM recorded_lectures l WHERE l.id=:id AND l.institute_id=:i AND l.status='published' AND l.publish_date<=:today AND ".self::STUDENT_SCOPE,['id'=>$id,'i'=>$i,'class'=>$class,'student'=>$student,'today'=>date('Y-m-d')])->fetchColumn();return$p?:null;}
    public function adminVideo(int$id,int$i):?string{$p=$this->db->query('SELECT video_path FROM recorded_lectures WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i])->fetchColumn();return$p?:null;}
    public function save(int$i,array$d):int{
        $audience=in_array($d['audience_type']??'',['all','class','student'],true)?$d['audience_type']:'class';
        $classes=array_values(array_unique(array_filter(array_map('intval',(array)($d['class_ids']??[])))));
        $subject=(int)($d['subject_id']??0);$student=(int)($d['student_id']??0);$teacher=(int)($d['teacher_id']??0);$title=trim((string)($d['title']??''));$url=trim((string)($d['video_url']??''));
        if($title===''||mb_strlen($title)>160||!in_array($d['status']??'',['draft','published'],true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($d['publish_date']??'')))throw new \DomainException('Title, publish date and status are required.');
        if($url!==''&&(mb_strlen($url)>500||!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)))throw new \DomainException('Enter a valid http or https lecture link.');
        if($url===''&&empty($d['video_path']))throw new \DomainException('Upload a video or paste a lecture link.');
        if($audience==='class'){if(!$classes)throw new \DomainException('Select at least one class.');$marks=implode(',',array_fill(0,count($classes),'?'));$stmt=$this->db->connection()->prepare("SELECT COUNT(*) FROM classes WHERE institute_id=? AND id IN ($marks)");$stmt->execute(array_merge([$i],$classes));if((int)$stmt->fetchColumn()!==count($classes))throw new \DomainException('One or more selected classes are invalid.');$student=0;}
        if($audience==='student'){if(!$student||!$this->db->query('SELECT 1 FROM students WHERE id=:id AND institute_id=:i',['id'=>$student,'i'=>$i])->fetchColumn())throw new \DomainException('Select a valid student.');$classes=[];}
        if($audience==='all'){$classes=[];$student=0;}
        if($subject&&!$this->db->query('SELECT 1 FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id JOIN classes c ON c.id=cs.class_id WHERE s.id=:s AND c.institute_id=:i',['s'=>$subject,'i'=>$i])->fetchColumn())throw new \DomainException('Select a valid subject.');
        if($teacher&&!$this->db->query('SELECT 1 FROM employees WHERE id=:id AND institute_id=:i',['id'=>$teacher,'i'=>$i])->fetchColumn())throw new \DomainException('Select a valid teacher.');
        $pdo=$this->db->connection();$pdo->beginTransaction();try{$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO recorded_lectures(institute_id,audience_type,student_id,subject_id,teacher_id,title,chapter,description,video_path,video_url,publish_date,status,created_by,created_at,updated_at) VALUES(:i,:a,:st,:su,:t,:title,:ch,:de,:vp,:vu,:pd,:status,:by,:c,:u)',['i'=>$i,'a'=>$audience,'st'=>$student?:null,'su'=>$subject?:null,'t'=>$teacher?:null,'title'=>$title,'ch'=>$d['chapter']??null,'de'=>$d['description']??null,'vp'=>$d['video_path']??null,'vu'=>$url?:null,'pd'=>$d['publish_date'],'status'=>$d['status'],'by'=>(int)$d['created_by']?:null,'c'=>$now,'u'=>$now]);$id=(int)$pdo->lastInsertId();$map=$pdo->prepare('INSERT INTO recorded_lecture_classes(lecture_id,class_id) VALUES(?,?)');foreach($classes as$c)$map->execute([$id,$c]);$pdo->commit();return$id;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public function delete(int$id,int$i):?string{$p=$this->adminVideo($id,$i);$this->db->query('DELETE FROM recorded_lectures WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i]);return$p;}
}
