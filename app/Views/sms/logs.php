<?php
$pageIntro = 'Audit trail of SMS activity: who queued, cancelled or retried a message, and what each gateway device reported.';
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>
<form class="filter-bar filter-bar--compact sms-filters" method="get">
  <div class="module-field"><label for="lEvent">Event</label><select class="module-select" id="lEvent" name="event"><option value="">All events</option><?php foreach ($events as $ev): ?><option value="<?= e($ev) ?>" <?= $selectedEvent === $ev ? 'selected' : '' ?>><?= e(sms_label($ev)) ?></option><?php endforeach ?></select></div>
  <?php if ($devices): ?><div class="module-field"><label for="lDevice">Device</label><select class="module-select" id="lDevice" name="device"><option value="">All devices</option><?php foreach ($devices as $d): ?><option value="<?= (int) $d['id'] ?>" <?= $selectedDevice === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['device_name']) ?></option><?php endforeach ?></select></div><?php endif ?>
  <div class="filter-actions"><button class="btn btn--primary">Filter</button><?php if ($selectedEvent || $selectedDevice): ?><a class="btn btn--secondary" href="<?= url('/admin/sms/logs') ?>">Reset</a><?php endif ?></div>
</form>
<section class="module-card">
  <div class="module-card__body">
  <?php if (!$rows): ?>
    <div class="compact-empty sms-empty"><i data-lucide="scroll-text"></i><h3>No log entries yet.</h3><p>SMS activity will be recorded here.</p></div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table sms-table">
      <thead><tr><th>Time</th><th>Event</th><th>Actor</th><th>Message</th><th>Device</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $l): $d = json_decode((string) $l['details_json'], true) ?: []; ?>
        <tr>
          <td data-label="Time" class="sms-nowrap"><?= e(date('d M Y, h:i:s A', strtotime($l['created_at']))) ?></td>
          <td data-label="Event"><strong><?= e(sms_label($l['event'])) ?></strong></td>
          <td data-label="Actor"><?= e($l['actor_name'] ?? '') ?><small class="sms-sub"><?= e(sms_label($l['actor_type'])) ?></small></td>
          <td data-label="Message"><?= $l['sms_message_id'] ? '<a href="' . e(url('/admin/sms/messages/' . $l['sms_message_id'])) . '">#' . (int) $l['sms_message_id'] . '</a>' . ($l['phone_number'] ? '<small class="sms-sub">' . e($l['phone_number']) . '</small>' : '') : '<span class="sms-muted">—</span>' ?></td>
          <td data-label="Device"><?= e($l['device_name'] ?? '—') ?></td>
          <td data-label="Details"><?php if ($d): ?><span class="sms-details"><?php foreach ($d as $k => $v): ?><span><?= e(sms_label((string) $k)) ?>: <?= e(is_array($v) ? implode(', ', array_map('strval', $v)) : (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v)) ?></span><?php endforeach ?></span><?php else: ?><span class="sms-muted">—</span><?php endif ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table></div>
    <?= sms_pager($page, $perPage, $total, ['event' => $selectedEvent, 'device' => $selectedDevice]) ?>
  <?php endif ?>
  </div>
</section>
</div></div></main>
