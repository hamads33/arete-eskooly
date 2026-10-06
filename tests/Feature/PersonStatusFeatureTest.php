<?php
declare(strict_types=1);

use App\Core\{App, CSRF, Request};
use App\Repositories\{AcademicRepository, ReportingRepository, SettingsRepository, StudentRepository, EmployeeRepository, UserRepository, WorkflowRepository};
use App\Services\{AuthService, FeeVoucherService, StudentService};
use App\Services\Sms\{SmsRecipients, SmsSettings};
use App\Support\PersonStatus;

/**
 * Inactive / suspended students and employees keep their history (registers, reports, vouchers,
 * salary) up to the date their status changed; what happens after follows the admin's rules.
 * Deletion is refused whenever history exists. Positive, negative and edge cases.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['status: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $i = (int) (new SettingsRepository($db))->institute()['id'];
    $admin = $db->query('SELECT u.id,u.name,u.email,u.status FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" ORDER BY u.id LIMIT 1')->fetch();
    $asAdmin = static fn() => $app->session()->set('auth_user', $admin + ['roles' => ['admin'], 'permissions' => ['*']]);
    $asAdmin();
    $suffix = bin2hex(random_bytes(4));
    $now = date('Y-m-d H:i:s');
    $get = static function (string $uri) use ($app) { parse_str((string) parse_url($uri, PHP_URL_QUERY), $q); return $app->run(new Request($q, [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri])); };
    $post = static fn(string $uri, array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $flash = static fn(): string => (string) json_encode($_SESSION ?? []);
    $academic = new AcademicRepository($db);
    $className = 'Status QA ' . $suffix;
    $academic->createClass($i, $className, 3000, null);
    $class = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $i, 'n' => $className])->fetchColumn();
    $students = new StudentRepository($db);
    $svc = new StudentService($students);
    $sIds = []; $eIds = []; $uIds = [];
    $mkStudent = static function (string $name) use ($svc, $students, $i, $class, $suffix, &$sIds): array {
        $d = $svc->data(['studentname' => $name . ' ' . $suffix, 'reg' => 'PS-' . $suffix . '-' . count($sIds), 'doa' => '2019-01-01', 'class' => $class, 'discount' => 0, 'fname' => 'QA'], $i);
        $d['created_at'] = $d['updated_at'];
        $sIds[] = $id = $students->create($d);
        return $students->find($id, $i);
    };
    $mkEmployee = static function (string $name) use ($db, $pdo, $i, $suffix, $now, &$eIds): int {
        $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,"2019-01-01","Teacher",20000,"active",:c,:u)', ['i' => $i, 'e' => 'PE-' . $suffix . '-' . count($eIds), 'n' => $name . ' ' . $suffix, 'c' => $now, 'u' => $now]);
        return $eIds[] = (int) $pdo->lastInsertId();
    };
    $att = static fn(int $sid, string $date, string $st = 'Present') => $db->query('INSERT INTO student_attendance(student_id,attendance_date,status,marked_by,created_at,updated_at) VALUES(:s,:d,:st,:u,:c,:c2)', ['s' => $sid, 'd' => $date, 'st' => $st, 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
    $register = static fn(string $date) => $get('/admin/attendance/students?date=' . $date . '&scope=class&class=' . $class)->content();
    $oldRules = $db->query('SELECT * FROM person_status_rules WHERE institute_id=:i', ['i' => $i])->fetchAll();
    $restoreRules = static function () use ($db, $i, $oldRules) {
        $db->query('DELETE FROM person_status_rules WHERE institute_id=:i', ['i' => $i]);
        foreach ($oldRules as $r) { $c = array_keys($r); $db->query('INSERT INTO person_status_rules(' . implode(',', $c) . ') VALUES(:' . implode(',:', $c) . ')', $r); }
        (new ReflectionProperty(PersonStatus::class, 'cache'))->setValue(null, []);
    };
    $rulesWith = static function (array $over) use ($post): void {
        $base = PersonStatus::DEFAULTS;
        foreach ($over as $path => $v) { [$t, $s, $a] = explode('.', $path); $base[$t][$s][$a] = $v; }
        $form = [];
        foreach ($base as $t => $sts) foreach ($sts as $s => $acts) foreach ($acts as $a => $v) if ($v) $form[$t][$s][$a] = '1';
        $post('/admin/settings/status-rules', ['rules' => $form]);
        (new ReflectionProperty(PersonStatus::class, 'cache'))->setValue(null, []);
    };

    try {
        $restoreRules();
        $db->query('DELETE FROM person_status_rules WHERE institute_id=:i', ['i' => $i]);
        (new ReflectionProperty(PersonStatus::class, 'cache'))->setValue(null, []);
        $a = $mkStudent('Ayan'); $b = $mkStudent('Bina'); $c = $mkStudent('Chand');
        $att((int) $a['id'], '2019-04-10');
        $att((int) $a['id'], '2019-04-20', 'Absent');

        // ---- pages -------------------------------------------------------------------------
        $check('student status page offers Active, Inactive and Suspend with reason and date', str_contains($sp = $get('/admin/students/status')->content(), 'value="suspended"') && str_contains($sp, 'name="effective_at"') && str_contains($sp, 'name="reason"'));
        $check('employee status page renders', $get('/admin/employees/status')->status() === 200);
        $rp = $get('/admin/settings/status-rules');
        $check('rules page renders under General Settings', $rp->status() === 200 && str_contains($rp->content(), 'History is never hidden') && str_contains($rp->content(), '/admin/settings/status-rules'));

        // ---- set inactive with a past effective date ------------------------------------------
        $res = $post('/admin/students/status', ['student_ids' => [$a['id']], 'status' => 'inactive', 'reason' => 'Left for another city', 'effective_at' => '2019-04-15T10:00']);
        $a2 = $students->find((int) $a['id'], $i);
        $check('status change records status, effective time and reason', $a2['status'] === 'inactive' && $a2['status_changed_at'] === '2019-04-15 10:00:00' && $a2['status_reason'] === 'Left for another city');
        $h = PersonStatus::history($db, 'student', (int) $a['id']);
        $check('status history records who changed it', count($h) === 1 && $h[0]['old_status'] === 'active' && $h[0]['new_status'] === 'inactive' && (int) $h[0]['changed_by'] === (int) $admin['id']);
        $check('the student list still shows an inactive student', str_contains($get('/admin/students?student_search=' . rawurlencode('Ayan ' . $suffix))->content(), 'Ayan ' . $suffix));
        $check('the profile shows the status history', str_contains($get('/admin/students/' . $a['id'])->content(), 'Left for another city'));

        // ---- attendance is date-aware -----------------------------------------------------------
        $check('register before the status date still lists the student', str_contains($register('2019-04-12'), 'Ayan ' . $suffix));
        $r20 = $register('2019-04-20');
        $check('register after the status date keeps days that have a record, with an Inactive tag', str_contains($r20, 'Ayan ' . $suffix) && str_contains($r20, 'Inactive since 15 Apr 2019'));
        $check('register after the status date with no record hides the inactive student (default rule)', !str_contains($register('2019-04-25'), 'Ayan ' . $suffix) && str_contains($register('2019-04-25'), 'Bina ' . $suffix));
        $before = (int) $db->query('SELECT COUNT(*) FROM student_attendance WHERE student_id=:s', ['s' => $a['id']])->fetchColumn();
        $post('/admin/attendance/students', ['date' => '2019-04-12', 'scope' => 'class', 'class' => $class, 'status' => [$a['id'] => 'Late', $b['id'] => 'Present', $c['id'] => 'Present'], 'remarks' => [$a['id'] => 'back-filled', $b['id'] => '', $c['id'] => '']]);
        $check('attendance for a past day can still be saved for a since-deactivated student', (string) $db->query("SELECT status FROM student_attendance WHERE student_id=:s AND attendance_date='2019-04-12'", ['s' => $a['id']])->fetchColumn() === 'Late');
        $check('earlier attendance records are untouched', (int) $db->query('SELECT COUNT(*) FROM student_attendance WHERE student_id=:s', ['s' => $a['id']])->fetchColumn() === $before + 1);
        $rep = new ReportingRepository($db);
        $check('monthly attendance report keeps the month the student was active', str_contains(json_encode($rep->studentMonthlyAttendance($i, '2019-04', $class)), 'Ayan ' . $suffix));
        $check('monthly attendance report leaves out later months', !str_contains(json_encode($rep->studentMonthlyAttendance($i, '2019-06', $class)), 'Ayan ' . $suffix));
        $check('attendance shortage report keeps the student for that period', in_array((int) $a['id'], array_map('intval', array_column($rep->attendanceShortage($i, '2019-04-01', '2019-04-30', $class), 'id')), true));
        $check('daily report keeps a day with a record', str_contains($get('/admin/attendance/daily-report?date=2019-04-20&class=' . $class)->content(), 'Ayan ' . $suffix));

        // ---- billing ----------------------------------------------------------------------------
        $vs = new FeeVoucherService($db);
        $ids = static fn(array $rows) => array_map('intval', array_column($rows, 'id'));
        $check('class vouchers for the month they left still include them', in_array((int) $a['id'], $ids($vs->students($i, 'class', $class, 0, '2019-04')), true));
        $check('class vouchers for a later month leave them out', !in_array((int) $a['id'], $ids($vs->students($i, 'class', $class, 0, '2019-06')), true) && in_array((int) $b['id'], $ids($vs->students($i, 'class', $class, 0, '2019-06')), true));
        $check('a single-student voucher can always be generated', in_array((int) $a['id'], $ids($vs->students($i, 'student', 0, (int) $a['id'], '2019-06')), true));

        // ---- admin rules ------------------------------------------------------------------------
        $rulesWith(['student.inactive.attendance' => true]);
        $check('admin can keep inactive students on the register', str_contains($register('2019-04-25'), 'Ayan ' . $suffix));
        $rulesWith(['student.inactive.billing' => true]);
        $check('admin can keep inactive students in class vouchers', in_array((int) $a['id'], $ids($vs->students($i, 'class', $class, 0, '2019-06')), true));
        $rulesWith([]);
        $check('rules return to default', !str_contains($register('2019-04-25'), 'Ayan ' . $suffix));

        // ---- suspension ----------------------------------------------------------------------------
        $post('/admin/students/status', ['student_ids' => [$b['id']], 'status' => 'suspended', 'reason' => 'Fee dispute', 'effective_at' => date('Y-m-d', strtotime('-1 day')) . 'T09:00']);
        $b2 = $students->find((int) $b['id'], $i);
        $check('a student can be suspended', $b2['status'] === 'suspended' && $b2['status_reason'] === 'Fee dispute');
        $today = $register(date('Y-m-d'));
        $check('a suspended student stays on today\'s register by default, tagged Suspended', str_contains($today, 'Bina ' . $suffix) && str_contains($today, 'Suspended since'));
        $check('a suspended student stays in class vouchers by default', in_array((int) $b['id'], $ids($vs->students($i, 'class', $class, 0, date('Y-m')) ), true));
        $sms = new SmsRecipients($db, new SmsSettings($db));
        $smsHits = array_column($sms->search($i, 'student', $suffix), 'label');
        $check('suspended students still get messages by default, inactive ones do not', (bool) array_filter($smsHits, fn($l) => str_contains((string) $l, 'Bina')) && !array_filter($smsHits, fn($l) => str_contains((string) $l, 'Ayan')));
        $rulesWith(['student.suspended.attendance' => false]);
        $check('admin can hide suspended students from the register', !str_contains($register(date('Y-m-d')), 'Bina ' . $suffix));
        $rulesWith([]);

        // ---- sign-in rule ---------------------------------------------------------------------------
        $mkLogin = static function (int $studentId, string $email) use ($db, $pdo, $i, $now, &$uIds): int {
            $db->query('INSERT INTO users(name,email,password_hash,status,created_at,updated_at) VALUES("QA Login",:e,:h,"active",:c,:u)', ['e' => $email, 'h' => password_hash('Qa-Pass-2026!', PASSWORD_DEFAULT), 'c' => $now, 'u' => $now]);
            $uIds[] = $uid = (int) $pdo->lastInsertId();
            $db->query('INSERT INTO user_roles(user_id,role_id) VALUES(:u,(SELECT id FROM roles WHERE slug="student"))', ['u' => $uid]);
            $db->query('INSERT INTO institute_users(user_id,institute_id,created_at) VALUES(:u,:i,:c)', ['u' => $uid, 'i' => $i, 'c' => $now]);
            $db->query('INSERT INTO student_accounts(student_id,user_id,created_by,created_at,updated_at) VALUES(:s,:u,:by,:c,:c2)', ['s' => $studentId, 'u' => $uid, 'by' => $uid, 'c' => $now, 'c2' => $now]);
            return $uid;
        };
        $auth = new AuthService(new UserRepository($db), $app->session());
        $mkLogin((int) $b['id'], 'qa-b-' . $suffix . '@example.test');
        $mkLogin((int) $a['id'], 'qa-a-' . $suffix . '@example.test');
        $check('a suspended student cannot sign in (default rule)', $auth->attempt('qa-b-' . $suffix . '@example.test', 'Qa-Pass-2026!') === 'inactive_person');
        $asAdmin();
        $check('an inactive student can still sign in by default (unchanged behaviour)', $auth->attempt('qa-a-' . $suffix . '@example.test', 'Qa-Pass-2026!') === 'authenticated');
        $asAdmin();
        $rulesWith(['student.inactive.login' => false]);
        $check('admin can block inactive students from signing in', $auth->attempt('qa-a-' . $suffix . '@example.test', 'Qa-Pass-2026!') === 'inactive_person');
        $asAdmin();
        $check('a wrong password is still reported as invalid, not as blocked', $auth->attempt('qa-a-' . $suffix . '@example.test', 'wrong') === 'invalid');
        $asAdmin();
        $rulesWith([]);
        $check('the login screen explains the block', str_contains(file_get_contents(BASE_PATH . '/app/Controllers/AuthController.php'), 'Your account is not active at the moment'));

        // ---- reactivation ------------------------------------------------------------------------------
        $post('/admin/students/status', ['student_ids' => [$a['id']], 'status' => 'active', 'reason' => 'Rejoined']);
        $check('reactivating returns the student everywhere and keeps both history rows', $students->find((int) $a['id'], $i)['status'] === 'active' && count(PersonStatus::history($db, 'student', (int) $a['id'])) === 2 && str_contains($register('2019-04-25'), 'Ayan ' . $suffix));

        // ---- negative status changes ---------------------------------------------------------------------
        $before = $students->find((int) $c['id'], $i);
        $res = $post('/admin/students/status', ['student_ids' => [$c['id']], 'status' => 'inactive', 'effective_at' => date('Y-m-d\TH:i', strtotime('+3 days'))]);
        $check('a future effective date is refused', $students->find((int) $c['id'], $i)['status'] === 'active');
        $check('an unknown status is refused', $post('/admin/students/status', ['student_ids' => [$c['id']], 'status' => 'expelled'])->status() === 422);
        $post('/admin/students/status', ['student_ids' => [], 'status' => 'inactive']);
        $check('nothing selected changes nothing', $students->find((int) $c['id'], $i)['status'] === 'active');
        $check('setting the same status twice adds no history', PersonStatus::change($db, $i, 'student', [(int) $a['id']], 'active', null, null, (int) $admin['id']) === 0);
        $check('another institute\'s student cannot be changed', PersonStatus::change($db, $i + 999999, 'student', [(int) $c['id']], 'inactive', null, null, (int) $admin['id']) === 0 && $students->find((int) $c['id'], $i)['status'] === 'active');
        $check('a status change without the CSRF token is refused', $app->run(new Request([], ['student_ids' => [$c['id']], 'status' => 'inactive'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/students/status']))->status() === 419 && $students->find((int) $c['id'], $i)['status'] === 'active');

        // ---- employees ---------------------------------------------------------------------------------------
        $e1 = $mkEmployee('Erum');
        $db->query('INSERT INTO employee_attendance(employee_id,attendance_date,status,marked_by,created_at,updated_at) VALUES(:e,"2019-04-10","Present",:u,:c,:c2)', ['e' => $e1, 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
        $post('/admin/employees/statuses', ['employee_ids' => [$e1], 'status' => 'inactive', 'reason' => 'Resigned', 'effective_at' => '2019-04-15T09:00']);
        $emp = (new EmployeeRepository($db))->find($e1, $i);
        $check('an employee can be set inactive with a reason and date', $emp['status'] === 'inactive' && $emp['status_changed_at'] === '2019-04-15 09:00:00');
        $empReg = static fn(string $d) => $get('/admin/attendance/employees?date=' . $d)->content();
        $check('employee register before the date lists them, after it does not', str_contains($empReg('2019-04-12'), 'Erum ' . $suffix) && !str_contains($empReg('2019-04-25'), 'Erum ' . $suffix));
        $wf = new WorkflowRepository($db);
        $check('salary pending list keeps them for the month they left, not later months', in_array($e1, array_map('intval', array_column($wf->pendingSalaries($i, '2019-04'), 'id')), true) && !in_array($e1, array_map('intval', array_column($wf->pendingSalaries($i, '2019-06'), 'id')), true));
        $check('salary report keeps them for that period', str_contains(json_encode($rep->salaryReport($i, '2019-04', '2019-04')), 'Erum ' . $suffix));
        $check('employee monthly attendance keeps the month they were active', str_contains(json_encode($rep->employeeMonthlyAttendance($i, '2019-04')), 'Erum ' . $suffix));
        $check('the employee list still shows them with a Change status link', str_contains($get('/admin/employees?employee_search=' . rawurlencode('Erum ' . $suffix))->content(), '/admin/employees/status?search='));

        // ---- deletion is refused whenever history exists --------------------------------------------------
        $attBefore = (int) $db->query('SELECT COUNT(*) FROM student_attendance WHERE student_id=:s', ['s' => $a['id']])->fetchColumn();
        $post('/admin/students/' . $a['id'] . '/delete', []);
        $check('a student with attendance cannot be deleted', $students->find((int) $a['id'], $i) !== null && (int) $db->query('SELECT COUNT(*) FROM student_attendance WHERE student_id=:s', ['s' => $a['id']])->fetchColumn() === $attBefore);
        $check('the refusal explains what history exists and suggests Inactive', str_contains($flash(), 'attendance') && str_contains($flash(), 'Set them Inactive instead'));
        $d = $mkStudent('Dua');
        $db->query("INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,total_amount,paid_amount,remaining_amount,status,created_by,created_at,updated_at) VALUES(:s,'2019-04',:n,100,100,0,100,'unpaid',:u,:c,:c2)", ['s' => $d['id'], 'n' => 'PSI-' . $suffix, 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
        $post('/admin/students/' . $d['id'] . '/delete', []);
        $check('a student with a fee invoice cannot be deleted', $students->find((int) $d['id'], $i) !== null);
        $check('a student with only a portal login cannot be deleted', $students->deleteStudent((int) $b['id'], $i) === 'in_use' && $students->find((int) $b['id'], $i) !== null);
        $empty = $mkStudent('Esha');
        PersonStatus::change($db, $i, 'student', [(int) $empty['id']], 'inactive', 'mistake', null, (int) $admin['id']);
        $res = $post('/admin/students/bulk-delete', ['student_ids' => [$a['id'], $c['id'], $empty['id']]]);
        $check('bulk delete removes only the student with no history', $students->find((int) $a['id'], $i) !== null && $students->find((int) $c['id'], $i) !== null && $students->find((int) $empty['id'], $i) === null);
        $check('deleting a student with no history works and leaves no status history behind', (int) $db->query("SELECT COUNT(*) FROM person_status_history WHERE person_type='student' AND person_id=:p", ['p' => $empty['id']])->fetchColumn() === 0);
        $check('bulk delete reports how many were kept', str_contains($flash(), 'were not deleted because they have attendance'));
        $e2 = $mkEmployee('Ehsan');
        $db->query('INSERT INTO salary_advances(institute_id,employee_id,amount,date_given,status,created_by,created_at,updated_at) VALUES(:i,:e,500,"2019-04-01","outstanding",:u,:c,:c2)', ['i' => $i, 'e' => $e2, 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
        $res = $post('/admin/employees/' . $e2 . '/delete', []);
        $check('an employee with a salary advance is refused cleanly instead of crashing', $res->status() === 302 && (new EmployeeRepository($db))->find($e2, $i) !== null);
        $post('/admin/employees/' . $e1 . '/delete', []);
        $check('an employee with attendance cannot be deleted', (new EmployeeRepository($db))->find($e1, $i) !== null);
        $e3 = $mkEmployee('Faiz');
        $post('/admin/employees/' . $e3 . '/delete', []);
        $check('an employee with no history can be deleted', (new EmployeeRepository($db))->find($e3, $i) === null);

        // ---- rules page is admin-only for saving ------------------------------------------------------------
        $res = $post('/admin/settings/status-rules', ['rules' => []]);
        $check('saving the rules is audited', (bool) $db->query("SELECT 1 FROM audit_logs WHERE action='status.rules_updated' ORDER BY id DESC LIMIT 1")->fetchColumn());
    } finally {
        $asAdmin();
        $restoreRules();
        foreach ($uIds as $u) {
            $db->query('DELETE FROM student_accounts WHERE user_id=:u', ['u' => $u]);
            $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $u]);
            $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $u]);
            try { $db->query('DELETE FROM remember_tokens WHERE user_id=:u', ['u' => $u]); } catch (\Throwable) {}
            $db->query('DELETE FROM users WHERE id=:u', ['u' => $u]);
        }
        foreach ($sIds as $id) {
            $db->query('DELETE FROM fee_payments WHERE student_id=:s', ['s' => $id]);
            $db->query('DELETE FROM fee_invoices WHERE student_id=:s', ['s' => $id]);
            $db->query("DELETE FROM person_status_history WHERE person_type='student' AND person_id=:s", ['s' => $id]);
            $db->query('DELETE FROM students WHERE id=:s', ['s' => $id]);
        }
        foreach ($eIds as $id) {
            $db->query('DELETE FROM salary_advances WHERE employee_id=:e', ['e' => $id]);
            $db->query("DELETE FROM person_status_history WHERE person_type='employee' AND person_id=:e", ['e' => $id]);
            $db->query('DELETE FROM employees WHERE id=:e', ['e' => $id]);
        }
        $db->query('DELETE FROM classes WHERE id=:c', ['c' => $class]);
    }
    return $out;
};
