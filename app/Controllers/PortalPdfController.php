<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\{ReportTemplateRepository,SettingsRepository,WorkflowRepository};
use App\Services\Pdf\{PdfService,ReportFilenameService,ReportTemplateService};

/**
 * Branded PDF documents a portal user may pull for themselves.
 *
 * Mirrors ReportPdfController's template/branding pipeline, but resolves the owning record
 * from the session instead of a route parameter, so a portal user can only ever produce their
 * own document.
 */
final class PortalPdfController extends PortalController
{
    /* ------------------------------------------------------------------ student */

    public function admissionLetter(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $institute = $this->repo()->institute((int) $student['institute_id']);
        $rules = (new SettingsRepository($this->app->database()))->rules((int) $student['institute_id']);

        return $this->pdf($request, $institute, 'admission-letter', 'admission-letter-' . $student['registration_no'], [
            'meta' => [
                'Admission Date' => $student['date_of_admission'] ?? '',
                'Registration No.' => $student['registration_no'],
                'Student Name' => $student['student_name'],
                'Father / Guardian' => $student['father_name'] ?? '',
                'Class' => $student['class_name'] ?? '',
                'Contact' => $student['mobile'] ?? '',
            ],
            'intro' => '<h2>Student Rules &amp; Regulations</h2>' . ($rules['student'] ?? '<p>No rules have been configured.</p>'),
            'signatures' => ['Parent / Guardian', 'Administrator'],
            'placeholders' => [
                'student_name' => $student['student_name'],
                'registration_no' => $student['registration_no'],
                'class_name' => $student['class_name'] ?? '',
                'father_name' => $student['father_name'] ?? '',
                'institute_name' => $institute['name'] ?? '',
            ],
        ]);
    }

    /** Fee paid slip for one of the student's own payments. */
    public function feeSlip(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $payment = $this->repo()->studentPayment((int) $student['id'], (int) $request->route('payment'));
        if ($payment === null) {
            return Response::html('Fee receipt not found.', 404);
        }
        $institute = $this->repo()->institute((int) $student['institute_id']);
        $items = $this->repo()->invoiceItems((int) $payment['fee_invoice_id']);
        foreach ($items as &$item) {
            $item['amount'] = $this->money($item['amount'], $institute);
        }
        unset($item);

        return $this->pdf($request, $institute, 'fee-receipt', 'fee-receipt-' . $payment['receipt_no'], [
            'meta' => [
                'Receipt No.' => $payment['receipt_no'],
                'Payment Date' => $payment['payment_date'],
                'Student' => $student['student_name'],
                'Registration' => $student['registration_no'],
                'Class' => $student['class_name'] ?? '',
                'Fee Month' => $payment['fee_month'],
                'Payment Method' => $payment['payment_method'],
                'Amount Paid' => $this->money($payment['amount'], $institute),
            ],
            'columns' => ['Particular' => 'particular', 'Amount' => 'amount'],
            'rows' => $items,
            'signatures' => ['Received By', 'Authorized Signature'],
            'placeholders' => [
                'student_name' => $student['student_name'],
                'receipt_no' => $payment['receipt_no'],
                'amount_paid' => $this->money($payment['amount'], $institute),
                'institute_name' => $institute['name'] ?? '',
            ],
        ]);
    }

    /** Result card for one exam the student sat. */
    public function reportCard(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $instituteId = (int) $student['institute_id'];
        $examId = (int) $request->route('exam');
        if (!$this->repo()->studentSatExam((int) $student['id'], $instituteId, $examId)) {
            return Response::html('Report card record not found.', 404);
        }
        $exam = $this->app->database()->query('SELECT * FROM exams WHERE id=:e AND institute_id=:i', ['e' => $examId, 'i' => $instituteId])->fetch();
        if (!is_array($exam)) {
            return Response::html('Report card record not found.', 404);
        }
        $institute = $this->repo()->institute($instituteId);
        $result = (new WorkflowRepository($this->app->database()))->studentResult($instituteId, $examId, (int) $student['id']);

        return $this->pdf($request, $institute, 'report-card', 'report-card-' . $student['registration_no'] . '-' . $exam['name'], [
            'meta' => [
                'Student' => $student['student_name'],
                'Registration' => $student['registration_no'],
                'Class' => $student['class_name'] ?? '',
                'Exam' => $exam['name'],
                'Obtained' => (string) ($result['obtained'] ?? 0),
                'Total' => (string) ($result['total'] ?? 0),
            ],
            'columns' => ['Subject' => 'subject', 'Total Marks' => 'total_marks', 'Obtained' => 'obtained_marks', 'Remarks' => 'remarks'],
            'rows' => $result['subjects'] ?? [],
            'signatures' => ['Class Teacher', 'Principal'],
            'placeholders' => [
                'student_name' => $student['student_name'],
                'registration_no' => $student['registration_no'],
                'class_name' => $student['class_name'] ?? '',
                'exam_name' => $exam['name'],
                'institute_name' => $institute['name'] ?? '',
            ],
        ]);
    }

