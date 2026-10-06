<?php
declare(strict_types=1);
return static function(PDO$pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,selector CHAR(32) NOT NULL UNIQUE,token_hash CHAR(64) NOT NULL,expires_at DATETIME NOT NULL,created_at DATETIME NOT NULL,INDEX idx_remember_user(user_id),INDEX idx_remember_expiry(expires_at),CONSTRAINT fk_remember_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
