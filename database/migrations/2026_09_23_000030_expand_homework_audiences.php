<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $cols=$pdo->query('SHOW COLUMNS FROM homework')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('audience_type',$cols,true))$pdo->exec("ALTER TABLE homework ADD audience_type ENUM('all','class','student') NOT NULL DEFAULT 'class' AFTER homework_date");
    if(!in_array('student_id',$cols,true))$pdo->exec('ALTER TABLE homework ADD student_id BIGINT UNSIGNED NULL AFTER subject_id, ADD INDEX idx_homework_student(student_id), ADD CONSTRAINT fk_homework_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE');
    $pdo->exec('ALTER TABLE homework MODIFY class_id BIGINT UNSIGNED NULL, MODIFY subject_id BIGINT UNSIGNED NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS homework_classes(homework_id BIGINT UNSIGNED NOT NULL,class_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(homework_id,class_id),INDEX idx_homework_class_class(class_id),CONSTRAINT fk_homework_class_homework FOREIGN KEY(homework_id) REFERENCES homework(id) ON DELETE CASCADE,CONSTRAINT fk_homework_class_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO homework_classes(homework_id,class_id) SELECT id,class_id FROM homework WHERE audience_type='class' AND class_id IS NOT NULL");
};
