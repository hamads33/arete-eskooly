<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/portal/employee/teaching/tests'))?>">Class Tests</a><i data-lucide="chevron-right"></i><span><?=e($test['title'])?></span></nav><h1><?=e($test['title'])?></h1><p><?=e($test['class_name'])?> · <?=e($test['subject_name'])?> · total <?=e((string)$test['total_marks'])?> marks</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Marks</h2><p>Tick Absent instead of entering 0 — absence is recorded separately and shows as ABS.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/portal/employee/teaching/tests/'.(int)$test['id'].'/marks'))?>">
<?=csrf_field()?>
<div class="table-scroll attendance-scroll"><table class="data-table attendance-table"><thead><tr><th>Registration</th><th>Student</th><th>Marks (max <?=e((string)$test['total_marks'])?>)</th><th>Absent</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($sheet as $row): $sid=(int)$row['id']; $abs=!empty($row['is_absent']); ?><tr>
<td data-label="Registration"><?=e($row['registration_no'])?></td>
<td data-label="Student" class="att-name"><?=e($row['student_name'])?></td>
<td data-label="Marks"><input class="module-input" type="number" step="0.01" min="0" max="<?=e((string)$test['total_marks'])?>" name="marks[<?=$sid?>]" value="<?=$row['obtained_marks']!==null?e((string)$row['obtained_marks']):''?>"></td>
<td data-label="Absent"><label class="attendance-checkbox"><input type="checkbox" name="absent[<?=$sid?>]" value="1" <?=$abs?'checked':''?>><span class="sr-only">Absent for <?=e($row['student_name'])?></span></label></td>
<td data-label="Remarks"><input class="module-input" type="text" maxlength="255" name="remarks[<?=$sid?>]" value="<?=e($row['remarks']??'')?>"></td>
</tr><?php endforeach?>
<?php if(!$sheet):?><tr><td colspan="5"><div class="compact-empty"><h3>No Active Students</h3><p>This class has no active students to mark.</p></div></td></tr><?php endif?>
</tbody></table></div>
<?php if($sheet):?><div class="filter-actions attendance-save"><button class="btn btn--primary" type="submit">Save Marks</button></div><?php endif?>
</form>
</div></section>
</div></div></main>
