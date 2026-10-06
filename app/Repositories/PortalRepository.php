<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Self-service queries for the student and employee portals.
 *
 * Every method takes an owner id that the caller resolved from the authenticated session
 * (see PortalMiddleware), never from request input. Institute scoping is applied on top so
 * a record moved between tenants cannot leak.
 */
final class PortalRepository
{
    public function __construct(private readonly Database $database) {}

    /* ---------------------------------------------------------------- identity */

    public function studentByUser(int $userId): ?array
    {
        $row = $this->database->query(
            'SELECT s.*, c.name AS class_name, c.monthly_tuition
             FROM student_accounts sa
             JOIN students s ON s.id = sa.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE sa.user_id = :user LIMIT 1',
            ['user' => $userId]
        )->fetch();
        return is_array($row) ? $row : null;
    }

    public function employeeByUser(int $userId): ?array
    {
        $row = $this->database->query(
            'SELECT e.* FROM employee_accounts ea
             JOIN employees e ON e.id = ea.employee_id
             WHERE ea.user_id = :user LIMIT 1',
            ['user' => $userId]
        )->fetch();
        return is_array($row) ? $row : null;
    }

    /* ----------------------------------------------------------------- student */

    public function studentAttendanceSummary(int $studentId, string $from, string $to): array
    {
        $row = $this->database->query(
            "SELECT
               COUNT(*) AS total,
               SUM(status = 'Present') AS present,
               SUM(status = 'Absent') AS absent,
               SUM(status = 'Late') AS late_count,
               SUM(status = 'Leave') AS leave_count
             FROM student_attendance
             WHERE student_id = :id AND attendance_date BETWEEN :from AND :to",
            ['id' => $studentId, 'from' => $from, 'to' => $to]
        )->fetch();
        return $this->summary(is_array($row) ? $row : []);
    }

    public function studentAttendanceRecords(int $studentId, string $from, string $to, int $limit = 200): array
    {
        return $this->database->query(
            'SELECT attendance_date, status, remarks FROM student_attendance
             WHERE student_id = :id AND attendance_date BETWEEN :from AND :to
             ORDER BY attendance_date DESC LIMIT ' . $this->limit($limit),
            ['id' => $studentId, 'from' => $from, 'to' => $to]
        )->fetchAll();
    }

    public function studentInvoices(int $studentId, int $limit = 60): array
    {
        return $this->database->query(
            'SELECT id, fee_month, invoice_no, total_amount, paid_amount, remaining_amount, status, voided_at, created_at
             FROM fee_invoices WHERE student_id = :id
             ORDER BY fee_month DESC LIMIT ' . $this->limit($limit),
            ['id' => $studentId]
        )->fetchAll();
    }

    public function studentInvoice(int $studentId, int $invoiceId): ?array
    {
        $row = $this->database->query(
            'SELECT * FROM fee_invoices WHERE id = :invoice AND student_id = :student LIMIT 1',
            ['invoice' => $invoiceId, 'student' => $studentId]
        )->fetch();
        return is_array($row) ? $row : null;
    }

    public function invoiceItems(int $invoiceId): array
    {
        return $this->database->query(
            'SELECT particular, amount FROM fee_invoice_items WHERE fee_invoice_id = :id ORDER BY id',
            ['id' => $invoiceId]
        )->fetchAll();
    }

    public function studentPayments(int $studentId, int $limit = 60): array
    {
        return $this->database->query(
            'SELECT id, receipt_no, payment_date, amount, payment_method, reference, reversed_at
             FROM fee_payments WHERE student_id = :id
             ORDER BY payment_date DESC, id DESC LIMIT ' . $this->limit($limit),
            ['id' => $studentId]
        )->fetchAll();
    }

    public function studentFeeTotals(int $studentId): array
    {
        $row = $this->database->query(
            'SELECT COALESCE(SUM(total_amount),0) AS billed,
                    COALESCE(SUM(paid_amount),0) AS paid,
                    COALESCE(SUM(remaining_amount),0) AS outstanding
             FROM fee_invoices WHERE student_id = :id',
            ['id' => $studentId]
        )->fetch();
        return is_array($row) ? $row : ['billed' => 0, 'paid' => 0, 'outstanding' => 0];
    }

    /** One of the student's own payments, joined to its invoice. Null if not theirs. */
    public function studentPayment(int $studentId, int $paymentId): ?array
    {
        $row = $this->database->query(
            'SELECT p.*, i.fee_month FROM fee_payments p
             JOIN fee_invoices i ON i.id = p.fee_invoice_id
             WHERE p.id = :payment AND p.student_id = :student LIMIT 1',
            ['payment' => $paymentId, 'student' => $studentId]
        )->fetch();
        return is_array($row) ? $row : null;
    }

