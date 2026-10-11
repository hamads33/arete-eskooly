<?php
$styles[] = 'css/fee-workspace.css?v=104';
$scripts[] = 'js/fee-workspace.js?v=2';
$currency = trim((string) ($institute['currency_symbol'] ?? $institute['currency'] ?? 'PKR'));
$money = static fn($v): string => number_format((float) $v, 2);
$monthLabel = date('F Y', strtotime($selectedMonth . '-01'));

// What the Fees Collection card shows: the saved invoice when there is one, otherwise a preview of
// what it will contain. Particulars stay editable (like eSkooly) until the first payment is taken.
$editable = false; $lines = []; $fine = 0.0; $discount = 0.0; $previous = 0.0; $alreadyPaid = 0.0; $discountLabel = 'Discount';
if ($selectedStudent) {
    $discType = ($selectedStudent['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
    $discValue = (float) ($selectedStudent['discount_value'] ?? $selectedStudent['discount_in_fee'] ?? 0);
    $discountLabel = $discType === 'fixed' ? 'Discount in Fee (fixed)' : 'Discount in Fee ' . rtrim(rtrim(number_format($discValue, 2), '0'), '.') . ' %';
    if ($invoice) {
        $editable = !$invoice['voided_at'] && (!$payments || can('*'));
        foreach ($invoiceItems as $item) {
            if (strcasecmp((string) $item['particular'], 'Fine') === 0) { $fine += (float) $item['amount']; continue; }
            $lines[] = ['particular' => $item['particular'], 'amount' => (float) $item['amount']];
        }
        if ($editable && $preview) { // show particulars from the structure that are not on the invoice yet, at 0
            $have = array_map('strtolower', array_column($lines, 'particular'));
            foreach ($preview['lines'] as $l) if (strcasecmp((string) $l['particular'], 'Fine') !== 0 && !in_array(strtolower($l['particular']), $have, true)) $lines[] = ['particular' => $l['particular'], 'amount' => 0.0];
        }
        $discount = (float) $invoice['discount']; $previous = (float) $invoice['previous_balance']; $alreadyPaid = (float) $invoice['paid_amount'];
    } elseif ($preview) {
        $editable = true; $discount = (float) $preview['discount']; $previous = (float) $preview['previous_balance'];
        foreach ($preview['lines'] as $l) { if (strcasecmp((string) $l['particular'], 'Fine') === 0) { $fine += (float) $l['amount']; continue; } $lines[] = $l; }
    }
}
$grandTotal = $invoice && !$editable ? (float) $invoice['total_amount'] : max(0, array_sum(array_column($lines, 'amount')) + $fine - $discount + $previous);
$remaining = $invoice && !$editable ? (float) $invoice['remaining_amount'] : max(0, $grandTotal - $alreadyPaid);
?>
<main class="main"><div class="main__inner"><div class="module-page fee-collection-page ek-fees<?= $selectedStudent ? ' has-student' : '' ?>">
<nav class="ek-crumbs" aria-label="Breadcrumb"><a href="<?= url('/admin/dashboard') ?>"><i data-lucide="banknote"></i>Fees</a><i data-lucide="chevron-right"></i><span>Collect Fees</span><?php if ($selectedStudent): ?><em>Student Wise</em><?php endif ?></nav>

<?php if (!$selectedStudent): ?>
<section class="ek-card ek-card--narrow fee-student-picker">
  <header class="ek-card__head"><h1>Collect Fees</h1><p>Choose how you want to collect fees today.</p></header>
  <div class="ek-tabs" role="tablist"><span class="is-active" role="tab" aria-selected="true"><i data-lucide="user-round"></i>Student Wise</span><a role="tab" href="<?= url('/admin/fees/vouchers') ?>"><i data-lucide="files"></i>Fee Vouchers</a><a role="tab" href="<?= url('/admin/fees/defaulters') ?>"><i data-lucide="circle-dollar-sign"></i>Outstanding</a></div>
  <form class="fee-search-toolbar ek-search" method="get" action="<?= url('/admin/fees/collect') ?>" data-fee-search><input type="hidden" name="browse" value="1">
    <div class="module-field fee-search-field"><label for="student_query"><i data-lucide="search"></i>Search student <span class="required">*</span></label><input class="module-input" id="student_query" name="student_query" value="<?= e($query) ?>" placeholder="Type student name or registration number" autocomplete="off" aria-controls="feeLookupResults"></div>
    <div class="module-field"><label for="fee_month">Fee month</label><input class="module-input" id="fee_month" name="month" type="month" value="<?= e($selectedMonth) ?>" required></div>
    <details class="fee-search-options" <?= $selectedClass || $onlyDue ? 'open' : '' ?>><summary><i data-lucide="sliders-horizontal"></i>Class &amp; pending fee filters</summary><div class="fee-search-options__body"><div class="module-field"><label for="fee_class">Class</label><select class="module-select" id="fee_class" name="class"><option value="0">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $selectedClass === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div><label class="fee-due-toggle"><input type="checkbox" name="only_due" value="1" <?= $onlyDue ? 'checked' : '' ?>><span><i data-lucide="alert-circle"></i>Pending fees only</span></label></div></details>
    <div class="ek-search__actions"><button class="btn btn--primary" type="submit"><i data-lucide="search"></i>Find Student</button><?php if ($query !== '' || $selectedClass || $onlyDue): ?><a class="btn btn--ghost" href="<?= url('/admin/fees/collect?month=' . rawurlencode($selectedMonth)) ?>">Clear</a><?php endif ?></div>
  </form>
  <p class="fee-search-status" role="status" data-fee-search-status>Start typing a name or registration number, or press Find Student to browse.</p>
  <div id="feeLookupResults" data-fee-results <?= $query === '' && !$selectedClass && !$onlyDue && empty($browseStudents) ? 'hidden' : '' ?>><?php if ($studentResults): ?><p class="fee-results-count"><?= count($studentResults) === 30 ? 'First 30' : count($studentResults) ?> student<?= count($studentResults) === 1 ? '' : 's' ?><?= $onlyDue ? ' with pending fees' : '' ?><?= $selectedClass ? ' in this class' : '' ?></p><div class="fee-student-results ek-results" aria-label="Student search results"><?php foreach ($studentResults as $s): $owes = (float) ($s['fee_remaining'] ?? 0) > 0; ?><a class="fee-student-result" href="<?= url('/admin/fees/collect?student_id=' . $s['id'] . '&month=' . rawurlencode($selectedMonth)) ?>"><span class="fee-student-copy"><strong><?= e($s['registration_no']) ?> – <?= e($s['student_name']) ?> – <?= e($s['class_name']) ?></strong></span><span class="fee-student-balance<?= $owes ? ' is-due' : '' ?>"><small><?= $owes ? 'Outstanding' : 'Paid up' ?></small><strong><?= e($currency) ?> <?= $money($s['fee_remaining'] ?? 0) ?></strong></span><i data-lucide="chevron-right"></i></a><?php endforeach ?></div><?php else: ?><div class="compact-empty"><h3>No students found</h3><p><?= $onlyDue ? 'No students in this filter currently owe any fees.' : 'Try another name, registration number or class.' ?></p></div><?php endif ?></div>
</section>
<?php else: ?>

<?php if ($pendingOtherMonths): $pendingTotal = array_sum(array_column($pendingOtherMonths, 'remaining_amount')); ?>
<section class="fee-arrears-banner ek-wide" role="alert"><span class="fee-arrears-banner__icon"><i data-lucide="triangle-alert"></i></span><div class="fee-arrears-banner__body"><strong><?= e($selectedStudent['student_name']) ?> also has <?= count($pendingOtherMonths) ?> other month<?= count($pendingOtherMonths) === 1 ? '' : 's' ?> pending — <?= e($currency) ?> <?= $money($pendingTotal) ?> in total.</strong><div class="fee-arrears-chips"><?php foreach ($pendingOtherMonths as $p): $overdue = $p['due_date'] && $p['due_date'] < date('Y-m-d'); ?><a class="fee-arrears-chip<?= $overdue ? ' is-overdue' : '' ?>" href="<?= url('/admin/fees/collect?student_id=' . (int) $selectedStudent['id'] . '&month=' . $p['fee_month']) ?>"><?= e(date('M Y', strtotime($p['fee_month'] . '-01'))) ?><span><?= e($currency) ?> <?= $money($p['remaining_amount']) ?></span></a><?php endforeach ?></div><p>Unpaid earlier months are carried into this month's invoice as <strong>Previous Balance</strong> when it is first created.</p></div></section>
<?php endif ?>

<section class="ek-card ek-collect fee-selected-student">
  <header class="ek-card__head"><h1>Fees Collection</h1><p><span class="required">*</span> are required fields.</p><a class="btn btn--ghost btn--sm ek-change" href="<?= url('/admin/fees/collect?month=' . rawurlencode($selectedMonth)) ?>"><i data-lucide="repeat"></i>Change Student</a></header>
  <div class="ek-tiles">
    <div class="ek-tile"><span><i data-lucide="id-card"></i>Registration</span><strong><?= e($selectedStudent['registration_no']) ?></strong></div>
    <div class="ek-tile"><span><i data-lucide="user-round"></i>Student Name</span><strong><?= e($selectedStudent['student_name']) ?></strong></div>
    <div class="ek-tile"><span><i data-lucide="users-round"></i>Guardian Name</span><strong><?= e($selectedStudent['father_name'] ?: '—') ?></strong></div>
    <div class="ek-tile"><span><i data-lucide="graduation-cap"></i>Class</span><strong><?= e($selectedStudent['class_name']) ?></strong></div>
  </div>

  <div class="ek-month-row"><form class="ek-month" method="get" action="<?= url('/admin/fees/collect') ?>"><input type="hidden" name="student_id" value="<?= (int) $selectedStudent['id'] ?>"><div class="module-field"><label for="invoice_month"><i data-lucide="calendar-days"></i>Fees month <span class="required">*</span></label><input class="module-input" id="invoice_month" type="month" name="month" value="<?= e($selectedMonth) ?>" required onchange="this.form.submit()"></div><noscript><button class="btn btn--secondary">View</button></noscript></form><?php if (!($invoice && $invoice['voided_at'])): ?><div class="module-field ek-date"><label for="payment_date"><i data-lucide="calendar"></i>Date <span class="required">*</span></label><input class="module-input" id="payment_date" type="date" name="payment_date" form="ekCollectForm" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div><?php endif ?></div>

  <?php if ($invoice && $invoice['voided_at']): ?>
    <div class="notice-panel"><strong>Invoice <?= e($invoice['invoice_no']) ?> is voided.</strong> <?= e($invoice['void_reason'] ?: 'No reason recorded.') ?></div>
  <?php else: ?>
  <form class="fee-payment-form ek-pay" id="ekCollectForm" method="post" action="<?= url('/admin/fees/payment') ?>" data-ek-collect data-currency="<?= e($currency) ?>" data-previous="<?= e($previous) ?>" data-paid="<?= e($alreadyPaid) ?>" data-editable="<?= $editable ? '1' : '0' ?>" data-fixed-remaining="<?= e(number_format($remaining, 2, '.', '')) ?>">
    <?= csrf_field() ?><input type="hidden" name="registration" value="<?= e($selectedStudent['registration_no']) ?>"><input type="hidden" name="month" value="<?= e($selectedMonth) ?>">
    <?php if ($invoice): ?><p class="ek-invoice-ref"><i data-lucide="file-text"></i>Invoice <strong><?= e($invoice['invoice_no']) ?></strong> · <?= e(ucwords(str_replace('_', ' ', $invoice['status']))) ?><?= $invoice['due_date'] ? ' · Due ' . e(date('d M Y', strtotime($invoice['due_date']))) : '' ?></p><?php endif ?>
    <div class="ek-table-wrap"><table class="ek-table no-table-tools"><thead><tr><th>Sr.</th><th>Particulars</th><th>Amount</th></tr></thead><tbody>
      <?php $sr = 0; foreach ($lines as $k => $l): $sr++; ?><tr><td><?= $sr ?></td><td><?= e(strtoupper((string) $l['particular'])) ?><?php if ($editable): ?><input type="hidden" name="item_label[<?= $k ?>]" value="<?= e($l['particular']) ?>"><?php endif ?></td><td><?php if ($editable): ?><input class="ek-amount" type="number" name="item_amount[<?= $k ?>]" value="<?= e($l['amount'] + 0) ?>" min="0" step="0.01" inputmode="decimal" data-ek-line aria-label="<?= e($l['particular']) ?> amount"><?php else: ?><span class="ek-fixed"><?= $money($l['amount']) ?></span><?php endif ?></td></tr><?php endforeach ?>
      <tr><td><?= ++$sr ?></td><td>FINE</td><td><?php if ($editable): ?><input class="ek-amount" type="number" name="fine_amount" value="<?= e($fine + 0) ?>" min="0" step="0.01" inputmode="decimal" data-ek-line aria-label="Fine amount"><?php else: ?><span class="ek-fixed"><?= $money($fine) ?></span><?php endif ?></td></tr>
      <tr class="ek-row-muted"><td><?= ++$sr ?></td><td>Previous Balance</td><td><?php if ($editable): ?><input class="ek-amount" type="number" name="previous_balance" value="<?= e($previous + 0) ?>" min="0" step="0.01" inputmode="decimal" data-ek-previous aria-label="Previous balance"><?php else: ?><span class="ek-fixed"><?= $money($previous) ?></span><?php endif ?></td></tr>
      <tr class="ek-row-muted"><td><?= ++$sr ?></td><td><?php if ($editable): ?>Discount<small class="ek-discount-default">Student default: <?= e($discType === 'fixed' ? $currency . ' ' . $money($discValue) : rtrim(rtrim(number_format($discValue, 2), '0'), '.') . ' %') ?></small><?php else: ?><?= e($discountLabel) ?><?php endif ?></td><td><?php if ($editable): ?><span class="ek-discount"><select name="discount_mode" data-ek-discount-mode aria-label="Discount type"><option value="amount" selected><?= e($currency) ?></option><option value="percent">%</option></select><input class="ek-amount" type="number" name="discount_amount" value="<?= e($discount + 0) ?>" min="0" step="0.01" inputmode="decimal" data-ek-discount aria-label="Discount"></span><small class="ek-discount-calc" data-ek-discount-calc></small><?php else: ?><span class="ek-fixed">- <?= $money($discount) ?></span><?php endif ?></td></tr>
      <?php if ($alreadyPaid > 0): ?><tr class="ek-row-paid"><td><?= ++$sr ?></td><td>Already Paid</td><td><span class="ek-fixed">- <?= $money($alreadyPaid) ?></span></td></tr><?php endif ?>
    </tbody></table></div>
    <?php if (!$editable && $invoice && !$invoice['voided_at']): ?><p class="ek-note"><i data-lucide="lock"></i>Particulars are locked because a payment is already recorded. An administrator can change them, or use <strong>Edit</strong> in Payment History below.</p><?php elseif ($editable && $alreadyPaid > 0): ?><p class="ek-note"><i data-lucide="info"></i>You can change any amount. The total cannot go below the <?= e($currency) ?> <?= $money($alreadyPaid) ?> already paid, and the change is recorded in the audit log.</p><?php endif ?>

    <div class="ek-totals">
      <div class="ek-total"><span>Total</span><strong data-ek-total><?= $money($grandTotal) ?></strong></div>
      <div class="ek-total ek-total--deposit"><label for="payment_amount">Deposit <span class="required">*</span></label><?php if ($remaining > 0): ?><input class="module-input" id="payment_amount" name="payment_amount" type="number" min="0.01" max="<?= e(number_format($invoice && !$editable ? (float) $invoice['remaining_amount'] : $remaining, 2, '.', '')) ?>" step="0.01" inputmode="decimal" required placeholder="0" data-ek-deposit><button class="ek-link" type="button" data-fee-pay-full>Pay full balance</button><?php else: ?><strong class="ek-paid">Paid in full</strong><?php endif ?></div>
      <div class="ek-total ek-total--due"><span>Due Balance</span><strong data-ek-due data-fee-after-payment><?= $money($remaining) ?></strong></div>
    </div>
    <?php if ($remaining > 0): ?>
    <details class="ek-more"><summary><i data-lucide="sliders-horizontal"></i>Payment method, reference &amp; remarks</summary><div class="module-form-grid module-form-grid--three"><div class="module-field"><label for="payment_method">Payment method</label><select class="module-select" id="payment_method" name="payment_method"><?php foreach ($paymentMethods as $method): ?><option><?= e($method) ?></option><?php endforeach ?></select></div><div class="module-field"><label for="payment_reference">Reference</label><input class="module-input" id="payment_reference" name="reference" maxlength="100" placeholder="Receipt / transaction no."></div><div class="module-field"><label for="payment_remarks">Remarks</label><input class="module-input" id="payment_remarks" name="remarks" maxlength="255" placeholder="Optional"></div></div></details>
    <div class="ek-submit"><button class="btn btn--primary btn--lg" type="submit"><i data-lucide="check"></i>Submit Fees</button></div>
    <?php else: ?><input type="hidden" name="payment_method" value="<?= e($paymentMethods[0] ?? 'Cash') ?>"><?php endif ?>
  </form>
  <?php endif ?>

  <div class="ek-extras filter-actions">
    <?php if ($invoice): ?><a class="btn btn--secondary" href="<?= e(url('/admin/reports/fee-invoice/' . (int) $invoice['id'] . '/pdf')) ?>" target="_blank" rel="noopener"><i data-lucide="file-text"></i>Fee Voucher (PDF)</a><a class="btn btn--ghost" href="<?= e(url('/admin/reports/fee-invoice/' . (int) $invoice['id'] . '/pdf?download=1')) ?>"><i data-lucide="download"></i>Download Voucher</a><?php
      $waPhone = $selectedStudent['father_mobile'] ?: ($selectedStudent['mobile'] ?? '');
      $waRemaining = (float) $invoice['remaining_amount'];
      $waMessage = 'Dear ' . ($selectedStudent['father_name'] ?: 'Parent') . ', fee invoice ' . $invoice['invoice_no'] . ' for ' . $selectedStudent['student_name'] . ' (' . $selectedStudent['class_name'] . ') for ' . $invoice['fee_month'] . '. ' . ($waRemaining > 0 ? 'Outstanding: ' . $currency . ' ' . $money($waRemaining) . '.' : 'This invoice is fully paid. Thank you.');
      $waLabel = 'Send on WhatsApp';
      require BASE_PATH . '/app/Views/partials/whatsapp-button.php';
    else: ?><form method="post" action="<?= url('/admin/fees/invoice') ?>"><?= csrf_field() ?><input type="hidden" name="registration" value="<?= e($selectedStudent['registration_no']) ?>"><input type="hidden" name="month" value="<?= e($selectedMonth) ?>"><button class="btn btn--secondary"><i data-lucide="file-plus-2"></i>Generate Invoice Only</button></form><a class="btn btn--ghost" href="<?= url('/admin/fees/vouchers?scope=student&student_id=' . (int) $selectedStudent['id'] . '&month=' . rawurlencode($selectedMonth)) ?>"><i data-lucide="files"></i>Print Voucher</a><?php endif ?>
  </div>
</section>

<?php if ($payments): ?>
<section class="ek-card ek-wide fee-payment-history"><header class="ek-card__head ek-card__head--left"><h2>Payment History</h2><p><?= e($monthLabel) ?> · invoice <?= e($invoice['invoice_no']) ?></p></header><div class="table-scroll"><table class="data-table"><thead><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Method</th><th>Status / Action</th></tr></thead><tbody><?php foreach ($payments as $p): ?><tr><td><a href="<?= url('/admin/fees/receipt/' . $p['id']) ?>"><?= e($p['receipt_no']) ?></a> &middot; <a href="<?= e(url('/admin/reports/fee-receipt/' . (int) $p['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Print Slip</a></td><td><?= e($p['payment_date']) ?></td><td><?= e($currency) ?> <?= $money($p['amount']) ?></td><td><?= e($p['payment_method']) ?></td><td><?php if ($p['reversed_at']): ?><span class="student-status student-status--inactive">Reversed</span><br><small><?= e($p['reversal_reason']) ?></small><?php else: ?><div class="row-actions"><?php if (can('*')): ?><a class="btn btn--secondary btn--sm" href="<?= url('/admin/fees/payments/' . $p['id'] . '/edit') ?>">Edit</a><?php endif ?><form class="credential-change-form" method="post" action="<?= url('/admin/fees/payments/' . $p['id'] . '/reverse') ?>"><?= csrf_field() ?><input class="module-input" name="reason" required placeholder="Reversal reason" aria-label="Reversal reason"><button class="btn btn--danger btn--sm">Reverse</button></form></div><?php endif ?></td></tr><?php endforeach ?></tbody></table></div></section>
<?php endif ?>
<?php endif ?>
</div></div></main>
