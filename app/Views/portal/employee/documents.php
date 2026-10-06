<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Documents</span></nav><h1>My Documents</h1><p>Official branded documents you can view or download.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Job Letter</h2><p>Your appointment details and the institute's employee rules.</p></div><a class="btn btn--primary" href="<?=e(url('/portal/employee/documents/job-letter'))?>" target="_blank" rel="noopener">Open PDF</a></header></section>

<section class="module-card"><header class="module-card__header"><div><h2>Salary Paid Slips</h2><p>A slip for each salary payment recorded against your record.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-records"><thead><tr><th>Month</th><th>Paid</th><th>Payment Date</th><th>Slip</th></tr></thead><tbody>
<?php foreach($payments as $p):?><tr><td data-label="Month"><?=e($p['salary_month'])?></td><td data-label="Paid"><?=e($cur)?> <?=number_format((float)$p['paid_amount'],2)?></td><td data-label="Payment Date"><?=e($p['payment_date'])?></td><td data-label="Slip"><a href="<?=e(url('/portal/employee/documents/salary-slip/'.(int)$p['id']))?>" target="_blank" rel="noopener">Open PDF</a></td></tr><?php endforeach?>
<?php if(!$payments):?><tr><td colspan="4"><div class="compact-empty"><h3>No Salary Records</h3><p>Slips appear here once a salary payment is recorded.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
