<?php
$amount = number_format((float) $notice['outstanding'], ((float) $notice['outstanding'] == floor((float) $notice['outstanding'])) ? 0 : 2);
?>
<main class="error-card restricted-card" role="alertdialog" aria-modal="true" aria-labelledby="restrictedTitle" aria-describedby="restrictedText">
  <span class="restricted-card__icon" aria-hidden="true"><?= $notice['fee'] ? '&#128179;' : '&#9888;' ?></span>
  <h1 id="restrictedTitle"><?= $notice['fee'] ? 'Please submit your fee' : 'Your account is suspended' ?></h1>
  <?php if ($notice['fee']): ?>
    <p id="restrictedText">Your account has been suspended because of unpaid fees<?= $notice['months'] ? ' for <strong>' . (int) $notice['months'] . ' month' . ($notice['months'] === 1 ? '' : 's') . '</strong>' : '' ?>.</p>
    <?php if ($notice['outstanding'] > 0): ?><div class="restricted-card__amount"><span>Amount pending</span><strong><?= e($notice['currency']) ?> <?= e($amount) ?></strong></div><?php endif ?>
    <p>Submit your pending fee to the institute to restore full access. Your account reopens automatically once the dues are cleared.</p>
  <?php else: ?>
    <p id="restrictedText">You can't use your account right now.<?= $notice['reason'] ? ' Reason: ' . e($notice['reason']) . '.' : '' ?> Please contact the institute administration.</p>
  <?php endif ?>
  <?php if (!empty($institute['phone'])): ?><p class="restricted-card__contact"><?= e($institute['name'] ?? 'Institute') ?> &middot; <?= e($institute['phone']) ?></p><?php endif ?>
  <div class="restricted-card__actions">
    <?php if ($notice['student_id']): ?><a href="<?= e(url('/portal/student/fees')) ?>">View fee details</a><?php endif ?>
    <form method="post" action="<?= e(url(app()->session()->has('impersonator_user_id') ? '/impersonation/stop' : '/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= app()->session()->has('impersonator_user_id') ? 'Stop viewing as this user' : 'Sign out' ?></button></form>
  </div>
</main>
<style>
.restricted-card__icon{display:grid;place-items:center;width:64px;height:64px;margin:0 auto;border-radius:50%;background:#fff1f2;font-size:30px}
.restricted-card__amount{display:flex;flex-direction:column;gap:4px;margin:0 auto 18px;padding:14px 18px;border:1px solid #f4c7cf;border-radius:14px;background:#fff5f6}
.restricted-card__amount span{color:#8a6570;font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.restricted-card__amount strong{color:#b42348;font-size:28px}
.restricted-card__contact{font-weight:600}
.restricted-card__actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px}
.restricted-card__actions form{margin:0}
.restricted-card__actions button{display:inline-flex;border:1px solid #d9c3cb;background:#fff;color:#6f1238;border-radius:10px;padding:12px 18px;font:inherit;font-weight:600;cursor:pointer}
</style>
