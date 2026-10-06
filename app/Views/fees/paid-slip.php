<?php
$cur = trim((string) ($institute['currency_symbol'] ?? $institute['currency'] ?? 'PKR'));
$m = static fn($v): string => number_format((float) $v, 0);
$f = $filters;
$live = array_filter($slips, static fn($x) => !$x['reversed_at']);
$total = array_sum(array_map(static fn($x) => (float) $x['amount'], $live));
$students = count(array_unique(array_column($live, 'student_id')));
$byMethod = [];
foreach ($live as $x) $byMethod[$x['payment_method']] = ($byMethod[$x['payment_method']] ?? 0) + (float) $x['amount'];
arsort($byMethod);
$range = $f['from'] || $f['to'] ? trim(($f['from'] ? date('d M Y', strtotime($f['from'])) : '…') . ' – ' . ($f['to'] ? date('d M Y', strtotime($f['to'])) : 'today')) : 'All dates';
?>
<main class="main"><div class="main__inner"><div class="module-page ek-fees fd-page ps-page">
<header class="module-page__header"><div><nav class="breadcrumb no-print"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Fees</span><i data-lucide="chevron-right"></i><span>Fees Paid Slip</span></nav><h1>Fees Paid Slip</h1><p><span class="no-print">Find any fee payment by student, receipt number or month — then view, print or send the slip.</span></p></div>
<div class="filter-actions no-print"><button class="btn btn--secondary" type="button" onclick="window.print()"><i data-lucide="printer"></i>Print list</button><a class="btn btn--primary" href="<?= url('/admin/fees/collect') ?>"><i data-lucide="wallet"></i>Collect Fees</a></div></header>

<p class="print-only fd-print-note">Showing: <?= e($range) ?><?= $f['month'] ? ' · fee month ' . e(date('F Y', strtotime($f['month'] . '-01'))) : '' ?> · printed <?= e(date('d M Y, h:i A')) ?></p>
<form class="fd-filters ps-filters no-print" method="get" action="<?= url('/admin/fees/receipt') ?>" data-ps-form>
  <div class="module-field fd-filters__search"><label for="ps_q">Student or receipt</label><input class="module-input" type="search" id="ps_q" name="q" value="<?= e($f['q']) ?>" placeholder="Type student name, registration, receipt no. or reference" autocomplete="off" data-ps-q></div>
  <div class="module-field"><label for="ps_month">Fee month</label><input class="module-input" type="month" id="ps_month" name="month" value="<?= e($f['month']) ?>"></div>
  <div class="module-field"><label for="ps_from">Paid from</label><input class="module-input" type="date" id="ps_from" name="from" value="<?= e($defaultView ? '' : $f['from']) ?>" max="<?= date('Y-m-d') ?>"></div>
  <div class="module-field"><label for="ps_to">Paid to</label><input class="module-input" type="date" id="ps_to" name="to" value="<?= e($f['to']) ?>" max="<?= date('Y-m-d') ?>"></div>
  <div class="module-field"><label for="ps_method">Method</label><select class="module-select" id="ps_method" name="method"><option value="">All methods</option><?php foreach ($methods as $me): ?><option <?= $f['method'] === $me ? 'selected' : '' ?>><?= e($me) ?></option><?php endforeach ?></select></div>
  <div class="module-field"><label for="ps_rev">Reversed</label><select class="module-select" id="ps_rev" name="reversed"><option value="">Hide reversed</option><option value="all" <?= $f['reversed'] === 'all' ? 'selected' : '' ?>>Show all</option><option value="only" <?= $f['reversed'] === 'only' ? 'selected' : '' ?>>Reversed only</option></select></div>
  <div class="ps-quick" aria-label="Quick dates"><span>Quick:</span><a href="<?= url('/admin/fees/receipt?from=' . date('Y-m-d') . '&to=' . date('Y-m-d')) ?>">Today</a><a href="<?= url('/admin/fees/receipt?from=' . date('Y-m-d', strtotime('-1 day')) . '&to=' . date('Y-m-d', strtotime('-1 day'))) ?>">Yesterday</a><a href="<?= url('/admin/fees/receipt?from=' . date('Y-m-01') . '&to=' . date('Y-m-d')) ?>">This month</a><a href="<?= url('/admin/fees/receipt?month=' . date('Y-m')) ?>">Fee month <?= e(date('M')) ?></a></div>
  <div class="fd-filters__actions"><button class="btn btn--primary"><i data-lucide="search"></i>Find slips</button><?php if (!$defaultView): ?><a class="btn btn--ghost" href="<?= url('/admin/fees/receipt') ?>">Clear</a><?php endif ?></div>
</form>

<div data-ps-results>
<section class="ss-tiles" aria-label="Summary">
  <div class="ss-tile ss-tile--good"><span>Collected</span><strong><?= e($cur) ?> <?= $m($total) ?></strong><small><?= e($defaultView ? 'last 30 days' : $range) ?></small></div>
  <div class="ss-tile"><span>Receipts</span><strong><?= count($live) ?></strong><small><?= $students ?> student<?= $students === 1 ? '' : 's' ?></small></div>
  <?php foreach (array_slice($byMethod, 0, 3, true) as $me => $amt): ?><div class="ss-tile"><span><?= e($me) ?></span><strong><?= e($cur) ?> <?= $m($amt) ?></strong><small>by method</small></div><?php endforeach ?>
</section>

