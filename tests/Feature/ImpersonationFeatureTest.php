<?php

declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\{RoleRepository,UserRepository};

return static function (App $app): array {
    $checks = [];
    $check = static function (string $name, bool $value) use (&$checks): void { $checks[$name] = $value; };
    $db = $app->database();
    $adminId = (int) $db->query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='admin' LIMIT 1")->fetchColumn();
    $admin = (new UserRepository($db))->findAuthContext($adminId);
    $targetId = (int) $db->query("SELECT u.id FROM users u JOIN institute_users iu ON iu.user_id=u.id WHERE iu.institute_id=:i AND u.id<>:a AND u.status='active' ORDER BY u.id LIMIT 1", ['i'=>$admin['institute_id'],'a'=>$adminId])->fetchColumn();
    if ($targetId <= 0) return ['impersonation fixture exists' => false];

    $app->session()->set('auth_user', $admin);
    $request = static fn (string $method, string $uri, array $body=[]): Request => new Request([], $body, [], ['REQUEST_METHOD'=>$method,'REQUEST_URI'=>$uri]);
    $rolesPage = $app->run($request('GET', '/admin/roles'));
    $check('administrator can access role management', $rolesPage->status() === 200);

    $suffix = bin2hex(random_bytes(4));
    $roles = new RoleRepository($db);
    $roleId = $roles->create('Impersonation QA ' . $suffix, 'Temporary regression role');
    $permissionId = (int) $db->query("SELECT id FROM permissions WHERE slug='students.view'")->fetchColumn();
    $roles->syncPermissions($roleId, [$permissionId]);
    $check('administrator can edit role permissions', $roles->permissionIds($roleId) === [$permissionId]);

    $start = $app->run($request('POST', '/admin/impersonate/' . $targetId, ['_token'=>CSRF::token()]));
    $check('administrator can impersonate institute account', $start->status() === 302 && (int)$app->session()->get('impersonator_user_id') === $adminId && (int)(user()['id']??0) === $targetId);
    $check('impersonation start is audited', (bool)$db->query("SELECT 1 FROM audit_logs WHERE action='impersonation.started' AND user_id=:a AND entity_id=:t", ['a'=>$adminId,'t'=>$targetId])->fetchColumn());

    $stop = $app->run($request('POST', '/impersonation/stop', ['_token'=>CSRF::token()]));
    $check('return to administrator restores original session', $stop->status() === 302 && !$app->session()->has('impersonator_user_id') && (int)(user()['id']??0) === $adminId);
    $check('impersonation stop is audited', (bool)$db->query("SELECT 1 FROM audit_logs WHERE action='impersonation.stopped' AND user_id=:a AND entity_id=:t", ['a'=>$adminId,'t'=>$targetId])->fetchColumn());

    $target = (new UserRepository($db))->findAuthContext($targetId);
    $app->session()->set('auth_user', $target);
    $denied = $app->run($request('POST', '/admin/impersonate/' . $adminId, ['_token'=>CSRF::token()]));
    $check('non-administrator cannot impersonate', $denied->status() === 403);

    $app->session()->set('auth_user', $admin);
    $db->query("DELETE FROM audit_logs WHERE action IN ('impersonation.started','impersonation.stopped') AND user_id=:a AND entity_id=:t", ['a'=>$adminId,'t'=>$targetId]);
    $roles->delete($roleId);
    return $checks;
};
