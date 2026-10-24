<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Request, Response};
use App\Repositories\SettingsRepository;
use App\Services\FeeVoucherService;

/**
 * Dummy (proforma) fee invoices for prospective students. Everything is typed in by the admin and
 * printed with the normal fee-voucher design; nothing is saved, so school financials never change.
 * When the student pays, the admin admits them and collects the fee the normal way.
 */
final class DummyInvoiceController extends AdminPageController
{
    public function index(Request $r): Response
    {
        if (!can('fees.view')) return Response::html('Forbidden', 403);
        $settings = new SettingsRepository($this->app->database());
        $institute = $settings->institute();
        $particulars = $this->app->database()->query('SELECT label, amount FROM fee_particulars WHERE institute_id=:i ORDER BY id', ['i' => $institute['id']])->fetchAll();
        return $this->view('fees/dummy-invoice', ['pageTitle' => 'Dummy Fee Invoice', 'activeMenu' => 'fees', 'activeSubmenu' => 'fees-dummy', 'institute' => $institute,
            'particulars' => $particulars, 'banks' => $settings->banks((int)$institute['id']), 'voucherSettings' => (new FeeVoucherService($this->app->database()))->settings((int)$institute['id'])]);
    }

    public function generate(Request $r): Response
    {
        if (!can('fees.view')) return Response::html('Forbidden', 403);
        $institute = (new SettingsRepository($this->app->database()))->institute();
        $name = trim((string)$r->input('student_name'));
        if ($name === '' || mb_strlen($name) > 120) return Response::html('Enter the student name (up to 120 characters).', 422);
        $month = (string)$r->input('month');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) return Response::html('Choose a valid fee month.', 422);
        $due = trim((string)$r->input('due_date'));
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) return Response::html('Enter a valid due date.', 422);
        $items = [];
        $amounts = (array)$r->input('item_amount', []);
        foreach ((array)$r->input('item_label', []) as $k => $label) {
            $label = trim((string)$label); $amt = trim((string)($amounts[$k] ?? ''));
            if ($label === '' && ($amt === '' || (float)$amt == 0.0)) continue;
            if ($amt === '' || (float)$amt == 0.0) continue;
            if ($label === '' || mb_strlen($label) > 60 || !is_numeric($amt) || (float)$amt < 0 || (float)$amt > 10000000) return Response::html('Each fee line needs a name (up to 60 characters) and an amount from 0 to 10,000,000.', 422);
            $items[] = ['particular' => $label, 'amount' => round((float)$amt, 2)];
        }
        if (!$items) return Response::html('Enter at least one fee amount.', 422);
        if (count($items) > 20) return Response::html('Enter at most 20 fee lines.', 422);
        foreach (['discount', 'previous_balance', 'fine_after_due'] as $f) { $v = $r->input($f, 0) ?: 0; if (!is_numeric($v) || (float)$v < 0 || (float)$v > 10000000) return Response::html('Discount, previous balance and fine must be numbers of 0 or more.', 422); }
        $subtotal = array_sum(array_column($items, 'amount'));
        $discount = min($subtotal, (float)($r->input('discount', 0) ?: 0));
        $prev = (float)($r->input('previous_balance', 0) ?: 0);
        $total = $subtotal - $discount + $prev;
        $copyLabels = [];
        foreach (['student' => 'Student Copy', 'institute' => 'Institute Copy', 'bank' => 'Bank Copy'] as $key => $label) if (in_array($key, (array)$r->input('copy', []), true)) $copyLabels[] = $label;
        if (!$copyLabels) $copyLabels = ['Student Copy'];
        $copies = count($copyLabels) === 3 ? 'three_copy' : (count($copyLabels) === 2 ? 'student_institute' : 'single');
        $bankId = (int)$r->input('bank_id', 0);
        $banks = (new FeeVoucherService($this->app->database()))->banksFor((int)$institute['id'], $bankId);
        if ($bankId && !$banks) return Response::html('Invalid bank account.', 422);
        $voucher = ['student' => ['student_name' => $name, 'registration_no' => trim((string)$r->input('registration_no')) ?: 'NEW ADMISSION', 'class_name' => trim((string)$r->input('class_name')), 'father_name' => trim((string)$r->input('father_name')), 'institute_id' => $institute['id']],
            'invoice' => ['invoice_no' => 'PRO-' . date('ymd-His'), 'fee_month' => $month, 'due_date' => $due ?: null, 'status' => 'unpaid', 'subtotal' => $subtotal, 'discount' => $discount, 'previous_balance' => $prev, 'total_amount' => $total, 'paid_amount' => 0, 'remaining_amount' => $total, 'voided_at' => null],
            'items' => $items];
        return FeeVouchersController::pdf($this->app, $institute, [$voucher], $copies, $copyLabels, $banks, (float)($r->input('fine_after_due', 0) ?: 0), 'dummy-fee-invoice.pdf', (string)$r->input('download') === '1', (string)$r->input('orientation') === 'P' ? 'P' : 'L');
    }
}
