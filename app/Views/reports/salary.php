<?php
$cur=$institute['currency_symbol']??'Rs';
$paid=array_sum(array_map(static fn(array $r):float=>(float)$r['total_paid'],$rows));
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Salary Report</h1><p>Salary paid per employee across a range of months.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="from_month">From Month</label><input class="module-input" id="from_month" name="from_month" type="month" value="<?=e($fromMonth)?>"></div>
<div class="module-field"><label for="to_month">To Month</label><input class="module-input" id="to_month" name="to_month" type="month" value="<?=e($toMonth)?>"></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<div class="metric-grid">
<div class="metric-card"><span>Total Paid</span><strong><?=e($cur)?> <?=number_format($paid,2)?></strong></div>
<div class="metric-card"><span>Employees</span><strong><?=count($rows)?></strong></div>
<div class="metric-card"><span>From</span><strong><?=e($fromMonth)?></strong></div>
<div class="metric-card"><span>To</span><strong><?=e($toMonth)?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Salary Report</h2><p><?=e($fromMonth)?> to <?=e($toMonth)?>.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Employee No</th><th>Name</th><th>Designation</th><th>Monthly Salary</th><th>Payments</th><th>Adjustments</th><th>Total Paid</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr>
<td><?=e($r['employee_no'])?></td><td><?=e($r['name'])?></td><td><?=e($r['role'])?></td>
<td><?=e($cur)?> <?=number_format((float)$r['monthly_salary'],2)?></td>
<td><?=(int)$r['payments']?></td>
<td><?=e($cur)?> <?=number_format((float)$r['total_adjustment'],2)?></td>
<td><?=e($cur)?> <?=number_format((float)$r['total_paid'],2)?></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="7"><div class="compact-empty"><h3>No Employees</h3><p>No active employees to report.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
