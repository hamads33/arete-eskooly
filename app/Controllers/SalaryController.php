<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};use App\Repositories\{WorkflowRepository,SettingsRepository,EmployeeRepository};use App\Support\Flash;
final class SalaryController extends AdminPageController
{
    private function i():int{return(int)(new SettingsRepository($this->app->database()))->institute()['id'];}
    private function w():WorkflowRepository{return new WorkflowRepository($this->app->database());}

    public function pay(Request $r): Response
    {
        $month = (string) $r->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
        $w = $this->w();
        return $this->renderPage('salary-pay', [
            'employees' => (new EmployeeRepository($this->app->database()))->all($this->i(), '', true),
            'payments' => $w->salaryPayments($this->i(), (int) $r->query('employee_id', 0)),
            'advanceBalances' => $w->advanceBalances($this->i()),
            'pending' => $w->pendingSalaries($this->i(), $month),
            'selectedMonth' => $month,
        ]);
    }

    public function store(Request$r):Response{
        try{
            $id=(new WorkflowRepository($this->app->database()))->paySalary(
                $this->i(),(int)$r->input('employee_id'),(string)$r->input('salary_month'),(float)$r->input('paid_amount'),
                (string)$r->input('payment_date'),(int)user()['id'],(float)$r->input('adjustment_amount',0),
                (string)$r->input('reference')?:null,(string)$r->input('remarks')?:null,
                (float)$r->input('deduction_amount',0),(float)$r->input('advance_recovery',0)
            );
        }catch(\DomainException$e){Flash::add('error',$e->getMessage());return Response::redirect(url('/admin/salary/pay?month='.rawurlencode((string)$r->input('salary_month')).'&employee_id='.(int)$r->input('employee_id')));}
        Flash::add('success','Salary payment saved.');
        return Response::redirect(url('/admin/salary/receipt/'.$id));
    }

    public function receipt(Request $r): Response {$id=(int)$r->route('id',(int)$r->query('id',0));$w=new WorkflowRepository($this->app->database());$slip=$id?$w->salarySlip($id,$this->i()):null;if($id&&!$slip)return Response::html('Salary payment not found',404);return $this->renderPage('salary-receipt',['slip'=>$slip,'payments'=>$w->salaryPayments($this->i())]); }

    /** Give / view salary advances. */
    public function advances(Request $r): Response
    {
        $w = $this->w();
        return $this->renderPage('salary-advances', [
            'employees' => (new EmployeeRepository($this->app->database()))->all($this->i(), '', true),
            'advances' => $w->salaryAdvances($this->i(), (int) $r->query('employee_id', 0)),
        ]);
    }

