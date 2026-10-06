<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Request, Response};
use App\Repositories\SettingsRepository;
use App\Services\FeeVoucherService;
use App\Support\Flash;

/** General Settings → Fee Voucher Settings: instructions, bank accounts to print, default fine, and a test preview. */
final class FeeVoucherSettingsController extends AdminPageController
{
    private function institute(): array { return (new SettingsRepository($this->app->database()))->institute(); }
    private function back(): Response { return Response::redirect(url('/admin/settings/fee-voucher')); }

    public function index(Request $r): Response
    {
        $i = $this->institute();
        $service = new FeeVoucherService($this->app->database());
        return $this->renderPage('fee-voucher-settings', ['voucherSettings' => $service->settings((int)$i['id']), 'banks' => (new SettingsRepository($this->app->database()))->banks((int)$i['id'])]);
    }

    public function save(Request $r): Response
    {
        if (!can('*')) return Response::html('Only an administrator can change voucher settings.', 403);
        $text = trim(str_replace("\r\n", "\n", (string)$r->input('voucher_instructions', '')));
        $fine = $r->input('voucher_default_fine', 0);
        if (mb_strlen($text) > 3000) { Flash::add('error', 'Instructions can be up to 3,000 characters.'); return $this->back(); }
        if (!is_numeric($fine === '' ? 0 : $fine) || (float)$fine < 0 || (float)$fine > 1000000) { Flash::add('error', 'Default fine must be a number from 0 to 1,000,000.'); return $this->back(); }
        $i = $this->institute();
        (new FeeVoucherService($this->app->database()))->saveSettings((int)$i['id'], $text, (array)$r->input('bank_ids', []), (float)($fine ?: 0));
        Flash::add('success', 'Fee voucher settings saved.');
        return $this->back();
    }

    public function addBank(Request $r): Response
    {
        if (!can('*')) return Response::html('Only an administrator can add bank accounts.', 403);
        $d = ['bank_name' => trim((string)$r->input('bank_name')), 'account_number' => trim((string)$r->input('account_number')), 'bank_address' => trim((string)$r->input('bank_address')), 'instructions' => trim((string)$r->input('instructions'))];
        if ($d['bank_name'] === '' || mb_strlen($d['bank_name']) > 120 || $d['account_number'] === '' || mb_strlen($d['account_number']) > 100 || mb_strlen($d['bank_address']) > 2000 || mb_strlen($d['instructions']) > 2000) {
            Flash::add('error', 'Enter a bank name (up to 120 characters) and an account number (up to 100).');
            return $this->back();
        }
        (new SettingsRepository($this->app->database()))->addBank((int)$this->institute()['id'], $d + ['logo_path' => '']);
        Flash::add('success', 'Bank account added. Tick it below to print it on vouchers.');
        return $this->back();
    }

    /** A sample voucher with the current settings — nothing is saved. Unsaved form values can be previewed via query string. */
    public function preview(Request $r): Response
    {
        $i = $this->institute();
        $service = new FeeVoucherService($this->app->database());
        $copies = (string)$r->query('copies', $service->settings((int)$i['id'])['copies']);
        if (!in_array($copies, ['single', 'student_institute', 'three_copy'], true)) $copies = 'single';
        $fine = $r->query('fine', null);
        $fine = is_numeric($fine) && (float)$fine >= 0 && (float)$fine <= 1000000 ? (float)$fine : $service->settings((int)$i['id'])['default_fine'];
        return FeeVouchersController::pdf($this->app, $i, $service->sample((int)$i['id']), $copies, [], $service->banksFor((int)$i['id']), $fine, 'sample-fee-voucher.pdf', false, (string)$r->query('orientation') === 'P' ? 'P' : 'L');
    }
}
