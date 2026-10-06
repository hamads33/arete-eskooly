<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Results</span></nav><h1>My Results</h1><p>Exam results, class tests and certificates.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<?php foreach($exams as $exam): ?>
<section class="module-card"><header class="module-card__header"><div><h2><?=e($exam['name'])?></h2><p>Starting <?=e($exam['date'])?></p></div><div class="metric-card"><span>Grade <?=e($exam['grade'])?></span><strong><?=e((string)$exam['percentage'])?>%</strong></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Subject</th><th>Obtained</th><th>Total</th><th>Percentage</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($exam['subjects'] as $s): $t=(float)$s['total_marks']; ?><tr><td><?=e($s['subject'])?></td><td><?=e((string)$s['obtained_marks'])?></td><td><?=e((string)$s['total_marks'])?></td><td><?=$t>0?e((string)round((float)$s['obtained_marks']/$t*100,1)).'%':'—'?></td><td><?=e($s['remarks']?:'—')?></td></tr><?php endforeach?>
<tr><th>Total</th><th><?=e((string)$exam['obtained'])?></th><th><?=e((string)$exam['total'])?></th><th><?=e((string)$exam['percentage'])?>%</th><th><?=e($exam['grade'])?></th></tr>
</tbody></table></div></div></section>
<?php endforeach; ?>
<?php if(!$exams): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="graduation-cap"></i></span><strong>No Exam Results Yet</strong><p>Results appear here once marks are published.</p></div></div></div></section>
<?php endif; ?>

<section class="module-card"><header class="module-card__header"><div><h2>Class Tests</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Title</th><th>Subject</th><th>Obtained</th><th>Total</th></tr></thead><tbody>
<?php foreach($tests as $t):?><tr><td><?=e($t['test_date'])?></td><td><?=e($t['title'])?></td><td><?=e($t['subject']??'—')?></td><td><?=e((string)$t['obtained_marks'])?></td><td><?=e((string)$t['total_marks'])?></td></tr><?php endforeach?>
<?php if(!$tests):?><tr><td colspan="5"><div class="compact-empty"><h3>No Class Tests</h3><p>No test marks have been recorded for you.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Certificates</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Certificate</th><th>Achievement</th></tr></thead><tbody>
<?php foreach($certificates as $c):?><tr><td><?=e($c['certificate_date'])?></td><td><?=e($c['template'])?></td><td><?=e($c['achievement']?:'—')?></td></tr><?php endforeach?>
<?php if(!$certificates):?><tr><td colspan="3"><div class="compact-empty"><h3>No Certificates</h3><p>Certificates issued to you will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
<section class="module-card"><header class="module-card__header"><div><h2>Online Tests</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Test</th><th>Subject</th><th>Obtained</th><th>Total</th><th>Percentage</th><th></th></tr></thead><tbody>
<?php foreach($onlineTests as$t):$total=(float)$t['total_marks'];?><tr><td><?=e($t['submitted_at'])?></td><td><?=e($t['title'])?><?php if(trim((string)($t['teacher_comment']??''))!==''):?><small class="teacher-comment-inline"><b>Teacher:</b> <?=e(mb_strimwidth((string)$t['teacher_comment'],0,120,'…'))?></small><?php endif?></td><td><?=e($t['subject']??'—')?></td><td><?=e($t['total_score'])?></td><td><?=e($t['total_marks'])?></td><td><?=$total>0?e((string)round((float)$t['total_score']/$total*100,1)).'%':'—'?></td><td><a class="btn btn--secondary" href="<?=url('/portal/student/online-tests/'.$t['attempt_id'].'/result')?>">View</a></td></tr><?php endforeach?>
<?php if(!$onlineTests):?><tr><td colspan="7"><div class="compact-empty"><h3>No Published Online Tests</h3><p>Results appear here once your teacher marks and publishes them.</p></div></td></tr><?php endif?></tbody></table></div></div></section>
</div></div></main>
