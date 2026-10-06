<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php require BASE_PATH . '/app/Views/partials/pwa-head.php'; ?>
  <link rel="icon" type="image/png" href="<?= e(asset('images/arete-logo.png')) ?>">
  <title><?= e($pageTitle ?? 'Error') ?> &middot; Arete Coaching Center</title>
  <link rel="stylesheet" href="<?= e(asset('css/variables.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/reset.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
  <style>body{display:grid;min-height:100vh;place-items:center;background:#f8f6f7;padding:24px}.error-card{width:min(100%,560px);text-align:center;background:#fff;border:1px solid #eadde2;border-radius:18px;padding:clamp(28px,6vw,48px);box-shadow:0 18px 45px rgba(84,9,37,.1)}.error-card h1{color:#6f1238;font-size:clamp(1.65rem,5vw,2rem);margin:14px 0}.error-card p{color:#6d6267;margin-bottom:24px}.error-card a{display:inline-flex;background:#7d1742;color:#fff;border-radius:10px;padding:12px 18px;text-decoration:none}</style>
  <link rel="stylesheet" href="<?= e(asset('css/mobile-ui.css?v=10')) ?>">
</head>
<body><?= $content ?><?php require BASE_PATH . '/app/Views/partials/pwa-ui.php'; ?></body>
</html>
