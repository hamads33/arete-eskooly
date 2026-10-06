<?php $t = $transaction; $type = $t['transaction_type']; $ret = str_starts_with($return, '/admin/') ? $return : '/admin/accounts/statement'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><h1>Edit <?= ucfirst($type) ?></h1><p>Correct this posted <?= e($type) ?> entry. Every change is recorded in the audit log.</p></div><a class="btn btn--secondary" href="<?= url($ret) ?>">Back</a></header>
<section class="module-card"><header class="module-card__header"><h2>Transaction #<?= (int) $t['id'] ?></h2></header><div class="module-card__body">
<form method="post" action="<?= url('/admin/accounts/transactions/' . $t['id']) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>">
<div class="module-form-grid module-form-grid--three">
<div class="module-field"><label>Account Head <span class="required">*</span></label><select class="module-select" name="account_head" required><?php foreach ($heads as $head): if (!in_array($head['type'], [$type, 'both'], true)) continue; ?><option value="<?= $head['id'] ?>" <?= (int) $t['account_head_id'] === (int) $head['id'] ? 'selected' : '' ?>><?= e($head['name']) ?></option><?php endforeach ?></select></div>
<div class="module-field"><label>Date <span class="required">*</span></label><input class="module-input" name="date" type="date" value="<?= e($t['transaction_date']) ?>" required></div>
<div class="module-field"><label>Description <span class="required">*</span></label><input class="module-input" name="description" maxlength="255" value="<?= e($t['description']) ?>" required></div>
<div class="module-field"><label>Amount <span class="required">*</span></label><input class="module-input" name="amount" type="number" min="0.01" step="0.01" value="<?= e($t['amount']) ?>" required></div>
<div class="module-field"><label>Reference</label><input class="module-input" name="reference" maxlength="50" value="<?= e($t['reference_type'] ?? '') ?>"></div>
</div>
<div class="module-card__footer"><button class="btn btn--primary" type="submit">Save Changes</button></div></form>
<form method="post" action="<?= url('/admin/accounts/transactions/' . $t['id'] . '/delete') ?>" onsubmit="const r=prompt('Reason for reversing this <?= e($type) ?> entry?');if(r===null)return false;this.reason.value=r;return true"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($ret) ?>"><input type="hidden" name="reason"><button class="btn btn--danger" type="submit">Reverse / Delete Entry</button></form>
</div></section></div></div></main>
