<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App,Controller,Request,Response,Validator};
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\Flash;

final class AuthController extends Controller
{
    private readonly AuthService $auth;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->auth = new AuthService(new UserRepository($app->database()), $app->session());
    }

    public function create(Request $request): Response
    {
        return $this->view('auth/login', ['pageTitle' => 'Administrator Login'], 'layouts/auth');
    }

    public function store(Request $request): Response
    {
        $validator = new Validator();
        $data = ['email' => strtolower(trim((string) $request->input('email'))), 'password' => (string) $request->input('password')];
        if (!$validator->validate($data, ['email' => 'required|email', 'password' => 'required|string'])) {
            $this->app->session()->flash('_old', ['email' => $data['email']]);
            $this->app->session()->flash('_errors', $validator->errors());
            return Response::redirect(url('/login'));
        }
        $result = $this->auth->attempt($data['email'], $data['password']);
        if ($result !== 'authenticated') {
            $message = $result==='restricted'?'This account has no portal access. Contact your institute administrator.':'Unable to sign in. Check your email and password.';
            $this->app->session()->flash('_old', ['email' => $data['email']]);
            $this->app->session()->flash('_errors', ['email' => [$message]]);
            return Response::redirect(url('/login'));
        }
        Flash::add('success', 'Welcome back. You are now signed in.');
        return Response::redirect(url($this->landingRoute()));
    }

    /**
     * Where a freshly authenticated user lands. Admin wins when a user somehow holds more
     * than one role, so an administrator is never dropped into a self-service portal.
     */
    private function landingRoute(): string
    {
        // Staff granted dashboard/module permissions belong in the administration workspace.
        // Check effective permission before the generic employee role, otherwise every linked
        // employee is incorrectly redirected to self-service even after a staff role is granted.
        if (hasRole('admin') || can('dashboard.view')) {
            return '/admin/dashboard';
        }
        if (hasRole('student')) {
            return '/portal/student';
        }
        if (hasRole('employee')) {
            return '/portal/employee';
        }
        if (hasRole('parent')) {
            return '/portal/parent';
        }
        return '/login';
    }

    public function destroy(Request $request): Response
    {
        $this->auth->logout();
        Flash::add('success', 'You have been signed out safely.');
        return Response::redirect(url('/login'));
    }

    public function signup(Request $request):Response
    {
        return $this->view('auth/signup',['pageTitle'=>'Institute registration — Coming soon','isSignup'=>true],'layouts/auth');
    }

    public function register(Request $request):Response
    {
        // Public registration is closed until launch, including direct POST requests.
        $page=$this->signup($request);
        return Response::html($page->content(),403);
    }
}
