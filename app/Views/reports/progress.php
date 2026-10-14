<?php $att=$progress['attendance']??null; $pct=($att&&(int)$att['total']>0)?round((int)$att['present']/(int)$att['total']*100,1):0.0; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Student Progress Report</h1><p>Every class test, exam and online test for one student, with percentages, plus attendance and behaviour.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="student">Student</label><select class="module-input" id="student" name="student" required><option value="0">-- Select a student --</option><?php foreach($students as $s):?><option value="<?=(int)$s['id']?>" <?=($student&&(int)$student['id']===(int)$s['id'])?'selected':''?>><?=e($s['student_name'])?> (<?=e($s['registration_no'])?>)</option><?php endforeach?></select></div>
<div class="module-field"><label for="subject">Subject</label><select class="module-input" id="subject" name="subject"><option value="0">All subjects</option><?php $seenSub=[]; foreach($subjects as $sub): if(isset($seenSub[(int)$sub['id']]))continue; $seenSub[(int)$sub['id']]=1; ?><option value="<?=(int)$sub['id']?>" <?=$filters['subject']===(int)$sub['id']?'selected':''?>><?=e($sub['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="type">Assessment</label><select class="module-input" id="type" name="type"><?php foreach([''=>'All assessments','class_test'=>'Class tests','exam'=>'Exams','online_test'=>'Online tests'] as $k=>$v):?><option value="<?=e($k)?>" <?=$filters['type']===$k?'selected':''?>><?=e($v)?></option><?php endforeach?></select></div>
<div class="module-field"><label for="from">From</label><input class="module-input" id="from" name="from" type="date" value="<?=e($filters['from'])?>"></div>
<div class="module-field"><label for="to">To</label><input class="module-input" id="to" name="to" type="date" value="<?=e($filters['to'])?>"></div>
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
<?php
$scored = static fn(string $type = ''): array => array_values(array_filter($assessments, static fn(array $a): bool => $a['percentage'] !== null && ($type === '' || $a['type'] === $type)));
$avg = static function (array $rows): ?float { $o = array_sum(array_map(static fn($a) => (float)$a['obtained'], $rows)); $t = array_sum(array_map(static fn($a) => (float)$a['total'], $rows)); return $t > 0 ? round($o / $t * 100, 1) : null; };
$typeLabel = ['class_test' => 'Class Test', 'exam' => 'Exam', 'online_test' => 'Online Test'];
$stateLabel = ['absent' => 'Absent', 'unmarked' => 'Awaiting marking', 'unpublished' => 'Marked, not published', 'no_total' => 'No total marks set'];
?>
<div class="metric-grid">
<div class="metric-card"><span>Overall (filtered)</span><strong><?=($v=$avg($scored()))!==null?e((string)$v).'%':'—'?></strong></div>
<div class="metric-card"><span>Class Tests</span><strong><?=($v=$avg($scored('class_test')))!==null?e((string)$v).'%':'—'?></strong></div>
<div class="metric-card"><span>Exams</span><strong><?=($v=$avg($scored('exam')))!==null?e((string)$v).'%':'—'?></strong></div>
<div class="metric-card"><span>Online Tests</span><strong><?=($v=$avg($scored('online_test')))!==null?e((string)$v).'%':'—'?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>All Assessments</h2><p><?=count($assessments)?> record<?=count($assessments)===1?'':'s'?><?=$filters['from']||$filters['to']?' · '.e($filters['from']?:'start').' to '.e($filters['to']?:'today'):''?>. Absences and unmarked tests are listed but not counted in percentages.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Subject</th><th>Obtained</th><th>Total</th><th>Percentage</th></tr></thead><tbody>
<?php foreach($assessments as $a): ?><tr>
<td><?=e((string)$a['d'])?></td><td><?=e($typeLabel[$a['type']])?></td><td><?=e($a['title'])?></td><td><?=e($a['subject']??'—')?></td>
<?php if($a['percentage']!==null): ?><td><?=e((string)(float)$a['obtained'])?></td><td><?=e((string)(float)$a['total'])?></td><td><?=e((string)$a['percentage'])?>%</td>
<?php else: ?><td colspan="3"><span class="status-badge<?=$a['state']==='absent'?' status-badge--absent':''?>"><?=e($stateLabel[$a['state']]??'—')?></span></td><?php endif ?>
</tr><?php endforeach ?>
<?php if(!$assessments):?><tr><td colspan="7"><div class="compact-empty"><h3>No Assessments</h3><p>Nothing recorded for this student with these filters.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

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

<section class="module-card"><header class="module-card__header"><div><h2>Online Tests</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Published Results</th><th>Average Percentage</th></tr></thead><tbody>
<?php if($onlineTests&&$onlineTests['tests']>0):?><tr><td><?=(int)$onlineTests['tests']?></td><td><?=e((string)$onlineTests['average_percentage'])?>%</td></tr>
<?php else:?><tr><td colspan="2"><div class="compact-empty"><h3>No Published Online Tests</h3><p>No online test results have been published for this student.</p></div></td></tr><?php endif?>
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
