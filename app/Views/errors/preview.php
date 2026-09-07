<div class="error-card">
  <span aria-hidden="true" style="font-size:2rem">🚧</span>
  <h1><?= e($moduleName ?? 'This module') ?> is under testing</h1>
  <p>This area is not production ready yet and has been temporarily disabled. It will be live soon.</p>
  <a href="<?= e(url('/admin/dashboard')) ?>">Back to dashboard</a>
</div>
