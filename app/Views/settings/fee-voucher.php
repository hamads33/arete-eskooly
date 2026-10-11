<?php
$styles[] = 'css/fee-workspace.css?v=104';
$selected = $voucherSettings['bank_ids'];
$admin = can('*');
?>
<main class="main"><div class="main__inner"><div class="module-page ek-fees">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>General Settings</span><i data-lucide="chevron-right"></i><span>Fee Voucher Settings</span></nav><h1>Fee Voucher Settings</h1><p>Instructions, bank accounts and the late fine printed on every fee voucher / challan.</p></div>
<div class="filter-actions"><a class="btn btn--primary" href="<?= url('/admin/settings/fee-voucher/preview?copies=three_copy') ?>" target="_blank" rel="noopener"><i data-lucide="eye"></i>Preview test invoice</a><a class="btn btn--secondary" href="<?= url('/admin/settings/fee-voucher/preview?copies=single') ?>" target="_blank" rel="noopener">Single copy (landscape)</a><a class="btn btn--secondary" href="<?= url('/admin/settings/fee-voucher/preview?copies=single&orientation=P') ?>" target="_blank" rel="noopener">Single copy (portrait)</a></div></header>

<section class="ek-card ek-wide"><header class="ek-card__head ek-card__head--left"><h2>Voucher content</h2><p>Saved settings apply to Generate Fees Invoice and to the Fee Voucher button on Collect Fees.</p></header>
<form class="ek-voucher" method="post" action="<?= url('/admin/settings/fee-voucher') ?>"><?= csrf_field() ?>
  <div class="module-field"><label for="voucher_instructions">Instructions printed on the voucher</label><textarea class="module-input" id="voucher_instructions" name="voucher_instructions" rows="6" maxlength="3000" placeholder="e.g.&#10;1. Fee must be paid by the 10th of every month.&#10;2. A fine applies after the due date.&#10;3. Keep the student copy as proof of payment." <?= $admin ? '' : 'disabled' ?>><?= e($voucherSettings['instructions']) ?></textarea><small>Each new line prints on its own line. Wrap words in **double asterisks** to print them in bold. Up to 3,000 characters.</small></div>
  <div class="module-field" style="max-width:280px"><label for="voucher_default_fine">Default fine after due date</label><input class="module-input" type="number" min="0" max="1000000" step="1" id="voucher_default_fine" name="voucher_default_fine" value="<?= e($voucherSettings['default_fine'] + 0) ?>" <?= $admin ? '' : 'disabled' ?>><small>Pre-filled on Generate Fees Invoice; printed as "Payable after due date".</small></div>
  <fieldset class="ek-copies"><legend>Bank accounts to print</legend>
  <?php if (!$banks): ?><p class="ek-note">No bank accounts yet — add one below.</p><?php endif ?>
  <?php foreach ($banks as $b): ?><label class="ek-switch"><input type="checkbox" name="bank_ids[]" value="<?= (int) $b['id'] ?>" <?= !$selected || in_array((int) $b['id'], $selected, true) ? 'checked' : '' ?> <?= $admin ? '' : 'disabled' ?>><span></span><?= e($b['bank_name']) ?> · <?= e($b['account_number']) ?></label><?php endforeach ?>
  </fieldset>
  <?php if ($admin): ?><div class="ek-submit ek-submit--row"><button class="btn btn--primary"><i data-lucide="save"></i>Save settings</button><a class="btn btn--ghost" href="<?= url('/admin/settings/fee-voucher/preview?copies=three_copy') ?>" target="_blank" rel="noopener"><i data-lucide="eye"></i>Preview test invoice</a></div><?php endif ?>
</form></section>

<?php if ($admin): ?>
<section class="ek-card ek-wide"><header class="ek-card__head ek-card__head--left"><h2>Add bank account</h2><p>Shown in the "Pay at" box on vouchers. Manage logos and delete accounts in <a href="<?= url('/admin/settings/fee-invoice-accounts') ?>">Accounts For Fees Invoice</a>.</p></header>
<form class="ek-voucher" method="post" action="<?= url('/admin/settings/fee-voucher/bank') ?>"><?= csrf_field() ?>
  <div class="ek-grid"><div class="module-field"><label for="bank_name">Bank name *</label><input class="module-input" id="bank_name" name="bank_name" maxlength="120" required placeholder="e.g. Meezan Bank"></div><div class="module-field"><label for="account_number">Account number / IBAN *</label><input class="module-input" id="account_number" name="account_number" maxlength="100" required></div><div class="module-field"><label for="bank_address">Branch / address</label><input class="module-input" id="bank_address" name="bank_address" maxlength="2000"></div></div>
  <div class="module-field"><label for="bank_instructions">Account name / note (optional)</label><input class="module-input" id="bank_instructions" name="instructions" maxlength="2000" placeholder="e.g. Title: Arete Coaching Center"></div>
  <div class="ek-submit ek-submit--row"><button class="btn btn--secondary"><i data-lucide="plus"></i>Add bank account</button></div>
</form></section>
<?php endif ?>
</div></div></main>
