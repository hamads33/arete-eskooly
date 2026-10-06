<div class="pwa-status" data-pwa-status role="status" aria-live="polite" hidden></div>
<?php if (!($hideInstallBanner ?? false)): ?>
<div class="pwa-install-banner" data-pwa-install-banner role="region" aria-label="Install Arete app">
  <img class="pwa-install-banner__logo" src="<?= e(url('/assets/pwa/arete-icon-192-v2.png')) ?>" alt="Arete">
  <div class="pwa-install-banner__copy"><strong>Install App</strong><span>Install Arete for a faster, app-like experience</span></div>
  <button class="pwa-install-banner__later" type="button" data-pwa-install-later>Later</button>
  <button class="pwa-install-banner__install" type="button" data-pwa-install>Install</button>
  <button class="pwa-install-banner__close" type="button" data-pwa-install-later aria-label="Dismiss install banner">&times;</button>
</div>
<?php endif; ?>

<script src="<?= e(asset('js/header-search.js?v=1')) ?>" defer></script>
<script>window.aretePwa={enabled:<?= app()->config('app.pwa_enabled', true) ? 'true' : 'false' ?>};</script>
<script src="<?= e(asset('js/pwa.js?v=9')) ?>" defer></script>
<script src="<?= e(asset('js/table-tools.js?v=1')) ?>" defer></script>
<script src="<?= e(asset('js/form-a11y.js?v=2')) ?>" defer></script>
<script src="<?= e(asset('js/print-options.js?v=1')) ?>" defer></script>
<script src="<?= e(asset('js/mobile-ux.js?v=3')) ?>" defer></script>
