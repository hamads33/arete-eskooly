<?php
$pageIntro = 'Queue one SMS per recipient for a whole group. Nothing is queued until you review the recipients and confirm.';
$f = $input;
$validCount = $preview ? count($preview['valid']) : 0;
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>

<form class="sms-bulk" method="get" action="<?= url('/admin/sms/bulk') ?>" data-sms-compose data-sms-bulk>
<input type="hidden" name="preview" value="1">
<div class="sms-grid">
<section class="module-card">
  <header class="module-card__header"><div><h2>1. Recipients</h2></div></header>
  <div class="module-card__body">
    <div class="module-form-grid module-form-grid--two">
      <div class="module-field">
        <label for="bulkAudience">Send to</label>
        <select class="module-select" id="bulkAudience" name="audience" data-bulk-audience>
          <?php foreach (['parents' => 'Parents / guardians', 'students' => 'Students', 'staff' => 'Staff'] as $v => $l): ?><option value="<?= $v ?>" <?= $f['audience'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="module-field">
        <label for="bulkFilter">Who</label>
        <select class="module-select" id="bulkFilter" name="filter" data-bulk-filter>
          <?php foreach ($filters as $v => $l): ?><option value="<?= $v ?>" <?= $f['filter'] === $v ? 'selected' : '' ?> <?= $v !== 'all' ? 'data-student-only' : '' ?>><?= e($l) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="module-field" data-bulk-for="class">
        <label for="bulkClass">Class</label>
        <select class="module-select" id="bulkClass" name="class_id"><option value="">Select class</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select>
      </div>
      <div class="module-field" data-bulk-for="section">
        <label for="bulkSection">Section</label>
        <select class="module-select" id="bulkSection" name="section_id"><option value="">Select section</option><?php foreach ($sections as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $f['section_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['class_name'] . ' – ' . $s['name']) ?></option><?php endforeach ?></select>
        <?php if (!$sections): ?><small>No sections have been set up yet.</small><?php endif ?>
      </div>
      <div class="module-field" data-bulk-for="attendance">
        <label for="bulkDate">Attendance date</label>
        <input class="module-input" id="bulkDate" type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= e($f['date']) ?>">
      </div>
      <div class="module-field" data-bulk-for="attendance">
        <label for="bulkStatus">Attendance status</label>
        <select class="module-select" id="bulkStatus" name="attendance_status"><?php foreach (['Absent', 'Late', 'Leave', 'Present'] as $st): ?><option <?= $f['attendance_status'] === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach ?></select>
      </div>
      <div class="module-field" data-bulk-for="exam">
        <label for="bulkExam">Exam</label>
        <select class="module-select" id="bulkExam" name="exam_id"><option value="">Select exam</option><?php foreach ($exams as $x): ?><option value="<?= (int) $x['id'] ?>" <?= $f['exam_id'] === (int) $x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?> (<?= e(sms_label($x['status'])) ?>)</option><?php endforeach ?></select>
        <small>Adds {{exam_name}}, {{marks}} and {{percentage}} for each student.</small>
      </div>
    </div>
    <p class="sms-hint" data-bulk-hint-fee <?= $f['filter'] === 'fee_outstanding' ? '' : 'hidden' ?>>Adds {{fee_amount}}, {{fee_month}} and {{due_date}} for each student.</p>
  </div>
</section>

<section class="module-card">
  <header class="module-card__header"><div><h2>2. Message</h2></div></header>
  <div class="module-card__body">
    <div class="module-form-grid module-form-grid--two">
      <div class="module-field">
        <label for="bulkTemplate">Template</label>
        <select class="module-select" id="bulkTemplate" name="template_id" data-sms-template>
          <option value="">No template</option>
          <?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>" data-body="<?= e($t['message_body']) ?>" <?= $f['template_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="module-field">
        <label for="bulkPriority">Priority</label>
        <select class="module-select" id="bulkPriority" name="priority"><?php foreach (['high' => 'High', 'normal' => 'Normal', 'low' => 'Low'] as $v => $l): ?><option value="<?= $v ?>" <?= ($f['priority'] ?: $smsSettings['default_priority']) === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select>
      </div>
    </div>
    <div class="module-field">
      <label for="bulkMessage">Message</label>
      <textarea class="module-textarea sms-textarea" id="bulkMessage" name="message" rows="5" maxlength="1000" required data-sms-message><?= e($f['message']) ?></textarea>
      <div class="sms-counter" data-sms-counter aria-live="polite"></div>
    </div>
    <details class="sms-vars">
      <summary>Insert a variable</summary>
      <div class="sms-vars__list"><?php foreach ($variables as $name => $desc): ?><button type="button" class="sms-chip" data-sms-insert="{{<?= e($name) ?>}}" title="<?= e($desc) ?>">{{<?= e($name) ?>}}</button><?php endforeach ?></div>
    </details>
    <fieldset class="sms-when">
      <legend>When</legend>
      <label class="sms-radio"><input type="radio" name="send_mode" value="now" <?= $f['send_mode'] !== 'schedule' ? 'checked' : '' ?> data-sms-mode> Queue now</label>
      <label class="sms-radio"><input type="radio" name="send_mode" value="schedule" <?= $f['send_mode'] === 'schedule' ? 'checked' : '' ?> data-sms-mode> Schedule</label>
      <input class="module-input sms-when__time" type="datetime-local" name="scheduled_at" min="<?= date('Y-m-d\TH:i') ?>" value="<?= e($f['scheduled_at']) ?>" data-sms-schedule <?= $f['send_mode'] === 'schedule' ? '' : 'hidden' ?>>
    </fieldset>
  </div>
  <footer class="module-card__footer sms-footer"><button class="btn btn--primary" type="submit"><i data-lucide="list-checks"></i>Preview Recipients</button></footer>
</section>
</div>
</form>

<?php if ($previewError): ?>
<div class="sms-notice sms-notice--error"><i data-lucide="circle-alert"></i><div><?= e($previewError) ?></div></div>
<?php elseif ($preview): ?>
<section class="module-card sms-review" id="review">
  <header class="module-card__header"><div><h2>3. Review and queue</h2><p>One SMS is queued per recipient, each with its own status and retry history.</p></div></header>
  <div class="module-card__body">
    <div class="sms-stats sms-stats--compact">
      <div class="sms-stat"><span class="sms-stat__body"><span>Selected recipients</span><strong><?= (int) $preview['total'] ?></strong></span></div>
      <div class="sms-stat sms-stat--good"><span class="sms-stat__body"><span>Will be queued</span><strong><?= $validCount ?></strong></span></div>
      <div class="sms-stat sms-stat--warn"><span class="sms-stat__body"><span>Skipped</span><strong><?= count($preview['skipped']) ?></strong></span></div>
      <?php if ($preview['duplicates']): ?><div class="sms-stat"><span class="sms-stat__body"><span>Same parent, same text (merged)</span><strong><?= (int) $preview['duplicates'] ?></strong></span></div><?php endif ?>
    </div>
    <?php if ($smsSettings['daily_limit']): ?><p class="sms-hint">Daily limit: <?= (int) $usage['today'] ?> of <?= (int) $smsSettings['daily_limit'] ?> used today.</p><?php endif ?>

    <?php if ($preview['skipped']): ?>
    <details class="sms-skipped">
      <summary><?= count($preview['skipped']) ?> recipients will be skipped — see why</summary>
      <div class="table-scroll"><table class="data-table sms-table"><thead><tr><th>Name</th><th>Details</th><th>Phone on record</th><th>Reason</th></tr></thead><tbody>
      <?php foreach ($preview['skipped'] as $s): ?><tr><td data-label="Name"><?= e($s['name']) ?></td><td data-label="Details"><?= e($s['detail']) ?></td><td data-label="Phone"><?= e($s['phone'] ?? '—') ?></td><td data-label="Reason"><?= e($s['reason']) ?></td></tr><?php endforeach ?>
      </tbody></table></div>
    </details>
    <?php endif ?>

    <?php if ($validCount): ?>
    <h3 class="sms-subhead">Sample messages</h3>
    <div class="sms-samples">
      <?php foreach (array_slice($preview['valid'], 0, 3) as $v): ?>
      <div class="sms-sample"><div class="sms-sample__to"><strong><?= e($v['name']) ?></strong> · <?= e($v['phone']) ?></div><div class="sms-bubble"><?= nl2br(e($v['message'])) ?></div></div>
      <?php endforeach ?>
    </div>
    <form method="post" action="<?= url('/admin/sms/bulk') ?>" class="sms-confirm">
      <?= csrf_field() ?>
      <?php foreach (['audience', 'filter', 'class_id', 'section_id', 'date', 'attendance_status', 'exam_id', 'template_id', 'message', 'priority', 'send_mode', 'scheduled_at'] as $k): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string) $f[$k]) ?>"><?php endforeach ?>
      <?php if ($validCount >= $confirmThreshold): ?>
      <label class="checkbox-row"><input type="checkbox" name="confirm" value="1" required><span><strong>Yes, queue <?= $validCount ?> messages</strong><small>Large batches cannot be recalled once a gateway starts sending them.</small></span></label>
      <?php endif ?>
      <button class="btn btn--primary" type="submit"><i data-lucide="send"></i><?= $f['send_mode'] === 'schedule' ? 'Schedule ' . $validCount . ' SMS' : 'Queue ' . $validCount . ' SMS' ?></button>
    </form>
    <?php else: ?>
    <div class="compact-empty sms-empty"><i data-lucide="user-x"></i><h3>Nobody to message</h3><p>None of the selected recipients has a usable phone number and complete details for this message.</p></div>
    <?php endif ?>
  </div>
</section>
<?php endif ?>
</div></div></main>
