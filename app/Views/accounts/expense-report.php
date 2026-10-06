<?php
$cur = $institute['currency_symbol'] ?? 'Rs';
$byHead = [];
foreach ($rows as $row) {
    $headName = $row['head_name'] ?? 'Unassigned';
    $byHead[$headName] = ($byHead[$headName] ?? 0) + (float) $row['amount'];
}
arsort($byHead);
$rangeLabel = $from === $to ? date('d M Y', strtotime($from)) : date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/dashboard')) ?>">Dashboard</a><i data-lucide="chevron-right"></i><a href="<?= e(url('/admin/accounts')) ?>">Accounts</a><i data-lucide="chevron-right"></i><span>Expense Report</span></nav><h1>Expense Report</h1><p>Every expense recorded from <?= e($rangeLabel) ?>.</p></div></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="from">From</label><input class="module-input" id="from" name="from" type="date" value="<?= e($from) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
<div class="module-field"><label for="to">To</label><input class="module-input" id="to" name="to" type="date" value="<?= e($to) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
<div class="module-field"><label for="head">Account Head</label><select class="module-select" id="head" name="head"><option value="0">All heads</option><?php foreach ($heads as $h): ?><option value="<?= (int) $h['id'] ?>" <?= $head === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach ?></select></div>
<div class="module-field"><label for="q">Search</label><input class="module-input" id="q" name="q" value="<?= e($q) ?>" placeholder="Description or reference"></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button><a class="btn btn--secondary" href="<?= e(url('/admin/accounts/expense-report')) ?>">Reset</a></div>
</form>

<?php if ($months): ?>
<div class="quick-months" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px">
<?php foreach (array_slice($months, 0, 12) as $m):
    $mFrom = $m . '-01';
    $mTo = date('Y-m-t', strtotime($mFrom));
    $active = $from === $mFrom && $to === $mTo;
?>
<a class="btn <?= $active ? 'btn--primary' : 'btn--secondary' ?>" style="min-height:32px;padding:6px 12px;font-size:12px" href="<?= e(url('/admin/accounts/expense-report?from=' . $mFrom . '&to=' . $mTo)) ?>"><?= e(date('M Y', strtotime($mFrom))) ?></a>
<?php endforeach ?>
</div>
<?php endif ?>

<div class="metric-grid">
<div class="metric-card"><span>Total Expense</span><strong><?= e($cur) ?> <?= number_format($total, 2) ?></strong></div>
<div class="metric-card"><span>Entries</span><strong><?= count($rows) ?></strong></div>
<div class="metric-card"><span>Account Heads</span><strong><?= count($byHead) ?></strong></div>
</div>

<?php if ($byHead): ?>
<section class="module-card"><header class="module-card__header"><div><h2>By Account Head</h2><p><?= e($rangeLabel) ?>.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Account Head</th><th>Amount</th><th>Share</th></tr></thead><tbody>
<?php foreach ($byHead as $headName => $amount): ?><tr><td><?= e($headName) ?></td><td><?= e($cur) ?> <?= number_format($amount, 2) ?></td><td><?= $total > 0 ? number_format($amount / $total * 100, 1) : '0.0' ?>%</td></tr><?php endforeach ?>
</tbody></table></div></div></section>
<?php endif ?>

<section class="module-card"><header class="module-card__header"><div><h2>Expense Entries</h2><p><?= e($rangeLabel) ?>.</p></div><div class="export-actions"><button class="export-button" type="button" onclick="window.print()">Print</button></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Description</th><th>Account Head</th><th>Amount</th><th>Reference</th><th>Recorded By</th><?php if (can('*')): ?><th>Actions</th><?php endif ?></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="<?= can('*') ? 7 : 6 ?>"><div class="compact-empty"><h3>No expenses</h3><p>No expense entries match this filter.</p></div></td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
<td><?= e($r['transaction_date']) ?></td>
<td><?= e($r['description']) ?></td>
<td><?= e($r['head_name'] ?? 'Unassigned') ?></td>
<td><?= e($cur) ?> <?= number_format((float) $r['amount'], 2) ?></td>
<td><?= e($r['reference_type'] ?? '—') ?></td>
<td><?= e($r['created_by_name'] ?? '—') ?></td>
<?php if (can('*')): $ret = '/admin/accounts/expense-report?' . http_build_query(array_filter(['from' => $from, 'to' => $to, 'head' => $head ?: null, 'q' => $q])); ?><td><div class="row-actions"><a class="btn btn--secondary btn--sm" href="<?= url('/admin/accounts/transactions/' . $r['id'] . '/edit?return=' . rawurlencode($ret)) ?>">Edit</a><form method="post" action="<?= url('/admin/accounts/transactions/' . $r['id'] . '/delete') ?>" data-reverse-form><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="reason"><button class="btn btn--danger btn--sm" type="submit">Reverse</button></form></div></td><?php endif ?>
</tr>
<?php endforeach; endif ?>
</tbody></table></div></div></section>
</div></div></main>
<script>document.querySelectorAll('[data-reverse-form]').forEach(f=>f.addEventListener('submit',e=>{const r=prompt('Reason for reversing this entry? It will be removed from the ledger and recorded in the audit log.');if(!r||!r.trim()){e.preventDefault();return}f.reason.value=r.trim()}));</script>
