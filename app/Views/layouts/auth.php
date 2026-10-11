<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="light">
<?php require BASE_PATH . '/app/Views/partials/pwa-head.php'; ?>
  <link rel="icon" type="image/png" href="<?= e(brand()['logo']) ?>">
  <title><?= e($pageTitle) ?> &middot; <?= e(brand()['short']) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/auth.css?v=6')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/mobile-ui.css?v=19')) ?>">
</head>
<body><main class="auth-page"><div class="auth-shell">
<section class="form-panel"><a class="auth-brand" href="<?= url('/login') ?>"><img class="auth-brand-logo" src="<?= e(brand()['logo']) ?>" alt="" width="62" height="62"><span><?= e(mb_strtoupper(brand()['name'])) ?><?php if(brand()['caption']!==''):?><span class="brand-caption"><?= e(brand()['caption']) ?></span><?php endif ?></span></a>
<?= $content ?>
<footer class="auth-footer"><span class="security-dot"></span> A thoughtful space for your school.</footer>
</section>
<aside class="visual-panel" aria-label="Your school workspace"><div class="visual-top"><span class="visual-label">BUILT AROUND YOUR SCHOOL</span><span class="visual-badge">One connected workspace</span></div>
<div class="visual-heading"><span class="eyebrow">LESS ADMIN. MORE POSSIBILITY.</span><h2>Great schools.<br>Beautifully <em>connected.</em></h2><p>Bring your people, learning and everyday<br class="desktop-break"> operations together in one place.</p></div>
<img class="workspace-art" src="<?= asset('images/auth-workspace.svg') ?>" alt="Original illustration of a school campus with attendance, calendar and learning cards">
<div class="visual-bottom"><span>People. Progress. Possibility.</span><span class="visual-index">01 / YOUR NEXT CHAPTER</span></div>
</aside></div><p class="page-signature"><?= e(mb_strtoupper(brand()['short'])) ?> &middot; INSTITUTE MANAGEMENT, CONSIDERED.</p></main>
<?php if($notification=\App\Support\Flash::get()):?><div class="auth-notification" role="status"><?= e($notification['message']) ?></div><?php endif ?>
<script src="<?= asset('js/auth.js?v=2') ?>" defer></script>
<?php require BASE_PATH . '/app/Views/partials/pwa-ui.php'; ?>
</body></html>
