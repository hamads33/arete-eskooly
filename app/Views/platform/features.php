<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/schools')) ?>">School Sign-ups</a><i data-lucide="chevron-right"></i><span><?= e($school['name']) ?></span></nav><h1>Features for <?= e($school['name']) ?></h1><p>Ticked features are available to this school. Unticked ones show a lock in its menu and a "not in your plan" message; its data is never deleted, and ticking a feature again brings everything back.</p></div></header>

<form method="post" action="<?= e(url('/admin/schools/' . (int) $school['id'] . '/features')) ?>">
<?= csrf_field() ?>
<section class="module-card"><header class="module-card__header"><div><h2>Included features</h2><p><?= count($modules) - count($locked) ?> of <?= count($modules) ?> enabled. Students, staff, classes, subjects, settings and roles are always included.</p></div></header><div class="module-card__body">
<h3 style="margin:0 0 .5rem">Limits</h3>
<div class="filter-bar filter-bar--compact" style="margin-bottom:1.25rem">
<?php foreach (\App\Platform\Features::LIMITS as $key => [, $label]): ?>
<div class="module-field"><label for="limit-<?= e($key) ?>">Maximum <?= e(strtolower($label)) ?></label><input class="module-input" id="limit-<?= e($key) ?>" name="limit_<?= e($key) ?>" type="number" min="0" step="1" inputmode="numeric" placeholder="No limit" value="<?= $limits[$key] !== null ? (int) $limits[$key] : '' ?>"><small>Now: <?= $usage[$key] !== null ? (int) $usage[$key] : '—' ?><?= $key === 'students' ? ' (archived students do not count)' : '' ?>. Leave empty for no limit.</small></div>
<?php endforeach ?>
</div>
<h3 style="margin:0 0 .5rem">Features</h3>
<div class="table-scroll"><table class="data-table"><thead><tr><th style="width:56px">On</th><th>Feature</th><th>What it covers</th></tr></thead><tbody>
<?php foreach ($modules as $key => [$label, $description]): $on = !in_array($key, $locked, true); ?>
<tr>
<td><input type="checkbox" id="feature-<?= e($key) ?>" name="enabled[]" value="<?= e($key) ?>" <?= $on ? 'checked' : '' ?>></td>
<td><label for="feature-<?= e($key) ?>"><strong><?= e($label) ?></strong></label><?= $on ? '' : ' <span class="status-badge status-badge--absent">Locked</span>' ?></td>
<td><?= e($description) ?></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
</div></section>
<?php require __DIR__ . '/_pages.php'; ?>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save plan</button><a class="btn" href="<?= e(url('/admin/schools')) ?>">Back to schools</a></div>
</form>
</div></div></main>
