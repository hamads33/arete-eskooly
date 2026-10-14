<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/schools')) ?>">School Sign-ups</a><i data-lucide="chevron-right"></i><span><?= e($school['name']) ?></span></nav><h1>Features for <?= e($school['name']) ?></h1><p>Ticked features are available to this school. Unticked ones show a lock in its menu and a "not in your plan" message; its data is never deleted, and ticking a feature again brings everything back.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Included features</h2><p><?= count($modules) - count($locked) ?> of <?= count($modules) ?> enabled. Students, staff, classes, subjects, settings and roles are always included.</p></div></header><div class="module-card__body">
<form method="post" action="<?= e(url('/admin/schools/' . (int) $school['id'] . '/features')) ?>">
<?= csrf_field() ?>
<div class="table-scroll"><table class="data-table"><thead><tr><th style="width:56px">On</th><th>Feature</th><th>What it covers</th></tr></thead><tbody>
<?php foreach ($modules as $key => [$label, $description]): $on = !in_array($key, $locked, true); ?>
<tr>
<td><input type="checkbox" id="feature-<?= e($key) ?>" name="enabled[]" value="<?= e($key) ?>" <?= $on ? 'checked' : '' ?>></td>
<td><label for="feature-<?= e($key) ?>"><strong><?= e($label) ?></strong></label><?= $on ? '' : ' <span class="status-badge status-badge--absent">Locked</span>' ?></td>
<td><?= e($description) ?></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save features</button><a class="btn" href="<?= e(url('/admin/schools')) ?>">Back to schools</a></div>
</form>
</div></section>
</div></div></main>
