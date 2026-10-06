<?php
$cur = $institute['currency_symbol'] ?? 'Rs';
$canEdit = can('*');
$returnTo = '/admin/accounts/statement?' . http_build_query(array_filter(['from_date' => $from, 'to_date' => $to, 'source' => $source, 'head' => $head ?: null, 'search' => $search]));
$rangeLabel = $from === $to ? date('d M Y', strtotime($from)) : date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
$headBreakdown = [];
foreach ($transactions as $t) {
    if ($t['source'] === 'manual') {
        $key = $t['head_name'] ?: 'Unassigned';
        $headBreakdown[$key] ??= ['income' => 0.0, 'expense' => 0.0];
        $headBreakdown[$key][$t['type']] += (float) $t['amount'];
    }
}
$sourceLabels = ['manual' => 'Manual Entries', 'fee' => 'Fee Collections', 'salary' => 'Salary Payments'];
$sourceIcons = ['manual' => 'notebook-pen', 'fee' => 'graduation-cap', 'salary' => 'users-round'];
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><a href="<?= url('/admin/accounts') ?>">Accounts</a><i data-lucide="chevron-right"></i><span>Account Statement</span></nav><h1>Account Statement</h1><p>Every rupee in and out — manual entries, fee collections and salary payments — for <?= e($rangeLabel) ?>.</p></div></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="from_date">From</label><input class="module-input" id="from_date" name="from_date" type="date" value="<?= e($from) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
<div class="module-field"><label for="to_date">To</label><input class="module-input" id="to_date" name="to_date" type="date" value="<?= e($to) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
<div class="module-field"><label for="source">Source</label><select class="module-select" id="source" name="source"><option value="">Everything</option><?php foreach ($sourceLabels as $key => $label): ?><option value="<?= $key ?>" <?= $source === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
<div class="module-field"><label for="head">Account Head</label><select class="module-select" id="head" name="head"><option value="0">All heads</option><?php foreach ($heads as $h): ?><option value="<?= (int) $h['id'] ?>" <?= $head === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach ?></select></div>
<div class="module-field"><label for="search">Search</label><input class="module-input" id="search" name="search" value="<?= e($search) ?>" placeholder="Description, receipt or reference"></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button><a class="btn btn--secondary" href="<?= e(url('/admin/accounts/statement')) ?>">Reset</a></div>
</form>
<p class="account-head-hint">Account Head narrows to manual entries only — fee collections and salary payments have no account head.</p>

<div class="metric-grid account-statement-metrics">
<div class="metric-card metric-card--income"><span>Total Income</span><strong><?= e($cur) ?> <?= number_format($income, 2) ?></strong></div>
<div class="metric-card metric-card--expense"><span>Total Expense</span><strong><?= e($cur) ?> <?= number_format($expense, 2) ?></strong></div>
<div class="metric-card<?= $balance >= 0 ? ' metric-card--income' : ' metric-card--expense' ?>"><span>Net Balance</span><strong><?= e($cur) ?> <?= number_format($balance, 2) ?></strong></div>
<div class="metric-card metric-card--warn"><span>Fee Still Uncollected</span><strong><?= e($cur) ?> <?= number_format($outstanding, 2) ?></strong><small>As of today, all months</small></div>
</div>

<div class="account-statement-grid">
<section class="module-card"><header class="module-card__header"><div><h2>By Source</h2><p>Where the money in this range came from and went.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Source</th><th>Income</th><th>Expense</th><th>Net</th></tr></thead><tbody>
<?php foreach ($sourceLabels as $key => $label): $row = $bySource[$key]; if ($row['income'] == 0 && $row['expense'] == 0) continue; ?>
<tr><td><i data-lucide="<?= $sourceIcons[$key] ?>" class="account-source-icon"></i><?= e($label) ?></td><td><?= $row['income'] > 0 ? e($cur) . ' ' . number_format($row['income'], 2) : '—' ?></td><td><?= $row['expense'] > 0 ? e($cur) . ' ' . number_format($row['expense'], 2) : '—' ?></td><td><?= e($cur) ?> <?= number_format($row['income'] - $row['expense'], 2) ?></td></tr>
<?php endforeach ?>
<?php if (!$transactions): ?><tr><td colspan="4"><div class="compact-empty"><h3>No activity</h3><p>No transactions match this filter.</p></div></td></tr><?php endif ?>
</tbody></table></div></div></section>

