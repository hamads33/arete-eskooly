<?php $pendingSchools = \App\Controllers\PlatformController::pendingCount(); if ($pendingSchools > 0): ?>
<link rel="stylesheet" href="<?= e(asset('css/schools.css?v=7')) ?>">
<a class="sp sp-notice" href="<?= url('/admin/schools?status=pending') ?>">
  <span class="sp-notice__icon" aria-hidden="true"><i data-lucide="building-2"></i></span>
  <span class="sp-notice__text"><strong><?= $pendingSchools ?> school<?= $pendingSchools === 1 ? '' : 's' ?> waiting for approval</strong><span>Review the application<?= $pendingSchools === 1 ? '' : 's' ?> and create <?= $pendingSchools === 1 ? 'its portal' : 'their portals' ?> in one click.</span></span>
  <span class="sp-btn sp-btn--primary sp-btn--sm">Review</span>
</a>
<?php endif ?>
