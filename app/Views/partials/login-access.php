<?php
/**
 * Access controls for one portal login row.
 * Expects: $a (account row), $accessBase (e.g. /admin/students/logins/12), $editUrl, $who ('student'|'employee'|'parent'), $feeRules (bool)
 */
$level = (string) ($a['status'] ?? '');
$source = $a['access_source'] ?? null;
$form = static function (string $status, string $label, string $icon, string $class, string $confirm = '', bool $reason = false) use ($accessBase): string {
    $html = '<form method="post" action="' . e(url($accessBase . '/status')) . '" class="login-access__form"' . ($confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="status" value="' . $status . '">';
    if ($reason) {
        $html .= '<input class="module-input login-access__reason" name="reason" maxlength="255" placeholder="Reason (optional)" aria-label="Reason">';
    }
    return $html . '<button class="btn ' . $class . '" type="submit"><i data-lucide="' . $icon . '"></i>' . e($label) . '</button></form>';
};
?>
<div class="login-access">
  <div class="login-access__state">
    <?php if ($level === 'active'): ?><span class="status-badge login-badge login-badge--active">Active</span>
    <?php elseif ($level === 'suspended'): ?><span class="status-badge login-badge login-badge--suspended">Suspended</span>
    <?php elseif ($level === 'disabled'): ?><span class="status-badge login-badge login-badge--blocked">Blocked</span>
    <?php else: ?><span class="login-access__none">—</span><?php endif ?>
    <?php if ($source === 'fee'): ?><small>Automatic · unpaid fees</small><?php elseif ($source === 'manual'): ?><small>Set by admin<?= $feeRules ? ' · fee rules paused' : '' ?></small><?php endif ?>
    <?php if (!empty($a['access_reason'])): ?><small><?= e($a['access_reason']) ?></small><?php endif ?>
  </div>
  <details class="login-access__menu">
    <summary class="btn btn--secondary"><i data-lucide="user-cog"></i>Manage</summary>
    <div class="login-access__panel">
      <?php if ($level !== 'active'): ?><?= $form('active', 'Activate', 'check-circle', 'btn--primary') ?><?php endif ?>
      <?php if ($level !== 'suspended'): ?><?= $form('suspended', 'Suspend', 'pause-circle', 'btn--secondary', 'Suspend this login? The ' . $who . ' can still sign in but will not be able to open or do anything until reactivated.', true) ?><?php endif ?>
      <?php if ($level !== 'disabled'): ?><?= $form('disabled', 'Block', 'ban', 'btn--secondary', 'Block this login? The ' . $who . ' will be signed out and cannot sign in until activated.', true) ?><?php endif ?>
      <?php if ($feeRules && $source === 'manual'): ?><?= $form('auto', 'Use fee rules again', 'refresh-ccw', 'btn--secondary') ?><?php endif ?>
      <?php if (!empty($editUrl)): ?><a class="btn btn--secondary" href="<?= e(url($editUrl)) ?>"><i data-lucide="pencil"></i>Edit <?= e($who) ?> record</a><?php endif ?>
      <form method="post" action="<?= e(url($accessBase . '/delete')) ?>" onsubmit="return confirm('Delete this login? The <?= e($who) ?> record is kept; only portal access is removed.')"><?= csrf_field() ?><button class="btn btn--danger" type="submit"><i data-lucide="trash-2"></i>Delete Login</button></form>
    </div>
  </details>
</div>
