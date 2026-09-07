<?php $short=array_values(array_filter($rows, static fn(array $r):bool=>(float)$r['percentage']<(float)$threshold && (int)$r['marked']>0)); ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Attendance Shortage Report</h1><p>Eligibility against the institute's minimum attendance. Reporting only &mdash; nobody is blocked automatically.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="from">From</label><input class="module-input" id="from" name="from" type="date" value="<?=e($from)?>"></div>
<div class="module-field"><label for="to">To</label><input class="module-input" id="to" name="to" type="date" value="<?=e($to)?>"></div>
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<?php if(!$configured): ?><div class="module-field"><label for="threshold">Required %</label><input class="module-input" id="threshold" name="threshold" type="number" step="0.1" min="0" max="100" value="<?=e((string)$threshold)?>"></div><?php endif; ?>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<div class="metric-grid">
<div class="metric-card"><span>Required Attendance</span><strong><?=e((string)$threshold)?>%</strong></div>
<div class="metric-card"><span>Students Reviewed</span><strong><?=count($rows)?></strong></div>
<div class="metric-card"><span>Below Threshold</span><strong><?=count($short)?></strong></div>
<div class="metric-card"><span>Threshold Source</span><strong><?=$configured?'Academic profile':'Manual'?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Attendance &amp; Eligibility</h2><p><?=e($from)?> to <?=e($to)?>. Late counts as attending; Absent and Leave do not.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Class</th><th>Marked</th><th>Present</th><th>Absent</th><th>Leave</th><th>Attendance</th><th>Shortage</th><th>Eligibility</th></tr></thead><tbody>
<?php foreach($rows as $r): $pct=(float)$r['percentage']; $marked=(int)$r['marked']; $gap=$marked>0?round(max(0,(float)$threshold-$pct),1):0.0; ?><tr>
<td><?=e($r['registration_no'])?></td>
<td><?=e($r['student_name'])?></td>
<td><?=e($r['class_name']??'—')?></td>
<td><?=$marked?></td>
<td><?=(int)$r['present']?></td>
<td><?=(int)$r['absent']?></td>
<td><?=(int)$r['on_leave']?></td>
<td><?=e((string)$pct)?>%</td>
<td><?=$marked>0?e((string)$gap).'%':'—'?></td>
<td><span class="status-badge"><?=$marked===0?'Not marked':($pct>=(float)$threshold?'Eligible':'Short')?></span></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="10"><div class="compact-empty"><h3>No Students</h3><p>No active students match this filter.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
