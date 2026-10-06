<?php
$pageIntro = 'Queue SMS for parents, students and staff. Messages are sent through the institute\'s Android SIM gateway.';
$headerActions = (can('sms.compose') ? '<a class="btn btn--primary" href="' . url('/admin/sms/compose') . '"><i data-lucide="send"></i>Compose SMS</a>' : '');
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>

<?php if ($deviceCount === 0): ?>
<div class="sms-notice"><i data-lucide="smartphone"></i><div><strong>No gateway device is connected yet.</strong> Queued messages wait safely in the LMS until your Android SMS bridge is connected<?php if (can('sms.view')): ?> from <a href="<?= url('/admin/sms/devices') ?>">Gateway Devices</a><?php endif ?>.</div></div>
<?php elseif ($connected === 0): ?>
<div class="sms-notice sms-notice--warn"><i data-lucide="wifi-off"></i><div><strong>No gateway device is online.</strong> Queued messages will go out when a device reconnects.</div></div>
<?php endif ?>

<section class="sms-stats" aria-label="SMS totals">
  <?php foreach ([
      ['Total SMS', $stats['total'] ?? 0, 'messages-square', '/admin/sms/queue'],
      ['Queued', ($stats['queued'] ?? 0) + ($stats['in_flight'] ?? 0), 'inbox', '/admin/sms/queue?status=queued'],
      ['Sent', $stats['sent'] ?? 0, 'check-check', '/admin/sms/sent'],
      ['Failed', $stats['failed'] ?? 0, 'circle-alert', '/admin/sms/failed'],
      ['Scheduled', $stats['scheduled'] ?? 0, 'calendar-clock', '/admin/sms/scheduled'],
      ['Today', $stats['today'] ?? 0, 'sun', '/admin/sms/queue?from=' . date('Y-m-d')],
      ['This Month', $stats['month'] ?? 0, 'calendar-days', '/admin/sms/queue?from=' . date('Y-m-01')],
      ['Gateways Online', $connected . ' / ' . $deviceCount, 'smartphone', '/admin/sms/devices'],
  ] as [$label, $value, $icon, $href]): ?>
  <a class="sms-stat" href="<?= url($href) ?>"><span class="sms-stat__icon"><i data-lucide="<?= $icon ?>"></i></span><span class="sms-stat__body"><span><?= e($label) ?></span><strong><?= e((string) $value) ?></strong></span></a>
  <?php endforeach ?>
</section>

<div class="sms-grid">
<section class="module-card">
  <header class="module-card__header"><div><h2>Recent Activity</h2><p>The latest messages in the queue.</p></div><a class="text-button" href="<?= url('/admin/sms/queue') ?>">View queue</a></header>
  <div class="module-card__body">
  <?php if (!$recent): ?>
    <div class="compact-empty sms-empty"><i data-lucide="message-square-dashed"></i><h3>No SMS messages have been queued yet.</h3><p>Compose a message or turn on an automation to get started.</p></div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table sms-table">
      <thead><tr><th>Recipient</th><th>Phone Number</th><th>Message</th><th>Type</th><th>Status</th><th>Created</th><th>Sent</th><th>Gateway Device</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $m): ?>
        <tr>
          <td data-label="Recipient"><a href="<?= url('/admin/sms/messages/' . $m['id']) ?>"><strong><?= e($m['recipient_name'] ?: 'Manual number') ?></strong></a><small class="sms-sub"><?= e(sms_label($m['recipient_type'])) ?></small></td>
          <td data-label="Phone" class="sms-nowrap"><?= e($m['phone_number']) ?></td>
          <td data-label="Message"><span class="sms-snippet"><?= e(mb_strimwidth($m['message'], 0, 70, '…')) ?></span></td>
          <td data-label="Type"><?= e(sms_label($m['message_type'])) ?></td>
          <td data-label="Status"><?= sms_status_badge($m['status']) ?></td>
          <td data-label="Created" class="sms-nowrap"><?= sms_time($m['created_at']) ?></td>
          <td data-label="Sent" class="sms-nowrap"><?= sms_time($m['sent_at']) ?></td>
          <td data-label="Device"><?= $m['device_name'] ? e($m['device_name']) : '<span class="sms-muted">—</span>' ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table></div>
  <?php endif ?>
  </div>
</section>

<aside class="sms-side">
  <section class="module-card">
    <header class="module-card__header"><div><h2>Quick Actions</h2></div></header>
    <div class="module-card__body sms-actions">
      <?php foreach ([
          ['Compose SMS', 'send', '/admin/sms/compose', 'sms.compose'],
          ['Bulk SMS', 'users-round', '/admin/sms/bulk', 'sms.bulk_send'],
          ['Create Template', 'file-plus-2', '/admin/sms/templates/create', 'sms.templates.manage'],
          ['View Queue', 'list-ordered', '/admin/sms/queue', 'sms.view'],
          ['Gateway Settings', 'smartphone', '/admin/sms/devices', 'sms.view'],
      ] as [$label, $icon, $href, $perm]): if (!can($perm)) continue; ?>
        <a class="sms-action" href="<?= url($href) ?>"><i data-lucide="<?= $icon ?>"></i><span><?= e($label) ?></span><i data-lucide="chevron-right" class="sms-action__go"></i></a>
      <?php endforeach ?>
    </div>
  </section>
  <section class="module-card">
    <header class="module-card__header"><div><h2>Gateway</h2></div></header>
    <div class="module-card__body sms-kv">
      <div><span>Connected devices</span><strong><?= (int) $connected ?> of <?= (int) $deviceCount ?></strong></div>
      <div><span>Last gateway activity</span><strong><?= $lastActivity ? e(date('d M, h:i A', strtotime($lastActivity))) : 'Never' ?></strong></div>
      <div><span>Used today</span><strong><?= (int) $usage['today'] ?><?= $smsSettings['daily_limit'] ? ' / ' . (int) $smsSettings['daily_limit'] : '' ?></strong></div>
      <div><span>Used this month</span><strong><?= (int) $usage['month'] ?><?= $smsSettings['monthly_limit'] ? ' / ' . (int) $smsSettings['monthly_limit'] : '' ?></strong></div>
    </div>
  </section>
</aside>
</div>
</div></div></main>
