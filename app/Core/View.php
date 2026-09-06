<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/admin'): string
    {
        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        $layoutFile = BASE_PATH . '/app/Views/' . $layout . '.php';
        if (!is_file($viewFile) || !is_file($layoutFile)) {
            throw new \RuntimeException("View or layout not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();
        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }
}
