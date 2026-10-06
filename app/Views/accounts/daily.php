<?php
$cur = $institute['currency_symbol'] ?? 'Rs';
$sourceLabels = ['manual' => 'Manual Entries', 'fee' => 'Fee Collections', 'salary' => 'Salary Payments'];
$sourceIcons = ['manual' => 'notebook-pen', 'fee' => 'graduation-cap', 'salary' => 'users-round'];
$net = $income - $expense;
$trendMax = 0.01;
foreach ($trend as $d) {
    $trendMax = max($trendMax, (float) $d['income'], (float) $d['expense']);
}
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><a href="<?= url('/admin/accounts') ?>">Accounts</a><i data-lucide="chevron-right"></i><span>Daily Statement</span></nav><h1>Daily Statement</h1><p><?= $isToday ? "Today's" : 'That day\'s' ?> collection and spending, across manual entries, fee collections and salary payments.</p></div><div class="export-actions"><button class="export-button" type="button" onclick="window.print()">Print</button></div></header>

<nav class="daily-report-nav">
<a class="btn btn--secondary" href="<?= url('/admin/accounts/daily?date=' . $prevDate) ?>"><i data-lucide="chevron-left"></i>Previous day</a>
<form method="get" style="display:contents"><strong><?= e(date('l, d F Y', strtotime($date))) ?><?= $isToday ? ' <span class="status-badge status-badge--active">Today</span>' : '' ?></strong><input class="module-input" type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>" onchange="this.form.submit()" aria-label="Choose a date" style="max-width:160px"></form>
<?php if ($nextDate): ?><a class="btn btn--secondary" href="<?= url('/admin/accounts/daily?date=' . $nextDate) ?>">Next day<i data-lucide="chevron-right"></i></a><?php else: ?><a class="btn btn--secondary" href="<?= url('/admin/accounts/daily') ?>">Jump to today</a><?php endif ?>
</nav>

