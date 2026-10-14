<?php
$primary = $template['primary_color'] ?? '#7A1632';
$currency = trim((string)($institute['currency_symbol'] ?? $institute['currency'] ?? 'PKR'));
$money = static fn($value): string => $currency.' '.number_format((float)$value, 2);
$copyLabels = match($copies) { 'three_copy' => ['Student Copy', 'Institute Copy', 'Bank Copy'], 'student_institute' => ['Student Copy', 'Institute Copy'], default => ['Student Copy'] };
if (!empty($copyLabelsOverride)) $copyLabels = array_values($copyLabelsOverride);
$grid = true; // always landscape, copies side by side
$fineAfterDue = max(0, (float)($fineAfterDue ?? 0));
$logo = '';
if (!empty($template['show_logo']) && !empty($institute['logo_path'])) {
    $candidate = BASE_PATH.'/public/'.ltrim((string)$institute['logo_path'], '/');
    if (is_file($candidate) && in_array(strtolower(pathinfo($candidate, PATHINFO_EXTENSION)), ['png','jpg','jpeg'], true)) $logo = $candidate;
}
$footerText = $templateService->renderText((string)($template['footer_text'] ?? ''), $placeholders, $type);
// Sizes so each copy fills its share of the sheet: three copies side by side on landscape A4,
// two copies stacked on portrait A4, or one copy filling a portrait page.
$n = count($copyLabels);
$z = match (true) {
    $n >= 3 => ['box' => 158, 'font' => 8.6, 'small' => 6.8, 'school' => 11.5, 'logo' => 12, 'pad' => 1.75, 'title' => 9, 'sign' => 16, 'amount' => 30, 'width' => 33.33],
    $n === 2 => ['box' => 158, 'font' => 9.4, 'small' => 7.4, 'school' => 13.5, 'logo' => 14, 'pad' => 1.9, 'title' => 10, 'sign' => 16, 'amount' => 40, 'width' => 50],
    !empty($singlePortrait) => ['box' => 206, 'font' => 10.5, 'small' => 8.4, 'school' => 17, 'logo' => 18, 'pad' => 2.6, 'title' => 12, 'sign' => 18, 'amount' => 50, 'width' => 100],
    default => ['box' => 158, 'font' => 9.6, 'small' => 7.6, 'school' => 14, 'logo' => 15, 'pad' => 1.9, 'title' => 10.5, 'sign' => 16, 'amount' => 44, 'width' => 62],
};
$fmtDate = static fn(?string $d): string => $d ? date('d M Y', strtotime($d)) : 'Not specified';
// Escaped text with line breaks; **text** prints bold (so admins can emphasise parts of the instructions).
// Fully paid invoices carry a rotated "PAID" stamp impression (SVG, so mPDF can rotate and fade it).
$paidStamp = static function (array $invoice): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="150" viewBox="0 0 300 150"><g transform="rotate(-12 150 75)" opacity="0.85" fill="none" stroke="#1d7a46">'
        .'<rect x="22" y="22" width="256" height="106" rx="14" stroke-width="7"/><rect x="34" y="34" width="232" height="82" rx="8" stroke-width="2.5"/>'
        .'<text x="150" y="86" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="54" font-weight="bold" fill="#1d7a46" stroke="none" letter-spacing="8">PAID</text>'
        .'<text x="150" y="108" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="13" font-weight="bold" fill="#1d7a46" stroke="none" letter-spacing="2">RECEIVED IN FULL</text></g></svg>';
    return '<div style="text-align:right;margin:2.5mm 3mm 3mm 0"><img src="data:image/svg+xml;base64,'.base64_encode($svg).'" style="width:46mm;height:23mm"></div>';
};
$richText = static fn(string $t): string => nl2br(preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e(trim($t))));
?>
<style>
body{font-family:dejavusans,sans-serif;font-size:<?= $z['font'] ?>pt;color:#211a1d;line-height:1.3}
.vbox{width:100%;border-collapse:collapse;border:0.6pt solid #ded6d9}
.vbox>tbody>tr>td{padding:3mm 3.5mm}
.identity{width:100%;border-collapse:collapse;margin-bottom:2mm}
.identity td{vertical-align:middle;padding:0}
.school{font-size:<?= $z['school'] ?>pt;font-weight:bold;color:<?= e($primary) ?>}
.contact{font-size:<?= $z['small'] ?>pt;color:#71676c;margin-top:0.8mm}
.copy{text-align:right;font-size:<?= $z['small'] + 0.6 ?>pt;color:<?= e($primary) ?>;font-weight:bold;white-space:nowrap}
.meta{width:100%;border-collapse:collapse;margin:0 0 2mm}
.meta td{padding:<?= $z['pad'] * 0.6 ?>mm 1.5mm;vertical-align:top;border-bottom:0.3pt solid #eee6e9}
.label{color:#71676c;font-size:<?= $z['small'] ?>pt}
.items{width:100%;border-collapse:collapse}
.items th{padding:<?= $z['pad'] ?>mm 2mm;background:#f5f0f2;color:<?= e($primary) ?>;border:0.4pt solid #ded6d9;text-align:left}
.items td{padding:<?= $z['pad'] ?>mm 2mm;border:0.4pt solid #ded6d9}
.items .sr{width:7mm;color:#71676c}
.items .amount{width:<?= $z['amount'] ?>mm;text-align:right;white-space:nowrap}
.items .total td{font-weight:bold;background:#f5f0f2}
.items .balance td{font-weight:bold;color:<?= e($primary) ?>;background:#f5f0f2}
.items .late td{font-weight:bold;color:#9b1c31;background:#fdf1f3}
.bank{font-size:<?= $z['small'] ?>pt;color:#4d4247;margin-top:2mm;padding:1.6mm 2mm;border:0.4pt dashed #d6cbd0}
.note{font-size:<?= $z['small'] ?>pt;color:#71676c;margin-top:1.5mm}
.signatures{width:100%;font-size:<?= $z['small'] ?>pt;color:#71676c;border-collapse:collapse}
.signatures td{width:50%;padding-top:2mm}
.cut{font-size:5pt;color:#aaa;text-align:center;border-top:0.4pt dashed #aaa;margin:2mm 0 2mm}
.doc-footer{font-size:6pt;color:#71676c;width:100%}
.page-number{text-align:right}
.footer-rule{border-top:.5pt solid #ded6d9;margin-bottom:2mm}
.copy-grid{width:100%;border-collapse:collapse;table-layout:fixed}
</style>
<?php require BASE_PATH.'/app/Views/pdf/partials/footer.php'; ?>
<?php foreach($vouchers as $index => $voucher): $student=$voucher['student']; $invoice=$voucher['invoice']; ?>
<?php if($index): ?><pagebreak /><?php endif ?>
<table class="copy-grid" style="width:<?= $n === 1 ? $z['width'] : 100 ?>%<?= $n === 1 ? ';margin:0 auto' : '' ?>"><tr>
<?php foreach($copyLabels as $copyIndex => $copyLabel): ?>
<?php $last = $copyIndex === $n - 1; ?><td style="width:<?= $z['width'] ?>%;vertical-align:top;padding:0 <?= $last ? '0' : '2.5mm' ?> 0 <?= $copyIndex === 0 ? '0' : '2.5mm' ?>;<?= !$last ? 'border-right:0.5pt dashed #aaa;' : '' ?>">
<?php $sr = 0; ?><table class="vbox" style="height:<?= $z['box'] ?>mm"><tr><td style="vertical-align:top">
<table class="identity"><tr><?php if($logo): ?><td style="width:<?= $z['logo'] + 2 ?>mm;padding-right:2mm;vertical-align:middle"><img src="<?= e($logo) ?>" width="<?= (int)round($z['logo'] * 3.78) ?>" height="<?= (int)round($z['logo'] * 3.78) ?>" style="width:<?= $z['logo'] ?>mm;height:<?= $z['logo'] ?>mm"></td><?php endif ?><td><div class="school"><?= e($institute['name']) ?></div><div class="contact"><?php if(!empty($template['show_address'])): ?><?= e($institute['address']??'') ?><?php endif ?><?php if(!empty($template['show_phone'])): ?> · <?= e($institute['phone']??'') ?><?php endif ?><?php if(!empty($template['show_website'])&&!empty($institute['website'])): ?> · <?= e($institute['website']) ?><?php endif ?></div></td><td class="copy" style="width:<?= $n >= 3 ? 20 : 26 ?>mm"><?= e($copyLabel) ?></td></tr></table>
<table style="width:100%;border-collapse:collapse;margin-bottom:2mm"><tr><td style="background:<?= e($primary) ?>;color:#fff;padding:<?= $z['pad'] + 0.4 ?>mm 2mm;font-weight:bold;font-size:<?= $z['title'] ?>pt"><?= e($template['title']??'Fee Voucher') ?> · <?= e(date('F Y',strtotime($invoice['fee_month'].'-01'))) ?></td></tr></table>
<?php if(!empty($template['subtitle'])): ?><div class="note"><?= e($template['subtitle']) ?></div><?php endif ?>
<table class="meta">
<tr><td style="width:50%"><span class="label">STUDENT</span><br><strong><?= e($student['student_name']) ?></strong></td><td><span class="label">INVOICE NO.</span><br><strong><?= e($invoice['invoice_no']) ?></strong></td></tr>
<tr><td><span class="label">FATHER / GUARDIAN</span><br><?= e(($student['father_name'] ?? '') ?: '—') ?></td><td><span class="label">REGISTRATION / CLASS</span><br><?= e($student['registration_no'].' / '.$student['class_name']) ?></td></tr>
<tr><td><span class="label">ISSUE DATE</span><br><?= e(date('d M Y')) ?></td><td><span class="label">DUE DATE</span><br><strong style="color:<?= e($primary) ?>"><?= e($fmtDate($invoice['due_date'] ?? null)) ?></strong> · <?= e(ucwords(str_replace('_',' ',$invoice['status']))) ?></td></tr>
</table>
<table class="items"><thead><tr><th class="sr">Sr.</th><th>Fee Particular</th><th class="amount">Amount</th></tr></thead><tbody>
<?php foreach($voucher['items'] as $item): ?><tr><td class="sr"><?= ++$sr ?></td><td><?= e($item['particular']) ?></td><td class="amount"><?= e($money($item['amount'])) ?></td></tr><?php endforeach ?>
<tr><td class="sr"></td><td>Subtotal</td><td class="amount"><?= e($money($invoice['subtotal'])) ?></td></tr>
<tr><td class="sr"></td><td>Discount</td><td class="amount">- <?= e($money($invoice['discount'])) ?></td></tr>
<tr><td class="sr"></td><td>Previous Balance</td><td class="amount"><?= e($money($invoice['previous_balance'])) ?></td></tr>
<tr class="total"><td class="sr"></td><td>Total Payable</td><td class="amount"><?= e($money($invoice['total_amount'])) ?></td></tr>
<tr><td class="sr"></td><td>Already Paid</td><td class="amount"><?= e($money($invoice['paid_amount'])) ?></td></tr>
<tr class="balance"><td class="sr"></td><td>Payable by Due Date<?= !empty($invoice['due_date']) ? ' ('.e($fmtDate($invoice['due_date'])).')' : '' ?></td><td class="amount"><?= e($money($invoice['remaining_amount'])) ?></td></tr>
<?php if($fineAfterDue>0&&(float)$invoice['remaining_amount']>0): ?><tr class="late"><td class="sr"></td><td>Payable After Due Date</td><td class="amount"><?= e($money((float)$invoice['remaining_amount']+$fineAfterDue)) ?></td></tr><?php endif ?>
</tbody></table>
<?php if(($invoice['status'] ?? '') === 'paid' && (float)$invoice['remaining_amount'] <= 0): ?><?= $paidStamp($invoice) ?><?php endif ?><?php if($banks): ?><div class="bank"><div class="label" style="margin-bottom:0.8mm">PAY AT</div><?php foreach($banks as $bank): ?><div style="margin-bottom:0.8mm"><strong><?= e($bank['bank_name']) ?></strong> · A/C <strong><?= e($bank['account_number']) ?></strong><?= !empty($bank['bank_address']) ? ' · '.e($bank['bank_address']) : '' ?><?php if(trim((string)($bank['instructions']??''))!==''): ?><br><span style="color:#71676c"><?= $richText((string)$bank['instructions']) ?></span><?php endif ?></div><?php endforeach ?></div><?php endif ?>
<?php if(trim((string)($voucherInstructions??''))!==''): ?><div class="bank instructions"><div class="label" style="margin-bottom:0.8mm">IMPORTANT INSTRUCTIONS</div><?= $richText((string)$voucherInstructions) ?></div><?php endif ?>
<?php if($fineAfterDue>0): ?><div class="note">A fine of <?= e($money($fineAfterDue)) ?> applies after the due date.</div><?php endif ?>
<?php if(!empty($template['body_html'])): ?><div class="note"><?= $templateService->renderText($template['body_html'],array_merge($placeholders,['student_name'=>$student['student_name'],'registration_no'=>$student['registration_no']]),$type) ?></div><?php endif ?>
</td></tr>
<tr><td style="vertical-align:bottom;height:<?= $z['sign'] ?>mm"><table class="signatures"><tr><td style="text-align:right">Accounts Office ______________________</td></tr></table></td></tr>
</table>
</td>
<?php endforeach ?></tr></table><?php endforeach ?>
