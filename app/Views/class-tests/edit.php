<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/class-tests'))?>">Class Tests</a><i data-lucide="chevron-right"></i><span><?=e($test['title'])?></span></nav><h1><?=e($test['title'])?></h1><p><?=e($test['class_name'])?> · <?=e($test['subject_name'])?> · total <?=e((string)$test['total_marks'])?> marks</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Test Details</h2></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/class-tests/'.(int)$test['id'].'/update'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="title">Title</label><input class="module-input" id="title" name="title" type="text" required maxlength="150" value="<?=e($test['title'])?>"></div>
<div class="module-field"><label for="date">Test Date</label><input class="module-input" id="date" name="date" type="date" required value="<?=e($test['test_date'])?>"></div>
<div class="module-field"><label for="total_marks">Total Marks</label><input class="module-input" id="total_marks" name="total_marks" type="number" step="0.01" min="1" required value="<?=e((string)$test['total_marks'])?>"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Details</button></div>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Marks</h2><p>Active students in <?=e($test['class_name'])?>. Leave blank to skip a student.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/class-tests/'.(int)$test['id'].'/marks'))?>">
<?=csrf_field()?>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Obtained (max <?=e((string)$test['total_marks'])?>)</th><th>Absent</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($sheet as $row):?><tr>
<td><?=e($row['registration_no'])?></td>
<td><?=e($row['student_name'])?></td>
<td><input class="module-input" type="number" step="0.01" min="0" max="<?=e((string)$test['total_marks'])?>" name="marks[<?=(int)$row['id']?>]" value="<?=$row['obtained_marks']!==null?e((string)$row['obtained_marks']):''?>"></td>
<td><label class="check-row"><input type="checkbox" name="absent[<?=(int)$row['id']?>]" value="1" <?=!empty($row['is_absent'])?'checked':''?>> ABS</label></td>
<td><input class="module-input" name="remarks[<?=(int)$row['id']?>]" value="<?=e($row['remarks']??'')?>" maxlength="255"></td>
</tr><?php endforeach?>
<?php if(!$sheet):?><tr><td colspan="5"><div class="compact-empty"><h3>No Active Students</h3><p>This class has no active students to mark.</p></div></td></tr><?php endif?>
</tbody></table></div>
<?php if($sheet):?><div class="filter-actions"><button class="btn btn--primary" type="submit">Save Marks</button></div><?php endif?>
</form>
</div></section>
</div></div></main>
