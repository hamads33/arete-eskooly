<?php
use App\Support\PersonStatus;
$cur = trim((string) ($institute['currency_symbol'] ?? $institute['currency'] ?? 'PKR'));
$m = static fn($v): string => number_format((float) $v, 0);
$f = $filters;
$total = array_sum(array_column($defaulters, 'due'));
$overdue = array_sum(array_column($defaulters, 'overdue'));
$partial = count(array_filter($defaulters, static fn($x) => $x['partial']));
$oldest = $defaulters ? min(array_column($defaulters, 'oldest')) : null;
$monthLabel = static fn(string $ym): string => date('M Y', strtotime($ym . '-01'));
$qs = static fn(array $over = []) => http_build_query(array_filter(array_replace(['search' => $f['q'], 'month' => $f['month'], 'class' => $f['class'] ?: null, 'state' => $f['state'], 'minimum' => $f['minimum'] ?: null, 'sort' => $f['sort'] !== 'due' ? $f['sort'] : null, 'unbilled' => $f['include_unbilled'] ? 1 : null], $over), static fn($v) => $v !== null && $v !== '' && $v !== 0));
$className = '';
foreach ($classes as $c) if ((int) $c['id'] === (int) $f['class']) $className = $c['name'];
$filterText = trim(implode(' · ', array_filter([$f['month'] ? date('F Y', strtotime($f['month'] . '-01')) : 'All months', $className, ['overdue' => 'Overdue only', 'partial' => 'Partly paid', 'unpaid' => 'Nothing paid', 'unbilled' => 'Not billed yet'][$f['state']] ?? '', $f['q'] ? '“' . $f['q'] . '”' : ''])));
?>
<main class="main"><div class="main__inner"><div class="module-page ek-fees fd-page">
<header class="module-page__header"><div><nav class="breadcrumb no-print"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Fees</span><i data-lucide="chevron-right"></i><span>Fee Defaulters</span></nav><h1>Fee Defaulters</h1><p><span class="no-print">Students with unpaid fees — grouped by student, with the months they owe.</span></p></div>
<div class="filter-actions no-print"><button class="btn btn--primary" type="button" onclick="window.print()"><i data-lucide="printer"></i>Print list</button><a class="btn btn--secondary" href="<?= url('/admin/reports/fee-defaulters/pdf' . ($qs() !== '' ? '?' . $qs() : '')) ?>" target="_blank" rel="noopener"><i data-lucide="file-text"></i>PDF</a><a class="btn btn--secondary" href="<?= url('/admin/fees/defaulters?' . $qs(['export' => 'csv'])) ?>"><i data-lucide="sheet"></i>Export CSV</a><a class="btn btn--ghost" href="<?= url('/admin/fees/collect') ?>"><i data-lucide="wallet"></i>Collect Fees</a></div></header>

