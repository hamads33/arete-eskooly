<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Request, Response};
use App\Repositories\{AcademicRepository, FeeRepository, ReportTemplateRepository, SettingsRepository};
use App\Services\FeeVoucherService;
use App\Services\Pdf\{PdfService, ReportTemplateService};
use App\Support\Flash;

final class FeeVouchersController extends AdminPageController
{
    public function index(Request $r): Response
    {
        $settings = new SettingsRepository($this->app->database());
        $institute = $settings->institute();
        $month = (string)$r->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
        $scope = (string)$r->query('scope', 'all');
        $class = (int)$r->query('class', 0);
        $student = (int)$r->query('student_id', 0);
        $service = new FeeVoucherService($this->app->database());
        $error = '';
        try { $rows = $service->students((int)$institute['id'], $scope, $class, $student, $month); }
        catch (\InvalidArgumentException $e) { $rows = []; $error = $e->getMessage(); }
        $feeSettings = $this->app->database()->query('SELECT voucher_copies FROM fee_settings WHERE institute_id=:i', ['i' => $institute['id']])->fetchColumn();
        return $this->renderPage('fees-vouchers', [
            'selectedMonth' => $month, 'scope' => $scope, 'selectedClass' => $class, 'selectedStudentId' => $student,
            'classes' => (new AcademicRepository($this->app->database()))->classes((int)$institute['id']),
            'students' => $service->students((int)$institute['id'], 'all', 0, 0),
            'voucherStudents' => $rows, 'selectionError' => $error, 'copies' => $feeSettings ?: 'single',
            'banks' => $settings->banks((int)$institute['id']), 'previewRequested' => $r->query('scope') !== null, 'voucherSettings' => $service->settings((int)$institute['id']),
        ]);
    }

