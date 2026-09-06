<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Observations</span></nav><h1>Observations</h1><p>Record notable behaviour for individual students.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Record Observation</h2></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/behaviour/observations'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="student_id">Student</label><select class="module-input" id="student_id" name="student_id" required><option value="">-- Select a student --</option><?php foreach($students as $s):?><option value="<?=(int)$s['id']?>"><?=e($s['student_name'])?> (<?=e($s['registration_no'])?>)</option><?php endforeach?></select></div>
<div class="module-field"><label for="observation_date">Date</label><input class="module-input" id="observation_date" name="observation_date" type="date" required value="<?=e(date('Y-m-d'))?>"></div>
<div class="module-field"><label for="category">Category</label><select class="module-input" id="category" name="category" required><option value="positive">Positive</option><option value="neutral" selected>Neutral</option><option value="concern">Concern</option></select></div>
<div class="module-field"><label for="note">Observation</label><textarea class="module-input" id="note" name="note" rows="3" required></textarea></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Observation</button></div>
</form>
</div></section>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<section class="module-card"><header class="module-card__header"><div><h2>Recorded Observations</h2><p><?=count($observations)?> record(s).</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Category</th><th>Observation</th><th>By</th><th></th></tr></thead><tbody>
<?php foreach($observations as $o):?><tr>
<td><?=e($o['observation_date'])?></td>
<td><?=e($o['student_name'])?><br><small><?=e($o['registration_no'])?></small></td>
<td><?=e($o['class_name']??'—')?></td>
<td><span class="status-badge"><?=e(ucfirst((string)$o['category']))?></span></td>
<td><?=nl2br(e($o['note']))?></td>
<td><?=e($o['author']??'—')?></td>
<td><form method="post" action="<?=e(url('/admin/behaviour/observations/'.(int)$o['id'].'/delete'))?>" class="inline-form"><?=csrf_field()?><button class="text-button" type="submit" onclick="return confirm('Delete this observation?')">Delete</button></form></td>
</tr><?php endforeach?>
<?php if(!$observations):?><tr><td colspan="7"><div class="compact-empty"><h3>No Observations</h3><p>Recorded observations will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
