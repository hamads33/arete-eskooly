<?php
$cur=$institute['currency_symbol']??'Rs';
$income=array_sum(array_map(static fn(array $r):float=>(float)$r['income'],$rows));
$expense=array_sum(array_map(static fn(array $r):float=>(float)$r['expense'],$rows));
$manualIncome=$income;$income+=(float)($feeCollected??0);
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Accounts Report</h1><p>Fee collections plus income and expense grouped by account head.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="from">From</label><input class="module-input" id="from" name="from" type="date" value="<?=e($from)?>"></div>
<div class="module-field"><label for="to">To</label><input class="module-input" id="to" name="to" type="date" value="<?=e($to)?>"></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<div class="metric-grid">
<div class="metric-card"><span>Total Income <small>(fees + other)</small></span><strong><?=e($cur)?> <?=number_format($income,2)?></strong></div>
<div class="metric-card"><span>Total Expense</span><strong><?=e($cur)?> <?=number_format($expense,2)?></strong></div>
<div class="metric-card"><span>Net Balance</span><strong><?=e($cur)?> <?=number_format($income-$expense,2)?></strong></div>
<div class="metric-card"><span>Fee Collections</span><strong><?=e($cur)?> <?=number_format((float)($feeCollected??0),2)?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>By Account Head</h2><p><?=e($from)?> to <?=e($to)?>.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Account Head</th><th>Type</th><th>Entries</th><th>Income</th><th>Expense</th><th>Net</th></tr></thead><tbody>
<?php foreach($rows as $r): $net=(float)$r['income']-(float)$r['expense']; ?><tr>
<td><?=e($r['head']??'Unassigned')?></td>
<td><?=e(ucfirst((string)($r['type']??'—')))?></td>
<td><?=(int)$r['entries']?></td>
<td><?=e($cur)?> <?=number_format((float)$r['income'],2)?></td>
<td><?=e($cur)?> <?=number_format((float)$r['expense'],2)?></td>
<td><?=e($cur)?> <?=number_format($net,2)?></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="6"><div class="compact-empty"><h3>No Transactions</h3><p>No account activity in this range.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
<?php $feeChargesLabel = substr($from, 0, 7) === substr($to, 0, 7) ? date('F Y', strtotime($from)) : date('M Y', strtotime($from)) . ' – ' . date('M Y', strtotime($to)); require BASE_PATH . '/app/Views/accounts/partials/fee-charges.php'; ?>
</div></div></main>
