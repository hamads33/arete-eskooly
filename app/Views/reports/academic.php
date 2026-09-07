<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Academic Activity Report</h1><p>Homework, class notes and teacher assignments in one place.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="from">From</label><input class="module-input" id="from" name="from" type="date" value="<?=e($from)?>"></div>
<div class="module-field"><label for="to">To</label><input class="module-input" id="to" name="to" type="date" value="<?=e($to)?>"></div>
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<div class="metric-grid">
<div class="metric-card"><span>Homework</span><strong><?=count($homework)?></strong></div>
<div class="metric-card"><span>Notes</span><strong><?=count($notes)?></strong></div>
<div class="metric-card"><span>Teacher Assignments</span><strong><?=count($assignments)?></strong></div>
<div class="metric-card"><span>Range</span><strong><?=e($from)?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Homework Published</h2><p><?=e($from)?> to <?=e($to)?>.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Homework</th></tr></thead><tbody>
<?php foreach($homework as $h):?><tr><td><?=e($h['homework_date'])?></td><td><?=e($h['class_name'])?></td><td><?=e($h['subject_name']??'—')?></td><td><?=e($h['teacher']??'—')?></td><td><?=e($h['homework_text'])?></td></tr><?php endforeach?>
<?php if(!$homework):?><tr><td colspan="5"><div class="compact-empty"><h3>No Homework</h3><p>No homework was published in this range.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Class Notes</h2><p><?=count($notes)?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Publish Date</th><th>Class</th><th>Subject</th><th>Title</th><th>Chapter</th><th>Teacher</th><th>Status</th></tr></thead><tbody>
<?php foreach($notes as $n):?><tr><td><?=e($n['publish_date'])?></td><td><?=e($n['class_name'])?></td><td><?=e($n['subject_name']??'—')?></td><td><?=e($n['title'])?></td><td><?=e($n['chapter']?:'—')?></td><td><?=e($n['teacher']??'—')?></td><td><span class="status-badge"><?=e(ucfirst((string)$n['status']))?></span></td></tr><?php endforeach?>
<?php if(!$notes):?><tr><td colspan="7"><div class="compact-empty"><h3>No Notes</h3><p>No class notes have been uploaded.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Teacher Assignments</h2><p><?=count($assignments)?> assignment(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Employee No</th><th>Teacher</th><th>Designation</th><th>Class</th><th>Subject</th><th>Scope</th><th>Status</th></tr></thead><tbody>
<?php foreach($assignments as $a):?><tr><td><?=e($a['employee_no'])?></td><td><?=e($a['teacher'])?></td><td><?=e($a['role'])?></td><td><?=e($a['class_name'])?></td><td><?=e($a['subject_name'])?></td><td><?=e(ucwords(str_replace('_',' ',(string)$a['assignment_type'])))?></td><td><span class="status-badge"><?=e(ucfirst((string)$a['status']))?></span></td></tr><?php endforeach?>
<?php if(!$assignments):?><tr><td colspan="7"><div class="compact-empty"><h3>No Assignments</h3><p>Assign teachers to classes and subjects to populate this report.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
