<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Repositories\{FeeRepository, StudentRepository};

final class FeeVoucherService
{
    public function __construct(private readonly Database $db) {}

    /** Students to bill for $month: everyone active, kept by the admin's inactive/suspended rule, or deactivated during/after that month. */
    public function students(int $institute, string $scope, int $class, int $student, ?string $month = null): array
    {
        $month = $month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : date('Y-m');
        if (!in_array($scope, ['all', 'class', 'student'], true)) throw new \InvalidArgumentException('Choose All Students, Class Wise or Student Wise.');
        $where = 's.institute_id=:i';
        $params = ['i' => $institute];
        if ($scope === 'student') {
            if ($student <= 0) throw new \InvalidArgumentException('Select a student.');
            $where .= ' AND s.id=:student'; $params['student'] = $student;
        } else {
            $where .= ' AND ' . \App\Support\PersonStatus::visibleSql($this->db, $institute, 'student', 's', 'billing', ':since');
            $params['since'] = $month . '-01 00:00:00';
            if ($scope === 'class') {
                if ($class <= 0) throw new \InvalidArgumentException('Select a class.');
                $where .= ' AND s.class_id=:class'; $params['class'] = $class;
            }
        }
        return $this->db->query('SELECT s.*,c.name class_name,c.monthly_tuition FROM students s JOIN classes c ON c.id=s.class_id AND c.institute_id=s.institute_id WHERE '.$where.' ORDER BY c.name,s.student_name,s.registration_no,s.id', $params)->fetchAll();
    }

    /**
     * @param list<array{label:string,amount:float}> $extras one-off charges (e.g. a fine, trip fee) added to every
     *        selected invoice; a charge whose label already exists on the invoice is set, not added again.
     */
    public function generate(int $institute, string $month, array $students, int $user, ?string $dueDate = null, array $extras = []): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw new \InvalidArgumentException('Choose a valid fee month.');
        if ($dueDate !== null) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
            if (!$date || $date->format('Y-m-d') !== $dueDate) throw new \InvalidArgumentException('Choose a valid due date.');
        }
        foreach ($students as $student) if ((int)$student['institute_id'] !== $institute) throw new \InvalidArgumentException('Student belongs to another institute.');
        $fees = new FeeRepository($this->db);
        $service = new FeeService($fees, new StudentRepository($this->db));
        $vouchers = [];
        foreach ($students as $student) {
            $existing = $fees->invoice((int)$student['id'], $month);
            $invoice = $service->ensureInvoice($student, $institute, $month, $user);
            if ($invoice['voided_at']) continue;
            // A due date entered on the voucher screen is printed on every voucher it generates.
            if ($dueDate !== null && $invoice['due_date'] !== $dueDate) {
                $this->db->query('UPDATE fee_invoices SET due_date=:d WHERE id=:id', ['d'=>$dueDate, 'id'=>$invoice['id']]);
                $invoice['due_date'] = $dueDate;
            }
            if ($extras) {
                $lines = []; $fine = 0.0;
                foreach ($fees->items((int)$invoice['id']) as $item) {
                    if (strcasecmp((string)$item['particular'], 'Fine') === 0) { $fine += (float)$item['amount']; continue; }
                    $lines[] = ['particular' => $item['particular'], 'amount' => (float)$item['amount']];
                }
                foreach ($extras as $x) {
                    if (strcasecmp($x['label'], 'Fine') === 0) { $fine = (float)$x['amount']; continue; }
                    $hit = false;
                    foreach ($lines as &$l) if (strcasecmp((string)$l['particular'], $x['label']) === 0) { $l['amount'] = (float)$x['amount']; $hit = true; }
                    unset($l);
                    if (!$hit) $lines[] = ['particular' => $x['label'], 'amount' => (float)$x['amount']];
                }
                $service->adjustInvoice((int)$invoice['id'], $lines, (float)$invoice['discount'], $fine);
                $invoice = $fees->invoice((int)$student['id'], $month);
            }
            $vouchers[] = ['student' => $student, 'invoice' => $invoice, 'items' => $fees->items((int)$invoice['id'])];
        }
        return $vouchers;
    }

    /** Voucher settings (General Settings → Fee Voucher Settings), with safe defaults before they are saved. */
    public function settings(int $institute): array
    {
        $row = $this->db->query('SELECT voucher_copies,voucher_instructions,voucher_bank_ids,voucher_default_fine FROM fee_settings WHERE institute_id=:i', ['i' => $institute])->fetch() ?: [];
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)($row['voucher_bank_ids'] ?? '')))));
        return ['copies' => $row['voucher_copies'] ?? 'single', 'instructions' => (string)($row['voucher_instructions'] ?? ''), 'bank_ids' => $ids, 'default_fine' => (float)($row['voucher_default_fine'] ?? 0)];
    }

    /** Bank accounts to print: the one picked on the voucher screen, else those chosen in settings, else all. */
    public function banksFor(int $institute, int $bankId = 0): array
    {
        $banks = (new \App\Repositories\SettingsRepository($this->db))->banks($institute);
        if ($bankId) return array_values(array_filter($banks, static fn($b) => (int)$b['id'] === $bankId));
        $ids = $this->settings($institute)['bank_ids'];
        return $ids ? array_values(array_filter($banks, static fn($b) => in_array((int)$b['id'], $ids, true))) : $banks;
    }

    public function saveSettings(int $institute, string $instructions, array $bankIds, float $defaultFine): void
    {
        $valid = array_map(static fn($b) => (int)$b['id'], (new \App\Repositories\SettingsRepository($this->db))->banks($institute));
        $ids = array_values(array_intersect(array_map('intval', $bankIds), $valid));
        $now = date('Y-m-d H:i:s');
        $this->db->query("INSERT IGNORE INTO fee_settings(institute_id,payment_methods_json,created_at,updated_at) VALUES(:i,'[\"Cash\",\"Bank\",\"Online Transfer\",\"Cheque\",\"JazzCash\",\"Easypaisa\",\"Other\"]',:c,:u)", ['i' => $institute, 'c' => $now, 'u' => $now]);
        $this->db->query('UPDATE fee_settings SET voucher_instructions=:t,voucher_bank_ids=:b,voucher_default_fine=:f,updated_at=:u WHERE institute_id=:i', ['t' => $instructions !== '' ? $instructions : null, 'b' => $ids ? implode(',', $ids) : null, 'f' => $defaultFine, 'u' => $now, 'i' => $institute]);
    }

    /** A made-up voucher for the settings preview. Nothing is written to the database. */
    public function sample(int $institute): array
    {
        $items = [['particular' => 'Monthly Tuition Fee', 'amount' => 5000], ['particular' => 'Admission Fee', 'amount' => 1000], ['particular' => 'Books', 'amount' => 750]];
        $subtotal = 6750.0; $discount = 500.0; $prev = 1200.0; $total = $subtotal - $discount + $prev;
        return [['student' => ['student_name' => 'Sample Student', 'registration_no' => 'SAMPLE-001', 'class_name' => 'Class 5', 'father_name' => 'Sample Father', 'institute_id' => $institute],
            'invoice' => ['invoice_no' => 'INV-SAMPLE-0001', 'fee_month' => date('Y-m'), 'due_date' => date('Y-m-10'), 'status' => 'unpaid', 'subtotal' => $subtotal, 'discount' => $discount, 'previous_balance' => $prev, 'total_amount' => $total, 'paid_amount' => 0, 'remaining_amount' => $total, 'voided_at' => null],
            'items' => $items]];
    }
}
