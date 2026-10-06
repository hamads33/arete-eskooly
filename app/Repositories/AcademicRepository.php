<?php

declare(strict_types=1);

namespace App\Repositories;
use App\Core\Database;
use PDOException;

final class AcademicRepository
{
    public function __construct(private readonly Database $db) {}
    public function classes(int $instituteId,string $search=''): array
    {
        return $this->db->query('SELECT c.*,COUNT(cs.subject_id) subject_count FROM classes c LEFT JOIN class_subjects cs ON cs.class_id=c.id WHERE c.institute_id=:id AND c.name LIKE :search GROUP BY c.id ORDER BY c.name',['id'=>$instituteId,'search'=>'%'.$search.'%'])->fetchAll();
    }
    public function createClass(int $instituteId,string $name,float $fee,?string $teacher): bool
    {
        try {$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO classes (institute_id,name,monthly_tuition,teacher_reference,created_at,updated_at) VALUES (:id,:name,:fee,:teacher,:created,:updated)',['id'=>$instituteId,'name'=>$name,'fee'=>$fee,'teacher'=>$teacher,'created'=>$now,'updated'=>$now]);return true;} catch(PDOException $e){if($e->getCode()==='23000')return false;throw $e;}
    }
    public function findClass(int $id,int $instituteId): ?array
    {
        $row=$this->db->query('SELECT c.*,COUNT(cs.subject_id) subject_count FROM classes c LEFT JOIN class_subjects cs ON cs.class_id=c.id WHERE c.id=:id AND c.institute_id=:institute GROUP BY c.id',['id'=>$id,'institute'=>$instituteId])->fetch();
        return $row ?: null;
    }
    public function updateClass(int $id,int $instituteId,string $name,float $fee,?string $teacher): string
    {
        try {
            $updated=$this->db->query('UPDATE classes SET name=:name,monthly_tuition=:fee,teacher_reference=:teacher,updated_at=:updated WHERE id=:id AND institute_id=:institute',['name'=>$name,'fee'=>$fee,'teacher'=>$teacher,'updated'=>date('Y-m-d H:i:s'),'id'=>$id,'institute'=>$instituteId])->rowCount();
            return $updated ? 'updated' : ($this->findClass($id,$instituteId) ? 'unchanged' : 'missing');
        } catch (PDOException $e) { if ($e->getCode()==='23000') return 'duplicate'; throw $e; }
    }
    public function deleteClass(int $id,int $instituteId): string
    {
        if (!$this->classExists($id,$instituteId)) return 'missing';
        $references=(int)$this->db->query('SELECT (SELECT COUNT(*) FROM students WHERE class_id=:students_class) + (SELECT COUNT(*) FROM exam_subjects WHERE class_id=:exam_class) + (SELECT COUNT(*) FROM class_tests WHERE class_id=:test_class) + (SELECT COUNT(*) FROM homework WHERE class_id=:homework_class) + (SELECT COUNT(*) FROM student_promotions WHERE from_class_id=:promotion_from OR to_class_id=:promotion_to) + (SELECT COUNT(*) FROM live_classes WHERE target_class_id=:live_class) + (SELECT COUNT(*) FROM live_class_classes WHERE class_id=:shared_live_class)',['students_class'=>$id,'exam_class'=>$id,'test_class'=>$id,'homework_class'=>$id,'promotion_from'=>$id,'promotion_to'=>$id,'live_class'=>$id,'shared_live_class'=>$id])->fetchColumn();
        if ($references > 0) return 'in_use';
        $this->db->query('DELETE FROM classes WHERE id=:id AND institute_id=:institute',['id'=>$id,'institute'=>$instituteId]);
        return 'deleted';
    }
    public function deleteClasses(array $ids,int $instituteId): array
    {
        $result=['deleted'=>0,'blocked'=>0,'missing'=>0];
        foreach(array_unique(array_map('intval',$ids)) as $id){$status=$this->deleteClass($id,$instituteId);if($status==='deleted')$result['deleted']++;elseif($status==='in_use')$result['blocked']++;else $result['missing']++;}
        return $result;
    }
    public function forceDeleteClass(int $id,int $instituteId,int $userId=0): string
    {
        $class=$this->findClass($id,$instituteId);if(!$class)return'missing';$destroyed=$this->countDependents($id);$pdo=$this->db->connection();$pdo->beginTransaction();
        try{
            $students=$this->db->query('SELECT id FROM students WHERE class_id=:class AND institute_id=:institute',['class'=>$id,'institute'=>$instituteId])->fetchAll();
            $this->db->query('DELETE FROM student_promotions WHERE from_class_id=:from_class OR to_class_id=:to_class',['from_class'=>$id,'to_class'=>$id]);
            $examIds=$this->db->query('SELECT id FROM exam_subjects WHERE class_id=:class',['class'=>$id])->fetchAll();foreach($examIds as $exam){$this->db->query('DELETE FROM exam_marks WHERE exam_subject_id=:id',['id'=>$exam['id']]);}$this->db->query('DELETE FROM exam_subjects WHERE class_id=:class',['class'=>$id]);
            $testIds=$this->db->query('SELECT id FROM class_tests WHERE class_id=:class',['class'=>$id])->fetchAll();foreach($testIds as $test){$this->db->query('DELETE FROM class_test_marks WHERE class_test_id=:id',['id'=>$test['id']]);}$this->db->query('DELETE FROM class_tests WHERE class_id=:class',['class'=>$id]);
            $this->db->query('DELETE FROM homework WHERE class_id=:class',['class'=>$id]);$this->db->query('DELETE FROM live_classes WHERE target_class_id=:class',['class'=>$id]);
            foreach($students as $student){$studentId=(int)$student['id'];$this->db->query('DELETE FROM student_promotions WHERE student_id=:student',['student'=>$studentId]);$invoices=$this->db->query('SELECT id FROM fee_invoices WHERE student_id=:student',['student'=>$studentId])->fetchAll();foreach($invoices as $invoice){$this->db->query('DELETE FROM fee_payments WHERE fee_invoice_id=:invoice',['invoice'=>$invoice['id']]);$this->db->query('DELETE FROM fee_invoice_items WHERE fee_invoice_id=:invoice',['invoice'=>$invoice['id']]);}$this->db->query('DELETE FROM fee_invoices WHERE student_id=:student',['student'=>$studentId]);$this->db->query('DELETE FROM students WHERE id=:student AND institute_id=:institute',['student'=>$studentId,'institute'=>$instituteId]);}
            $this->db->query('DELETE FROM class_subjects WHERE class_id=:class',['class'=>$id]);$this->db->query('DELETE FROM classes WHERE id=:class AND institute_id=:institute',['class'=>$id,'institute'=>$instituteId]);$pdo->commit();$this->auditForceDelete($instituteId,$userId,$id,(string)$class['name'],$destroyed);return'force_deleted';
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public function forceDeleteClasses(array $ids,int $instituteId,int $userId=0): int{$deleted=0;foreach(array_unique(array_map('intval',$ids))as$id){if($this->forceDeleteClass($id,$instituteId,$userId)==='force_deleted')$deleted++;}return$deleted;}
    public function classExists(int $id,int $instituteId): bool { return (bool)$this->db->query('SELECT 1 FROM classes WHERE id=:id AND institute_id=:institute',['id'=>$id,'institute'=>$instituteId])->fetchColumn(); }
    public function withSubjects(int $instituteId): array
    {
        $rows=$this->db->query('SELECT c.id,c.name,s.id subject_id,s.name subject_name,cs.total_marks FROM classes c LEFT JOIN class_subjects cs ON cs.class_id=c.id LEFT JOIN subjects s ON s.id=cs.subject_id WHERE c.institute_id=:id ORDER BY c.name,s.name',['id'=>$instituteId])->fetchAll(); $out=[];
        foreach($rows as $r){$out[$r['id']]??=['id'=>$r['id'],'name'=>$r['name'],'subjects'=>[]];if($r['subject_name']!==null)$out[$r['id']]['subjects'][]=['id'=>$r['subject_id'],'name'=>$r['subject_name'],'marks'=>$r['total_marks']];}return array_values($out);
    }
    public function assign(int $instituteId,int $classId,string $name,float $marks): bool
    {
        $pdo=$this->db->connection();$pdo->beginTransaction();try{$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO subjects (institute_id,name,created_at,updated_at) VALUES (:id,:name,:created,:updated) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),updated_at=VALUES(updated_at)',['id'=>$instituteId,'name'=>$name,'created'=>$now,'updated'=>$now]);$subjectId=(int)$pdo->lastInsertId();$exists=$this->db->query('SELECT 1 FROM class_subjects WHERE class_id=:class AND subject_id=:subject',['class'=>$classId,'subject'=>$subjectId])->fetchColumn();if($exists){$pdo->rollBack();return false;}$this->db->query('INSERT INTO class_subjects (class_id,subject_id,total_marks,created_at,updated_at) VALUES (:class,:subject,:marks,:created,:updated)',['class'=>$classId,'subject'=>$subjectId,'marks'=>$marks,'created'=>$now,'updated'=>$now]);$pdo->commit();return true;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    /**
     * Counts what a force delete is about to destroy. Used for the audit record so a wiped
     * class leaves a trace of exactly how much financial and academic history went with it.
     */
    private function countDependents(int $classId): array
    {
        $one = fn(string $sql): int => (int) $this->db->query($sql, ['c' => $classId])->fetchColumn();
        return [
            'students' => $one('SELECT COUNT(*) FROM students WHERE class_id=:c'),
            'fee_invoices' => $one('SELECT COUNT(*) FROM fee_invoices fi JOIN students s ON s.id=fi.student_id WHERE s.class_id=:c'),
            'fee_payments' => $one('SELECT COUNT(*) FROM fee_payments p JOIN students s ON s.id=p.student_id WHERE s.class_id=:c'),
            'exam_marks' => $one('SELECT COUNT(*) FROM exam_marks m JOIN exam_subjects es ON es.id=m.exam_subject_id WHERE es.class_id=:c'),
            'class_test_marks' => $one('SELECT COUNT(*) FROM class_test_marks m JOIN class_tests t ON t.id=m.class_test_id WHERE t.class_id=:c'),
            'attendance' => $one('SELECT COUNT(*) FROM student_attendance a JOIN students s ON s.id=a.student_id WHERE s.class_id=:c'),
        ];
    }

    /** Writes the destructive-action audit row. Never blocks the delete it records. */
    private function auditForceDelete(int $instituteId, int $userId, int $classId, string $className, array $destroyed): void
    {
        try {
            $this->db->query(
                'INSERT INTO audit_logs(institute_id,user_id,action,entity_type,entity_id,metadata_json,created_at) VALUES(:i,:u,:a,:t,:e,:m,:c)',
                [
                    'i' => $instituteId,
                    'u' => $userId ?: null,
                    'a' => 'class.force_delete',
                    't' => 'class',
                    'e' => $classId,
                    'm' => json_encode(['class_name' => $className, 'destroyed' => $destroyed], JSON_UNESCAPED_SLASHES),
                    'c' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            // Auditing must never turn a completed delete into a user-visible failure.
        }
    }

    /** Subjects for the institute with how many classes each is attached to. */
    public function subjects(int $instituteId): array
    {
        return $this->db->query(
            'SELECT s.id, s.name,
                    (SELECT COUNT(*) FROM class_subjects cs WHERE cs.subject_id = s.id) AS class_count
             FROM subjects s WHERE s.institute_id = :i ORDER BY s.name',
            ['i' => $instituteId]
        )->fetchAll();
    }

    public function renameSubject(int $id, int $instituteId, string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            return 'invalid';
        }
        if (!$this->db->query('SELECT 1 FROM subjects WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId])->fetchColumn()) {
            return 'missing';
        }
        if ($this->db->query('SELECT 1 FROM subjects WHERE institute_id=:i AND name=:n AND id<>:id LIMIT 1', ['i' => $instituteId, 'n' => $name, 'id' => $id])->fetchColumn()) {
            return 'duplicate';
        }
        $this->db->query('UPDATE subjects SET name=:n,updated_at=:u WHERE id=:id AND institute_id=:i', ['n' => $name, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $instituteId]);
        return 'updated';
    }

    /**
     * Detaches a subject from one class. Refused when the pairing already carries academic
     * history, so marks and homework can never be orphaned.
     */
    public function unassignSubject(int $classId, int $subjectId, int $instituteId): string
    {
        $owned = $this->db->query(
            'SELECT 1 FROM class_subjects cs JOIN classes c ON c.id=cs.class_id
             WHERE cs.class_id=:c AND cs.subject_id=:s AND c.institute_id=:i',
            ['c' => $classId, 's' => $subjectId, 'i' => $instituteId]
        )->fetchColumn();
        if (!$owned) {
            return 'missing';
        }
        $used = $this->db->query('SELECT 1 FROM exam_subjects WHERE class_id=:c AND subject_id=:s LIMIT 1', ['c' => $classId, 's' => $subjectId])->fetchColumn()
            || $this->db->query('SELECT 1 FROM class_tests WHERE class_id=:c AND subject_id=:s LIMIT 1', ['c' => $classId, 's' => $subjectId])->fetchColumn()
            || $this->db->query('SELECT 1 FROM homework WHERE class_id=:c AND subject_id=:s LIMIT 1', ['c' => $classId, 's' => $subjectId])->fetchColumn();
        if ($used) {
            return 'in_use';
        }
        $this->db->query('DELETE FROM class_subjects WHERE class_id=:c AND subject_id=:s', ['c' => $classId, 's' => $subjectId]);
        return 'removed';
    }

    /** Deletes a subject entirely; refused while any class or record still references it. */
    public function deleteSubject(int $id, int $instituteId): string
    {
        if (!$this->db->query('SELECT 1 FROM subjects WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId])->fetchColumn()) {
            return 'missing';
        }
        foreach (['class_subjects', 'exam_subjects', 'class_tests', 'homework', 'class_notes'] as $table) {
            if ($this->db->query("SELECT 1 FROM {$table} WHERE subject_id=:s LIMIT 1", ['s' => $id])->fetchColumn()) {
                return 'in_use';
            }
        }
        $this->db->query('DELETE FROM subjects WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId]);
        return 'deleted';
    }
}
