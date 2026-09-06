<?php
declare(strict_types=1);
return static function(PDO$pdo):void{
$columns=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institute_preferences'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('theme',$columns,true))$pdo->exec("ALTER TABLE institute_preferences ADD theme ENUM('maroon','system') NOT NULL DEFAULT 'maroon' AFTER currency_symbol");
if(!in_array('language',$columns,true))$pdo->exec("ALTER TABLE institute_preferences ADD language VARCHAR(10) NOT NULL DEFAULT 'en' AFTER theme");
$pdo->exec("CREATE TABLE IF NOT EXISTS discount_types(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,discount_mode ENUM('percentage','fixed') NOT NULL,discount_value DECIMAL(12,2) NOT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_discount_name(institute_id,name),INDEX idx_discount_status(institute_id,status),CONSTRAINT fk_discount_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