<p class="print-only fd-print-note">Showing: <?= e($filterText) ?> · printed <?= e(date('d M Y, h:i A')) ?></p>
<form class="fd-filters no-print" method="get" action="<?= url('/admin/fees/defaulters') ?>">
  <div class="module-field fd-filters__search"><label for="fd_q">Search</label><input class="module-input" type="search" id="fd_q" name="search" value="<?= e($f['q']) ?>" placeholder="Student, registration, guardian, phone or invoice"></div>
  <div class="module-field"><label for="fd_month">Fee month</label><input class="module-input" type="month" id="fd_month" name="month" value="<?= e($f['month']) ?>" title="Leave empty for all months"></div>
  <div class="module-field"><label for="fd_class">Class</label><select class="module-select" id="fd_class" name="class"><option value="0">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $f['class'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
  <div class="module-field"><label for="fd_state">Show</label><select class="module-select" id="fd_state" name="state"><option value="">All defaulters</option><option value="overdue" <?= $f['state'] === 'overdue' ? 'selected' : '' ?>>Overdue (past due date)</option><option value="partial" <?= $f['state'] === 'partial' ? 'selected' : '' ?>>Partly paid</option><option value="unpaid" <?= $f['state'] === 'unpaid' ? 'selected' : '' ?>>Nothing paid</option><option value="unbilled" <?= $f['state'] === 'unbilled' ? 'selected' : '' ?>>Not billed yet</option></select></div>
  <div class="module-field"><label for="fd_min">Minimum due</label><input class="module-input" type="number" min="0" step="1" id="fd_min" name="minimum" value="<?= e($f['minimum'] ?: '') ?>" placeholder="0"></div>
  <div class="module-field"><label for="fd_sort">Sort by</label><select class="module-select" id="fd_sort" name="sort"><option value="due">Highest due first</option><option value="oldest" <?= $f['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest unpaid first</option><option value="class" <?= $f['sort'] === 'class' ? 'selected' : '' ?>>Class</option><option value="name" <?= $f['sort'] === 'name' ? 'selected' : '' ?>>Name</option></select></div>
  <label class="fd-toggle" title="Pick a fee month first"><input type="checkbox" name="unbilled" value="1" <?= $f['include_unbilled'] ? 'checked' : '' ?> <?= $f['month'] ? '' : 'disabled' ?>><span>Include students not billed for this month yet</span></label>
  <div class="fd-filters__actions"><button class="btn btn--primary"><i data-lucide="filter"></i>Apply</button><?php if ($qs() !== ''): ?><a class="btn btn--ghost" href="<?= url('/admin/fees/defaulters') ?>">Clear</a><?php endif ?></div>
</form>

<section class="ss-tiles fd-tiles" aria-label="Summary">
  <div class="ss-tile"><span>Defaulters</span><strong><?= count($defaulters) ?></strong><small>students</small></div>
  <div class="ss-tile ss-tile--warn"><span>Total outstanding</span><strong><?= e($cur) ?> <?= $m($total) ?></strong><small>in this view</small></div>
  <div class="ss-tile fd-tile--danger"><span>Overdue</span><strong><?= e($cur) ?> <?= $m($overdue) ?></strong><small>past the due date</small></div>
  <div class="ss-tile"><span>Partly paid</span><strong><?= $partial ?></strong><small>students</small></div>
  <div class="ss-tile"><span>Oldest unpaid</span><strong><?= $oldest ? e($monthLabel($oldest)) : '—' ?></strong><small>fee month</small></div>
</section>

<?php if (!$defaulters): ?>
<section class="module-card"><div class="compact-empty"><h3>No defaulters</h3><p><?= $qs() !== '' ? 'Nobody matches these filters.' : 'Every student is paid up. 🎉' ?></p></div></section>
<?php else: ?>
<section class="module-card fd-card"><div class="fd-table-wrap"><table class="fd-table no-table-tools mobile-register">
<thead><tr><th>#</th><th>Student</th><th>Guardian</th><th>Months due</th><th class="fd-num">Total due</th><th>Last paid</th><th class="no-print">Actions</th></tr></thead>
<tbody>
<?php foreach ($defaulters as $n => $x):
  $phone = trim((string) $x['phone']);
  $monthsText = implode(', ', array_map(static fn($mo) => $monthLabel($mo['month']), $x['months']));
  $msg = 'Dear ' . ($x['guardian'] ?: 'Parent') . ', ' . $x['student_name'] . ' (' . $x['class_name'] . ') has an outstanding fee of ' . $cur . ' ' . number_format($x['due'], 0) . ' for ' . $monthsText . '. Kindly pay at the earliest. Thank you — ' . ($institute['name'] ?? '');
  $firstInvoice = 0; foreach ($x['months'] as $mo) if ($mo['invoice_id']) { $firstInvoice = $mo['invoice_id']; break; }
?>
<tr>
  <td class="fd-sr" data-label="#"><?= $n + 1 ?></td>
  <td class="fd-student" data-label="Student"><strong><?= e($x['student_name']) ?></strong><small><?= e($x['registration_no']) ?> · <?= e($x['class_name']) ?></small><?php if ($x['student_status'] !== 'active'): ?><small class="person-status-tag person-status-tag--<?= e($x['student_status']) ?>"><?= e(PersonStatus::label($x['student_status'])) ?></small><?php endif ?></td>
  <td class="fd-guardian" data-label="Guardian"><?= e($x['guardian'] ?: '—') ?><?php if ($phone): ?><small><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></small><?php else: ?><small class="ss-muted">No phone</small><?php endif ?></td>
  <td class="fd-months" data-label="Months due"><?php foreach ($x['months'] as $mo): ?><span class="fd-month<?= $mo['overdue'] ? ' is-overdue' : '' ?><?= !empty($mo['not_billed']) ? ' is-unbilled' : '' ?>" title="<?= !empty($mo['not_billed']) ? 'Not billed yet — expected fee' : e(($mo['invoice_no'] ?? '') . ($mo['due_date'] ? ' · due ' . date('d M Y', strtotime($mo['due_date'])) : '') . ($mo['previous'] > 0 ? ' · includes previous balance ' . $cur . ' ' . $m($mo['previous']) : '')) ?>"><?= e($monthLabel($mo['month'])) ?> <b><?= e($cur) ?> <?= $m($mo['remaining']) ?></b><?= $mo['overdue'] ? ' <em>overdue</em>' : '' ?><?= !empty($mo['not_billed']) ? ' <em>not billed</em>' : '' ?></span><?php endforeach ?></td>
  <td class="fd-num fd-due" data-label="Total due"><strong><?= e($cur) ?> <?= $m($x['due']) ?></strong><?php if ($x['paid'] > 0): ?><small>paid <?= e($cur) ?> <?= $m($x['paid']) ?> so far</small><?php endif ?></td>
  <td class="fd-last" data-label="Last paid"><?= $x['last_payment'] ? e(date('d M Y', strtotime($x['last_payment']))) : '<span class="ss-muted">Never</span>' ?></td>
  <td class="fd-actions no-print" data-label="Actions"><div class="fd-actions__row">
    <a class="btn btn--primary btn--sm" href="<?= url('/admin/fees/collect?student_id=' . (int) $x['student_id'] . '&month=' . rawurlencode($x['oldest'])) ?>"><i data-lucide="wallet"></i>Collect</a>
    <?php if ($phone): ?><form method="post" action="<?= url('/admin/whatsapp/open') ?>" target="_blank" class="inline-form"><?= csrf_field() ?><input type="hidden" name="phone" value="<?= e($phone) ?>"><input type="hidden" name="message" value="<?= e($msg) ?>"><button class="btn btn--secondary btn--sm" title="Send a WhatsApp reminder"><i data-lucide="message-circle"></i>Remind</button></form><?php endif ?>
    <?php if ($firstInvoice): ?><a class="btn btn--ghost btn--sm" href="<?= url('/admin/reports/fee-invoice/' . $firstInvoice . '/pdf') ?>" target="_blank" rel="noopener" title="Fee voucher"><i data-lucide="file-text"></i>Voucher</a><?php endif ?>
  </div></td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot><tr><td></td><td colspan="3"><strong><?= count($defaulters) ?> defaulter<?= count($defaulters) === 1 ? '' : 's' ?></strong></td><td class="fd-num"><strong><?= e($cur) ?> <?= $m($total) ?></strong></td><td colspan="2" class="no-print"></td><td class="print-only"></td></tr></tfoot>
</table></div></section>
<?php endif ?>
</div></div></main>
