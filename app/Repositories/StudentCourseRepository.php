<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Core\Database;

/**
 * "My Courses": one course per subject assigned to the student's class, built entirely from data
 * the LMS already holds. The teacher comes from teacher_assignments (a subject assignment first,
 * otherwise the whole-class teacher). Counts use the same audience rules as the student's
 * lecture, homework, notes and online-test pages, so a card never counts something the student
 * cannot open.
 */
final class StudentCourseRepository
{
    public function __construct(private readonly Database $db) {}

    /** @return list<array> */
    public function courses(array $student): array
    {
        $i = (int) $student['institute_id'];
        $class = (int) $student['class_id'];
        $sid = (int) $student['id'];
        $subjects = $this->db->query('SELECT s.id,s.name,cs.total_marks FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=:c ORDER BY s.name', ['c' => $class])->fetchAll();
        if (!$subjects) {
            return [];
        }
        $teachers = $this->teachers($i, $class);
        $week = date('Y-m-d', strtotime('-7 days'));
        $today = date('Y-m-d');
        $bind = ['i' => $i, 'c' => $class, 's' => $sid];
        $count = function (string $sql, array $extra = []) use ($bind): array {
            $out = [];
            foreach ($this->db->query($sql, $bind + $extra)->fetchAll() as $r) {
                $out[(int) $r['subject_id']] = ['total' => (int) $r['total'], 'new' => (int) $r['fresh']];
            }
            return $out;
        };
        $lectures = $count("SELECT l.subject_id,COUNT(*) total,SUM(l.publish_date>=:w) fresh FROM recorded_lectures l WHERE l.institute_id=:i AND l.status='published' AND l.publish_date<=:t AND l.subject_id IS NOT NULL
            AND (l.audience_type='all' OR (l.audience_type='class' AND EXISTS(SELECT 1 FROM recorded_lecture_classes lc WHERE lc.lecture_id=l.id AND lc.class_id=:c)) OR (l.audience_type='student' AND l.student_id=:s)) GROUP BY l.subject_id", ['w' => $week, 't' => $today]);
        $homework = $count("SELECT h.subject_id,COUNT(*) total,SUM(h.homework_date>=:w) fresh FROM homework h WHERE h.teacher_id IN(SELECT id FROM employees WHERE institute_id=:i) AND h.status='published' AND h.subject_id IS NOT NULL
            AND (h.audience_type='all' OR (h.audience_type='class' AND EXISTS(SELECT 1 FROM homework_classes hc WHERE hc.homework_id=h.id AND hc.class_id=:c)) OR (h.audience_type='student' AND h.student_id=:s)) GROUP BY h.subject_id", ['w' => $week]);
        $notes = $count("SELECT n.subject_id,COUNT(*) total,SUM(n.publish_date>=:w) fresh FROM class_notes n WHERE n.institute_id=:i AND n.status='published' AND n.subject_id IS NOT NULL
            AND (n.audience_type='all' OR (n.audience_type='class' AND EXISTS(SELECT 1 FROM class_note_classes nc WHERE nc.note_id=n.id AND nc.class_id=:c)) OR (n.audience_type='student' AND n.student_id=:s)) GROUP BY n.subject_id", ['w' => $week]);
        $tests = $count("SELECT t.subject_id,COUNT(*) total,SUM(t.status='published' AND (t.closes_at IS NULL OR t.closes_at>=:n1) AND NOT EXISTS(SELECT 1 FROM online_test_attempts a WHERE a.test_id=t.id AND a.student_id=:s AND a.status IN('submitted','marked'))) fresh
            FROM online_tests t WHERE t.institute_id=:i AND t.class_id=:c AND t.status IN('published','closed') AND (t.opens_at IS NULL OR t.opens_at<=:n2) GROUP BY t.subject_id", ['n1' => date('Y-m-d H:i:s'), 'n2' => date('Y-m-d H:i:s')]);
        $announcements = [];
        foreach ($this->db->query("SELECT a.teacher_id,COUNT(*) total FROM announcements a WHERE a.institute_id=:i AND a.teacher_id IS NOT NULL AND a.status='published' AND a.publish_at<=:n1 AND (a.expires_at IS NULL OR a.expires_at>=:n2)
            AND a.audience IN('all','students') AND (a.class_id IS NULL OR a.class_id=:c) GROUP BY a.teacher_id", ['i' => $i, 'c' => $class, 'n1' => date('Y-m-d H:i:s'), 'n2' => date('Y-m-d H:i:s')])->fetchAll() as $r) {
            $announcements[(int) $r['teacher_id']] = (int) $r['total'];
        }
        $out = [];
        foreach ($subjects as $sub) {
            $id = (int) $sub['id'];
            $teacher = $teachers['subject'][$id] ?? $teachers['class'] ?? null;
            $zero = ['total' => 0, 'new' => 0];
            $out[] = [
                'id' => $id, 'name' => $sub['name'], 'total_marks' => $sub['total_marks'],
                'teacher' => $teacher,
                'lectures' => $lectures[$id] ?? $zero,
                'homework' => $homework[$id] ?? $zero,
                'tests' => $tests[$id] ?? $zero,
                'notes' => $notes[$id] ?? $zero,
                'announcements' => $teacher ? ($announcements[(int) $teacher['id']] ?? 0) : 0,
                'live' => null,
            ];
        }
        return $out;
    }

    /** Subject teachers by subject id, plus the whole-class teacher as the fallback. */
    private function teachers(int $institute, int $class): array
    {
        $rows = $this->db->query("SELECT ta.subject_id,ta.assignment_type,e.id,e.name,e.picture_path,e.education,e.role FROM teacher_assignments ta JOIN employees e ON e.id=ta.employee_id
            WHERE ta.institute_id=:i AND ta.class_id=:c AND ta.status='active' AND e.status='active' ORDER BY ta.id", ['i' => $institute, 'c' => $class])->fetchAll();
        $out = ['subject' => [], 'class' => null];
        foreach ($rows as $r) {
            $t = ['id' => (int) $r['id'], 'name' => $r['name'], 'picture' => $r['picture_path'] ?: null, 'education' => $r['education'] ?: null, 'role' => $r['role'] ?: null];
            if ($r['subject_id'] !== null) {
                $out['subject'][(int) $r['subject_id']] ??= $t;
            } elseif ($out['class'] === null) {
                $out['class'] = $t;
            }
        }
        return $out;
    }
}
