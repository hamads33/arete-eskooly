<?php
declare(strict_types=1);

use App\Core\{App, CSRF, Request};
use App\Repositories\{AccountRepository, FeeRepository, WorkflowRepository};

/**
 * Admin edit/reversal of income, expense, fee and salary entries from every screen that lists
 * them (statement, daily, expense report, fee collection), with the negative and edge cases,
 * plus the mobile attendance and upload markup.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['finance edit: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $adminRow = $db->query('SELECT u.id,u.name,u.email,u.status FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetch();
    $admin = (int) $adminRow['id'];
    $app->session()->set('auth_user', array_merge($adminRow, ['roles' => ['admin'], 'permissions' => ['*']]));
    $get = static function (string $uri) use ($app) { parse_str((string) parse_url($uri, PHP_URL_QUERY), $q); return $app->run(new Request($q, [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri])); };
    $post = static fn(string $uri, array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $location = static fn($res): string => (string) ($res->headers()['Location'] ?? '');
    $r = new AccountRepository($db);
    $fees = new FeeRepository($db);
    $headIds = [];
    $employee = 0;
    $invoices = [];

    try {
        // ==== manual income / expense ======================================================
        foreach (['expense', 'income', 'both'] as $type) {
            $r->addHead($institute, ucfirst($type) . ' head ' . $suffix, $type);
            $headIds[$type] = (int) $db->query('SELECT id FROM account_heads WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => ucfirst($type) . ' head ' . $suffix])->fetchColumn();
        }
        $expense = $r->create($institute, 'expense', $today, 'Generator fuel ' . $suffix, 1200, $admin, null, $headIds['expense']);
        $income = $r->create($institute, 'income', $today, 'Book sale ' . $suffix, 800, $admin, null, $headIds['income']);
        $edit = static fn(int $id, array $over) => $post('/admin/accounts/transactions/' . $id, $over + ['date' => $today, 'description' => 'Generator fuel ' . $suffix, 'amount' => '1200', 'reference' => '', 'account_head' => $headIds['expense']]);

        // every listing screen offers Edit + Reverse for manual entries
        $statement = $get('/admin/accounts/statement?search=' . $suffix);
        $check('statement shows edit and reverse for manual entries', $statement->status() === 200 && str_contains($statement->content(), '/admin/accounts/transactions/' . $expense . '/edit') && str_contains($statement->content(), 'data-reverse-form'));
        $report = $get('/admin/accounts/expense-report?q=' . $suffix . '&from=' . $today . '&to=' . $today);
        $check('expense report shows edit and reverse', $report->status() === 200 && str_contains($report->content(), '/admin/accounts/transactions/' . $expense . '/edit') && str_contains($report->content(), '/admin/accounts/transactions/' . $expense . '/delete'));
        $daily = $get('/admin/accounts/daily?date=' . $today);
        $check('daily report shows edit for income and expense', $daily->status() === 200 && str_contains($daily->content(), '/admin/accounts/transactions/' . $income . '/edit') && str_contains($daily->content(), '/admin/accounts/transactions/' . $expense . '/edit'));
        $check('the edit page renders', $get('/admin/accounts/transactions/' . $expense . '/edit')->status() === 200);
        $check('editing a non-existent transaction is a 404', $get('/admin/accounts/transactions/999999999/edit')->status() === 404);

        // positive edits
        $res = $edit($expense, ['description' => 'Generator diesel ' . $suffix, 'amount' => '1500', 'reference' => 'R-' . $suffix, 'return' => '/admin/accounts/expense-report?q=' . $suffix]);
        $row = $r->find($expense, $institute);
        $check('an expense can be edited (amount, description, reference)', (float) $row['amount'] === 1500.0 && $row['description'] === 'Generator diesel ' . $suffix && $row['reference_type'] === 'R-' . $suffix);
        $check('saving returns to the screen it came from', str_contains($location($res), '/admin/accounts/expense-report'));
        $edit($expense, ['description' => 'Generator diesel ' . $suffix, 'amount' => '1500', 'date' => '2019-06-30']);
        $check('the date of an expense can be changed', $r->find($expense, $institute)['transaction_date'] === '2019-06-30');
        $edit($expense, ['description' => 'Generator diesel ' . $suffix, 'amount' => '1500', 'account_head' => $headIds['both']]);
        $check('an expense can move to a head that allows both types', (int) $r->find($expense, $institute)['account_head_id'] === $headIds['both']);
        $edit($expense, ['description' => 'Generator diesel ' . $suffix, 'amount' => '0.01']);
        $check('the smallest valid amount (0.01) is accepted', (float) $r->find($expense, $institute)['amount'] === 0.01);
        $post('/admin/accounts/transactions/' . $income, ['date' => $today, 'description' => 'Book sale ' . $suffix, 'amount' => '950', 'reference' => '', 'account_head' => $headIds['income']]);
        $check('an income entry can be edited', (float) $r->find($income, $institute)['amount'] === 950.0);
        $res = $edit($expense, ['description' => 'Generator diesel ' . $suffix, 'amount' => '1500', 'return' => 'https://evil.example/']);
        $check('an outside return address is ignored', str_contains($location($res), '/admin/accounts/statement') && !str_contains($location($res), 'evil'));

        // negative edits leave the row untouched
        $before = $r->find($expense, $institute);
        foreach ([['amount' => '0'], ['amount' => '-50'], ['amount' => 'abc'], ['description' => ''], ['description' => str_repeat('x', 256)], ['date' => 'not-a-date'], ['reference' => str_repeat('r', 51)], ['account_head' => $headIds['income']], ['account_head' => 999999999]] as $bad) {
            $edit($expense, $bad + ['description' => 'Generator diesel ' . $suffix, 'amount' => '1500']);
        }
        $check('zero, negative, non-numeric, blank, over-long, bad-date and wrong-head edits are all rejected', $before == $r->find($expense, $institute));
        $res = $post('/admin/accounts/transactions/999999999', ['date' => $today, 'description' => 'x', 'amount' => '5', 'reference' => '', 'account_head' => $headIds['expense']]);
        $check('updating a non-existent transaction is a 404', $res->status() === 404);
        $app->run(new Request([], ['date' => $today, 'description' => 'CSRF ' . $suffix, 'amount' => '9', 'reference' => '', 'account_head' => $headIds['expense']], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/accounts/transactions/' . $expense]));
        $check('an edit without a CSRF token is refused', $r->find($expense, $institute)['description'] !== 'CSRF ' . $suffix);

        // reversal
        $res = $post('/admin/accounts/transactions/' . $expense . '/delete', ['reason' => 'duplicate', 'return' => '/admin/accounts/daily?date=' . $today]);
        $check('an expense can be reversed', $r->find($expense, $institute) === null);
        $check('reversal returns to the screen it came from', str_contains($location($res), '/admin/accounts/daily'));
        $check('the reversal is audited with its reason', str_contains((string) $db->query("SELECT metadata_json FROM audit_logs WHERE action='account.transaction_deleted' AND entity_id=:id ORDER BY id DESC LIMIT 1", ['id' => $expense])->fetchColumn(), 'duplicate'));
        $post('/admin/accounts/transactions/' . $expense . '/delete', ['reason' => 'again']);
        $check('reversing the same entry twice is harmless', $r->find($expense, $institute) === null && $r->find($income, $institute) !== null);
        $post('/admin/accounts/transactions/' . $income . '/delete', ['reason' => 'wrong entry']);
        $check('an income entry can be reversed', $r->find($income, $institute) === null);
        $check('reversed entries disappear from the statement', !str_contains($get('/admin/accounts/statement?search=' . $suffix)->content(), 'Book sale ' . $suffix));

        // ==== fee payments ====================================================================
        $student = (int) $db->query('SELECT id FROM students WHERE institute_id=:i ORDER BY id LIMIT 1', ['i' => $institute])->fetchColumn();
        if ($student) {
            $db->query("INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,total_amount,paid_amount,remaining_amount,status,created_by,created_at,updated_at) VALUES(:s,'2019-12',:n,5000,5000,4000,1000,'partial',:u,:c,:c2)",
                ['s' => $student, 'n' => 'TINV-' . $suffix, 'u' => $admin, 'c' => $now, 'c2' => $now]);
            $invoice = $invoices[] = (int) $pdo->lastInsertId();
            $mkPayment = static function (float $amount) use ($db, $pdo, $invoice, $student, $admin, $now, $today, $suffix): int {
                $db->query("INSERT INTO fee_payments(fee_invoice_id,student_id,receipt_no,payment_date,amount,payment_method,received_by,created_at) VALUES(:f,:s,:r,:d,:a,'Cash',:u,:c)",
                    ['f' => $invoice, 's' => $student, 'r' => 'TRC-' . random_int(100000, 999999) . $suffix, 'd' => $today, 'a' => $amount, 'u' => $admin, 'c' => $now]);
                return (int) $pdo->lastInsertId();
            };
            $pay1 = $mkPayment(3000);
            $pay2 = $mkPayment(1000);
            $inv = static fn() => $db->query('SELECT paid_amount,remaining_amount,status FROM fee_invoices WHERE id=:id', ['id' => $invoice])->fetch();
            $editFee = static fn(int $id, array $over) => $post('/admin/fees/payments/' . $id, $over + ['payment_date' => $today, 'amount' => '1000', 'payment_method' => 'Cash', 'reference' => '', 'remarks' => '']);

            $collect = $get('/admin/fees/collect?student_id=' . $student . '&month=2019-12');
            $check('fee collection history shows an Edit button', $collect->status() === 200 && str_contains($collect->content(), '/admin/fees/payments/' . $pay1 . '/edit'));
            $check('statement shows Edit for fee payments', str_contains($get('/admin/accounts/statement?from_date=' . $today . '&to_date=' . $today . '&source=fee')->content(), '/admin/fees/payments/' . $pay1 . '/edit'));
            $check('daily report shows Edit for fee payments', str_contains($get('/admin/accounts/daily?date=' . $today)->content(), '/admin/fees/payments/' . $pay1 . '/edit'));
            $check('the fee payment edit page renders', $get('/admin/fees/payments/' . $pay1 . '/edit')->status() === 200);
            $check('editing a non-existent fee payment is a 404', $get('/admin/fees/payments/999999999/edit')->status() === 404);

            $res = $editFee($pay1, ['amount' => '2500', 'payment_method' => 'JazzCash', 'reference' => 'TX-' . $suffix, 'payment_date' => '2019-12-05']);
            $p = $fees->payment($pay1, $institute);
            $check('a fee payment can be edited (amount, method, date, reference)', (float) $p['amount'] === 2500.0 && $p['payment_method'] === 'JazzCash' && $p['payment_date'] === '2019-12-05' && $p['reference'] === 'TX-' . $suffix);
            $i = $inv();
            $check('lowering a payment raises the invoice balance', (float) $i['paid_amount'] === 3500.0 && (float) $i['remaining_amount'] === 1500.0 && $i['status'] === 'partial');
            $check('fee edit returns to the collection screen', str_contains($location($res), '/admin/fees/collect?student_id=' . $student));
            $check('the fee edit is audited', (bool) $db->query("SELECT 1 FROM audit_logs WHERE action='fee.payment_edited' AND entity_id=:id", ['id' => $pay1])->fetchColumn());

            $editFee($pay1, ['amount' => '4000']);
            $i = $inv();
            $check('paying exactly the remaining balance marks the invoice paid', (float) $fees->payment($pay1, $institute)['amount'] === 4000.0 && (float) $i['remaining_amount'] === 0.0 && $i['status'] === 'paid');

            $before = $fees->payment($pay1, $institute);
            foreach ([['amount' => '4000.02'], ['amount' => '0'], ['amount' => '-10'], ['amount' => 'ten'], ['payment_date' => '05/12/2019'], ['payment_method' => 'Bitcoin'], ['payment_method' => 'Bank Transfer']] as $bad) {
                $editFee($pay1, $bad + ['amount' => '4000']);
            }
            $check('over-paying, zero, negative, non-numeric, bad-date, unknown and unconfigured-method edits are rejected', $before == $fees->payment($pay1, $institute) && $inv()['status'] === 'paid');

            $post('/admin/fees/payments/' . $pay2 . '/reverse', ['reason' => 'cheque bounced', 'return' => '/admin/accounts/statement']);
            $i = $inv();
            $check('reversing a fee payment restores the invoice balance', (float) $i['paid_amount'] === 4000.0 && (float) $i['remaining_amount'] === 1000.0 && $i['status'] === 'partial');
            $before = $fees->payment($pay2, $institute);
            $editFee($pay2, ['amount' => '500']);
            $check('a reversed payment cannot be edited', $fees->payment($pay2, $institute) == $before);
            $post('/admin/fees/payments/' . $pay2 . '/reverse', ['reason' => 'again']);
            $check('reversing a fee payment twice does not change the balance', (float) $inv()['paid_amount'] === 4000.0);
            $before = $inv();
            $post('/admin/fees/payments/' . $pay1 . '/reverse', ['reason' => '']);
            $check('a fee reversal without a reason is refused', $inv() == $before && $fees->payment($pay1, $institute)['reversed_at'] === null);
            $editFee($pay1, ['amount' => '5000']);
            $check('the balance freed by a reversal can be used by editing another payment', (float) $fees->payment($pay1, $institute)['amount'] === 5000.0 && $inv()['status'] === 'paid');
            $check('updating a non-existent fee payment is a 404', $post('/admin/fees/payments/999999999', ['payment_date' => $today, 'amount' => '1', 'payment_method' => 'Cash'])->status() === 404);
        }

        // ==== salary ============================================================================
        $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",30000,"active",:x,:x2)',
            ['i' => $institute, 'e' => 'EMP-REV-' . $suffix, 'n' => 'Reverse Teacher ' . $suffix, 'd' => $today, 'x' => $now, 'x2' => $now]);
        $employee = (int) $pdo->lastInsertId();
        $w = new WorkflowRepository($db);
        $w->giveSalaryAdvance($institute, $employee, 4000, $today, 'Advance', $admin);
        $pay = $w->paySalary($institute, $employee, '2019-11', 26000, $today, $admin, 0, null, null, 0, 4000);
        $check('the advance is recovered by the salary payment', $w->advanceBalance($employee) === 0.0);
        $check('daily report shows Reverse for salary payments', str_contains($get('/admin/accounts/daily?date=' . $today)->content(), '/admin/salary/payments/' . $pay . '/reverse'));
        $post('/admin/salary/payments/' . $pay . '/reverse', ['reason' => '']);
        $check('a salary reversal without a reason is refused', (bool) $db->query('SELECT 1 FROM salary_payments WHERE id=:id', ['id' => $pay])->fetchColumn());
        $post('/admin/salary/payments/' . $pay . '/reverse', ['reason' => 'paid wrong month']);
        $check('a salary payment can be reversed', !$db->query('SELECT 1 FROM salary_payments WHERE id=:id', ['id' => $pay])->fetchColumn());
        $check('reversing salary restores the recovered advance', $w->advanceBalance($employee) === 4000.0);
        $post('/admin/salary/payments/' . $pay . '/reverse', ['reason' => 'again']);
        $check('reversing the same salary twice is harmless', $w->advanceBalance($employee) === 4000.0);
        $check('the month can be paid again after reversal', $w->paySalary($institute, $employee, '2019-11', 30000, $today, $admin) > 0);

        // ==== mobile markup =====================================================================
        $att = $get('/admin/attendance/employees?date=' . $today);
        $check('employee attendance renders its register', $att->status() === 200 && str_contains($att->content(), 'attendance-table') && str_contains($att->content(), 'data-label="Status"'));
        $att = $get('/admin/attendance/students?date=' . $today . '&scope=all');
        $check('student attendance renders its register', $att->status() === 200 && str_contains($att->content(), 'attendance-table'));
        $check('table sorting and filters load on every page', str_contains($att->content(), 'js/table-tools.js'));
    } finally {
        $db->query('DELETE FROM account_transactions WHERE institute_id=:i AND description LIKE :d', ['i' => $institute, 'd' => '%' . $suffix]);
        foreach ($headIds as $h) {
            $db->query('DELETE FROM account_heads WHERE id=:id', ['id' => $h]);
        }
        foreach ($invoices as $invoice) {
            $db->query('DELETE FROM fee_payments WHERE fee_invoice_id=:f', ['f' => $invoice]);
            $db->query('DELETE FROM fee_invoices WHERE id=:f', ['f' => $invoice]);
        }
        if ($employee) {
            $db->query('DELETE FROM salary_advance_recoveries WHERE salary_advance_id IN (SELECT id FROM salary_advances WHERE employee_id=:e)', ['e' => $employee]);
            $db->query('DELETE FROM salary_advances WHERE employee_id=:e', ['e' => $employee]);
            $db->query('DELETE FROM salary_payments WHERE employee_id=:e', ['e' => $employee]);
            $db->query('DELETE FROM employees WHERE id=:e', ['e' => $employee]);
        }
    }
    return $out;
};
