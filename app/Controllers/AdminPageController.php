<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Repositories\SettingsRepository;

abstract class AdminPageController extends Controller
{
    protected function renderPage(string $key, array $extra = []): Response
    {
        $page = $this->app->config('pages.' . $key);
        if (!is_array($page)) {
            throw new \RuntimeException("Page configuration not found: {$key}");
        }
        // TEMPORARY DEMO DATA. Replace with repositories/services during backend integration.
        $demo = $this->app->config('pages._demo', []);
        $institute = $extra['institute'] ?? (new SettingsRepository($this->app->database()))->institute();
        return $this->view((string) $page['view'], array_replace($page, ['demo' => $demo, 'institute' => $institute], $extra));
    }
}
