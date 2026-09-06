<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name((string) $this->config['name']);
        session_set_cookie_params([
            'lifetime' => (int) $this->config['lifetime'],
            'path' => '/',
            'secure' => (bool) $this->config['secure'],
            'httponly' => (bool) $this->config['http_only'],
            'samesite' => (string) $this->config['same_site'],
        ]);
        session_start();
        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        unset($_SESSION['_flash_next']);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    public function flashed(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_now'][$key] ?? $default;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash_now'][$key] ?? $_SESSION[$key] ?? $default;
        unset($_SESSION['_flash_now'][$key], $_SESSION[$key]);
        return $value;
    }

    public function regenerate(): bool
    {
        return session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
