<?php
declare(strict_types=1);

/**
 * Student answer uploads: how many pages a student may upload per test (institute default plus
 * an optional per-test value) and how many days submitted answer images are kept before the
 * daily cleanup removes them (0 = keep forever). Purely additive; existing rows keep working.
 */
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS online_test_settings (
        institute_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        max_pages SMALLINT UNSIGNED NOT NULL DEFAULT 20,
        retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
        last_cleanup_at DATETIME NULL,
        last_cleanup_files INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NULL,
        CONSTRAINT fk_online_test_settings_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $cols = $pdo->query('SHOW COLUMNS FROM online_tests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('max_pages', $cols, true)) $pdo->exec('ALTER TABLE online_tests ADD max_pages SMALLINT UNSIGNED NULL AFTER duration_minutes');
    $cols = $pdo->query('SHOW COLUMNS FROM online_test_attempts')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('files_purged_at', $cols, true)) $pdo->exec('ALTER TABLE online_test_attempts ADD files_purged_at DATETIME NULL');
};