    /** Whether this student has marks recorded for the given exam in their institute. */
    public function studentSatExam(int $studentId, int $instituteId, int $examId): bool
    {
        return (bool) $this->database->query(
            'SELECT 1 FROM exam_marks m
             JOIN exam_subjects es ON es.id = m.exam_subject_id
             JOIN exams x ON x.id = es.exam_id
             WHERE m.student_id = :student AND x.id = :exam AND x.institute_id = :institute LIMIT 1',
            ['student' => $studentId, 'exam' => $examId, 'institute' => $instituteId]
        )->fetchColumn();
    }

    public function studentExamResults(int $studentId, int $instituteId): array
    {
        return $this->database->query(
            'SELECT x.id AS exam_id, x.name AS exam_name, x.start_date, sub.name AS subject,
                    es.total_marks, m.obtained_marks, m.is_absent, m.remarks
             FROM exam_marks m
             JOIN exam_subjects es ON es.id = m.exam_subject_id
             JOIN exams x ON x.id = es.exam_id
             JOIN subjects sub ON sub.id = es.subject_id
             WHERE m.student_id = :student AND x.institute_id = :institute AND x.status = \'published\'
             ORDER BY x.start_date DESC, sub.name',
            ['student' => $studentId, 'institute' => $instituteId]
        )->fetchAll();
    }

