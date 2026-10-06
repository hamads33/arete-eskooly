<?php
declare(strict_types=1);
return static function (PDO $pdo): void {
    if (!$pdo->query("SHOW COLUMNS FROM fee_settings LIKE 'reminder_enabled'")->fetch()) $pdo->exec("ALTER TABLE fee_settings ADD reminder_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_methods_json");
    if (!$pdo->query("SHOW COLUMNS FROM fee_settings LIKE 'reminder_message'")->fetch()) $pdo->exec("ALTER TABLE fee_settings ADD reminder_message VARCHAR(500) NULL AFTER reminder_enabled");
};
