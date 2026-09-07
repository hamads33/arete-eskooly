<?php $showDate=true; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/portal/employee/teaching'))?>">My Teaching</a><i data-lucide="chevron-right"></i><span>Attendance</span></nav><h1>Mark Attendance</h1><p>Present, Absent, Late and Leave are recorded separately.</p></div></header>

<?php require __DIR__.'/_scopebar.php'; ?>

<?php if(!$selectedClass): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="clipboard-check"></i></span><strong>Choose a Class</strong><p>Select one of your assigned classes and a date.</p></div></div></div></section>
<?php elseif(!$rows): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users-round"></i></span><strong>No Active Students</strong><p>This class has no active students to mark.</p></div></div></div></section>
<?php else: ?>
<section class="module-card"><header class="module-card__header"><div><h2>Attendance for <?=e($date)?></h2><p><?=count($rows)?> student(s). Saving again updates the same records.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/portal/employee/teaching/attendance'))?>">
<?=csrf_field()?>
<input type="hidden" name="class" value="<?=(int)$selectedClass?>">
<input type="hidden" name="date" value="<?=e($date)?>">
<div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($rows as $row): $cur=$row['attendance_status']??'Present'; ?><tr>
<td><?=e($row['registration_no'])?></td>
<td><?=e($row['student_name'])?></td>
<td><select class="module-input" name="status[<?=(int)$row['id']?>]"><?php foreach(['Present','Absent','Late','Leave'] as $s):?><option value="<?=$s?>" <?=$cur===$s?'selected':''?>><?=$s?></option><?php endforeach?></select></td>
<td><input class="module-input" type="text" maxlength="255" name="remarks[<?=(int)$row['id']?>]" value="<?=e($row['remarks']??'')?>"></td>
</tr><?php endforeach?>
</tbody></table></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Attendance</button></div>
</form>
</div></section>
<?php endif; ?>
</div></div></main>
