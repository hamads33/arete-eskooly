<?php if ($breadcrumb !== []): ?>
<nav class="breadcrumb" aria-label="Breadcrumb"><?php foreach ($breadcrumb as $index => $crumb): ?><span><?= e($crumb) ?></span><?= $index < count($breadcrumb) - 1 ? '<i data-lucide="chevron-right"></i>' : '' ?><?php endforeach; ?></nav>
<?php endif; ?>
