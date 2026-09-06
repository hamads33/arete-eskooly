<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\UserRepository;
use App\Support\Flash;

/**
 * Self-service account settings for portal users — the equivalent of eSkooly's
 * StudentPortal/account.php. A portal user may change their own sign-in email and password
 * and nothing else; their academic or employment record stays admin-owned.
 */
final class PortalAccountController extends PortalController
{
    public function edit(Request $request): Response
    {
        if (!$identity = $this->identity()) {
            return $this->forbidden();
        }
        return $this->portalView('portal/account', $identity['audience'], [
            'pageTitle' => 'Account Settings',
            'activeMenu' => 'portal-account',
            'instituteId' => $identity['instituteId'],
            'record' => $identity['record'],
        ]);
    }

    public function update(Request $request): Response
    {
        if (!$identity = $this->identity()) {
            return $this->forbidden();
        }
        $users = new UserRepository($this->app->database());
        $userId = $this->userId();
        $email = strtolower(trim((string) $request->input('email')));
        $current = (string) $request->input('current_password');
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = ['Enter a valid email address.'];
        }

        // Changing the current password is optional; changing the email is not.
        if ($password !== '' || $confirm !== '') {
            if (strlen($password) < 8 || strlen($password) > 72) {
                $errors['password'] = ['Use between 8 and 72 characters.'];
            } elseif ($password !== $confirm) {
                $errors['password_confirmation'] = ['Passwords do not match.'];
            }
        }

        // The current password always gates the change, so a hijacked session cannot lock the owner out.
        $existing = $this->app->database()->query('SELECT password_hash FROM users WHERE id=:id', ['id' => $userId])->fetchColumn();
        if (!is_string($existing) || !password_verify($current, $existing)) {
            $errors['current_password'] = ['Your current password is incorrect.'];
        }

        $taken = $this->app->database()->query('SELECT 1 FROM users WHERE email=:e AND id<>:id LIMIT 1', ['e' => $email, 'id' => $userId])->fetchColumn();
        if ($taken) {
            $errors['email'] = ['Another account already uses this email.'];
        }

        if ($errors !== []) {
            $this->app->session()->flash('_errors', $errors);
            $this->app->session()->flash('_old', ['email' => $email]);
            Flash::add('error', 'Please correct the highlighted fields.');
            return Response::redirect(url('/portal/account'));
        }

        $users->updateAccount($userId, $email, $password !== '' ? $password : null);

        // A stored managed-credential cipher would now be stale, so clear it: the admin's
        // "reveal password" view must not show a value the user has since changed.
        $table = $identity['audience'] === 'student' ? 'student_accounts' : 'employee_accounts';
        if ($password !== '') {
            $this->app->database()->query("UPDATE {$table} SET password_cipher=NULL,updated_at=:u WHERE user_id=:id", ['u' => date('Y-m-d H:i:s'), 'id' => $userId]);
        }

        Flash::add('success', $password !== '' ? 'Your email and password have been updated.' : 'Your email has been updated.');
        return Response::redirect(url('/portal/account'));
    }
}
