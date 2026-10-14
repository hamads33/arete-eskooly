<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Platform\Features;
use App\Platform\PlatformConfig;
use App\Platform\SchoolLogo;
use App\Platform\SchoolProvisioner;
use App\Platform\SchoolRegistry;
use App\Repositories\SettingsRepository;
use App\Support\Flash;

/**
 * School sign-up approvals inside the main portal's admin area (/admin/schools). The routes only
 * exist on the main portal (PlatformConfig::enabled() is false on every school site), the /admin
 * middleware requires a signed-in user, and this controller further requires a full administrator.
 */
final class PlatformController extends Controller
{
    private function denied(): ?Response
    {
        return PlatformConfig::enabled() && hasRole('admin') ? null : Response::html('Forbidden', 403);
    }

    public function index(Request $request): Response
    {
        if ($x = $this->denied()) return $x;
        return $this->view('platform/admin', [
            'pageTitle' => 'School Sign-ups',
            'activeMenu' => 'schools',
            'activeSubmenu' => 'schools',
            'institute' => (new SettingsRepository($this->app->database()))->institute(),
            'schools' => (new SchoolRegistry())->all(),
            'schoolUrl' => [PlatformConfig::class, 'schoolUrl'],
            'filter' => (string) $request->query('status', ''),
            'styles' => ['css/schools.css?v=6'],
        ]);
    }

