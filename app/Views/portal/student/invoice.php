<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/portal/student/fees'))?>">My Fees</a><i data-lucide="chevron-right"></i><span><?=e($invoice['invoice_no'])?></span></nav><h1>Invoice <?=e($invoice['invoice_no'])?></h1><p><?=e($student['student_name'])?> · <?=e($invoice['fee_month'])?></p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<div class="metric-grid">
<div class="metric-card"><span>Total</span><strong><?=e($cur)?> <?=number_format((float)$invoice['total_amount'],2)?></strong></div>
<div class="metric-card"><span>Paid</span><strong><?=e($cur)?> <?=number_format((float)$invoice['paid_amount'],2)?></strong></div>
<div class="metric-card"><span>Remaining</span><strong><?=e($cur)?> <?=number_format((float)$invoice['remaining_amount'],2)?></strong></div>
<div class="metric-card"><span>Status</span><strong><?=e(ucfirst(str_replace('_',' ',(string)$invoice['status'])))?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Particulars</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Particular</th><th>Amount</th></tr></thead><tbody>
<?php foreach($items as $it):?><tr><td><?=e($it['particular'])?></td><td><?=e($cur)?> <?=number_format((float)$it['amount'],2)?></td></tr><?php endforeach?>
<?php if(!$items):?><tr><td colspan="2"><div class="compact-empty"><h3>No Particulars</h3><p>This invoice has no line items.</p></div></td></tr><?php endif?>
<tr><th>Subtotal</th><th><?=e($cur)?> <?=number_format((float)$invoice['subtotal'],2)?></th></tr>
<tr><th>Discount</th><th><?=e($cur)?> <?=number_format((float)$invoice['discount'],2)?></th></tr>
<tr><th>Previous Balance</th><th><?=e($cur)?> <?=number_format((float)$invoice['previous_balance'],2)?></th></tr>
<tr><th>Total</th><th><?=e($cur)?> <?=number_format((float)$invoice['total_amount'],2)?></th></tr>
</tbody></table></div></div></section>
</div></div></main>
