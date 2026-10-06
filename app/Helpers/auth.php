<?php

declare(strict_types=1);

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        $user = app()->session()->get('auth_user');
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('auth')) {
    function auth(): ?array { return auth_user(); }
}

if (!function_exists('user')) {
    function user(): ?array { return auth_user(); }
}

if (!function_exists('check')) {
    function check(): bool { return auth_user() !== null; }
}

if (!function_exists('guest')) {
    function guest(): bool { return !check(); }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        $permissions = auth_user()['permissions'] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}

if (!function_exists('hasRole')) {
    function hasRole(string $role): bool
    {
        return in_array($role, auth_user()['roles'] ?? [], true);
    }
}

if (!function_exists('can_any')) {
    /** True when the user holds at least one of the given permissions. */
    function can_any(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (can($permission)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('route_permission')) {
    /**
     * The 'view' permission guarding an /admin route, derived exactly the way
     * AdminAreaMiddleware derives it, so menus and landing pages never offer a link
     * that the gate will then reject.
     */
    function route_permission(?string $route): ?string
    {
        if ($route === null || $route === '') {
            return null;
        }
        $segments = array_values(array_filter(explode('/', $route)));
        $map = app()->config('permissions.' . ($segments[1] ?? 'dashboard'));
        return is_array($map) ? ($map['view'] ?? null) : null;
    }
}

if (!function_exists('can_open')) {
    /** Whether the signed-in user may open an /admin route. Unmapped modules stay admin-only. */
    function can_open(?string $route): bool
    {
        if (can('*')) {
            return true;
        }
        // Mirrors AdminAreaMiddleware's dashboard exception, so the menu offers the shell to
        // everyone the gate will actually let in.
        if ($route !== null && str_starts_with($route, '/admin/dashboard')) {
            return can('dashboard.view') || has_admin_access();
        }
        $permission = route_permission($route);
        return $permission !== null && can($permission);
    }
}

if (!function_exists('first_admin_route')) {
    /**
     * The first menu entry the user may open, in menu order, or null if none.
     *
     * The dashboard is skipped deliberately: it is open to anyone who can reach any other module,
     * so counting it here would make has_admin_access() answer its own question.
     */
    function first_admin_route(): ?string
    {
        foreach ((array) app()->config('navigation', []) as $item) {
            foreach (array_merge([$item], $item['children'] ?? []) as $entry) {
                $route = $entry['route'] ?? null;
                if ($route === null || $route === '' || ($entry['key'] ?? '') === 'logout') {
                    continue;
                }
                if (str_starts_with($route, '/admin/dashboard')) {
                    continue;
                }
                if (can_open($route)) {
                    return $route;
                }
            }
        }
        return null;
    }
}

if (!function_exists('has_admin_access')) {
    /** Whether the user may open any part of the administration workspace at all. */
    function has_admin_access(): bool
    {
        return can('*') || first_admin_route() !== null;
    }
}

if (!function_exists('admin_home')) {
    /**
     * First admin page the signed-in user may actually open.
     *
     * Staff who hold one narrow module permission (live classes, say) must not be forced through
     * /admin/dashboard: requiring dashboard.view merely to get in is what used to make admins
     * hand out the financial overview alongside every narrow grant.
     */
    function admin_home(): string
    {
        if (guest()) {
            return '/login';
        }
        if (can('*') || can('dashboard.view')) {
            return '/admin/dashboard';
        }
        if (($route = first_admin_route()) !== null) {
            return $route;
        }
        if (hasRole('student')) {
            return '/portal/student';
        }
        if (hasRole('parent')) {
            return '/portal/parent';
        }
        return '/portal/employee';
    }
}
