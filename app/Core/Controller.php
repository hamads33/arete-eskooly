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
                'css/variables.css?v=2', 'css/reset.css', 'css/base.css', 'css/layout.css',
                'css/components.css?v=106', 'css/account-settings.css?v=1',
                'css/settings.css?v=102', 'css/admin-pages.css?v=109', 'css/dashboard.css',
                'css/responsive.css',
                'css/print.css?v=3', 'css/reports.css?v=1',
            ];
            $baseScripts = [
                'vendor/lucide.min.js', 'js/dropdowns.js', 'js/sidebar.js?v=5', 'js/app.js?v=4',
                'js/class-subject-filter.js?v=1',
                'js/preview-lock.js?v=3',
            ];
            $data['styles'] = array_values(array_unique(array_merge($baseStyles, $data['styles'] ?? [])));
            // One copy per script file: pages list "js/app.js" while the base lists "js/app.js?v=4",
            // and loading app.js twice ran every handler (delete confirmations, print) twice.
            $seen = [];
            $data['scripts'] = array_values(array_filter(array_merge($baseScripts, $data['scripts'] ?? []), static function (string $script) use (&$seen): bool {
                $file = strtok($script, '?');
                if (isset($seen[$file])) return false;
                return $seen[$file] = true;
            }));
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
        // Teachers see their portal sidebar on the admin teaching pages too, so the menu does
        // not change under them when they open Recorded Lectures, Online Tests, etc.
        if ($layout === 'layouts/admin' && !array_key_exists('navigation', $data) && \App\Controllers\PortalController::isTeacherShell()) {
            $defaults['navigation'] = \App\Controllers\PortalController::teacherNavigation($this->app);
        }
        $payload = array_replace($defaults, $data);
        // Modules outside this school's plan: shown locked in the admin menu, left out of portals.
        $payload['navigation'] = $layout === 'layouts/portal'
            ? \App\Platform\Features::hideLocked((array) $payload['navigation'])
            : \App\Platform\Features::lockNavigation((array) $payload['navigation']);
        return Response::html(View::render($view, $payload, $layout));
    }

    /**
     * Hides menu entries the signed-in user cannot open, so the sidebar matches what
     * AdminAreaMiddleware will actually allow. Mirrors that middleware's rules: the required
     * permission comes from the route's module segment, and an unmapped module is admin-only.
     */
    protected function navigationFor(array $navigation): array
    {
        if ($navigation === [] || can('*')) {
            return $navigation;
        }

        $allowed = static function (?string $route): bool {
            if ($route === null || $route === '') {
                return true; // Parent entries and non-links are judged by their children.
            }
            return can_open($route);
        };

        $visible = [];
        foreach ($navigation as $item) {
            $children = [];
            foreach ($item['children'] ?? [] as $child) {
                $isLocked = (bool) ($child['locked'] ?? false);
                if (($child['key'] ?? '') === 'logout' || (!$isLocked && $allowed($child['route'] ?? null)) || ($isLocked && can('*'))) {
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
            if (!$hadChildren) {
                // A locked leaf (no route, no children — "SMS Gateway" style upsell) isn't judged
                // by children because it has none; treating its null route as always-visible, like
                // a real parent, would leak these placeholders to every restricted user.
                if ((bool) ($item['locked'] ?? false) && !can('*')) {
                    continue;
                }
                if (!$allowed($item['route'] ?? null)) {
                    continue;
                }
            }
            $item['children'] = $children;
            $visible[] = $item;
        }
        return $visible;
    }
}
