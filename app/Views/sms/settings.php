<?php
$pageIntro = 'Configure how the SMS queue and the Android SIM gateway behave.';
$s = $smsSettings;
$check = static fn(string $name, string $label, string $help = '') => '<label class="checkbox-row"><input type="checkbox" name="' . $name . '" value="1" ' . (!empty($s[$name]) ? 'checked' : '') . '><span><strong>' . e($label) . '</strong>' . ($help ? '<small>' . e($help) . '</small>' : '') . '</span></label>';
$num = static fn(string $name, string $label, int $min, int $max, string $help = '') => '<div class="module-field"><label for="set-' . $name . '">' . e($label) . '</label><input class="module-input" id="set-' . $name . '" type="number" name="' . $name . '" min="' . $min . '" max="' . $max . '" value="' . (int) $s[$name] . '">' . ($help ? '<small>' . e($help) . '</small>' : '') . '</div>';
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>
<form method="post" action="<?= url('/admin/sms/settings') ?>" class="sms-settings">
<?= csrf_field() ?>
<section class="module-card">
  <header class="module-card__header"><div><h2>General</h2></div></header>
  <div class="module-card__body">
    <?= $check('sms_enabled', 'Enable SMS module', 'When off, nothing new can be queued and gateways receive no messages.') ?>
    <div class="module-form-grid module-form-grid--two">
      <div class="module-field"><label for="set-cc">Default country code</label><input class="module-input" id="set-cc" name="default_country_code" inputmode="numeric" maxlength="4" value="<?= e($s['default_country_code']) ?>"><small>Used for numbers written without one, e.g. 03001234567 becomes +<?= e($s['default_country_code']) ?>3001234567.</small></div>
      <div class="module-field"><label>Sender</label><input class="module-input" value="Android SIM Gateway" disabled><small>Messages are sent by a paired Android phone using the institute's SIM.</small></div>
    </div>
    <?= $check('allow_manual_sms', 'Allow manual SMS (Compose)') ?>
    <?= $check('allow_bulk_sms', 'Allow bulk SMS') ?>
  </div>
</section>
<section class="module-card">
  <header class="module-card__header"><div><h2>Queue</h2></div></header>
  <div class="module-card__body">
    <?= $check('queue_processing_enabled', 'Hand queued messages to gateways', 'Turn off to pause sending without turning the module off; messages keep queuing.') ?>
    <div class="module-form-grid module-form-grid--three">
      <?= $num('batch_size', 'Batch size', 1, 100, 'Most messages one device receives per request.') ?>
      <?= $num('max_retries', 'Max retries', 0, 10, 'For temporary errors such as no signal.') ?>
      <?= $num('retry_delay_minutes', 'Retry delay (minutes)', 1, 1440, 'Grows with each attempt.') ?>
      <div class="module-field"><label for="set-priority">Default priority</label><select class="module-select" id="set-priority" name="default_priority"><?php foreach (['high' => 'High', 'normal' => 'Normal', 'low' => 'Low'] as $v => $l): ?><option value="<?= $v ?>" <?= $s['default_priority'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
      <?= $num('reservation_timeout_minutes', 'Reservation timeout (minutes)', 2, 240, 'Messages a silent device picked up return to the queue after this.') ?>
    </div>
  </div>
</section>
<section class="module-card">
  <header class="module-card__header"><div><h2>Limits</h2></div></header>
  <div class="module-card__body"><div class="module-form-grid module-form-grid--two">
    <?= $num('daily_limit', 'Daily SMS limit', 0, 1000000, '0 means no limit. Counts every message queued today (cancelled excluded).') ?>
    <?= $num('monthly_limit', 'Monthly SMS limit', 0, 10000000, '0 means no limit.') ?>
  </div></div>
</section>
<section class="module-card">
  <header class="module-card__header"><div><h2>Quiet Hours</h2></div></header>
  <div class="module-card__body">
    <?= $check('quiet_hours_enabled', 'Enable quiet hours', 'Gateways receive no messages during these hours; queued messages wait until quiet hours end.') ?>
    <div class="module-form-grid module-form-grid--two">
      <div class="module-field"><label for="set-qs">Start</label><input class="module-input" id="set-qs" type="time" name="quiet_hours_start" value="<?= e($s['quiet_hours_start']) ?>"></div>
      <div class="module-field"><label for="set-qe">End</label><input class="module-input" id="set-qe" type="time" name="quiet_hours_end" value="<?= e($s['quiet_hours_end']) ?>"></div>
    </div>
  </div>
</section>
<section class="module-card">
  <header class="module-card__header"><div><h2>Automation</h2><p>Master switches; each automation is also turned on individually under Automations.</p></div></header>
  <div class="module-card__body">
    <?= $check('attendance_sms_enabled', 'Attendance alerts') ?>
    <?= $check('fee_sms_enabled', 'Fee alerts', 'Due and overdue reminders, and payment confirmations.') ?>
    <?= $check('result_sms_enabled', 'Result alerts') ?>
    <?= $check('announcement_sms_enabled', 'Announcement SMS', 'Shows “Also send via SMS” on the Announcements page.') ?>
  </div>
</section>
<section class="module-card">
  <header class="module-card__header"><div><h2>Gateway</h2></div></header>
  <div class="module-card__body"><div class="module-form-grid module-form-grid--two">
    <?= $num('offline_timeout_minutes', 'Offline timeout (minutes)', 1, 1440, 'A device with no heartbeat for this long is shown as offline.') ?>
    <?= $num('heartbeat_interval_seconds', 'Heartbeat interval (seconds)', 15, 3600, 'Recommended to the bridge app; keep it well below the offline timeout.') ?>
    <div class="module-field"><label for="set-dev">Default device</label><select class="module-select" id="set-dev" name="default_device_id"><option value="0">Any connected device</option><?php foreach ($devices as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $s['default_device_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['device_name']) ?></option><?php endforeach ?></select><small>Shown to the bridge app for reference; any active, paired device may send.</small></div>
    <div class="module-field"><label for="set-sim">Default SIM slot</label><select class="module-select" id="set-sim" name="default_sim_slot"><option value="0">Phone default</option><?php foreach ([1, 2] as $slot): ?><option value="<?= $slot ?>" <?= (int) $s['default_sim_slot'] === $slot ? 'selected' : '' ?>>SIM <?= $slot ?></option><?php endforeach ?></select><small>Used when a device has no SIM slot of its own set.</small></div>
  </div></div>
</section>
<div class="sms-sticky-actions"><button class="btn btn--primary" type="submit"><i data-lucide="save"></i>Save Settings</button></div>
</form>
</div></div></main>
