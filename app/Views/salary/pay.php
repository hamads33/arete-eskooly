<?php $cur = $institute['currency_symbol'] ?? 'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><h1>Pay Salary</h1><p>Record a salary payment, with an optional bonus, deduction and advance auto-deducted from what's due.</p></div><div class="filter-actions"><a class="btn btn--primary" href="<?= url('/admin/salary/sheet') ?>"><i data-lucide="table-2"></i>Salary Sheet</a><a class="btn btn--secondary" href="<?= url('/admin/salary/advances') ?>">Advance Salary</a></div></header>

<section class="module-card"><div class="module-card__body">
<form method="post" action="<?= url('/admin/salary/pay') ?>" data-salary-form>
<?= csrf_field() ?>
<div class="module-form-grid module-form-grid--three">
<div class="module-field"><label>Employee</label><select class="module-select" name="employee_id" required data-salary-employee><option value="">Select Employee</option><?php foreach($employees as$e):?><option value="<?= $e['id'] ?>" data-advance="<?= number_format((float)($advanceBalances[$e['id']] ?? 0), 2, '.', '') ?>" data-salary="<?= number_format((float)$e['monthly_salary'], 2, '.', '') ?>"><?= e($e['employee_no'].' — '.$e['name'].' ('.number_format((float)$e['monthly_salary'],2).')'.(isset($advanceBalances[$e['id']])?' — advance due '.$cur.' '.number_format($advanceBalances[$e['id']],2):'')) ?></option><?php endforeach ?></select></div>
<div class="module-field"><label>Salary Month</label><input class="module-input" type="month" name="salary_month" value="<?= date('Y-m') ?>" required></div>
<div class="module-field"><label>Payment Date</label><input class="module-input" type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></div>
</div>
<div class="salary-net-banner" data-salary-net-banner hidden>
<div><span>Monthly Salary</span><strong data-salary-net-base><?= e($cur) ?> 0.00</strong></div>
<div><span>Advance (Deducted)</span><strong data-salary-net-advance class="is-danger"><?= e($cur) ?> 0.00</strong></div>
<div><span>Net Payable</span><strong data-salary-net-payable class="is-primary"><?= e($cur) ?> 0.00</strong></div>
</div>
<div class="module-form-grid module-form-grid--three">
<div class="module-field"><label>Paid Amount</label><input class="module-input" type="number" min="0.01" step="0.01" name="paid_amount" required data-salary-paid><small>Prefilled as salary minus deduction and advance — adjust if needed.</small></div>
<div class="module-field"><label>Bonus / Adjustment</label><input class="module-input" type="number" step="0.01" name="adjustment_amount" value="0" data-salary-bonus><small>A positive bonus. Enter a deduction below instead of a negative number here.</small></div>
<div class="module-field"><label>Deduction</label><input class="module-input" type="number" min="0" step="0.01" name="deduction_amount" value="0" data-salary-deduction><small>Fine, absence, leave without pay, etc.</small></div>
<div class="module-field"><label>Advance Deduction</label><input class="module-input" type="number" min="0" step="0.01" name="advance_recovery" value="0" data-salary-advance-recovery><small data-salary-advance-hint>Advance to deduct for this employee: <?= e($cur) ?> 0.00</small></div>
<div class="module-field"><label>Reference</label><input class="module-input" name="reference"></div>
<div class="module-field field--wide"><label>Remarks</label><textarea class="module-textarea" name="remarks"></textarea></div>
</div>
<button class="btn btn--primary">Pay Salary</button>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Salary Pending</h2><p>Active employees not yet paid for the selected month.</p></div>
<form method="get" class="filter-bar filter-bar--compact" style="margin:0"><div class="module-field"><label>Month</label><input class="module-input" type="month" name="month" value="<?= e($selectedMonth) ?>"></div><button class="btn btn--secondary">View</button></form>
</header><div class="module-card__body">
<?php if(!$pending):?><div class="compact-empty"><h3>All salaries paid</h3><p>Every active employee has been paid for <?= e(date('F Y', strtotime($selectedMonth.'-01'))) ?>.</p></div><?php else:?>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Employee</th><th>Role</th><th>Monthly Salary</th><th>Advance</th><th>Net Payable</th><th></th></tr></thead><tbody>
<?php foreach($pending as$p):$net=max(0,(float)$p['monthly_salary']-(float)$p['advance_balance']);?><tr><td><?= e($p['employee_no'].' — '.$p['name']) ?></td><td><?= e($p['role']) ?></td><td><?= e($cur) ?> <?= number_format((float)$p['monthly_salary'],2) ?></td><td><?= (float)$p['advance_balance']>0 ? '<span class="status-badge status-badge--pending">'.e($cur).' '.number_format((float)$p['advance_balance'],2).'</span>' : '—' ?></td><td><strong><?= e($cur) ?> <?= number_format($net,2) ?></strong></td><td><a class="btn btn--secondary" href="?month=<?= e($selectedMonth) ?>&employee_id=<?= $p['id'] ?>#top" data-salary-prefill="<?= $p['id'] ?>">Pay Now</a></td></tr><?php endforeach ?>
</tbody></table></div>
<p class="dashboard-card__subtitle"><?= count($pending) ?> employee<?= count($pending)===1?'':'s' ?> pending for <?= e(date('F Y', strtotime($selectedMonth.'-01'))) ?>.</p>
<?php endif ?>
</div></section>

