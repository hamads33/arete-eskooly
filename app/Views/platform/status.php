<?php
$domain = \App\Platform\PlatformConfig::domain();
$st = $school['status'] ?? null;
$host = $slug !== '' ? $slug . '.' . $domain : '';
$applied = $school ? date('d M Y', strtotime((string) $school['created_at'])) : '';
?>
<link rel="stylesheet" href="<?= e(asset('css/schools.css?v=7')) ?>">
<div class="sp">
<?php if ($notice !== ''): ?><p class="sp-banner" role="status"><?= e($notice) ?></p><?php endif ?>

<?php if ($slug === '' || !$school): ?>
  <div class="auth-heading signup-heading">
    <span class="eyebrow">APPLICATION STATUS</span>
    <?php if ($slug === ''): ?>
      <h1>Check your application<span>.</span></h1><p>Enter the portal address you applied for.</p>
    <?php else: ?>
      <h1>No portal here yet<span>.</span></h1><p>There is no school at <strong><?= e($host) ?></strong>. Check the spelling, or apply for this address now.</p>
    <?php endif ?>
  </div>
  <form class="auth-form sp-form" method="get" action="<?= url('/school-status') ?>">
    <div class="auth-field sp-slug"><label for="school">Portal address</label>
      <div class="auth-control"><input id="school" name="school" type="text" autocapitalize="none" spellcheck="false" placeholder="brightfuture" value="<?= e($slug) ?>" required><span class="sp-slug__suffix">.<?= e($domain) ?></span></div></div>
    <button class="auth-submit" type="submit"><span>Check status</span><span aria-hidden="true">→</span></button>
  </form>
  <p class="auth-switch">Want your own portal? <a href="<?= url('/register-school') ?>">Register your school</a></p>

<?php elseif ($st === 'active'): ?>
  <div class="auth-heading signup-heading"><span class="eyebrow">APPROVED</span><h1>Your portal is live<span>.</span></h1><p><strong><?= e($school['name']) ?></strong> is ready. Sign in with the email and password you chose when applying.</p></div>
  <ol class="sp-steps">
    <li class="sp-step sp-step--done"><span class="sp-step__dot">✓</span><div><strong>Application submitted</strong><span><?= e($applied) ?></span></div></li>
    <li class="sp-step sp-step--done"><span class="sp-step__dot">✓</span><div><strong>Approved by Arete</strong><span><?= e(date('d M Y', strtotime((string) $school['approved_at']))) ?></span></div></li>
    <li class="sp-step sp-step--done"><span class="sp-step__dot">✓</span><div><strong>Portal live</strong><span><?= e($host) ?></span></div></li>
  </ol>
  <a class="auth-submit" href="<?= e($address) ?>/login" style="text-decoration:none"><span>Go to <?= e($host) ?></span><span aria-hidden="true">→</span></a>

<?php elseif ($st === 'suspended'): ?>
  <div class="auth-heading signup-heading"><span class="eyebrow">ACCESS REVOKED</span><h1>This portal is paused<span>.</span></h1><p>Access to <strong><?= e($host) ?></strong> has been revoked by Arete. The school's data is safe and nothing has been deleted.</p></div>
  <div class="sp-status-card"><dl><dt>School</dt><dd><?= e($school['name']) ?></dd><dt>Address</dt><dd><?= e($host) ?></dd></dl></div>
  <p class="auth-switch" style="text-align:left">If you are this school's administrator, please contact Arete Coaching Center to restore access.</p>

<?php elseif ($st === 'rejected'): ?>
  <div class="auth-heading signup-heading"><span class="eyebrow">NOT APPROVED</span><h1>Application not approved<span>.</span></h1><p>The application for <strong><?= e($host) ?></strong> was not approved.</p></div>
  <?php if (trim((string) $school['note']) !== ''): ?><div class="sp-status-card"><dl><dt>Reason</dt><dd><?= e($school['note']) ?></dd></dl></div><?php endif ?>
  <a class="auth-submit" href="<?= url('/register-school') ?>" style="text-decoration:none"><span>Apply again</span><span aria-hidden="true">→</span></a>

<?php else: /* pending or failed: both are "under review" for the school */ ?>
  <div class="auth-heading signup-heading"><span class="eyebrow">UNDER REVIEW</span><h1>Application received<span>.</span></h1><p>Your portal at <strong><?= e($host) ?></strong> is waiting for approval from the Arete team.</p></div>
  <div class="sp-status-card"><dl><dt>School</dt><dd><?= e($school['name']) ?></dd><dt>Applied</dt><dd><?= e($applied) ?></dd><dt>Administrator</dt><dd><?= e($school['admin_name']) ?></dd></dl></div>
  <ol class="sp-steps">
    <li class="sp-step sp-step--done"><span class="sp-step__dot">✓</span><div><strong>Application submitted</strong><span><?= e($applied) ?></span></div></li>
    <li class="sp-step sp-step--current"><span class="sp-step__dot">2</span><div><strong>Review by Arete</strong><span>Usually within one working day.</span></div></li>
    <li class="sp-step"><span class="sp-step__dot">3</span><div><strong>Portal goes live</strong><span>This page will show a sign-in button as soon as it is ready.</span></div></li>
  </ol>
  <a class="auth-submit" href="<?= url('/school-status?school=' . rawurlencode($slug)) ?>" style="text-decoration:none"><span>Refresh status</span><span aria-hidden="true" data-icon="refresh">↻</span></a>
<?php endif ?>
</div>
