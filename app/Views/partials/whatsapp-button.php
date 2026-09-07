<?php
/**
 * Click-to-WhatsApp button.
 *
 * Posts through /admin/whatsapp/open so the attempt is logged and CSRF-protected, then opens
 * wa.me in a new tab — which picks up the admin's already-signed-in WhatsApp Web session.
 * Nothing is sent automatically; the admin still presses send in WhatsApp.
 *
 * Expects, set immediately before the include:
 *   $waPhone   — recipient number in any local format (normalised server-side)
 *   $waMessage — prefilled message text
 *   $waLabel   — optional button label (default "WhatsApp")
 *   $waStyle   — optional 'icon' for a compact icon-only button
 */
$waPhone = trim((string) ($waPhone ?? ''));
$waMessage = (string) ($waMessage ?? '');
$waLabel = (string) ($waLabel ?? 'WhatsApp');
$waStyle = (string) ($waStyle ?? 'button');
?>
<?php if ($waPhone !== ''): ?>
<form method="post" action="<?= e(url('/admin/whatsapp/open')) ?>" target="_blank" rel="noopener" class="inline-form whatsapp-form">
  <?= csrf_field() ?>
  <input type="hidden" name="phone" value="<?= e($waPhone) ?>">
  <input type="hidden" name="message" value="<?= e($waMessage) ?>">
  <?php if ($waStyle === 'icon'): ?>
  <button class="class-action-button whatsapp-button" type="submit" title="<?= e($waLabel) ?>" aria-label="<?= e($waLabel) ?>"><i data-lucide="message-circle"></i></button>
  <?php else: ?>
  <button class="btn btn--ghost whatsapp-button" type="submit"><i data-lucide="message-circle"></i><?= e($waLabel) ?></button>
  <?php endif; ?>
</form>
<?php else: ?>
<span class="whatsapp-missing" title="No mobile number recorded">&mdash;</span>
<?php endif; ?>
<?php $waPhone = null; $waMessage = null; $waLabel = null; $waStyle = null; ?>