<?php if (!$slips): ?>
<section class="module-card"><div class="compact-empty"><h3>No payments found</h3><p><?= $defaultView ? 'No fees were collected in the last 30 days.' : 'Try another name, receipt number or date range.' ?></p></div></section>
<?php else: ?>
<section class="module-card fd-card"><header class="module-card__header no-print"><div><h2><?= $defaultView ? 'Recent payments' : count($slips) . ' payment' . (count($slips) === 1 ? '' : 's') ?></h2><p><?= $defaultView ? 'Last 30 days. Search above to find older slips.' : e($range) ?><?= count($slips) >= 200 ? ' · showing the latest 200 — narrow the dates to see more' : '' ?></p></div></header>
<div class="fd-table-wrap"><table class="fd-table ps-table no-table-tools">
<thead><tr><th>Receipt</th><th>Student</th><th>Fee month</th><th>Paid on</th><th class="fd-num">Amount</th><th>Method</th><th class="no-print">Slip</th></tr></thead><tbody>
<?php foreach ($slips as $x):
  $phone = trim((string) ($x['father_mobile'] ?: $x['mobile']));
  $msg = 'Dear Parent, we have received ' . $cur . ' ' . number_format((float) $x['amount'], 0) . ' for ' . $x['student_name'] . ' (' . $x['class_name'] . ') for ' . date('F Y', strtotime($x['fee_month'] . '-01')) . '. Receipt: ' . $x['receipt_no'] . '. Remaining balance: ' . $cur . ' ' . number_format((float) $x['remaining_amount'], 0) . '. Thank you.';
?>
<tr class="<?= $x['reversed_at'] ? 'ps-reversed' : '' ?>">
  <td class="ps-receipt" data-label="Receipt"><a href="<?= url('/admin/fees/receipt/' . (int) $x['id']) ?>"><strong><?= e($x['receipt_no']) ?></strong></a><?php if ($x['reversed_at']): ?><small class="ps-rev-tag">Reversed<?= $x['reversal_reason'] ? ' · ' . e($x['reversal_reason']) : '' ?></small><?php endif ?></td>
  <td class="fd-student" data-label="Student"><strong><?= e($x['student_name']) ?></strong><small><?= e($x['registration_no']) ?> · <?= e($x['class_name']) ?></small></td>
  <td data-label="Fee month"><?= e(date('M Y', strtotime($x['fee_month'] . '-01'))) ?><small><?= (float) $x['remaining_amount'] > 0 ? e($cur) . ' ' . $m($x['remaining_amount']) . ' still due' : 'Fully paid' ?></small></td>
  <td data-label="Paid on"><?= e(date('d M Y', strtotime($x['payment_date']))) ?><small>by <?= e($x['received_by_name'] ?: '—') ?></small></td>
  <td class="fd-num" data-label="Amount"><strong class="ps-amount"><?= e($cur) ?> <?= $m($x['amount']) ?></strong></td>
  <td data-label="Method"><?= e($x['payment_method']) ?><?= $x['reference'] ? '<small>' . e($x['reference']) . '</small>' : '' ?></td>
  <td class="fd-actions no-print" data-label="Slip"><div class="fd-actions__row">
    <a class="btn btn--secondary btn--sm" href="<?= url('/admin/fees/receipt/' . (int) $x['id']) ?>"><i data-lucide="eye"></i>View</a>
    <a class="btn btn--primary btn--sm" href="<?= url('/admin/reports/fee-receipt/' . (int) $x['id'] . '/pdf') ?>" target="_blank" rel="noopener"><i data-lucide="printer"></i>Detailed</a>
    <?php if ($phone && !$x['reversed_at']): ?><form method="post" action="<?= url('/admin/whatsapp/open') ?>" target="_blank" class="inline-form"><?= csrf_field() ?><input type="hidden" name="phone" value="<?= e($phone) ?>"><input type="hidden" name="message" value="<?= e($msg) ?>"><button class="btn btn--ghost btn--sm" title="Send the receipt on WhatsApp"><i data-lucide="message-circle"></i>Send</button></form><?php endif ?>
  </div></td>
</tr>
<?php endforeach ?>
</tbody>
<tfoot><tr><td colspan="4"><strong><?= count($live) ?> receipt<?= count($live) === 1 ? '' : 's' ?></strong><?= count($slips) > count($live) ? ' <small>(' . (count($slips) - count($live)) . ' reversed not counted)</small>' : '' ?></td><td class="fd-num"><strong><?= e($cur) ?> <?= $m($total) ?></strong></td><td></td><td class="no-print"></td></tr></tfoot>
</table></div></section>
<?php endif ?>
</div>
</div></div></main>
<script>
// Search as you type: refresh the results without leaving the page (the form still works without JS).
(()=>{const form=document.querySelector('[data-ps-form]');if(!form||!window.fetch)return;let timer,ctrl;
const run=async()=>{ctrl?.abort();ctrl=new AbortController();const u=new URL(form.action,location.href);u.search=new URLSearchParams(new FormData(form)).toString();const box=document.querySelector('[data-ps-results]');box?.setAttribute('aria-busy','true');
try{const r=await fetch(u,{signal:ctrl.signal,credentials:'same-origin'});const doc=new DOMParser().parseFromString(await r.text(),'text/html');const fresh=doc.querySelector('[data-ps-results]');const cur=document.querySelector('[data-ps-results]');if(fresh&&cur){cur.replaceWith(fresh);window.lucide?.createIcons();history.replaceState(null,'',u);}}catch(e){if(e.name!=='AbortError')document.querySelector('[data-ps-results]')?.removeAttribute('aria-busy');}};
form.querySelector('[data-ps-q]').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(run,300);});})();
</script>
