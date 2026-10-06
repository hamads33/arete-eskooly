<?php
declare(strict_types=1);

/**
 * Extra minutes a teacher gives one student on top of the test's time allowance (e.g. a slow
 * connection or a late start). Purely additive; 0 for every existing attempt.
 */
return static function (PDO $pdo): void {
    $cols = $pdo->query('SHOW COLUMNS FROM online_test_attempts')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('extra_minutes', $cols, true)) $pdo->exec('ALTER TABLE online_test_attempts ADD extra_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER started_at');
};
