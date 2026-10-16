<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DomainException;

/**
 * Exam Center: an exam ("Weekly Test No 02") holds class + subject papers (exam_subjects) and
 * the marks entered on them (exam_marks). This adds who sits the exam, a review / publish state
 * per paper and consolidated results, on top of the existing tables.
 *
 * Who sits an exam (its roster):
 *   active students of the exam's classes (exam_classes, plus the classes of its papers for
 *   exams made before this existed), minus excluded students, plus individually added students
 *   (each sitting the papers of one chosen class). exam_students holds one row per student per
 *   exam, so a student can never be counted twice.
 *
 * A paper's result is visible to students and parents once the paper is published, or when the
 * whole exam has the existing "published" / "locked" status.
 */
final class ExamCenterRepository
{
    public function __construct(private readonly Database $db) {}

    // ---------------------------------------------------------------- settings

    /** @return array{teachers_add_papers:bool,teachers_publish:bool} */
    public function settings(int $institute): array
    {
        $row = $this->db->query('SELECT teachers_add_papers,teachers_publish FROM exam_settings WHERE institute_id=:i', ['i' => $institute])->fetch();
        return ['teachers_add_papers' => (bool) ($row['teachers_add_papers'] ?? false), 'teachers_publish' => (bool) ($row['teachers_publish'] ?? false)];
    }

    public function saveSettings(int $institute, bool $addPapers, bool $publish, int $user): void
    {
        $this->db->query('INSERT INTO exam_settings(institute_id,teachers_add_papers,teachers_publish,updated_by,updated_at) VALUES(:i,:a,:p,:u,:n)
            ON DUPLICATE KEY UPDATE teachers_add_papers=VALUES(teachers_add_papers),teachers_publish=VALUES(teachers_publish),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',
            ['i' => $institute, 'a' => $addPapers ? 1 : 0, 'p' => $publish ? 1 : 0, 'u' => $user, 'n' => date('Y-m-d H:i:s')]);
    }

    /** True when the institute has grading set up (Marks Grading or a rule profile with grades). */
    public function hasGrading(int $institute): bool
    {
        if ($this->db->query('SELECT 1 FROM grading_rules WHERE institute_id=:i LIMIT 1', ['i' => $institute])->fetchColumn()) return true;
        return (bool) $this->db->query('SELECT 1 FROM academic_profile_grades pg JOIN academic_rule_profiles p ON p.id=pg.profile_id WHERE p.institute_id=:i LIMIT 1', ['i' => $institute])->fetchColumn();
    }

    // ---------------------------------------------------------------- exams

