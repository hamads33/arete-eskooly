<?php
use App\Support\PersonStatus;
$cur = trim((string) ($institute['currency_symbol'] ?? $institute['currency'] ?? 'Rs'));
$money = static fn($v): string => number_format((float) $v, 0);
$monthLabel = date('F Y', strtotime($month . '-01'));
$admin = can('*');
$chip = static function (string $label, $n, string $tone): string { $n = (float) $n; return $n > 0 ? '<span class="ss-chip ss-chip--' . $tone . '">' . e(rtrim(rtrim(number_format($n, 1), '0'), '.')) . ' ' . $label . '</span>' : ''; };
$s = $summary;
?>
<main class="main"><div class="main__inner"><div class="module-page salary-sheet-page">
<header class="module-page__header"><div><nav class="breadcrumb no-print"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Salary</span><i data-lucide="chevron-right"></i><span>Salary Sheet</span></nav><h1>Salary Sheet <span class="ss-month"><?= e($monthLabel) ?></span></h1><p class="no-print">Bonus, deductions and advance recovery for every employee — change any row and pay it right here.</p></div>
<div class="filter-actions no-print"><button class="btn btn--secondary" type="button" onclick="window.print()"><i data-lucide="printer"></i>Print</button><a class="btn btn--secondary" href="<?= url('/admin/reports/salary-sheet/pdf?month=' . rawurlencode($month)) ?>" target="_blank" rel="noopener"><i data-lucide="file-text"></i>PDF</a><a class="btn btn--ghost" href="<?= url('/admin/salary/advances') ?>"><i data-lucide="hand-coins"></i>Advances</a></div></header>

<form class="ss-filters no-print" method="get" action="<?= url('/admin/salary/sheet') ?>">
  <div class="module-field"><label for="ss_month">Month</label><input class="module-input" type="month" id="ss_month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()"></div>
  <div class="module-field ss-filters__search"><label for="ss_q">Search</label><input class="module-input" type="search" id="ss_q" name="q" value="<?= e($q) ?>" placeholder="Name, employee no. or role"></div>
  <div class="module-field"><label for="ss_role">Role</label><select class="module-select" id="ss_role" name="role"><option value="">All roles</option><?php foreach ($roles as $ro): ?><option <?= $role === $ro ? 'selected' : '' ?>><?= e($ro) ?></option><?php endforeach ?></select></div>
  <div class="module-field"><label for="ss_state">Status</label><select class="module-select" id="ss_state" name="state"><option value="">All</option><option value="pending" <?= $state === 'pending' ? 'selected' : '' ?>>Pending</option><option value="paid" <?= $state === 'paid' ? 'selected' : '' ?>>Paid</option></select></div>
  <div class="ss-filters__actions"><button class="btn btn--primary"><i data-lucide="filter"></i>Apply</button><?php if ($q !== '' || $role !== '' || $state !== ''): ?><a class="btn btn--ghost" href="<?= url('/admin/salary/sheet?month=' . rawurlencode($month)) ?>">Clear</a><?php endif ?></div>
</form>

<section class="ss-tiles" aria-label="Summary">
  <div class="ss-tile"><span>On payroll</span><strong><?= (int) $s['count'] ?></strong><small>employees</small></div>
  <div class="ss-tile"><span>Total salary</span><strong><?= e($cur) ?> <?= $money($s['base']) ?></strong><small>before bonus &amp; deductions</small></div>
  <div class="ss-tile ss-tile--good"><span>Paid</span><strong><?= e($cur) ?> <?= $money($s['paid']) ?></strong><small><?= (int) $s['paid_count'] ?> employee<?= $s['paid_count'] == 1 ? '' : 's' ?></small></div>
  <div class="ss-tile ss-tile--warn"><span>Pending</span><strong><?= e($cur) ?> <?= $money($s['pending']) ?></strong><small><?= (int) $s['pending_count'] ?> employee<?= $s['pending_count'] == 1 ? '' : 's' ?> · estimated</small></div>
  <div class="ss-tile"><span>Deductions</span><strong><?= e($cur) ?> <?= $money($s['deductions']) ?></strong><small>on paid salaries</small></div>
  <div class="ss-tile"><span>Advance recovered</span><strong><?= e($cur) ?> <?= $money($s['recovered']) ?></strong><small>on paid salaries</small></div>
</section>

