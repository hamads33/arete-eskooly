<?php
declare(strict_types=1);
/** Remembers which portal pop-up notifications (new lecture, homework, fee due) a user has dismissed. */
return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_notification_reads(user_id BIGINT UNSIGNED NOT NULL,item_key VARCHAR(80) NOT NULL,read_at DATETIME NOT NULL,PRIMARY KEY(user_id,item_key),CONSTRAINT fk_notification_reads_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
