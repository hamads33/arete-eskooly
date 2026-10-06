<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,title VARCHAR(180) NOT NULL,message TEXT NOT NULL,audience ENUM('all','students','parents','employees','teachers') NOT NULL DEFAULT 'all',image_path VARCHAR(255) NULL,show_popup TINYINT(1) NOT NULL DEFAULT 0,status ENUM('draft','published') NOT NULL DEFAULT 'published',publish_at DATETIME NOT NULL,expires_at DATETIME NULL,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX idx_announcement_delivery(institute_id,status,audience,publish_at),CONSTRAINT fk_announcement_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,CONSTRAINT fk_announcement_user FOREIGN KEY(created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
