<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Roles &amp; Permissions</span></nav><h1>Roles &amp; Permissions</h1><p>Control what each type of user can see and change.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Create Role</h2><p>Add a staff role, then grant it permissions.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/roles'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="name">Role Name</label><input class="module-input" id="name" name="name" type="text" required maxlength="80" placeholder="e.g. Librarian"></div>
<div class="module-field"><label for="description">Description</label><input class="module-input" id="description" name="description" type="text" maxlength="255" placeholder="Optional"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Create Role</button></div>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>All Roles</h2><p><?=count($roles)?> role(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Role</th><th>Slug</th><th>Type</th><th>Permissions</th><th>Users</th><th>Actions</th></tr></thead><tbody>
<?php foreach($roles as $r): $sys=(int)$r['is_system']===1; ?>
<tr>
<td><?=e($r['name'])?><?php if($r['description']):?><br><small><?=e($r['description'])?></small><?php endif?></td>
<td><code><?=e($r['slug'])?></code></td>
<td><span class="status-badge"><?=$sys?'Built-in':'Custom'?></span></td>
<td><?=$r['slug']==='admin'?'Full access':(int)$r['permission_count']?></td>
<td><?=(int)$r['user_count']?></td>
<td><a href="<?=e(url('/admin/roles/'.(int)$r['id'].'/edit'))?>">Manage</a><?php if(!$sys && (int)$r['user_count']===0): ?> · <form method="post" action="<?=e(url('/admin/roles/'.(int)$r['id'].'/delete'))?>" style="display:inline"><?=csrf_field()?><button class="text-button" type="submit" onclick="return confirm('Delete this role?')">Delete</button></form><?php endif?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div></section>
</div></div></main>
