<?php
/**
 * Schedule / edit a live class (admin). One form serves both: $meeting is null when creating.
 *
 * The class, section, subject and student pickers are conveniences only — every one of these
 * values is re-validated server-side in LiveClassService::normalise() and, for teachers, against
 * teacher_assignments, so a hand-edited POST gains nothing.
 */
$editing = $meeting !== null;
$action = $editing ? url('/admin/live-classes/'.$meeting['id']) : url('/admin/live-classes');
$audience = $editing ? (string) $meeting['audience_type'] : 'SC';
$startDate = $editing ? date('Y-m-d', strtotime((string) $meeting['local_start'])) : date('Y-m-d');
$startTime = $editing ? date('H:i', strtotime((string) $meeting['local_start'])) : '';
$participants = $participants ?? [];
?>
<main class="main"><div class="main__inner"><div class="admin-page">
  <header class="admin-page__header">
    <div><span class="module-eyebrow">Online learning</span><h1><?= $editing?'Edit Live Class':'Schedule Live Class' ?></h1><p>Times are entered and shown in <?= e($timezone) ?>.</p></div>
    <a class="btn btn--ghost" href="<?= url('/admin/live-classes') ?>"><i data-lucide="arrow-left"></i>Back to live classes</a>
  </header>

  <?php if(($connection['status'] ?? '')!=='connected'):?>
    <div class="module-card"><div class="module-card__body"><p class="live-connection__status live-connection__status--error">Google Calendar is not connected. Connect the institute Google account first.</p></div></div>
  <?php endif?>

  <form class="module-card" method="post" action="<?= e($action) ?>"><?= csrf_field() ?>
    <div class="module-card__body form-grid">
      <label class="field field--wide"><span>Title <em>*</em></span>
        <input class="form-input" type="text" name="title" maxlength="160" required value="<?= e(old('title', $editing?$meeting['title']:'')) ?>">
      </label>

      <label class="field field--wide"><span>Description</span>
        <textarea class="form-input" name="description" rows="3"><?= e(old('description', $editing?($meeting['description']??''):'')) ?></textarea>
      </label>

      <label class="field"><span>Class <em>*</em></span>
        <select class="form-select" name="class_id" required data-class-source data-reload-url="<?= url('/admin/live-classes/create') ?>">
          <option value="">Select class</option>
          <?php foreach($classes as $c):?><option value="<?= e($c['id']) ?>" <?= (int)old('class_id',$selectedClass)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach?>
        </select>
      </label>

      <label class="field"><span>Subject</span>
        <select class="form-select" name="subject_id">
          <option value="">No specific subject</option>
          <?php foreach($subjects as $s):?><option value="<?= e($s['id']) ?>" <?= $editing&&(int)$meeting['subject_id']===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach?>
        </select>
      </label>

      <label class="field"><span>Audience <em>*</em></span>
        <select class="form-select" name="audience_type" data-audience>
          <option value="SC" <?= $audience==='SC'?'selected':'' ?>>Whole class</option>
          <option value="SEC" <?= $audience==='SEC'?'selected':'' ?>>One section</option>
          <option value="SEL" <?= $audience==='SEL'?'selected':'' ?>>Selected students</option>
        </select>
      </label>

      <label class="field" data-audience-field="SEC"><span>Section</span>
        <select class="form-select" name="section_id">
          <option value="">Select section</option>
          <?php foreach($sections as $s):?><option value="<?= e($s['id']) ?>" <?= $editing&&(int)$meeting['section_id']===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach?>
        </select>
      </label>

      <fieldset class="field field--wide" data-audience-field="SEL">
        <legend>Students</legend>
        <?php if(!$students):?><p class="field__hint">Choose a class first to list its students.</p>
        <?php else:?><div class="live-student-picker">
          <?php foreach($students as $s):?>
            <label class="live-student-picker__item"><input type="checkbox" name="students[]" value="<?= e($s['id']) ?>" <?= in_array((int)$s['id'],$participants,true)?'checked':'' ?>><span><?= e($s['student_name']) ?> <em><?= e($s['registration_no']) ?></em></span></label>
          <?php endforeach?>
        </div><?php endif?>
      </fieldset>

      <label class="field"><span>Date <em>*</em></span><input class="form-input" type="date" name="date" required value="<?= e(old('date',$startDate)) ?>"></label>
      <label class="field"><span>Start time <em>*</em></span><input class="form-input" type="time" name="time" required value="<?= e(old('time',$startTime)) ?>"></label>
      <label class="field"><span>Duration <em>*</em></span>
        <?php $duration = $editing ? (int) $meeting['duration_minutes'] : 60; ?>
        <select class="form-select" name="duration" required>
          <?php foreach([30,45,60,90] as $option):?><option value="<?= $option ?>" <?= $duration===$option?'selected':'' ?>><?= $option ?> minutes</option><?php endforeach?>
          <?php if(!in_array($duration,[30,45,60,90],true)):?><option value="<?= e($duration) ?>" selected><?= e($duration) ?> minutes</option><?php endif?>
        </select>
      </label>
    </div>
    <div class="module-card__footer">
      <button class="btn btn--primary" type="submit"><i data-lucide="calendar-check"></i><?= $editing?'Save changes':'Schedule live class' ?></button>
      <a class="btn btn--ghost" href="<?= url('/admin/live-classes') ?>">Cancel</a>
    </div>
  </form>
</div></div></main>
