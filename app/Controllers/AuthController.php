<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{App,Controller,Request,Response,Validator};
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\RememberMeService;
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
        $remember = $request->input('remember') === '1';
        if (!$validator->validate($data, ['email' => 'required|email', 'password' => 'required|string'])) {
            $this->app->session()->flash('_old', ['email' => $data['email'], 'remember' => $remember ? '1' : '']);
            $this->app->session()->flash('_errors', $validator->errors());
            return Response::redirect(url('/login'));
        }
        $result = $this->auth->attempt($data['email'], $data['password']);
        if ($result !== 'authenticated') {
            // 'disabled' is only returned after the password was verified, so naming it leaks nothing.
            $message = $result==='inactive_person'?'Your account is not active at the moment. Please contact the institute administration.':($result==='restricted'?'This account has no portal access. Contact your institute administrator.':($result==='disabled'?'This account has been blocked. Please contact the institute administration.':'Unable to sign in. Check your email and password.'));
            $this->app->session()->flash('_old', ['email' => $data['email'], 'remember' => $remember ? '1' : '']);
            $this->app->session()->flash('_errors', ['email' => [$message]]);
            return Response::redirect(url('/login'));
        }
        if ($remember) {
            (new RememberMeService($this->app->database(),$this->app->session(),(array)$this->app->config('session',[])))->issue((int)user()['id']);
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
        if (hasRole('admin')) {
            return '/admin/dashboard';
        }
        if (hasRole('student')) {
            return '/portal/student';
        }
        if (hasRole('parent')) {
            return '/portal/parent';
        }
        // Teachers work from their own, assignment-scoped portal rather than the admin area.
        if (hasRole('teacher') && !can('*')) {
            return '/portal/employee';
        }
        // Staff granted any admin module belong in the administration workspace, landing on the
        // first page they may actually open. Resolving this per-permission rather than demanding
        // dashboard.view is what lets a narrow grant stand on its own; admin_home() falls back to
        // the employee portal for staff holding no admin module at all.
        return check() ? admin_home() : '/login';
    }

    public function destroy(Request $request): Response
    {
        (new RememberMeService($this->app->database(),$this->app->session(),(array)$this->app->config('session',[])))->revoke();
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
