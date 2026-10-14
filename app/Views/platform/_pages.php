<?php
// Per-page access, grouped like the school's sidebar. Expects $pages and $lockedPages.
$oldPages = old('pages_enabled', null);
$groups = [];
foreach ($pages as $page) $groups[$page['group']][] = $page;
?>
<section class="module-card"><header class="module-card__header"><div><h2>Individual pages</h2><p>Untick a single page to lock just that page, for example one setting or one report. A page inside a locked feature above stays locked whatever its tick says.</p></div></header><div class="module-card__body">
<input type="hidden" name="pages_form" value="1">
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px">
<?php foreach ($groups as $group => $items): ?>
<fieldset style="border:1px solid #eadde2;border-radius:12px;padding:10px 14px;margin:0">
<legend style="font-weight:700;padding:0 6px"><?= e($group) ?></legend>
<?php foreach ($items as $page): $id = 'page-' . substr(md5($page['route']), 0, 10); $on = !in_array($page['route'], $lockedPages, true) && ($oldPages === null || in_array($page['route'], (array) $oldPages, true)); $module = \App\Platform\Features::moduleFor($page['route']); ?>
<label for="<?= e($id) ?>" style="display:flex;gap:8px;align-items:flex-start;padding:4px 0;cursor:pointer"><input type="checkbox" id="<?= e($id) ?>" name="pages_enabled[]" value="<?= e($page['route']) ?>" <?= $on ? 'checked' : '' ?>><span><?= e($page['label']) ?><?php if ($module !== null): ?> <small style="color:#8a7d82">· <?= e(\App\Platform\Features::label($module)) ?></small><?php endif ?></span></label>
<?php endforeach ?>
</fieldset>
<?php endforeach ?>
</div>
</div></section>
