<?php
declare(strict_types=1);
return static function(PDO$pdo):void{$pdo->exec("ALTER TABLE fee_invoices MODIFY status ENUM('unpaid','partial','paid','carried_forward') NOT NULL DEFAULT 'unpaid'");};
