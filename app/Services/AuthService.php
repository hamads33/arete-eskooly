<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(private readonly UserRepository $users, private readonly Session $session) {}

    public function attempt(string $email, string $password): string
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) return 'invalid';
        // Re-apply the unpaid-fee rule first, so a student who has just paid gets straight back in.
        (new AccountAccess(app()->database()))->evaluateUser((int) $user['id']);
        $user = $this->users->findByEmail($email) ?? $user;
        // Suspended accounts may sign in; every page then shows the restriction notice instead.
        if (!in_array($user['status'], ['active', 'suspended'], true)) return 'disabled';
        $context = $this->users->findAuthContext((int) $user['id']);
        if ($context === null) return 'invalid';
        $roles=(array)$context['roles'];
        // An inactive / suspended student or employee may be blocked from signing in (General Settings → Inactive & Suspended Rules).
        if(!in_array('admin',$roles,true)&&!\App\Support\PersonStatus::loginAllowed(app()->database(),(int)$user['id']))return'inactive_person';
        if(!array_intersect(['admin','student','employee','parent'],$roles)&&!$this->users->hasLinkedIdentity((int)$user['id']))return'restricted';
        $this->users->updateLastLogin((int) $user['id']);
        $this->session->regenerate();
        $this->session->set('auth_user', $context);
        return 'authenticated';
    }

    public function logout(): void
    {
        $this->session->forget('auth_user');
        $this->session->forget('impersonator_user_id');
        $this->session->regenerate();
    }

    public function register(array $data): array
    {
        $validator=new \App\Core\Validator();
        $validator->validate($data,['name'=>'required|max:120','institute_name'=>'required|max:160','email'=>'required|email|max:190','password'=>'required']);
        $errors=$validator->errors();
        if(strlen($data['password'])<8||strlen($data['password'])>72)$errors['password']=['Use between 8 and 72 characters.'];
        if($data['password']!==$data['password_confirmation'])$errors['password_confirmation']=['Passwords do not match.'];
        if($this->users->findByEmail($data['email']))$errors['email']=['An account already uses this email. Please sign in.'];
        if($errors)return $errors;
        try{$this->users->registerInstitute($data['name'],$data['institute_name'],$data['email'],$data['password']);}
        catch(\PDOException $e){if($e->getCode()==='23000')return ['email'=>['Unable to create this account. Try another email.']];throw $e;}
        return [];
    }
}
