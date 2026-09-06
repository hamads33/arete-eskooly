<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
/** @var \App\Core\App $app */
$app = require BASE_PATH . '/bootstrap/app.php';
$pdo = $app->database()->connection();

$email = strtolower(trim((string) getenv('DEV_ADMIN_EMAIL')));
$password = (string) getenv('DEV_ADMIN_PASSWORD');
$name = trim((string) (getenv('DEV_ADMIN_NAME') ?: 'Local Administrator'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    throw new RuntimeException('Set a valid DEV_ADMIN_EMAIL and a DEV_ADMIN_PASSWORD of at least 12 characters.');
}

$pdo->beginTransaction();
try {
    $now = date('Y-m-d H:i:s');
    $role = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug');
    $role->execute(['slug' => 'admin']);
    $roleId = $role->fetchColumn();
    if (!$roleId) {
        $insertRole = $pdo->prepare('INSERT INTO roles (name, slug, created_at, updated_at) VALUES (:name, :slug, :created_at, :updated_at)');
        $insertRole->execute(['name' => 'Administrator', 'slug' => 'admin', 'created_at' => $now, 'updated_at' => $now]);
        $roleId = (int) $pdo->lastInsertId();
    }
    $permissionSlugs = ['dashboard.view', 'settings.view', 'students.view', 'employees.view', 'accounts.view', 'fees.view', 'salary.view', 'attendance.view', 'homework.view', 'exams.view', 'reports.view', 'communication.view'];
    foreach ($permissionSlugs as $slug) {
        $findPermission = $pdo->prepare('SELECT id FROM permissions WHERE slug = :slug');
        $findPermission->execute(['slug' => $slug]);
        $permissionId = $findPermission->fetchColumn();
        if (!$permissionId) {
            $insertPermission = $pdo->prepare('INSERT INTO permissions (name, slug, created_at, updated_at) VALUES (:name, :slug, :created_at, :updated_at)');
            $insertPermission->execute(['name' => ucwords(str_replace('.', ' ', $slug)), 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
            $permissionId = (int) $pdo->lastInsertId();
        }
        $linkPermission = $pdo->prepare($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)' : 'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
        $linkPermission->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
    }
    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $findUser->execute(['email' => $email]);
    $userId = $findUser->fetchColumn();
    if (!$userId) {
        $insertUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, 'active', :created_at, :updated_at)");
        $insertUser->execute(['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'created_at' => $now, 'updated_at' => $now]);
        $userId = (int) $pdo->lastInsertId();
    }
    $linkRole = $pdo->prepare($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)' : 'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
    $linkRole->execute(['user_id' => $userId, 'role_id' => $roleId]);
    $pdo->commit();
    echo "Development administrator is ready: {$email}" . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
