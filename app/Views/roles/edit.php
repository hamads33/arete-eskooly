<?php
$sys = (int)$role['is_system'] === 1;
$isAdmin = $role['slug'] === 'admin';

// Modules whose figures are commercially sensitive. Flagged in the editor so an administrator
// can see at a glance that a grant exposes money or staff pay, rather than discovering it later.
$sensitive = ['sms' => 1, 'accounts' => 1, 'fees' => 1, 'salary' => 1, 'reports' => 1, 'roles' => 1, 'settings' => 1, 'employees' => 1];

$icons = [
    'dashboard' => 'layout-dashboard', 'settings' => 'settings-2', 'students' => 'users-round',
    'employees' => 'briefcase-business', 'classes' => 'school', 'subjects' => 'book-open',
    'accounts' => 'landmark', 'fees' => 'badge-dollar-sign', 'salary' => 'wallet-cards',
    'attendance' => 'clipboard-check', 'homework' => 'notebook-pen', 'exams' => 'file-check-2',
    'class-tests' => 'clipboard-list', 'behaviour' => 'smile', 'reports' => 'chart-no-axes-combined',
    'certificates' => 'award', 'communication' => 'messages-square', 'roles' => 'shield-check',
    'notes' => 'sticky-note', 'whatsapp' => 'message-circle', 'live-classes' => 'video', 'recorded-lectures' => 'clapperboard',
    'google-calendar' => 'calendar-days', 'teacher-assignments' => 'user-cog', 'sms' => 'message-square-text',
];
// Headings that ucwords() would get wrong.
$labels = ['sms' => 'SMS Center', 'google-calendar' => 'Google Calendar'];

$grantedTotal = 0;
$permissionTotal = 0;
foreach ($groups as $perms) {
    $permissionTotal += count($perms);
    foreach ($perms as $p) {
        if (in_array((int)$p['id'], $granted, true)) {
            $grantedTotal++;
        }
    }
}
?>
<main class="main roles-page roles-edit-page"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/roles'))?>">Roles &amp; Permissions</a><i data-lucide="chevron-right"></i><span><?=e($role['name'])?></span></nav><h1><?=e($role['name'])?></h1><p><?=$isAdmin?'The administrator role always has full access.':'Grant only what this role needs. Anything you leave off stays hidden from its menu, its pages and its dashboard figures.'?></p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Role Details</h2><p>Slug <code><?=e($role['slug'])?></code><?=$sys?' · built-in role':''?></p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/roles/'.(int)$role['id'].'/update'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="name">Role Name</label><input class="module-input" id="name" name="name" type="text" required maxlength="80" value="<?=e($role['name'])?>"<?=$sys?' readonly':''?>><?php if($sys):?><small>Built-in role names cannot be changed.</small><?php endif?></div>
<div class="module-field"><label for="description">Description</label><input class="module-input" id="description" name="description" type="text" maxlength="255" value="<?=e($role['description'])?>"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Details</button></div>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Permissions</h2><p><?=$isAdmin?'Full access is granted and cannot be edited.':'Tick what this role may do. View lets them open and read; Manage lets them change records.'?></p></div></header><div class="module-card__body">
<?php if($isAdmin || $wildcard): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="shield-check"></i></span><strong>Full Access</strong><p>This role holds the wildcard permission, so every module is available to it.</p></div></div>
<?php else: ?>
<form method="post" action="<?=e(url('/admin/roles/'.(int)$role['id'].'/permissions'))?>" id="permissionForm" data-permission-editor>
<?=csrf_field()?>

<div class="perm-toolbar">
  <div class="perm-search">
    <i data-lucide="search" aria-hidden="true"></i>
    <label class="sr-only" for="permSearch">Search permissions</label>
    <input id="permSearch" type="search" placeholder="Search modules or permissions..." autocomplete="off" data-perm-search>
  </div>
  <p class="perm-toolbar__count"><strong data-perm-count><?=$grantedTotal?></strong> of <?=$permissionTotal?> granted</p>
  <div class="perm-toolbar__actions">
    <button class="btn btn--ghost" type="button" data-perm-expand>Expand all</button>
    <button class="btn btn--ghost" type="button" data-perm-clear>Clear all</button>
  </div>
