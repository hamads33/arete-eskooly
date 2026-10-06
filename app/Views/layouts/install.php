<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
  <meta name="description" content="<?= e($pageDescription) ?>">
<?php require BASE_PATH . '/app/Views/partials/pwa-head.php'; ?>
  <link rel="icon" type="image/png" href="<?= e(asset('images/arete-logo.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/install.css?v=3')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/install-refinement.css?v=1')) ?>">
  <title><?= e($pageTitle) ?> &middot; Arete Coaching Center</title>
  <link rel="stylesheet" href="<?= e(asset('css/mobile-ui.css?v=10')) ?>">
</head>
<body class="install-page">
<?= $content ?>
<?php require BASE_PATH . '/app/Views/partials/pwa-ui.php'; ?>
</body>
</html>