<?php if (!$rows): ?>
<section class="module-card"><div class="compact-empty"><h3><?= $s['count'] ? 'No employees match these filters' : 'No employees on the payroll for ' . e($monthLabel) ?></h3><p><?= $s['count'] ? 'Clear the filters to see everyone.' : 'Add employees or check their status.' ?></p></div></section>
<?php else: ?>
<form method="post" action="<?= url('/admin/salary/sheet/pay') ?>" data-ss-form data-currency="<?= e($cur) ?>"><?= csrf_field() ?><input type="hidden" name="month" value="<?= e($month) ?>"><?php foreach (['q' => $q, 'role' => $role, 'state' => $state] as $k => $v): if ((string) $v !== ''): ?><input type="hidden" name="keep_<?= $k ?>" value="<?= e((string) $v) ?>"><?php endif; endforeach ?>
<section class="module-card ss-card"><header class="module-card__header no-print"><div><h2><?= (int) $s['filtered'] ?> employee<?= $s['filtered'] == 1 ? '' : 's' ?></h2><p>Tick pending rows to pay them together. Typing in a pending row ticks it for you.</p></div></header>
<div class="ss-table-wrap"><table class="ss-table no-table-tools mobile-register">
<thead><tr><th class="ss-check no-print"><input type="checkbox" data-ss-all aria-label="Select all pending employees"></th><th>Employee</th><th>Attendance</th><th class="ss-num">Salary</th><th class="ss-num">Bonus</th><th class="ss-num">Deduction</th><th class="ss-num">Advance</th><th class="ss-num">Net pay</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($rows as $x): $id = (int) $x['id']; $paid = (bool) $x['payment_id']; $bal = (float) $x['advance_balance']; ?>
<tr class="ss-row <?= $paid ? 'is-paid' : 'is-pending' ?>" data-ss-row data-base="<?= e($x['base']) ?>" data-balance="<?= e($paid ? $x['recovery'] : $bal) ?>" data-paid="<?= $paid ? '1' : '0' ?>">
  <td class="ss-check no-print" data-label="Pay"><?php if (!$paid): ?><input type="checkbox" name="pay[]" value="<?= $id ?>" data-ss-pick aria-label="Pay <?= e($x['name']) ?>"><?php endif ?></td>
  <td class="ss-emp" data-label="Employee"><strong><?= e($x['name']) ?></strong><small><?= e($x['employee_no']) ?><?= $x['role'] ? ' · ' . e($x['role']) : '' ?></small><?php if ($x['status'] !== 'active'): ?><small class="person-status-tag person-status-tag--<?= e($x['status']) ?>"><?= e(PersonStatus::label($x['status'])) ?><?= $x['status_changed_at'] ? ' since ' . e(date('d M', strtotime($x['status_changed_at']))) : '' ?></small><?php endif ?></td>
  <td class="ss-att" data-label="Attendance"><?php if ((int) $x['marked_days'] === 0): ?><span class="ss-muted">Not marked</span><?php else: ?><?= $chip('P', $x['present_days'], 'p') . $chip('Late', $x['late_days'], 'l') . $chip('A', $x['absent_days'], 'a') . $chip('Half', $x['half_days'], 'h') . $chip('Leave', $x['leave_days'], 'v') ?><?php endif ?></td>
  <td class="ss-num" data-label="Salary"><strong><?= e($cur) ?> <?= $money($x['base']) ?></strong><small><?= e($cur) ?> <?= $money($x['day_rate']) ?>/day</small></td>
  <td class="ss-num" data-label="Bonus"><input class="ss-input" type="number" min="0" step="1" inputmode="numeric" value="<?= e($x['bonus'] + 0) ?>" data-ss-bonus <?= $paid ? 'form="ss-adj-' . $id . '" name="bonus" disabled' : 'name="row[' . $id . '][bonus]"' ?> aria-label="Bonus for <?= e($x['name']) ?>"></td>
  <td class="ss-num" data-label="Deduction"><input class="ss-input ss-input--deduct" type="number" min="0" step="1" inputmode="numeric" value="<?= e($x['deduction'] + 0) ?>" data-ss-deduction <?= $paid ? 'form="ss-adj-' . $id . '" name="deduction" disabled' : 'name="row[' . $id . '][deduction]"' ?> aria-label="Deduction for <?= e($x['name']) ?>"><?php if ((float) $x['suggested_deduction'] > 0): ?><button class="ss-suggest no-print" type="button" data-ss-suggest="<?= e(round((float) $x['suggested_deduction'])) ?>" <?= $paid ? 'hidden' : '' ?> title="<?= e(rtrim(rtrim(number_format($x['absence_units'], 1), '0'), '.')) ?> absent day(s) × <?= e($cur) ?> <?= $money($x['day_rate']) ?>">Absences: <?= e($cur) ?> <?= $money($x['suggested_deduction']) ?></button><?php endif ?></td>
  <td class="ss-num" data-label="Advance"><?php if ($paid): ?><?= (float) $x['recovery'] > 0 ? e($cur) . ' ' . $money($x['recovery']) . '<small>recovered</small>' : '<span class="ss-muted">—</span>' ?><?php elseif ($bal > 0): ?><input class="ss-input" type="number" min="0" max="<?= e(min($bal, (float) $x['base'])) ?>" step="1" inputmode="numeric" name="row[<?= $id ?>][recovery]" value="<?= e($x['recovery'] + 0) ?>" data-ss-recovery aria-label="Advance to recover from <?= e($x['name']) ?>"><small>due <?= e($cur) ?> <?= $money($bal) ?></small><?php else: ?><input type="hidden" name="row[<?= $id ?>][recovery]" value="0" data-ss-recovery><span class="ss-muted">—</span><?php endif ?></td>
  <td class="ss-num ss-net" data-label="Net pay"><strong data-ss-net><?= e($cur) ?> <?= $money($x['net']) ?></strong></td>
  <td class="ss-state" data-label="Status"><?php if ($paid): ?><span class="ss-badge ss-badge--paid">Paid <?= e(date('d M', strtotime($x['payment_date']))) ?></span><span class="ss-actions no-print"><a href="<?= url('/admin/salary/receipt/' . (int) $x['payment_id']) ?>">Slip</a><?php if ($admin): ?><button class="ss-link" type="button" data-ss-edit>Edit</button><button class="btn btn--primary btn--sm" type="submit" form="ss-adj-<?= $id ?>" data-ss-save hidden>Save</button><button class="ss-link" type="button" data-ss-cancel hidden>Cancel</button><?php endif ?></span><?php else: ?><span class="ss-badge ss-badge--pending">Pending</span><input class="ss-note no-print" name="row[<?= $id ?>][remarks]" maxlength="200" placeholder="Note (optional)" aria-label="Note for <?= e($x['name']) ?>"><?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot><tr><td class="no-print"></td><td colspan="2" class="ss-foot-title"><strong>Total (<?= count($rows) ?>)</strong></td><td class="ss-num" data-label="Salary"><strong><?= e($cur) ?> <?= $money(array_sum(array_column($rows, 'base'))) ?></strong></td><td class="ss-num" data-label="Bonus" data-ss-sum="bonus"><?= e($cur) ?> <?= $money(array_sum(array_column($rows, 'bonus'))) ?></td><td class="ss-num" data-label="Deduction" data-ss-sum="deduction"><?= e($cur) ?> <?= $money(array_sum(array_column($rows, 'deduction'))) ?></td><td class="ss-num" data-label="Advance" data-ss-sum="recovery"><?= e($cur) ?> <?= $money(array_sum(array_column($rows, 'recovery'))) ?></td><td class="ss-num ss-foot-net" data-label="Net pay"><strong data-ss-grand><?= e($cur) ?> <?= $money(array_sum(array_column($rows, 'net'))) ?></strong></td><td></td></tr></tfoot>
