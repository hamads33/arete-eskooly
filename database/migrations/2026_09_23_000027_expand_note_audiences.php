<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $cols=$pdo->query('SHOW COLUMNS FROM class_notes')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('audience_type',$cols,true))$pdo->exec("ALTER TABLE class_notes ADD audience_type ENUM('all','class','student') NOT NULL DEFAULT 'class' AFTER institute_id");
    if(!in_array('student_id',$cols,true))$pdo->exec('ALTER TABLE class_notes ADD student_id BIGINT UNSIGNED NULL AFTER subject_id, ADD INDEX idx_notes_student(student_id), ADD CONSTRAINT fk_notes_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE');
    if(!in_array('external_url',$cols,true))$pdo->exec('ALTER TABLE class_notes ADD external_url VARCHAR(1000) NULL AFTER attachment_path');
    $pdo->exec('ALTER TABLE class_notes MODIFY class_id BIGINT UNSIGNED NULL, MODIFY subject_id BIGINT UNSIGNED NULL');
};
