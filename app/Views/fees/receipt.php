<?php
$styles[] = 'css/fee-workspace.css?v=4';
$money = static fn($v): string => number_format((float) $v, 2);
$cur = trim((string) ($institute['currency_symbol'] ?? $institute['currency'] ?? 'PKR'));
?>
<main class="main"><div class="main__inner"><div class="module-page ek-fees">
<nav class="ek-crumbs" aria-label="Breadcrumb"><a href="<?= url('/admin/fees/collect') ?>"><i data-lucide="banknote"></i>Fees</a><i data-lucide="chevron-right"></i><a href="<?= url('/admin/fees/receipt') ?>">Fees Paid Slip</a><i data-lucide="chevron-right"></i><span><?= $receipt ? e($receipt['receipt_no']) : 'Receipt' ?></span></nav>
<?php if (!$receipt): ?>
<section class="ek-card"><div class="compact-empty"><h3>Select a payment from Collect Fees to view its receipt.</h3><p><a class="btn btn--primary" href="<?= url('/admin/fees/collect') ?>">Go to Collect Fees</a></p></div></section>
<?php else:
  $remaining = (float) ($receipt['remaining_amount'] ?? 0);
  $reversed = !empty($receipt['reversed_at']);
  $statusClass = $reversed ? 'reversed' : ($remaining > 0 ? 'partial' : 'paid');
  $statusText = $reversed ? 'This payment was reversed' : ($remaining > 0 ? 'Partially Paid' : 'Fully Paid');
  $statusIcon = $reversed ? 'undo-2' : ($remaining > 0 ? 'circle-alert' : 'circle-check');
  $monthLabel = date('F, Y', strtotime($receipt['fee_month'] . '-01'));
