<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Read-only reporting queries.
 *
 * These back the reports eSkooly locks behind its Desktop edition. Nothing here duplicates
 * business rules: totals are derived from the same persisted rows the operational modules
 * write, so a report can never disagree with the ledger it summarises.
 */
final class ReportingRepository
{
    public function __construct(private readonly Database $database) {}

    /* --------------------------------------------------- monthly attendance */

    /** Days in a YYYY-MM period. */
    public function daysInMonth(string $period): int
    {
        [$year, $month] = array_map('intval', explode('-', $period));
        return (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    /**
     * Per-student attendance for one month: a day => status map plus totals.
     * Returns [] for a class with no active students.
     */
    public function studentMonthlyAttendance(int $instituteId, string $period, int $classId = 0): array
    {
        $sql = 'SELECT s.id, s.registration_no, s.student_name, c.name AS class_name,
                       DAY(a.attendance_date) AS day, a.status
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN student_attendance a
                       ON a.student_id = s.id AND DATE_FORMAT(a.attendance_date, \'%Y-%m\') = :p
                WHERE s.institute_id = :i AND s.status = \'active\'';
        $params = ['i' => $instituteId, 'p' => $period];
        if ($classId > 0) {
            $sql .= ' AND s.class_id = :c';
            $params['c'] = $classId;
        }
        return $this->pivot($this->database->query($sql . ' ORDER BY c.name, s.student_name', $params)->fetchAll(), 'student_name');
    }

    public function employeeMonthlyAttendance(int $instituteId, string $period): array
    {
        $rows = $this->database->query(
            'SELECT e.id, e.employee_no AS registration_no, e.name AS student_name, e.role AS class_name,
                    DAY(a.attendance_date) AS day, a.status
             FROM employees e
             LEFT JOIN employee_attendance a
                    ON a.employee_id = e.id AND DATE_FORMAT(a.attendance_date, \'%Y-%m\') = :p
             WHERE e.institute_id = :i AND e.status = \'active\'
             ORDER BY e.name',
            ['i' => $instituteId, 'p' => $period]
        )->fetchAll();
        return $this->pivot($rows, 'student_name');
    }

    /** Collapses the day-per-row result into one row per person with a day => status map. */
    private function pivot(array $rows, string $nameKey): array
    {
        $people = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $people[$id] ??= [
                'id' => $id,
                'registration_no' => $row['registration_no'],
                'name' => $row[$nameKey],
                'group' => $row['class_name'],
                'days' => [],
                'present' => 0, 'absent' => 0, 'leave' => 0, 'total' => 0,
            ];
            if ($row['day'] === null || $row['status'] === null) {
                continue;
            }
            $status = (string) $row['status'];
            $people[$id]['days'][(int) $row['day']] = $status;
            $people[$id]['total']++;
            if ($status === 'Present') {
                $people[$id]['present']++;
            } elseif ($status === 'Absent') {
                $people[$id]['absent']++;
            } else {
                $people[$id]['leave']++;
            }
        }
        foreach ($people as &$person) {
            $person['percentage'] = $person['total'] > 0 ? round($person['present'] / $person['total'] * 100, 1) : 0.0;
        }
        return array_values($people);
    }

    /* ------------------------------------------------------- fee collection */

    /** Payments in a date range, with the collecting user and fee month. */
    public function feeCollection(int $instituteId, string $from, string $to, int $classId = 0): array
    {
        $sql = 'SELECT p.receipt_no, p.payment_date, p.amount, p.payment_method, p.reference,
                       s.registration_no, s.student_name, c.name AS class_name,
                       i.fee_month, u.name AS received_by
                FROM fee_payments p
                JOIN fee_invoices i ON i.id = p.fee_invoice_id
                JOIN students s ON s.id = p.student_id
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN users u ON u.id = p.received_by
                WHERE s.institute_id = :i AND p.payment_date BETWEEN :from AND :to';
        $params = ['i' => $instituteId, 'from' => $from, 'to' => $to];
        if ($classId > 0) {
            $sql .= ' AND s.class_id = :c';
            $params['c'] = $classId;
        }
        return $this->database->query($sql . ' ORDER BY p.payment_date DESC, p.id DESC LIMIT 1000', $params)->fetchAll();
    }

    /** Collection totals grouped by payment method, for the report summary. */
    public function feeCollectionByMethod(int $instituteId, string $from, string $to): array
    {
        return $this->database->query(
            'SELECT p.payment_method, COUNT(*) AS payments, SUM(p.amount) AS total
             FROM fee_payments p JOIN students s ON s.id = p.student_id
             WHERE s.institute_id = :i AND p.payment_date BETWEEN :from AND :to
             GROUP BY p.payment_method ORDER BY total DESC',
            ['i' => $instituteId, 'from' => $from, 'to' => $to]
        )->fetchAll();
    }

    /* ------------------------------------------------------------- accounts */

    /** Income and expense totals per account head. */
    public function accountsSummary(int $instituteId, string $from, string $to): array
    {
        return $this->database->query(
            "SELECT h.name AS head, h.type,
                    SUM(CASE WHEN t.transaction_type='income' THEN t.amount ELSE 0 END) AS income,
                    SUM(CASE WHEN t.transaction_type='expense' THEN t.amount ELSE 0 END) AS expense,
                    COUNT(t.id) AS entries
             FROM account_transactions t
             LEFT JOIN account_heads h ON h.id = t.account_head_id
             WHERE t.institute_id = :i AND t.transaction_date BETWEEN :from AND :to
             GROUP BY h.name, h.type ORDER BY h.name",
            ['i' => $instituteId, 'from' => $from, 'to' => $to]
        )->fetchAll();
    }

    /* --------------------------------------------------------------- salary */

    /** Salary paid per employee across a period range. */
    public function salaryReport(int $instituteId, string $fromMonth, string $toMonth): array
    {
        return $this->database->query(
            'SELECT e.employee_no, e.name, e.role, e.monthly_salary,
                    COUNT(sp.id) AS payments,
                    COALESCE(SUM(sp.paid_amount),0) AS total_paid,
                    COALESCE(SUM(sp.adjustment_amount),0) AS total_adjustment
             FROM employees e
             LEFT JOIN salary_payments sp
                    ON sp.employee_id = e.id AND sp.salary_month BETWEEN :from AND :to
             WHERE e.institute_id = :i AND e.status = \'active\'
             GROUP BY e.id, e.employee_no, e.name, e.role, e.monthly_salary
             ORDER BY e.name',
            ['i' => $instituteId, 'from' => $fromMonth, 'to' => $toMonth]
        )->fetchAll();
    }

    /* ----------------------------------------------------- student progress */

    /**
     * Whole-picture progress for one student: exam averages, class-test averages,
     * attendance and behaviour, in one place.
     */
    public function studentProgress(int $instituteId, int $studentId): array
    {
        $exams = $this->database->query(
            'SELECT x.name AS exam, x.start_date,
                    SUM(m.obtained_marks) AS obtained, SUM(es.total_marks) AS total
             FROM exam_marks m
             JOIN exam_subjects es ON es.id = m.exam_subject_id
             JOIN exams x ON x.id = es.exam_id
             WHERE m.student_id = :s AND x.institute_id = :i
             GROUP BY x.id, x.name, x.start_date ORDER BY x.start_date DESC',
            ['s' => $studentId, 'i' => $instituteId]
        )->fetchAll();

        $tests = $this->database->query(
            'SELECT sub.name AS subject, COUNT(*) AS tests,
                    ROUND(AVG(m.obtained_marks / t.total_marks * 100),2) AS average_percentage
             FROM class_test_marks m
             JOIN class_tests t ON t.id = m.class_test_id
             LEFT JOIN subjects sub ON sub.id = t.subject_id
             WHERE m.student_id = :s AND t.institute_id = :i
             GROUP BY sub.name ORDER BY sub.name',
            ['s' => $studentId, 'i' => $instituteId]
        )->fetchAll();

        $attendance = $this->database->query(
            "SELECT COUNT(*) AS total,
                    SUM(status='Present') AS present,
                    SUM(status='Absent') AS absent
             FROM student_attendance WHERE student_id = :s",
            ['s' => $studentId]
        )->fetch();

        $behaviour = $this->database->query(
            'SELECT t.domain, ROUND(AVG(r.rating),2) AS average_rating, COUNT(*) AS rated
             FROM behaviour_ratings r JOIN behaviour_traits t ON t.id = r.trait_id
             WHERE r.student_id = :s AND r.institute_id = :i
             GROUP BY t.domain',
            ['s' => $studentId, 'i' => $instituteId]
        )->fetchAll();

        return [
            'exams' => $exams,
            'tests' => $tests,
            'attendance' => is_array($attendance) ? $attendance : ['total' => 0, 'present' => 0, 'absent' => 0],
            'behaviour' => $behaviour,
        ];
    }
}
