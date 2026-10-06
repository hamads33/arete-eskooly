<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\{RoleRepository,SettingsRepository};
use App\Support\Flash;
use DomainException;

/**
 * Roles & Permissions management.
 *
 * eSkooly locks this behind its Desktop subscription; it is available here because RBAC is
 * what makes non-admin staff access possible at all.
 */
final class RolesController extends AdminPageController
{
    private function repo(): RoleRepository
    {
        return new RoleRepository($this->app->database());
    }

    private function instituteId(): int
    {
        return (int) (new SettingsRepository($this->app->database()))->institute()['id'];
    }

    private function denied(): ?Response
    {
        return can('roles.view') ? null : Response::html('Forbidden', 403);
    }

    public function index(Request $request): Response
    {
        if ($x = $this->denied()) {
            return $x;
        }
        return $this->view('roles/index', [
            'pageTitle' => 'Roles & Permissions',
            'pageDescription' => 'Control what each type of user can see and change.',
            'activeMenu' => 'roles',
            'activeSubmenu' => 'roles',
            'breadcrumb' => ['Dashboard', 'Roles & Permissions'],
            'roles' => $this->repo()->all(),
            'institute' => (new SettingsRepository($this->app->database()))->institute(),
        ]);
    }

    public function edit(Request $request): Response
    {
        if ($x = $this->denied()) {
            return $x;
        }
        $role = $this->repo()->find((int) $request->route('id'));
        if ($role === null) {
            return Response::html('Role not found', 404);
        }
        $institute = $this->instituteId();
        return $this->view('roles/edit', [
            'pageTitle' => $role['name'] . ' — Permissions',
            'pageDescription' => 'Grant or revoke what this role can do.',
            'activeMenu' => 'roles',
            'activeSubmenu' => 'roles',
            'breadcrumb' => ['Dashboard', 'Roles & Permissions', $role['name']],
            'styles' => ['css/roles.css?v=1'],
            'scripts' => ['js/role-permissions.js?v=1'],
            'role' => $role,
            'groups' => $this->repo()->permissionGroups(),
            'granted' => $this->repo()->permissionIds((int) $role['id']),
            'wildcard' => $this->repo()->hasWildcard((int) $role['id']),
            'members' => $this->repo()->members((int) $role['id'], $institute),
            'assignable' => $this->repo()->assignableUsers((int) $role['id'], $institute),
            'institute' => (new SettingsRepository($this->app->database()))->institute(),
        ]);
    }

    public function store(Request $request): Response
    {
        try {
            $this->repo()->create((string) $request->input('name'), (string) $request->input('description', ''));
            Flash::add('success', 'Role created.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/roles'));
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->route('id');
        try {
            $this->repo()->update($id, (string) $request->input('name'), (string) $request->input('description', ''));
            Flash::add('success', 'Role updated.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/roles/' . $id . '/edit'));
    }

    public function savePermissions(Request $request): Response
    {
        $id = (int) $request->route('id');
        try {
            $count = $this->repo()->syncPermissions($id, (array) $request->input('permissions', []));
            Flash::add('success', $count . ' permission(s) granted to this role.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/roles/' . $id . '/edit'));
    }

    public function delete(Request $request): Response
    {
        try {
            $this->repo()->delete((int) $request->route('id'));
            Flash::add('success', 'Role deleted.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/roles'));
    }

    public function assign(Request $request): Response
    {
        $id = (int) $request->route('id');
        try {
            $this->repo()->assign($id, (int) $request->input('user_id'), $this->instituteId());
            Flash::add('success', 'Role assigned.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/roles/' . $id . '/edit'));
    }

    public function unassign(Request $request): Response
    {
        $id = (int) $request->route('id');
        try {
            $this->repo()->unassign($id, (int) $request->input('user_id'), $this->instituteId());
            Flash::add('success', 'Role removed from user.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/roles/' . $id . '/edit'));
    }
}
