<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * A suspended account may sign in but cannot use anything: every page redirects to the
 * restriction notice and every action is refused. Students keep read-only access to their fee
 * details and fee slips so they can pay. Runs after AuthMiddleware on /admin, /portal and
 * /attachments.
 */
final class AccountRestrictionMiddleware
{
    private const STUDENT_FEE_PAGES = '#^/portal/student/(fees(/\d+)?|documents/fee-slip/\d+)$#';

    public function __construct(private readonly App $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth_user();
        if (($user['status'] ?? 'active') !== 'suspended') {
            return $next($request);
        }
        $get = strtoupper($request->method()) === 'GET';
        if ($get && preg_match(self::STUDENT_FEE_PAGES, $request->path()) && hasRole('student')) {
            return $next($request);
        }
        if ($get) {
            return Response::redirect(url('/account/restricted'));
        }
        return Response::html('Your account is suspended. This action is not available.', 423);
    }
}
