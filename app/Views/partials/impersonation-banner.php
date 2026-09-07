<?php if (app()->session()->has('impersonator_user_id')): ?>
<aside class="impersonation-banner" role="status" aria-live="polite">
  <span><i data-lucide="eye" aria-hidden="true"></i> Viewing as <strong><?= e(user()['name'] ?? 'another user') ?></strong></span>
  <form method="post" action="<?= e(url('/impersonation/stop')) ?>"><?= csrf_field() ?><button type="submit">Return to admin</button></form>
</aside>
<?php endif; ?>
