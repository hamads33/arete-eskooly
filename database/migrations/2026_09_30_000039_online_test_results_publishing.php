<?php
declare(strict_types=1);
/**
 * Result publishing and marked-paper upload for online tests.
 *
 * A marked attempt is not visible to the student or parent until an admin/teacher explicitly
 * publishes it (published_at set), and visible_to controls whether the student, the parent, or
 * both may see it. Existing marked attempts are backfilled as published to 'both' (their
 * marked_at time) so nothing that was already visible under the old behaviour disappears.
 */
return static function (PDO $pdo): void {
    $cols = $pdo->query('SHOW COLUMNS FROM online_test_attempts')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('published_at', $cols, true)) {
        $pdo->exec("ALTER TABLE online_test_attempts ADD published_at DATETIME NULL AFTER marked_by");
    }
    if (!in_array('visible_to', $cols, true)) {
        $pdo->exec("ALTER TABLE online_test_attempts ADD visible_to ENUM('student','parent','both') NOT NULL DEFAULT 'both' AFTER published_at");
    }
    if (!in_array('marked_files_json', $cols, true)) {
        $pdo->exec("ALTER TABLE online_test_attempts ADD marked_files_json TEXT NULL AFTER submission_files_json");
    }
    $pdo->exec("UPDATE online_test_attempts SET published_at=marked_at WHERE status='marked' AND published_at IS NULL");
};
