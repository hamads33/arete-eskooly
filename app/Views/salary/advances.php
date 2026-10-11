<?php $cur = $institute['currency_symbol'] ?? 'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><h1>Advance Salary</h1><p>Give an employee a salary advance; it is recovered from their future salary payments.</p></div><a class="btn btn--secondary" href="<?= url('/admin/salary/pay') ?>">Pay Salary</a></header>

<section class="module-card"><header class="module-card__header"><h2>Give Advance</h2></header><div class="module-card__body">
<form method="post" action="<?= url('/admin/salary/advances') ?>">
<?= csrf_field() ?>
<div class="module-form-grid module-form-grid--three">
<div class="module-field"><label>Employee</label><select class="module-select" name="employee_id" required><option value="">Select Employee</option><?php foreach($employees as$e):?><option value="<?= $e['id'] ?>"><?= e($e['employee_no'].' — '.$e['name'].' ('.number_format((float)$e['monthly_salary'],2).')') ?></option><?php endforeach ?></select></div>
<div class="module-field"><label>Amount</label><input class="module-input" type="number" min="0.01" step="0.01" name="amount" required></div>
<div class="module-field"><label>Date Given</label><input class="module-input" type="date" name="date_given" value="<?= date('Y-m-d') ?>" required></div>
<div class="module-field field--wide"><label>Reason <small>(optional)</small></label><input class="module-input" name="reason" maxlength="255" placeholder="e.g. medical emergency"></div>
</div>
<button class="btn btn--primary">Give Advance</button>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><h2>Advances</h2></header><div class="module-card__body">
<?php if(!$advances):?><div class="compact-empty"><h3>No advances yet</h3><p>Advances given to employees will appear here.</p></div><?php else:?>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Employee</th><th>Date</th><th>Amount</th><th>Recovered</th><th>Balance</th><th>Reason</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach($advances as$a):?><tr>
<td><?= e($a['employee_no'].' — '.$a['name']) ?></td>
<td><?= e($a['date_given']) ?></td>
<td><?= e($cur) ?> <?= number_format((float)$a['amount'],2) ?></td>
<td><?= e($cur) ?> <?= number_format((float)$a['recovered_amount'],2) ?></td>
<td><?= e($cur) ?> <?= number_format((float)$a['balance'],2) ?></td>
<td><?= e($a['reason'] ?: '—') ?></td>
<td><span class="status-badge status-badge--<?= $a['status']==='outstanding'?'pending':($a['status']==='settled'?'active':'inactive') ?>"><?= e(ucwords(str_replace('_',' ',$a['status']))) ?></span></td>
<td><?php if($a['status']==='outstanding'):?><form method="post" action="<?= url('/admin/salary/advances/'.$a['id'].'/write-off') ?>" onsubmit="return confirm('Write off the remaining balance of this advance? This cannot be undone.')"><?= csrf_field() ?><button class="btn btn--secondary">Write Off</button></form><?php endif ?></td>
</tr><?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</div></section>
</div></div></main>