    public function exam(int $institute, int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM exams WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $institute])->fetch();
        return $row ?: null;
    }

    /**
     * Every exam with its counts, newest first. For a teacher, $paperIds limits the counts to their
     * papers and only exams with one of their papers or classes ($classIds) are listed.
     */
    public function exams(int $institute, ?array $paperIds = null, array $classIds = []): array
    {
        $exams = $this->db->query('SELECT * FROM exams WHERE institute_id=:i ORDER BY start_date DESC,id DESC LIMIT 200', ['i' => $institute])->fetchAll();
        $out = [];
        foreach ($exams as $exam) {
            $papers = $this->papers($institute, (int) $exam['id']);
            if ($paperIds !== null) {
                $papers = array_values(array_filter($papers, static fn ($p) => in_array((int) $p['id'], $paperIds, true)));
                if ($papers === [] && !array_intersect($classIds, $this->classIds((int) $exam['id']))) continue;
            }
            $exam['classes'] = $this->classes((int) $exam['id']);
            $exam['student_count'] = count($this->roster($institute, (int) $exam['id']));
            $exam['paper_count'] = count($papers);
            $exam['complete_papers'] = count(array_filter($papers, static fn ($p) => $p['roster'] > 0 && $p['marked'] >= $p['roster']));
            $exam['published_papers'] = count(array_filter($papers, static fn ($p) => $p['visible']));
            $exam['submitted_papers'] = count(array_filter($papers, static fn ($p) => $p['marks_status'] === 'submitted' && !$p['visible']));
            $exam['subject_count'] = count(array_unique(array_column($papers, 'subject_id')));
            $out[] = $exam;
        }
        return $out;
    }

    public function create(int $institute, string $name, string $start, string $end, int $user, array $classIds = []): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) throw new DomainException('Enter the exam name.');
        if (!$this->date($start) || !$this->date($end) || $start > $end) throw new DomainException('Enter valid dates: the end date cannot be before the start date.');
        if ($this->db->query('SELECT 1 FROM exams WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => $name])->fetchColumn()) throw new DomainException('An exam with this name already exists.');
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $this->db->query('INSERT INTO exams(institute_id,name,start_date,end_date,created_by,created_at,updated_at) VALUES(:i,:n,:s,:e,:u,:c,:c2)', ['i' => $institute, 'n' => $name, 's' => $start, 'e' => $end, 'u' => $user, 'c' => $now, 'c2' => $now]);
            $id = (int) $pdo->lastInsertId();
            foreach ($classIds as $class) $this->addClass($institute, $id, (int) $class, $user);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $institute, int $id, string $name, string $start, string $end): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) throw new DomainException('Enter the exam name.');
        if (!$this->date($start) || !$this->date($end) || $start > $end) throw new DomainException('Enter valid dates: the end date cannot be before the start date.');
        if ($this->db->query('SELECT 1 FROM exams WHERE institute_id=:i AND name=:n AND id<>:id', ['i' => $institute, 'n' => $name, 'id' => $id])->fetchColumn()) throw new DomainException('An exam with this name already exists.');
        $this->db->query('UPDATE exams SET name=:n,start_date=:s,end_date=:e,updated_at=:u WHERE id=:id AND institute_id=:i', ['n' => $name, 's' => $start, 'e' => $end, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute]);
    }

    // ---------------------------------------------------------------- classes and students

    /** Classes taking the exam: the ones added to it, and the classes of its papers. */
    public function classes(int $examId): array
    {
        return $this->db->query('SELECT c.id,c.name,MAX(x.added) added FROM (
                SELECT class_id,1 added FROM exam_classes WHERE exam_id=:e1
                UNION ALL SELECT class_id,0 FROM exam_subjects WHERE exam_id=:e2
            ) x JOIN classes c ON c.id=x.class_id GROUP BY c.id,c.name ORDER BY c.name', ['e1' => $examId, 'e2' => $examId])->fetchAll();
    }

    private function classIds(int $examId): array
    {
        return array_map('intval', array_column($this->classes($examId), 'id'));
    }

    public function addClass(int $institute, int $examId, int $classId, int $user): void
    {
        if (!$this->exam($institute, $examId)) throw new DomainException('Exam not found.');
        if (!$this->db->query('SELECT 1 FROM classes WHERE id=:c AND institute_id=:i', ['c' => $classId, 'i' => $institute])->fetchColumn()) throw new DomainException('Choose a valid class.');
        $this->db->query('INSERT IGNORE INTO exam_classes(exam_id,class_id,created_by,created_at) VALUES(:e,:c,:u,:n)', ['e' => $examId, 'c' => $classId, 'u' => $user, 'n' => date('Y-m-d H:i:s')]);
    }

    /** A class can be taken off an exam until it has papers or added students. */
    public function removeClass(int $institute, int $examId, int $classId): void
    {
        if (!$this->exam($institute, $examId)) throw new DomainException('Exam not found.');
        if ($this->db->query('SELECT 1 FROM exam_subjects WHERE exam_id=:e AND class_id=:c LIMIT 1', ['e' => $examId, 'c' => $classId])->fetchColumn()) throw new DomainException('This class already has papers in the exam. Delete its papers first.');
        if ($this->db->query("SELECT 1 FROM exam_students WHERE exam_id=:e AND class_id=:c AND mode='include' LIMIT 1", ['e' => $examId, 'c' => $classId])->fetchColumn()) throw new DomainException('Some added students sit this class\'s papers. Remove them first.');
        $this->db->query('DELETE FROM exam_classes WHERE exam_id=:e AND class_id=:c', ['e' => $examId, 'c' => $classId]);
    }

    /**
     * Everyone sitting the exam (or one class's papers of it): [id, registration_no, student_name,
     * class_id (whose papers they sit), class_name, own_class_name, source 'class' | 'added'].
     */
    public function roster(int $institute, int $examId, int $classId = 0): array
    {
        $classes = $this->classIds($examId);
        if ($classId) $classes = array_values(array_intersect($classes, [$classId]));
        $rows = [];
        if ($classes) {
            $in = implode(',', array_map('intval', $classes));
            $rows = $this->db->query("SELECT st.id,st.registration_no,st.student_name,st.class_id,c.name class_name,c.name own_class_name,'class' source
                FROM students st JOIN classes c ON c.id=st.class_id
                WHERE st.institute_id=:i AND st.status='active' AND st.class_id IN ({$in})
                AND NOT EXISTS(SELECT 1 FROM exam_students es WHERE es.exam_id=:e AND es.student_id=st.id)", ['i' => $institute, 'e' => $examId])->fetchAll();
        }
        $added = $this->db->query("SELECT st.id,st.registration_no,st.student_name,es.class_id,c.name class_name,oc.name own_class_name,'added' source
            FROM exam_students es JOIN students st ON st.id=es.student_id JOIN classes c ON c.id=es.class_id LEFT JOIN classes oc ON oc.id=st.class_id
            WHERE es.exam_id=:e AND es.mode='include' AND st.institute_id=:i AND st.status='active'" . ($classId ? ' AND es.class_id=:c' : ''),
            ['e' => $examId, 'i' => $institute] + ($classId ? ['c' => $classId] : []))->fetchAll();
        $rows = array_merge($rows, $added);
        usort($rows, static fn ($a, $b) => [$a['class_name'], $a['student_name'], $a['registration_no']] <=> [$b['class_name'], $b['student_name'], $b['registration_no']]);
        return $rows;
    }

    public function excluded(int $institute, int $examId): array
    {
        return $this->db->query("SELECT st.id,st.registration_no,st.student_name,c.name class_name FROM exam_students es JOIN students st ON st.id=es.student_id LEFT JOIN classes c ON c.id=st.class_id WHERE es.exam_id=:e AND es.mode='exclude' AND st.institute_id=:i ORDER BY st.student_name", ['e' => $examId, 'i' => $institute])->fetchAll();
    }

    /**
     * Adds students individually; each sits the papers of $classId (one of the exam's classes).
     * A student already in the roster through their class is moved, never duplicated.
     *
     * @return int students added
     */
    public function includeStudents(int $institute, int $examId, array $studentIds, int $classId, int $user): int
    {
        if (!$this->exam($institute, $examId)) throw new DomainException('Exam not found.');
        if (!in_array($classId, $this->classIds($examId), true)) throw new DomainException('Add the class to the exam first, then choose it for these students.');
        $count = 0;
        foreach (array_unique(array_map('intval', $studentIds)) as $sid) {
            $student = $this->db->query("SELECT id,class_id FROM students WHERE id=:s AND institute_id=:i AND status='active'", ['s' => $sid, 'i' => $institute])->fetch();
            if (!$student) continue;
            if ((int) $student['class_id'] === $classId) {
                // Their own class already brings them in: just clear any exclusion.
                $this->db->query('DELETE FROM exam_students WHERE exam_id=:e AND student_id=:s', ['e' => $examId, 's' => $sid]);
            } else {
                $this->db->query("INSERT INTO exam_students(exam_id,student_id,mode,class_id,created_by,created_at) VALUES(:e,:s,'include',:c,:u,:n)
                    ON DUPLICATE KEY UPDATE mode='include',class_id=VALUES(class_id),created_by=VALUES(created_by),created_at=VALUES(created_at)",
                    ['e' => $examId, 's' => $sid, 'c' => $classId, 'u' => $user, 'n' => date('Y-m-d H:i:s')]);
            }
            $count++;
        }
        return $count;
    }

    /** Takes a student out of the exam; marks already entered stay but are no longer shown. */
    public function excludeStudent(int $institute, int $examId, int $studentId, int $user): void
    {
        if (!$this->exam($institute, $examId)) throw new DomainException('Exam not found.');
        if (!$this->db->query('SELECT 1 FROM students WHERE id=:s AND institute_id=:i', ['s' => $studentId, 'i' => $institute])->fetchColumn()) throw new DomainException('Student not found.');
        $this->db->query("INSERT INTO exam_students(exam_id,student_id,mode,class_id,created_by,created_at) VALUES(:e,:s,'exclude',NULL,:u,:n)
            ON DUPLICATE KEY UPDATE mode='exclude',class_id=NULL,created_by=VALUES(created_by),created_at=VALUES(created_at)",
            ['e' => $examId, 's' => $studentId, 'u' => $user, 'n' => date('Y-m-d H:i:s')]);
    }

    /** Undoes an exclusion or an individual addition. */
    public function resetStudent(int $institute, int $examId, int $studentId): void
    {
        if (!$this->exam($institute, $examId)) throw new DomainException('Exam not found.');
        $this->db->query('DELETE FROM exam_students WHERE exam_id=:e AND student_id=:s', ['e' => $examId, 's' => $studentId]);
    }

    /** Active students not sitting the exam, for the "add students" picker. */
    public function candidates(int $institute, int $examId, string $search, int $classId = 0): array
    {
        $in = array_map('array_values', [array_column($this->roster($institute, $examId), 'id')])[0];
        $sql = "SELECT st.id,st.registration_no,st.student_name,c.name class_name FROM students st LEFT JOIN classes c ON c.id=st.class_id WHERE st.institute_id=:i AND st.status='active'";
        $p = ['i' => $institute];
        if ($search !== '') { $sql .= ' AND (st.student_name LIKE :q OR st.registration_no LIKE :q2)'; $p['q'] = '%' . $search . '%'; $p['q2'] = '%' . $search . '%'; }
        if ($classId) { $sql .= ' AND st.class_id=:c'; $p['c'] = $classId; }
        $rows = $this->db->query($sql . ' ORDER BY c.name,st.student_name LIMIT 300', $p)->fetchAll();
        return array_values(array_filter($rows, static fn ($r) => !in_array((int) $r['id'], array_map('intval', $in), true)));
    }

    // ---------------------------------------------------------------- papers

    /** Papers of an exam with marking progress. */
    public function papers(int $institute, int $examId, int $classId = 0): array
    {
        $exam = $this->exam($institute, $examId);
        if (!$exam) return [];
        $rows = $this->db->query('SELECT es.*,c.name class_name,s.name subject_name FROM exam_subjects es JOIN classes c ON c.id=es.class_id JOIN subjects s ON s.id=es.subject_id WHERE es.exam_id=:e' . ($classId ? ' AND es.class_id=:c' : '') . ' ORDER BY c.name,s.name',
            ['e' => $examId] + ($classId ? ['c' => $classId] : []))->fetchAll();
        $rosters = [];
        foreach ($rows as &$row) {
            $cid = (int) $row['class_id'];
            $rosters[$cid] ??= array_map('intval', array_column($this->roster($institute, $examId, $cid), 'id'));
            $ids = $rosters[$cid];
            $row['roster'] = count($ids);
            $row['marked'] = $ids ? (int) $this->db->query('SELECT COUNT(*) FROM exam_marks WHERE exam_subject_id=:p AND student_id IN (' . implode(',', $ids) . ')', ['p' => $row['id']])->fetchColumn() : 0;
            $row['visible'] = $row['published_at'] !== null || in_array($exam['status'], ['published', 'locked'], true);
            $row['exam_status'] = $exam['status'];
        }
        unset($row);
        return $rows;
    }

    public function paper(int $institute, int $paperId): ?array
    {
        $row = $this->db->query('SELECT es.*,e.name exam_name,e.status exam_status,e.start_date,e.end_date,c.name class_name,s.name subject_name FROM exam_subjects es JOIN exams e ON e.id=es.exam_id JOIN classes c ON c.id=es.class_id JOIN subjects s ON s.id=es.subject_id WHERE es.id=:p AND e.institute_id=:i', ['p' => $paperId, 'i' => $institute])->fetch();
        if (!$row) return null;
        $row['visible'] = $row['published_at'] !== null || in_array($row['exam_status'], ['published', 'locked'], true);
        return $row;
    }

    /**
     * Adds papers for one class and one or more subjects (subjects taught in that class only).
     * The class joins the exam if it was not in it. Existing papers are left unchanged.
     *
     * @return array{0:int,1:list<string>} papers added, and subjects that already had a paper
     */
    public function addPapers(int $institute, int $examId, int $classId, array $subjectIds, float $total, int $user): array
    {
        $exam = $this->exam($institute, $examId);
        if (!$exam) throw new DomainException('Exam not found.');
        if ($exam['status'] === 'locked') throw new DomainException('This exam is locked.');
        if ($total <= 0 || $total > 10000) throw new DomainException('Enter the total marks (more than 0).');
        $subjectIds = array_values(array_unique(array_filter(array_map('intval', $subjectIds))));
        if (!$subjectIds) throw new DomainException('Choose at least one subject.');
        if (!$this->db->query('SELECT 1 FROM classes WHERE id=:c AND institute_id=:i', ['c' => $classId, 'i' => $institute])->fetchColumn()) throw new DomainException('Choose a valid class.');
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            $this->addClass($institute, $examId, $classId, $user);
            $added = 0;
            $skipped = [];
            $now = date('Y-m-d H:i:s');
            foreach ($subjectIds as $sid) {
                $subject = $this->db->query('SELECT s.name FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=:c AND cs.subject_id=:s', ['c' => $classId, 's' => $sid])->fetchColumn();
                if ($subject === false) throw new DomainException('A chosen subject is not taught in this class.');
                if ($this->db->query('SELECT 1 FROM exam_subjects WHERE exam_id=:e AND class_id=:c AND subject_id=:s', ['e' => $examId, 'c' => $classId, 's' => $sid])->fetchColumn()) { $skipped[] = (string) $subject; continue; }
                $this->db->query('INSERT INTO exam_subjects(exam_id,class_id,subject_id,total_marks,created_by,created_at,updated_at) VALUES(:e,:c,:s,:t,:u,:n,:n2)', ['e' => $examId, 'c' => $classId, 's' => $sid, 't' => $total, 'u' => $user, 'n' => $now, 'n2' => $now]);
                $added++;
            }
            $pdo->commit();
            return [$added, $skipped];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function updatePaperTotal(int $institute, int $paperId, float $total): void
    {
        $paper = $this->paper($institute, $paperId);
        if (!$paper) throw new DomainException('Paper not found.');
        if ($paper['exam_status'] === 'locked') throw new DomainException('This exam is locked.');
        if ($total <= 0 || $total > 10000) throw new DomainException('Enter the total marks (more than 0).');
        if ($this->db->query('SELECT 1 FROM exam_marks WHERE exam_subject_id=:p AND obtained_marks>:t LIMIT 1', ['p' => $paperId, 't' => $total])->fetchColumn()) throw new DomainException('Some marks already entered are higher than this total. Correct them first.');
        $this->db->query('UPDATE exam_subjects SET total_marks=:t,updated_at=:u WHERE id=:p', ['t' => $total, 'u' => date('Y-m-d H:i:s'), 'p' => $paperId]);
    }

    /** Class subjects for the "add papers" form. */
    public function classSubjects(int $classId): array
    {
        return $this->db->query('SELECT s.id,s.name FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.class_id=:c ORDER BY s.name', ['c' => $classId])->fetchAll();
    }

    // ---------------------------------------------------------------- marks

    /** The paper's roster with any marks already entered. */
    public function sheet(int $institute, int $paperId): array
    {
        $paper = $this->paper($institute, $paperId);
        if (!$paper) return [];
        $rows = $this->roster($institute, (int) $paper['exam_id'], (int) $paper['class_id']);
        $marks = [];
        foreach ($this->db->query('SELECT student_id,obtained_marks,is_absent,remarks,updated_at FROM exam_marks WHERE exam_subject_id=:p', ['p' => $paperId])->fetchAll() as $m) $marks[(int) $m['student_id']] = $m;
        foreach ($rows as &$row) {
            $m = $marks[(int) $row['id']] ?? null;
            $row['obtained_marks'] = $m['obtained_marks'] ?? null;
            $row['is_absent'] = (int) ($m['is_absent'] ?? 0);
            $row['remarks'] = $m['remarks'] ?? '';
            $row['entered'] = $m !== null;
        }
        unset($row);
        return $rows;
    }

    /**
     * Saves a whole sheet in one transaction. A blank mark (not absent) clears that student's
     * entry. Only students on the paper's roster are accepted; marks must be 0..total.
     *
     * @return int entries saved
     */
    public function saveMarks(int $institute, int $paperId, array $marks, array $absent, array $remarks, int $user): int
    {
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            // Lock the paper row so two people saving the same sheet cannot interleave.
            $paper = $this->db->query('SELECT es.id,es.exam_id,es.class_id,es.total_marks,e.status FROM exam_subjects es JOIN exams e ON e.id=es.exam_id WHERE es.id=:p AND e.institute_id=:i FOR UPDATE', ['p' => $paperId, 'i' => $institute])->fetch();
            if (!$paper) throw new DomainException('Paper not found.');
            if ($paper['status'] === 'locked') throw new DomainException('This exam is locked. Marks cannot be changed.');
            $allowed = array_flip(array_map('intval', array_column($this->roster($institute, (int) $paper['exam_id'], (int) $paper['class_id']), 'id')));
            $total = (float) $paper['total_marks'];
            $now = date('Y-m-d H:i:s');
            $count = 0;
            foreach ($marks as $sid => $value) {
                $sid = (int) $sid;
                if (!isset($allowed[$sid])) throw new DomainException('A student on this sheet is not part of this exam any more. Reload the page.');
                $isAbsent = !empty($absent[$sid]);
                $value = trim((string) $value);
                $remark = mb_substr(trim((string) ($remarks[$sid] ?? '')), 0, 255);
                if (!$isAbsent && $value === '') {
                    $this->db->query('DELETE FROM exam_marks WHERE exam_subject_id=:p AND student_id=:s', ['p' => $paperId, 's' => $sid]);
                    continue;
                }
                if (!$isAbsent && !is_numeric($value)) throw new DomainException('Marks must be numbers.');
                $score = $isAbsent ? 0.0 : (float) $value;
                if ($score < 0 || $score > $total) throw new DomainException('Marks must be between 0 and ' . rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') . '.');
                $this->db->query('INSERT INTO exam_marks(exam_subject_id,student_id,obtained_marks,is_absent,remarks,entered_by,created_at,updated_at) VALUES(:p,:s,:m,:a,:r,:u,:n,:n2)
                    ON DUPLICATE KEY UPDATE obtained_marks=VALUES(obtained_marks),is_absent=VALUES(is_absent),remarks=VALUES(remarks),entered_by=VALUES(entered_by),updated_at=VALUES(updated_at)',
                    ['p' => $paperId, 's' => $sid, 'm' => $score, 'a' => $isAbsent ? 1 : 0, 'r' => $remark !== '' ? $remark : null, 'u' => $user, 'n' => $now, 'n2' => $now]);
                $count++;
            }
            $this->db->query('UPDATE exam_subjects SET updated_at=:u WHERE id=:p', ['u' => $now, 'p' => $paperId]);
            if ($paper['status'] === 'draft') $this->db->query("UPDATE exams SET status='marks_entered',updated_at=:u WHERE id=:e AND status='draft'", ['u' => $now, 'e' => $paper['exam_id']]);
            $pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // ---------------------------------------------------------------- review and publishing

    public function submit(int $institute, int $paperId, int $user): void
    {
        $paper = $this->paper($institute, $paperId);
        if (!$paper) throw new DomainException('Paper not found.');
        $this->db->query("UPDATE exam_subjects SET marks_status='submitted',submitted_at=:n,submitted_by=:u WHERE id=:p", ['n' => date('Y-m-d H:i:s'), 'u' => $user, 'p' => $paperId]);
    }

    /** Sends a paper back to its teacher for corrections (it stops being visible to students). */
    public function reopen(int $institute, int $paperId): void
    {
        $paper = $this->paper($institute, $paperId);
        if (!$paper) throw new DomainException('Paper not found.');
        if ($paper['exam_status'] === 'locked') throw new DomainException('This exam is locked.');
        $this->db->query("UPDATE exam_subjects SET marks_status='open',submitted_at=NULL,submitted_by=NULL,published_at=NULL,published_by=NULL WHERE id=:p", ['p' => $paperId]);
    }

    /**
     * Publishes papers. Papers with students still unmarked are refused unless $allowIncomplete.
     *
     * @return array{0:int,1:list<string>} papers published, and the incomplete ones left out
     */
    public function publish(int $institute, array $paperIds, int $user, bool $allowIncomplete): array
    {
        $done = 0;
        $incomplete = [];
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            foreach (array_unique(array_map('intval', $paperIds)) as $pid) {
                $paper = $this->paper($institute, $pid);
                if (!$paper) continue;
                $progress = $this->papers($institute, (int) $paper['exam_id'], (int) $paper['class_id']);
                $row = array_values(array_filter($progress, static fn ($p) => (int) $p['id'] === $pid))[0] ?? null;
                if (!$allowIncomplete && $row && $row['marked'] < $row['roster']) { $incomplete[] = $paper['class_name'] . ' · ' . $paper['subject_name'] . ' (' . $row['marked'] . '/' . $row['roster'] . ')'; continue; }
                $this->db->query("UPDATE exam_subjects SET published_at=COALESCE(published_at,:n),published_by=COALESCE(published_by,:u),marks_status='submitted',submitted_at=COALESCE(submitted_at,:n2) WHERE id=:p", ['n' => date('Y-m-d H:i:s'), 'u' => $user, 'n2' => date('Y-m-d H:i:s'), 'p' => $pid]);
                $done++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return [$done, $incomplete];
    }

    public function unpublish(int $institute, array $paperIds): int
    {
        $done = 0;
        foreach (array_unique(array_map('intval', $paperIds)) as $pid) {
            $paper = $this->paper($institute, $pid);
            if (!$paper || $paper['exam_status'] === 'locked') continue;
            $this->db->query('UPDATE exam_subjects SET published_at=NULL,published_by=NULL WHERE id=:p', ['p' => $pid]);
            $done++;
        }
        return $done;
    }

    /** Whole exam: every paper published and the exam marked "published" (the existing status). */
    public function publishExam(int $institute, int $examId, int $user, bool $allowIncomplete): array
    {
        $exam = $this->exam($institute, $examId);
        if (!$exam) throw new DomainException('Exam not found.');
        if ($exam['status'] === 'locked') throw new DomainException('This exam is locked.');
        $ids = array_map('intval', array_column($this->papers($institute, $examId), 'id'));
        if (!$ids) throw new DomainException('This exam has no papers yet.');
        [$done, $incomplete] = $this->publish($institute, $ids, $user, $allowIncomplete);
        if (!$incomplete) $this->db->query("UPDATE exams SET status='published',updated_at=:u WHERE id=:e", ['u' => date('Y-m-d H:i:s'), 'e' => $examId]);
        return [$done, $incomplete];
    }

    public function unpublishExam(int $institute, int $examId): void
    {
        $exam = $this->exam($institute, $examId);
        if (!$exam) throw new DomainException('Exam not found.');
        if ($exam['status'] === 'locked') throw new DomainException('This exam is locked. Unlock it first.');
        $this->db->query('UPDATE exam_subjects SET published_at=NULL,published_by=NULL WHERE exam_id=:e', ['e' => $examId]);
        $this->db->query("UPDATE exams SET status='marks_entered',updated_at=:u WHERE id=:e AND status='published'", ['u' => date('Y-m-d H:i:s'), 'e' => $examId]);
    }

    public function setLocked(int $institute, int $examId, bool $locked): void
    {
        $exam = $this->exam($institute, $examId);
        if (!$exam) throw new DomainException('Exam not found.');
        if ($locked && $exam['status'] !== 'published') throw new DomainException('Publish the whole exam before locking it.');
        $this->db->query('UPDATE exams SET status=:s,updated_at=:u WHERE id=:e', ['s' => $locked ? 'locked' : 'published', 'u' => date('Y-m-d H:i:s'), 'e' => $examId]);
    }

    // ---------------------------------------------------------------- results

    /**
     * One class's consolidated result: subjects as columns, students as rows. Absent papers are
     * shown as absent and count as 0; papers with no entry are "not marked" and are left out of
     * the totals, so a missing mark is never silently treated as zero.
     */
    public function classResult(int $institute, int $examId, int $classId): array
    {
        $papers = $this->papers($institute, $examId, $classId);
        $students = $this->roster($institute, $examId, $classId);
        $marks = [];
        if ($papers) {
            $ids = implode(',', array_map('intval', array_column($papers, 'id')));
            foreach ($this->db->query("SELECT exam_subject_id,student_id,obtained_marks,is_absent FROM exam_marks WHERE exam_subject_id IN ({$ids})")->fetchAll() as $m) $marks[(int) $m['student_id']][(int) $m['exam_subject_id']] = $m;
        }
        $rows = [];
        foreach ($students as $st) {
            $cells = [];
            $obtained = 0.0;
            $max = 0.0;
            $missing = 0;
            $absent = 0;
            foreach ($papers as $p) {
                $m = $marks[(int) $st['id']][(int) $p['id']] ?? null;
                if ($m === null) { $cells[(int) $p['id']] = null; $missing++; continue; }
                $cells[(int) $p['id']] = ['marks' => (float) $m['obtained_marks'], 'absent' => (bool) $m['is_absent']];
                if ($m['is_absent']) $absent++;
                $obtained += (float) $m['obtained_marks'];
                $max += (float) $p['total_marks'];
            }
            $rows[] = $st + ['cells' => $cells, 'obtained' => $obtained, 'max' => $max, 'missing' => $missing, 'absent' => $absent,
                'percentage' => $max > 0 ? round($obtained / $max * 100, 2) : null,
                'state' => $papers === [] ? 'no_papers' : ($missing === count($papers) ? 'not_marked' : ($absent === count($papers) ? 'absent' : ($missing > 0 ? 'incomplete' : 'complete')))];
        }
        return ['papers' => $papers, 'rows' => $rows];
    }

    private function date(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
