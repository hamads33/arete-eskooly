<?php $copySet = match ($voucherSettings['copies'] ?? 'single') { 'three_copy' => ['bank', 'student', 'institute'], 'student_institute' => ['student', 'institute'], default => ['student'] };
$lines = $particulars ?: [['label' => 'Admission Fee', 'amount' => 0], ['label' => 'Monthly Tuition Fee', 'amount' => 0]];
while (count($lines) < count($particulars) + 3) $lines[] = ['label' => '', 'amount' => 0]; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><span class="module-eyebrow">Fees</span><h1>Dummy Fee Invoice</h1><p>Print a fee invoice for a prospective student. Nothing is saved: it does not appear in fee reports, balances or defaulters. When the student pays, admit them and collect the fee from Collect Fees.</p></div></header>
<form class="module-card" method="post" action="<?= url('/admin/fees/dummy-invoice') ?>" target="_blank"><?= csrf_field() ?>
<div class="module-card__header"><div><h2>Student details</h2></div></div>
<div class="module-card__body module-form"><div class="module-form-grid module-form-grid--three">
<div class="module-field"><label for="d_name">Student name</label><input class="module-input" id="d_name" name="student_name" maxlength="120" required></div>
<div class="module-field"><label for="d_father">Father name</label><input class="module-input" id="d_father" name="father_name" maxlength="120"></div>
<div class="module-field"><label for="d_class">Class</label><input class="module-input" id="d_class" name="class_name" maxlength="80"></div>
<div class="module-field"><label for="d_reg">Registration no.</label><input class="module-input" id="d_reg" name="registration_no" maxlength="40" placeholder="NEW ADMISSION"></div>
<div class="module-field"><label for="d_month">Fee month</label><input class="module-input" type="month" id="d_month" name="month" value="<?= date('Y-m') ?>" required></div>
<div class="module-field"><label for="d_due">Due date</label><input class="module-input" type="date" id="d_due" name="due_date"></div>
</div></div>
<div class="module-card__header"><div><h2>Fee particulars</h2><p>Lines left at 0 are not printed.</p></div></div>
<div class="module-card__body module-form"><div class="module-form-grid">
<?php foreach ($lines as $line): ?>
<div class="module-field"><input class="module-input" name="item_label[]" maxlength="60" value="<?= e($line['label']) ?>" placeholder="Particular"></div>
<div class="module-field"><input class="module-input" type="number" step="0.01" min="0" name="item_amount[]" value="<?= (float) $line['amount'] > 0 ? e((string) $line['amount']) : '' ?>" placeholder="Amount"></div>
<?php endforeach ?>
</div><div class="module-form-grid module-form-grid--three">
<div class="module-field"><label for="d_disc">Discount</label><input class="module-input" type="number" step="0.01" min="0" id="d_disc" name="discount" value="0"></div>
<div class="module-field"><label for="d_prev">Previous balance</label><input class="module-input" type="number" step="0.01" min="0" id="d_prev" name="previous_balance" value="0"></div>
<div class="module-field"><label for="d_fine">Fine after due date</label><input class="module-input" type="number" step="0.01" min="0" id="d_fine" name="fine_after_due" value="<?= e((string) ($voucherSettings['default_fine'] ?? 0)) ?>"></div>
<div class="module-field"><label for="d_bank">Select bank</label><select class="module-select" name="bank_id" id="d_bank"><option value="0">All configured accounts</option><?php foreach ($banks as $bank): ?><option value="<?= (int) $bank['id'] ?>"><?= e($bank['bank_name'] . ' · ' . $bank['account_number']) ?></option><?php endforeach ?></select></div>
<div class="module-field"><label for="d_orient">Page layout for a single copy</label><select class="module-select" name="orientation" id="d_orient"><option value="L">Landscape</option><option value="P">Portrait</option></select></div>
</div>
<fieldset class="ek-copies"><legend>Copies</legend><?php foreach (['bank' => 'Bank Copy', 'student' => 'Student Copy', 'institute' => 'Institute Copy'] as $key => $label): ?><label class="ek-switch"><input type="checkbox" name="copy[]" value="<?= $key ?>" <?= in_array($key, $copySet, true) ? 'checked' : '' ?>><span></span><?= $label ?></label><?php endforeach ?></fieldset>
<div class="filter-bar"><button class="btn btn--primary" type="submit"><i data-lucide="printer"></i>Print dummy invoice</button><button class="btn btn--secondary" type="submit" name="download" value="1"><i data-lucide="download"></i>Download PDF</button></div>
</div></form>
</div></div></main>
