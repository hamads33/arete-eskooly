<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class DashboardRepository
{
    public function __construct(private readonly Database $db) {}

    public function summary(int $institute): array
    {
        $students = (int) $this->db->query(
            "SELECT COUNT(*) FROM students WHERE institute_id=:i AND status='active'",
            ['i' => $institute]
        )->fetchColumn();

        $employees = (int) $this->db->query(
            "SELECT COUNT(*) FROM employees WHERE institute_id=:i AND status='active'",
            ['i' => $institute]
        )->fetchColumn();

        $months = [];
        $income = [];
        $expense = [];
        $start = new \DateTimeImmutable('first day of -5 months');
        $end = $start->modify('+6 months')->format('Y-m-01');

        for ($n = 0; $n < 6; $n++) {
            $date = $start->modify("+{$n} months");
            $key = $date->format('Y-m');
            $months[] = $date->format('M');
            $income[$key] = 0.0;
            $expense[$key] = 0.0;
        }

        // Account entries remain available for manually entered income/expenses. Fee
        // collections and salary payments are operational records, so include them here
        // as well. The reference exclusions make this safe if a deployment has already
        // started mirroring either workflow into the ledger.
        $rows = $this->db->query(
            "SELECT DATE_FORMAT(transaction_date,'%Y-%m') ym, transaction_type, SUM(amount) total
             FROM account_transactions
             WHERE institute_id=:i
               AND transaction_date>=:from
               AND transaction_date<:to
               AND COALESCE(reference_type,'') NOT IN ('fee_payment','salary_payment')
             GROUP BY ym, transaction_type",
            ['i' => $institute, 'from' => $start->format('Y-m-01'), 'to' => $end]
        )->fetchAll();

        foreach ($rows as $row) {
            if (!isset($income[$row['ym']])) {
                continue;
            }
            if ($row['transaction_type'] === 'income') {
                $income[$row['ym']] += (float) $row['total'];
            } elseif ($row['transaction_type'] === 'expense') {
                $expense[$row['ym']] += (float) $row['total'];
            }
        }

        $feeRows = $this->db->query(
            "SELECT ym, SUM(total) total FROM (
                SELECT DATE_FORMAT(p.payment_date,'%Y-%m') ym, SUM(p.amount) total
                FROM fee_payments p
                JOIN fee_invoices i ON i.id=p.fee_invoice_id
                JOIN students s ON s.id=p.student_id
                WHERE s.institute_id=:i1
                  AND p.payment_date>=:from1
                  AND p.payment_date<:to1
                  AND p.reversed_at IS NULL
                  AND i.voided_at IS NULL
                GROUP BY p.payment_date
                UNION ALL
                SELECT DATE_FORMAT(COALESCE(i.updated_at,i.created_at),'%Y-%m') ym, SUM(i.paid_amount) total
                FROM fee_invoices i
                JOIN students s ON s.id=i.student_id
                WHERE s.institute_id=:i2
                  AND COALESCE(i.paid_amount,0)>0
                  AND i.voided_at IS NULL
                  AND NOT EXISTS (SELECT 1 FROM fee_payments p WHERE p.fee_invoice_id=i.id)
                  AND COALESCE(i.updated_at,i.created_at)>=:from2
                  AND COALESCE(i.updated_at,i.created_at)<:to2
                GROUP BY ym
            ) collected GROUP BY ym",
            [
                'i1' => $institute, 'from1' => $start->format('Y-m-01'), 'to1' => $end,
                'i2' => $institute, 'from2' => $start->format('Y-m-01'), 'to2' => $end,
            ]
        )->fetchAll();

        foreach ($feeRows as $row) {
            if (isset($income[$row['ym']])) {
                $income[$row['ym']] += (float) $row['total'];
            }
        }

        $salaryRows = $this->db->query(
            "SELECT DATE_FORMAT(payment_date,'%Y-%m') ym, SUM(paid_amount) total
             FROM salary_payments sp
             JOIN employees e ON e.id=sp.employee_id
             WHERE e.institute_id=:i
               AND sp.payment_date>=:from
               AND sp.payment_date<:to
             GROUP BY ym",
            ['i' => $institute, 'from' => $start->format('Y-m-01'), 'to' => $end]
        )->fetchAll();

        foreach ($salaryRows as $row) {
            if (isset($expense[$row['ym']])) {
                $expense[$row['ym']] += (float) $row['total'];
            }
        }

        $incomes = array_values($income);
        $expenses = array_values($expense);
        // The Revenue / Total Profit tiles read as "this month", not the six-month chart total.
        $currentMonth = date('Y-m');
        $revenue = $income[$currentMonth] ?? 0.0;
        $costs = $expense[$currentMonth] ?? 0.0;

        // Recalculate paid/remaining from payment rows instead of trusting cached
        // invoice totals. This keeps older production records accurate as well.
        $fee = $this->db->query(
            "SELECT
                COALESCE(SUM(i.total_amount),0) total,
                COALESCE(SUM(COALESCE(p.paid,i.paid_amount,0)),0) paid,
                COALESCE(SUM(GREATEST(i.total_amount-COALESCE(p.paid,i.paid_amount,0),0)),0) remaining
             FROM fee_invoices i
             JOIN students s ON s.id=i.student_id
             LEFT JOIN (
                SELECT fee_invoice_id, SUM(amount) paid
                FROM fee_payments
                WHERE reversed_at IS NULL
                GROUP BY fee_invoice_id
             ) p ON p.fee_invoice_id=i.id
             WHERE s.institute_id=:i AND i.voided_at IS NULL",
            ['i' => $institute]
        )->fetch() ?: ['total' => 0, 'paid' => 0, 'remaining' => 0];

        $today = date('Y-m-d');
        // Late arrivals were in class, so they count as present; Half Day staff likewise.
        $present = $this->countAttendance('student_attendance', 'student_id', $institute, $today, ['Present', 'Late']);
        $absent = $this->countAttendance('student_attendance', 'student_id', $institute, $today, ['Absent']);
        $studentsMarked = $this->countAttendance('student_attendance', 'student_id', $institute, $today, []);
        $staffPresent = $this->countAttendance('employee_attendance', 'employee_id', $institute, $today, ['Present', 'Late', 'Half Day']);
        $staffMarked = $this->countAttendance('employee_attendance', 'employee_id', $institute, $today, []);
        $absentStudents = $this->db->query(
            "SELECT s.student_name, s.registration_no, c.name class_name
             FROM student_attendance a
             JOIN students s ON s.id=a.student_id
             LEFT JOIN classes c ON c.id=s.class_id
             WHERE s.institute_id=:i AND a.attendance_date=:d AND a.status='Absent'
             ORDER BY c.name ASC,s.student_name ASC,s.registration_no ASC,s.id ASC LIMIT 6",
            ['i' => $institute, 'd' => $today]
        )->fetchAll();

        $classes = $this->db->query(
            "SELECT c.name, COUNT(s.id) strength
             FROM classes c
             LEFT JOIN students s ON s.class_id=c.id AND s.status='active'
             WHERE c.institute_id=:i
             GROUP BY c.id,c.name
             ORDER BY c.name",
            ['i' => $institute]
        )->fetchAll();

        $admissions = $this->db->query(
            "SELECT s.student_name,s.registration_no,c.name class_name,s.date_of_admission
             FROM students s
             JOIN classes c ON c.id=s.class_id
             WHERE s.institute_id=:i AND s.date_of_admission>=:from
             ORDER BY s.date_of_admission DESC,s.id DESC LIMIT 8",
            ['i' => $institute, 'from' => date('Y-m-01')]
        )->fetchAll();

        return compact(
            'students', 'employees', 'months', 'incomes', 'expenses', 'revenue',
            'costs', 'fee', 'present', 'absent', 'studentsMarked', 'absentStudents', 'staffPresent', 'staffMarked', 'classes', 'admissions'
        );
    }

    /** Counts the day's records in any of $statuses; an empty list counts every marked record. */
    private function countAttendance(string $table, string $person, int $institute, string $date, array $statuses): int
    {
        $params = ['i' => $institute, 'd' => $date];
        $filter = '';
        if ($statuses !== []) {
            $marks = [];
            foreach (array_values($statuses) as $n => $status) {
                $marks[] = ":s{$n}";
                $params["s{$n}"] = $status;
            }
            $filter = ' AND a.status IN (' . implode(',', $marks) . ')';
        }
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM {$table} a
             JOIN " . ($table === 'student_attendance' ? 'students' : 'employees') . " p ON p.id=a.{$person}
             WHERE p.institute_id=:i AND a.attendance_date=:d AND p.status='active'{$filter}",
            $params
        )->fetchColumn();
    }
}
