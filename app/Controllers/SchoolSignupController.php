<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Platform\PlatformConfig;
use App\Platform\SchoolProvisioner;
use App\Platform\SchoolRegistry;

/** Public "Register your school" form on the main portal. Requests wait for platform approval. */
final class SchoolSignupController extends Controller
{
    public function create(Request $request): Response
    {
        return $this->view('platform/register', ['pageTitle' => 'Register your school', 'domain' => PlatformConfig::domain(), 'submitted' => (string) $this->app->session()->flashed('school_submitted', '')], 'layouts/auth');
    }

    public function store(Request $request): Response
    {
        $data = [
            'school_name' => trim((string) $request->input('school_name')),
            'slug' => strtolower(trim((string) $request->input('slug'))),
            'city' => trim((string) $request->input('city')),
            'admin_name' => trim((string) $request->input('admin_name')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => trim((string) $request->input('phone')),
        ];
        $password = (string) $request->input('password');
        $validator = new Validator();
        $validator->validate($data, ['school_name' => 'required|max:160', 'slug' => 'required|max:30', 'city' => 'required|max:120', 'admin_name' => 'required|max:120', 'email' => 'required|email|max:190', 'phone' => 'required']);
        $errors = $validator->errors();
        if (!preg_match('/^\+?[0-9 ()-]{7,20}$/', $data['phone'])) $errors['phone'] = ['Enter a valid mobile number.'];
        if (!SchoolProvisioner::validSlug($data['slug'])) $errors['slug'] = ['Use 3–30 lowercase letters, numbers or single hyphens, starting with a letter.'];
        if (strlen($password) < 8 || strlen($password) > 72) $errors['password'] = ['Use between 8 and 72 characters.'];
        if ($password !== (string) $request->input('password_confirmation')) $errors['password_confirmation'] = ['Passwords do not match.'];
        // Spam trap: a hidden field with a name browsers do not autofill. Never fail silently: a
        // person who somehow trips it sees a message and keeps what they typed.
        if ((string) $request->input('zx_hp_field') !== '') $errors['school_name'] = ['We could not submit this form. Please try again.'];
        $registry = new SchoolRegistry();
        // Applying twice: send them to the status of the application they already have.
        if (!$errors && ($existing = $registry->openByEmail($data['email']))) {
            $this->app->session()->flash('status_notice', 'You have already applied with this email. Here is the status of that application.');
            return Response::redirect(url('/school-status?school=' . rawurlencode((string) $existing['slug'])));
        }
        if (!isset($errors['slug']) && ($registry->slugTaken($data['slug']) || is_dir(SchoolProvisioner::siteDir($data['slug'])))) $errors['slug'] = ['This address is already taken. Try another.'];
        if (!$errors && $registry->pendingFromIp($request->ip()) >= 3) $errors['school_name'] = ['Too many requests from this connection. Please wait for the earlier ones to be reviewed.'];
        if ($errors) {
            $this->app->session()->flash('_errors', $errors);
            $this->app->session()->flash('_old', $data);
            return Response::redirect(url('/register-school'));
        }
        $registry->create([
            'slug' => $data['slug'], 'name' => $data['school_name'], 'city' => $data['city'], 'admin_name' => $data['admin_name'],
            'admin_email' => $data['email'], 'admin_phone' => $data['phone'], 'hash' => password_hash($password, PASSWORD_DEFAULT), 'ip' => $request->ip(),
        ]);
        $this->app->session()->flash('school_submitted', $data['slug']);
        return Response::redirect(url('/register-school'));
    }

    /** Live "is this address free?" check for the registration form. */
    public function check(Request $request): Response
    {
        $slug = strtolower(trim((string) $request->query('slug', '')));
        if (!SchoolProvisioner::validSlug($slug)) {
            $reserved = in_array($slug, \App\Platform\PlatformConfig::RESERVED, true);
            return Response::json(['ok' => false, 'message' => $reserved ? 'This address is reserved. Try another.' : 'Use 3–30 lowercase letters, numbers or single hyphens, starting with a letter.']);
        }
        $taken = (new SchoolRegistry())->slugTaken($slug) || is_dir(SchoolProvisioner::siteDir($slug));
        return Response::json(['ok' => !$taken, 'message' => $taken ? 'Sorry, ' . $slug . ' is already taken.' : '✓ ' . $slug . '.' . PlatformConfig::domain() . ' is available.']);
    }

    /**
     * Public application status for one subdomain. School subdomains that are not live yet (or whose
     * access was revoked) are sent here by nginx instead of showing a bare error.
     */
    public function status(Request $request): Response
    {
        $slug = strtolower(trim((string) $request->query('school', '')));
        $school = preg_match('/^[a-z0-9-]{1,40}$/', $slug) ? (new SchoolRegistry())->findBySlug($slug) : null;
        return $this->view('platform/status', [
            'pageTitle' => 'Application status',
            'slug' => $slug,
            'school' => $school,
            'address' => $slug !== '' ? PlatformConfig::schoolUrl($slug) : '',
            'notice' => (string) $this->app->session()->flashed('status_notice', ''),
        ], 'layouts/auth');
    }
}
