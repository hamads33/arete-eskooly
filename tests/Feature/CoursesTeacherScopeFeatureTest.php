<?php
declare(strict_types=1);

use App\Core\{App,Request};
use App\Repositories\{PakistanErpRepository,RecordedLectureRepository,StudentCourseRepository,UserRepository};
use App\Services\CredentialService;

/** Student "My Courses" cards, and teachers being limited to what they are assigned. */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['courses/scope: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $academic = new \App\Repositories\AcademicRepository($db);
    $session = $app->session();
    $get = static fn(string $uri) => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $as = static fn(int $id) => $session->set('auth_user', (new UserRepository($db))->findAuthContext($id));
    $ids = ['classes' => [], 'employees' => [], 'students' => [], 'users' => [], 'lectures' => [], 'exams' => []];
    try {
        foreach (['A', 'B'] as $k) {
            $academic->createClass($institute, "Scope {$k} {$suffix}", 1000, 'x');
            $ids['classes'][$k] = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => "Scope {$k} {$suffix}"])->fetchColumn();
        }
        $academic->assign($institute, $ids['classes']['A'], 'Algebra ' . $suffix, 100);
        $academic->assign($institute, $ids['classes']['A'], 'Botany ' . $suffix, 100);
        $academic->assign($institute, $ids['classes']['B'], 'Algebra ' . $suffix, 100);
        $algebra = (int) $db->query('SELECT id FROM subjects WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Algebra ' . $suffix])->fetchColumn();
        $botany = (int) $db->query('SELECT id FROM subjects WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Botany ' . $suffix])->fetchColumn();
        $db->query("INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,education,status,created_at,updated_at) VALUES(:i,:n,:nm,:d,'Teacher',1,'PhD Mathematics','active',:c,:u)",
            ['i' => $institute, 'n' => 'SC-T-' . $suffix, 'nm' => 'Scope Teacher ' . $suffix, 'd' => date('Y-m-d'), 'c' => $now, 'u' => $now]);
        $teacher = $ids['employees'][] = (int) $pdo->lastInsertId();
        (new PakistanErpRepository($db))->saveAssignment($institute, $teacher, $ids['classes']['A'], $algebra, null, 'subject', $admin);
        $tUser = $ids['users'][] = (int) (new CredentialService($db))->create('employee', $teacher, $institute, 'scope.t.' . $suffix . '@example.test', $admin, 'Scope#' . $suffix)['user_id'];
        $db->query("INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug='teacher'", ['u' => $tUser]);
        $db->query("INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,'active',:x,:x2)",
            ['i' => $institute, 'c' => $ids['classes']['A'], 'r' => 'SC-S-' . $suffix, 'n' => 'Scope Student ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
        $studentId = $ids['students'][] = (int) $pdo->lastInsertId();
        $sUser = $ids['users'][] = (int) (new CredentialService($db))->create('student', $studentId, $institute, 'scope.s.' . $suffix . '@example.test', $admin, 'Scope#' . $suffix)['user_id'];
        $ids['lectures'][] = (new RecordedLectureRepository($db))->save($institute, ['audience_type' => 'class', 'class_ids' => [$ids['classes']['A']], 'subject_id' => $algebra, 'title' => 'Algebra L1 ' . $suffix,
            'video_url' => 'https://example.com/v', 'publish_date' => date('Y-m-d'), 'status' => 'published', 'created_by' => $admin]);
        $ids['lectures'][] = (new RecordedLectureRepository($db))->save($institute, ['audience_type' => 'class', 'class_ids' => [$ids['classes']['B']], 'subject_id' => $algebra, 'title' => 'Other class ' . $suffix,
            'video_url' => 'https://example.com/v', 'publish_date' => date('Y-m-d'), 'status' => 'published', 'created_by' => $admin]);

        // ---- My Courses --------------------------------------------------------------------------
        $student = (new \App\Repositories\PortalRepository($db))->studentByUser($sUser);
        $courses = array_column((new StudentCourseRepository($db))->courses($student), null, 'name');
        $check('one course per subject of the class', count($courses) === 2 && isset($courses['Algebra ' . $suffix], $courses['Botany ' . $suffix]));
        $check('the assigned teacher is picked up automatically', ($courses['Algebra ' . $suffix]['teacher']['name'] ?? '') === 'Scope Teacher ' . $suffix && ($courses['Algebra ' . $suffix]['teacher']['education'] ?? '') === 'PhD Mathematics');
        $check('lecture count only includes lectures shared with this class', $courses['Algebra ' . $suffix]['lectures']['total'] === 1);
        $check('a subject without a teacher says so', $courses['Botany ' . $suffix]['teacher'] === null);
        $as($sUser);
        $page = $get('/portal/student/courses')->content();
        $check('My Courses page renders the cards', str_contains($page, 'course-card') && str_contains($page, 'Scope Teacher ' . $suffix) && str_contains($page, 'Teacher to be assigned'));

        // ---- teacher scope -----------------------------------------------------------------------
        $as($tUser);
        foreach (['/admin/students', '/admin/classes', '/admin/dashboard', '/admin/attendance/students', '/admin/reports/fee-collection', '/admin/exams/create'] as $uri) {
            $allowed = $uri !== '/admin/exams/create' && can_open($uri);
            $status = $get($uri)->status();
            $check("teacher module grants are respected at {$uri}", $allowed ? $status === 200 : in_array($status, [302, 403], true));
        }
        $exam = $ids['exams'][] = (function () use ($db, $institute, $admin, $now, $pdo) {
            $db->query("INSERT INTO exams(institute_id,name,start_date,end_date,created_by,created_at,updated_at,status) VALUES(:i,'Scope exam',:d,:d2,:u,:c,:c2,'draft')", ['i' => $institute, 'd' => date('Y-m-d'), 'd2' => date('Y-m-d'), 'u' => $admin, 'c' => $now, 'c2' => $now]);
            return (int) $pdo->lastInsertId();
        })();
        $db->query('INSERT INTO exam_subjects(exam_id,class_id,subject_id,total_marks,created_at,updated_at) VALUES(:e,:c,:s,100,:n,:n2)', ['e' => $exam, 'c' => $ids['classes']['A'], 's' => $algebra, 'n' => $now, 'n2' => $now]);
        $mine = (int) $pdo->lastInsertId();
        $db->query('INSERT INTO exam_subjects(exam_id,class_id,subject_id,total_marks,created_at,updated_at) VALUES(:e,:c,:s,100,:n,:n2)', ['e' => $exam, 'c' => $ids['classes']['B'], 's' => $algebra, 'n' => $now, 'n2' => $now]);
        $theirs = (int) $pdo->lastInsertId();
        $marks = $get('/admin/exams/marks')->content();
        $check('teacher sees exam mark sheets for assigned class and subject only', str_contains($marks, 'value="' . $mine . '"') && !str_contains($marks, 'value="' . $theirs . '"'));
        $res = $app->run(new Request([], ['_token' => \App\Core\CSRF::token(), 'exam_subject_id' => $theirs, 'marks' => []], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/exams/marks']));
        $check('teacher cannot save marks for another class', $res->status() === 403);
        $session->forget('auth_user');
    } finally {
        $session->forget('auth_user');
        foreach ($ids['exams'] as $e) { $db->query('DELETE FROM exam_subjects WHERE exam_id=:e', ['e' => $e]); $db->query('DELETE FROM exams WHERE id=:e', ['e' => $e]); }
        foreach ($ids['lectures'] as $l) $db->query('DELETE FROM recorded_lectures WHERE id=:l', ['l' => $l]);
        foreach ($ids['students'] as $s) { $db->query('DELETE FROM student_accounts WHERE student_id=:s', ['s' => $s]); $db->query('DELETE FROM students WHERE id=:s', ['s' => $s]); }
        foreach ($ids['employees'] as $e) { $db->query('DELETE FROM teacher_assignments WHERE employee_id=:e', ['e' => $e]); $db->query('DELETE FROM employee_accounts WHERE employee_id=:e', ['e' => $e]); $db->query('DELETE FROM employees WHERE id=:e', ['e' => $e]); }
        foreach ($ids['users'] as $u) { foreach (['user_roles', 'institute_users', 'remember_tokens'] as $t) $db->query("DELETE FROM {$t} WHERE user_id=:u", ['u' => $u]); $db->query('DELETE FROM users WHERE id=:u', ['u' => $u]); }
        foreach ($ids['classes'] as $c) { $db->query('DELETE FROM class_subjects WHERE class_id=:c', ['c' => $c]); $db->query('DELETE FROM classes WHERE id=:c', ['c' => $c]); }
    }
    return $out;
};