    public function studentClassTests(int $studentId, int $instituteId, int $limit = 60): array
    {
        return $this->database->query(
            'SELECT t.title, t.test_date, t.total_marks, sub.name AS subject, m.obtained_marks, m.is_absent, m.remarks
             FROM class_test_marks m
             JOIN class_tests t ON t.id = m.class_test_id
             LEFT JOIN subjects sub ON sub.id = t.subject_id
             WHERE m.student_id = :student AND t.institute_id = :institute AND t.status IN (\'published\',\'completed\')
             ORDER BY t.test_date DESC LIMIT ' . $this->limit($limit),
            ['student' => $studentId, 'institute' => $instituteId]
        )->fetchAll();
    }

    public function studentHomework(int $classId, int $instituteId, int $limit = 60, int $studentId = 0): array
    {
        return $this->database->query(
            'SELECT h.homework_date, h.homework_text, h.attachment_path,
                    sub.name AS subject, e.name AS teacher
             FROM homework h
             LEFT JOIN subjects sub ON sub.id = h.subject_id
             LEFT JOIN employees e ON e.id = h.teacher_id
             WHERE h.teacher_id IN(SELECT id FROM employees WHERE institute_id=:institute) AND h.status = \'published\' AND (h.audience_type=\'all\' OR (h.audience_type=\'class\' AND EXISTS(SELECT 1 FROM homework_classes hc WHERE hc.homework_id=h.id AND hc.class_id=:class)) OR (h.audience_type=\'student\' AND h.student_id=:student))
             ORDER BY h.homework_date DESC LIMIT ' . $this->limit($limit),
            ['class' => $classId, 'student'=>$studentId, 'institute' => $instituteId]
        )->fetchAll();
    }

    public function studentNotes(int$classId,int$instituteId,int$limit=60,int$studentId=0):array
    {
        return $this->database->query('SELECT n.id,n.title,n.chapter,n.description,n.attachment_path,n.external_url,n.publish_date,COALESCE(s.name,"General") subject,e.name teacher,n.audience_type FROM class_notes n LEFT JOIN subjects s ON s.id=n.subject_id LEFT JOIN employees e ON e.id=n.teacher_id WHERE n.institute_id=:institute AND n.status="published" AND (n.audience_type="all" OR (n.audience_type="class" AND EXISTS(SELECT 1 FROM class_note_classes nc WHERE nc.note_id=n.id AND nc.class_id=:class)) OR (n.audience_type="student" AND n.student_id=:student)) ORDER BY n.publish_date DESC,n.id DESC LIMIT '.$this->limit($limit),['class'=>$classId,'student'=>$studentId,'institute'=>$instituteId])->fetchAll();
    }

    public function studentCertificates(int $studentId, int $instituteId): array
    {
        return $this->database->query(
            'SELECT id, template, achievement, certificate_date FROM certificates
             WHERE student_id = :student AND institute_id = :institute AND recipient_type = \'student\'
             ORDER BY certificate_date DESC',
            ['student' => $studentId, 'institute' => $instituteId]
        )->fetchAll();
    }

    /* ---------------------------------------------------------------- employee */

    public function employeeAttendanceSummary(int $employeeId, string $from, string $to): array
    {
        $row = $this->database->query(
            "SELECT
               COUNT(*) AS total,
               SUM(status = 'Present') AS present,
               SUM(status = 'Absent') AS absent,
               SUM(status IN ('Leave','Half Day')) AS leave_count
             FROM employee_attendance
             WHERE employee_id = :id AND attendance_date BETWEEN :from AND :to",
            ['id' => $employeeId, 'from' => $from, 'to' => $to]
        )->fetch();
        return $this->summary(is_array($row) ? $row : []);
    }

    public function employeeAttendanceRecords(int $employeeId, string $from, string $to, int $limit = 200): array
    {
        return $this->database->query(
            'SELECT attendance_date, status, time_in, time_out, remarks FROM employee_attendance
             WHERE employee_id = :id AND attendance_date BETWEEN :from AND :to
             ORDER BY attendance_date DESC LIMIT ' . $this->limit($limit),
            ['id' => $employeeId, 'from' => $from, 'to' => $to]
        )->fetchAll();
    }

    public function employeeSalaryPayments(int $employeeId, int $limit = 60): array
    {
        return $this->database->query(
            'SELECT id, salary_month, base_salary, adjustment_amount, deduction_amount, advance_recovered, paid_amount, payment_date
             FROM salary_payments WHERE employee_id = :id
             ORDER BY salary_month DESC LIMIT ' . $this->limit($limit),
            ['id' => $employeeId]
        )->fetchAll();
    }

    /** One of the employee's own salary payments. Null if not theirs. */
    public function employeeSalaryPayment(int $employeeId, int $paymentId): ?array
    {
        $row = $this->database->query(
            'SELECT * FROM salary_payments WHERE id = :payment AND employee_id = :employee LIMIT 1',
            ['payment' => $paymentId, 'employee' => $employeeId]
        )->fetch();
        return is_array($row) ? $row : null;
    }

    public function employeeSalaryTotal(int $employeeId): float
    {
        return (float) $this->database->query(
            'SELECT COALESCE(SUM(paid_amount),0) FROM salary_payments WHERE employee_id = :id',
            ['id' => $employeeId]
        )->fetchColumn();
    }

    public function employeeHomework(int $employeeId, int $instituteId, int $limit = 60): array
    {
        return $this->database->query(
            'SELECT h.homework_date, h.homework_text, h.attachment_path,
                    c.name AS class_name, sub.name AS subject
             FROM homework h
             JOIN classes c ON c.id = h.class_id
             LEFT JOIN subjects sub ON sub.id = h.subject_id
             WHERE h.teacher_id = :teacher AND c.institute_id = :institute
             ORDER BY h.homework_date DESC LIMIT ' . $this->limit($limit),
            ['teacher' => $employeeId, 'institute' => $instituteId]
        )->fetchAll();
    }

    /**
     * Classes this employee is named on. classes.teacher_reference is free text rather than
     * a foreign key, so this matches on the stored name.
     */
    public function employeeClasses(int $instituteId, string $employeeName, int$employeeId=0): array
    {
        if (trim($employeeName) === '' && $employeeId <= 0) {
            return [];
        }
        return $this->database->query(
            'SELECT DISTINCT c.id, c.name, c.monthly_tuition,
                    (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = \'active\') AS student_count
             FROM classes c
             LEFT JOIN teacher_assignments ta ON ta.class_id=c.id AND ta.employee_id=:employee AND ta.status=\'active\'
             WHERE c.institute_id = :institute AND (c.teacher_reference = :teacher OR ta.id IS NOT NULL)
             ORDER BY c.name',
            ['institute' => $instituteId, 'teacher' => $employeeName, 'employee'=>$employeeId]
        )->fetchAll();
    }

    /* ------------------------------------------------------------------ shared */

    public function grades(int $instituteId): array
    {
        return $this->database->query(
            'SELECT grade, range_from, range_to, status FROM grading_rules
             WHERE institute_id = :institute ORDER BY range_from DESC',
            ['institute' => $instituteId]
        )->fetchAll();
    }

    public function institute(int $instituteId): array
    {
        $row = $this->database->query(
            'SELECT i.*, p.theme, p.language, p.currency_symbol
             FROM institutes i
             LEFT JOIN institute_preferences p ON p.institute_id = i.id
             WHERE i.id = :id LIMIT 1',
            ['id' => $instituteId]
        )->fetch();
        return is_array($row) ? $row : [];
    }

    private function summary(array $row): array
    {
        $total = (int) ($row['total'] ?? 0);
        $present = (int) ($row['present'] ?? 0);
        return [
            'total' => $total,
            'present' => $present,
            'absent' => (int) ($row['absent'] ?? 0),
            'leave' => (int) ($row['leave_count'] ?? 0),
            'late' => (int) ($row['late_count'] ?? 0),
            'percentage' => $total > 0 ? round($present / $total * 100, 1) : 0.0,
        ];
    }

    private function limit(int $limit): int
    {
        return max(1, min($limit, 500));
    }
}
