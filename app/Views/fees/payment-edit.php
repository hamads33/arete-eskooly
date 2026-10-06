<?php $p = $payment; $cur = $institute['currency_symbol'] ?? 'Rs'; $ret = str_starts_with($return, '/admin/') ? $return : '/admin/fees/collect?student_id=' . (int) $p['student_id'] . '&month=' . rawurlencode((string) $p['fee_month']); ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><h1>Edit Fee Payment</h1><p><?= e($p['student_name']) ?> (<?= e($p['registration_no']) ?>) · <?= e($p['fee_month']) ?> · Receipt <?= e($p['receipt_no']) ?></p></div><a class="btn btn--secondary" href="<?= url($ret) ?>">Back</a></header>
<section class="module-card"><header class="module-card__header"><div><h2>Payment Details</h2><p>Invoice <?= e($p['invoice_no']) ?> total <?= e($cur) ?> <?= number_format((float) $p['total_amount'], 2) ?>. The invoice balance is recalculated when you save, and the change is recorded in the audit log.</p></div></header><div class="module-card__body">
<?php if ($p['reversed_at']): ?><div class="compact-empty"><h3>This payment was reversed</h3><p><?= e($p['reversal_reason'] ?? '') ?></p></div><?php else: ?>
<form method="post" action="<?= url('/admin/fees/payments/' . $p['id']) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>">
<div class="module-form-grid module-form-grid--three">
<div class="module-field"><label>Payment Date <span class="required">*</span></label><input class="module-input" type="date" name="payment_date" value="<?= e($p['payment_date']) ?>" required></div>
<div class="module-field"><label>Amount <span class="required">*</span></label><input class="module-input" type="number" name="amount" min="0.01" step="0.01" value="<?= e($p['amount']) ?>" required></div>
<div class="module-field"><label>Method <span class="required">*</span></label><select class="module-select" name="payment_method" required><?php $ms = $methods; if (!in_array($p['payment_method'], $ms, true)) $ms[] = $p['payment_method']; foreach ($ms as $m): ?><option <?= $m === $p['payment_method'] ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach ?></select></div>
<div class="module-field"><label>Reference</label><input class="module-input" name="reference" maxlength="100" value="<?= e($p['reference'] ?? '') ?>"></div>
<div class="module-field field--wide"><label>Remarks</label><input class="module-input" name="remarks" maxlength="255" value="<?= e($p['remarks'] ?? '') ?>"></div>
</div>
<div class="module-card__footer"><button class="btn btn--primary" type="submit">Save Changes</button></div></form>
<form method="post" action="<?= url('/admin/fees/payments/' . $p['id'] . '/reverse') ?>" class="row-actions"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>"><input class="module-input" name="reason" required placeholder="Reason for reversing this payment"><button class="btn btn--danger" type="submit">Reverse Payment</button></form>
<?php endif ?>
</div></section></div></div></main>
