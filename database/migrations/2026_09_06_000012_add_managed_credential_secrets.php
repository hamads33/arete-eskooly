<?php
declare(strict_types=1);
return static function(PDO $pdo):void{foreach(['student_accounts','employee_accounts']as$table){$cols=$pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('password_cipher',$cols,true))$pdo->exec("ALTER TABLE {$table} ADD password_cipher TEXT NULL AFTER user_id");}};
