<!doctype html>
<html lang="<?= e(($institute['language'] ?? 'en') === 'ur' ? 'ur' : 'en') ?>" data-theme="<?= e($institute['theme'] ?? 'maroon') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= e($pageDescription) ?>">
  <title><?= e($pageTitle) ?> · <?= e($institute['name'] ?? 'Institute') ?></title>
<?php foreach (array_values(array_unique($styles)) as $style): ?>
  <link rel="stylesheet" href="<?= e(asset($style)) ?>">
<?php endforeach; ?>
</head>
<body class="portal-shell">
  <div class="app-shell" id="appShell">
<?php require BASE_PATH . '/app/Views/partials/portal-header.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>
<?= $content ?>
  </div>

<?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/modal-root.php'; ?>

<?php foreach (array_values(array_unique($scripts)) as $script): ?>
  <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
<?php foreach ($inlineScripts as $inlineScript): ?>
  <script><?= $inlineScript ?></script>
<?php endforeach; ?>
</body>
</html>
