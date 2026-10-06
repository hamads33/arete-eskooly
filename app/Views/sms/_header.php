<?php
/** @var string $pageTitle @var string $activeSubmenu */
require __DIR__ . '/_helpers.php';
$smsTabs = [
    'sms-dashboard' => ['Dashboard', '/admin/sms', 'sms.view'],
    'sms-compose' => ['Compose', '/admin/sms/compose', 'sms.compose'],
    'sms-bulk' => ['Bulk SMS', '/admin/sms/bulk', 'sms.bulk_send'],
    'sms-templates' => ['Templates', '/admin/sms/templates', 'sms.view'],
    'sms-queue' => ['Queue', '/admin/sms/queue', 'sms.view'],
    'sms-sent' => ['Sent', '/admin/sms/sent', 'sms.view'],
    'sms-failed' => ['Failed', '/admin/sms/failed', 'sms.view'],
    'sms-scheduled' => ['Scheduled', '/admin/sms/scheduled', 'sms.view'],
    'sms-automations' => ['Automations', '/admin/sms/automations', 'sms.view'],
    'sms-devices' => ['Gateway Devices', '/admin/sms/devices', 'sms.view'],
    'sms-settings' => ['Settings', '/admin/sms/settings', 'sms.settings.manage'],
    'sms-logs' => ['Logs', '/admin/sms/logs', 'sms.logs.view'],
];
?>
<header class="module-page__header sms-header">
  <div>
    <nav class="breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><a href="<?= url('/admin/sms') ?>">SMS Center</a><?php if ($activeSubmenu !== 'sms-dashboard'): ?><i data-lucide="chevron-right"></i><span><?= e($pageTitle) ?></span><?php endif ?></nav>
    <h1><?= e($pageTitle) ?></h1>
    <?php if (!empty($pageIntro)): ?><p><?= e($pageIntro) ?></p><?php endif ?>
  </div>
  <?php if (!empty($headerActions)): ?><div class="sms-header__actions"><?= $headerActions ?></div><?php endif ?>
</header>
<?php if (empty($smsSettings['sms_enabled'])): ?>
<div class="sms-notice sms-notice--warn"><i data-lucide="circle-pause"></i><div><strong>The SMS module is turned off.</strong> Nothing new can be queued until it is enabled<?php if (can('sms.settings.manage')): ?> in <a href="<?= url('/admin/sms/settings') ?>">SMS Settings</a><?php endif ?>.</div></div>
<?php endif ?>
<nav class="sms-tabs" aria-label="SMS Center sections">
<?php foreach ($smsTabs as $key => [$label, $href, $perm]): if (!can($perm)) continue; ?>
  <a class="sms-tab<?= $activeSubmenu === $key ? ' is-active' : '' ?>" href="<?= url($href) ?>"<?= $activeSubmenu === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach ?>
</nav>
