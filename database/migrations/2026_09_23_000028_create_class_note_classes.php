<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_note_classes(note_id BIGINT UNSIGNED NOT NULL,class_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(note_id,class_id),INDEX idx_note_class_class(class_id),CONSTRAINT fk_note_class_note FOREIGN KEY(note_id) REFERENCES class_notes(id) ON DELETE CASCADE,CONSTRAINT fk_note_class_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO class_note_classes(note_id,class_id) SELECT id,class_id FROM class_notes WHERE audience_type='class' AND class_id IS NOT NULL");
};
