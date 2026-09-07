<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/settings/academic-structure'))?>">Academic Structure</a><i data-lucide="chevron-right"></i><span>Place Students</span></nav><h1>Place Students</h1><p>Assign each student to a section and stream.</p></div></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class" required><option value="0">-- Select a class --</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary">Load Students</button></div>
</form>

<?php if(!$selectedClass): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users-round"></i></span><strong>Choose a Class</strong><p>Select a class to place its students.</p></div></div></div></section>
<?php elseif(!$sections): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="layout-grid"></i></span><strong>No Sections For This Class</strong><p>Add a section before placing students.</p><a class="btn btn--primary" href="<?=e(url('/admin/settings/academic-structure'))?>">Manage Sections</a></div></div></div></section>
<?php elseif(!$students): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users-round"></i></span><strong>No Students</strong><p>This class has no students yet.</p></div></div></div></section>
<?php else: ?>
<section class="module-card"><header class="module-card__header"><div><h2>Student Placement</h2><p><?=count($students)?> student(s).</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/settings/academic-placement'))?>">
<?=csrf_field()?>
<input type="hidden" name="class" value="<?=(int)$selectedClass?>">
<div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Section</th><th>Stream / Group</th></tr></thead><tbody>
<?php foreach($students as $st): $sid=(int)$st['id']; ?><tr>
<td><?=e($st['registration_no'])?></td>
<td><?=e($st['student_name'])?></td>
<td><select class="module-input" name="section[<?=$sid?>]"><option value="0">&mdash;</option><?php foreach($sections as $sec):?><option value="<?=(int)$sec['id']?>" <?=((int)($st['section_id']??0)===(int)$sec['id'])?'selected':''?>><?=e($sec['name'])?></option><?php endforeach?></select></td>
<td><select class="module-input" name="stream[<?=$sid?>]"><option value="0">&mdash;</option><?php foreach($streams as $str):?><option value="<?=(int)$str['id']?>" <?=((int)($st['stream_id']??0)===(int)$str['id'])?'selected':''?>><?=e($str['name'])?></option><?php endforeach?></select></td>
</tr><?php endforeach?>
</tbody></table></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Placement</button></div>
</form>
</div></section>
<?php endif; ?>
</div></div></main>
