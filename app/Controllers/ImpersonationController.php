<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App,Controller,Request,Response};
use App\Repositories\{PakistanErpRepository,UserRepository};
use App\Support\Flash;

final class ImpersonationController extends Controller
{
    public function start(Request $request): Response
    {
        if (!can('*') || $this->app->session()->has('impersonator_user_id')) return Response::html('Forbidden', 403);
        $admin = user();
        $targetId = (int) $request->route('id');
        $target = (new UserRepository($this->app->database()))->findAuthContext($targetId);
        if (!$target || $target['status'] !== 'active' || $targetId === (int) $admin['id']
            || (int) $target['institute_id'] !== (int) $admin['institute_id'] || in_array('admin', (array) $target['roles'], true)) {
            return Response::html('Account not available for impersonation.', 404);
        }
        $this->audit((int) $admin['institute_id'], (int) $admin['id'], 'impersonation.started', $targetId);
        $this->app->session()->set('impersonator_user_id', (int) $admin['id']);
        $this->app->session()->set('auth_user', $target);
        $this->app->session()->regenerate();
        Flash::add('success', 'You are now viewing the application as ' . $target['name'] . '.');
        return Response::redirect(url($this->landingRoute($target)));
    }

    public function stop(Request $request): Response
    {
        $adminId = (int) $this->app->session()->get('impersonator_user_id', 0);
        if ($adminId <= 0) return Response::html('No active impersonation session.', 403);
        $current = user();
        $admin = (new UserRepository($this->app->database()))->findAuthContext($adminId);
        if (!$admin || $admin['status'] !== 'active' || !in_array('admin', (array) $admin['roles'], true)
            || (int) $admin['institute_id'] !== (int) ($current['institute_id'] ?? 0)) {
            $this->app->session()->forget('impersonator_user_id');
            $this->app->session()->forget('auth_user');
            $this->app->session()->regenerate();
            return Response::redirect(url('/login'));
        }
        $this->audit((int) $admin['institute_id'], $adminId, 'impersonation.stopped', (int) ($current['id'] ?? 0));
        $this->app->session()->forget('impersonator_user_id');
        $this->app->session()->set('auth_user', $admin);
        $this->app->session()->regenerate();
        Flash::add('success', 'Returned to your administrator account.');
        return Response::redirect(url('/admin/roles'));
    }

    private function landingRoute(array $target): string
    {
        if (in_array('dashboard.view', (array) $target['permissions'], true)) return '/admin/dashboard';
        if (in_array('student', (array) $target['roles'], true)) return '/portal/student';
        if (in_array('parent', (array) $target['roles'], true)) return '/portal/parent';
        return '/portal/employee';
    }

    private function audit(int $instituteId, int $adminId, string $action, int $targetId): void
    {
        (new PakistanErpRepository($this->app->database()))->audit($instituteId, $adminId, $action, 'user', $targetId, ['target_user_id' => $targetId]);
    }
}