</div>

<p class="perm-empty" data-perm-noresults hidden>No permissions match that search.</p>

<div class="perm-grid">
<?php foreach($groups as $module => $perms):
    $moduleGranted = 0;
    foreach($perms as $p){ if(in_array((int)$p['id'],$granted,true)) $moduleGranted++; }
    $label = $labels[$module] ?? ucwords(str_replace('-',' ',$module));
    $open = $moduleGranted > 0;
?>
  <section class="perm-module<?=$open?' is-open':''?>" data-perm-module data-perm-terms="<?=e(strtolower($label.' '.$module))?>">
    <header class="perm-module__head">
      <button class="perm-module__toggle" type="button" data-perm-toggle aria-expanded="<?=$open?'true':'false'?>">
        <span class="perm-module__icon"><i data-lucide="<?=e($icons[$module] ?? 'square-dashed')?>"></i></span>
        <span class="perm-module__title">
          <strong><?=e($label)?></strong>
          <span class="perm-module__meta"><span data-perm-module-count><?=$moduleGranted?></span> of <?=count($perms)?> granted</span>
        </span>
        <?php if(isset($sensitive[$module])): ?><span class="perm-tag perm-tag--sensitive" title="Exposes financial, payroll or account-control data">Sensitive</span><?php endif; ?>
        <i class="perm-module__chevron" data-lucide="chevron-down" aria-hidden="true"></i>
      </button>
      <label class="perm-all">
        <input type="checkbox" data-perm-all<?=$moduleGranted===count($perms)?' checked':''?>>
        <span>All</span>
      </label>
    </header>
    <div class="perm-module__body">
<?php foreach($perms as $p):
    $slug = (string)$p['slug'];
    $parts = explode('.',$slug);
    $action = count($parts) > 1 ? end($parts) : 'access';
?>
      <label class="perm-row" data-perm-row data-perm-terms="<?=e(strtolower($p['name'].' '.$slug))?>">
        <input type="checkbox" name="permissions[]" value="<?=(int)$p['id']?>"<?=in_array((int)$p['id'],$granted,true)?' checked':''?> data-perm-check>
        <span class="perm-row__body">
          <span class="perm-row__name"><?=e($p['name'])?></span>
          <code class="perm-row__slug"><?=e($slug)?></code>
        </span>
        <span class="perm-chip perm-chip--<?=e($action)?>"><?=e(ucfirst(str_replace('_',' ',$action)))?></span>
      </label>
<?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>

<div class="perm-savebar">
  <p><i data-lucide="info" aria-hidden="true"></i>Changes apply the next time each user signs in.</p>
  <button class="btn btn--primary" type="submit">Save Permissions</button>
</div>
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
<?php foreach($members as $m):?><tr><td><?=e($m['name'])?></td><td><?=e($m['email'])?></td><td><div class="table-actions"><?php if(can('*') && (int)$m['id']!==(int)(user()['id']??0) && !$isAdmin):?><form method="post" action="<?=e(url('/admin/impersonate/'.(int)$m['id']))?>"><?=csrf_field()?><button class="btn btn--secondary" type="submit"><i data-lucide="eye"></i>View as user</button></form><?php endif?><form method="post" action="<?=e(url('/admin/roles/'.(int)$role['id'].'/unassign'))?>"><?=csrf_field()?><input type="hidden" name="user_id" value="<?=(int)$m['id']?>"><button class="text-button" type="submit">Remove</button></form></div></td></tr><?php endforeach?>
<?php if(!$members):?><tr><td colspan="3"><div class="compact-empty"><h3>No Users</h3><p>Nobody currently holds this role.</p></div></td></tr><?php endif?>
</tbody></table></div>
</div></section>
</div></div></main>
