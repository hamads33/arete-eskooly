<?php
declare(strict_types=1);
/**
 * Account access levels.
 *   active    - normal use
 *   suspended - may sign in, but every page shows the restriction notice (e.g. unpaid fees)
 *   disabled  - blocked: sign-in refused
 * access_source records who set the level: 'manual' (an admin; automatic fee rules leave it
 * alone) or 'fee' (the automatic unpaid-fee rule, lifted again automatically once paid).
 */
return static function(PDO $pdo):void{
    $pdo->exec("ALTER TABLE users MODIFY status ENUM('active','suspended','disabled') NOT NULL DEFAULT 'active'");
    $cols=$pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('access_source',$cols,true))$pdo->exec('ALTER TABLE users ADD access_source VARCHAR(10) NULL AFTER status, ADD access_reason VARCHAR(255) NULL AFTER access_source, ADD access_changed_at DATETIME NULL AFTER access_reason');
    $fee=$pdo->query('SHOW COLUMNS FROM fee_settings')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('suspend_after_months',$fee,true))$pdo->exec('ALTER TABLE fee_settings ADD suspend_after_months TINYINT UNSIGNED NOT NULL DEFAULT 0, ADD block_after_months TINYINT UNSIGNED NOT NULL DEFAULT 0');
};
