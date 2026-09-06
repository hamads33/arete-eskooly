<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>Dashboard</span></nav><h1>Welcome, <?=e($employee['name'])?></h1><p><?=e($employee['role'])?> · Employee <?=e($employee['employee_no'])?></p></div></header>

<div class="metric-grid">
<div class="metric-card"><span>Attendance</span><strong><?=e((string)$attendance['percentage'])?>%</strong></div>
<div class="metric-card"><span>Present Days</span><strong><?=(int)$attendance['present']?> / <?=(int)$attendance['total']?></strong></div>
<div class="metric-card"><span>Salary Paid</span><strong><?=e($cur)?> <?=number_format($salaryTotal,2)?></strong></div>
<div class="metric-card"><span>My Classes</span><strong><?=count($classes)?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Recent Salary Payments</h2></div><a class="btn btn--ghost" href="<?=e(url('/portal/employee/salary'))?>">View all</a></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Month</th><th>Base</th><th>Adjustment</th><th>Paid</th><th>Payment Date</th></tr></thead><tbody>
<?php foreach($salaries as $s):?><tr><td><?=e($s['salary_month'])?></td><td><?=e($cur)?> <?=number_format((float)$s['base_salary'],2)?></td><td><?=e($cur)?> <?=number_format((float)$s['adjustment_amount'],2)?></td><td><?=e($cur)?> <?=number_format((float)$s['paid_amount'],2)?></td><td><?=e($s['payment_date'])?></td></tr><?php endforeach?>
<?php if(!$salaries):?><tr><td colspan="5"><div class="compact-empty"><h3>No Salary Records</h3><p>Salary payments will appear here once recorded.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Recent Homework Assigned</h2></div><a class="btn btn--ghost" href="<?=e(url('/portal/employee/homework'))?>">View all</a></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Homework</th></tr></thead><tbody>
<?php foreach($homework as $h):?><tr><td><?=e($h['homework_date'])?></td><td><?=e($h['class_name'])?></td><td><?=e($h['subject']??'—')?></td><td><?=e($h['homework_text'])?></td></tr><?php endforeach?>
<?php if(!$homework):?><tr><td colspan="4"><div class="compact-empty"><h3>No Homework Assigned</h3><p>Homework you assign will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
