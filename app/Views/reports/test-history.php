<?php
$sat = array_values(array_filter($rows, static fn(array $r): bool => !(int)$r['is_absent']));
$obtained = array_sum(array_map(static fn(array $r): float => (float)$r['obtained_marks'], $sat));
$total = array_sum(array_map(static fn(array $r): float => (float)$r['total_marks'], $sat));
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Student Test History</h1><p>Every class test recorded for one student. Absences show as ABS, never as zero.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="student">Student</label><select class="module-input" id="student" name="student" required><option value="0">-- Select a student --</option><?php foreach($students as $s):?><option value="<?=(int)$s['id']?>" <?=($student&&(int)$student['id']===(int)$s['id'])?'selected':''?>><?=e($s['student_name'])?> (<?=e($s['registration_no'])?>)</option><?php endforeach?></select></div>
<div class="module-field"><label for="from">From</label><input class="module-input" id="from" name="from" type="date" value="<?=e($from)?>"></div>
<div class="module-field"><label for="to">To</label><input class="module-input" id="to" name="to" type="date" value="<?=e($to)?>"></div>
<div class="filter-actions"><button class="btn btn--primary">View History</button></div>
</form>

<?php if(!$student): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="user-round"></i></span><strong>Choose a Student</strong><p>Select a student to see their test history.</p></div></div></div></section>
<?php else: ?>
<div class="metric-grid">
<div class="metric-card"><span>Student</span><strong><?=e($student['student_name'])?></strong></div>
<div class="metric-card"><span>Tests Recorded</span><strong><?=count($rows)?></strong></div>
<div class="metric-card"><span>Absent</span><strong><?=count($rows)-count($sat)?></strong></div>
<div class="metric-card"><span>Average</span><strong><?=$total>0?e((string)round($obtained/$total*100,1)).'%':'—'?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Test History</h2><p><?=e($from)?> to <?=e($to)?>.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Test</th><th>Class</th><th>Subject</th><th>Total</th><th>Obtained</th><th>Percentage</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($rows as $r): $abs=(int)$r['is_absent']; $t=(float)$r['total_marks']; ?><tr>
<td><?=e($r['test_date'])?></td>
<td><?=e($r['title'])?></td>
<td><?=e($r['class_name']??'—')?></td>
<td><?=e($r['subject']??'—')?></td>
<td><?=e((string)$r['total_marks'])?></td>
<td><?=$abs?'<span class="status-badge">ABS</span>':e((string)$r['obtained_marks'])?></td>
<td><?=$abs?'—':($t>0?e((string)round((float)$r['obtained_marks']/$t*100,1)).'%':'—')?></td>
<td><?=e($r['remarks']?:'—')?></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="8"><div class="compact-empty"><h3>No Tests</h3><p>No class tests recorded for this student in the selected range.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
<?php endif; ?>
</div></div></main>
