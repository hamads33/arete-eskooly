<?php $sys=(int)$role['is_system']===1; $isAdmin=$role['slug']==='admin'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/roles'))?>">Roles &amp; Permissions</a><i data-lucide="chevron-right"></i><span><?=e($role['name'])?></span></nav><h1><?=e($role['name'])?></h1><p><?=$isAdmin?'The administrator role always has full access.':'Grant or revoke what this role can do.'?></p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Role Details</h2><p>Slug <code><?=e($role['slug'])?></code><?=$sys?' · built-in role':''?></p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/roles/'.(int)$role['id'].'/update'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="name">Role Name</label><input class="module-input" id="name" name="name" type="text" required maxlength="80" value="<?=e($role['name'])?>"<?=$sys?' readonly':''?>><?php if($sys):?><small>Built-in role names cannot be changed.</small><?php endif?></div>
<div class="module-field"><label for="description">Description</label><input class="module-input" id="description" name="description" type="text" maxlength="255" value="<?=e($role['description'])?>"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Details</button></div>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Permissions</h2><p><?=$isAdmin?'Full access is granted and cannot be edited.':'Tick what this role may do.'?></p></div></header><div class="module-card__body">
<?php if($isAdmin || $wildcard): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="shield-check"></i></span><strong>Full Access</strong><p>This role holds the wildcard permission, so every module is available to it.</p></div></div>
<?php else: ?>
<form method="post" action="<?=e(url('/admin/roles/'.(int)$role['id'].'/permissions'))?>">
<?=csrf_field()?>
<?php foreach($groups as $module => $perms): ?>
<fieldset class="module-fieldset"><legend><?=e(ucwords(str_replace('-',' ',$module)))?></legend>
<?php foreach($perms as $p): ?>
<label class="checkbox-row"><input type="checkbox" name="permissions[]" value="<?=(int)$p['id']?>"<?=in_array((int)$p['id'],$granted,true)?' checked':''?>> <span><?=e($p['name'])?> <code><?=e($p['slug'])?></code></span></label>
<?php endforeach; ?>
</fieldset>
<?php endforeach; ?>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Permissions</button></div>
</form>
<?php endif; ?>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Assigned Users</h2><p><?=count($members)?> user(s) hold this role.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/roles/'.(int)$role['id'].'/assign'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="user_id">Add User</label><select class="module-input" id="user_id" name="user_id" required><option value="">-- Select a user --</option><?php foreach($assignable as $u):?><option value="<?=(int)$u['id']?>"><?=e($u['name'])?> (<?=e($u['email'])?>)</option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Assign Role</button></div>
</form>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Actions</th></tr></thead><tbody>
<?php foreach($members as $m):?><tr><td><?=e($m['name'])?></td><td><?=e($m['email'])?></td><td><form method="post" action="<?=e(url('/admin/roles/'.(int)$role['id'].'/unassign'))?>"><?=csrf_field()?><input type="hidden" name="user_id" value="<?=(int)$m['id']?>"><button class="text-button" type="submit">Remove</button></form></td></tr><?php endforeach?>
<?php if(!$members):?><tr><td colspan="3"><div class="compact-empty"><h3>No Users</h3><p>Nobody currently holds this role.</p></div></td></tr><?php endif?>
</tbody></table></div>
</div></section>
</div></div></main>
