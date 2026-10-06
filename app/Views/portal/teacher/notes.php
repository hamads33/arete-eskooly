<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/portal/employee/teaching'))?>">My Teaching</a><i data-lucide="chevron-right"></i><span>Class Notes</span></nav><h1>My Class Notes</h1><p>Share study material with your assigned classes.</p></div></header>

<?php require __DIR__.'/_scopebar.php'; ?>

<?php if($selectedClass && $subjects): ?>
<section class="module-card"><header class="module-card__header"><div><h2>Upload Note</h2></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/portal/employee/teaching/notes'))?>" enctype="multipart/form-data" class="module-form">
<?=csrf_field()?>
<input type="hidden" name="class_id" value="<?=(int)$selectedClass?>">
<div class="module-field"><label for="subject_id">Subject</label><select class="module-input" id="subject_id" name="subject_id" required><?php foreach($subjects as $id=>$name):?><option value="<?=(int)$id?>"><?=e($name)?></option><?php endforeach?></select></div>
<div class="module-field"><label for="title">Title</label><input class="module-input" id="title" name="title" type="text" required maxlength="150"></div>
<div class="module-field"><label for="chapter">Chapter / Topic</label><input class="module-input" id="chapter" name="chapter" type="text" maxlength="150" placeholder="Optional"></div>
<div class="module-field"><label for="description">Description</label><textarea class="module-input" id="description" name="description" rows="3"></textarea></div>
<div class="module-field"><label for="publish_date">Publish Date</label><input class="module-input" id="publish_date" name="publish_date" type="date" value="<?=e(date('Y-m-d'))?>"></div>
<div class="module-field"><label for="status">Status</label><select class="module-input" id="status" name="status"><option value="published">Published</option><option value="draft">Draft</option></select></div>
<div class="module-field"><label for="attachment">Attachment</label><input class="module-input" id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG or PNG up to 5 MB.</small></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Note</button></div>
</form>
</div></section>
<?php endif; ?>

<section class="module-card"><header class="module-card__header"><div><h2>Notes I Uploaded</h2><p><?=count($rows)?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Publish Date</th><th>Class</th><th>Subject</th><th>Title</th><th>Chapter</th><th>Status</th><th>File</th></tr></thead><tbody>
<?php foreach($rows as $n):?><tr>
<td><?=e($n['publish_date'])?></td><td><?=e($n['class_name'])?></td><td><?=e($n['subject_name']??'—')?></td>
<td><?=e($n['title'])?></td><td><?=e($n['chapter']?:'—')?></td>
<td><span class="status-badge"><?=e(ucfirst((string)$n['status']))?></span></td>
<td><?php if(!empty($n['attachment_path'])):?><a href="<?=e(url('/attachments/notes/'.$n['id']))?>" target="_blank" rel="noopener">Open preview</a><?php else:?>—<?php endif?></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="7"><div class="compact-empty"><h3>No Notes</h3><p>Notes you upload will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
