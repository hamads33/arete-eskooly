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