    public function giveAdvance(Request $r): Response
    {
        try {
            $this->w()->giveSalaryAdvance($this->i(), (int) $r->input('employee_id'), (float) $r->input('amount'), (string) $r->input('date_given'), (string) $r->input('reason') ?: null, (int) user()['id']);
            Flash::add('success', 'Advance recorded.');
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/salary/advances'));
    }

    public function writeOffAdvance(Request $r): Response
    {
        if ($this->w()->writeOffSalaryAdvance($this->i(), (int) $r->route('id'))) {
            Flash::add('success', 'Remaining advance balance written off.');
        } else {
            Flash::add('error', 'That advance could not be written off.');
        }
        return Response::redirect(url('/admin/salary/advances'));
    }

    /** Monthly salary sheet: every employee on the payroll with attendance, deductions, advance recovery and net pay, editable on the spot. */
    public function sheet(Request $r): Response
    {
        $month = (string) $r->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
        $all = $this->w()->salarySheet($this->i(), $month);
        $roles = array_values(array_unique(array_filter(array_map(static fn($x) => (string) $x['role'], $all))));
        sort($roles);
        $q = trim((string) $r->query('q', ''));
        $role = (string) $r->query('role', '');
        $state = (string) $r->query('state', '');
        $rows = array_values(array_filter($all, static function (array $x) use ($q, $role, $state): bool {
            if ($q !== '' && stripos($x['name'] . ' ' . $x['employee_no'] . ' ' . $x['role'], $q) === false) return false;
            if ($role !== '' && $x['role'] !== $role) return false;
            if ($state === 'paid' && !$x['payment_id']) return false;
            if ($state === 'pending' && $x['payment_id']) return false;
            return true;
        }));
        $sum = static fn(array $list, string $k): float => array_sum(array_map(static fn($x) => (float) $x[$k], $list));
        $paid = array_filter($all, static fn($x) => (bool) $x['payment_id']);
        $pending = array_filter($all, static fn($x) => !$x['payment_id']);
        return $this->renderPage('salary-sheet', [
            'month' => $month, 'rows' => $rows, 'roles' => $roles, 'q' => $q, 'role' => $role, 'state' => $state,
            'summary' => ['count' => count($all), 'base' => $sum($all, 'base'), 'paid_count' => count($paid), 'paid' => $sum($paid, 'net'), 'pending_count' => count($pending), 'pending' => $sum($pending, 'net'), 'deductions' => $sum($paid, 'deduction'), 'recovered' => $sum($paid, 'recovery'), 'filtered' => count($rows)],
        ]);
    }

    /** Back to the Salary Sheet for $month with the search / role / status filters the admin had applied. */
    private function sheetBack(Request $r, string $month): string
    {
        $query = ['month' => $month];
        foreach (['q', 'role', 'state'] as $k) {
            $v = trim((string) $r->input('keep_' . $k, ''));
            if ($v !== '') $query[$k] = mb_substr($v, 0, 80);
        }
        return url('/admin/salary/sheet?' . http_build_query($query));
    }

    /** Pays every ticked employee on the sheet with the bonus / deduction / advance recovery typed in their row. */
    public function sheetPay(Request $r): Response
    {
        $month = (string) $r->input('month');
        $back = $this->sheetBack($r, $month);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) { Flash::add('error', 'Choose a valid month.'); return Response::redirect(url('/admin/salary/sheet')); }
        $date = (string) $r->input('payment_date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) { Flash::add('error', 'Choose a payment date that is today or earlier.'); return Response::redirect($back); }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $r->input('pay', [])))));
        if (!$ids) { Flash::add('error', 'Tick at least one pending employee to pay.'); return Response::redirect($back); }
        $rowsIn = (array) $r->input('row', []);
        $sheet = [];
        foreach ($this->w()->salarySheet($this->i(), $month) as $x) $sheet[(int) $x['id']] = $x;
        $reference = trim((string) $r->input('reference', '')) ?: null;
        $done = 0; $errors = [];
        foreach ($ids as $id) {
            $x = $sheet[$id] ?? null;
            if (!$x) { $errors[] = 'Employee #' . $id . ' is not on this month\'s payroll.'; continue; }
            if ($x['payment_id']) { $errors[] = $x['name'] . ' is already paid for this month.'; continue; }
            $in = (array) ($rowsIn[$id] ?? []);
            $num = static fn($v) => is_numeric($v) ? (float) $v : null;
            $bonus = $num($in['bonus'] ?? 0); $deduction = $num($in['deduction'] ?? 0); $recovery = $num($in['recovery'] ?? 0);
            if ($bonus === null || $deduction === null || $recovery === null || $bonus < 0 || $deduction < 0 || $recovery < 0) { $errors[] = $x['name'] . ': bonus, deduction and advance must be numbers of 0 or more.'; continue; }
            $net = round((float) $x['base'] + $bonus - $deduction - $recovery, 2);
            if ($net <= 0) { $errors[] = $x['name'] . ': net salary must be more than zero.'; continue; }
            try {
                $this->w()->paySalary($this->i(), $id, $month, $net, $date, (int) user()['id'], $bonus, $reference, trim((string) ($in['remarks'] ?? '')) ?: null, $deduction, $recovery);
                $done++;
            } catch (\DomainException $e) {
                $errors[] = $x['name'] . ': ' . $e->getMessage();
            }
        }
        // One notification for the whole batch: what was paid, and why the rest were not.
        $paid = $done . ' salar' . ($done === 1 ? 'y' : 'ies') . ' paid for ' . date('F Y', strtotime($month . '-01'));
        if (!$errors) {
            Flash::add('success', $paid . '.');
        } else {
            $why = implode(' • ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? ' • and ' . (count($errors) - 3) . ' more.' : '');
            Flash::add($done ? 'warning' : 'error', ($done ? $paid . '. Not paid: ' : 'Nothing was paid. ') . $why);
        }
        return Response::redirect($back);
    }

    /** Edits the bonus / deduction (and date, reference, remarks) of a salary already paid. Admin only, audited. */
    public function adjustPayment(Request $r): Response
    {
        $month = (string) $r->input('month', date('Y-m'));
        $back = $this->sheetBack($r, preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : date('Y-m'));
        if (!can('*')) return Response::html('Only an administrator can change a paid salary.', 403);
        $num = static fn($v) => is_numeric($v) ? (float) $v : null;
        $bonus = $num($r->input('bonus', 0)); $deduction = $num($r->input('deduction', 0));
        if ($bonus === null || $deduction === null) { Flash::add('error', 'Bonus and deduction must be numbers.'); return Response::redirect($back); }
        try {
            $change = $this->w()->adjustSalaryPayment((int) $r->route('id'), $this->i(), $bonus, $deduction, trim((string) $r->input('payment_date', '')) ?: null, $r->input('reference') !== null ? (string) $r->input('reference') : null, $r->input('remarks') !== null ? (string) $r->input('remarks') : null);
        } catch (\DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect($back);
        }
        (new \App\Repositories\PakistanErpRepository($this->app->database()))->audit($this->i(), (int) user()['id'], 'salary.payment_adjusted', 'salary_payment', (int) $r->route('id'), $change);
        Flash::add('success', 'Salary updated: net paid is now ' . number_format((float) $change['after']['paid_amount'], 2) . '.');
        return Response::redirect($back);
    }
}