?>
<section class="ek-card ek-receipt">
  <div class="ek-status ek-status--<?= $statusClass ?>" role="status"><i data-lucide="<?= $statusIcon ?>"></i><?= e($statusText) ?></div>
  <div class="ek-print">
    <a class="btn btn--primary" href="<?= e(url('/admin/reports/fee-receipt/' . (int) $receipt['payment_id'] . '/pdf')) ?>" target="_blank" rel="noopener"><i data-lucide="printer"></i>Detailed</a>
    <button class="btn btn--primary" type="button" onclick="document.body.classList.add('ek-print-mini');window.print();setTimeout(()=>document.body.classList.remove('ek-print-mini'),500)"><i data-lucide="printer"></i>Mini</button>
    <a class="btn btn--ghost" href="<?= e(url('/admin/reports/fee-receipt/' . (int) $receipt['payment_id'] . '/pdf?download=1')) ?>"><i data-lucide="download"></i>Download</a>
    <?php if (!empty($receipt['fee_invoice_id'])): ?><a class="btn btn--ghost" href="<?= e(url('/admin/reports/fee-invoice/' . (int) $receipt['fee_invoice_id'] . '/pdf')) ?>" target="_blank" rel="noopener"><i data-lucide="file-text"></i>Fee Voucher</a><?php endif ?>
    <?php
      $waPhone = trim((string) ($receipt['father_mobile'] ?? '')) ?: trim((string) ($receipt['mobile'] ?? ''));
      $waMessage = 'Dear Parent, we have received ' . $cur . ' ' . $money($receipt['payment_amount']) . ' for ' . $receipt['student_name'] . ' (' . $receipt['class_name'] . ') against invoice ' . ($receipt['invoice_no'] ?? '') . '. Receipt: ' . $receipt['receipt_no'] . '. Remaining balance: ' . $money($remaining) . '. Thank you.';
      $waLabel = 'Send receipt on WhatsApp';
      require BASE_PATH . '/app/Views/partials/whatsapp-button.php';
    ?>
    <?php if (!$reversed && !empty($paymentSmsOn)): $smsState = $paymentSms['status'] ?? null; ?>
      <?php if ($smsState === 'sent'): ?><span class="ek-sms-state ek-sms-state--sent" title="<?= e(($paymentSms['phone_number'] ?? '') . ' · ' . ($paymentSms['sent_at'] ?? '')) ?>"><i data-lucide="message-square-check"></i>SMS sent</span>
      <?php elseif (in_array($smsState, ['queued', 'reserved', 'processing', 'scheduled'], true)): ?><span class="ek-sms-state"><i data-lucide="clock"></i>SMS queued</span>
      <?php else: ?><form method="post" action="<?= e(url('/admin/fees/receipt/' . (int) $receipt['payment_id'] . '/sms')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn--secondary" type="submit" title="The fee-received SMS was not sent for this payment"><i data-lucide="message-square"></i>Send SMS</button></form><?php endif ?>
    <?php endif ?>
  </div>
  <h1>Fees Paid Receipt</h1>
  <p class="ek-receipt-meta"><span>Student: <strong><?= e($receipt['student_name']) ?></strong></span><span>Reg: <strong><?= e($receipt['registration_no']) ?></strong></span><span>Class: <strong><?= e($receipt['class_name']) ?></strong></span><span>Month: <strong><?= e($monthLabel) ?></strong></span><span>Receipt: <strong><?= e($receipt['receipt_no']) ?></strong></span></p>
  <div class="ek-totals">
    <div class="ek-total"><span>Total Amount</span><strong><?= e($cur) ?> <?= $money($receipt['total_amount']) ?></strong></div>
    <div class="ek-total ek-total--deposit"><span>Deposit Amount</span><strong><?= e($cur) ?> <?= $money($receipt['payment_amount']) ?></strong></div>
    <div class="ek-total ek-total--due"><span>Remaining Balance</span><strong class="<?= $remaining <= 0 ? 'is-clear' : '' ?>"><?= e($cur) ?> <?= $money($remaining) ?></strong></div>
  </div>
  <details class="ek-receipt-detail" open><summary>Receipt details</summary>
    <div class="ek-tiles"><?php foreach (['Payment Date' => 'payment_date', 'Method' => 'payment_method', 'Reference' => 'reference', 'Received By' => 'received_by_name'] as $l => $k): ?><div class="ek-tile"><span><?= $l ?></span><strong><?= e($receipt[$k] ?: '—') ?></strong></div><?php endforeach ?></div>
    <div class="ek-table-wrap"><table class="ek-table no-table-tools"><thead><tr><th>Sr.</th><th>Particulars</th><th>Amount</th></tr></thead><tbody>
      <?php $sr = 0; foreach ($items as $item): ?><tr><td><?= ++$sr ?></td><td><?= e(strtoupper((string) $item['particular'])) ?></td><td><?= $money($item['amount']) ?></td></tr><?php endforeach ?>
      <tr class="ek-row-muted"><td><?= ++$sr ?></td><td>Previous Balance</td><td><?= $money($receipt['previous_balance']) ?></td></tr>
      <tr class="ek-row-muted"><td><?= ++$sr ?></td><td>Discount</td><td>- <?= $money($receipt['discount']) ?></td></tr>
      <tr><td></td><td><strong>Total</strong></td><td><strong><?= $money($receipt['total_amount']) ?></strong></td></tr>
      <tr class="ek-row-paid"><td></td><td><strong>Deposit (this receipt)</strong></td><td><strong><?= $money($receipt['payment_amount']) ?></strong></td></tr>
      <tr><td></td><td><strong>Due-able Balance</strong></td><td><strong><?= $money($remaining) ?></strong></td></tr>
    </tbody></table></div>
  </details>
  <div class="ek-receipt-next">
    <a class="btn btn--secondary" href="<?= url('/admin/fees/collect?student_id=' . (int) $receipt['student_id'] . '&month=' . rawurlencode((string) $receipt['fee_month'])) ?>"><i data-lucide="arrow-left"></i>Back to <?= e($receipt['student_name']) ?></a>
    <a class="btn btn--primary" href="<?= url('/admin/fees/collect?month=' . rawurlencode((string) $receipt['fee_month'])) ?>"><i data-lucide="plus"></i>Collect Another Fee</a>
  </div>
</section>
<section class="ek-mini-slip" aria-hidden="true"><h2><?= e($receipt['institute_name']) ?></h2><p><strong>Fee Submission Slip</strong></p><dl>
  <?php foreach (['Reg. No' => $receipt['registration_no'], 'Student Name' => $receipt['student_name'], 'Father Name' => $receipt['father_name'] ?? '', 'Class' => $receipt['class_name'], 'Receipt No' => $receipt['receipt_no'], 'Submit Date' => $receipt['payment_date'], 'Fees Month' => $receipt['fee_month'], 'Total Amount' => $cur . ' ' . $money($receipt['total_amount']), 'Deposit Amount' => $cur . ' ' . $money($receipt['payment_amount']), 'Remaining Balance' => $cur . ' ' . $money($remaining)] as $l => $v): ?><dt><?= e($l) ?>:</dt><dd><?= e((string) $v) ?></dd><?php endforeach ?>
</dl><p class="ek-mini-sign">Received by: <?= e($receipt['received_by_name']) ?></p></section>
<?php endif ?>
</div></div></main>
