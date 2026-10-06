<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller,Request,Response};
use App\Services\AccountAccess;

/** The page a suspended account sees in place of everything else. */
final class AccountRestrictedController extends Controller
{
    public function show(Request $request): Response
    {
        $user = auth_user();
        if (($user['status'] ?? '') !== 'suspended') {
            return Response::redirect(url('/'));
        }
        $notice = (new AccountAccess($this->app->database()))->notice((int) $user['id']);
        $institute = $this->app->database()->query('SELECT i.name,i.phone FROM institute_users iu JOIN institutes i ON i.id=iu.institute_id WHERE iu.user_id=:u LIMIT 1', ['u' => (int) $user['id']])->fetch() ?: [];
        return $this->view('account/restricted', ['pageTitle' => 'Account Suspended', 'notice' => $notice, 'institute' => $institute], 'layouts/error');
    }
}
