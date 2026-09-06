<?php

declare(strict_types=1);

namespace App\Support;

final class Flash
{
    public static function add(string $type, string $message): void
    {
        $allowed = ['success', 'error', 'warning', 'info'];
        app()->session()->flash('notification', [
            'type' => in_array($type, $allowed, true) ? $type : 'info',
            'message' => $message,
        ]);
    }

    public static function get(): ?array
    {
        $flash = app()->session()->flashed('notification');
        return is_array($flash) ? $flash : null;
    }
}
