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
$canManage = (bool) ($canManage ?? can('homework.manage'));
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

<?php if ($editing && $canManage): ?>
<section class="module-card"><header class="module-card__header"><div><h2>Edit Homework</h2><p><?= e($editing['class_name']) ?> &middot; <?= e($editing['subject_name'] ?? 'No subject') ?> &middot; <?= e($editing['homework_date']) ?></p></div><a class="btn btn--ghost" href="<?= e(url('/admin/homework')) ?>">Cancel</a></header><div class="module-card__body">
<form method="post" action="<?= e(url('/admin/homework/' . (int) $editing['id'])) ?>" enctype="multipart/form-data" class="module-form">
  <?= csrf_field() ?>
  <div class="module-form-grid module-form-grid--three">
    <div class="module-field"><label for="editDate">Date</label><input class="module-input" id="editDate" type="date" name="hwdate" value="<?= e($editing['homework_date']) ?>" required></div>
    <?php if ($authorLocked ?? false): ?>
      <div class="module-field"><label>Teacher</label><input class="module-input" type="text" value="<?= e($editing['teacher_name'] ?? '') ?>" readonly></div>
    <?php else: ?>
      <div class="module-field"><label for="editTeacher">Teacher</label><select class="module-input" id="editTeacher" name="hwteacher"><?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $editing['teacher_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?></select></div>
    <?php endif ?>
    <?php $selectedClasses = array_map('intval', explode(',', (string) ($editing['class_ids'] ?? $editing['class_id'] ?? ''))); $editAudience = $editing['audience_type'] ?? 'class'; ?>
    <div class="module-field"><label for="editAudience">Audience</label><select class="module-select" id="editAudience" name="audience_type"><?php foreach (['class'=>'One or more classes','all'=>'All students','student'=>'Specific student'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $editAudience === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
    <fieldset class="module-field field--wide" data-edit-classes><legend>Select classes</legend><div class="live-class-picker__options"><?php foreach($classes as $c): ?><label class="live-class-picker__option"><input type="checkbox" name="class_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int)$c['id'],$selectedClasses,true)?'checked':'' ?>><span><?= e($c['name']) ?></span></label><?php endforeach ?></div></fieldset>
    <div class="module-field" data-edit-student><label for="editStudent">Student</label><select class="module-select" id="editStudent" name="student_id"><option value="">-- Select student --</option><?php foreach($students as $student): ?><option value="<?= (int)$student['id'] ?>" <?= (int)($editing['student_id']??0)===(int)$student['id']?'selected':'' ?>><?= e($student['student_name'].' ? '.$student['registration_no'].' ? '.$student['class_name']) ?></option><?php endforeach ?></select></div>
    <div class="module-field"><label for="editSubject">Subject (optional)</label><select class="module-input" id="editSubject" name="hwsubject"><option value="">General</option><?php $seen=[];foreach($subjects as $subject):if(isset($seen[$subject['id']]))continue;$seen[$subject['id']]=true; ?><option value="<?= (int)$subject['id'] ?>" <?= (int)($editing['subject_id']??0)===(int)$subject['id']?'selected':'' ?>><?= e($subject['name']) ?></option><?php endforeach ?></select></div>
    <div class="module-field field--wide"><label for="editText">Homework</label><textarea class="module-input" id="editText" name="homework" rows="3" required><?= e($editing['homework_text']) ?></textarea></div>
    <div class="module-field"><label for="editFile">Replace attachment</label><input class="module-input" id="editFile" type="file" name="hwattachment" accept=".pdf,.jpg,.jpeg,.png"><?php if ($editing['attachment_path']): ?><small><a href="<?= e(url('/' . ltrim((string) $editing['attachment_path'], '/'))) ?>" target="_blank" rel="noopener">Current attachment</a></small><?php endif ?></div>
  </div>
  <div class="filter-actions"><button class="btn btn--primary" type="submit">Update Homework</button></div>
</form>
</div></section>
<?php endif ?>

<?php if ($canManage): ?>
<section class="module-card"><header class="module-card__header"><div><h2>Add Homework</h2><p>Choose the audience and an optional subject.</p></div></header><div class="module-card__body">
<form method="post" action="<?= e(url('/admin/homework')) ?>" enctype="multipart/form-data" class="module-form">
  <?= csrf_field() ?>
  <div class="module-form-grid module-form-grid--three">
    <div class="module-field"><label for="addDate">Date</label><input class="module-input" id="addDate" type="date" name="hwdate" value="<?= e(date('Y-m-d')) ?>" required></div>
    <?php if ($authorLocked ?? false): ?>
      <div class="module-field"><label>Teacher</label><input class="module-input" type="text" value="<?= e($authorName ?? 'You') ?>" readonly></div>
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

<?php endif ?>
<section class="module-card"><header class="module-card__header"><div><h2>Assigned Homework</h2><p><?= count($rows) ?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Homework</th><th>Attachment</th><?php if ($canManage): ?><th>Actions</th><?php endif ?></tr></thead><tbody>
<?php foreach ($rows as $h): ?><tr>
  <td><?= e($h['homework_date']) ?></td>
  <td><?=e(($h['audience_type']??'class')==='all'?'All students':(($h['audience_type']??'class')==='student'?($h['student_name']??'Student'):($h['class_names']??$h['class_name']??'Class')))?></td>
  <td><?= e($h['subject_name'] ?? '—') ?></td>
  <td><?= e($h['teacher_name'] ?? '—') ?></td>
  <td><?= e($h['homework_text']) ?></td>
  <td><?php if ($h['attachment_path']): ?><a href="<?= e(url('/' . ltrim((string) $h['attachment_path'], '/'))) ?>" target="_blank" rel="noopener">Open</a><?php else: ?>—<?php endif ?></td>
  <?php if ($canManage): ?><td>
    <a class="text-button" href="<?= e(url('/admin/homework?edit=' . (int)$h['id'])) ?>">Edit</a>
    <form method="post" action="<?= e(url('/admin/homework/' . (int)$h['id'] . '/delete')) ?>" class="inline-form"><?= csrf_field() ?><button class="text-button" type="submit" onclick="return confirm('Delete this homework?')">Delete</button></form>
  </td><?php endif ?>
</tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="7"><div class="compact-empty"><h3>No Homework</h3><p>No assignments match these filters.</p></div></td></tr><?php endif ?>
</tbody></table></div></div></section>
</div></div></main>
<?php if ($canManage): ?><script>
document.addEventListener('DOMContentLoaded', () => {
  [['homeworkAudience','[data-homework-classes]','[data-homework-student]'], ['editAudience','[data-edit-classes]','[data-edit-student]']].forEach(([id, classes, student]) => {
    const audience = document.getElementById(id);
    if (!audience) return;
    const classPicker = document.querySelector(classes), studentPicker = document.querySelector(student);
    const sync = () => {
      classPicker.hidden = audience.value !== 'class';
      studentPicker.hidden = audience.value !== 'student';
      studentPicker.querySelector('select').required = audience.value === 'student';
    };
    audience.addEventListener('change', sync);
    sync();
  });
});
</script><?php endif ?>
