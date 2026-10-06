<?php
declare(strict_types=1);

/**
 * Fee voucher settings: general payment instructions printed on every challan, which bank
 * accounts to print (NULL = all), and the default "fine after due date". Purely additive.
 */
return static function (PDO $pdo): void {
    $cols = $pdo->query('SHOW COLUMNS FROM fee_settings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('voucher_instructions', $cols, true)) $pdo->exec('ALTER TABLE fee_settings ADD voucher_instructions TEXT NULL');
    if (!in_array('voucher_bank_ids', $cols, true)) $pdo->exec('ALTER TABLE fee_settings ADD voucher_bank_ids VARCHAR(500) NULL');
    if (!in_array('voucher_default_fine', $cols, true)) $pdo->exec('ALTER TABLE fee_settings ADD voucher_default_fine DECIMAL(12,2) NOT NULL DEFAULT 0');
};
