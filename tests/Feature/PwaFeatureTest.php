<?php
declare(strict_types=1);

use App\Core\App;

return static function (App $app): array {
    $root = BASE_PATH . '/public';
    $manifest = json_decode((string) file_get_contents($root . '/manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);
    $worker = (string) file_get_contents($root . '/sw.js');
    $pwaScript = (string) file_get_contents($root . '/assets/js/pwa.js');
    $installCss = (string) file_get_contents($root . '/assets/css/install.css');
    $layoutSources = implode("\n", array_map(static fn (string $file): string => (string) file_get_contents(BASE_PATH . '/app/Views/layouts/' . $file), ['admin.php', 'portal.php', 'auth.php', 'error.php']));
    $icons = [];
    foreach (['arete-icon-192-v2.png' => 192, 'arete-icon-512-v2.png' => 512, 'arete-maskable-512-v2.png' => 512] as $file => $size) {
        $image = getimagesize($root . '/assets/pwa/' . $file);
        $icons[$file] = is_array($image) && $image[0] === $size && $image[1] === $size && $image['mime'] === 'image/png';
    }

    return [
        'PWA manifest is valid JSON' => is_array($manifest),
        'PWA manifest uses one role-neutral root launch' => ($manifest['id'] ?? null) === '/' && ($manifest['start_url'] ?? null) === '/' && ($manifest['scope'] ?? null) === '/',
        'PWA manifest uses standalone maroon branding on white' => ($manifest['display'] ?? null) === 'standalone' && ($manifest['theme_color'] ?? null) === '#7A1632' && ($manifest['background_color'] ?? null) === '#FFFFFF',
        'PWA branded icon dimensions are correct' => !in_array(false, $icons, true),
        'PWA manifest declares maskable icon' => str_contains((string) file_get_contents($root . '/manifest.webmanifest'), '"purpose": "maskable"'),
        'public install route resolves without authentication' => ($install = $app->run(new \App\Core\Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/install']))) && $install->status() === 200 && str_contains($install->content(), 'data-pwa-install-page'),
        'install page provides native iOS installed and fallback states' => str_contains($install->content(), 'data-install-state="available"') && str_contains($install->content(), 'data-install-state="ios"') && str_contains($install->content(), 'data-install-state="installed"') && str_contains($install->content(), 'data-install-state="open-browser"'),
        'install page never presents a fake install action when native prompt is withheld' => !str_contains($install->content(), 'data-pwa-page-install-fallback') && str_contains($install->content(), 'data-pwa-recheck'),
        'install page is responsive and safe-area aware' => str_contains($installCss, '@media screen and (max-width:340px)') && str_contains($installCss, 'safe-area-inset-top') && str_contains($installCss, 'min-width:320px'),
        'service worker syntax has versioned caches' => str_contains($worker, "const VERSION = '") && str_contains($worker, 'arete-static-') && str_contains($worker, 'arete-runtime-'),
        'service worker ignores mutations' => str_contains($worker, "request.method !== 'GET'"),
        'service worker keeps navigation network-only' => str_contains($worker, "request.mode === 'navigate'") && str_contains($worker, "fetch(request).catch(() => caches.match('/offline.html'))"),
        'service worker runtime cache is assets-only' => str_contains($worker, "url.pathname.startsWith('/assets/')"),
        'service worker does not precache private routes' => !preg_match("~PRECACHE[\\s\\S]*?['\"]/(?:admin|portal|uploads|teacher|student|parent|login)~", $worker),
        'service worker removes obsolete caches' => str_contains($worker, "name.startsWith('arete-')") && str_contains($worker, 'caches.delete(name)'),
        'offline page is neutral and retryable' => str_contains((string) file_get_contents($root . '/offline.html'), "You're offline") && str_contains((string) file_get_contents($root . '/offline.html'), 'Try Again'),
        'all shared browser layouts include PWA metadata and UI' => substr_count($layoutSources, 'partials/pwa-head.php') === 4 && substr_count($layoutSources, 'partials/pwa-ui.php') === 4,
        'install flow offers native prompt browser fallback and Later' => str_contains($pwaScript, 'beforeinstallprompt') && str_contains($pwaScript, 'prompt.prompt()') && str_contains($pwaScript, 'browser menu') && str_contains($pwaScript, 'arete-install-later') && str_contains((string) file_get_contents(BASE_PATH . '/app/Views/partials/pwa-ui.php'), 'data-pwa-install-later'),
        'updates use the quiet browser lifecycle without intrusive prompts' => !str_contains($pwaScript, 'SKIP_WAITING') && !str_contains((string) file_get_contents(BASE_PATH . '/app/Views/partials/pwa-ui.php'), 'data-pwa-update'),
        'offline forms uploads and live actions are blocked without replay' => str_contains($pwaScript, "addEventListener('submit'") && str_contains($pwaScript, "event.target.type === 'file'") && str_contains($pwaScript, 'live-classes') && !str_contains($worker, "addEventListener('sync'"),
        'installed mobile PWA keeps student Meet handoff in the active context' => str_contains($pwaScript, 'standalone && mobile') && str_contains($pwaScript, "link.removeAttribute('target')") && str_contains($pwaScript, '/portal/student/live-classes/'),
    ];
};
