<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Platform\PlatformConfig;
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
