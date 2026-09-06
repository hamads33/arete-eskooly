<?php
declare(strict_types=1);
return static function(PDO$pdo):void{$pdo->exec("INSERT IGNORE INTO institute_users(user_id,institute_id,created_at) SELECT u.id,i.id,NOW() FROM users u CROSS JOIN (SELECT MIN(id) id FROM institutes HAVING COUNT(*)=1) i JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='admin'");};
