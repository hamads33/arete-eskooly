<?php
declare(strict_types=1);

/**
 * Exam Center: an exam (e.g. "Weekly Test No 02") is the container for its class + subject papers.
 *
 * - exam_classes: classes taking the exam. Their active students are included automatically.
 *   Older exams have no rows here; their classes are still read from their papers.
 * - exam_students: individual students added to an exam (sitting a chosen class's papers) or
 *   excluded from it. One row per student per exam, so nobody is counted twice.
 * - exam_subjects (papers) gain a review / publish state, so a teacher can send marks for review
 *   and results can be published per paper, per class or for the whole exam.
 * - exam_settings: what the admin lets teachers do. Both are off by default.
 *
 * Purely additive: no existing row is changed, and existing exams keep their current status.
 */
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_classes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,exam_id BIGINT UNSIGNED NOT NULL,class_id BIGINT UNSIGNED NOT NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_exam_class(exam_id,class_id),INDEX idx_exam_class_class(class_id),CONSTRAINT fk_exam_classes_exam FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE,CONSTRAINT fk_exam_classes_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_students (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,exam_id BIGINT UNSIGNED NOT NULL,student_id BIGINT UNSIGNED NOT NULL,mode ENUM('include','exclude') NOT NULL,class_id BIGINT UNSIGNED NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_exam_student(exam_id,student_id),INDEX idx_exam_students_class(exam_id,class_id),CONSTRAINT fk_exam_students_exam FOREIGN KEY(exam_id) REFERENCES exams(id) ON DELETE CASCADE,CONSTRAINT fk_exam_students_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,CONSTRAINT fk_exam_students_class FOREIGN KEY(class_id) REFERENCES classes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_settings (institute_id BIGINT UNSIGNED PRIMARY KEY,teachers_add_papers TINYINT(1) NOT NULL DEFAULT 0,teachers_publish TINYINT(1) NOT NULL DEFAULT 0,updated_by BIGINT UNSIGNED NULL,updated_at DATETIME NULL,CONSTRAINT fk_exam_settings_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cols = $pdo->query('SHOW COLUMNS FROM exam_subjects')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('marks_status', $cols, true)) $pdo->exec("ALTER TABLE exam_subjects ADD marks_status ENUM('open','submitted') NOT NULL DEFAULT 'open' AFTER total_marks");
    if (!in_array('submitted_at', $cols, true)) $pdo->exec('ALTER TABLE exam_subjects ADD submitted_at DATETIME NULL AFTER marks_status, ADD submitted_by BIGINT UNSIGNED NULL AFTER submitted_at');
    if (!in_array('published_at', $cols, true)) $pdo->exec('ALTER TABLE exam_subjects ADD published_at DATETIME NULL AFTER submitted_by, ADD published_by BIGINT UNSIGNED NULL AFTER published_at');
    if (!in_array('created_by', $cols, true)) $pdo->exec('ALTER TABLE exam_subjects ADD created_by BIGINT UNSIGNED NULL AFTER published_by');
};
