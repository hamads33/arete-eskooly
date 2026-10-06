<?php
$pageIntro = 'Queue a single SMS. It is sent when a gateway device picks it up.';
$type = $old['recipient_type'] ?? 'guardian';
$mode = $old['send_mode'] ?? 'now';
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>

<form class="sms-compose" method="post" action="<?= url('/admin/sms/compose') ?>" data-sms-compose data-search-url="<?= e(url('/admin/sms/recipients')) ?>">
<?= csrf_field() ?>
<section class="module-card">
  <header class="module-card__header"><div><h2>Recipient</h2></div></header>
  <div class="module-card__body">
    <div class="sms-segment" role="radiogroup" aria-label="Recipient type">
      <?php foreach (['student' => ['Student', 'graduation-cap'], 'guardian' => ['Parent / Guardian', 'users-round'], 'staff' => ['Staff', 'briefcase-business'], 'manual' => ['Manual Number', 'hash']] as $value => [$label, $icon]): ?>
      <label class="sms-segment__option"><input type="radio" name="recipient_type" value="<?= $value ?>" <?= $type === $value ? 'checked' : '' ?> data-sms-type><span><i data-lucide="<?= $icon ?>"></i><?= e($label) ?></span></label>
      <?php endforeach ?>
    </div>
    <div class="module-form-grid module-form-grid--two sms-recipient-grid">
      <div class="module-field sms-autocomplete" data-sms-picker <?= $type === 'manual' ? 'hidden' : '' ?>>
        <label for="smsRecipientSearch">Recipient</label>
        <input class="module-input" id="smsRecipientSearch" name="recipient_label" autocomplete="off" placeholder="Type a name, registration or employee no." value="<?= e($old['recipient_label'] ?? '') ?>" data-sms-search aria-autocomplete="list" aria-controls="smsRecipientList">
        <input type="hidden" name="recipient_id" value="<?= (int) ($old['recipient_id'] ?? 0) ?>" data-sms-recipient-id>
        <ul class="sms-suggest" id="smsRecipientList" role="listbox" hidden data-sms-suggest></ul>
        <small data-sms-picker-hint>Parents are found by their child's name.</small>
      </div>
      <div class="module-field" data-sms-manual-name <?= $type === 'manual' ? '' : 'hidden' ?>>
        <label for="smsManualName">Name <small>(optional)</small></label>
        <input class="module-input" id="smsManualName" name="recipient_name" maxlength="160" value="<?= e($old['recipient_name'] ?? '') ?>" placeholder="Who is this for?">
      </div>
      <div class="module-field">
        <label for="smsPhone">Phone Number</label>
        <input class="module-input" id="smsPhone" name="phone_number" inputmode="tel" maxlength="20" value="<?= e($old['phone_number'] ?? '') ?>" placeholder="03001234567" data-sms-phone>
        <small data-sms-phone-hint>Filled from the recipient's record. Numbers are normalised to +<?= e($smsSettings['default_country_code']) ?> format.</small>
      </div>
    </div>
  </div>
</section>

<div class="sms-grid">
<section class="module-card">
  <header class="module-card__header"><div><h2>Message</h2></div></header>
  <div class="module-card__body">
    <div class="module-form-grid module-form-grid--two">
      <div class="module-field">
        <label for="smsTemplate">Template</label>
        <select class="module-select" id="smsTemplate" name="template_id" data-sms-template>
          <option value="">No template</option>
          <?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>" data-body="<?= e($t['message_body']) ?>" <?= (int) ($old['template_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="module-field">
        <label for="smsPriority">Priority</label>
        <select class="module-select" id="smsPriority" name="priority">
          <?php foreach (['high' => 'High', 'normal' => 'Normal', 'low' => 'Low'] as $v => $l): ?><option value="<?= $v ?>" <?= ($old['priority'] ?? $smsSettings['default_priority']) === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
        </select>
      </div>
    </div>
    <div class="module-field">
      <label for="smsMessage">Message</label>
      <textarea class="module-textarea sms-textarea" id="smsMessage" name="message" rows="6" maxlength="1000" required data-sms-message><?= e($old['message'] ?? '') ?></textarea>
      <div class="sms-counter" data-sms-counter aria-live="polite"></div>
    </div>
    <details class="sms-vars">
      <summary>Insert a variable</summary>
      <div class="sms-vars__list"><?php foreach ($variables as $name => $desc): ?><button type="button" class="sms-chip" data-sms-insert="{{<?= e($name) ?>}}" title="<?= e($desc) ?>">{{<?= e($name) ?>}}</button><?php endforeach ?></div>
      <small>Variables are filled from the selected recipient's record. A variable with no value for this recipient stops the message from being queued.</small>
    </details>
    <fieldset class="sms-when">
      <legend>When</legend>
      <label class="sms-radio"><input type="radio" name="send_mode" value="now" <?= $mode !== 'schedule' ? 'checked' : '' ?> data-sms-mode> Queue now</label>
      <label class="sms-radio"><input type="radio" name="send_mode" value="schedule" <?= $mode === 'schedule' ? 'checked' : '' ?> data-sms-mode> Schedule</label>
      <input class="module-input sms-when__time" type="datetime-local" name="scheduled_at" min="<?= date('Y-m-d\TH:i') ?>" value="<?= e($old['scheduled_at'] ?? '') ?>" data-sms-schedule <?= $mode === 'schedule' ? '' : 'hidden' ?>>
    </fieldset>
  </div>
  <footer class="module-card__footer sms-footer">
    <button class="btn btn--secondary" type="reset" data-sms-clear>Clear</button>
    <button class="btn btn--primary" type="submit" data-sms-submit><i data-lucide="send"></i><span data-sms-submit-label>Send to Queue</span></button>
  </footer>
</section>

<aside class="sms-side">
  <section class="module-card sms-preview">
    <header class="module-card__header"><div><h2>Preview</h2></div></header>
    <div class="module-card__body">
      <dl class="sms-preview__meta"><dt>To</dt><dd data-sms-preview-to>—</dd><dt>Phone</dt><dd data-sms-preview-phone>—</dd></dl>
      <div class="sms-bubble" data-sms-preview-text><span class="sms-muted">Your message will appear here.</span></div>
      <p class="sms-preview__note"><i data-lucide="info"></i>Variables are filled in when the message is queued. The SMS is sent once a gateway device picks it up.</p>
    </div>
  </section>
</aside>
</div>
</form>
</div></div></main>
