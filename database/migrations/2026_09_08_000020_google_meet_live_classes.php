<?php
declare(strict_types=1);
/**
 * Google Meet live classes.
 *
 * Extends the existing live_classes domain rather than introducing a parallel "meetings" table:
 * live_classes is already institute-scoped, audience-typed and FK-constrained, and both
 * AcademicRepository's class in-use check and its cascade delete point at it. Forking the domain
 * would break those references and orphan existing rows. See docs/GOOGLE-MEET-INTEGRATION-PLAN.md.
 *
 * Additive and idempotent. Legacy rows keep provider='manual' and their room_code.
 */
return static function (PDO $pdo): void {
    $now = date('Y-m-d H:i:s');
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    $has = static function (string $table, string $column) use ($pdo, $database): bool {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=:d AND table_name=:t AND column_name=:c');
        $statement->execute(['d' => $database, 't' => $table, 'c' => $column]);
        return (bool) $statement->fetchColumn();
    };
    $column = static function (string $table, string $name, string $definition) use ($pdo, $has): void {
        if (!$has($table, $name)) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$name} {$definition}");
        }
    };
    $index = static function (string $table, string $name, string $definition) use ($pdo, $database): void {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema=:d AND table_name=:t AND index_name=:i LIMIT 1');
        $statement->execute(['d' => $database, 't' => $table, 'i' => $name]);
        if (!$statement->fetchColumn()) {
            $pdo->exec("ALTER TABLE {$table} ADD {$definition}");
        }
    };

    /* ---------------------------------------------------- institute Google connection */

    // One connection per institute (§4 of the brief): the admin authorises the official institute
    // account once and every teacher schedules through it. Tokens are AES-256-GCM ciphertext
    // produced by App\Services\Google\TokenCipher — never plaintext, never logged, never rendered.
    $pdo->exec("CREATE TABLE IF NOT EXISTS google_oauth_connections(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institute_id BIGINT UNSIGNED NOT NULL,
        provider VARCHAR(40) NOT NULL DEFAULT 'google_calendar',
        connected_by_user_id BIGINT UNSIGNED NULL,
        account_email VARCHAR(190) NULL,
        calendar_id VARCHAR(190) NOT NULL DEFAULT 'primary',
        access_token_encrypted TEXT NULL,
        refresh_token_encrypted TEXT NULL,
        token_expires_at DATETIME NULL,
        scopes TEXT NULL,
        status ENUM('connected','reconnect_required','disconnected') NOT NULL DEFAULT 'disconnected',
        last_error VARCHAR(255) NULL,
        connected_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY uq_google_connection(institute_id,provider),
        CONSTRAINT fk_google_connection_institute FOREIGN KEY(institute_id) REFERENCES institutes(id) ON DELETE CASCADE,
        CONSTRAINT fk_google_connection_user FOREIGN KEY(connected_by_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* ------------------------------------------------------------ live_classes columns */

    $column('live_classes', 'provider', "ENUM('manual','google_meet') NOT NULL DEFAULT 'manual' AFTER institute_id");
    $column('live_classes', 'created_by_role', "VARCHAR(40) NOT NULL DEFAULT 'admin' AFTER created_by");
    $column('live_classes', 'teacher_id', 'BIGINT UNSIGNED NULL AFTER target_employee_id');
    $column('live_classes', 'subject_id', 'BIGINT UNSIGNED NULL AFTER target_class_id');
    $column('live_classes', 'section_id', 'BIGINT UNSIGNED NULL AFTER subject_id');
    $column('live_classes', 'description', 'TEXT NULL AFTER title');
    $column('live_classes', 'start_time', 'DATETIME NULL AFTER scheduled_at');
    $column('live_classes', 'end_time', 'DATETIME NULL AFTER start_time');
    $column('live_classes', 'timezone', "VARCHAR(64) NOT NULL DEFAULT 'Asia/Karachi' AFTER end_time");
    $column('live_classes', 'google_event_id', 'VARCHAR(190) NULL AFTER timezone');
    $column('live_classes', 'google_calendar_id', 'VARCHAR(190) NULL AFTER google_event_id');
    $column('live_classes', 'meet_link', 'VARCHAR(255) NULL AFTER google_calendar_id');
    $column('live_classes', 'conference_status', "VARCHAR(40) NULL AFTER meet_link");
    $column('live_classes', 'status', "ENUM('scheduled','completed','cancelled','google_error') NOT NULL DEFAULT 'scheduled' AFTER conference_status");
    $column('live_classes', 'google_sync_status', "ENUM('synced','pending','failed','not_applicable') NOT NULL DEFAULT 'not_applicable' AFTER status");
    $column('live_classes', 'last_google_error', 'VARCHAR(255) NULL AFTER google_sync_status');
    $column('live_classes', 'cancelled_at', 'DATETIME NULL AFTER last_google_error');
    $column('live_classes', 'cancelled_by', 'BIGINT UNSIGNED NULL AFTER cancelled_at');

    // Google-backed rows have no self-hosted room code. MySQL allows repeated NULLs in a unique
    // index, so uq_live_room keeps protecting real room codes without blocking Meet rows.
    $pdo->exec('ALTER TABLE live_classes MODIFY room_code VARCHAR(100) NULL');

    // The eSkooly audience vocabulary is extended, not replaced, so existing rows stay valid:
    // SEC = one section, SEL = an explicitly chosen set of students.
    $pdo->exec("ALTER TABLE live_classes MODIFY audience_type ENUM('AS','AT','SC','SS','ST','SEC','SEL') NOT NULL");

    $index('live_classes', 'uq_live_google_event', 'UNIQUE KEY uq_live_google_event(institute_id,google_event_id)');
    $index('live_classes', 'idx_live_window', 'INDEX idx_live_window(institute_id,start_time,end_time)');
    $index('live_classes', 'idx_live_teacher', 'INDEX idx_live_teacher(institute_id,teacher_id,status)');

    foreach ([
        ['fk_live_teacher', 'teacher_id', 'employees'],
        ['fk_live_subject', 'subject_id', 'subjects'],
        ['fk_live_section', 'section_id', 'class_sections'],
    ] as [$name, $columnName, $table]) {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.table_constraints WHERE table_schema=:d AND table_name=\'live_classes\' AND constraint_name=:n');
        $statement->execute(['d' => $database, 'n' => $name]);
        if (!$statement->fetchColumn()) {
            $pdo->exec("ALTER TABLE live_classes ADD CONSTRAINT {$name} FOREIGN KEY({$columnName}) REFERENCES {$table}(id) ON DELETE SET NULL");
        }
    }

    /* ------------------------------------------------------------------- participants */

    // Only the SEL audience needs rows here. Class- and section-wide meetings resolve their
    // audience from students live, so a transferred student immediately gains and loses the
    // right meetings instead of being frozen into a snapshot taken at scheduling time.
    //
    // join_clicked_at records that the student clicked our Join action. It is NOT evidence the
    // student entered the Google Meet: Google exposes no attendance for this integration, and
    // nothing in the product may present this column as verified attendance.
    $pdo->exec("CREATE TABLE IF NOT EXISTS live_class_participants(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        live_class_id BIGINT UNSIGNED NOT NULL,
        student_id BIGINT UNSIGNED NOT NULL,
        join_clicked_at DATETIME NULL COMMENT 'LMS join-link click, not verified Google Meet attendance',
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_live_participant(live_class_id,student_id),
        INDEX idx_live_participant_student(student_id),
        CONSTRAINT fk_live_participant_class FOREIGN KEY(live_class_id) REFERENCES live_classes(id) ON DELETE CASCADE,
        CONSTRAINT fk_live_participant_student FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    /* -------------------------------------------------------------------- permissions */

    $permissions = [
        'live-classes.view' => 'View live classes',
        'live-classes.manage' => 'Schedule, edit and cancel live classes',
        'live-classes.instant' => 'Start an instant live class',
        'live-classes.manage-all' => 'Manage every teacher\'s live classes',
        'google-calendar.manage' => 'Connect and disconnect the institute Google account',
    ];
    $insertPermission = $pdo->prepare('INSERT IGNORE INTO permissions(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)');
    foreach ($permissions as $slug => $name) {
        $insertPermission->execute(['n' => $name, 's' => $slug, 'c' => $now, 'u' => $now]);
    }

    $link = $pdo->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.slug=:permission WHERE r.slug=:role');
    $grants = [
        // Admin holds '*', so it needs no explicit grant; principal reads, teacher hosts.
        'principal' => ['live-classes.view'],
        'teacher' => ['live-classes.view', 'live-classes.manage', 'live-classes.instant'],
        'management-staff' => ['live-classes.view'],
    ];
    foreach ($grants as $role => $slugs) {
        foreach ($slugs as $slug) {
            $link->execute(['role' => $role, 'permission' => $slug]);
        }
    }
    // google-calendar.manage is deliberately granted to no role but admin ('*'): teachers must
    // never reach the OAuth routes or see any part of the institute's Google credentials.
};
