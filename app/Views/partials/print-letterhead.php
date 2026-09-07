<?php
/**
 * Institute letterhead and footer, rendered on every admin/portal page but visible only when
 * printing (see print.css). This is what turns a browser print into a branded document
 * instead of a screenshot of the interface.
 */
$printInstitute = $institute ?? [];
$printName = $printInstitute['name'] ?? 'Institute';
$printBits = array_values(array_filter([
    $printInstitute['address'] ?? null,
    $printInstitute['phone'] ?? null,
    $printInstitute['website'] ?? null,
]));
?>
<div class="print-letterhead" aria-hidden="true">
  <div class="print-letterhead__row">
    <img class="print-letterhead__logo" src="<?= e(asset(($printInstitute['logo_path'] ?? '') !== '' ? (string) $printInstitute['logo_path'] : 'images/arete-logo.png')) ?>" alt="">
    <div>
      <p class="print-letterhead__name"><?= e($printName) ?></p>
      <?php if ($printBits !== []): ?><p class="print-letterhead__meta"><?= e(implode(' · ', $printBits)) ?></p><?php endif; ?>
    </div>
  </div>
  <div class="print-letterhead__title"><?= e($pageTitle ?? '') ?></div>
</div>
<div class="print-footer" aria-hidden="true">
  <span><?= e($printName) ?></span>
  <span>Generated <?= e(date('d M Y, H:i')) ?></span>
</div>
