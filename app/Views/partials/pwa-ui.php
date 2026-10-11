<div class="pwa-status" data-pwa-status role="status" aria-live="polite" hidden></div>


<script src="<?= e(asset('js/header-search.js?v=1')) ?>" defer></script>
<script>window.aretePwa={enabled:<?= app()->config('app.pwa_enabled', true) ? 'true' : 'false' ?>};</script>
<script src="<?= e(asset('js/pwa.js?v=9')) ?>" defer></script>
<script src="<?= e(asset('js/table-tools.js?v=1')) ?>" defer></script>
<script src="<?= e(asset('js/form-a11y.js?v=2')) ?>" defer></script>
<script src="<?= e(asset('js/print-options.js?v=1')) ?>" defer></script>
<script src="<?= e(asset('js/mobile-ux.js?v=8')) ?>" defer></script>

