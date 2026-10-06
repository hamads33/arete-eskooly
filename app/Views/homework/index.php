<?php
/**
 * Homework.
 *
 * The row list used to render a full edit form — class, subject and teacher dropdowns — for
 * every assignment. With a few hundred classes that produced a multi-megabyte page with tens of
 * thousands of <option> elements, which mobile browsers could not render (dropdowns came up
 * empty). Now the list is a compact table and the edit form is rendered only for the one row
 * being edited, via ?edit={id}.
 */
$editId = (int) ($editId ?? 0);
$editing = null;
foreach ($rows as $candidate) {
    if ((int) $candidate['id'] === $editId) {
        $editing = $candidate;
        break;
    }
}
$filterDate = (string) ($filterDate ?? '');
$filterClass = (int) ($filterClass ?? 0);
$filterTeacher = (int) ($filterTeacher ?? 0);
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/dashboard')) ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Homework</span></nav><h1>Homework</h1><p>Filter, add and update persisted assignments.</p></div></header>

<form class="filter-bar filter-bar--compact">
  <div class="module-field"><label for="filterDate">Date</label><input class="module-input" id="filterDate" type="date" name="date" value="<?= e($filterDate) ?>"></div>
  <div class="module-field"><label for="filterClass">Class</label><select class="module-input" id="filterClass" name="class"><option value="">All Classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filterClass === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
  <?php if (!$pinnedTeacher): ?>
  <div class="module-field"><label for="filterTeacher">Teacher</label><select class="module-input" id="filterTeacher" name="teacher"><option value="">All Teachers</option><?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $filterTeacher === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
  <?php endif ?>
  <div class="filter-actions"><button class="btn btn--primary">Filter</button><a class="btn btn--ghost" href="<?= e(url('/admin/homework')) ?>">Reset</a></div>
</form>

