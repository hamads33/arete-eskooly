<?php
declare(strict_types=1);

/**
 * A student's written answer to one question may need several page photos (a multi-page
 * handwritten solution), not just one file. attachment_path stays as the first/only file for
 * back-compat with anything still reading it directly; attachment_paths_json is the new
 * authoritative list when a question has more than one uploaded page.
 */
return static function (PDO $pdo): void {
    $cols = $pdo->query('SHOW COLUMNS FROM online_test_answers')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('attachment_paths_json', $cols, true)) {
        $pdo->exec('ALTER TABLE online_test_answers ADD attachment_paths_json TEXT NULL AFTER attachment_path');
    }
};
