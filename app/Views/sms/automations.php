<?php
$pageIntro = 'Queue SMS automatically when something happens in the LMS. Each event is sent once: saving attendance again or re-running reminders never sends duplicates.';
$canEdit = can('sms.settings.manage');
$toggleLabels = ['attendance_sms_enabled' => 'Attendance alerts', 'fee_sms_enabled' => 'Fee alerts', 'result_sms_enabled' => 'Result alerts'];
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>
<div class="sms-automations">
<?php foreach ($rules as $event => $rule): [$label, $trigger, , $toggle] = $events[$event]; $off = $toggle && empty($smsSettings[$toggle]); $c = $rule['conditions']; ?>
<form class="module-card sms-rule<?= $rule['enabled'] ? ' is-on' : '' ?>" method="post" action="<?= url('/admin/sms/automations/' . $event) ?>">
  <?= csrf_field() ?>
  <header class="module-card__header">
    <div><h2><?= e($label) ?></h2><p><i data-lucide="zap"></i><?= e($trigger) ?></p></div>
    <label class="sms-switch"><input type="checkbox" name="enabled" value="1" <?= $rule['enabled'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><span aria-hidden="true"></span><span class="sr-only">Enabled</span></label>
  </header>
  <div class="module-card__body">
    <?php if ($off): ?><div class="sms-notice sms-notice--warn"><i data-lucide="circle-pause"></i><div><?= e($toggleLabels[$toggle]) ?> are switched off in SMS Settings, so this automation will not run.</div></div><?php endif ?>
    <?php if ($rule['enabled'] && !$rule['template_active']): ?><div class="sms-notice sms-notice--warn"><i data-lucide="file-x"></i><div>The selected template is inactive, so this automation is paused.</div></div><?php endif ?>
    <div class="module-form-grid module-form-grid--two">
      <div class="module-field"><label>Recipient</label><input class="module-input" value="Parent / Guardian" disabled><small>Linked guardian first, then the guardian, father or mother mobile on the admission form, then the student's own mobile number.</small></div>
      <div class="module-field"><label for="tpl-<?= $event ?>">Template</label>
        <select class="module-select" id="tpl-<?= $event ?>" name="template_id" <?= $canEdit ? '' : 'disabled' ?>><?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $rule['template_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?><?= $t['is_active'] ? '' : ' (inactive)' ?></option><?php endforeach ?></select>
        <small>This SMS can use: <?= e('{{' . implode('}}, {{', \App\Services\Sms\SmsAutomation::variablesFor($event)) . '}}') ?></small>
      </div>
      <?php if ($event === 'attendance_absent'): ?>
      <div class="module-field field--wide"><label>Alert when a student is marked</label><div class="sms-checks"><?php foreach (['Absent', 'Late', 'Leave'] as $s): ?><label class="sms-radio"><input type="checkbox" name="statuses[]" value="<?= $s ?>" <?= in_array($s, $c['statuses'] ?? [], true) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>> <?= $s ?></label><?php endforeach ?></div>
        <small>Only today's register sends alerts. If a student is corrected to Present before the alert goes out, the alert is cancelled.</small></div>
      <?php elseif ($event === 'fee_due'): ?>
      <div class="module-field"><label for="db-<?= $event ?>">Remind this many days before the due date</label><input class="module-input" id="db-<?= $event ?>" type="number" min="0" max="30" name="days_before" value="<?= (int) $c['days_before'] ?>" <?= $canEdit ? '' : 'disabled' ?>><small>Needs the daily reminder job (see docs). One reminder per invoice.</small></div>
      <?php elseif ($event === 'fee_overdue'): ?>
      <div class="module-field"><label for="da-<?= $event ?>">Remind this many days after the due date</label><input class="module-input" id="da-<?= $event ?>" type="number" min="1" max="60" name="days_after" value="<?= (int) $c['days_after'] ?>" <?= $canEdit ? '' : 'disabled' ?>><small>Needs the daily reminder job. One overdue reminder per invoice.</small></div>
      <?php endif ?>
    </div>
  </div>
  <?php if ($canEdit): ?><footer class="module-card__footer sms-footer"><button class="btn btn--primary" type="submit"><i data-lucide="save"></i>Save</button></footer><?php endif ?>
</form>
<?php endforeach ?>
</div>
<section class="module-card">
  <header class="module-card__header"><div><h2>Announcements</h2><p>Announcement SMS is sent from the Announcements page: tick “Also send via SMS” when publishing.</p></div><a class="btn btn--secondary" href="<?= url('/admin/announcements') ?>">Open Announcements</a></header>
</section>
</div></div></main>
