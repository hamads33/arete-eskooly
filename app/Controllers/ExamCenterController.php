<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\AcademicRepository;
use App\Repositories\ExamCenterRepository;
use App\Repositories\SettingsRepository;
use App\Support\Flash;

/**
 * Exam Center: Exams → class → paper (subject) → marks → results → publishing.
 *
 * Administrators (and every non-teacher role allowed into Exams) manage everything. Teachers see
 * only exams that include their classes, enter marks only on papers for the classes and subjects
 * they are assigned to, and add papers or publish results only when the admin has switched that
 * on (Exam settings). Every rule is checked here, never only by hiding buttons.
 */
final class ExamCenterController extends Controller
{
    private ?int $instituteId = null;
    private ?array $teacher = null;

    private function i(): int
    {
        return $this->instituteId ??= (int) (new SettingsRepository($this->app->database()))->institute()['id'];
    }

    private function repo(): ExamCenterRepository
    {
        return new ExamCenterRepository($this->app->database());
    }

    /** Null for full access; otherwise the teacher's employee id and assignments. */
    private function teacher(): ?array
    {
        if (can('*') || !hasRole('teacher')) return null;
        if ($this->teacher !== null) return $this->teacher;
        $db = $this->app->database();
        $employee = (int) $db->query('SELECT ea.employee_id FROM employee_accounts ea JOIN employees e ON e.id=ea.employee_id WHERE ea.user_id=:u AND e.institute_id=:i', ['u' => (int) user()['id'], 'i' => $this->i()])->fetchColumn();
        $assignments = $employee ? $db->query("SELECT class_id,subject_id,assignment_type FROM teacher_assignments WHERE employee_id=:e AND institute_id=:i AND status='active'", ['e' => $employee, 'i' => $this->i()])->fetchAll() : [];
        return $this->teacher = ['employee' => $employee, 'assignments' => $assignments, 'classes' => array_values(array_unique(array_map('intval', array_column($assignments, 'class_id'))))];
    }

    private function teaches(int $classId, int $subjectId): bool
    {
        $t = $this->teacher();
        if ($t === null) return true;
        foreach ($t['assignments'] as $a) {
            if ((int) $a['class_id'] !== $classId) continue;
            if (in_array($a['assignment_type'], ['whole_class', 'homeroom'], true) || (int) $a['subject_id'] === $subjectId) return true;
        }
        return false;
    }

    /** Paper ids the teacher may mark (null = all). */
    private function teacherPaperIds(): ?array
    {
        if ($this->teacher() === null) return null;
        $rows = $this->app->database()->query('SELECT es.id,es.class_id,es.subject_id FROM exam_subjects es JOIN exams e ON e.id=es.exam_id WHERE e.institute_id=:i', ['i' => $this->i()])->fetchAll();
        return array_values(array_map(static fn ($r) => (int) $r['id'], array_filter($rows, fn ($r) => $this->teaches((int) $r['class_id'], (int) $r['subject_id']))));
    }

    private function forbidden(string $message = 'You do not have access to this.'): Response
    {
        return Response::html(View::render('errors/403', ['pageTitle' => 'Access Denied', 'message' => $message], 'layouts/error'), 403);
    }

    private function adminOnly(): ?Response
    {
        return $this->teacher() === null ? null : $this->forbidden('Only administrators can do this.');
    }

    private function back(string $path): Response
    {
        return Response::redirect(url($path));
    }

    private function page(string $view, array $data): Response
    {
        return $this->view($view, $data + [
            'activeMenu' => 'exams',
            'activeSubmenu' => 'exam-center',
            'styles' => ['css/exam-center.css?v=4'],
            'scripts' => ['js/exam-center.js?v=2'],
            'isTeacher' => $this->teacher() !== null,
            'settings' => $this->repo()->settings($this->i()),
        ]);
    }

    // ------------------------------------------------------------------ list

    public function index(Request $request): Response
    {
        $t = $this->teacher();
        $exams = $this->repo()->exams($this->i(), $this->teacherPaperIds(), $t['classes'] ?? []);
        $myPapers = [];
        if ($t !== null) {
            $ids = $this->teacherPaperIds() ?? [];
            foreach ($exams as $exam) {
                foreach ($this->repo()->papers($this->i(), (int) $exam['id']) as $p) {
                    if (in_array((int) $p['id'], $ids, true)) $myPapers[] = $p + ['exam_name' => $exam['name']];
                }
            }
        }
        return $this->page('exams/center/index', [
            'pageTitle' => 'Exams',
            'exams' => $exams,
            'myPapers' => $myPapers,
            'classes' => $this->classesWithCounts(),
        ]);
    }