<section class="module-card"><header class="module-card__header"><h2>Recent Payments</h2></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Employee</th><th>Month</th><th>Deduction</th><th>Advance Deducted</th><th>Amount</th><th>Date</th><th>Slip</th></tr></thead><tbody><?php foreach($payments as$p):?><tr><td><?= e($p['name']) ?></td><td><?= e($p['salary_month']) ?></td><td><?= number_format((float)($p['deduction_amount']??0),2) ?></td><td><?= number_format((float)($p['advance_recovered']??0),2) ?></td><td><?= number_format((float)$p['paid_amount'],2) ?></td><td><?= e($p['payment_date']) ?></td><td><a href="<?= url('/admin/salary/receipt/'.$p['id']) ?>">View</a></td></tr><?php endforeach ?></tbody></table></div></div></section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var select = document.querySelector('[data-salary-employee]');
  var hint = document.querySelector('[data-salary-advance-hint]');
  var recoveryInput = document.querySelector('[data-salary-advance-recovery]');
  var paidInput = document.querySelector('[data-salary-paid]');
  var bonusInput = document.querySelector('[data-salary-bonus]');
  var deductionInput = document.querySelector('[data-salary-deduction]');
  var banner = document.querySelector('[data-salary-net-banner]');
  var bannerBase = document.querySelector('[data-salary-net-base]');
  var bannerAdvance = document.querySelector('[data-salary-net-advance]');
  var bannerPayable = document.querySelector('[data-salary-net-payable]');
  var currency = <?= json_encode($cur) ?>;

  function money(n) { return currency + ' ' + n.toFixed(2); }

  function onEmployeeChange() {
    var opt = select.options[select.selectedIndex];
    var balance = opt ? parseFloat(opt.getAttribute('data-advance') || '0') : 0;
    var base = opt ? parseFloat(opt.getAttribute('data-salary') || '0') : 0;
    hint.textContent = 'Advance to deduct for this employee: ' + money(balance);
    recoveryInput.max = balance > 0 ? balance.toFixed(2) : 0;
    banner.hidden = !opt || !opt.value;
    // Salary due: automatically recover the full outstanding advance first, employee gets the remainder.
    recoveryInput.value = balance > 0 ? Math.min(balance, base).toFixed(2) : 0;
    recalc();
  }

  function recalc() {
    var opt = select.options[select.selectedIndex];
    var base = opt ? parseFloat(opt.getAttribute('data-salary') || '0') : 0;
    var bonus = parseFloat(bonusInput.value || '0') || 0;
    var deduction = parseFloat(deductionInput.value || '0') || 0;
    var recovery = parseFloat(recoveryInput.value || '0') || 0;
    var net = Math.max(0, base + bonus - deduction - recovery);
    bannerBase.textContent = money(base);
    bannerAdvance.textContent = money(recovery);
    bannerPayable.textContent = money(net);
    paidInput.value = net.toFixed(2);
  }

  select.addEventListener('change', onEmployeeChange);
  [bonusInput, deductionInput, recoveryInput].forEach(function (el) {
    el.addEventListener('input', recalc);
  });
  onEmployeeChange();
  var params = new URLSearchParams(window.location.search);
  var employeeId = params.get('employee_id');
  if (employeeId) { select.value = employeeId; onEmployeeChange(); }
});
</script>
</div></div></main>
