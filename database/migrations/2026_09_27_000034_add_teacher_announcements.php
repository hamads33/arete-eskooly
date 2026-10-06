<?php
declare(strict_types=1);
/**
 * Teacher announcements: teacher_id marks an announcement as a teacher's (posted by the teacher,
 * or by the admin in the teacher's name) and class_id optionally limits it to one class.
 */
return static function(PDO $pdo):void{
    $cols=$pdo->query('SHOW COLUMNS FROM announcements')->fetchAll(PDO::FETCH_COLUMN);
    if(!in_array('teacher_id',$cols,true))$pdo->exec('ALTER TABLE announcements ADD teacher_id BIGINT UNSIGNED NULL AFTER audience, ADD CONSTRAINT fk_announcement_teacher FOREIGN KEY(teacher_id) REFERENCES employees(id) ON DELETE SET NULL');
    if(!in_array('class_id',$cols,true))$pdo->exec('ALTER TABLE announcements ADD class_id BIGINT UNSIGNED NULL AFTER teacher_id, ADD CONSTRAINT fk_announcement_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE');
};
