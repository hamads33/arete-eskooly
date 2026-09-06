<?php
declare(strict_types=1);
/**
 * Behaviour & Skills.
 *
 * eSkooly locks all five of its Behaviour & Skills pages ("available in Desktop Version
 * only"), so the workflow could not be observed directly. The model here follows eSkooly's
 * own public description of the feature — definable Affective (attitude, discipline) and
 * Psychomotor (physical skills, arts) domains, rated per student, with domain reports — plus
 * free-text observations for the "Observations" menu entry.
 *
 * Additive only.
 */
return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS behaviour_traits(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        domain ENUM('affective','psychomotor') NOT NULL,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_behaviour_trait(institute_id,domain,name),
        INDEX idx_behaviour_trait_domain(institute_id,domain,status),
        CONSTRAINT fk_behaviour_trait_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // One rating per student, per trait, per period. Period is a YYYY-MM term marker so a
    // student can be re-rated over time without losing history.
    $pdo->exec("CREATE TABLE IF NOT EXISTS behaviour_ratings(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        trait_id BIGINT UNSIGNED NOT NULL,
        period CHAR(7) NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        remarks VARCHAR(255) NULL,
        rated_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_behaviour_rating(student_id,trait_id,period),
        INDEX idx_behaviour_rating_period(institute_id,period),
        CONSTRAINT fk_behaviour_rating_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_behaviour_rating_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE,
        CONSTRAINT fk_behaviour_rating_trait FOREIGN KEY(trait_id) REFERENCES behaviour_traits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS behaviour_observations(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        observation_date DATE NOT NULL,
        category ENUM('positive','concern','neutral') NOT NULL DEFAULT 'neutral',
        note TEXT NOT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_observation_student(student_id,observation_date),
        CONSTRAINT fk_observation_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_observation_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Starter traits per institute, matching eSkooly's stated domains. Idempotent.
    $now = date('Y-m-d H:i:s');
    $seed = [
        ['affective', 'Punctuality', 'Arrives on time and prepared', 1],
        ['affective', 'Discipline', 'Follows institute rules and instructions', 2],
        ['affective', 'Attitude to Learning', 'Engagement and willingness to participate', 3],
        ['affective', 'Cooperation', 'Works well with peers and staff', 4],
        ['affective', 'Honesty', 'Truthfulness and integrity', 5],
        ['affective', 'Responsibility', 'Takes ownership of work and belongings', 6],
        ['psychomotor', 'Handwriting', 'Legibility and neatness of written work', 1],
        ['psychomotor', 'Sports', 'Participation and physical coordination', 2],
        ['psychomotor', 'Drawing & Art', 'Creative and artistic skill', 3],
        ['psychomotor', 'Music', 'Musical participation and skill', 4],
        ['psychomotor', 'Practical Work', 'Handling of laboratory or practical tasks', 5],
        ['psychomotor', 'Public Speaking', 'Confidence and clarity when presenting', 6],
    ];
    $statement = $pdo->prepare(
        'INSERT IGNORE INTO behaviour_traits(institute_id,domain,name,description,sort_order,status,created_at,updated_at)
         SELECT id,:domain,:name,:description,:sort,\'active\',:c,:u FROM institutes'
    );
    foreach ($seed as [$domain, $name, $description, $sort]) {
        $statement->execute(['domain' => $domain, 'name' => $name, 'description' => $description, 'sort' => $sort, 'c' => $now, 'u' => $now]);
    }
};
