<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        monthly_tuition DECIMAL(12,2) NOT NULL DEFAULT 0,
        teacher_reference VARCHAR(120) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_class_name (institute_id, name),
        CONSTRAINT fk_classes_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_subject_name (institute_id, name),
        CONSTRAINT fk_subjects_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_subjects (
        class_id BIGINT UNSIGNED NOT NULL,
        subject_id BIGINT UNSIGNED NOT NULL,
        total_marks DECIMAL(7,2) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (class_id, subject_id),
        CONSTRAINT fk_class_subjects_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        CONSTRAINT fk_class_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
