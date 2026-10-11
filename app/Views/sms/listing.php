<?php
$pageIntro = $intro;
$canManage = can('sms.queue.manage');
$queryString = preg_replace('/[^A-Za-z0-9_=&%\-.]/', '', (string) ($_SERVER['QUERY_STRING'] ?? '')) ?? '';
$return = '/admin/sms/' . $listView . ($queryString !== '' ? '?' . $queryString : '');
$action = static function (int $id, string $what, string $label, string $icon, string $class = 'btn--secondary', ?string $confirm = null) use ($return): string {
    return '<form method="post" action="' . e(url('/admin/sms/messages/' . $id . '/' . $what)) . '"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="return" value="' . e($return) . '"><button class="btn ' . $class . ' btn--sm" type="submit"><i data-lucide="' . $icon . '"></i>' . e($label) . '</button></form>';
};
$empty = [
    'queue' => ['inbox', 'No SMS messages have been queued yet.', 'Messages you compose, bulk sends and automations appear here.'],
    'sent' => ['check-check', 'No messages have been sent yet.', 'A message shows here once a gateway device confirms it was sent.'],
    'failed' => ['circle-check-big', 'No failed messages.', 'Messages the gateway could not send will appear here for retry.'],
    'scheduled' => ['calendar-clock', 'No scheduled messages.', 'Choose "Schedule" when composing to send later.'],
][$listView];
$hasFilters = array_filter($filters) || $batch;
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>

