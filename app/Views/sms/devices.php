<?php
$pageIntro = 'Android phones that send queued SMS through the institute\'s SIM card.';
$canManage = can('sms.gateway.manage');
$connected = count(array_filter($devices, static fn($d) => $d['live_status'] === 'connected'));
$act = static function (int $id, string $what, string $label, string $icon, string $class = 'btn--secondary', ?string $confirm = null): string {
    return '<form method="post" action="' . e(url('/admin/sms/devices/' . $id . '/' . $what)) . '"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field()
        . '<button class="btn ' . $class . ' btn--sm" type="submit"><i data-lucide="' . $icon . '"></i>' . e($label) . '</button></form>';
};
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>

<?php if ($secret): ?>
<section class="sms-secret" role="alert">
  <i data-lucide="key-round"></i>
  <div>
    <strong><?= $secret['kind'] === 'pairing' ? 'Pairing code' : 'Access token' ?></strong>
    <code class="sms-secret__value" data-copy-source><?= e($secret['code']) ?></code>
    <p><strong>Copy this <?= $secret['kind'] === 'pairing' ? 'code' : 'token' ?> now. It will not be shown again.</strong>
    <?php if ($secret['kind'] === 'pairing'): ?>Enter it in the Android SMS bridge app within <?= \App\Services\Sms\SmsGateway::PAIRING_TTL_MINUTES ?> minutes (expires <?= e(date('h:i A', strtotime($secret['expires_at']))) ?>). It works once.<?php else: ?>Enter it in the Android SMS bridge app as the device token. Earlier tokens for this device no longer work.<?php endif ?></p>
    <button class="btn btn--secondary btn--sm" type="button" data-copy-button><i data-lucide="copy"></i>Copy</button>
  </div>
</section>
<?php endif ?>

<section class="module-card sms-bridge">
  <div class="module-card__body">
    <span class="sms-bridge__icon"><i data-lucide="smartphone"></i></span>
    <div class="sms-bridge__text">
      <h2>Android SMS Bridge</h2>
      <p>Connect an Android device to send queued LMS messages through the institute's SIM card.</p>
      <p class="sms-bridge__status">Status: <?= $connected ? sms_status_badge('connected') . ' ' . $connected . ' device' . ($connected === 1 ? '' : 's') . ' online' : ($devices ? sms_status_badge('offline') . ' No device online' : sms_status_badge('pending') . ' Not connected') ?></p>
      <?php if (!$devices): ?><p class="sms-muted">Your Android SMS bridge can be connected later from this page. Until then, queued messages wait safely in the LMS.</p><?php endif ?>
    </div>
    <?php if ($canManage): ?>
    <form class="sms-bridge__form" method="post" action="<?= url('/admin/sms/devices') ?>">
      <?= csrf_field() ?>
      <div class="module-field"><label for="devName">Device name</label><input class="module-input" id="devName" name="device_name" maxlength="120" required placeholder="e.g. Office Samsung A15"></div>
      <div class="module-field"><label for="devSim">SIM slot <small>(optional)</small></label><select class="module-select" id="devSim" name="sim_slot"><option value="">Phone default</option><option value="1">SIM 1</option><option value="2">SIM 2</option></select></div>
      <button class="btn btn--primary" type="submit"><i data-lucide="plus"></i>Register Device &amp; Get Pairing Code</button>
    </form>
    <?php endif ?>
  </div>
  <footer class="module-card__footer sms-bridge__api"><i data-lucide="plug"></i><span>API base URL for the bridge app:</span><code><?= e($apiBase) ?></code></footer>
</section>

