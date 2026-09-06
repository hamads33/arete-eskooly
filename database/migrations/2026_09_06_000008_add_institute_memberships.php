<?php
declare(strict_types=1);
return static function(PDO $pdo):void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS institute_users (user_id BIGINT UNSIGNED PRIMARY KEY, institute_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, INDEX idx_membership_institute(institute_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(institute_id) REFERENCES institutes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec('INSERT IGNORE INTO institute_users(user_id,institute_id,created_at) SELECT sa.user_id,s.institute_id,NOW() FROM student_accounts sa JOIN students s ON s.id=sa.student_id');
    $pdo->exec('INSERT IGNORE INTO institute_users(user_id,institute_id,created_at) SELECT ea.user_id,e.institute_id,NOW() FROM employee_accounts ea JOIN employees e ON e.id=ea.employee_id');
    $pdo->exec('INSERT IGNORE INTO institute_users(user_id,institute_id,created_at) SELECT u.id,i.id,NOW() FROM users u CROSS JOIN (SELECT id FROM institutes ORDER BY id LIMIT 1) i');
};
