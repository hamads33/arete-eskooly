<?php
declare(strict_types=1);

/**
 * Inactive / suspended students and employees keep their history.
 *
 * - students/employees.status gains 'suspended'; status_changed_at records from when the current
 *   status applies (so registers and reports show the person for every date before it), and
 *   status_reason records why.
 * - person_status_history keeps every change (who, when, effective from, reason).
 * - person_status_rules lets the admin decide what an inactive or suspended student/employee still
 *   appears in (attendance, fee vouchers / salary list, messages) and whether they can sign in.
 *
 * Purely additive. People already inactive get status_changed_at = their last update time,
 * which is when the status was set (the old code updated updated_at on every status change).
 */
return static function (PDO $pdo): void {
    foreach (['students', 'employees'] as $t) {
        $type = (string) $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='status'")->fetchColumn();
        if (!str_contains($type, "'suspended'")) {
            $pdo->exec("ALTER TABLE {$t} MODIFY status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM {$t}")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('status_changed_at', $cols, true)) {
            $pdo->exec("ALTER TABLE {$t} ADD status_changed_at DATETIME NULL AFTER status");
            $pdo->exec("UPDATE {$t} SET status_changed_at=updated_at WHERE status<>'active'");
        }
        if (!in_array('status_reason', $cols, true)) {
            $pdo->exec("ALTER TABLE {$t} ADD status_reason VARCHAR(255) NULL AFTER status_changed_at");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS person_status_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        person_type ENUM('student','employee') NOT NULL,
        person_id BIGINT UNSIGNED NOT NULL,
        old_status VARCHAR(20) NOT NULL,
        new_status VARCHAR(20) NOT NULL,
        effective_at DATETIME NOT NULL,
        reason VARCHAR(255) NULL,
        changed_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_status_history_person (person_type, person_id, effective_at),
        INDEX idx_status_history_institute (institute_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed one history row for people already inactive, so their timeline is not empty.
    foreach (['students' => 'student', 'employees' => 'employee'] as $t => $kind) {
        $pdo->exec("INSERT INTO person_status_history(institute_id,person_type,person_id,old_status,new_status,effective_at,reason,changed_by,created_at)
            SELECT x.institute_id,'{$kind}',x.id,'active',x.status,x.status_changed_at,'Recorded before status history was introduced',NULL,NOW()
            FROM {$t} x WHERE x.status<>'active' AND x.status_changed_at IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM person_status_history h WHERE h.person_type='{$kind}' AND h.person_id=x.id)");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS person_status_rules (
        institute_id BIGINT UNSIGNED NOT NULL,
        person_type ENUM('student','employee') NOT NULL,
        status ENUM('inactive','suspended') NOT NULL,
        in_attendance TINYINT(1) NOT NULL,
        in_billing TINYINT(1) NOT NULL,
        in_messages TINYINT(1) NOT NULL,
        can_login TINYINT(1) NOT NULL,
        updated_by BIGINT UNSIGNED NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (institute_id, person_type, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
