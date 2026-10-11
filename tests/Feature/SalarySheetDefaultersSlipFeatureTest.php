<?php
declare(strict_types=1);

use App\Core\{App, CSRF, Request};
use App\Repositories\{AcademicRepository, FeeRepository, SettingsRepository, StudentRepository, WorkflowRepository};
use App\Services\StudentService;
use App\Support\PersonStatus;

/** Salary Sheet (on-the-spot deductions), Fee Defaulters and Fees Paid Slip — positive, negative and edge cases. */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['sheet/defaulters/slip: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $i = (int) (new SettingsRepository($db))->institute()['id'];
    $admin = $db->query('SELECT u.id,u.name,u.email,u.status FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" ORDER BY u.id LIMIT 1')->fetch();
    $app->session()->set('auth_user', $admin + ['roles' => ['admin'], 'permissions' => ['*']]);
    $suffix = bin2hex(random_bytes(4));
    $now = date('Y-m-d H:i:s');
    $get = static function (string $uri) use ($app) { parse_str((string) parse_url($uri, PHP_URL_QUERY), $q); return $app->run(new Request($q, [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri])); };
    $post = static fn(string $uri, array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $flash = static fn(): string => (string) json_encode($_SESSION ?? []);
    $w = new WorkflowRepository($db);
    $fees = new FeeRepository($db);
    $eIds = []; $sIds = []; $class = 0;
    $month = '2019-05';
    try {
        // ================= Salary Sheet =================
        $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,mobile,status,created_at,updated_at) VALUES(:i,:e,:n,"2019-01-01","Teacher",31000,"03001112233","active",:c,:u)', ['i' => $i, 'e' => 'SS-' . $suffix, 'n' => 'Sheet Teacher ' . $suffix, 'c' => $now, 'u' => $now]);
        $e = $eIds[] = (int) $pdo->lastInsertId();
        $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,"2019-01-01","Accountant",20000,"active",:c,:u)', ['i' => $i, 'e' => 'SS2-' . $suffix, 'n' => 'Sheet Accountant ' . $suffix, 'c' => $now, 'u' => $now]);
        $e2 = $eIds[] = (int) $pdo->lastInsertId();
        foreach (range(1, 20) as $d) $db->query('INSERT INTO employee_attendance(employee_id,attendance_date,status,marked_by,created_at,updated_at) VALUES(:e,:d,"Present",:u,:c,:c2)', ['e' => $e, 'd' => sprintf('2019-05-%02d', $d), 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
        foreach (['2019-05-21' => 'Absent', '2019-05-22' => 'Absent', '2019-05-23' => 'Half Day', '2019-05-24' => 'Leave'] as $d => $st) $db->query('INSERT INTO employee_attendance(employee_id,attendance_date,status,marked_by,created_at,updated_at) VALUES(:e,:d,:s,:u,:c,:c2)', ['e' => $e, 'd' => $d, 's' => $st, 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
        $w->giveSalaryAdvance($i, $e, 5000, '2019-04-15', 'QA advance', (int) $admin['id']);
        $row = static fn(int $id) => array_values(array_filter($w->salarySheet($i, $month), fn($x) => (int) $x['id'] === $id))[0] ?? null;
        $r = $row($e);
        $check('sheet lists the employee with attendance counted', $r && (int) $r['present_days'] === 20 && (int) $r['absent_days'] === 2 && (int) $r['half_days'] === 1 && (int) $r['leave_days'] === 1);
        $check('absence deduction suggestion = (2 absent + ½ half day) × day rate', abs($r['suggested_deduction'] - round(31000 / 31 * 2.5, 2)) < 0.01);
        $check('outstanding advance is pre-filled for recovery', (float) $r['recovery'] === 5000.0 && (float) $r['net'] === 26000.0);
        $page = $get('/admin/salary/sheet?month=' . $month);
        $check('sheet page renders with tiles, inputs and the absence button', $page->status() === 200 && str_contains($page->content(), 'Sheet Teacher ' . $suffix) && str_contains($page->content(), 'row[' . $e . '][deduction]') && str_contains($page->content(), 'Absences:') && str_contains($page->content(), 'On payroll'));
        $check('sheet filters by status and search', !str_contains($get('/admin/salary/sheet?month=' . $month . '&state=paid&q=' . $suffix)->content(), 'Sheet Teacher ' . $suffix) && str_contains($get('/admin/salary/sheet?month=' . $month . '&state=pending&q=Accountant%20' . $suffix)->content(), 'Sheet Accountant ' . $suffix));

        // negative bulk pay
        $count = static fn() => (int) $db->query("SELECT COUNT(*) FROM salary_payments WHERE employee_id IN ($e,$e2) AND salary_month='2019-05'")->fetchColumn();
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => '2019-05-31', 'pay' => []]);
        $check('paying with nothing ticked does nothing', $count() === 0 && str_contains($flash(), 'Tick at least one'));
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => date('Y-m-d', strtotime('+2 days')), 'pay' => [$e]]);
        $check('a future payment date is refused', $count() === 0);
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => '2019-05-31', 'pay' => [$e], 'row' => [$e => ['bonus' => '0', 'deduction' => '-5', 'recovery' => '0']]]);
        $check('a negative deduction is refused', $count() === 0);
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => '2019-05-31', 'pay' => [$e], 'row' => [$e => ['bonus' => '0', 'deduction' => '99999', 'recovery' => '0']]]);
        $check('a deduction leaving zero or less is refused', $count() === 0 && str_contains($flash(), 'net salary must be more than zero'));
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => '2019-05-31', 'pay' => [$e], 'row' => [$e => ['bonus' => '0', 'deduction' => '0', 'recovery' => '9000']]]);
        $check('recovering more advance than is owed is refused', $count() === 0);
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => '2019-05-31', 'pay' => [$e], 'row' => [$e => ['bonus' => 'abc', 'deduction' => '0', 'recovery' => '0']]]);
        $check('text in a number field is refused', $count() === 0);
        $check('a pay request without the CSRF token is refused', $app->run(new Request([], ['month' => $month, 'payment_date' => '2019-05-31', 'pay' => [$e]], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/salary/sheet/pay']))->status() === 419 && $count() === 0);

        // positive bulk pay: two employees at once, one with on-the-spot bonus / deduction / partial recovery
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => '2019-05-31', 'reference' => 'BATCH-' . $suffix, 'pay' => [$e, $e2], 'row' => [$e => ['bonus' => '1000', 'deduction' => '2419', 'recovery' => '3000', 'remarks' => 'absences'], $e2 => ['bonus' => '0', 'deduction' => '0', 'recovery' => '0']]]);
        $p1 = $db->query("SELECT * FROM salary_payments WHERE employee_id=:e AND salary_month='2019-05'", ['e' => $e])->fetch();
        $check('bulk pay records each salary with the typed bonus, deduction and recovery', $p1 && (float) $p1['paid_amount'] === 26581.0 && (float) $p1['deduction_amount'] === 2419.0 && (float) $p1['advance_recovered'] === 3000.0 && $p1['reference'] === 'BATCH-' . $suffix && $p1['remarks'] === 'absences');
        $check('the second employee is paid in the same batch', (float) $db->query("SELECT paid_amount FROM salary_payments WHERE employee_id=:e AND salary_month='2019-05'", ['e' => $e2])->fetchColumn() === 20000.0);
        $check('the recovered advance reduces the outstanding balance', abs($w->advanceBalance($e) - 2000) < 0.01);
        $post('/admin/salary/sheet/pay', ['month' => $month, 'payment_date' => '2019-05-31', 'pay' => [$e], 'row' => [$e => ['bonus' => '0', 'deduction' => '0', 'recovery' => '0']]]);
        $check('paying the same month twice is refused', $count() === 2 && str_contains($flash(), 'already paid'));
        // A browser's next request starts the session, which moves the flash from "next" to "now".
        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? []; unset($_SESSION['_flash_next']);
        $page = $get('/admin/salary/sheet?month=' . $month)->content();
        $check('an error notification looks like an error (alert icon, error colour, close button)', str_contains($page, 'data-type="error"') && str_contains($page, 'data-lucide="circle-alert"') && str_contains($page, 'toast__close'));

        // mixed batch: one valid row, one invalid row — filters survive the redirect and one message explains both
        $res = $post('/admin/salary/sheet/pay', ['month' => '2019-04', 'payment_date' => '2019-04-30', 'keep_q' => 'Sheet', 'keep_role' => 'Teacher', 'keep_state' => 'pending', 'pay' => [$e, $e2], 'row' => [$e => ['bonus' => '0', 'deduction' => '99999', 'recovery' => '0'], $e2 => ['bonus' => '0', 'deduction' => '0', 'recovery' => '0']]]);
        $loc = (string) ($res->headers()['Location'] ?? '');
        $check('after paying, the sheet reopens with the same month, search, role and status filters', str_contains($loc, 'month=2019-04') && str_contains($loc, 'q=Sheet') && str_contains($loc, 'role=Teacher') && str_contains($loc, 'state=pending'));
        $check('a batch with a failure says what was paid and why the rest was not, in one warning', str_contains($flash(), '1 salary paid for April 2019') && str_contains($flash(), 'Not paid') && str_contains($flash(), 'more than zero') && str_contains($flash(), '"warning"'));
        $check('…and only the valid row was paid', (int) $db->query("SELECT COUNT(*) FROM salary_payments WHERE employee_id IN ($e,$e2) AND salary_month='2019-04'")->fetchColumn() === 1);
        $r = $row($e);
        $check('sheet shows the paid row with its real figures', (int) $r['payment_id'] === (int) $p1['id'] && (float) $r['bonus'] === 1000.0 && (float) $r['net'] === 26581.0);

        // on-the-spot edit of a paid salary
        $post('/admin/salary/payments/' . $p1['id'] . '/adjust', ['month' => $month, 'bonus' => '0', 'deduction' => '1000']);
        $p1b = $db->query('SELECT * FROM salary_payments WHERE id=:id', ['id' => $p1['id']])->fetch();
        $check('editing a paid salary recomputes the net (31000 + 0 − 1000 − 3000)', (float) $p1b['paid_amount'] === 27000.0 && (float) $p1b['deduction_amount'] === 1000.0 && (float) $p1b['advance_recovered'] === 3000.0);
        $check('the edit is audited', (bool) $db->query("SELECT 1 FROM audit_logs WHERE action='salary.payment_adjusted' AND entity_id=:id", ['id' => $p1['id']])->fetchColumn());
        $check('the account statement uses the edited amount', in_array(27000.0, array_map('floatval', array_column(array_filter((new \App\Repositories\AccountRepository($db))->ledger($i, '2019-05-31', '2019-05-31', 'salary'), fn($x) => (int) $x['ref_id'] === (int) $p1['id']), 'amount')), true));
        foreach (['negative' => ['bonus' => '-1', 'deduction' => '0'], 'zero net' => ['bonus' => '0', 'deduction' => '28000'], 'future date' => ['bonus' => '0', 'deduction' => '0', 'payment_date' => date('Y-m-d', strtotime('+3 days'))], 'text' => ['bonus' => 'x', 'deduction' => '0']] as $label => $body) {
            $post('/admin/salary/payments/' . $p1['id'] . '/adjust', ['month' => $month] + $body);
            $check('editing with a ' . $label . ' value is refused', (float) $db->query('SELECT paid_amount FROM salary_payments WHERE id=:id', ['id' => $p1['id']])->fetchColumn() === 27000.0);
        }
        $check('editing a payment that does not exist is refused', str_contains((string) $post('/admin/salary/payments/999999999/adjust', ['month' => $month, 'bonus' => '0', 'deduction' => '0'])->headers()['Location'] ?? '', '/admin/salary/sheet') && str_contains($flash(), 'not found'));

        // an employee who left mid-month is still on that month's sheet and payable
        PersonStatus::change($db, $i, 'employee', [$e2], 'inactive', 'Left', '2019-06-15 12:00', (int) $admin['id']);
        $check('an employee who left mid-month stays on that month\'s sheet', $row($e2) !== null && array_filter($w->salarySheet($i, '2019-06'), fn($x) => (int) $x['id'] === $e2));
        $check('…and can still be paid for that month', $w->paySalary($i, $e2, '2019-06', 10000, '2019-06-20', (int) $admin['id']) > 0);
        $check('…but is not on later months', !array_filter($w->salarySheet($i, '2019-08'), fn($x) => (int) $x['id'] === $e2));
        $threw = false; try { $w->paySalary($i, $e2, '2019-08', 10000, '2019-08-20', (int) $admin['id']); } catch (\DomainException) { $threw = true; }
        $check('…and cannot be paid for a month after leaving', $threw);

        // ================= Fee Defaulters =================
        $academic = new AcademicRepository($db);
        $academic->createClass($i, 'Def QA ' . $suffix, 4000, null);
        $class = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $i, 'n' => 'Def QA ' . $suffix])->fetchColumn();
        $students = new StudentRepository($db);
        $mk = static function (string $n, string $phone) use ($students, $i, $class, $suffix, &$sIds) {
            $d = (new StudentService($students))->data(['studentname' => $n . ' ' . $suffix, 'reg' => 'DQ-' . $suffix . '-' . count($sIds), 'doa' => '2019-01-01', 'class' => $class, 'discount' => 0, 'fname' => 'Father ' . $n, 'fmobile' => $phone], $i);
            $d['created_at'] = $d['updated_at'];
            $sIds[] = $id = $students->create($d);
            if ($phone !== '') $students->update($id, $i, ['father_mobile' => $phone]);
            return $id;
        };
        $x = $mk('Xara', '03009998877'); $y = $mk('Yasir', ''); $z = $mk('Zoya', '');
        $inv = static function (int $sid, string $m, float $total, float $paid, ?string $due, bool $void = false) use ($db, $pdo, $admin, $now, $suffix) {
            $db->query("INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,total_amount,paid_amount,remaining_amount,status,due_date,voided_at,created_by,created_at,updated_at) VALUES(:s,:m,:n,:t,:t2,:p,:r,:st,:d,:v,:u,:c,:c2)",
                ['s' => $sid, 'm' => $m, 'n' => 'DQI-' . $sid . '-' . $m . '-' . $suffix, 't' => $total, 't2' => $total, 'p' => $paid, 'r' => $void ? 0 : $total - $paid, 'st' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'), 'd' => $due, 'v' => $void ? $now : null, 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
            return (int) $pdo->lastInsertId();
        };
        $xi1 = $inv($x, '2019-05', 4000, 3000, '2019-05-10');
        $xi2 = $inv($x, '2019-06', 4000, 0, null);
        $inv($y, '2019-05', 4000, 4000, '2019-05-10');
        $inv($z, '2019-05', 4000, 0, '2019-05-10', true);
        $db->query("INSERT INTO fee_payments(fee_invoice_id,student_id,receipt_no,payment_date,amount,payment_method,reference,received_by,created_at) VALUES(:f,:s,:r,'2019-05-08',3000,'Cash','DQREF',:u,:c)", ['f' => $xi1, 's' => $x, 'r' => 'DQR-' . $suffix, 'u' => $admin['id'], 'c' => $now]);
        $pay1 = (int) $pdo->lastInsertId();
        $db->query("INSERT INTO fee_payments(fee_invoice_id,student_id,receipt_no,payment_date,amount,payment_method,received_by,created_at,reversed_at,reversal_reason) VALUES(:f,:s,:r,'2019-05-09',500,'Bank',:u,:c,:c2,'bounced')", ['f' => $xi1, 's' => $x, 'r' => 'DQRV-' . $suffix, 'u' => $admin['id'], 'c' => $now, 'c2' => $now]);
        $def = static fn(array $f) => array_values(array_filter($fees->defaulters($i, $f + ['class' => $class]), fn($d) => str_contains($d['student_name'], $suffix)));
        $all = $def([]);
        $check('defaulters are grouped per student with every month they owe', count($all) === 1 && $all[0]['student_id'] === $x && count($all[0]['months']) === 2 && abs($all[0]['due'] - 5000) < 0.01);
        $check('overdue amount counts only months past the due date', abs($all[0]['overdue'] - 1000) < 0.01);
        $check('fully paid and voided invoices are not defaulters', !array_filter($all, fn($d) => in_array($d['student_id'], [$y, $z], true)));
        $check('last payment date ignores reversed payments', $all[0]['last_payment'] === '2019-05-08');
        $check('guardian phone comes from the father\'s mobile', $all[0]['phone'] === '03009998877');
        $check('overdue filter', count($def(['state' => 'overdue'])) === 1 && count($def(['state' => 'overdue', 'month' => '2019-06'])) === 0);
        $check('partly paid / nothing paid filters', count($def(['state' => 'partial'])) === 1 && count($def(['state' => 'unpaid'])) === 0);
        $check('minimum due filter', count($def(['minimum' => 5000])) === 1 && count($def(['minimum' => 5001])) === 0);
        $check('month filter totals just that month', abs(($def(['month' => '2019-06'])[0]['due'] ?? 0) - 4000) < 0.01);
        $unb = $def(['month' => '2019-07', 'include_unbilled' => true]);
        $check('students not billed for a month can be included at their expected fee', count($unb) >= 2 && (bool) array_filter($unb, fn($d) => $d['student_id'] === $y && $d['not_billed'] && $d['due'] >= 4000));
        $page = $get('/admin/fees/defaulters?class=' . $class)->content();
        $check('defaulters page renders the student once with month chips, phone and actions', substr_count($page, '<strong>Xara ' . $suffix . '</strong>') === 1 && str_contains($page, 'fd-month') && str_contains($page, 'tel:03009998877') && str_contains($page, 'Remind') && str_contains($page, 'Print list'));
        $csv = $get('/admin/fees/defaulters?class=' . $class . '&export=csv');
        $check('CSV export downloads the list', str_contains((string) ($csv->headers()['Content-Type'] ?? ''), 'text/csv') && str_contains($csv->content(), 'Xara ' . $suffix) && str_contains($csv->content(), '5000.00'));
        $pdf = $get('/admin/reports/fee-defaulters/pdf?class=' . $class);
        $check('defaulters PDF downloads as a compact landscape list', $pdf->status() === 200 && str_contains((string) ($pdf->headers()['Content-Type'] ?? ''), 'pdf') && str_starts_with($pdf->content(), '%PDF'));
        $orient = static function (string $pdf): string { preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)/', $pdf, $m); return $m ? ((float) $m[1] > (float) $m[2] ? 'L' : 'P') : '?'; };
        $check('the admin can pick portrait (classic) or landscape (compact) for a report PDF', $orient($get('/admin/reports/fee-defaulters/pdf?layout=portrait')->content()) === 'P' && $orient($get('/admin/reports/fee-defaulters/pdf?layout=landscape')->content()) === 'L');
        $check('portrait keeps the classic layout; landscape uses the compact one', !\App\Services\Pdf\PdfService::isReport('document', ['type' => 'admissions', 'columns' => ['a' => 'a']], ['page_orientation' => 'P']) && \App\Services\Pdf\PdfService::isReport('document', ['type' => 'admissions', 'columns' => ['a' => 'a']], ['page_orientation' => 'L']));
        $check('list reports use the compact layout; letters and slips keep theirs', \App\Services\Pdf\PdfService::isReport('document', ['type' => 'fee-defaulters', 'columns' => ['a' => 'a']]) && !\App\Services\Pdf\PdfService::isReport('document', ['type' => 'fee-receipt', 'columns' => ['a' => 'a']]) && !\App\Services\Pdf\PdfService::isReport('document', ['type' => 'admission-letter']));
        $total = (int) $db->query('SELECT COUNT(*) FROM students WHERE institute_id=:i', ['i' => $i])->fetchColumn();
        $check('every student is listed, not just the first 25 (' . $total . ')', count((new StudentRepository($db))->paginate($i)) === $total && count((new StudentRepository($db))->basicList($i)) === $total);
        $check('bad filter values are ignored safely', $get('/admin/fees/defaulters?state=hack&sort=drop&month=2019-13&minimum=-5')->status() === 200);

        // ================= Fees Paid Slip =================
        $slips = $fees->paidSlips($i, ['q' => 'DQR-' . $suffix]);
        $check('paid slip search finds a receipt by number', count($slips) === 1 && (int) $slips[0]['id'] === $pay1);
        $check('reversed payments are hidden by default', count($fees->paidSlips($i, ['q' => $suffix])) === 1);
        $check('…and can be shown', count($fees->paidSlips($i, ['q' => $suffix, 'reversed' => 'all'])) === 2 && count($fees->paidSlips($i, ['q' => $suffix, 'reversed' => 'only'])) === 1);
        $check('fee month, date range and method filters', count($fees->paidSlips($i, ['q' => $suffix, 'month' => '2019-05'])) === 1 && count($fees->paidSlips($i, ['q' => $suffix, 'month' => '2019-06'])) === 0 && count($fees->paidSlips($i, ['q' => $suffix, 'from' => '2019-05-09'])) === 0 && count($fees->paidSlips($i, ['q' => $suffix, 'method' => 'Bank'])) === 0);
        $check('search by reference and by student registration', count($fees->paidSlips($i, ['q' => 'DQREF'])) >= 1 && count($fees->paidSlips($i, ['q' => 'DQ-' . $suffix . '-0'])) === 1);
        $sp = $get('/admin/fees/receipt?q=' . rawurlencode('DQR-' . $suffix))->content();
        $check('paid slip page shows the receipt with View, Detailed and WhatsApp', str_contains($sp, 'DQR-' . $suffix) && str_contains($sp, '/admin/fees/receipt/' . $pay1) && str_contains($sp, '/admin/reports/fee-receipt/' . $pay1 . '/pdf') && str_contains($sp, 'whatsapp'));
        $check('the default view lists recent payments', $get('/admin/fees/receipt')->status() === 200 && str_contains($get('/admin/fees/receipt')->content(), 'Fees Paid Slip'));
        $check('a receipt that does not exist is a 404', $get('/admin/fees/receipt/999999999')->status() === 404);
        $check('bad filter values are handled', $get('/admin/fees/receipt?month=bad&from=x&to=y&reversed=hack')->status() === 200);
    } finally {
        foreach ($eIds as $id) {
            $db->query('DELETE FROM salary_advance_recoveries WHERE salary_advance_id IN (SELECT id FROM salary_advances WHERE employee_id=:e)', ['e' => $id]);
            $db->query('DELETE FROM salary_advances WHERE employee_id=:e', ['e' => $id]);
            $db->query('DELETE FROM salary_payments WHERE employee_id=:e', ['e' => $id]);
            $db->query('DELETE FROM employee_attendance WHERE employee_id=:e', ['e' => $id]);
            $db->query("DELETE FROM person_status_history WHERE person_type='employee' AND person_id=:e", ['e' => $id]);
            $db->query('DELETE FROM employees WHERE id=:e', ['e' => $id]);
        }
        foreach ($sIds as $id) {
            $db->query('DELETE FROM fee_payments WHERE student_id=:s', ['s' => $id]);
            $db->query('DELETE FROM fee_invoices WHERE student_id=:s', ['s' => $id]);
            $db->query('DELETE FROM students WHERE id=:s', ['s' => $id]);
        }
        if ($class) $db->query('DELETE FROM classes WHERE id=:c', ['c' => $class]);
    }
    return $out;
};
