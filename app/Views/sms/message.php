<?php
$m = $message;
$canManage = can('sms.queue.manage');
$pageIntro = match ($m['status']) {
    'queued' => 'Queued for the SMS gateway. It will be sent when a gateway device picks it up.',
    'scheduled' => 'Waiting for its scheduled time, then it joins the queue.',
    'reserved' => 'A gateway device has picked this message up.',
    'processing' => 'A gateway device is sending this message.',
    'sent' => 'A gateway device confirmed this message was sent.',
    'failed' => 'The gateway could not send this message.',
    default => 'This message was cancelled and will not be sent.',
};
$btn = static function (string $what, string $label, string $icon, string $class = 'btn--secondary', ?string $confirm = null) use ($m): string {
    return '<form method="post" action="' . e(url('/admin/sms/messages/' . $m['id'] . '/' . $what)) . '"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field()
        . '<button class="btn ' . $class . '" type="submit"><i data-lucide="' . $icon . '"></i>' . e($label) . '</button></form>';
};
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>
<div class="sms-grid">
<section class="module-card">
  <header class="module-card__header"><div><h2>Message</h2></div><?= sms_status_badge($m['status']) ?></header>
  <div class="module-card__body">
    <div class="sms-bubble sms-bubble--large"><?= nl2br(e($m['message'])) ?></div>
    <p class="sms-counter"><?= (int) $text['characters'] ?> characters · <?= (int) $text['segments'] ?> SMS segment<?= $text['segments'] === 1 ? '' : 's' ?><?= $text['unicode'] ? ' · Unicode' : '' ?></p>
    <?php if ($m['status'] === 'failed' || $m['failure_code']): ?>
    <div class="sms-notice sms-notice--error"><i data-lucide="circle-alert"></i><div><strong><?= e($m['failure_code'] ?? 'Failed') ?></strong> — <?= e($m['failure_reason'] ?? '') ?><?= $m['status'] === 'queued' ? ' A retry is scheduled.' : '' ?></div></div>
    <?php endif ?>
    <?php if ($m['status'] === 'scheduled' && $canManage): ?>
    <form class="sms-reschedule" method="post" action="<?= url('/admin/sms/messages/' . $m['id'] . '/reschedule') ?>" data-sms-compose>
      <?= csrf_field() ?>
      <h3 class="sms-subhead">Edit scheduled message</h3>
      <div class="module-field"><label for="rsMessage">Message</label><textarea class="module-textarea sms-textarea" id="rsMessage" name="message" rows="4" maxlength="1000" required data-sms-message><?= e($m['message']) ?></textarea><div class="sms-counter" data-sms-counter></div></div>
      <div class="module-field"><label for="rsWhen">Send at</label><input class="module-input" id="rsWhen" type="datetime-local" name="scheduled_at" min="<?= date('Y-m-d\TH:i') ?>" value="<?= e(date('Y-m-d\TH:i', strtotime($m['scheduled_at']))) ?>" required></div>
      <button class="btn btn--primary" type="submit"><i data-lucide="save"></i>Save changes</button>
    </form>
    <?php endif ?>
  </div>
  <?php if ($canManage): ?>
  <footer class="module-card__footer sms-footer">
    <?php if (in_array($m['status'], ['failed', 'cancelled'], true)): ?><?= $btn($m['status'] === 'failed' ? 'retry' : 'requeue', $m['status'] === 'failed' ? 'Retry' : 'Requeue', 'rotate-ccw', 'btn--primary') ?><?php endif ?>
    <?php if (in_array($m['status'], ['queued', 'scheduled', 'failed'], true)): ?><?= $btn('cancel', 'Cancel', 'ban', 'btn--secondary', 'Cancel this message?') ?><?php endif ?>
    <?php if (in_array($m['status'], ['queued', 'scheduled', 'failed', 'cancelled'], true)): ?><?= $btn('delete', 'Delete', 'trash-2', 'btn--danger', 'Delete this message permanently?') ?><?php endif ?>
  </footer>
  <?php endif ?>
</section>
<aside class="sms-side">
  <section class="module-card">
    <header class="module-card__header"><div><h2>Details</h2></div></header>
    <div class="module-card__body sms-kv">
      <div><span>Recipient</span><strong><?= e($m['recipient_name'] ?: 'Manual number') ?></strong></div>
      <div><span>Recipient type</span><strong><?= e(sms_label($m['recipient_type'])) ?></strong></div>
      <div><span>Phone</span><strong><?= e($m['phone_number']) ?></strong></div>
      <div><span>Type</span><strong><?= e(sms_label($m['message_type'])) ?></strong></div>
      <div><span>Source</span><strong><?= e(sms_label($m['source'])) ?></strong></div>
      <div><span>Priority</span><strong><?= e(ucfirst($m['priority'])) ?></strong></div>
      <div><span>Template</span><strong><?= e($m['template_name'] ?? '—') ?></strong></div>
      <div><span>Attempts</span><strong><?= (int) $m['retry_count'] ?> of <?= (int) $m['max_retries'] ?> retries</strong></div>
      <div><span>Created</span><strong><?= sms_time($m['created_at']) ?></strong></div>
      <div><span>Created by</span><strong><?= e($m['created_by_name'] ?? 'Automation') ?></strong></div>
      <?php if ($m['scheduled_at']): ?><div><span>Scheduled for</span><strong><?= sms_time($m['scheduled_at']) ?></strong></div><?php endif ?>
      <div><span>Gateway device</span><strong><?= e($m['device_name'] ?? '—') ?></strong></div>
      <?php if ($m['sim_slot']): ?><div><span>SIM slot</span><strong><?= (int) $m['sim_slot'] ?></strong></div><?php endif ?>
      <div><span>Sent</span><strong><?= sms_time($m['sent_at']) ?></strong></div>
      <?php if ($m['external_reference']): ?><div><span>Device reference</span><strong><?= e($m['external_reference']) ?></strong></div><?php endif ?>
    </div>
  </section>
  <?php if (can('sms.logs.view')): ?>
  <section class="module-card">
    <header class="module-card__header"><div><h2>History</h2></div></header>
    <div class="module-card__body">
      <?php if (!$logs): ?><p class="sms-muted">No history yet.</p><?php else: ?>
      <ol class="sms-timeline"><?php foreach ($logs as $l): $d = json_decode((string) $l['details_json'], true) ?: []; ?>
        <li><strong><?= e(sms_label($l['event'])) ?></strong><span><?= e($l['actor_name'] ?? sms_label($l['actor_type'])) ?> · <?= e(date('d M, h:i:s A', strtotime($l['created_at']))) ?></span><?php if (!empty($d['code'])): ?><code class="sms-code"><?= e($d['code']) ?></code><?php endif ?><?php if (!empty($d['reason'])): ?><small><?= e($d['reason']) ?></small><?php endif ?></li>
      <?php endforeach ?></ol>
      <?php endif ?>
    </div>
  </section>
  <?php endif ?>
</aside>
</div>
</div></div></main>