    /** The logo a school uploaded with its application, for the approvals page (admin only). */
    public function logo(Request $request): Response
    {
        if ($x = $this->denied()) return $x;
        $school = (new SchoolRegistry())->find((int) $request->route('id'));
        $path = $school ? SchoolLogo::pending((string) $school['slug']) : null;
        if ($path === null) return Response::html('Not found', 404);
        return new Response((string) file_get_contents($path), 200, ['Content-Type' => str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Form for the Arete admin to set up a school directly, with its features and limits. */
    public function create(Request $request): Response
    {
        if ($x = $this->denied()) return $x;
        return $this->view('platform/create', [
            'pageTitle' => 'Create a School',
            'activeMenu' => 'schools',
            'activeSubmenu' => 'schools',
            'modules' => Features::MODULES,
        ]);
    }

    /**
     * Creates and immediately provisions a school from the admin form, then applies the chosen
     * features and limits. Uses the same checks as the public sign-up.
     */
    public function store(Request $request): Response
    {
        if ($x = $this->denied()) return $x;
        $data = [
            'school_name' => trim((string) $request->input('school_name')),
            'slug' => strtolower(trim((string) $request->input('slug'))),
            'city' => trim((string) $request->input('city')),
            'admin_name' => trim((string) $request->input('admin_name')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => trim((string) $request->input('phone')),
        ];
        $password = (string) $request->input('password');
        $validator = new \App\Core\Validator();
        $validator->validate($data, ['school_name' => 'required|max:160', 'slug' => 'required|max:30', 'city' => 'required|max:120', 'admin_name' => 'required|max:120', 'email' => 'required|email|max:190', 'phone' => 'required']);
        $errors = $validator->errors();
        if (!preg_match('/^\+?[0-9 ()-]{7,20}$/', $data['phone'])) $errors['phone'] = ['Enter a valid mobile number.'];
        if (!SchoolProvisioner::validSlug($data['slug'])) $errors['slug'] = ['Use 3–30 lowercase letters, numbers or single hyphens, starting with a letter.'];
        if (strlen($password) < 8 || strlen($password) > 72) $errors['password'] = ['Use between 8 and 72 characters.'];
        $registry = new SchoolRegistry();
        if (!isset($errors['slug']) && ($registry->slugTaken($data['slug']) || is_dir(SchoolProvisioner::siteDir($data['slug'])))) $errors['slug'] = ['This address is already taken.'];
        $limits = [];
        foreach (array_keys(Features::LIMITS) as $key) {
            $raw = trim((string) $request->input('limit_' . $key, ''));
            if ($raw !== '' && (!ctype_digit($raw) || (int) $raw > 1000000)) $errors['limit_' . $key] = ['Enter a whole number, or leave empty for no limit.'];
            $limits[$key] = (int) $raw;
        }
        $logo = $request->file('school_logo');
        if (!$errors && $logo && ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && SchoolLogo::storePending($logo, $data['slug']) === false) {
            $errors['school_logo'] = ['The logo must be a PNG or JPG image no larger than 1 MB.'];
        }
        if ($errors) {
            $this->app->session()->flash('_errors', $errors);
            $this->app->session()->flash('_old', $data + ['enabled' => (array) $request->input('enabled', [])] + array_combine(array_map(static fn ($k) => 'limit_' . $k, array_keys($limits)), array_map(static fn ($v) => $v ?: '', $limits)));
            return Response::redirect(url('/admin/schools/new'));
        }
        $id = $registry->create([
            'slug' => $data['slug'], 'name' => $data['school_name'], 'city' => $data['city'], 'admin_name' => $data['admin_name'],
            'admin_email' => $data['email'], 'admin_phone' => $data['phone'], 'hash' => password_hash($password, PASSWORD_DEFAULT), 'ip' => $request->ip(),
        ]);
        @set_time_limit(300);
        $provisioner = new SchoolProvisioner($registry);
        [$ok, $message] = $provisioner->provision($id);
        if (!$ok) {
            Flash::add('error', $message . ' The school is listed under "Awaiting approval" with Retry setup; set its features after it is live.');
            return Response::redirect(url('/admin/schools?status=pending'));
        }
        $locked = array_values(array_diff(array_keys(Features::MODULES), array_map('strval', (array) $request->input('enabled', []))));
        try {
            $provisioner->setLimits($id, $limits);
            $provisioner->setLockedModules($id, $locked);
            @file_put_contents(BASE_PATH . '/storage/logs/platform-support-logins.log', date('c') . ' CREATED ' . $data['slug'] . ' locked=[' . implode(',', $locked) . '] by ' . (user()['email'] ?? '?') . PHP_EOL, FILE_APPEND | LOCK_EX);
            Flash::add('success', $message . ($locked ? ' ' . count($locked) . ' feature(s) locked.' : ' All features enabled.'));
        } catch (\DomainException $e) {
            Flash::add('error', $message . ' But its plan could not be saved: ' . $e->getMessage() . ' Open Features to set it.');
        }
        return Response::redirect(url('/admin/schools?status=active'));
    }

    /** The tick-list of modules a school may use (unticked = locked behind the paywall). */
    public function features(Request $request): Response
    {
        if ($x = $this->denied()) return $x;
        $registry = new SchoolRegistry();
        $school = $registry->find((int) $request->route('id'));
        if (!$school) return Response::html('School not found', 404);
        if (!in_array($school['status'], ['active', 'suspended'], true)) {
            Flash::add('error', 'Features can be set once a school is live.');
            return Response::redirect(url('/admin/schools'));
        }
        return $this->view('platform/features', [
            'pageTitle' => 'Features · ' . $school['name'],
            'activeMenu' => 'schools',
            'activeSubmenu' => 'schools',
            'school' => $school,
            'modules' => Features::MODULES,
            'locked' => (new SchoolProvisioner($registry))->lockedModules((string) $school['slug']),
            'limits' => (new SchoolProvisioner($registry))->limits((string) $school['slug']),
            'usage' => $this->usage($school),
        ]);
    }

    /** Current students / staff in a school, for showing next to its limits. */
    private function usage(array $school): array
    {
        $out = array_fill_keys(array_keys(Features::LIMITS), null);
        try {
            $pdo = SchoolRegistry::serverConnection((string) $school['db_name']);
            foreach (Features::LIMITS as $key => [, , $table, $where]) $out[$key] = (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        } catch (\Throwable) {
            // Shown as "—" when the school's database cannot be read.
        }
        return $out;
    }

    public function action(Request $request): Response
    {
        if ($x = $this->denied()) return $x;
        $registry = new SchoolRegistry();
        $id = (int) $request->route('id');
        $school = $registry->find($id);
        if (!$school) return Response::html('School not found', 404);
        $provisioner = new SchoolProvisioner($registry);
        switch ((string) $request->route('action')) {
            case 'approve':
                @set_time_limit(300);
                [$ok, $message] = $provisioner->provision($id);
                Flash::add($ok ? 'success' : 'error', $message);
                break;
            case 'reject':
                if ($school['status'] === 'pending' || $school['status'] === 'failed') {
                    $registry->update($id, ['status' => 'rejected', 'note' => mb_substr(trim((string) $request->input('note')), 0, 500)]);
                    \App\Platform\SchoolLogo::discard((string) $school['slug']);
                    Flash::add('success', $school['name'] . ' was rejected.');
                }
                break;
            case 'delete':
                if (strtolower(trim((string) $request->input('confirm'))) !== $school['slug']) {
                    Flash::add('error', 'Type the school address exactly to confirm deleting it.');
                    break;
                }
                [$ok, $message] = $provisioner->destroy($id);
                if ($ok) @file_put_contents(BASE_PATH . '/storage/logs/platform-support-logins.log', date('c') . ' DELETED ' . $school['slug'] . ' (' . $school['name'] . ', ' . $school['admin_email'] . ') by ' . (user()['email'] ?? '?') . PHP_EOL, FILE_APPEND | LOCK_EX);
                Flash::add($ok ? 'success' : 'error', $message);
                break;
            case 'login':
                return $this->supportLogin($school);
            case 'features':
                $enabled = array_map('strval', (array) $request->input('enabled', []));
                $locked = array_values(array_diff(array_keys(Features::MODULES), $enabled));
                try {
                    $limits = [];
                    foreach (array_keys(Features::LIMITS) as $key) {
                        $raw = trim((string) $request->input('limit_' . $key, ''));
                        if ($raw !== '' && !ctype_digit($raw)) throw new \DomainException('Limits must be whole numbers; leave a box empty for no limit.');
                        $limits[$key] = (int) $raw;
                    }
                    $provisioner->setLimits($id, $limits);
                    $provisioner->setLockedModules($id, $locked);
                    @file_put_contents(BASE_PATH . '/storage/logs/platform-support-logins.log', date('c') . ' FEATURES ' . $school['slug'] . ' locked=[' . implode(',', $locked) . '] by ' . (user()['email'] ?? '?') . PHP_EOL, FILE_APPEND | LOCK_EX);
                    Flash::add('success', 'Plan saved for ' . $school['name'] . ($locked ? ': ' . count($locked) . ' locked.' : ': everything is enabled.'));
                } catch (\DomainException $e) {
                    Flash::add('error', $e->getMessage());
                }
                return Response::redirect(url('/admin/schools/' . $id . '/features'));
            case 'revoke':
                $provisioner->suspend($id, true);
                Flash::add('success', 'Access revoked for ' . $school['name'] . '. Its data is kept and access can be restored at any time.');
                break;
            case 'restore':
                $provisioner->suspend($id, false);
                Flash::add('success', 'Access restored for ' . $school['name'] . '.');
                break;
            default:
                return Response::html('Unknown action', 404);
        }
        $back = (string) $request->input('back', '');
        return Response::redirect(url('/admin/schools' . (in_array($back, ['pending', 'active', 'suspended', 'rejected', 'all'], true) ? '?status=' . $back : '')));
    }

    /**
     * Password-free sign-in to a live school as its administrator. A single-use token, valid for
     * 60 seconds, is written to the school's own database; the browser carries it to the school
     * in a POST (never in the URL), and the school deletes it on first use.
     */
    private function supportLogin(array $school): Response
    {
        if ($school['status'] !== 'active' || !$school['db_name']) {
            Flash::add('error', 'Only live schools can be opened.');
            return Response::redirect(url('/admin/schools'));
        }
        try {
            $pdo = SchoolRegistry::serverConnection((string) $school['db_name']);
            $pdo->exec('CREATE TABLE IF NOT EXISTS platform_login_tokens (token_hash CHAR(64) PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, created_by VARCHAR(190) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $pdo->prepare('DELETE FROM platform_login_tokens WHERE expires_at < :now')->execute(['now' => date('Y-m-d H:i:s')]);
            $userId = (int) $pdo->query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='admin' AND u.status='active' ORDER BY u.id LIMIT 1")->fetchColumn();
            if ($userId === 0) throw new \RuntimeException('This school has no active administrator account.');
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO platform_login_tokens(token_hash,user_id,created_by,expires_at,created_at) VALUES(:h,:u,:by,:exp,:now)')->execute([
                'h' => hash('sha256', $token), 'u' => $userId, 'by' => (string) (user()['email'] ?? 'arete-admin'),
                'exp' => date('Y-m-d H:i:s', time() + 60), 'now' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Flash::add('error', 'Could not open ' . $school['name'] . ': ' . $e->getMessage());
            return Response::redirect(url('/admin/schools'));
        }
        // Audit trail of who opened which school, kept on the main portal.
        @file_put_contents(BASE_PATH . '/storage/logs/platform-support-logins.log', date('c') . ' ' . $school['slug'] . ' by ' . (user()['email'] ?? '?') . ' from ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . PHP_EOL, FILE_APPEND | LOCK_EX);
        return Response::html(View::render('platform/handoff', [
            'action' => PlatformConfig::schoolUrl((string) $school['slug']) . '/platform-login',
            'token' => $token,
            'school' => $school['name'],
        ], 'platform/_bare'));
    }

    /** Sign-ups needing action (waiting, or setup to retry) for the dashboard notice; 0 when the platform is off or unreachable. */
    public static function pendingCount(): int
    {
        if (!PlatformConfig::enabled() || !hasRole('admin')) return 0;
        try {
            $registry = new SchoolRegistry();
            return count($registry->all('pending')) + count($registry->all('failed'));
        } catch (\Throwable) {
            return 0;
        }
    }
}
