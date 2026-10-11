<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Attendance</span></nav><h1>My Attendance</h1><p>Your recorded attendance for the selected range.</p></div></header>

<form class="filter-bar filter-bar--compact" method="get"><div class="module-field"><label for="from">From Date</label><input class="module-input" id="from" name="from" type="date" value="<?=e($from)?>"></div><div class="module-field"><label for="to">To Date</label><input class="module-input" id="to" name="to" type="date" value="<?=e($to)?>"></div><div class="filter-actions"><button class="btn btn--primary">Apply</button></div></form>

<div class="metric-grid">
<div class="metric-card"><span>Attendance</span><strong><?=e((string)$summary['percentage'])?>%</strong></div>
<div class="metric-card"><span>Present</span><strong><?=(int)$summary['present']?></strong></div>
<div class="metric-card"><span>Absent</span><strong><?=(int)$summary['absent']?></strong></div>
<div class="metric-card"><span>On Leave</span><strong><?=(int)$summary['leave']?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Attendance Record</h2><p><?=e($from)?> to <?=e($to)?></p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-register"><thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($records as $r):?><tr><td data-label="Date"><?=e($r['attendance_date'])?></td><td data-label="Day"><?=e(date('l',strtotime((string)$r['attendance_date'])))?></td><td data-label="Status"><span class="status-badge"><?=e($r['status'])?></span></td><td data-label="Remarks" class="record-wide"><?=e($r['remarks']?:'—')?></td></tr><?php endforeach?>
<?php if(!$records):?><tr><td colspan="4"><div class="compact-empty"><h3>No Records Found</h3><p>No attendance was recorded in this range.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
