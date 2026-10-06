<?php
declare(strict_types=1);
/**
 * Recorded lectures: an uploaded video or a shared link (YouTube, Drive, …) published to all
 * students, selected classes or one student. Uploading is gated by recorded-lectures.manage,
 * which is granted to no role here: only the admin ('*') or a role the admin enables may publish.
 */
return static function(PDO $pdo):void{
    $now=date('Y-m-d H:i:s');
    $pdo->exec("CREATE TABLE IF NOT EXISTS recorded_lectures(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,institute_id BIGINT UNSIGNED NOT NULL,audience_type VARCHAR(10) NOT NULL DEFAULT 'class',student_id BIGINT UNSIGNED NULL,subject_id BIGINT UNSIGNED NULL,teacher_id BIGINT UNSIGNED NULL,title VARCHAR(160) NOT NULL,chapter VARCHAR(160) NULL,description TEXT NULL,video_path VARCHAR(255) NULL,video_url VARCHAR(500) NULL,publish_date DATE NOT NULL,status VARCHAR(10) NOT NULL DEFAULT 'published',created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX idx_lecture_institute(institute_id,status,publish_date),CONSTRAINT fk_lecture_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,CONSTRAINT fk_lecture_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,CONSTRAINT fk_lecture_subject FOREIGN KEY(subject_id) REFERENCES subjects(id) ON DELETE SET NULL,CONSTRAINT fk_lecture_teacher FOREIGN KEY(teacher_id) REFERENCES employees(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS recorded_lecture_classes(lecture_id BIGINT UNSIGNED NOT NULL,class_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(lecture_id,class_id),INDEX idx_lecture_class_class(class_id),CONSTRAINT fk_lecture_class_lecture FOREIGN KEY(lecture_id) REFERENCES recorded_lectures(id) ON DELETE CASCADE,CONSTRAINT fk_lecture_class_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stmt=$pdo->prepare('INSERT IGNORE INTO permissions(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)');
    foreach(['recorded-lectures.view'=>'View recorded lectures','recorded-lectures.manage'=>'Upload and delete recorded lectures']as$slug=>$name)$stmt->execute(['n'=>$name,'s'=>$slug,'c'=>$now,'u'=>$now]);
};
