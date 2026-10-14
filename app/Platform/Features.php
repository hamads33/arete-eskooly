<?php

declare(strict_types=1);

namespace App\Platform;

use App\Core\Response;
use App\Core\View;

/**
 * Per-school feature locks (the paywall). The Arete admin ticks which modules a signed-up school
 * may use; the unticked ones are written to that school's own .env as SCHOOL_LOCKED_MODULES, so a
 * school admin can never unlock them. The main portal has no such line and is never locked.
 *
 * Every entry point consults this class: the /admin and /portal gates, the menus, the SMS queue
 * and the nightly fee/SMS jobs. Core school records (students, staff, classes, settings) are not
 * lockable.
 */
final class Features
{
    /**
     * key => [label, short description, path prefixes]. A path belongs to the module whose prefix
     * matches it longest, so /admin/reports/fee-collection is Fees while /admin/reports is Reports.
     */
    public const MODULES = [
        'fees' => ['Fees & Invoices', 'Fee vouchers, collection, defaulters, fee automation and fee settings', ['/admin/fees', '/admin/reports/fee-collection', '/admin/settings/fee-particulars', '/admin/settings/fee-structure', '/admin/settings/discount-types', '/admin/settings/fee-invoice-accounts', '/admin/settings/fee-voucher', '/portal/student/fees', '/portal/student/documents/fee-slip']],
        'accounts' => ['Accounts', 'Income, expenses and account statements', ['/admin/accounts', '/admin/reports/accounts']],
        'salary' => ['Salary', 'Salary sheets, payments and advances', ['/admin/salary', '/admin/reports/salary', '/portal/employee/salary', '/portal/employee/documents/salary-slip']],
        'attendance' => ['Attendance', 'Student and staff attendance', ['/admin/attendance', '/admin/reports/student-attendance', '/admin/reports/employee-attendance', '/admin/reports/attendance-shortage', '/portal/student/attendance', '/portal/employee/attendance', '/portal/employee/teaching/attendance']],
        'homework' => ['Homework & Notes', 'Homework and class notes', ['/admin/homework', '/admin/notes', '/portal/student/homework', '/portal/student/notes', '/portal/employee/homework', '/portal/employee/teaching/homework', '/portal/employee/teaching/notes']],
        'exams' => ['Exams', 'Exams, marks and result cards', ['/admin/exams', '/portal/student/documents/report-card']],
        'class-tests' => ['Class Tests', 'Class tests and test results', ['/admin/class-tests', '/admin/reports/test-history', '/portal/employee/teaching/tests']],
        'online-tests' => ['Online Tests', 'Online tests, submissions and the results center', ['/admin/online-tests', '/admin/results', '/portal/student/online-tests']],
        'live-classes' => ['Live Classes', 'Google Meet live classes', ['/admin/live-classes', '/portal/student/live-classes', '/portal/employee/teaching/live-classes']],
        'recorded-lectures' => ['Recorded Lectures', 'Uploaded video lectures', ['/admin/recorded-lectures', '/portal/student/recorded-lectures']],
        'sms' => ['SMS Center', 'SMS through the Android gateway, reminders and automations', ['/admin/sms', '/admin/communication/sms-gateway']],
        'whatsapp' => ['WhatsApp', 'WhatsApp messages', ['/admin/whatsapp']],
        'behaviour' => ['Behaviour & Skills', 'Behaviour and skill ratings', ['/admin/behaviour']],
        'reports' => ['Reports', 'Admission, attendance, progress and other reports', ['/admin/reports']],
    ];

    /** @var list<string>|null */
    private static ?array $locked = null;

    /** Modules locked for the school this request is serving (empty on the main portal). */
    public static function locked(): array
    {
        if (self::$locked !== null) {
            return self::$locked;
        }
        $raw = school_slug() === '' ? '' : (string) getenv('SCHOOL_LOCKED_MODULES');
        $keys = array_values(array_intersect(array_map('trim', explode(',', $raw)), array_keys(self::MODULES)));
        return self::$locked = $keys;
    }

    public static function isLocked(string $module): bool
    {
        return in_array($module, self::locked(), true);
    }

