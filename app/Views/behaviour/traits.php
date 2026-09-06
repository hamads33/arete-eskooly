<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Behaviour &amp; Skills</span></nav><h1>Behaviour Traits &amp; Skills</h1><p>Define what your institute rates in each domain.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Add Trait</h2><p>Affective covers attitude and discipline; Psychomotor covers physical and artistic skill.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/behaviour/traits'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="domain">Domain</label><select class="module-input" id="domain" name="domain" required><?php foreach($domains as $d):?><option value="<?=e($d)?>"><?=e(ucfirst($d))?></option><?php endforeach?></select></div>
<div class="module-field"><label for="name">Trait Name</label><input class="module-input" id="name" name="name" type="text" required maxlength="120" placeholder="e.g. Teamwork"></div>
<div class="module-field"><label for="description">Description</label><input class="module-input" id="description" name="description" type="text" maxlength="255" placeholder="Optional"></div>
<div class="module-field"><label for="sort_order">Sort Order</label><input class="module-input" id="sort_order" name="sort_order" type="number" min="0" max="65535" value="0"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Add Trait</button></div>
</form>
</div></section>

<?php foreach($domains as $domain): $rows=array_values(array_filter($traits, static fn(array $t):bool => $t['domain']===$domain)); ?>
<section class="module-card"><header class="module-card__header"><div><h2><?=e(ucfirst($domain))?> Domain</h2><p><?=count($rows)?> trait(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>#</th><th>Trait</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $t):?><tr>
<td><?=(int)$t['sort_order']?></td>
<td><?=e($t['name'])?></td>
<td><?=e($t['description']?:'—')?></td>
<td><span class="status-badge"><?=e(ucfirst((string)$t['status']))?></span></td>
<td>
<form method="post" action="<?=e(url('/admin/behaviour/traits/'.(int)$t['id'].'/status'))?>" class="inline-form"><?=csrf_field()?><input type="hidden" name="status" value="<?=$t['status']==='active'?'inactive':'active'?>"><button class="text-button" type="submit"><?=$t['status']==='active'?'Deactivate':'Activate'?></button></form>
 · <form method="post" action="<?=e(url('/admin/behaviour/traits/'.(int)$t['id'].'/delete'))?>" class="inline-form"><?=csrf_field()?><button class="text-button" type="submit" onclick="return confirm('Delete this trait?')">Delete</button></form>
</td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="5"><div class="compact-empty"><h3>No Traits</h3><p>Add a trait to start rating this domain.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
<?php endforeach; ?>
</div></div></main>
