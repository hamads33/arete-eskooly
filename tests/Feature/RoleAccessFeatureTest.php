<?php

declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\{RoleRepository,SettingsRepository,UserRepository};

return static function (App $app): array {
    $checks = [];
    $check = static function (string $name, bool $result) use (&$checks): void {
        $checks[$name] = $result;
    };

    $db = $app->database();
    $session = $app->session();
    $instituteId = (int) (new SettingsRepository($db))->institute()['id'];
    $adminId = (int) $db->query(
        "SELECT u.id FROM users u
         JOIN user_roles ur ON ur.user_id=u.id
         JOIN roles r ON r.id=ur.role_id
         WHERE r.slug='admin' LIMIT 1"
    )->fetchColumn();
    $adminContext = (new UserRepository($db))->findAuthContext($adminId);
    $suffix = bin2hex(random_bytes(5));
    $now = date('Y-m-d H:i:s');
    $roleRepository = new RoleRepository($db);
    $roleId = 0;
    $userId = 0;

    $request = static fn (string $method, string $uri, array $body = []): Request => new Request(
        [],
        $body,
        [],
        ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]
    );

    try {
        $roleId = $roleRepository->create('Live Class Operator ' . $suffix, 'Narrow access regression fixture');
        $permissionId = (int) $db->query(
            "SELECT id FROM permissions WHERE slug='live-classes.view'"
        )->fetchColumn();
        $roleRepository->syncPermissions($roleId, [$permissionId]);

        $db->query(
            "INSERT INTO users(name,email,password_hash,status,created_at,updated_at)
             VALUES(:n,:e,:p,'active',:c,:u)",
            [
                'n' => 'Live Class Operator',
                'e' => 'role-access-' . $suffix . '@example.test',
                'p' => password_hash('Temporary-Password-2026!', PASSWORD_DEFAULT),
                'c' => $now,
                'u' => $now,
            ]
        );
        $userId = (int) $db->connection()->lastInsertId();
        $db->query(
            'INSERT INTO institute_users(institute_id,user_id,created_at) VALUES(:i,:u,:c)',
            ['i' => $instituteId, 'u' => $userId, 'c' => $now]
        );
        $roleRepository->assign($roleId, $userId, $instituteId);
        $session->set('auth_user', (new UserRepository($db))->findAuthContext($userId));

        $dashboard = $app->run($request('GET', '/admin/dashboard'));
        $content = $dashboard->content();
        $check('narrow staff can open the redacted dashboard shell', $dashboard->status() === 200);
        $check('narrow staff sees its allowed navigation item', str_contains($content, 'href="' . url('/admin/live-classes') . '"'));
        $check('locked SMS placeholder is hidden from restricted staff', !str_contains($content, 'data-tooltip="SMS"'));
        $check('locked Certificates placeholder is hidden from restricted staff', !str_contains($content, 'data-tooltip="Certificates"'));
        $check('unauthorized header links are hidden from restricted staff', !str_contains($content, 'aria-label="Open messaging"') && !str_contains($content, 'href="' . url('/admin/settings/account') . '"'));
        $check('restricted dashboard payload contains no financial figures', !str_contains($content, '"revenue":') || str_contains($content, '"revenue":0'));

        $students = $app->run($request('GET', '/admin/students'));
        $check('narrow staff cannot open an ungranted module', $students->status() === 403);
        $check('permission error returns to the first allowed module', str_contains($students->content(), url('/admin/live-classes')));

        $rebuild = $app->run($request('POST', '/admin/dashboard/rebuild', ['_token' => CSRF::token()]));
        $check('dashboard shell access does not grant dashboard writes', $rebuild->status() === 403);

        $login = $app->run($request('GET', '/login'));
        $check('signed-in narrow staff lands on its first allowed module', ($login->headers()['Location'] ?? '') === url('/admin/live-classes'));

        $session->set('auth_user', $adminContext);
        $adminDashboard = $app->run($request('GET', '/admin/dashboard'));
        // SMS was a locked placeholder until the SMS Center shipped; it is now a real module.
        $check('administrator sees the SMS Center in the sidebar', str_contains($adminDashboard->content(), 'data-tooltip="SMS Center"'));
        $check('certificates is removed from the admin sidebar', !str_contains($adminDashboard->content(), 'data-tooltip="Certificates"'));
    } finally {
        $session->set('auth_user', $adminContext);
        if ($userId > 0) {
            $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $userId]);
            $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $userId]);
            $db->query('DELETE FROM users WHERE id=:u', ['u' => $userId]);
        }
        if ($roleId > 0) {
            $db->query('DELETE FROM role_permissions WHERE role_id=:r', ['r' => $roleId]);
            $db->query('DELETE FROM role_meta WHERE role_id=:r', ['r' => $roleId]);
            $db->query('DELETE FROM roles WHERE id=:r', ['r' => $roleId]);
        }
    }

    return $checks;
};
