<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use Closure;

/**
 * Gates the /portal prefix to users holding a portal role. AuthMiddleware runs first and
 * guarantees an active session; this only decides whether the signed-in user belongs here.
 */
final class PortalMiddleware
{
    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (($locked = \App\Platform\Features::lockedFor($request->path())) !== null) {
            return \App\Platform\Features::lockedResponse($locked);
        }
        if ($this->hasPortalRole() || $this->hasPortalRecord()) {
            return $next($request);
        }
        return Response::html(View::render('errors/403', ['pageTitle' => 'Access Denied'], 'layouts/error'), 403);
    }

    private function hasPortalRole(): bool
    {
        return hasRole('student') || hasRole('employee') || hasRole('parent');
    }

    /**
     * Falls back to the linked record rather than the role slug alone.
     *
     * An administrator can grant a staff role such as "teacher" or "accountant" from Roles &
     * Permissions without also granting the generic "employee" role. Gating purely on slugs
     * locked those users out of their own portal even though they have a real employee record,
     * so ownership is what actually decides access here. The per-page controllers still resolve
     * the same record, so this widens entry without widening what anyone can see.
     */
    private function hasPortalRecord(): bool
    {
        $userId = (int) (user()['id'] ?? 0);
        if ($userId === 0) {
            return false;
        }
        foreach (['student_accounts', 'employee_accounts', 'guardian_accounts'] as $table) {
            try {
                if ($this->app->database()->query("SELECT 1 FROM {$table} WHERE user_id = :u LIMIT 1", ['u' => $userId])->fetchColumn()) {
                    return true;
                }
            } catch (\Throwable) {
                // A table missing in this deployment simply cannot grant access.
            }
        }
        return false;
    }
}
