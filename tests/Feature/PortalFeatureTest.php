<?php
declare(strict_types=1);

use App\Core\{App,Request};
use App\Repositories\{AcademicRepository,PortalRepository};
use App\Services\{AuthService,CredentialService};
use App\Repositories\UserRepository;

/**
 * Student and employee portal coverage: role grant, login routing, self-scoping,
 * cross-account isolation and admin/portal separation.
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

    // ---- fixtures -----------------------------------------------------------
    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'Portal ' . $suffix, 2500, 'Portal Teacher ' . $suffix);
    $classId = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Portal ' . $suffix])->fetchColumn();

    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:x2)', ['i' => $institute, 'c' => $classId, 'r' => 'PORTAL-' . $suffix, 'n' => 'Portal Student', 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $studentId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:x2)', ['i' => $institute, 'c' => $classId, 'r' => 'PORTAL-OTHER-' . $suffix, 'n' => 'Other Student', 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $otherStudentId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",50000,"active",:x,:x2)', ['i' => $institute, 'e' => 'EMP-PORTAL-' . $suffix, 'n' => 'Portal Teacher ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $employeeId = (int) $pdo->lastInsertId();

    // Owned data for the student, plus a record belonging to the other student.
    $db->query('INSERT INTO student_attendance(student_id,attendance_date,status,remarks,marked_by,created_at,updated_at) VALUES(:s,:d,"Present","",:u,:x,:x2)', ['s' => $studentId, 'd' => date('Y-m-d'), 'u' => $admin, 'x' => $now, 'x2' => $now]);
    $db->query('INSERT INTO student_attendance(student_id,attendance_date,status,remarks,marked_by,created_at,updated_at) VALUES(:s,:d,"Absent","",:u,:x,:x2)', ['s' => $otherStudentId, 'd' => date('Y-m-d'), 'u' => $admin, 'x' => $now, 'x2' => $now]);
    $db->query('INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,discount,previous_balance,total_amount,paid_amount,remaining_amount,status,created_by,created_at,updated_at) VALUES(:s,:m,:no,2500,0,0,2500,1000,1500,"partial",:u,:x,:x2)', ['s' => $studentId, 'm' => date('Y-m'), 'no' => 'PINV-' . $suffix, 'u' => $admin, 'x' => $now, 'x2' => $now]);
    $invoiceId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,discount,previous_balance,total_amount,paid_amount,remaining_amount,status,created_by,created_at,updated_at) VALUES(:s,:m,:no,9999,0,0,9999,0,9999,"unpaid",:u,:x,:x2)', ['s' => $otherStudentId, 'm' => date('Y-m'), 'no' => 'PINV-OTHER-' . $suffix, 'u' => $admin, 'x' => $now, 'x2' => $now]);
    $otherInvoiceId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO employee_attendance(employee_id,attendance_date,status,marked_by,created_at,updated_at) VALUES(:e,:d,"Present",:u,:x,:x2)', ['e' => $employeeId, 'd' => date('Y-m-d'), 'u' => $admin, 'x' => $now, 'x2' => $now]);
    $db->query('INSERT INTO salary_payments(employee_id,salary_month,base_salary,adjustment_amount,paid_amount,payment_date,paid_by,created_at) VALUES(:e,:m,50000,0,50000,:d,:u,:x)', ['e' => $employeeId, 'm' => date('Y-m'), 'd' => date('Y-m-d'), 'u' => $admin, 'x' => $now]);

    // ---- credential grants --------------------------------------------------
    $credentials = new CredentialService($db);
    $studentPassword = 'PortalStudent#' . $suffix;
    $studentLogin = $credentials->create('student', $studentId, $institute, 'portal.student.' . $suffix . '@example.test', $admin, $studentPassword);
    $employeePassword = 'PortalStaff#' . $suffix;
    $employeeLogin = $credentials->create('employee', $employeeId, $institute, 'portal.staff.' . $suffix . '@example.test', $admin, $employeePassword);
    $studentUser = (int) $studentLogin['user_id'];
    $employeeUser = (int) $employeeLogin['user_id'];

    $roleOf = static fn (int $u): array => $db->query('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=:u', ['u' => $u])->fetchAll(\PDO::FETCH_COLUMN);
    $check('credential creation grants student portal role', in_array('student', $roleOf($studentUser), true));
    $check('credential creation grants employee portal role', in_array('employee', $roleOf($employeeUser), true));
    $check('credential creation records institute membership', (int) $db->query('SELECT institute_id FROM institute_users WHERE user_id=:u', ['u' => $studentUser])->fetchColumn() === $institute);

    // ---- login is no longer restricted --------------------------------------
    $auth = new AuthService(new UserRepository($db), $app->session());
    $check('student login is authenticated', $auth->attempt('portal.student.' . $suffix . '@example.test', $studentPassword) === 'authenticated');
    $check('employee login is authenticated', $auth->attempt('portal.staff.' . $suffix . '@example.test', $employeePassword) === 'authenticated');
    $check('wrong portal password rejected', $auth->attempt('portal.student.' . $suffix . '@example.test', 'not-the-password') === 'invalid');

    // ---- repository self-scoping -------------------------------------------
    $portal = new PortalRepository($db);
    $resolved = $portal->studentByUser($studentUser);
    $check('portal resolves student from user', is_array($resolved) && (int) $resolved['id'] === $studentId);
    $check('portal resolves employee from user', ($e = $portal->employeeByUser($employeeUser)) !== null && (int) $e['id'] === $employeeId);
    $check('portal returns nothing for admin user', $portal->studentByUser($admin) === null && $portal->employeeByUser($admin) === null);

    $invoices = $portal->studentInvoices($studentId);
    $check('student sees only own invoices', $invoices !== [] && array_reduce($invoices, static fn ($c, $i) => $c && (int) $i['id'] !== $otherInvoiceId, true));
    $check('student invoice lookup rejects another students invoice', $portal->studentInvoice($studentId, $otherInvoiceId) === null);
    $check('student invoice lookup accepts own invoice', ($own = $portal->studentInvoice($studentId, $invoiceId)) !== null && (int) $own['id'] === $invoiceId);
    $totals = $portal->studentFeeTotals($studentId);
    $check('student fee totals are own only', (float) $totals['outstanding'] === 1500.0 && (float) $totals['paid'] === 1000.0);
    $summary = $portal->studentAttendanceSummary($studentId, date('Y-m-d'), date('Y-m-d'));
    $check('student attendance summary is own only', $summary['total'] === 1 && $summary['present'] === 1 && $summary['percentage'] === 100.0);
    $check('employee salary total is own only', $portal->employeeSalaryTotal($employeeId) === 50000.0);
    $check('employee classes resolve by teacher name', array_reduce($portal->employeeClasses($institute, 'Portal Teacher ' . $suffix), static fn ($c, $r) => $c || (int) $r['id'] === $classId, false));
    $check('employee classes ignore blank teacher name', $portal->employeeClasses($institute, '   ') === []);

    // ---- HTTP routing and authorization ------------------------------------
    $session = $app->session();
    $as = static function (int $id, array $roles) use ($session): void {
        $session->set('auth_user', ['id' => $id, 'name' => 'Portal', 'email' => 'portal@test', 'status' => 'active', 'roles' => $roles, 'permissions' => []]);
    };
    $get = static fn (string $uri): \App\Core\Response => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));

    $as($studentUser, ['student']);
    $dashboard = $get('/portal/student');
    $check('student dashboard renders', $dashboard->status() === 200 && str_contains($dashboard->content(), 'Portal Student'));
    $check('student dashboard shows own outstanding fee', str_contains($dashboard->content(), '1,500.00'));
    $check('student fees page renders own invoice', str_contains($get('/portal/student/fees')->content(), 'PINV-' . $suffix));
    $check('student fees page hides other invoice', !str_contains($get('/portal/student/fees')->content(), 'PINV-OTHER-' . $suffix));
    $check('student attendance page renders', $get('/portal/student/attendance')->status() === 200);
    $check('student results page renders', $get('/portal/student/results')->status() === 200);
    $check('student homework page renders', $get('/portal/student/homework')->status() === 200);
    $check('student profile page renders', str_contains($get('/portal/student/profile')->content(), 'PORTAL-' . $suffix));
    $check('student own invoice detail renders', $get('/portal/student/fees/' . $invoiceId)->status() === 200);
    $check('student cannot open another students invoice', $get('/portal/student/fees/' . $otherInvoiceId)->status() === 404);
    $check('student blocked from employee portal', $get('/portal/employee')->status() === 403);
    $check('student blocked from admin area', $get('/admin/dashboard')->status() === 403);

    $as($employeeUser, ['employee']);
    $employeeDashboard = $get('/portal/employee');
    $check('employee dashboard renders', $employeeDashboard->status() === 200 && str_contains($employeeDashboard->content(), 'Portal Teacher'));
    $check('employee salary page shows own payment', str_contains($get('/portal/employee/salary')->content(), '50,000.00'));
    $check('employee attendance page renders', $get('/portal/employee/attendance')->status() === 200);
    $check('employee classes page renders assigned class', str_contains($get('/portal/employee/classes')->content(), 'Portal ' . $suffix));
    $check('employee homework page renders', $get('/portal/employee/homework')->status() === 200);
    $check('employee profile page renders', str_contains($get('/portal/employee/profile')->content(), 'EMP-PORTAL-' . $suffix));
    $check('employee blocked from student portal', $get('/portal/student')->status() === 403);
    $check('employee blocked from admin area', $get('/admin/dashboard')->status() === 403);

    // ---- eSkooly StudentPortal parity: documents, messaging, account ---------
    $db->query('INSERT INTO fee_payments(fee_invoice_id,student_id,receipt_no,payment_date,amount,payment_method,received_by,created_at) VALUES(:i,:s,:r,:d,1000,"Cash",:u,:x)', ['i' => $invoiceId, 's' => $studentId, 'r' => 'PRCP-' . $suffix, 'd' => date('Y-m-d'), 'u' => $admin, 'x' => $now]);
    $paymentId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO fee_payments(fee_invoice_id,student_id,receipt_no,payment_date,amount,payment_method,received_by,created_at) VALUES(:i,:s,:r,:d,500,"Cash",:u,:x)', ['i' => $otherInvoiceId, 's' => $otherStudentId, 'r' => 'PRCP-OTHER-' . $suffix, 'd' => date('Y-m-d'), 'u' => $admin, 'x' => $now]);
    $otherPaymentId = (int) $pdo->lastInsertId();

    $check('student payment lookup accepts own payment', $portal->studentPayment($studentId, $paymentId) !== null);
    $check('student payment lookup rejects another students payment', $portal->studentPayment($studentId, $otherPaymentId) === null);
    $check('employee salary payment lookup is own only', $portal->employeeSalaryPayment($employeeId, 999999999) === null);

    $as($studentUser, ['student']);
    $isPdf = static fn (\App\Core\Response $r): bool => $r->status() === 200 && str_starts_with($r->content(), '%PDF');
    $check('student documents page renders', $get('/portal/student/documents')->status() === 200);
    $check('student admission letter is a PDF', $isPdf($get('/portal/student/documents/admission-letter')));
    $check('student fee slip is a PDF', $isPdf($get('/portal/student/documents/fee-slip/' . $paymentId)));
    $check('student cannot print another students fee slip', $get('/portal/student/documents/fee-slip/' . $otherPaymentId)->status() === 404);
    $check('student cannot print report card for an exam not sat', $get('/portal/student/documents/report-card/999999999')->status() === 404);
    $check('student messages page renders', $get('/portal/messages')->status() === 200);
    $check('student account settings page renders', $get('/portal/account')->status() === 200);
    $check('student blocked from employee documents', $get('/portal/employee/documents')->status() === 403);
    $check('student blocked from employee job letter', $get('/portal/employee/documents/job-letter')->status() === 403);

    // Account settings must reject a wrong current password and keep the old hash.
    $before = (string) $db->query('SELECT password_hash FROM users WHERE id=:id', ['id' => $studentUser])->fetchColumn();
    $post = static fn (string $uri, array $body): \App\Core\Response => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $check('portal account update enforces CSRF', $post('/portal/account', ['email' => 'x@y.test'])->status() === 419);
    $token = \App\Core\CSRF::token();
    $post('/portal/account', ['_token' => $token, 'email' => 'portal.student.' . $suffix . '@example.test', 'current_password' => 'wrong-password', 'password' => 'BrandNew#2026', 'password_confirmation' => 'BrandNew#2026']);
    $check('account update rejects wrong current password', (string) $db->query('SELECT password_hash FROM users WHERE id=:id', ['id' => $studentUser])->fetchColumn() === $before);

    $token = \App\Core\CSRF::token();
    $post('/portal/account', ['_token' => $token, 'email' => 'portal.renamed.' . $suffix . '@example.test', 'current_password' => $studentPassword, 'password' => 'BrandNew#2026', 'password_confirmation' => 'BrandNew#2026']);
    $after = (string) $db->query('SELECT password_hash FROM users WHERE id=:id', ['id' => $studentUser])->fetchColumn();
    $check('account update persists new password', password_verify('BrandNew#2026', $after));
    $check('account update persists new email', (string) $db->query('SELECT email FROM users WHERE id=:id', ['id' => $studentUser])->fetchColumn() === 'portal.renamed.' . $suffix . '@example.test');
    $check('account update clears stale credential cipher', $db->query('SELECT password_cipher FROM student_accounts WHERE user_id=:id', ['id' => $studentUser])->fetchColumn() === null);

    // Messaging: a portal user may start a thread and reply, but not post into a foreign one.
    $token = \App\Core\CSRF::token();
    $post('/portal/messages', ['_token' => $token, 'recipient_id' => $employeeUser, 'subject' => 'Portal ' . $suffix, 'body' => 'Hello from the portal']);
    $threadId = (int) $db->query('SELECT id FROM conversations WHERE subject=:s', ['s' => 'Portal ' . $suffix])->fetchColumn();
    $check('portal user can start a conversation', $threadId > 0);
    $token = \App\Core\CSRF::token();
    $post('/portal/messages/' . $threadId, ['_token' => $token, 'body' => 'A reply from the portal']);
    $check('portal user can reply to own conversation', (int) $db->query('SELECT COUNT(*) FROM messages WHERE conversation_id=:c', ['c' => $threadId])->fetchColumn() === 2);
    $check('portal conversation view is participant scoped', $get('/portal/messages/' . $threadId)->status() === 200);

    $foreign = (int) $db->query('SELECT c.id FROM conversations c WHERE c.id<>:t ORDER BY c.id DESC LIMIT 1', ['t' => $threadId])->fetchColumn();
    if ($foreign > 0) {
        $check('portal user cannot open a conversation they are not in', $get('/portal/messages/' . $foreign)->status() === 404);
    }

    // An admin holds no portal record, so the portal must refuse rather than leak a shell.
    $as($admin, ['admin']);
    $check('admin without portal record is refused', $get('/portal/student')->status() === 403);

    // Guests are redirected, never served portal content.
    $session->forget('auth_user');
    $guest = $get('/portal/student');
    $check('guest redirected away from portal', $guest->status() === 302);

    // ---- cleanup ------------------------------------------------------------
    $as($admin, ['admin']);
    $db->query('DELETE FROM salary_payments WHERE employee_id=:e', ['e' => $employeeId]);
    $db->query('DELETE FROM employee_attendance WHERE employee_id=:e', ['e' => $employeeId]);
    $db->query('DELETE FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE subject=:s)', ['s' => 'Portal ' . $suffix]);
    $db->query('DELETE FROM conversation_participants WHERE conversation_id IN (SELECT id FROM conversations WHERE subject=:s)', ['s' => 'Portal ' . $suffix]);
    $db->query('DELETE FROM conversations WHERE subject=:s', ['s' => 'Portal ' . $suffix]);
    $db->query('DELETE FROM fee_payments WHERE student_id IN (:a,:b)', ['a' => $studentId, 'b' => $otherStudentId]);
    $db->query('DELETE FROM fee_invoices WHERE id IN (:a,:b)', ['a' => $invoiceId, 'b' => $otherInvoiceId]);
    $db->query('DELETE FROM student_attendance WHERE student_id IN (:a,:b)', ['a' => $studentId, 'b' => $otherStudentId]);
    $db->query('DELETE FROM student_accounts WHERE student_id=:s', ['s' => $studentId]);
    $db->query('DELETE FROM employee_accounts WHERE employee_id=:e', ['e' => $employeeId]);
    $db->query('DELETE FROM user_roles WHERE user_id IN (:a,:b)', ['a' => $studentUser, 'b' => $employeeUser]);
    $db->query('DELETE FROM institute_users WHERE user_id IN (:a,:b)', ['a' => $studentUser, 'b' => $employeeUser]);
    $db->query('DELETE FROM users WHERE id IN (:a,:b)', ['a' => $studentUser, 'b' => $employeeUser]);
    $db->query('DELETE FROM students WHERE id IN (:a,:b)', ['a' => $studentId, 'b' => $otherStudentId]);
    $db->query('DELETE FROM employees WHERE id=:e', ['e' => $employeeId]);
    $db->query('DELETE FROM classes WHERE id=:c', ['c' => $classId]);

    return $out;
};
