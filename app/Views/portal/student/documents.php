<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Documents</span></nav><h1>My Documents</h1><p>Official branded documents you can view or download.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Admission Letter</h2><p>Your admission record and the institute's student rules.</p></div><a class="btn btn--primary" href="<?=e(url('/portal/student/documents/admission-letter'))?>" target="_blank" rel="noopener">Open PDF</a></header></section>

<section class="module-card"><header class="module-card__header"><div><h2>Fees Paid Slips</h2><p>A receipt for each payment recorded against your account.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Method</th><th>Slip</th></tr></thead><tbody>
<?php foreach($payments as $p):?><tr><td><?=e($p['receipt_no'])?></td><td><?=e($p['payment_date'])?></td><td><?=e($cur)?> <?=number_format((float)$p['amount'],2)?></td><td><?=e($p['payment_method'])?></td><td><a href="<?=e(url('/portal/student/documents/fee-slip/'.(int)$p['id']))?>" target="_blank" rel="noopener">Open PDF</a></td></tr><?php endforeach?>
<?php if(!$payments):?><tr><td colspan="5"><div class="compact-empty"><h3>No Payments Recorded</h3><p>Slips appear here once a payment is received.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Result Cards</h2><p>One result card for each exam you have sat.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Exam</th><th>Starting</th><th>Result Card</th></tr></thead><tbody>
<?php foreach($exams as $x):?><tr><td><?=e($x['name'])?></td><td><?=e($x['date'])?></td><td><a href="<?=e(url('/portal/student/documents/report-card/'.(int)$x['id']))?>" target="_blank" rel="noopener">Open PDF</a></td></tr><?php endforeach?>
<?php if(!$exams):?><tr><td colspan="3"><div class="compact-empty"><h3>No Results Yet</h3><p>Result cards appear here once marks are published.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
