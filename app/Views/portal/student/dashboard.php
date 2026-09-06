<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>Dashboard</span></nav><h1>Welcome, <?=e($student['student_name'])?></h1><p><?=e($student['class_name']??'Unassigned class')?> · Registration <?=e($student['registration_no'])?></p></div></header>

<div class="metric-grid">
<div class="metric-card"><span>Attendance</span><strong><?=e((string)$attendance['percentage'])?>%</strong></div>
<div class="metric-card"><span>Present Days</span><strong><?=(int)$attendance['present']?> / <?=(int)$attendance['total']?></strong></div>
<div class="metric-card"><span>Fees Outstanding</span><strong><?=e($cur)?> <?=number_format((float)$fees['outstanding'],2)?></strong></div>
<div class="metric-card"><span>Latest Result</span><strong><?=$latestExam?e((string)$latestExam['percentage']).'%':'—'?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Recent Fee Invoices</h2><p>Your most recent billing activity.</p></div><a class="btn btn--ghost" href="<?=e(url('/portal/student/fees'))?>">View all</a></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Month</th><th>Invoice</th><th>Total</th><th>Paid</th><th>Remaining</th><th>Status</th></tr></thead><tbody>
<?php foreach($invoices as $i):?><tr><td><?=e($i['fee_month'])?></td><td><a href="<?=e(url('/portal/student/fees/'.(int)$i['id']))?>"><?=e($i['invoice_no'])?></a></td><td><?=e($cur)?> <?=number_format((float)$i['total_amount'],2)?></td><td><?=e($cur)?> <?=number_format((float)$i['paid_amount'],2)?></td><td><?=e($cur)?> <?=number_format((float)$i['remaining_amount'],2)?></td><td><span class="status-badge"><?=e(ucfirst(str_replace('_',' ',(string)$i['status'])))?></span></td></tr><?php endforeach?>
<?php if(!$invoices):?><tr><td colspan="6"><div class="compact-empty"><h3>No Invoices Yet</h3><p>Fee invoices will appear here once generated.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Latest Homework</h2><p>Assignments for <?=e($student['class_name']??'your class')?>.</p></div><a class="btn btn--ghost" href="<?=e(url('/portal/student/homework'))?>">View all</a></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Subject</th><th>Teacher</th><th>Homework</th></tr></thead><tbody>
<?php foreach($homework as $h):?><tr><td><?=e($h['homework_date'])?></td><td><?=e($h['subject']??'—')?></td><td><?=e($h['teacher']??'—')?></td><td><?=e($h['homework_text'])?></td></tr><?php endforeach?>
<?php if(!$homework):?><tr><td colspan="4"><div class="compact-empty"><h3>No Homework</h3><p>Nothing has been assigned to your class yet.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
