<?php $cur=$institute['currency_symbol']??'Rs'; $total=array_sum(array_map(static fn(array $r):float=>(float)$r['amount'],$rows)); ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Fee Collection Report</h1><p>Payments received in the selected range.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="from">From</label><input class="module-input" id="from" name="from" type="date" value="<?=e($from)?>"></div>
<div class="module-field"><label for="to">To</label><input class="module-input" id="to" name="to" type="date" value="<?=e($to)?>"></div>
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<div class="metric-grid">
<div class="metric-card"><span>Total Collected</span><strong><?=e($cur)?> <?=number_format($total,2)?></strong></div>
<div class="metric-card"><span>Payments</span><strong><?=count($rows)?></strong></div>
<div class="metric-card"><span>From</span><strong><?=e($from)?></strong></div>
<div class="metric-card"><span>To</span><strong><?=e($to)?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>By Payment Method</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Method</th><th>Payments</th><th>Total</th></tr></thead><tbody>
<?php foreach($methods as $m):?><tr><td><?=e($m['payment_method'])?></td><td><?=(int)$m['payments']?></td><td><?=e($cur)?> <?=number_format((float)$m['total'],2)?></td></tr><?php endforeach?>
<?php if(!$methods):?><tr><td colspan="3"><div class="compact-empty"><h3>No Payments</h3><p>No payments were received in this range.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Payments</h2><p><?=count($rows)?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Receipt</th><th>Date</th><th>Student</th><th>Class</th><th>Fee Month</th><th>Method</th><th>Received By</th><th>Amount</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr>
<td><?=e($r['receipt_no'])?></td><td><?=e($r['payment_date'])?></td>
<td><?=e($r['student_name'])?><br><small><?=e($r['registration_no'])?></small></td>
<td><?=e($r['class_name']??'—')?></td><td><?=e($r['fee_month'])?></td>
<td><?=e($r['payment_method'])?></td><td><?=e($r['received_by']??'—')?></td>
<td><?=e($cur)?> <?=number_format((float)$r['amount'],2)?></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="8"><div class="compact-empty"><h3>No Payments</h3><p>No payments were received in this range.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
