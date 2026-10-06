<?php
// Print layout: the admin picks Portrait (classic detailed) or Landscape (compact list) when printing
// (js/print-options.js). Very wide reports (31-day registers, admissions, contacts) start in landscape.
$printDefault = (($activeMenu ?? '') === 'reports' && in_array(($activeSubmenu ?? ''), ['report-student-attendance', 'report-employee-attendance', 'report-admissions', 'report-contacts'], true)) ? 'landscape' : 'portrait';
?>
<!doctype html>
<html data-print-default="<?= $printDefault ?>" data-print-mode="<?= $printDefault ?>" lang="<?= e(($institute['language'] ?? 'en') === 'ur' ? 'ur' : 'en') ?>" data-theme="<?= e($institute['theme'] ?? 'maroon') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php require BASE_PATH . '/app/Views/partials/pwa-head.php'; ?>
  <link rel="icon" type="image/png" href="<?= e(asset('images/arete-logo.png')) ?>"><link rel="apple-touch-icon" href="<?= e(asset('images/arete-logo.png')) ?>">
  <meta name="description" content="<?= e($pageDescription) ?>">
  <title><?= e($pageTitle) ?> · <?= e($institute['name'] ?? 'Arete Coaching Center') ?></title>
<?php foreach (array_values(array_unique($styles)) as $style): ?>
  <link rel="stylesheet" href="<?= e(asset($style)) ?>">
<?php endforeach; ?>
  <link rel="stylesheet" href="<?= e(asset('css/premium-ui.css?v=1')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/workflows.css?v=1')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/ui-polish.css?v=2')) ?>">
  <style id="printPageSize">@media print{@page{size:A4 <?= $printDefault ?>;margin:<?= $printDefault === 'landscape' ? '9mm 8mm 10mm' : '14mm 12mm 16mm' ?>}}</style>
  <link rel="stylesheet" href="<?= e(asset('css/mobile-ui.css?v=10')) ?>">
</head>
<?php $reportScreen=($activeMenu??'')==='reports';$reportLandscape=in_array(($activeSubmenu??''),['report-student-attendance','report-employee-attendance','report-admissions','report-contacts'],true); ?>
<body class="<?= $reportScreen?'report-screen ':'' ?><?= $reportLandscape?'report-landscape':'' ?>">
<?php require BASE_PATH . '/app/Views/partials/impersonation-banner.php'; ?>
  <div class="app-shell" id="appShell">
<?php require BASE_PATH . '/app/Views/partials/header.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/print-letterhead.php'; ?>
<?php if($reportScreen){ob_start();require BASE_PATH.'/app/Views/reports/partials/masthead.php';$reportMasthead=(string)ob_get_clean();$content=preg_replace_callback('/<div class="module-page">/',static fn(array$m):string=>$m[0].$reportMasthead,$content,1)??$content;} ?>
<?= $content ?>
  </div>

<?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/modal-root.php'; ?>

<?php if (isset($dashboardData)): ?>
  <script>
    window.dashboardData = <?= json_encode($dashboardData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
  </script>
<?php endif; ?>
<?php foreach (array_values(array_unique($scripts)) as $script): ?>
  <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
<?php foreach ($inlineScripts as $inlineScript): ?>
  <script><?= $inlineScript ?></script>
<?php endforeach; ?>
<?php require BASE_PATH . '/app/Views/partials/pwa-ui.php'; ?>
</body>
</html>
