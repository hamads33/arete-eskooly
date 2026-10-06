<?php
declare(strict_types=1);

use App\Core\{App,Request};
use App\Repositories\UserRepository;
use App\Services\{AccountAccess,AuthService,CredentialService};

/** Suspend / block levels, the automatic unpaid-fee rule, and what a suspended user can reach. */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['access: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $session = $app->session();
    $savedFee = $db->query('SELECT suspend_after_months,block_after_months FROM fee_settings WHERE institute_id=:i', ['i' => $institute])->fetch();
    if (!$savedFee) {
        $db->query("INSERT INTO fee_settings(institute_id,generation_day,admission_month_policy,voucher_copies,payment_methods_json,updated_by,created_at,updated_at) VALUES(:i,1,'current_month','single','[\"Cash\"]',:u,:c,:c2)", ['i' => $institute, 'u' => $admin, 'c' => $now, 'c2' => $now]);
    }
    $classId = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i ORDER BY id LIMIT 1', ['i' => $institute])->fetchColumn();
    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,"active",:x,:x2)',
        ['i' => $institute, 'c' => $classId, 'r' => 'ACC-' . $suffix, 'n' => 'Access Student ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $studentId = (int) $pdo->lastInsertId();
    $email = 'access.' . $suffix . '@example.test';
    $password = 'Access#' . $suffix;
    $credentials = new CredentialService($db);
    $userId = (int) $credentials->create('student', $studentId, $institute, $email, $admin, $password)['user_id'];
    $invoices = [];
    try {
        $access = new AccountAccess($db);
        $auth = new AuthService(new UserRepository($db), $session);
        $status = static fn() => (string) $db->query('SELECT status FROM users WHERE id=:id', ['id' => $userId])->fetchColumn();
        $get = static fn(string $uri) => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));

        // Three overdue unpaid months, newest first.
        foreach ([1, 2, 3] as $n) {
            $month = date('Y-m', strtotime("first day of -{$n} month"));
            $db->query('INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,discount,previous_balance,total_amount,paid_amount,remaining_amount,status,due_date,created_by,created_at,updated_at) VALUES(:s,:m,:no,1000,0,0,1000,0,1000,"unpaid",:d,:u,:x,:x2)',
                ['s' => $studentId, 'm' => $month, 'no' => 'ACC-' . $suffix . '-' . $n, 'd' => $month . '-10', 'u' => $admin, 'x' => $now, 'x2' => $now]);
            $invoices[] = (int) $pdo->lastInsertId();
        }
        $check('counts consecutive unpaid months', $access->unpaidMonths($studentId) === 3);

        $setRule = static fn(int $s, int $b) => $db->query('UPDATE fee_settings SET suspend_after_months=:s,block_after_months=:b WHERE institute_id=:i', ['s' => $s, 'b' => $b, 'i' => $institute]);
        $setRule(0, 0);
        $access->evaluateStudent($studentId, $institute);
        $check('rule off leaves the login active', $status() === 'active');
        $setRule(2, 4);
        $access->evaluateStudent($studentId, $institute);
        $check('unpaid for the suspend limit suspends the login', $status() === 'suspended');
        $check('a suspended student can still sign in', $auth->attempt($email, $password) === 'authenticated');
        $session->set('auth_user', (new UserRepository($db))->findAuthContext($userId));
        $dash = $get('/portal/student');
        $check('every page redirects to the restriction notice', $dash->status() === 302 && str_contains((string) ($dash->headers()['Location'] ?? ''), '/account/restricted'));
        $notice = $get('/account/restricted')->content();
        $check('the notice asks for the fee and shows the amount', str_contains($notice, 'Please submit your fee') && str_contains($notice, '3,000') && str_contains($notice, '3 months'));
        $check('fee details stay readable', $get('/portal/student/fees')->status() === 200);
        $check('actions are refused', $app->run(new Request([], ['_token' => \App\Core\CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/portal/account']))->status() === 423);
        $session->forget('auth_user');

        $setRule(2, 3);
        $access->evaluateStudent($studentId, $institute);
        $check('unpaid for the block limit blocks the login', $status() === 'disabled');
        $check('a blocked student cannot sign in', $auth->attempt($email, $password) === 'disabled');

        $db->query("UPDATE fee_invoices SET status='paid',paid_amount=1000,remaining_amount=0 WHERE id=:id", ['id' => $invoices[0]]);
        $check('paying the latest month restores access at sign-in', $auth->attempt($email, $password) === 'authenticated' && $status() === 'active');
        $session->forget('auth_user');

        // Manual decisions win over the fee rule until handed back.
        $db->query("UPDATE fee_invoices SET status='unpaid',paid_amount=0,remaining_amount=1000 WHERE id=:id", ['id' => $invoices[0]]);
        $access->setManual('student', $studentId, $institute, 'active', 'Promised to pay');
        $access->evaluateStudent($studentId, $institute);
        $check('an admin activation is not overridden by the fee rule', $status() === 'active');
        $access->setManual('student', $studentId, $institute, 'active', '', null);
        $check('handing back to fee rules re-applies them', $status() === 'disabled');
        $access->setManual('student', $studentId, $institute, 'suspended', 'Discipline');
        $row = $db->query('SELECT access_source,access_reason FROM users WHERE id=:id', ['id' => $userId])->fetch();
        $check('manual suspension records who and why', $status() === 'suspended' && $row['access_source'] === 'manual' && $row['access_reason'] === 'Discipline');

        // Admin buttons.
        $session->set('auth_user', (new UserRepository($db))->findAuthContext($admin));
        $post = static fn(string $uri, array $body) => $app->run(new Request([], $body + ['_token' => \App\Core\CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
        $post("/admin/students/logins/{$studentId}/status", ['status' => 'disabled', 'reason' => 'Left institute']);
        $check('admin can block from Manage Login', $status() === 'disabled');
        $post("/admin/students/logins/{$studentId}/status", ['status' => 'active']);
        $check('admin can activate from Manage Login', $status() === 'active');
        $page = $get('/admin/students/logins')->content();
        $check('Manage Login offers suspend, block and edit record', str_contains($page, 'Suspend') && str_contains($page, 'Block') && str_contains($page, "/admin/students/{$studentId}/edit"));
    } finally {
        $session->forget('auth_user');
        foreach ($invoices as $inv) {
            $db->query('DELETE FROM fee_invoices WHERE id=:id', ['id' => $inv]);
        }
        $db->query('DELETE FROM student_accounts WHERE student_id=:s', ['s' => $studentId]);
        $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $userId]);
        $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $userId]);
        $db->query('DELETE FROM remember_tokens WHERE user_id=:u', ['u' => $userId]);
        $db->query('DELETE FROM users WHERE id=:u', ['u' => $userId]);
        $db->query('DELETE FROM students WHERE id=:s', ['s' => $studentId]);
        if ($savedFee) {
            $db->query('UPDATE fee_settings SET suspend_after_months=:s,block_after_months=:b WHERE institute_id=:i', ['s' => $savedFee['suspend_after_months'], 'b' => $savedFee['block_after_months'], 'i' => $institute]);
        } else {
            $db->query('DELETE FROM fee_settings WHERE institute_id=:i', ['i' => $institute]);
        }
    }
    return $out;
};