<form class="filter-bar filter-bar--compact sms-filters" method="get">
  <div class="module-field"><label for="fQ">Recipient</label><input class="module-input" id="fQ" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, phone or text"></div>
  <?php if ($listView === 'queue'): ?>
  <div class="module-field"><label for="fStatus">Status</label><select class="module-select" id="fStatus" name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(sms_label($s)) ?></option><?php endforeach ?></select></div>
  <?php endif ?>
  <div class="module-field"><label for="fType">Type</label><select class="module-select" id="fType" name="type"><option value="">All types</option><?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(sms_label($t)) ?></option><?php endforeach ?></select></div>
  <div class="module-field"><label for="fSource">Source</label><select class="module-select" id="fSource" name="source"><option value="">All sources</option><?php foreach ($sources as $s): ?><option value="<?= $s ?>" <?= $filters['source'] === $s ? 'selected' : '' ?>><?= e(sms_label($s)) ?></option><?php endforeach ?></select></div>
  <?php if ($devices): ?><div class="module-field"><label for="fDevice">Device</label><select class="module-select" id="fDevice" name="device"><option value="">All devices</option><?php foreach ($devices as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $filters['device'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['device_name']) ?></option><?php endforeach ?></select></div><?php endif ?>
  <div class="module-field"><label for="fFrom">From</label><input class="module-input" id="fFrom" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
  <div class="module-field"><label for="fTo">To</label><input class="module-input" id="fTo" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
  <div class="filter-actions"><button class="btn btn--primary">Filter</button><?php if ($hasFilters): ?><a class="btn btn--secondary" href="<?= url('/admin/sms/' . $listView) ?>">Reset</a><?php endif ?></div>
</form>
<?php if ($batch): ?><div class="sms-notice"><i data-lucide="layers"></i><div>Showing one bulk batch (<?= (int) $total ?> messages). <a href="<?= url('/admin/sms/queue') ?>">Show all messages</a></div></div><?php endif ?>

<section class="module-card">
  <div class="module-card__body">
  <?php if (!$rows): ?>
    <div class="compact-empty sms-empty"><i data-lucide="<?= $empty[0] ?>"></i><h2><?= $hasFilters ? 'No messages match these filters.' : e($empty[1]) ?></h2><p><?= $hasFilters ? 'Try widening the filters.' : e($empty[2]) ?></p></div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table sms-table">
      <thead><tr>
        <?php if ($listView === 'queue'): ?><th>ID</th><th>Recipient</th><th>Phone</th><th>Type</th><th>Message</th><th>Priority</th><th>Status</th><th>Attempts</th><th>Scheduled</th><th>Created</th><th>Device</th>
        <?php elseif ($listView === 'sent'): ?><th>Recipient</th><th>Phone</th><th>Message</th><th>Source</th><th>Device</th><th>Sent At</th>
        <?php elseif ($listView === 'failed'): ?><th>Recipient</th><th>Phone</th><th>Message</th><th>Failure Reason</th><th>Attempts</th><th>Device</th><th>Failed At</th>
        <?php else: ?><th>Recipient</th><th>Message</th><th>Scheduled At</th><th>Created By</th><?php endif ?>
        <th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $m): $id = (int) $m['id']; $who = '<a href="' . e(url('/admin/sms/messages/' . $id)) . '"><strong>' . e($m['recipient_name'] ?: 'Manual number') . '</strong></a><small class="sms-sub">' . e(sms_label($m['recipient_type'])) . '</small>'; $snippet = '<span class="sms-snippet">' . e(mb_strimwidth($m['message'], 0, 70, '…')) . '</span>'; $device = $m['device_name'] ? e($m['device_name']) : '<span class="sms-muted">—</span>'; ?>
        <tr>
        <?php if ($listView === 'queue'): ?>
          <td data-label="ID">#<?= $id ?></td><td data-label="Recipient"><?= $who ?></td><td data-label="Phone" class="sms-nowrap"><?= e($m['phone_number']) ?></td><td data-label="Type"><?= e(sms_label($m['message_type'])) ?></td>
          <td data-label="Message"><?= $snippet ?></td><td data-label="Priority"><span class="sms-priority sms-priority--<?= e($m['priority']) ?>"><?= e(ucfirst($m['priority'])) ?></span></td><td data-label="Status"><?= sms_status_badge($m['status']) ?></td>
          <td data-label="Attempts"><?= (int) $m['retry_count'] ?> / <?= (int) $m['max_retries'] ?></td><td data-label="Scheduled" class="sms-nowrap"><?= sms_time($m['scheduled_at']) ?></td><td data-label="Created" class="sms-nowrap"><?= sms_time($m['created_at']) ?></td><td data-label="Device"><?= $device ?></td>
        <?php elseif ($listView === 'sent'): ?>
          <td data-label="Recipient"><?= $who ?></td><td data-label="Phone" class="sms-nowrap"><?= e($m['phone_number']) ?></td><td data-label="Message"><?= $snippet ?></td><td data-label="Source"><?= e(sms_label($m['source'])) ?></td><td data-label="Device"><?= $device ?></td><td data-label="Sent At" class="sms-nowrap"><?= sms_time($m['sent_at']) ?></td>
        <?php elseif ($listView === 'failed'): ?>
          <td data-label="Recipient"><?= $who ?></td><td data-label="Phone" class="sms-nowrap"><?= e($m['phone_number']) ?></td><td data-label="Message"><?= $snippet ?></td>
          <td data-label="Reason"><?php if ($m['failure_code']): ?><code class="sms-code"><?= e($m['failure_code']) ?></code><?php endif ?><small class="sms-sub"><?= e($m['failure_reason'] ?? '') ?></small></td>
          <td data-label="Attempts"><?= (int) $m['retry_count'] ?></td><td data-label="Device"><?= $device ?></td><td data-label="Failed At" class="sms-nowrap"><?= sms_time($m['failed_at']) ?></td>
        <?php else: ?>
          <td data-label="Recipient"><?= $who ?><small class="sms-sub"><?= e($m['phone_number']) ?></small></td><td data-label="Message"><?= $snippet ?></td><td data-label="Scheduled At" class="sms-nowrap"><?= sms_time($m['scheduled_at']) ?></td><td data-label="Created By"><?= e($m['created_by_name'] ?? 'Automation') ?></td>
        <?php endif ?>
          <td data-label="Actions"><div class="sms-row-actions">
            <a class="btn btn--secondary btn--sm" href="<?= url('/admin/sms/messages/' . $id) ?>"><i data-lucide="eye"></i><?= $m['status'] === 'scheduled' && $canManage ? 'View / Edit' : 'View' ?></a>
            <?php if ($canManage): ?>
              <?php if (in_array($m['status'], ['failed', 'cancelled'], true)): ?><?= $action($id, $m['status'] === 'failed' ? 'retry' : 'requeue', $m['status'] === 'failed' ? 'Retry' : 'Requeue', 'rotate-ccw') ?><?php endif ?>
              <?php if (in_array($m['status'], ['queued', 'scheduled', 'failed'], true)): ?><?= $action($id, 'cancel', 'Cancel', 'ban', 'btn--secondary', 'Cancel this message?') ?><?php endif ?>
              <?php if (in_array($m['status'], ['queued', 'scheduled', 'failed', 'cancelled'], true)): ?><?= $action($id, 'delete', 'Delete', 'trash-2', 'btn--danger', 'Delete this message permanently?') ?><?php endif ?>
            <?php endif ?>
          </div></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table></div>
    <?= sms_pager($page, $perPage, $total, $filters + ['batch' => $batch]) ?>
  <?php endif ?>
  </div>
</section>
</div></div></main>
