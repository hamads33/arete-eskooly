<div class="auth-heading"><span class="eyebrow">YOUR INSTITUTE, AT YOUR FINGERTIPS</span><h1>Welcome back<span>.</span></h1><p>A new day of possibilities starts here.<br>Sign in to your school workspace.</p></div>
<form class="auth-form" method="post" action="<?= url('/login') ?>">
<?= csrf_field() ?>
<?php $fields=[['email','Email address','email','email','you@school.edu'],['password','Password','password','current-password','Enter your password']]; require BASE_PATH.'/app/Views/auth/fields.php'; ?>
<button class="auth-submit" type="submit"><span>Sign In</span><span aria-hidden="true">↗</span></button>
</form>
<p class="auth-switch">New to Arete? <a href="<?= url('/signup') ?>">Create an institute account <span aria-hidden="true">→</span></a></p>
<div class="auth-reassurance"><svg width="19" height="21" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6L12 3Z" stroke="currentColor" stroke-width="1.5"/><path d="m8 12 3 3 5-6" stroke="currentColor" stroke-width="1.5"/></svg><span>Your workspace. Your people. Securely connected.</span></div>
