<?php
declare(strict_types=1);
/**
 * Enables the student and employee portals.
 *
 * Managed logins created by CredentialService historically produced a users row with no
 * role and no institute membership, so AuthService::attempt rejected them as 'restricted'.
 * This migration seeds the portal roles/permissions and backfills both links for every
 * account that already exists. Additive only: no table is dropped or reset.
 */
return static function (PDO $pdo): void {
    $now = date('Y-m-d H:i:s');

    $role = $pdo->prepare('INSERT IGNORE INTO roles(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)');
    foreach ([['Student', 'student'], ['Employee', 'employee']] as [$name, $slug]) {
        $role->execute(['n' => $name, 's' => $slug, 'c' => $now, 'u' => $now]);
    }

    $permission = $pdo->prepare('INSERT IGNORE INTO permissions(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)');
    foreach ([['Student portal access', 'portal.student'], ['Employee portal access', 'portal.employee']] as [$name, $slug]) {
        $permission->execute(['n' => $name, 's' => $slug, 'c' => $now, 'u' => $now]);
    }

    $link = $pdo->prepare(
        'INSERT IGNORE INTO role_permissions(role_id,permission_id)
         SELECT r.id,p.id FROM roles r JOIN permissions p ON p.slug=:permission WHERE r.slug=:role'
    );
    $link->execute(['role' => 'student', 'permission' => 'portal.student']);
    $link->execute(['role' => 'employee', 'permission' => 'portal.employee']);

    // Backfill roles for managed logins that predate this migration.
    $pdo->exec(
        "INSERT IGNORE INTO user_roles(user_id,role_id)
         SELECT sa.user_id,r.id FROM student_accounts sa JOIN roles r ON r.slug='student'"
    );
    $pdo->exec(
        "INSERT IGNORE INTO user_roles(user_id,role_id)
         SELECT ea.user_id,r.id FROM employee_accounts ea JOIN roles r ON r.slug='employee'"
    );

    // Backfill institute membership from the owning record, so portal queries stay tenant-scoped.
    $pdo->exec(
        'INSERT IGNORE INTO institute_users(user_id,institute_id,created_at)
         SELECT sa.user_id,s.institute_id,NOW() FROM student_accounts sa JOIN students s ON s.id=sa.student_id'
    );
    $pdo->exec(
        'INSERT IGNORE INTO institute_users(user_id,institute_id,created_at)
         SELECT ea.user_id,e.institute_id,NOW() FROM employee_accounts ea JOIN employees e ON e.id=ea.employee_id'
    );
};