<div class="metric-grid account-statement-metrics">
<div class="metric-card metric-card--income"><span>Collected Today</span><strong><?= e($cur) ?> <?= number_format($income, 2) ?></strong></div>
<div class="metric-card metric-card--expense"><span>Spent Today</span><strong><?= e($cur) ?> <?= number_format($expense, 2) ?></strong></div>
<div class="metric-card<?= $net >= 0 ? ' metric-card--income' : ' metric-card--expense' ?>"><span>Net for the Day</span><strong><?= e($cur) ?> <?= number_format($net, 2) ?></strong></div>
<div class="metric-card metric-card--warn"><span>Fee Still Uncollected</span><strong><?= e($cur) ?> <?= number_format($outstanding, 2) ?></strong><small>As of today, all months</small></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>By Source</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Source</th><th>Collected</th><th>Spent</th></tr></thead><tbody>
<?php $any = false; foreach ($sourceLabels as $key => $label): $row = $bySource[$key]; if ($row['income'] == 0 && $row['expense'] == 0) continue; $any = true; ?>
<tr><td><i data-lucide="<?= $sourceIcons[$key] ?>" class="account-source-icon"></i><?= e($label) ?></td><td><?= $row['income'] > 0 ? e($cur) . ' ' . number_format($row['income'], 2) : '—' ?></td><td><?= $row['expense'] > 0 ? e($cur) . ' ' . number_format($row['expense'], 2) : '—' ?></td></tr>
<?php endforeach ?>
<?php if (!$any): ?><tr><td colspan="3"><div class="compact-empty"><h3>No activity that day</h3><p>Nothing was collected or spent on <?= e(date('d M Y', strtotime($date))) ?>.</p></div></td></tr><?php endif ?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2><?= e(date('d M Y', strtotime($date))) ?> — Transactions</h2><p><?= count($transactions) ?> transaction<?= count($transactions) === 1 ? '' : 's' ?></p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table account-statement-table"><thead><tr><th>Description</th><th>Source</th><th>Expense</th><th>Income</th><th>Reference</th><?php if (can('*')): ?><th>Actions</th><?php endif ?></tr></thead><tbody>
<?php if (!$transactions): ?><tr><td colspan="<?= can('*') ? 6 : 5 ?>"><div class="compact-empty"><h3>No transactions</h3><p>Nothing recorded for this day yet.</p></div></td></tr>
<?php else: foreach ($transactions as $t): ?>
<tr>
<td><?= e($t['description']) ?><?= $t['head_name'] ? '<small class="account-row-head">' . e($t['head_name']) . '</small>' : '' ?><?= !empty($t['items']) ? '<small class="account-row-head account-row-items">' . e($t['items']) . '</small>' : '' ?></td>
<td><span class="account-source-badge account-source-badge--<?= e($t['source']) ?>"><?= e($sourceLabels[$t['source']] ?? $t['source']) ?></span></td>
<td class="is-expense"><?= $t['type'] === 'expense' ? number_format((float) $t['amount'], 2) : '—' ?></td>
<td class="is-income"><?= $t['type'] === 'income' ? number_format((float) $t['amount'], 2) : '—' ?></td>
<td><?= e($t['reference'] ?: '—') ?></td>
<?php if (can('*')): $ret = '/admin/accounts/daily?date=' . $date; ?><td><div class="row-actions">
<?php if ($t['source'] === 'manual'): ?><a class="btn btn--secondary btn--sm" href="<?= url('/admin/accounts/transactions/' . $t['ref_id'] . '/edit?return=' . rawurlencode($ret)) ?>">Edit</a><?php $act = '/admin/accounts/transactions/' . $t['ref_id'] . '/delete'; elseif ($t['source'] === 'fee'): ?><a class="btn btn--secondary btn--sm" href="<?= url('/admin/fees/payments/' . $t['ref_id'] . '/edit?return=' . rawurlencode($ret)) ?>">Edit</a><?php $act = '/admin/fees/payments/' . $t['ref_id'] . '/reverse'; else: $act = '/admin/salary/payments/' . $t['ref_id'] . '/reverse'; endif ?>
<form method="post" action="<?= url($act) ?>" data-reverse-form><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="reason"><button class="btn btn--danger btn--sm" type="submit">Reverse</button></form>
</div></td><?php endif ?>
</tr>
<?php endforeach; endif ?>
</tbody></table></div></div></section>

<section class="module-card no-print"><header class="module-card__header"><div><h2>Last 14 Days</h2><p>Compare today with recent days at a glance.</p></div><a class="btn btn--secondary" href="<?= url('/admin/accounts/statement?from_date=' . date('Y-m-d', strtotime($date . ' -13 days')) . '&to_date=' . $date) ?>">Open as statement</a></header><div class="module-card__body"><div class="table-scroll"><table class="data-table daily-trend-table"><thead><tr><th>Date</th><th>Collected</th><th>Spent</th><th>Net</th></tr></thead><tbody>
<?php foreach ($trend as $d): $dnet = (float) $d['income'] - (float) $d['expense']; ?>
<tr class="<?= $d['date'] === $date ? 'is-today' : '' ?>"><td><a href="<?= url('/admin/accounts/daily?date=' . $d['date']) ?>"><?= e(date('D, d M', strtotime($d['date']))) ?></a></td><td><?= e($cur) ?> <?= number_format((float) $d['income'], 2) ?></td><td><?= e($cur) ?> <?= number_format((float) $d['expense'], 2) ?></td><td class="<?= $dnet >= 0 ? 'is-income' : 'is-expense' ?>"><?= e($cur) ?> <?= number_format($dnet, 2) ?></td></tr>
<?php endforeach ?>
<?php if (!$trend): ?><tr><td colspan="4"><div class="compact-empty"><h3>No recent activity</h3></div></td></tr><?php endif ?>
</tbody></table></div></div></section>
</div></div></main>
<script>document.querySelectorAll('[data-reverse-form]').forEach(f=>f.addEventListener('submit',e=>{const r=prompt('Reason for reversing this entry? It will be removed from the ledger and recorded in the audit log.');if(!r||!r.trim()){e.preventDefault();return}f.reason.value=r.trim()}));</script>
