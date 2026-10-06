<?php
declare(strict_types=1);

use App\Core\App;
use App\Repositories\WorkflowRepository;

/** Salary advances, deductions, and the salary-pending list. */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['salary: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $w = new WorkflowRepository($db);

    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",30000,"active",:x,:x2)',
        ['i' => $institute, 'e' => 'EMP-SAL-' . $suffix, 'n' => 'Salary Teacher ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $employee = (int) $pdo->lastInsertId();
    $month = '2019-0' . random_int(1, 9);

    try {
        // ---- pending list ---------------------------------------------------------------
        $pending = array_column($w->pendingSalaries($institute, $month), null, 'id');
        $check('an unpaid employee shows in the pending list', isset($pending[$employee]) && (float) $pending[$employee]['monthly_salary'] === 30000.0);

        // ---- advances ---------------------------------------------------------------------
        $check('a new employee has no advance balance', $w->advanceBalance($employee) === 0.0);
        $advance1 = $w->giveSalaryAdvance($institute, $employee, 4000, date('Y-m-d', strtotime('-10 days')), 'First advance', $admin);
        $advance2 = $w->giveSalaryAdvance($institute, $employee, 3000, date('Y-m-d'), 'Second advance', $admin);
        $check('advance balance is the sum of outstanding advances', $w->advanceBalance($employee) === 7000.0);
        $check('advanceBalances lists the employee', ($w->advanceBalances($institute)[$employee] ?? null) === 7000.0);
        $threw = false;
        try { $w->giveSalaryAdvance($institute, $employee, 0, date('Y-m-d'), null, $admin); } catch (DomainException) { $threw = true; }
        $check('a zero advance is rejected', $threw);

        // ---- paying with a deduction and a partial advance recovery -----------------------
        $threw = false;
        try { $w->paySalary($institute, $employee, $month, 30000, date('Y-m-d'), $admin, 0, null, null, 0, 99999); } catch (DomainException) { $threw = true; }
        $check('recovering more than the outstanding balance is rejected', $threw && $w->advanceBalance($employee) === 7000.0);

        $paymentId = $w->paySalary($institute, $employee, $month, 26500, date('Y-m-d'), $admin, 1000, 'REF-' . $suffix, 'note', 1500, 5000);
        $slip = $w->salarySlip($paymentId, $institute);
        $check('net adjustment folds in bonus, deduction and advance recovery', (float) $slip['adjustment_amount'] === -5500.0);
        $check('deduction and advance recovery are stored separately', (float) $slip['deduction_amount'] === 1500.0 && (float) $slip['advance_recovered'] === 5000.0);
        $check('recovery is applied oldest-advance-first (FIFO)', $w->advanceBalance($employee) === 2000.0);
        $advances = array_column($w->salaryAdvances($institute, $employee), null, 'id');
        $check('the older advance is fully recovered and settled', (float) $advances[$advance1]['balance'] === 0.0 && $advances[$advance1]['status'] === 'settled');
        $check('the newer advance keeps its remaining balance', (float) $advances[$advance2]['balance'] === 2000.0 && $advances[$advance2]['status'] === 'outstanding');
        $check('the paid employee drops off the pending list for that month', !array_key_exists($employee, array_column($w->pendingSalaries($institute, $month), null, 'id')));
        $otherMonth = $month === '2019-01' ? '2019-02' : '2019-01';
        $pendingElsewhere = array_column($w->pendingSalaries($institute, $otherMonth), null, 'id');
        $check('the remaining advance balance shows against the employee for an unpaid month', ($pendingElsewhere[$employee]['advance_balance'] ?? null) !== null && (float) $pendingElsewhere[$employee]['advance_balance'] === 2000.0);

        $threw = false;
        try { $w->paySalary($institute, $employee, $month, 26500, date('Y-m-d'), $admin); } catch (DomainException) { $threw = true; }
        $check('a second payment for the same month is rejected', $threw);

        // ---- write off ----------------------------------------------------------------------
        $check('write-off clears the remaining balance', $w->writeOffSalaryAdvance($institute, $advance2) && $w->advanceBalance($employee) === 0.0);
        $check('write-off cannot be repeated on a settled advance', !$w->writeOffSalaryAdvance($institute, $advance2));
    } finally {
        $db->query('DELETE FROM salary_advance_recoveries WHERE salary_advance_id IN (SELECT id FROM salary_advances WHERE employee_id=:e)', ['e' => $employee]);
        $db->query('DELETE FROM salary_advances WHERE employee_id=:e', ['e' => $employee]);
        $db->query('DELETE FROM salary_payments WHERE employee_id=:e', ['e' => $employee]);
        $db->query('DELETE FROM employees WHERE id=:e', ['e' => $employee]);
    }
    return $out;
};
