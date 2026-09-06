<?php
declare(strict_types=1);
/**
 * Makes RBAC real.
 *
 * Before this, 10 of 12 seeded permission slugs were checked nowhere and /admin was gated on
 * the admin role alone, so the product was effectively single-role. AdminAreaMiddleware now
 * resolves a permission per module/method from config/permissions.php; this migration seeds
 * the matching permissions, the staff roles, and their grants.
 *
 * Additive and idempotent. The admin role receives the '*' wildcard, so administrators keep
 * unrestricted access exactly as before.
 */
return static function (PDO $pdo): void {
    $now = date('Y-m-d H:i:s');

    $permissions = [
        '*' => 'Full access',
        'dashboard.view' => 'View dashboard',
        'settings.view' => 'View settings', 'settings.manage' => 'Manage settings',
        'students.view' => 'View students', 'students.manage' => 'Manage students',
        'employees.view' => 'View employees', 'employees.manage' => 'Manage employees',
        'classes.view' => 'View classes', 'classes.manage' => 'Manage classes',
        'subjects.view' => 'View subjects', 'subjects.manage' => 'Manage subjects',
        'accounts.view' => 'View accounts', 'accounts.manage' => 'Manage accounts',
        'fees.view' => 'View fees', 'fees.manage' => 'Manage fees',
        'salary.view' => 'View salary', 'salary.manage' => 'Manage salary',
        'attendance.view' => 'View attendance', 'attendance.manage' => 'Mark attendance',
        'homework.view' => 'View homework', 'homework.manage' => 'Manage homework',
        'exams.view' => 'View exams', 'exams.manage' => 'Manage exams and marks',
        'class-tests.view' => 'View class tests', 'class-tests.manage' => 'Manage class tests',
        'behaviour.view' => 'View behaviour and skills', 'behaviour.manage' => 'Rate behaviour and skills',
        'reports.view' => 'View reports',
        'certificates.view' => 'View certificates', 'certificates.manage' => 'Issue certificates',
        'communication.view' => 'View messaging', 'communication.manage' => 'Send messages',
        'roles.view' => 'View roles and permissions', 'roles.manage' => 'Manage roles and permissions',
        'portal.student' => 'Student portal access',
        'portal.employee' => 'Employee portal access',
    ];
    $insertPermission = $pdo->prepare('INSERT IGNORE INTO permissions(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)');
    foreach ($permissions as $slug => $name) {
        $insertPermission->execute(['n' => $name, 's' => $slug, 'c' => $now, 'u' => $now]);
    }

    $roles = [
        'admin' => 'Administrator',
        'principal' => 'Principal',
        'teacher' => 'Teacher',
        'accountant' => 'Accountant',
        'management-staff' => 'Management Staff',
        'student' => 'Student',
        'employee' => 'Employee',
    ];
    $insertRole = $pdo->prepare('INSERT IGNORE INTO roles(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)');
    foreach ($roles as $slug => $name) {
        $insertRole->execute(['n' => $name, 's' => $slug, 'c' => $now, 'u' => $now]);
    }

    $grants = [
        'admin' => ['*'],
        'principal' => [
            'dashboard.view', 'students.view', 'employees.view', 'classes.view', 'subjects.view',
            'attendance.view', 'homework.view', 'exams.view', 'class-tests.view', 'behaviour.view',
            'reports.view', 'certificates.view', 'certificates.manage',
            'communication.view', 'communication.manage', 'fees.view', 'salary.view', 'accounts.view',
        ],
        'teacher' => [
            'dashboard.view', 'students.view', 'classes.view', 'subjects.view',
            'attendance.view', 'attendance.manage',
            'homework.view', 'homework.manage',
            'exams.view', 'exams.manage',
            'class-tests.view', 'class-tests.manage',
            'behaviour.view', 'behaviour.manage',
            'reports.view', 'communication.view', 'communication.manage',
        ],
        'accountant' => [
            'dashboard.view', 'students.view', 'fees.view', 'fees.manage',
            'accounts.view', 'accounts.manage', 'salary.view', 'salary.manage',
            'reports.view', 'communication.view', 'communication.manage',
        ],
        'management-staff' => [
            'dashboard.view', 'students.view', 'students.manage', 'employees.view',
            'classes.view', 'subjects.view', 'attendance.view', 'homework.view',
            'reports.view', 'communication.view', 'communication.manage',
        ],
        'student' => ['portal.student'],
        'employee' => ['portal.employee'],
    ];
    $link = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions(role_id,permission_id)
         SELECT r.id,p.id FROM roles r JOIN permissions p ON p.slug=:permission WHERE r.slug=:role'
    );
    foreach ($grants as $role => $slugs) {
        foreach ($slugs as $slug) {
            $link->execute(['role' => $role, 'permission' => $slug]);
        }
    }

    // System roles must not be deleted or re-scoped from the UI.
    $pdo->exec("CREATE TABLE IF NOT EXISTS role_meta(
        role_id BIGINT UNSIGNED PRIMARY KEY,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        description VARCHAR(255) NOT NULL DEFAULT '',
        CONSTRAINT fk_role_meta_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec(
        "INSERT IGNORE INTO role_meta(role_id,is_system,description)
         SELECT id,1,'Built-in role' FROM roles WHERE slug IN ('admin','student','employee')"
    );
    $pdo->exec(
        "INSERT IGNORE INTO role_meta(role_id,is_system,description)
         SELECT id,0,'Staff role' FROM roles WHERE slug IN ('principal','teacher','accountant','management-staff')"
    );
};
