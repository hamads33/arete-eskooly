<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $sql = [
        "CREATE TABLE IF NOT EXISTS institutes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            target_line VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            website VARCHAR(255) NULL,
            address TEXT NOT NULL,
            country VARCHAR(100) NOT NULL,
            logo_path VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS institute_preferences (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            institute_id BIGINT UNSIGNED NOT NULL UNIQUE,
            timezone VARCHAR(80) NOT NULL DEFAULT 'Asia/Karachi',
            currency CHAR(3) NOT NULL DEFAULT 'PKR',
            currency_symbol VARCHAR(8) NOT NULL DEFAULT 'Rs',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT fk_preferences_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS fee_particulars (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            institute_id BIGINT UNSIGNED NOT NULL,
            month_scope ENUM('all','specific') NOT NULL,
            month_number TINYINT UNSIGNED NULL,
            target_scope ENUM('all','class','student') NOT NULL,
            target_reference VARCHAR(120) NULL,
            item_key VARCHAR(60) NOT NULL,
            label VARCHAR(120) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            is_fixed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_fee_scope_item (institute_id, month_scope, month_number, target_scope, target_reference, item_key),
            INDEX idx_fee_resolution (institute_id, target_scope, target_reference, month_scope, month_number),
            CONSTRAINT fk_fee_particulars_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS fee_invoice_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            institute_id BIGINT UNSIGNED NOT NULL,
            bank_name VARCHAR(120) NOT NULL,
            account_number VARCHAR(100) NOT NULL,
            bank_address TEXT NOT NULL,
            instructions TEXT NULL,
            logo_path VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_invoice_accounts_institute (institute_id),
            CONSTRAINT fk_invoice_accounts_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            institute_id BIGINT UNSIGNED NOT NULL,
            audience ENUM('student','employee') NOT NULL,
            content MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_rules_audience (institute_id, audience),
            CONSTRAINT fk_rules_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS grading_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            institute_id BIGINT UNSIGNED NOT NULL,
            grade VARCHAR(8) NOT NULL,
            range_from DECIMAL(5,2) NOT NULL,
            range_to DECIMAL(5,2) NOT NULL,
            status ENUM('PASS','FAIL') NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_grading_grade (institute_id, grade),
            CONSTRAINT fk_grading_institute FOREIGN KEY (institute_id) REFERENCES institutes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($sql as $statement) $pdo->exec($statement);

    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO institutes (name,target_line,phone,website,address,country,created_at,updated_at)
        SELECT 'Arete Coaching Center','Learning today, leading tomorrow','+92 300 1234567','https://example.edu','Islamabad, Pakistan','Pakistan',:created,:updated
        WHERE NOT EXISTS (SELECT 1 FROM institutes LIMIT 1)")->execute(['created' => $now, 'updated' => $now]);
    $pdo->prepare("INSERT IGNORE INTO institute_preferences (institute_id,timezone,currency,currency_symbol,created_at,updated_at)
        SELECT id,'Asia/Karachi','PKR','Rs',:created,:updated FROM institutes ORDER BY id LIMIT 1")->execute(['created' => $now, 'updated' => $now]);
};
