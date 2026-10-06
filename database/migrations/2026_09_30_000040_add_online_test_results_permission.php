<?php
declare(strict_types=1);

/**
 * A dedicated "manage results" permission, split out from online-tests.manage, so an admin can
 * grant/restrict publishing and downloading test results independently of who may create or
 * delete tests. Granted by default to every role that already holds online-tests.manage, so
 * nobody loses access on upgrade; the admin can then revoke it per-role from Roles & Permissions.
 */
return static function (PDO $pdo): void {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT IGNORE INTO permissions(name,slug,created_at,updated_at) VALUES(?,?,?,?)');
    $stmt->execute(['Publish & manage online test results', 'online-tests.results', $now, $now]);

    $pdo->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id)
        SELECT rp.role_id, p.id
        FROM role_permissions rp
        JOIN permissions existing ON existing.id = rp.permission_id AND existing.slug = 'online-tests.manage'
        JOIN permissions p ON p.slug = 'online-tests.results'");
};
