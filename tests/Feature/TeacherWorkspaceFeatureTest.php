<?php
declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\{AcademicRepository,PakistanErpRepository,UserRepository};
use App\Services\CredentialService;

/**
 * Teacher workspace: authoring from the employee portal, constrained by teacher_assignments.
 *
 * Asserts persisted state and scope denial, not just HTTP 200.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out[$n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();

    // ---- fixtures: one assigned class, one unassigned class ------------------
    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'TW A ' . $suffix, 1000, null);
    $academic->createClass($institute, 'TW B ' . $suffix, 1000, null);
    $assigned = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'TW A ' . $suffix])->fetchColumn();
    $foreign = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'TW B ' . $suffix])->fetchColumn();
    $academic->assign($institute, $assigned, 'TW Math ' . $suffix, 100);
    $academic->assign($institute, $foreign, 'TW Sci ' . $suffix, 100);
    $subject = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $assigned])->fetchColumn();
    $foreignSubject = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $foreign])->fetchColumn();

    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",50000,"active",:x,:y)', ['i' => $institute, 'e' => 'TW-' . $suffix, 'n' => 'TW Teacher ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
    $employee = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:y)', ['i' => $institute, 'c' => $assigned, 'r' => 'TWS-' . $suffix, 'n' => 'TW Student', 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
    $student = (int) $pdo->lastInsertId();

    $credential = (new CredentialService($db))->create('employee', $employee, $institute, 'tw.' . $suffix . '@example.test', $admin, 'TeacherPass#' . $suffix);
    $user = (int) $credential['user_id'];

    // Subject-level assignment only: this teacher is NOT a homeroom teacher.
    (new PakistanErpRepository($db))->saveAssignment($institute, $employee, $assigned, $subject, null, 'subject', $admin);

    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));
    $get = static fn (string $uri): \App\Core\Response => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $post = static fn (string $uri, array $body): \App\Core\Response => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));

    // ---- pages render --------------------------------------------------------
    $check('teacher workspace renders', $get('/portal/employee/teaching')->status() === 200);
    $check('teacher attendance page renders', $get('/portal/employee/teaching/attendance?class=' . $assigned)->status() === 200);
    $check('teacher homework page renders', $get('/portal/employee/teaching/homework?class=' . $assigned)->status() === 200);
    $check('teacher notes page renders', $get('/portal/employee/teaching/notes?class=' . $assigned)->status() === 200);
    $check('teacher tests page renders', $get('/portal/employee/teaching/tests?class=' . $assigned)->status() === 200);
    $check('teaching menu appears for assigned teacher', str_contains($get('/portal/employee')->content(), 'My Teaching'));

    // ---- CSRF ---------------------------------------------------------------
    $check('teacher attendance enforces CSRF', $post('/portal/employee/teaching/attendance', ['class' => $assigned])->status() === 419);

    // ---- attendance: a subject teacher may mark their class register ---------
    $post('/portal/employee/teaching/attendance', ['_token' => CSRF::token(), 'class' => $assigned, 'date' => date('Y-m-d'), 'status' => [$student => 'Late'], 'remarks' => [$student => 'late bus']]);
    $status = $db->query('SELECT status FROM student_attendance WHERE student_id=:s AND attendance_date=:d', ['s' => $student, 'd' => date('Y-m-d')])->fetchColumn();
    $check('subject teacher can mark class attendance', $status === 'Late');
    $check('Late is stored distinctly from Present', $status !== 'Present');

    // ---- attendance: cannot touch an unassigned class ------------------------
    $post('/portal/employee/teaching/attendance', ['_token' => CSRF::token(), 'class' => $foreign, 'date' => date('Y-m-d'), 'status' => []]);
    $leaked = (int) $db->query('SELECT COUNT(*) FROM student_attendance a JOIN students s ON s.id=a.student_id WHERE s.class_id=:c', ['c' => $foreign])->fetchColumn();
    $check('teacher cannot mark attendance for unassigned class', $leaked === 0);

    // ---- homework: authored as self, scoped ---------------------------------
    $post('/portal/employee/teaching/homework', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'date' => date('Y-m-d'), 'homework' => 'TW homework ' . $suffix]);
    $homework = (int) $db->query('SELECT COUNT(*) FROM homework WHERE teacher_id=:t AND class_id=:c', ['t' => $employee, 'c' => $assigned])->fetchColumn();
    $check('teacher can publish homework to assigned class', $homework === 1);

    $post('/portal/employee/teaching/homework', ['_token' => CSRF::token(), 'class_id' => $foreign, 'subject_id' => $foreignSubject, 'date' => date('Y-m-d'), 'homework' => 'leak ' . $suffix]);
    $check('teacher cannot publish homework to unassigned class', (int) $db->query('SELECT COUNT(*) FROM homework WHERE class_id=:c', ['c' => $foreign])->fetchColumn() === 0);

    // ---- notes ---------------------------------------------------------------
    $post('/portal/employee/teaching/notes', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'title' => 'TW note ' . $suffix, 'publish_date' => date('Y-m-d'), 'status' => 'published']);
    $check('teacher can upload a class note', (int) $db->query('SELECT COUNT(*) FROM class_notes WHERE teacher_id=:t AND class_id=:c', ['t' => $employee, 'c' => $assigned])->fetchColumn() === 1);
    $post('/portal/employee/teaching/notes', ['_token' => CSRF::token(), 'class_id' => $foreign, 'subject_id' => $foreignSubject, 'title' => 'leak note ' . $suffix, 'publish_date' => date('Y-m-d')]);
    $check('teacher cannot upload notes to unassigned class', (int) $db->query('SELECT COUNT(*) FROM class_notes WHERE class_id=:c', ['c' => $foreign])->fetchColumn() === 0);

    // ---- class tests and absent marking --------------------------------------
    $post('/portal/employee/teaching/tests', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'title' => 'TW Quiz ' . $suffix, 'date' => date('Y-m-d'), 'total_marks' => 20]);
    $test = (int) $db->query('SELECT id FROM class_tests WHERE title=:t', ['t' => 'TW Quiz ' . $suffix])->fetchColumn();
    $check('teacher can create a class test', $test > 0);

    $post('/portal/employee/teaching/tests', ['_token' => CSRF::token(), 'class_id' => $foreign, 'subject_id' => $foreignSubject, 'title' => 'TW Leak ' . $suffix, 'date' => date('Y-m-d'), 'total_marks' => 20]);
    $check('teacher cannot create a test for unassigned class', (int) $db->query('SELECT COUNT(*) FROM class_tests WHERE title=:t', ['t' => 'TW Leak ' . $suffix])->fetchColumn() === 0);

    if ($test > 0) {
        $check('teacher marks page renders', $get('/portal/employee/teaching/tests/' . $test . '/marks')->status() === 200);
        $post('/portal/employee/teaching/tests/' . $test . '/marks', ['_token' => CSRF::token(), 'marks' => [$student => ''], 'absent' => [$student => '1']]);
        $absent = $db->query('SELECT is_absent FROM class_test_marks WHERE class_test_id=:t AND student_id=:s', ['t' => $test, 's' => $student])->fetchColumn();
        $check('absent is recorded rather than a zero mark', (int) $absent === 1);
    }

    // ---- a staff role alone must still open the portal ----------------------
    // An admin can grant "teacher" from Roles & Permissions without also granting the generic
    // "employee" role. Gating the portal on role slugs alone locked such users out of their
    // own workspace, so access is decided by the linked employee record.
    $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $user]);
    $db->query('INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug=:s', ['u' => $user, 's' => 'teacher']);
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));
    $roles = (array) (user()['roles'] ?? []);
    $check('staff role without the generic employee role is set up', in_array('teacher', $roles, true) && !in_array('employee', $roles, true));
    $check('teacher-only role can still open the employee portal', $get('/portal/employee')->status() === 200);
    $check('teacher-only role can still open the teaching workspace', $get('/portal/employee/teaching')->status() === 200);

    // Restore the employee role for the remaining assertions.
    $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $user]);
    $db->query('INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug=:s', ['u' => $user, 's' => 'employee']);
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));

    // ---- an employee with no assignment gets no workspace --------------------
    $db->query('DELETE FROM teacher_assignments WHERE employee_id=:e', ['e' => $employee]);
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));
    $check('unassigned employee sees no teaching menu', !str_contains($get('/portal/employee')->content(), 'My Teaching'));
    $post('/portal/employee/teaching/attendance', ['_token' => CSRF::token(), 'class' => $assigned, 'date' => date('Y-m-d'), 'status' => [$student => 'Absent']]);
    $check('unassigned employee cannot mark attendance', $db->query('SELECT status FROM student_attendance WHERE student_id=:s AND attendance_date=:d', ['s' => $student, 'd' => date('Y-m-d')])->fetchColumn() === 'Late');

    // ---- cleanup -------------------------------------------------------------
    $db->query('DELETE FROM class_test_marks WHERE student_id=:s', ['s' => $student]);
    $db->query('DELETE FROM class_tests WHERE institute_id=:i AND title LIKE :t', ['i' => $institute, 't' => 'TW %' . $suffix]);
    $db->query('DELETE FROM class_notes WHERE teacher_id=:t', ['t' => $employee]);
    $db->query('DELETE FROM homework WHERE teacher_id=:t', ['t' => $employee]);
    $db->query('DELETE FROM student_attendance WHERE student_id=:s', ['s' => $student]);
    $db->query('DELETE FROM employee_accounts WHERE employee_id=:e', ['e' => $employee]);
    $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $user]);
    $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $user]);
    $db->query('DELETE FROM users WHERE id=:u', ['u' => $user]);
    $db->query('DELETE FROM students WHERE id=:s', ['s' => $student]);
    $db->query('DELETE FROM employees WHERE id=:e', ['e' => $employee]);
    $db->query('DELETE FROM class_subjects WHERE class_id IN (:a,:b)', ['a' => $assigned, 'b' => $foreign]);
    $db->query('DELETE FROM subjects WHERE id IN (:a,:b)', ['a' => $subject, 'b' => $foreignSubject]);
    $db->query('DELETE FROM classes WHERE id IN (:a,:b)', ['a' => $assigned, 'b' => $foreign]);

    return $out;
};
