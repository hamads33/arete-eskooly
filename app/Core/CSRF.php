<?php

declare(strict_types=1);

namespace App\Core;

final class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $session = app()->session();
        if (!$session->has(self::SESSION_KEY)) {
            $session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }
        return (string) $session->get(self::SESSION_KEY);
    }

    public static function validate(?string $token): bool
    {
        return is_string($token) && hash_equals(self::token(), $token);
    }
}
