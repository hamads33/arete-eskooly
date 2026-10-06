<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $hasMode = $pdo->query("SHOW COLUMNS FROM online_tests LIKE 'answer_mode'")->fetch();
    if (!$hasMode) {
        $pdo->exec("ALTER TABLE online_tests ADD answer_mode ENUM('mixed','upload_only') NOT NULL DEFAULT 'mixed' AFTER instructions");
    }

    $hasFiles = $pdo->query("SHOW COLUMNS FROM online_test_attempts LIKE 'submission_files_json'")->fetch();
    if (!$hasFiles) {
        $pdo->exec('ALTER TABLE online_test_attempts ADD submission_files_json TEXT NULL AFTER submitted_at');
    }
};
