<?php $att=$progress['attendance']??null; $pct=($att&&(int)$att['total']>0)?round((int)$att['present']/(int)$att['total']*100,1):0.0; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Student Progress Report</h1><p>Exams, class tests, attendance and behaviour for one student.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="student">Student</label><select class="module-input" id="student" name="student" required><option value="0">-- Select a student --</option><?php foreach($students as $s):?><option value="<?=(int)$s['id']?>" <?=($student&&(int)$student['id']===(int)$s['id'])?'selected':''?>><?=e($s['student_name'])?> (<?=e($s['registration_no'])?>)</option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary">View Progress</button></div>
</form>

<?php if(!$student): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="user-round"></i></span><strong>Choose a Student</strong><p>Select a student to see their full progress picture.</p></div></div></div></section>
<?php else: ?>

<div class="metric-grid">
<div class="metric-card"><span>Student</span><strong><?=e($student['student_name'])?></strong></div>
<div class="metric-card"><span>Class</span><strong><?=e($student['class_name']??'—')?></strong></div>
<div class="metric-card"><span>Attendance</span><strong><?=e((string)$pct)?>%</strong></div>
<div class="metric-card"><span>Exams Sat</span><strong><?=count($progress['exams'])?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Exam Performance</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Exam</th><th>Date</th><th>Obtained</th><th>Total</th><th>Percentage</th></tr></thead><tbody>
<?php foreach($progress['exams'] as $x): $t=(float)$x['total']; ?><tr>
<td><?=e($x['exam'])?></td><td><?=e($x['start_date'])?></td>
<td><?=e((string)$x['obtained'])?></td><td><?=e((string)$x['total'])?></td>
<td><?=$t>0?e((string)round((float)$x['obtained']/$t*100,2)).'%':'—'?></td>
</tr><?php endforeach?>
<?php if(!$progress['exams']):?><tr><td colspan="5"><div class="compact-empty"><h3>No Exam Results</h3><p>No exam marks recorded for this student.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Class Test Averages</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Subject</th><th>Tests</th><th>Average</th></tr></thead><tbody>
<?php foreach($progress['tests'] as $t):?><tr><td><?=e($t['subject']??'—')?></td><td><?=(int)$t['tests']?></td><td><?=e((string)$t['average_percentage'])?>%</td></tr><?php endforeach?>
<?php if(!$progress['tests']):?><tr><td colspan="3"><div class="compact-empty"><h3>No Class Tests</h3><p>No test marks recorded for this student.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Behaviour &amp; Skills</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Domain</th><th>Traits Rated</th><th>Average Rating</th></tr></thead><tbody>
<?php foreach($progress['behaviour'] as $b):?><tr><td><?=e(ucfirst((string)$b['domain']))?></td><td><?=(int)$b['rated']?></td><td><?=e((string)$b['average_rating'])?> / 5</td></tr><?php endforeach?>
<?php if(!$progress['behaviour']):?><tr><td colspan="3"><div class="compact-empty"><h3>No Ratings</h3><p>No behaviour or skills ratings recorded for this student.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Attendance</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Days Marked</th><th>Present</th><th>Absent</th><th>Percentage</th></tr></thead><tbody>
<tr><td><?=(int)($att['total']??0)?></td><td><?=(int)($att['present']??0)?></td><td><?=(int)($att['absent']??0)?></td><td><?=e((string)$pct)?>%</td></tr>
</tbody></table></div></div></section>

<?php endif; ?>
</div></div></main>