    /** The lockable module a path belongs to, or null for core pages. */
    public static function moduleFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $path = '/' . trim((string) parse_url($path, PHP_URL_PATH), '/');
        $best = null;
        $bestLength = 0;
        foreach (self::MODULES as $key => [, , $prefixes]) {
            foreach ($prefixes as $prefix) {
                if (($path === $prefix || str_starts_with($path, $prefix . '/')) && strlen($prefix) > $bestLength) {
                    $best = $key;
                    $bestLength = strlen($prefix);
                }
            }
        }
        return $best;
    }

    /** Menu pages that can never be locked: signing out and the user's own account. */
    private const ALWAYS_OPEN = ['/admin/dashboard', '/admin/settings/account', '/admin/schools'];

    /** @var list<string>|null */
    private static ?array $lockedPages = null;
    /** @var list<array{route:string,label:string,group:string}>|null */
    private static ?array $pages = null;

    /**
     * Every admin menu page that can be locked on its own, from config/navigation.php, as
     * [route, label, group]. Groups follow the sidebar sections.
     */
    public static function pages(): array
    {
        if (self::$pages !== null) return self::$pages;
        $out = [];
        foreach ((array) app()->config('navigation', []) as $item) {
            $entries = ($item['children'] ?? []) !== [] ? $item['children'] : [$item];
            foreach ($entries as $entry) {
                $route = (string) ($entry['route'] ?? '');
                if ($route === '' || ($entry['key'] ?? '') === 'logout' || !str_starts_with($route, '/admin/') || in_array($route, self::ALWAYS_OPEN, true)) continue;
                $out[] = ['route' => $route, 'label' => (string) $entry['label'], 'group' => ($item['children'] ?? []) !== [] ? (string) $item['label'] : 'Other'];
            }
        }
        return self::$pages = $out;
    }

    /** Single pages locked for the school this request is serving. */
    public static function lockedPages(): array
    {
        if (self::$lockedPages !== null) return self::$lockedPages;
        $raw = school_slug() === '' ? '' : (string) getenv('SCHOOL_LOCKED_PAGES');
        $valid = array_column(self::pages(), 'route');
        return self::$lockedPages = array_values(array_intersect(array_map('trim', explode(',', $raw)), $valid));
    }

    /** The menu page a path belongs to: the page whose address is the longest prefix of it. */
    public static function pageFor(string $path): ?string
    {
        $path = '/' . trim((string) parse_url($path, PHP_URL_PATH), '/');
        $best = null;
        foreach (self::pages() as ['route' => $route]) {
            if (($path === $route || str_starts_with($path, $route . '/')) && ($best === null || strlen($route) > strlen($best))) $best = $route;
        }
        return $best;
    }

    /**
     * Why a request path is locked: a module key, "page:<route>" for a single locked page, or null
     * when it is open.
     */
    public static function lockedFor(string $path): ?string
    {
        if (self::locked() === [] && self::lockedPages() === []) {
            return null;
        }
        $module = self::moduleFor($path);
        if ($module !== null && self::isLocked($module)) return $module;
        if (self::lockedPages() !== [] && ($page = self::pageFor($path)) !== null && in_array($page, self::lockedPages(), true)) return 'page:' . $page;
        return null;
    }

    public static function label(string $module): string
    {
        if (str_starts_with($module, 'page:')) {
            foreach (self::pages() as $p) if ('page:' . $p['route'] === $module) return $p['label'];
            return 'This page';
        }
        return self::MODULES[$module][0] ?? 'This feature';
    }

    /** Countable limits a school's plan can set: key => [.env name, label, table, rows that count]. */
    public const LIMITS = [
        'students' => ['SCHOOL_MAX_STUDENTS', 'Students', 'students', 'archived_at IS NULL'],
        'staff' => ['SCHOOL_MAX_STAFF', 'Staff', 'employees', '1=1'],
    ];

    /** The school's cap for a limit, or null when unlimited (always null on the main portal). */
    public static function limit(string $key): ?int
    {
        if (school_slug() === '' || !isset(self::LIMITS[$key])) return null;
        $raw = trim((string) getenv(self::LIMITS[$key][0]));
        return ctype_digit($raw) && (int) $raw > 0 ? (int) $raw : null;
    }

    /**
     * Null when $adding more records fit the plan, otherwise the message to show. Archived students
     * do not count, so archiving makes room.
     */
    public static function roomFor(\App\Core\Database $db, string $key, int $adding = 1): ?string
    {
        $max = self::limit($key);
        if ($max === null) return null;
        [, $label, $table, $where] = self::LIMITS[$key];
        $current = (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        if ($current + $adding <= $max) return null;
        return "Your plan allows up to {$max} " . strtolower($label) . " and you have {$current}" . ($adding > 1 ? ", so {$adding} more cannot be added" : '') . '. Contact Arete Coaching Center to raise the limit.';
    }

    /** Throws when the plan has no room; the last line of defence inside the repositories. */
    public static function assertRoom(\App\Core\Database $db, string $key, int $adding = 1): void
    {
        if (($message = self::roomFor($db, $key, $adding)) !== null) throw new \DomainException($message);
    }

    /** The page shown instead of a locked module. */
    public static function lockedResponse(string $module): Response
    {
        return Response::html(View::render('errors/plan-locked', ['pageTitle' => self::label($module) . ' is not included', 'module' => self::label($module)], 'layouts/error'), 403);
    }

    /**
     * Admin menu: locked entries stay visible with a lock (so the school sees what it could add),
     * and open the "not in your plan" popup instead of the page.
     */
    public static function lockNavigation(array $navigation): array
    {
        if (self::locked() === [] && self::lockedPages() === []) {
            return $navigation;
        }
        foreach ($navigation as &$item) {
            $children = $item['children'] ?? [];
            foreach ($children as &$child) {
                if (self::lockedFor((string) ($child['route'] ?? '')) !== null) {
                    $child['locked'] = true;
                    $child['plan_locked'] = true;
                }
            }
            unset($child);
            $item['children'] = $children;
            $real = array_filter($children, static fn (array $c): bool => ($c['key'] ?? '') !== 'logout');
            $allLocked = $real !== [] && array_filter($real, static fn (array $c): bool => empty($c['plan_locked'])) === [];
            if ($allLocked || ($real === [] && self::lockedFor((string) ($item['route'] ?? '')) !== null)) {
                $item['locked'] = true;
                $item['plan_locked'] = true;
            }
        }
        unset($item);
        return $navigation;
    }

    /** Student / parent / staff portal menus: locked entries are simply left out. */
    public static function hideLocked(array $navigation): array
    {
        if (self::locked() === [] && self::lockedPages() === []) {
            return $navigation;
        }
        return array_values(array_filter($navigation, static fn (array $item): bool => self::lockedFor((string) ($item['route'] ?? '')) === null));
    }
}
