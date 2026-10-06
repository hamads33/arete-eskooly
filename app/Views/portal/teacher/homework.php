<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/portal/employee/teaching'))?>">My Teaching</a><i data-lucide="chevron-right"></i><span>Homework</span></nav><h1>My Homework</h1><p>Publish homework to your assigned classes.</p></div></header>

<?php require __DIR__.'/_scopebar.php'; ?>

<?php if($selectedClass && $subjects): ?>
<section class="module-card"><header class="module-card__header"><div><h2>Publish Homework</h2></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/portal/employee/teaching/homework'))?>" enctype="multipart/form-data" class="module-form">
<?=csrf_field()?>
<input type="hidden" name="class_id" value="<?=(int)$selectedClass?>">
<div class="module-field"><label for="subject_id">Subject</label><select class="module-input" id="subject_id" name="subject_id" required><?php foreach($subjects as $id=>$name):?><option value="<?=(int)$id?>"><?=e($name)?></option><?php endforeach?></select></div>
<div class="module-field"><label for="date">Date</label><input class="module-input" id="date" name="date" type="date" required value="<?=e(date('Y-m-d'))?>"></div>
<div class="module-field"><label for="homework">Homework</label><textarea class="module-input" id="homework" name="homework" rows="3" required></textarea></div>
<div class="module-field"><label for="attachment">Attachment</label><input class="module-input" id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG or PNG up to 5 MB.</small></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Publish Homework</button></div>
</form>
</div></section>
<?php elseif($selectedClass): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="book-open"></i></span><strong>No Subjects Assigned</strong><p>You have no subjects assigned in this class.</p></div></div></div></section>
<?php endif; ?>

<section class="module-card"><header class="module-card__header"><div><h2>Homework I Assigned</h2><p><?=count($rows)?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-records"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Homework</th><th>Attachment</th><th></th></tr></thead><tbody>
<?php foreach($rows as $h):?><tr>
<td data-label="Date"><?=e($h['homework_date'])?></td><td data-label="Class" class="record-title"><?=e($h['class_name'])?></td><td data-label="Subject" class="record-title"><?=e($h['subject_name']??'—')?></td>
<td data-label="Homework" class="record-wide"><?=e($h['homework_text'])?></td>
<td data-label="Attachment" class="record-wide"><?php if(!empty($h['attachment_path'])):?><a href="<?=e(url('/'.ltrim((string)$h['attachment_path'],'/')))?>" target="_blank" rel="noopener">Open</a><?php else:?>—<?php endif?></td>
<td data-label="Action" class="record-wide"><form method="post" action="<?=e(url('/portal/employee/teaching/homework/'.(int)$h['id'].'/delete'))?>" class="inline-form"><?=csrf_field()?><button class="text-button" type="submit" onclick="return confirm('Delete this homework?')">Delete</button></form></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="6"><div class="compact-empty"><h3>No Homework</h3><p>Homework you publish will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
