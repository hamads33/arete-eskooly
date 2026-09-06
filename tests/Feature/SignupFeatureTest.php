<?php
declare(strict_types=1);
use App\Core\{App,Request,CSRF};
use App\Repositories\{UserRepository,SettingsRepository,StudentRepository,WorkflowRepository};
use App\Services\AuthService;
return static function(App $app):array{
    $checks=[];$db=$app->database();$pdo=$db->connection();$session=$app->session();
    $original=$session->get('auth_user');$session->forget('auth_user');
    $request=static fn(string $method,string $path,array $body=[])=>new Request([],$body,[],['REQUEST_METHOD'=>$method,'REQUEST_URI'=>$path]);
    $users=new UserRepository($db);$auth=new AuthService($users,$session);
    $checks['guest login has premium shell']=$app->run($request('GET','/login'))->status()===200;
    $response=$app->run($request('GET','/signup'));
    $checks['guest signup shows coming soon without registration form']=$response->status()===200&&str_contains($response->content(),"live soon")&&!str_contains($response->content(),'<form');
    $checks['signup rejects missing CSRF']=$app->run($request('POST','/signup'))->status()===419;
    $suffix=bin2hex(random_bytes(6));
    $data=['name'=>'Signup Test Owner','institute_name'=>'Signup Institute '.$suffix,'email'=>'signup-'.$suffix.'@example.test','password'=>'Secure-Test-2026','password_confirmation'=>'Secure-Test-2026'];
    foreach(['name'=>'','institute_name'=>'','email'=>'bad-email','password'=>'short','password_confirmation'=>'different'] as $key=>$value){
        $invalid=array_replace($data,[$key=>$value]);
        $checks['signup validates '.$key]=count($auth->register($invalid))>0;
    }
    $before=session_id();$createdUser=0;$createdInstitute=0;
    try{
        $response=$app->run($request('POST','/signup',$data+['_token'=>CSRF::token(),'role'=>'super-admin']));
        $checks['direct public signup POST is blocked']=$response->status()===403&&str_contains($response->content(),'live soon');
        $checks['blocked registration creates no user or institute']=$users->findByEmail($data['email'])===null&&!$db->query('SELECT id FROM institutes WHERE name=:name',['name'=>$data['institute_name']])->fetchColumn();
        $checks['blocked registration does not authenticate']=$session->get('auth_user')===null;
        // Retain transaction and isolation coverage for the internal registration service.
        $errors=$auth->register($data);
        $auth->attempt($data['email'],$data['password']);
        $created=$users->findByEmail($data['email']);$createdUser=(int)($created['id']??0);
        $context=$users->findAuthContext($createdUser);$createdInstitute=(int)($context['institute_id']??0);
        $checks['internal registration service remains functional']=$errors===[]&&$session->get('auth_user')['id']===$createdUser;
        $checks['signup regenerates session']=$before!==session_id();
        $checks['signup persists password hash']=$created['password_hash']!==$data['password']&&password_verify($data['password'],$created['password_hash']);
        $checks['signup assigns only server-side admin']=$context['roles']===['admin'];
        $institute=(new SettingsRepository($db))->institute();
        $checks['signup institute relationship and preferences persist']=$createdInstitute>0&&$institute['name']===$data['institute_name']&&$institute['currency']==='PKR';
        $checks['signup cannot read existing students']=(new StudentRepository($db))->paginate($createdInstitute)=== [];
        $other=$db->query('SELECT id FROM students WHERE institute_id<>:i LIMIT 1',['i'=>$createdInstitute])->fetchColumn();
        $checks['signup cannot fetch other institute student by ID']=!$other||(new StudentRepository($db))->find((int)$other,$createdInstitute)===null;
        $checks['authenticated signup redirects']=$app->run($request('GET','/signup'))->status()===302;
        $checks['signup duplicate email validation']=isset($auth->register($data)['email']);
        $count=(int)$db->query('SELECT COUNT(*) FROM institutes')->fetchColumn();
        try{$users->registerInstitute('Duplicate','Rollback institute',$data['email'],$data['password']);$rolled=false;}catch(PDOException){$rolled=true;}
        $checks['signup duplicate race rolls back institute']=$rolled&&$count===(int)$db->query('SELECT COUNT(*) FROM institutes')->fetchColumn();
        $otherAdmin=$db->query('SELECT user_id FROM institute_users WHERE institute_id<>:i LIMIT 1',['i'=>$createdInstitute])->fetchColumn();
        try{(new WorkflowRepository($db))->createConversation($createdInstitute,$createdUser,(int)$otherAdmin,'Cross institute');$isolated=false;}catch(DomainException){$isolated=true;}
        $checks['signup cannot message another institute admin']=$isolated;
    }finally{
        $session->forget('auth_user');
        if($createdUser)$db->query('DELETE FROM users WHERE id=:id',['id'=>$createdUser]);
        if($createdInstitute)$db->query('DELETE FROM institutes WHERE id=:id',['id'=>$createdInstitute]);
        if($original)$session->set('auth_user',$original);
    }
    return $checks;
};
