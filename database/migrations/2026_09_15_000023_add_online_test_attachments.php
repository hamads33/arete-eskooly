<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $cols=$pdo->query('SHOW COLUMNS FROM online_tests')->fetchAll(PDO::FETCH_COLUMN);if(!in_array('subject_id',$cols,true))$pdo->exec('ALTER TABLE online_tests ADD subject_id BIGINT UNSIGNED NULL AFTER class_id, ADD CONSTRAINT fk_online_tests_subject FOREIGN KEY(subject_id) REFERENCES subjects(id)');
    $cols=$pdo->query('SHOW COLUMNS FROM online_test_questions')->fetchAll(PDO::FETCH_COLUMN);if(!in_array('attachment_path',$cols,true))$pdo->exec('ALTER TABLE online_test_questions ADD attachment_path VARCHAR(255) NULL AFTER question_text');
    $cols=$pdo->query('SHOW COLUMNS FROM online_test_answers')->fetchAll(PDO::FETCH_COLUMN);if(!in_array('attachment_path',$cols,true))$pdo->exec('ALTER TABLE online_test_answers ADD attachment_path VARCHAR(255) NULL AFTER answer_text');
    $pdo->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.slug='online-tests.manage' WHERE r.slug='teacher'");
};
