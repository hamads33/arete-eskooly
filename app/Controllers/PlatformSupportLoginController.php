<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Support\Flash;

/**
 * School side of the Arete admin's password-free sign-in (/platform-login, school sites only).
 * The token was written to this school's database by the main portal moments ago; it is valid
 * once, for 60 seconds, and is deleted as it is used.
 */
final class PlatformSupportLoginController extends Controller
{
    public function login(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $db = $this->app->database();
        $row = null;
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            try {
                $hash = hash('sha256', $token);
                $row = $db->query('SELECT user_id,created_by FROM platform_login_tokens WHERE token_hash=:h AND expires_at>=:now', ['h' => $hash, 'now' => date('Y-m-d H:i:s')])->fetch() ?: null;
                // Delete first: only the request that actually removes the row may sign in.
                if ($row && $db->query('DELETE FROM platform_login_tokens WHERE token_hash=:h', ['h' => $hash])->rowCount() !== 1) $row = null;
            } catch (\PDOException) {
                $row = null; // No token table yet: nothing was ever issued for this school.
            }
        }
        $context = $row ? (new UserRepository($db))->findAuthContext((int) $row['user_id']) : null;
        if ($context === null) {
            Flash::add('error', 'That sign-in link has expired. Open the school again from the Arete admin portal.');
            return Response::redirect(url('/login'));
        }
        $session = $this->app->session();
        $session->regenerate();
        $session->set('auth_user', $context);
        $session->set('platform_support_by', (string) $row['created_by']);
        Flash::add('info', 'Signed in as the school administrator from the Arete admin portal.');
        return Response::redirect(url('/admin/dashboard'));
    }
}
