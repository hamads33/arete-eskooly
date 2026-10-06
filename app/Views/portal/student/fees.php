<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Fees</span></nav><h1>My Fees</h1><p>Your invoices and recorded payments.</p></div></header>

<div class="metric-grid">
<div class="metric-card"><span>Total Billed</span><strong><?=e($cur)?> <?=number_format((float)$totals['billed'],2)?></strong></div>
<div class="metric-card"><span>Total Paid</span><strong><?=e($cur)?> <?=number_format((float)$totals['paid'],2)?></strong></div>
<div class="metric-card"><span>Outstanding</span><strong><?=e($cur)?> <?=number_format((float)$totals['outstanding'],2)?></strong></div>
<div class="metric-card"><span>Invoices</span><strong><?=count($invoices)?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Fee Invoices</h2><p>Select an invoice to see its particulars.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-records"><thead><tr><th>Month</th><th>Invoice</th><th>Total</th><th>Paid</th><th>Remaining</th><th>Status</th></tr></thead><tbody>
<?php foreach($invoices as $i):?><tr><td data-label="Month"><?=e($i['fee_month'])?></td><td data-label="Invoice" class="record-title"><a href="<?=e(url('/portal/student/fees/'.(int)$i['id']))?>"><?=e($i['invoice_no'])?></a></td><td data-label="Total"><?=e($cur)?> <?=number_format((float)$i['total_amount'],2)?></td><td data-label="Paid"><?=e($cur)?> <?=number_format((float)$i['paid_amount'],2)?></td><td data-label="Remaining"><?=e($cur)?> <?=number_format((float)$i['remaining_amount'],2)?></td><td data-label="Status"><span class="status-badge"><?=e(ucfirst(str_replace('_',' ',(string)$i['status'])))?></span></td></tr><?php endforeach?>
<?php if(!$invoices):?><tr><td colspan="6"><div class="compact-empty"><h3>No Invoices Yet</h3><p>Fee invoices will appear here once generated.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Payment History</h2><p>Payments recorded against your account.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-records"><thead><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead><tbody>
<?php foreach($payments as $p):?><tr><td data-label="Receipt" class="record-title"><?=e($p['receipt_no'])?></td><td data-label="Date"><?=e($p['payment_date'])?></td><td data-label="Amount"><?=e($cur)?> <?=number_format((float)$p['amount'],2)?></td><td data-label="Method"><?=e($p['payment_method'])?></td><td data-label="Reference"><?=e($p['reference']?:'—')?></td></tr><?php endforeach?>
<?php if(!$payments):?><tr><td colspan="5"><div class="compact-empty"><h3>No Payments Recorded</h3><p>Payments will appear here once received.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
