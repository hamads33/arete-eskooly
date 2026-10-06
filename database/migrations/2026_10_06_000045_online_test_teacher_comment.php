<?php
declare(strict_types=1);

/**
 * An overall comment from the teacher to the student on a marked online test (separate from the
 * per-question feedback). It can be added or changed any time after marking and is shown with
 * the published result. Purely additive.
 */
return static function (PDO $pdo): void {
    $cols = $pdo->query('SHOW COLUMNS FROM online_test_attempts')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('teacher_comment', $cols, true)) $pdo->exec('ALTER TABLE online_test_attempts ADD teacher_comment TEXT NULL');
    if (!in_array('teacher_comment_at', $cols, true)) $pdo->exec('ALTER TABLE online_test_attempts ADD teacher_comment_at DATETIME NULL');
};
