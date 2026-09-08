<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    protected function view(string $view, array $data = [], string $layout = 'layouts/admin'): Response
    {
        if (in_array($layout, ['layouts/admin', 'layouts/portal'], true)) {
            $baseStyles = [
                'css/variables.css', 'css/reset.css', 'css/base.css', 'css/layout.css',
                'css/components.css?v=6', 'css/account-settings.css?v=1',
                'css/settings.css?v=2', 'css/admin-pages.css?v=5', 'css/dashboard.css',
                'css/responsive.css',
                'css/print.css?v=2', 'css/reports.css?v=1',
            ];
            $baseScripts = [
                'vendor/lucide.min.js', 'js/dropdowns.js', 'js/sidebar.js?v=4', 'js/app.js?v=3',
                'js/class-subject-filter.js?v=1',
                'js/preview-lock.js?v=2',
            ];
            $data['styles'] = array_values(array_unique(array_merge($baseStyles, $data['styles'] ?? [])));
            $data['scripts'] = array_values(array_unique(array_merge($baseScripts, $data['scripts'] ?? [])));
        }

        $defaults = [
            'pageTitle' => 'Admin',
            'pageDescription' => '',
            'breadcrumb' => [],
            'activeMenu' => '',
            'activeSubmenu' => '',
            'styles' => [],
            'scripts' => [],
            'inlineScripts' => [],
            'navigation' => $this->navigationFor($this->app->config('navigation', [])),
        ];
        return Response::html(View::render($view, array_replace($defaults, $data), $layout));
    }

    /**
     * Hides menu entries the signed-in user cannot open, so the sidebar matches what
     * AdminAreaMiddleware will actually allow. Mirrors that middleware's rules: the required
     * permission comes from the route's module segment, and an unmapped module is admin-only.
     */
    private function navigationFor(array $navigation): array
    {
        if ($navigation === [] || can('*')) {
            return $navigation;
        }

        $allowed = function (?string $route): bool {
            if ($route === null || $route === '') {
                return true; // Parent entries and non-links are judged by their children.
            }
            $segments = array_values(array_filter(explode('/', $route)));
            $module = $segments[1] ?? 'dashboard';
            $map = $this->app->config('permissions.' . $module);
            return is_array($map) && isset($map['view']) && can((string) $map['view']);
        };

        $visible = [];
        foreach ($navigation as $item) {
            $children = [];
            foreach ($item['children'] ?? [] as $child) {
                if (($child['key'] ?? '') === 'logout' || $allowed($child['route'] ?? null)) {
                    $children[] = $child;
                }
            }
            $hadChildren = ($item['children'] ?? []) !== [];
            // A parent whose children are all hidden is itself hidden; only the logout entry
            // surviving is not reason enough to keep a section visible.
            $meaningful = array_filter($children, static fn (array $c): bool => ($c['key'] ?? '') !== 'logout');
            if ($hadChildren && $meaningful === []) {
                continue;
            }
            if (!$hadChildren && !$allowed($item['route'] ?? null)) {
                continue;
            }
            $item['children'] = $children;
            $visible[] = $item;
        }
        return $visible;
    }
}