    public function generate(Request $r): Response
    {
        $institute = (new SettingsRepository($this->app->database()))->institute();
        $scope = (string)$r->input('scope', 'all');
        $month = (string)$r->input('month');
        $copies = (string)$r->input('copies', 'single');
        $bankId = (int)$r->input('bank_id', 0);
        $banks = (new FeeVoucherService($this->app->database()))->banksFor((int)$institute['id'], $bankId);
        if ($bankId && !$banks) return Response::html('Invalid bank account.', 422);
        if (!in_array($copies, ['single', 'student_institute', 'three_copy'], true)) return Response::html('Invalid voucher copies.', 422);
        // eSkooly-style copy toggles: any combination of Bank / Student / Institute copy.
        $copyLabels = [];
        $picked = $r->input('copy');
        if (is_array($picked)) {
            foreach (['student' => 'Student Copy', 'institute' => 'Institute Copy', 'bank' => 'Bank Copy'] as $key => $label) if (in_array($key, $picked, true)) $copyLabels[] = $label;
            if (!$copyLabels) return Response::html('Choose at least one voucher copy.', 422);
            $copies = count($copyLabels) === 3 ? 'three_copy' : (count($copyLabels) === 2 ? 'student_institute' : 'single');
        }
        $fine = $r->input('fine_after_due', 0);
        if (!is_numeric($fine ?: 0) || (float)$fine < 0 || (float)$fine > 1000000) return Response::html('Fine after due date must be a number from 0 to 1,000,000.', 422);
        $extras = [];
        foreach ((array)$r->input('extra_label', []) as $k => $label) {
            $label = trim((string)$label); $amt = ((array)$r->input('extra_amount', []))[$k] ?? '';
            if ($label === '' && ($amt === '' || $amt === null)) continue;
            if ($label === '' || mb_strlen($label) > 60) return Response::html('Each extra charge needs a name of up to 60 characters.', 422);
            if (!is_numeric($amt) || (float)$amt <= 0 || (float)$amt > 10000000) return Response::html('Each extra charge needs an amount greater than 0.', 422);
            $extras[] = ['label' => $label, 'amount' => round((float)$amt, 2)];
        }
        if (count($extras) > 10) return Response::html('Add at most 10 extra charges at a time.', 422);
        if ($extras && !can('*')) return Response::html('Only an administrator can add charges to invoices.', 403);
        try {
            $service = new FeeVoucherService($this->app->database());
            $students = $service->students((int)$institute['id'], $scope, (int)$r->input('class'), (int)$r->input('student_id'), $month);
            if (!$students) throw new \InvalidArgumentException('No students match this selection.');
            // Extra charges rewrite the invoices they touch, so charging more than one student must be
            // confirmed by typing the head count; a stray "All Students" tab can no longer bill everyone.
            if ($extras && $scope !== 'student' && trim((string)$r->input('confirm_extra_count')) !== (string)count($students)) {
                throw new \InvalidArgumentException('Nothing was charged. These extra charges would be added to '.count($students).' students ('.($scope === 'all' ? 'All Students' : 'the whole class').'). To charge one student, use the Student Wise tab. To really charge all '.count($students).', type '.count($students).' in the confirmation box.');
            }
            // Class / All Students runs leave fully paid invoices alone: a bulk charge never lands on a
            // student who has already paid the month, and printing skips them unless asked not to.
            if ($scope !== 'student' && ($extras || (string)$r->input('skip_paid', '1') === '1')) {
                $paid = array_map('intval', $this->app->database()->query("SELECT student_id FROM fee_invoices WHERE fee_month=:m AND status='paid' AND voided_at IS NULL", ['m' => $month])->fetchAll(\PDO::FETCH_COLUMN));
                $students = array_values(array_filter($students, static fn(array $s): bool => !in_array((int)$s['id'], $paid, true)));
                if (!$students) throw new \InvalidArgumentException('Every student in this selection has already paid this month. Nothing was generated or charged.');
            }
            $before = [];
            if ($extras) {
                $feeRepo = new FeeRepository($this->app->database());
                foreach ($students as $s) if ($inv = $feeRepo->invoice((int)$s['id'], $month)) $before[$inv['invoice_no']] = array_map(static fn(array $i): array => [$i['particular'], (float)$i['amount']], $feeRepo->items((int)$inv['id']));
            }
            $vouchers = $service->generate((int)$institute['id'], $month, $students, (int)user()['id'], trim((string)$r->input('due_date')) ?: null, $extras);
            if ($extras) (new \App\Repositories\PakistanErpRepository($this->app->database()))->audit((int)$institute['id'], (int)user()['id'], 'fee.extra_charges_added', 'fee_month', null, ['month' => $month, 'scope' => $scope, 'students' => count($vouchers), 'charges' => $extras, 'before' => $before]);
            if (!$vouchers) throw new \InvalidArgumentException('All invoices in this selection are voided. No vouchers were generated.');
        } catch (\InvalidArgumentException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect(url('/admin/fees/vouchers'));
        }
        return self::pdf($this->app, $institute, $vouchers, $copies, $copyLabels, $banks, (float)($fine ?: 0), 'fee-vouchers-'.$month.'-'.$scope.'.pdf', (string)$r->input('download') === '1', (string)$r->input('orientation') === 'P' ? 'P' : 'L');
    }

    /** Renders vouchers with the institute's fee-invoice template and voucher instructions (also used by the settings preview). */
    public static function pdf(\App\Core\App $app, array $institute, array $vouchers, string $copies, array $copyLabels, array $banks, float $fine, string $filename, bool $download, string $orientation = 'L'): Response
    {
        $templateService = new ReportTemplateService();
        $template = array_replace($templateService->defaults('fee-invoice'), (new ReportTemplateRepository($app->database()))->find((int)$institute['id'], 'fee-invoice') ?? []);
        // Challans print landscape with copies side by side; a single copy may be printed portrait if the admin chooses.
        $single = count($copyLabels) === 1 || (!$copyLabels && $copies === 'single');
        $portrait = $single && $orientation === 'P';
        $template['page_orientation'] = $portrait ? 'P' : 'L';
        $template['margin_mode'] = 'compact'; // vouchers use the whole sheet
        $settings = (new FeeVoucherService($app->database()))->settings((int)$institute['id']);
        return (new PdfService())->render('fee-vouchers', [
            'institute' => $institute, 'type' => 'fee-invoice', 'templateService' => $templateService,
            'placeholders' => ['institute_name' => $institute['name']], 'vouchers' => $vouchers, 'copies' => $copies,
            'banks' => $banks, 'copyLabelsOverride' => $copyLabels, 'fineAfterDue' => $fine, 'voucherInstructions' => $settings['instructions'], 'singlePortrait' => $portrait,
        ], $template, $filename, $download);
    }
}
