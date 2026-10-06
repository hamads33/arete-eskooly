<?php
declare(strict_types=1);

use App\Core\{App,Request};
use App\Repositories\{AcademicRepository,RecordedLectureRepository,UserRepository};
use App\Services\{AuthService,CredentialService,StudentNotifications};

/**
 * Subject folders for recorded lectures, student pop-up notifications, and admin
 * suspend / edit / delete of portal logins.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out[$n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();

    // ---- fixtures -----------------------------------------------------------
    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'Folders ' . $suffix, 2500, 'Folder Teacher ' . $suffix);
    $classId = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Folders ' . $suffix])->fetchColumn();
    $academic->assign($institute, $classId, 'Physics ' . $suffix, 100);
    $academic->assign($institute, $classId, 'Chemistry ' . $suffix, 100);
    $physics = (int) $db->query('SELECT id FROM subjects WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Physics ' . $suffix])->fetchColumn();
    $chemistry = (int) $db->query('SELECT id FROM subjects WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Chemistry ' . $suffix])->fetchColumn();

    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:x2)', ['i' => $institute, 'c' => $classId, 'r' => 'FOLD-' . $suffix, 'n' => 'Folder Student', 'd' => $today, 'x' => $now, 'x2' => $now]);
    $studentId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",50000,"active",:x,:x2)', ['i' => $institute, 'e' => 'EMP-FOLD-' . $suffix, 'n' => 'Folder Teacher ' . $suffix, 'd' => $today, 'x' => $now, 'x2' => $now]);
    $employeeId = (int) $pdo->lastInsertId();

    $credentials = new CredentialService($db);
    $email = 'folder.student.' . $suffix . '@example.test';
    $password = 'FolderStudent#' . $suffix;
    $studentUser = (int) $credentials->create('student', $studentId, $institute, $email, $admin, $password)['user_id'];
    $employeeUser = (int) $credentials->create('employee', $employeeId, $institute, 'folder.staff.' . $suffix . '@example.test', $admin, 'FolderStaff#' . $suffix)['user_id'];

    $lectures = new RecordedLectureRepository($db);
    $physicsOld = $lectures->save($institute, ['audience_type' => 'class', 'class_ids' => [$classId], 'subject_id' => $physics, 'title' => 'Motion basics ' . $suffix, 'video_url' => 'https://example.test/motion', 'publish_date' => date('Y-m-d', strtotime('-40 days')), 'status' => 'published', 'created_by' => $admin]);
    $physicsNew = $lectures->save($institute, ['audience_type' => 'class', 'class_ids' => [$classId], 'subject_id' => $physics, 'title' => 'Newton laws ' . $suffix, 'video_url' => 'https://example.test/newton', 'publish_date' => $today, 'status' => 'published', 'created_by' => $admin]);

    $db->query('INSERT INTO homework(homework_date,audience_type,teacher_id,class_id,subject_id,student_id,homework_text,created_by,status,created_at,updated_at) VALUES(:d,"student",:t,NULL,:s,:st,:txt,:u,"published",:x,:x2)', ['d' => $today, 't' => $employeeId, 's' => $chemistry, 'st' => $studentId, 'txt' => 'Balance equations ' . $suffix, 'u' => $admin, 'x' => $now, 'x2' => $now]);
    $homeworkId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,discount,previous_balance,total_amount,paid_amount,remaining_amount,status,created_by,created_at,updated_at) VALUES(:s,:m,:no,2500,0,0,2500,0,2500,"unpaid",:u,:x,:x2)', ['s' => $studentId, 'm' => date('Y-m'), 'no' => 'FINV-' . $suffix, 'u' => $admin, 'x' => $now, 'x2' => $now]);
    $invoiceId = (int) $pdo->lastInsertId();

    // ---- subject folders ----------------------------------------------------
    $session = $app->session();
    $as = static function (int $id, array $roles) use ($session): void {
        $session->set('auth_user', ['id' => $id, 'name' => 'Folder', 'email' => 'folder@test', 'status' => 'active', 'roles' => $roles, 'permissions' => []]);
    };
    $get = static function (string $uri) use ($app): \App\Core\Response {
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        return $app->run(new Request($query, [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    };
    $post = static fn (string $uri, array $body): \App\Core\Response => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));

    $as($studentUser, ['student']);
    $index = $get('/portal/student/recorded-lectures')->content();
    $check('lecture index shows a folder per assigned subject', str_contains($index, 'Physics ' . $suffix) && str_contains($index, 'Chemistry ' . $suffix));
    $check('empty subject folder exists by default', str_contains($index, 'No lectures yet'));
    $folder = $get('/portal/student/recorded-lectures?subject=' . $physics)->content();
    $check('subject folder lists its own lectures', str_contains($folder, 'Newton laws ' . $suffix) && str_contains($folder, 'Motion basics ' . $suffix));
    $check('subject folder groups lectures by date', substr_count($folder, 'data-lecture-day') >= 2);
    $check('subject folder lists newest date first', strpos($folder, 'Newton laws ' . $suffix) < strpos($folder, 'Motion basics ' . $suffix));
    $chemFolder = $get('/portal/student/recorded-lectures?subject=' . $chemistry)->content();
    $check('other subject folder does not mix in physics lectures', !str_contains($chemFolder, 'lecture-tile__title">Newton laws ' . $suffix) && str_contains($folder, 'lecture-tile__title">Newton laws ' . $suffix));

    // ---- notifications ------------------------------------------------------
    $student = (new \App\Repositories\PortalRepository($db))->studentByUser($studentUser);
    $notify = new StudentNotifications($db);
    $keys = array_column($notify->unread($studentUser, $student), 'key');
    $check('new lecture raises a notification', in_array('lecture:' . $physicsNew, $keys, true));
    $check('old lecture does not raise a notification', !in_array('lecture:' . $physicsOld, $keys, true));
    $check('new homework raises a notification', in_array('homework:' . $homeworkId, $keys, true));
    $check('unpaid fee raises a notification', in_array('fee:' . $invoiceId . ':' . $today, $keys, true));
    $check('portal page renders the notification pop-up', str_contains($get('/portal/student/homework')->content(), 'data-student-notify'));

    $post('/portal/notifications/read', ['_token' => \App\Core\CSRF::token(), 'keys' => $keys]);
    $check('dismissed notifications stay dismissed', $notify->unread($studentUser, $student) === []);
    $check('pop-up is gone after dismissal', !str_contains($get('/portal/student/homework')->content(), 'data-student-notify'));

    // ---- teacher announcements ----------------------------------------------
    (new \App\Repositories\PakistanErpRepository($db))->saveAssignment($institute, $employeeId, $classId, $physics, null, 'subject', $admin);
    $academic->createClass($institute, 'Other ' . $suffix, 2500, 'Nobody');
    $otherClass = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Other ' . $suffix])->fetchColumn();
    $as($employeeUser, ['employee', 'teacher']);
    $token = \App\Core\CSRF::token();
    $post('/portal/employee/teaching/announcements', ['_token' => $token, 'title' => 'Teacher note ' . $suffix, 'message' => 'Bring lab coats', 'class_id' => $classId, 'audience' => 'all']);
    $teacherAnn = (int) $db->query('SELECT id FROM announcements WHERE title=:t AND teacher_id=:e', ['t' => 'Teacher note ' . $suffix, 'e' => $employeeId])->fetchColumn();
    $check('teacher can post an announcement to an assigned class', $teacherAnn > 0);
    $post('/portal/employee/teaching/announcements', ['_token' => $token, 'title' => 'Sneaky ' . $suffix, 'message' => 'x', 'class_id' => $otherClass, 'audience' => 'all']);
    $check('teacher cannot post to an unassigned class', !$db->query('SELECT 1 FROM announcements WHERE title=:t', ['t' => 'Sneaky ' . $suffix])->fetchColumn());
    $check('teacher workspace lists own announcements', str_contains($get('/portal/employee/teaching/announcements')->content(), 'Teacher note ' . $suffix));
    $annRepo = new \App\Repositories\AnnouncementRepository($db);
    $onBehalf = $annRepo->create($institute, ['title' => 'On behalf ' . $suffix, 'message' => 'From admin', 'audience' => 'students', 'teacher_id' => $employeeId, 'class_id' => $classId, 'status' => 'published'], $admin);
    $school = $annRepo->create($institute, ['title' => 'School wide ' . $suffix, 'message' => 'Holiday', 'audience' => 'all', 'status' => 'published'], $admin);
    $elsewhere = $annRepo->create($institute, ['title' => 'Other class ' . $suffix, 'message' => 'x', 'audience' => 'all', 'class_id' => $otherClass, 'status' => 'published'], $admin);
    $as($studentUser, ['student']);
    $page = $get('/portal/announcements')->content();
    $check('student sees school and teacher announcements separately', str_contains($page, 'School Announcements') && str_contains($page, 'Teacher Announcements') && strpos($page, 'School wide ' . $suffix) < strpos($page, 'Teacher Announcements') && strpos($page, 'Teacher note ' . $suffix) > strpos($page, 'Teacher Announcements'));
    $check('admin announcement in teacher name shows the teacher', str_contains($page, 'On behalf ' . $suffix) && str_contains($page, 'Folder Teacher ' . $suffix));
    $check('student does not see another class announcement', !str_contains($page, 'Other class ' . $suffix));
    $rows = array_column($annRepo->all($institute), null, 'id');
    $check('admin list marks who posted a teacher announcement', (int) $rows[$teacherAnn]['by_teacher'] === 1 && (int) $rows[$onBehalf]['by_teacher'] === 0);
    $db->query('DELETE FROM announcements WHERE id IN (:a,:b,:c,:d)', ['a' => $teacherAnn, 'b' => $onBehalf, 'c' => $school, 'd' => $elsewhere]);
    $db->query('DELETE FROM teacher_assignments WHERE employee_id=:e', ['e' => $employeeId]);
    $db->query('DELETE FROM classes WHERE id=:c', ['c' => $otherClass]);

    // ---- login suspend / edit / delete --------------------------------------
    $auth = new AuthService(new UserRepository($db), $session);
    $credentials->setStatus('student', $studentId, $institute, 'disabled');
    $check('suspended student cannot log in', $auth->attempt($email, $password) === 'disabled');
    $as($studentUser, ['student']);
    $check('suspended student session is ended', $get('/portal/student')->status() === 302);
    $credentials->create('student', $studentId, $institute, $email, $admin, 'Changed#' . $suffix);
    $check('password change does not unsuspend', (string) $db->query('SELECT status FROM users WHERE id=:id', ['id' => $studentUser])->fetchColumn() === 'disabled');
    $credentials->setStatus('student', $studentId, $institute, 'active');
    $check('activated student can log in again', $auth->attempt($email, 'Changed#' . $suffix) === 'authenticated');
    $newEmail = 'folder.renamed.' . $suffix . '@example.test';
    $credentials->create('student', $studentId, $institute, $newEmail, $admin, null);
    $check('email-only edit keeps the password', $auth->attempt($newEmail, 'Changed#' . $suffix) === 'authenticated');
    $threw = false;
    try { $credentials->create('employee', $employeeId, $institute, $newEmail, $admin, null); } catch (\DomainException) { $threw = true; }
    $check('login email cannot duplicate another account', $threw);
    $credentials->delete('student', $studentId, $institute);
    $check('deleted login cannot sign in', $auth->attempt($newEmail, 'Changed#' . $suffix) !== 'authenticated');
    $check('deleted login keeps the student record', (bool) $db->query('SELECT 1 FROM students WHERE id=:id', ['id' => $studentId])->fetchColumn());
    $check('deleted login frees the email for reuse', (int) $credentials->create('student', $studentId, $institute, $newEmail, $admin, 'Fresh#' . $suffix)['user_id'] !== $studentUser);
    $session->forget('auth_user');

    // ---- cleanup ------------------------------------------------------------
    $users = $db->query('SELECT user_id FROM student_accounts WHERE student_id=:s', ['s' => $studentId])->fetchAll(\PDO::FETCH_COLUMN);
    $users = array_map('intval', array_merge($users, [$studentUser, $employeeUser]));
    $db->query('DELETE FROM recorded_lectures WHERE id IN (:a,:b)', ['a' => $physicsOld, 'b' => $physicsNew]);
    $db->query('DELETE FROM homework WHERE id=:h', ['h' => $homeworkId]);
    $db->query('DELETE FROM fee_invoices WHERE id=:i', ['i' => $invoiceId]);
    $db->query('DELETE FROM student_accounts WHERE student_id=:s', ['s' => $studentId]);
    $db->query('DELETE FROM employee_accounts WHERE employee_id=:e', ['e' => $employeeId]);
    foreach (array_unique($users) as $u) {
        $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $u]);
        $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $u]);
        $db->query('DELETE FROM users WHERE id=:u', ['u' => $u]);
    }
    $db->query('DELETE FROM students WHERE id=:s', ['s' => $studentId]);
    $db->query('DELETE FROM employees WHERE id=:e', ['e' => $employeeId]);
    $db->query('DELETE FROM class_subjects WHERE class_id=:c', ['c' => $classId]);
    $db->query('DELETE FROM subjects WHERE id IN (:a,:b)', ['a' => $physics, 'b' => $chemistry]);
    $db->query('DELETE FROM classes WHERE id=:c', ['c' => $classId]);

    return $out;
};
