<?php
declare(strict_types=1);
/**
 * Salary advances and an explicit deduction on salary payments.
 *
 * salary_payments.adjustment_amount stays exactly as before (net bonus/penalty already used by
 * every slip, report and portal view), so nothing that reads it needs to change. deduction_amount
 * and advance_recovered are new, purely additive columns that break that net figure down for the
 * Pay Salary form and receipt: adjustment_amount = bonus - deduction_amount - advance_recovered.
 */
return static function (PDO $pdo): void {
    $cols = $pdo->query('SHOW COLUMNS FROM salary_payments')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('deduction_amount', $cols, true)) {
        $pdo->exec('ALTER TABLE salary_payments ADD deduction_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER adjustment_amount');
    }
    if (!in_array('advance_recovered', $cols, true)) {
        $pdo->exec('ALTER TABLE salary_payments ADD advance_recovered DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deduction_amount');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS salary_advances (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        employee_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        recovered_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        date_given DATE NOT NULL,
        reason VARCHAR(255) NULL,
        status ENUM('outstanding','settled','written_off') NOT NULL DEFAULT 'outstanding',
        created_by BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_salary_advance_employee(employee_id,status),
        INDEX idx_salary_advance_institute(institute_id,status),
        CONSTRAINT fk_salary_advance_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_salary_advance_employee FOREIGN KEY(employee_id) REFERENCES employees(id),
        CONSTRAINT fk_salary_advance_user FOREIGN KEY(created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS salary_advance_recoveries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        salary_advance_id BIGINT UNSIGNED NOT NULL,
        salary_payment_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_recovery_advance(salary_advance_id),
        CONSTRAINT fk_recovery_advance FOREIGN KEY(salary_advance_id) REFERENCES salary_advances(id) ON DELETE CASCADE,
        CONSTRAINT fk_recovery_payment FOREIGN KEY(salary_payment_id) REFERENCES salary_payments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
