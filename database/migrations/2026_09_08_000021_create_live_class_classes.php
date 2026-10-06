<?php

declare(strict_types=1);

/**
 * Allows one Google Meet live class to target several classes while preserving
 * live_classes.target_class_id as the primary/legacy target.
 */
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_class_classes(
        live_class_id BIGINT UNSIGNED NOT NULL,
        class_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY(live_class_id,class_id),
        INDEX idx_live_class_classes_class(class_id,live_class_id),
        CONSTRAINT fk_live_class_classes_live FOREIGN KEY(live_class_id) REFERENCES live_classes(id) ON DELETE CASCADE,
        CONSTRAINT fk_live_class_classes_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $now = date('Y-m-d H:i:s');
    $statement = $pdo->prepare(
        'INSERT IGNORE INTO live_class_classes(live_class_id,class_id,created_at)
         SELECT id,target_class_id,:created FROM live_classes WHERE target_class_id IS NOT NULL'
    );
    $statement->execute(['created' => $now]);
};