    /* ----------------------------------------------------------------- employee */

    public function jobLetter(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        $institute = $this->repo()->institute((int) $employee['institute_id']);
        $rules = (new SettingsRepository($this->app->database()))->rules((int) $employee['institute_id']);

        return $this->pdf($request, $institute, 'job-letter', 'job-letter-' . $employee['employee_no'], [
            'meta' => [
                'Employee No.' => $employee['employee_no'],
                'Employee Name' => $employee['name'],
                'Designation' => $employee['role'],
                'Joining Date' => $employee['joining_date'] ?? '',
                'Monthly Salary' => $this->money($employee['monthly_salary'] ?? 0, $institute),
            ],
            'intro' => '<p>We are pleased to confirm your appointment with the institute under the terms recorded below.</p><h2>Employee Rules &amp; Regulations</h2>' . ($rules['employee'] ?? '<p>No rules have been configured.</p>'),
            'signatures' => ['Employee Signature', 'Authorized Signature'],
            'placeholders' => [
                'employee_name' => $employee['name'],
                'employee_no' => $employee['employee_no'],
                'role' => $employee['role'],
                'joining_date' => $employee['joining_date'] ?? '',
                'institute_name' => $institute['name'] ?? '',
            ],
        ]);
    }

    public function salarySlip(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        $payment = $this->repo()->employeeSalaryPayment((int) $employee['id'], (int) $request->route('payment'));
        if ($payment === null) {
            return Response::html('Salary payment not found.', 404);
        }
        $institute = $this->repo()->institute((int) $employee['institute_id']);

        return $this->pdf($request, $institute, 'salary-slip', 'salary-slip-' . $employee['employee_no'] . '-' . $payment['salary_month'], [
            'meta' => [
                'Employee' => $employee['employee_no'] . ' - ' . $employee['name'],
                'Designation' => $employee['role'],
                'Salary Month' => $payment['salary_month'],
                'Base Salary' => $this->money($payment['base_salary'], $institute),
                'Adjustment' => $this->money($payment['adjustment_amount'], $institute),
                'Deduction' => $this->money($payment['deduction_amount'] ?? 0, $institute),
                'Advance Recovered' => $this->money($payment['advance_recovered'] ?? 0, $institute),
                'Paid Amount' => $this->money($payment['paid_amount'], $institute),
                'Payment Date' => $payment['payment_date'],
                'Reference' => $payment['reference'] ?: '-',
                'Remarks' => $payment['remarks'] ?: '-',
            ],
            'signatures' => ['Employee Signature', 'Authorized Signature'],
            'placeholders' => [
                'employee_name' => $employee['name'],
                'employee_no' => $employee['employee_no'],
                'salary_month' => $payment['salary_month'],
                'amount_paid' => $this->money($payment['paid_amount'], $institute),
                'institute_name' => $institute['name'] ?? '',
            ],
        ]);
    }

    /* ------------------------------------------------------------------- shared */

    private function pdf(Request $request, array $institute, string $type, string $filename, array $data, string $view = 'document'): Response
    {
        $service = new ReportTemplateService();
        $template = array_replace(
            $service->defaults($type),
            (new ReportTemplateRepository($this->app->database()))->find((int) ($institute['id'] ?? 0), $type) ?? []
        );
        return (new PdfService())->render(
            $view,
            $data + ['institute' => $institute, 'type' => $type, 'templateService' => $service, 'placeholders' => $data['placeholders'] ?? []],
            $template,
            (new ReportFilenameService())->make($filename),
            $request->query('download') === '1'
        );
    }

    private function money(mixed $value, array $institute): string
    {
        return ($institute['currency_symbol'] ?? $institute['currency'] ?? '') . ' ' . number_format((float) $value, 2);
    }
}