<?php $feeChargesLabel = substr($from, 0, 7) === substr($to, 0, 7) ? date('F Y', strtotime($from)) : date('M Y', strtotime($from)) . ' – ' . date('M Y', strtotime($to)); require BASE_PATH . '/app/Views/accounts/partials/fee-charges.php'; ?>
<?php if ($headBreakdown): ?>
<section class="module-card"><header class="module-card__header"><div><h2>By Account Head</h2><p>Manual entries only.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Head</th><th>Income</th><th>Expense</th></tr></thead><tbody>
<?php foreach ($headBreakdown as $name => $row): ?><tr><td><?= e($name) ?></td><td><?= $row['income'] > 0 ? e($cur) . ' ' . number_format($row['income'], 2) : '—' ?></td><td><?= $row['expense'] > 0 ? e($cur) . ' ' . number_format($row['expense'], 2) : '—' ?></td></tr><?php endforeach ?>
</tbody></table></div></div></section>
<?php endif ?>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Ledger</h2><p><?= count($transactions) ?> transaction<?= count($transactions) === 1 ? '' : 's' ?> · <?= e($rangeLabel) ?></p></div><div class="export-actions"><button class="export-button" type="button" onclick="window.print()">Print</button></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table account-statement-table"><thead><tr><th>Date</th><th>Description</th><th>Source</th><th>Expense</th><th>Income</th><th>Running Balance</th><th>Reference</th><?php if ($canEdit): ?><th>Actions</th><?php endif ?></tr></thead><tbody>
<?php if (!$transactions): ?><tr><td colspan="<?= $canEdit ? 8 : 7 ?>"><div class="compact-empty"><h3>No records found</h3><p>Widen the date range or clear a filter.</p></div></td></tr>
<?php else: foreach ($transactions as $t): ?>
<tr>
<td><?= e($t['date']) ?></td>
<td><?= e($t['description']) ?><?= $t['head_name'] ? '<small class="account-row-head">' . e($t['head_name']) . '</small>' : '' ?><?= !empty($t['items']) ? '<small class="account-row-head account-row-items">' . e($t['items']) . '</small>' : '' ?></td>
<td><span class="account-source-badge account-source-badge--<?= e($t['source']) ?>"><?= e($sourceLabels[$t['source']] ?? $t['source']) ?></span></td>
<td class="is-expense"><?= $t['type'] === 'expense' ? number_format((float) $t['amount'], 2) : '—' ?></td>
<td class="is-income"><?= $t['type'] === 'income' ? number_format((float) $t['amount'], 2) : '—' ?></td>
<td><?= e($cur) ?> <?= number_format((float) $t['balance'], 2) ?></td>
<td><?= e($t['reference'] ?: '—') ?></td>
<?php if ($canEdit): ?><td><div class="row-actions">
<?php if ($t['source'] === 'manual'): ?><a class="btn btn--secondary btn--sm" href="<?= url('/admin/accounts/transactions/' . $t['ref_id'] . '/edit?return=' . rawurlencode($returnTo)) ?>">Edit</a>
<?php $act = '/admin/accounts/transactions/' . $t['ref_id'] . '/delete'; elseif ($t['source'] === 'fee'): ?><a class="btn btn--secondary btn--sm" href="<?= url('/admin/fees/payments/' . $t['ref_id'] . '/edit?return=' . rawurlencode($returnTo)) ?>">Edit</a>
<?php $act = '/admin/fees/payments/' . $t['ref_id'] . '/reverse'; else: $act = $t['source'] === 'fee' ? '/admin/fees/payments/' . $t['ref_id'] . '/reverse' : '/admin/salary/payments/' . $t['ref_id'] . '/reverse'; endif ?>
<form method="post" action="<?= url($act) ?>" data-reverse-form><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($returnTo) ?>"><input type="hidden" name="reason"><button class="btn btn--danger btn--sm" type="submit">Reverse</button></form>
</div></td><?php endif ?>
</tr>
<?php endforeach; endif ?>
</tbody></table></div></div></section>
</div></div></main>
<script>document.querySelectorAll('[data-reverse-form]').forEach(f=>f.addEventListener('submit',e=>{const r=prompt('Reason for reversing this entry? It will be removed from the ledger and recorded in the audit log.');if(!r||!r.trim()){e.preventDefault();return}f.reason.value=r.trim()}));</script>