<?php if ($editing): ?>
<section class="module-card"><header class="module-card__header"><div><h2>Edit Homework</h2><p><?= e($editing['class_name']) ?> &middot; <?= e($editing['subject_name'] ?? 'No subject') ?> &middot; <?= e($editing['homework_date']) ?></p></div><a class="btn btn--ghost" href="<?= e(url('/admin/homework')) ?>">Cancel</a></header><div class="module-card__body">
<form method="post" action="<?= e(url('/admin/homework/' . (int) $editing['id'])) ?>" enctype="multipart/form-data" class="module-form">
  <?= csrf_field() ?>
  <div class="module-form-grid module-form-grid--three">
    <div class="module-field"><label for="editDate">Date</label><input class="module-input" id="editDate" type="date" name="hwdate" value="<?= e($editing['homework_date']) ?>" required></div>
    <?php if ($pinnedTeacher): ?>
      <input type="hidden" name="hwteacher" value="<?= (int) $pinnedTeacher ?>">
    <?php else: ?>
      <div class="module-field"><label for="editTeacher">Teacher</label><select class="module-input" id="editTeacher" name="hwteacher"><?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $editing['teacher_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
    <?php endif ?>
    <div class="module-field"><label for="editClass">Class</label><select class="module-input" id="editClass" name="hwclass" required><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $editing['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
    <div class="module-field"><label for="editSubject">Subject</label><select class="module-input" id="editSubject" name="hwsubject" required data-subject-filter="#editClass"><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>" data-class="<?= (int) $s['class_id'] ?>" <?= (int) $editing['subject_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="module-field field--wide"><label for="editText">Homework</label><textarea class="module-input" id="editText" name="homework" rows="3" required><?= e($editing['homework_text']) ?></textarea></div>
    <div class="module-field"><label for="editFile">Replace attachment</label><input class="module-input" id="editFile" type="file" name="hwattachment" accept=".pdf,.jpg,.jpeg,.png"><?php if ($editing['attachment_path']): ?><small><a href="<?= e(url('/' . ltrim((string) $editing['attachment_path'], '/'))) ?>" target="_blank" rel="noopener">Current attachment</a></small><?php endif ?></div>
  </div>
  <div class="filter-actions"><button class="btn btn--primary" type="submit">Update Homework</button></div>
</form>
</div></section>
<?php endif ?>

<section class="module-card"><header class="module-card__header"><div><h2>Add Homework</h2><p>Choose a class, then one of its subjects.</p></div></header><div class="module-card__body">
<form method="post" action="<?= e(url('/admin/homework')) ?>" enctype="multipart/form-data" class="module-form">
  <?= csrf_field() ?>
  <div class="module-form-grid module-form-grid--three">
    <div class="module-field"><label for="addDate">Date</label><input class="module-input" id="addDate" type="date" name="hwdate" value="<?= e(date('Y-m-d')) ?>" required></div>
    <?php if ($pinnedTeacher): ?>
      <input type="hidden" name="hwteacher" value="<?= (int) $pinnedTeacher ?>">
      <div class="module-field"><label>Teacher</label><input class="module-input" type="text" value="<?= e($teachers[0]['name'] ?? 'You') ?>" readonly></div>
    <?php else: ?>
      <div class="module-field"><label for="addTeacher">Teacher</label><select class="module-input" id="addTeacher" name="hwteacher" required><?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach ?></select></div>
    <?php endif ?>
    <?php if($pinnedTeacher):?><div class="module-field"><label for="hwClass">Class</label><select class="module-input" id="hwClass" name="hwclass" required><option value="">-- Select a class --</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach ?></select></div><?php else:?><div class="module-field"><label>Audience</label><select class="module-select" id="homeworkAudience" name="audience_type"><option value="class">One or more classes</option><option value="all">All students</option><option value="student">Specific student</option></select></div><fieldset class="module-field field--wide" data-homework-classes><legend>Select classes</legend><div class="live-class-picker__options"><?php foreach($classes as$c):?><label class="live-class-picker__option"><input type="checkbox" name="class_ids[]" value="<?=$c['id']?>"><span><?=e($c['name'])?></span></label><?php endforeach?></div></fieldset><div class="module-field" data-homework-student hidden><label>Student</label><select class="module-select" name="student_id"><option value="">-- Select student --</option><?php foreach($students as$s):?><option value="<?=$s['id']?>"><?=e($s['student_name'].' · '.$s['registration_no'].' · '.$s['class_name'])?></option><?php endforeach?></select></div><?php endif?>
    <div class="module-field"><label for="hwSubject">Subject <small><?=!$pinnedTeacher?'(optional)':''?></small></label><select class="module-input" id="hwSubject" name="hwsubject" <?=$pinnedTeacher?'required data-subject-filter="#hwClass"':''?>><option value="">General</option><?php $seenSubjects=[];foreach($subjects as$s):if(!$pinnedTeacher&&isset($seenSubjects[$s['id']]))continue;$seenSubjects[$s['id']]=true;?><option value="<?= (int) $s['id'] ?>" data-class="<?= (int) $s['class_id'] ?>"><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="module-field field--wide"><label for="hwText">Homework</label><textarea class="module-input" id="hwText" name="homework" rows="3" required></textarea></div>
    <div class="module-field"><label for="hwFile">Attachment</label><input class="module-input" id="hwFile" type="file" name="hwattachment" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG or PNG up to 5 MB.</small></div>
  </div>
  <div class="filter-actions"><button class="btn btn--primary" type="submit">Add Homework</button></div>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Assigned Homework</h2><p><?= count($rows) ?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Homework</th><th>Attachment</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $h): ?><tr>
  <td><?= e($h['homework_date']) ?></td>
  <td><?=e(($h['audience_type']??'class')==='all'?'All students':(($h['audience_type']??'class')==='student'?($h['student_name']??'Student'):($h['class_names']??$h['class_name']??'Class')))?></td>
  <td><?= e($h['subject_name'] ?? '—') ?></td>
  <td><?= e($h['teacher'] ?? '—') ?></td>
  <td><?= e($h['homework_text']) ?></td>
  <td><?php if ($h['attachment_path']): ?><a href="<?= e(url('/' . ltrim((string) $h['attachment_path'], '/'))) ?>" target="_blank" rel="noopener">Open</a><?php else: ?>—<?php endif ?></td>
  <td>
    <?php if(($h['audience_type']??'class')==='class'&&count(explode(',',(string)($h['class_names']??'')))===1):?><a class="text-button" href="<?= e(url('/admin/homework?edit=' . (int) $h['id'])) ?>">Edit</a><?php endif?>
    <form method="post" action="<?= e(url('/admin/homework/' . (int) $h['id'] . '/delete')) ?>" class="inline-form"><?= csrf_field() ?><button class="text-button" type="submit" onclick="return confirm('Delete this homework?')">Delete</button></form>
  </td>
</tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7"><div class="compact-empty"><h3>No Homework</h3><p>No assignments match these filters.</p></div></td></tr><?php endif ?>
</tbody></table></div></div></section>
</div></div></main>
<?php if(!$pinnedTeacher):?><script>document.addEventListener('DOMContentLoaded',()=>{const a=document.getElementById('homeworkAudience'),c=document.querySelector('[data-homework-classes]'),s=document.querySelector('[data-homework-student]');const sync=()=>{c.hidden=a.value!=='class';s.hidden=a.value!=='student';s.querySelector('select').required=a.value==='student'};a.addEventListener('change',sync);sync()});</script><?php endif?>