<?php if ($devices): ?>
<section class="module-card">
  <header class="module-card__header"><div><h2>Registered Devices</h2></div></header>
  <div class="module-card__body">
    <div class="table-scroll"><table class="data-table sms-table">
      <thead><tr><th>Device</th><th>Device UUID</th><th>Phone Number</th><th>SIM</th><th>Model</th><th>Status</th><th>Last Seen</th><th>Last IP</th><th>Last Message Sent</th><th>Active</th><?php if ($canManage): ?><th>Actions</th><?php endif ?></tr></thead>
      <tbody>
      <?php foreach ($devices as $d): $id = (int) $d['id']; ?>
        <tr>
          <td data-label="Device"><strong><?= e($d['device_name']) ?></strong><small class="sms-sub"><?= (int) $d['sent_count'] ?> sent<?= $d['battery_level'] !== null ? ' · battery ' . (int) $d['battery_level'] . '%' : '' ?><?= $d['network'] ? ' · ' . e($d['network']) : '' ?></small></td>
          <td data-label="UUID"><?= $d['device_uuid'] ? '<code class="sms-code">' . e(mb_strimwidth($d['device_uuid'], 0, 18, '…')) . '</code>' : '<span class="sms-muted">Not paired</span>' ?></td>
          <td data-label="Phone" class="sms-nowrap"><?= e($d['phone_number'] ?? '—') ?></td>
          <td data-label="SIM"><?= $d['sim_slot'] ? 'SIM ' . (int) $d['sim_slot'] : 'Default' ?><?= $d['sim_name'] ? '<small class="sms-sub">' . e($d['sim_name']) . '</small>' : '' ?></td>
          <td data-label="Model"><?= e(trim(($d['manufacturer'] ?? '') . ' ' . ($d['model'] ?? '')) ?: '—') ?><?= $d['android_version'] ? '<small class="sms-sub">Android ' . e($d['android_version']) . ($d['app_version'] ? ' · app ' . e($d['app_version']) : '') . '</small>' : '' ?></td>
          <td data-label="Status"><?= sms_status_badge($d['live_status']) ?><?php if ($d['pairing_expires_at']): ?><small class="sms-sub">Pairing code valid until <?= e(date('h:i A', strtotime($d['pairing_expires_at']))) ?></small><?php endif ?></td>
          <td data-label="Last Seen" class="sms-nowrap"><?= sms_time($d['last_seen_at']) ?></td>
          <td data-label="Last IP"><?= e($d['last_ip'] ?? '—') ?></td>
          <td data-label="Last Sent" class="sms-nowrap"><?= sms_time($d['last_sent_at']) ?></td>
          <td data-label="Active"><?= $d['is_active'] ? 'Yes' : 'No' ?></td>
          <?php if ($canManage): ?>
          <td data-label="Actions"><div class="sms-row-actions">
            <details class="sms-menu"><summary class="btn btn--secondary btn--sm"><i data-lucide="settings-2"></i>Manage</summary>
              <div class="sms-menu__panel">
                <form method="post" action="<?= url('/admin/sms/devices/' . $id . '/update') ?>" class="sms-menu__rename"><?= csrf_field() ?>
                  <label>Name<input class="module-input" name="device_name" value="<?= e($d['device_name']) ?>" maxlength="120" required></label>
                  <label>SIM slot<select class="module-select" name="sim_slot"><option value="">Phone default</option><?php foreach ([1, 2] as $s): ?><option value="<?= $s ?>" <?= (int) $d['sim_slot'] === $s ? 'selected' : '' ?>>SIM <?= $s ?></option><?php endforeach ?></select></label>
                  <button class="btn btn--secondary btn--sm" type="submit">Save</button>
                </form>
                <?php if ($d['is_active']): ?>
                  <?= $act($id, 'pairing', 'Generate Pairing Code', 'qr-code') ?>
                  <?= $act($id, 'token', $d['active_tokens'] ? 'Regenerate Token' : 'Generate Token', 'key-round', 'btn--secondary', $d['active_tokens'] ? 'The current token will stop working immediately. Continue?' : null) ?>
                  <?php if ($d['active_tokens']): ?><?= $act($id, 'revoke', 'Revoke Token', 'shield-off', 'btn--secondary', 'Revoke all tokens? The device will stop sending until paired again.') ?><?php endif ?>
                  <?= $act($id, 'disable', 'Disable Device', 'power-off', 'btn--secondary', 'Disable this device?') ?>
                <?php else: ?>
                  <?= $act($id, 'enable', 'Enable Device', 'power') ?>
                <?php endif ?>
                <?php if (can('sms.logs.view')): ?><a class="btn btn--secondary btn--sm" href="<?= url('/admin/sms/logs?device=' . $id) ?>"><i data-lucide="scroll-text"></i>View Device Logs</a><?php endif ?>
                <?= $act($id, 'delete', 'Remove Device', 'trash-2', 'btn--danger', 'Remove this paired device? Its access will be revoked immediately. Sent-message history will be preserved.') ?>
              </div>
            </details>
          </div></td>
          <?php endif ?>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table></div>
  </div>
</section>
<?php else: ?>
<div class="compact-empty sms-empty"><i data-lucide="smartphone-nfc"></i><h3>No gateway device is connected.</h3><p>Register a device above when your Android SMS bridge is ready.</p></div>
<?php endif ?>
</div></div></main>
