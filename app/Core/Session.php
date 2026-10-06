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
        session_cache_limiter('nocache');
        ini_set('session.gc_maxlifetime', (string) max(
            (int) $this->config['lifetime'],
            (int) ($this->config['remember_lifetime'] ?? 0),
        ));
        session_name((string) $this->config['name']);
        $this->useOwnSavePath();
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

    /**
     * Keeps session files in the app's own folder. Ubuntu's phpsessionclean timer empties the
     * php.ini session folder every 30 minutes of anything idle for 24 minutes (php.ini's
     * gc_maxlifetime), whatever lifetime the app sets — which signed students out in the middle
     * of a test and threw away their uploaded pages. Here PHP's own GC honours our lifetime.
     * A session still in the old folder is copied over once, so nobody is signed out by the move.
     */
    private function useOwnSavePath(): void
    {
        $path = (string) ($this->config['save_path'] ?? '');
        if ($path === '' || (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) || !is_writable($path)) {
            return;
        }
        $legacy = (string) session_save_path();
        $legacy = $legacy !== '' ? substr($legacy, (int) strrpos(';' . $legacy, ';')) : sys_get_temp_dir();
        session_save_path($path);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '1000');
        $id = (string) ($_COOKIE[session_name()] ?? '');
        if ($id !== '' && preg_match('/^[A-Za-z0-9,-]{22,256}$/', $id) && realpath($legacy) !== realpath($path)) {
            $old = rtrim($legacy, '/\\') . '/sess_' . $id;
            $new = $path . '/sess_' . $id;
            if (!is_file($new) && is_file($old) && is_readable($old) && @copy($old, $new)) {
                @chmod($new, 0600);
            }
        }
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

    public function remember(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        setcookie(session_name(), session_id(), [
            'expires' => time() + (int) ($this->config['remember_lifetime'] ?? 2592000),
            'path' => '/',
            'secure' => (bool) $this->config['secure'],
            'httponly' => (bool) $this->config['http_only'],
            'samesite' => (string) $this->config['same_site'],
        ]);
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
