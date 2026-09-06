<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Salary</span></nav><h1>My Salary</h1><p>Salary payments recorded against your record.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<div class="metric-grid">
<div class="metric-card"><span>Monthly Salary</span><strong><?=e($cur)?> <?=number_format((float)$employee['monthly_salary'],2)?></strong></div>
<div class="metric-card"><span>Total Paid</span><strong><?=e($cur)?> <?=number_format($total,2)?></strong></div>
<div class="metric-card"><span>Payments</span><strong><?=count($payments)?></strong></div>
<div class="metric-card"><span>Designation</span><strong><?=e($employee['role'])?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Salary History</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Month</th><th>Base Salary</th><th>Adjustment</th><th>Paid</th><th>Payment Date</th></tr></thead><tbody>
<?php foreach($payments as $p):?><tr><td><?=e($p['salary_month'])?></td><td><?=e($cur)?> <?=number_format((float)$p['base_salary'],2)?></td><td><?=e($cur)?> <?=number_format((float)$p['adjustment_amount'],2)?></td><td><?=e($cur)?> <?=number_format((float)$p['paid_amount'],2)?></td><td><?=e($p['payment_date'])?></td></tr><?php endforeach?>
<?php if(!$payments):?><tr><td colspan="5"><div class="compact-empty"><h3>No Salary Records</h3><p>Salary payments will appear here once recorded.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