    /** All classes with how many active students each has (for the "students included" count). */
    private function classesWithCounts(): array
    {
        $counts = [];
        foreach ($this->app->database()->query("SELECT class_id,COUNT(*) n FROM students WHERE institute_id=:i AND status='active' GROUP BY class_id", ['i' => $this->i()])->fetchAll() as $r) $counts[(int) $r['class_id']] = (int) $r['n'];
        return array_map(static fn ($c) => $c + ['active_students' => $counts[(int) $c['id']] ?? 0], (new AcademicRepository($this->app->database()))->classes($this->i()));
    }

    /**
     * One student's results in every exam, found by name or registration number, with the
     * result card PDF for each. Administrators see unpublished results too (marked as such).
     */
    public function student(Request $request): Response
    {
        if ($x = $this->adminOnly()) return $x;
        $db = $this->app->database();
        $q = trim((string) $request->query('q', ''));
        $class = (int) $request->query('class', 0);
        $matches = [];
        if ($q !== '' || $class) {
            $sql = "SELECT st.id,st.registration_no,st.student_name,st.status,c.name class_name FROM students st LEFT JOIN classes c ON c.id=st.class_id WHERE st.institute_id=:i";
            $p = ['i' => $this->i()];
            if ($q !== '') { $sql .= ' AND (st.student_name LIKE :q OR st.registration_no LIKE :q2 OR st.father_name LIKE :q3)'; $p += ['q' => '%' . $q . '%', 'q2' => '%' . $q . '%', 'q3' => '%' . $q . '%']; }
            if ($class) { $sql .= ' AND st.class_id=:c'; $p['c'] = $class; }
            $matches = $db->query($sql . " ORDER BY st.status='active' DESC,st.student_name LIMIT 60", $p)->fetchAll();
        }
        $student = null;
        $results = [];
        $sid = (int) $request->query('student', 0);
        if ($sid) {
            $student = $db->query('SELECT st.*,c.name class_name FROM students st LEFT JOIN classes c ON c.id=st.class_id WHERE st.id=:s AND st.institute_id=:i', ['s' => $sid, 'i' => $this->i()])->fetch() ?: null;
            if ($student) {
                $workflow = new \App\Repositories\WorkflowRepository($db);
                $exams = $db->query('SELECT DISTINCT e.id,e.name,e.start_date,e.end_date,e.status FROM exam_marks m JOIN exam_subjects es ON es.id=m.exam_subject_id JOIN exams e ON e.id=es.exam_id WHERE m.student_id=:s AND e.institute_id=:i ORDER BY e.start_date DESC,e.id DESC', ['s' => $sid, 'i' => $this->i()])->fetchAll();
                foreach ($exams as $e) {
                    $r = $workflow->studentResult($this->i(), (int) $e['id'], $sid);
                    $published = (int) $db->query('SELECT COUNT(*) FROM exam_subjects es JOIN exam_marks m ON m.exam_subject_id=es.id AND m.student_id=:s WHERE es.exam_id=:e AND (es.published_at IS NOT NULL OR :st IN (\'published\',\'locked\'))', ['s' => $sid, 'e' => $e['id'], 'st' => $e['status']])->fetchColumn();
                    $results[] = $e + ['result' => $r, 'published_count' => $published];
                }
            }
        }
        return $this->page('exams/center/student', [
            'pageTitle' => 'Student Result',
            'activeSubmenu' => 'exam-student',
            'q' => $q,
            'selectedClass' => $class,
            'classes' => $this->classesWithCounts(),
            'matches' => $matches,
            'student' => $student,
            'results' => $results,
            'hasGrading' => $this->repo()->hasGrading($this->i()),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($x = $this->adminOnly()) return $x;
        try {
            $id = $this->repo()->create($this->i(), (string) $request->input('name'), (string) $request->input('start_date'), (string) $request->input('end_date'), (int) user()['id'], (array) $request->input('classes', []));
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
            $this->app->session()->flash('_old', ['name' => (string) $request->input('name'), 'start_date' => (string) $request->input('start_date'), 'end_date' => (string) $request->input('end_date')]);
            return $this->back('/admin/exams');
        }
        Flash::add('success', 'Exam created. Next, add its papers (subjects and total marks).');
        return $this->back('/admin/exams/' . $id . '?tab=papers');
    }

    public function saveSettings(Request $request): Response
    {
        if ($x = $this->adminOnly()) return $x;
        $this->repo()->saveSettings($this->i(), $request->input('teachers_add_papers') === '1', $request->input('teachers_publish') === '1', (int) user()['id']);
        Flash::add('success', 'Teacher permissions for exams saved.');
        return $this->back('/admin/exams#exam-settings');
    }

    // ------------------------------------------------------------------ one exam

    public function show(Request $request): Response
    {
        $repo = $this->repo();
        $exam = $repo->exam($this->i(), (int) $request->route('id'));
        if (!$exam) return Response::html('Exam not found', 404);
        $t = $this->teacher();
        $paperIds = $this->teacherPaperIds();
        $classes = $repo->classes((int) $exam['id']);
        if ($t !== null) {
            $classes = array_values(array_filter($classes, static fn ($c) => in_array((int) $c['id'], $t['classes'], true)));
            if ($classes === []) return $this->forbidden('This exam does not include any class you teach.');
        }
        $tab = in_array($request->query('tab'), ['overview', 'papers', 'students', 'results'], true) ? (string) $request->query('tab') : 'overview';
        if ($t !== null && $tab === 'students') $tab = 'overview';
        $papers = $repo->papers($this->i(), (int) $exam['id']);
        if ($paperIds !== null) $papers = array_values(array_filter($papers, static fn ($p) => in_array((int) $p['id'], $paperIds, true)));
        $classId = (int) $request->query('class', 0);
        if ($classId && !in_array($classId, array_map('intval', array_column($classes, 'id')), true)) $classId = 0;
        if (!$classId && $classes) $classId = (int) $classes[0]['id'];
        $data = [
            'pageTitle' => $exam['name'],
            'exam' => $exam,
            'tab' => $tab,
            'classes' => $classes,
            'papers' => $papers,
            'selectedClass' => $classId,
            'roster' => $t === null ? $repo->roster($this->i(), (int) $exam['id']) : array_values(array_filter($repo->roster($this->i(), (int) $exam['id']), static fn ($r) => in_array((int) $r['class_id'], $t['classes'], true))),
        ];
        if ($tab === 'papers') {
            $allClasses = (new AcademicRepository($this->app->database()))->classes($this->i());
            if ($t !== null) $allClasses = array_values(array_filter($allClasses, static fn ($c) => in_array((int) $c['id'], $t['classes'], true)));
            $subjectsByClass = [];
            foreach ($allClasses as $c) {
                $subjectsByClass[(int) $c['id']] = array_values(array_filter($repo->classSubjects((int) $c['id']), fn ($s) => $this->teaches((int) $c['id'], (int) $s['id'])));
            }
            $data += ['allClasses' => $allClasses, 'subjectsByClass' => $subjectsByClass];
        }
        if ($tab === 'students') {
            $data += [
                'excluded' => $repo->excluded($this->i(), (int) $exam['id']),
                'candidates' => trim((string) $request->query('q', '')) !== '' || (int) $request->query('from_class', 0) ? $repo->candidates($this->i(), (int) $exam['id'], trim((string) $request->query('q', '')), (int) $request->query('from_class', 0)) : [],
                'allClasses' => $this->classesWithCounts(),
                'q' => trim((string) $request->query('q', '')),
                'fromClass' => (int) $request->query('from_class', 0),
            ];
        }
        if ($tab === 'results' && $classId) {
            $result = $repo->classResult($this->i(), (int) $exam['id'], $classId);
            if ($paperIds !== null) {
                $result['papers'] = array_values(array_filter($result['papers'], static fn ($p) => in_array((int) $p['id'], $paperIds, true)));
            }
            $hasGrading = $repo->hasGrading($this->i());
            if ($hasGrading) {
                // Grades come from the institute's own grading set-up, the same way the result card does.
                $workflow = new \App\Repositories\WorkflowRepository($this->app->database());
                foreach ($result['rows'] as &$row) $row['grade'] = $row['percentage'] !== null ? ($workflow->studentResult($this->i(), (int) $exam['id'], (int) $row['id'])['grade']['grade'] ?? null) : null;
                unset($row);
            }
            $data += ['result' => $result, 'hasGrading' => $hasGrading];
        }
        return $this->page('exams/center/show', $data);
    }

    public function update(Request $request): Response
    {
        if ($x = $this->adminOnly()) return $x;
        $id = (int) $request->route('id');
        try {
            $this->repo()->update($this->i(), $id, (string) $request->input('name'), (string) $request->input('start_date'), (string) $request->input('end_date'));
            Flash::add('success', 'Exam details saved.');
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return $this->back('/admin/exams/' . $id);
    }

    /** Classes and individual students (admin only). */
    public function students(Request $request): Response
    {
        if ($x = $this->adminOnly()) return $x;
        $id = (int) $request->route('id');
        $repo = $this->repo();
        $user = (int) user()['id'];
        try {
            switch ((string) $request->input('do')) {
                case 'add_class':
                    $classes = array_filter(array_map('intval', (array) $request->input('classes', [])));
                    if (!$classes) throw new \DomainException('Choose at least one class.');
                    foreach ($classes as $c) $repo->addClass($this->i(), $id, $c, $user);
                    Flash::add('success', count($classes) . ' class(es) added. Their active students are included automatically.');
                    break;
                case 'remove_class':
                    $repo->removeClass($this->i(), $id, (int) $request->input('class'));
                    Flash::add('success', 'Class removed from the exam.');
                    break;
                case 'include':
                    $n = $repo->includeStudents($this->i(), $id, (array) $request->input('students', []), (int) $request->input('sit_class'), $user);
                    Flash::add($n ? 'success' : 'error', $n ? $n . ' student(s) added to the exam.' : 'Tick at least one student to add.');
                    break;
                case 'exclude':
                    $repo->excludeStudent($this->i(), $id, (int) $request->input('student'), $user);
                    Flash::add('success', 'Student taken out of this exam. Any marks already entered are kept and come back if you add them again.');
                    break;
                case 'reset':
                    $repo->resetStudent($this->i(), $id, (int) $request->input('student'));
                    Flash::add('success', 'Student restored to their class.');
                    break;
                default:
                    return Response::html('Unknown action', 400);
            }
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return $this->back('/admin/exams/' . $id . '?tab=students');
    }

    public function addPapers(Request $request): Response
    {
        $id = (int) $request->route('id');
        $class = (int) $request->input('class');
        $subjects = array_map('intval', (array) $request->input('subjects', []));
        $t = $this->teacher();
        if ($t !== null) {
            if (!$this->repo()->settings($this->i())['teachers_add_papers']) return $this->forbidden('Your administrator has not allowed teachers to add exam papers.');
            foreach ($subjects as $s) if (!$this->teaches($class, $s)) return $this->forbidden('You can only add papers for the classes and subjects you teach.');
            if (!in_array($class, array_map('intval', array_column($this->repo()->classes($id), 'id')), true)) return $this->forbidden('This class is not part of the exam. Ask the admin to add it.');
        }
        try {
            [$added, $skipped] = $this->repo()->addPapers($this->i(), $id, $class, $subjects, (float) $request->input('total_marks'), (int) user()['id']);
            if ($added) Flash::add('success', $added . ' paper(s) added.');
            if ($skipped) Flash::add('error', 'Already in this exam, left unchanged: ' . implode(', ', $skipped) . '.');
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return $this->back('/admin/exams/' . $id . '?tab=papers&class=' . $class);
    }

    public function paperTotal(Request $request): Response
    {
        if ($x = $this->adminOnly()) return $x;
        $paper = $this->repo()->paper($this->i(), (int) $request->route('paper'));
        if (!$paper) return Response::html('Paper not found', 404);
        try {
            $this->repo()->updatePaperTotal($this->i(), (int) $paper['id'], (float) $request->input('total_marks'));
            Flash::add('success', 'Total marks updated for ' . $paper['class_name'] . ' · ' . $paper['subject_name'] . '.');
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return $this->back('/admin/exams/' . (int) $paper['exam_id'] . '?tab=papers&class=' . (int) $paper['class_id']);
    }

    // ------------------------------------------------------------------ marks

    /** A teacher may change marks until they send the paper for review or it is published. */
    private function canEdit(array $paper): bool
    {
        if ($paper['exam_status'] === 'locked') return false;
        if ($this->teacher() === null) return true;
        return $paper['marks_status'] !== 'submitted' && !$paper['visible'];
    }

    private function canPublish(array $paper): bool
    {
        if ($paper['exam_status'] === 'locked') return false;
        return $this->teacher() === null || $this->repo()->settings($this->i())['teachers_publish'];
    }

    private function ownPaper(int $paperId): ?array
    {
        $paper = $this->repo()->paper($this->i(), $paperId);
        if (!$paper) return null;
        if ($this->teacher() !== null && !$this->teaches((int) $paper['class_id'], (int) $paper['subject_id'])) return null;
        return $paper;
    }

    public function sheet(Request $request): Response
    {
        $paper = $this->ownPaper((int) $request->route('paper'));
        if (!$paper) return $this->forbidden('This paper is not one you can open.');
        $rows = $this->repo()->sheet($this->i(), (int) $paper['id']);
        return $this->page('exams/center/sheet', [
            'pageTitle' => $paper['subject_name'] . ' · ' . $paper['class_name'] . ' · ' . $paper['exam_name'],
            'paper' => $paper,
            'rows' => $rows,
            'canEdit' => $this->canEdit($paper),
            'canPublish' => $this->canPublish($paper),
        ]);
    }

    public function saveSheet(Request $request): Response
    {
        $paper = $this->ownPaper((int) $request->route('paper'));
        if (!$paper) return $this->forbidden('This paper is not one you can change.');
        if (!$this->canEdit($paper)) {
            Flash::add('error', $paper['exam_status'] === 'locked' ? 'This exam is locked.' : 'These marks were sent for review or published, so they can no longer be changed here. Ask the admin to reopen the paper.');
            return $this->back('/admin/exams/papers/' . (int) $paper['id']);
        }
        try {
            $n = $this->repo()->saveMarks($this->i(), (int) $paper['id'], (array) $request->input('marks', []), (array) $request->input('absent', []), (array) $request->input('remarks', []), (int) user()['id']);
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage() . ' Nothing was saved.');
            return $this->back('/admin/exams/papers/' . (int) $paper['id']);
        }
        $after = (string) $request->input('after', '');
        if ($after === 'submit') {
            if ($this->canPublish($paper) && $this->teacher() !== null) {
                [$done, $incomplete] = $this->repo()->publish($this->i(), [(int) $paper['id']], (int) user()['id'], false);
                Flash::add($done ? 'success' : 'error', $done ? 'Marks saved and published to students.' : 'Marks saved, but not published: some students have no marks yet (' . implode(', ', $incomplete) . ').');
            } else {
                $this->repo()->submit($this->i(), (int) $paper['id'], (int) user()['id']);
                Flash::add('success', 'Marks saved and sent to the admin for review.');
            }
        } else {
            Flash::add('success', $n . ' mark(s) saved for ' . $paper['class_name'] . ' · ' . $paper['subject_name'] . '.');
        }
        // The sheet closes after saving: back to the exam's papers (or the teacher's list).
        return $this->back($this->teacher() !== null ? '/admin/exams' : '/admin/exams/' . (int) $paper['exam_id'] . '?tab=papers&class=' . (int) $paper['class_id']);
    }

    // ------------------------------------------------------------------ publishing

    public function paperAction(Request $request): Response
    {
        $paper = $this->ownPaper((int) $request->route('paper'));
        if (!$paper) return $this->forbidden('This paper is not one you can change.');
        $repo = $this->repo();
        $user = (int) user()['id'];
        try {
            switch ((string) $request->route('action')) {
                case 'submit':
                    $repo->submit($this->i(), (int) $paper['id'], $user);
                    Flash::add('success', 'Sent to the admin for review.');
                    break;
                case 'reopen':
                    if ($x = $this->adminOnly()) return $x;
                    $repo->reopen($this->i(), (int) $paper['id']);
                    Flash::add('success', 'Paper reopened: the teacher can change the marks again, and students no longer see it.');
                    break;
                case 'publish':
                    if (!$this->canPublish($paper)) return $this->forbidden('Your administrator has not allowed teachers to publish results.');
                    [$done, $incomplete] = $repo->publish($this->i(), [(int) $paper['id']], $user, $request->input('allow_incomplete') === '1' && $this->teacher() === null);
                    Flash::add($done ? 'success' : 'error', $done ? 'Published. Students and parents can now see this result.' : 'Not published: some students have no marks yet (' . implode(', ', $incomplete) . '). Enter them or mark them absent first.');
                    break;
                case 'unpublish':
                    if (!$this->canPublish($paper)) return $this->forbidden('Your administrator has not allowed teachers to publish results.');
                    if (in_array($paper['exam_status'], ['published', 'locked'], true)) throw new \DomainException('The whole exam is published. Unpublish the exam from its page first.');
                    $repo->unpublish($this->i(), [(int) $paper['id']]);
                    Flash::add('success', 'Unpublished. Students no longer see this result.');
                    break;
                case 'delete':
                    if ($x = $this->adminOnly()) return $x;
                    $repo2 = new \App\Repositories\WorkflowRepository($this->app->database());
                    $repo2->deleteExamSetup($this->i(), (int) $paper['id']);
                    Flash::add('success', $paper['subject_name'] . ' paper removed from ' . $paper['class_name'] . '.');
                    break;
                default:
                    return Response::html('Unknown action', 404);
            }
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        $back = (string) $request->input('back', '');
        if ($back === 'sheet' && $request->route('action') !== 'delete') return $this->back('/admin/exams/papers/' . (int) $paper['id']);
        return $this->back('/admin/exams/' . (int) $paper['exam_id'] . '?tab=' . ($back === 'results' ? 'results' : 'papers') . '&class=' . (int) $paper['class_id']);
    }

    /** Publish / unpublish a class or the whole exam, or lock it (admin only). */
    public function examAction(Request $request): Response
    {
        if ($x = $this->adminOnly()) return $x;
        $id = (int) $request->route('id');
        $repo = $this->repo();
        $user = (int) user()['id'];
        $class = (int) $request->input('class', 0);
        $allow = $request->input('allow_incomplete') === '1';
        try {
            switch ((string) $request->route('action')) {
                case 'publish-class':
                    $ids = array_map('intval', array_column($repo->papers($this->i(), $id, $class), 'id'));
                    if (!$ids) throw new \DomainException('This class has no papers yet.');
                    [$done, $incomplete] = $repo->publish($this->i(), $ids, $user, $allow);
                    Flash::add($incomplete ? 'error' : 'success', $done . ' paper(s) published.' . ($incomplete ? ' Not published because students are still unmarked: ' . implode(', ', $incomplete) . '. Tick "even if incomplete" to publish them as they are.' : ''));
                    break;
                case 'unpublish-class':
                    $exam = $repo->exam($this->i(), $id);
                    if ($exam && in_array($exam['status'], ['published', 'locked'], true)) throw new \DomainException('The whole exam is published. Unpublish the exam first.');
                    $n = $repo->unpublish($this->i(), array_map('intval', array_column($repo->papers($this->i(), $id, $class), 'id')));
                    Flash::add('success', $n . ' paper(s) unpublished.');
                    break;
                case 'publish':
                    [$done, $incomplete] = $repo->publishExam($this->i(), $id, $user, $allow);
                    if ($incomplete) {
                        Flash::add('error', $done . ' paper(s) published, but the exam is not fully published: ' . implode(', ', $incomplete) . ' still have unmarked students. Tick "publish anyway" to publish as it is.');
                    } else {
                        Flash::add('success', 'The whole exam is published. Students and parents can see their results.');
                        $queued = (new \App\Services\Sms\SmsAutomation($this->app->database()))->resultPublished($this->i(), $id);
                        if ($queued) Flash::add('success', $queued . ' result SMS queued for the SMS gateway.');
                    }
                    break;
                case 'unpublish':
                    $repo->unpublishExam($this->i(), $id);
                    Flash::add('success', 'Exam unpublished. Students no longer see these results.');
                    break;
                case 'lock':
                    $repo->setLocked($this->i(), $id, true);
                    Flash::add('success', 'Exam locked. Marks can no longer be changed.');
                    break;
                case 'unlock':
                    $repo->setLocked($this->i(), $id, false);
                    Flash::add('success', 'Exam unlocked.');
                    break;
                default:
                    return Response::html('Unknown action', 404);
            }
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        $tab = (string) $request->input('back', 'overview');
        return $this->back('/admin/exams/' . $id . '?tab=' . (in_array($tab, ['overview', 'papers', 'results'], true) ? $tab : 'overview') . ($class ? '&class=' . $class : ''));
    }
}
