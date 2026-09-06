<?php $user=user(); $name=$audience==='employee'?($record['name']??''):($record['student_name']??''); ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>Account Settings</span></nav><h1>Account Settings</h1><p>Change the email and password you sign in with.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Sign-in Details</h2><p>Signed in as <?=e($name)?>.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/portal/account'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field<?= error('email')?' has-error':'' ?>"><label for="email">Email</label><input class="module-input" id="email" name="email" type="email" required value="<?=e(old('email',$user['email']??''))?>"><?php if($m=error('email')):?><span class="field-error"><?=e($m)?></span><?php endif?></div>
<div class="module-field<?= error('current_password')?' has-error':'' ?>"><label for="current_password">Current Password</label><input class="module-input" id="current_password" name="current_password" type="password" required autocomplete="current-password"><?php if($m=error('current_password')):?><span class="field-error"><?=e($m)?></span><?php endif?></div>
<div class="module-field<?= error('password')?' has-error':'' ?>"><label for="password">New Password</label><input class="module-input" id="password" name="password" type="password" autocomplete="new-password" minlength="8"><small>Leave blank to keep your current password.</small><?php if($m=error('password')):?><span class="field-error"><?=e($m)?></span><?php endif?></div>
<div class="module-field<?= error('password_confirmation')?' has-error':'' ?>"><label for="password_confirmation">Confirm New Password</label><input class="module-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"><?php if($m=error('password_confirmation')):?><span class="field-error"><?=e($m)?></span><?php endif?></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Changes</button></div>
</form>
</div></section>
</div></div></main>