</table></div></section>

<?php if ($s['pending_count']): ?>
<div class="ss-paybar no-print" data-ss-paybar>
  <div class="ss-paybar__sum"><span><strong data-ss-count>0</strong> selected</span><strong class="ss-paybar__total" data-ss-total><?= e($cur) ?> 0</strong></div>
  <div class="module-field"><label for="ss_date">Payment date</label><input class="module-input" type="date" id="ss_date" name="payment_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
  <div class="module-field"><label for="ss_ref">Reference</label><input class="module-input" id="ss_ref" name="reference" maxlength="100" placeholder="Cheque / transfer no. (optional)"></div>
  <button class="btn btn--primary btn--lg" type="submit" data-ss-submit disabled><i data-lucide="badge-check"></i>Pay selected</button>
</div>
<?php endif ?>
</form>
<?php foreach ($rows as $x): if (!$x['payment_id'] || !$admin) continue; ?><form id="ss-adj-<?= (int) $x['id'] ?>" method="post" action="<?= url('/admin/salary/payments/' . (int) $x['payment_id'] . '/adjust') ?>" class="ss-adjust-form" data-ss-adjust data-name="<?= e($x['name']) ?>"><?= csrf_field() ?><input type="hidden" name="month" value="<?= e($month) ?>"><?php foreach (['q' => $q, 'role' => $role, 'state' => $state] as $k => $v): if ((string) $v !== ''): ?><input type="hidden" name="keep_<?= $k ?>" value="<?= e((string) $v) ?>"><?php endif; endforeach ?></form><?php endforeach ?>
<div class="ss-print-sign print-only"><span>Prepared by ____________________</span><span>Checked by ____________________</span><span>Approved by ____________________</span></div>
<?php endif ?>
</div></div></main>
