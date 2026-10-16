<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/schools')) ?>">School Sign-ups</a><i data-lucide="chevron-right"></i><span>Default plan</span></nav><h1>Default Plan for New Schools</h1><p>Every new school starts with these features, pages and limits, whether it signs up itself or you create it. You can still change any one school from its Features page. Saving here does not change schools that already exist.</p></div></header>

<form method="post" action="<?= e(url('/admin/schools/defaults')) ?>">
<?= csrf_field() ?>
<section class="module-card"><header class="module-card__header"><div><h2>Included features</h2><p><?= count($modules) - count($locked) ?> of <?= count($modules) ?> enabled by default. Students, staff, classes, subjects, settings and roles are always included.</p></div></header><div class="module-card__body">
<h3 style="margin:0 0 .5rem">Subscription length</h3>
<div class="filter-bar filter-bar--compact" style="margin-bottom:1.25rem">
<div class="module-field"><label for="valid_days">Access lasts (days)</label><input class="module-input" id="valid_days" name="valid_days" type="number" min="1" max="3660" step="1" inputmode="numeric" placeholder="No end date" value="<?= $validDays !== null ? (int) $validDays : '' ?>"><small>For example 10 for a trial or 30 for a month, counted from the day the school goes live. Access stops by itself afterwards. Leave empty for no end date.</small></div>
</div>
<h3 style="margin:0 0 .5rem">Limits</h3>
<div class="filter-bar filter-bar--compact" style="margin-bottom:1.25rem">
<?php foreach (\App\Platform\Features::LIMITS as $key => [, $label]): ?>
<div class="module-field"><label for="limit-<?= e($key) ?>">Maximum <?= e(strtolower($label)) ?></label><input class="module-input" id="limit-<?= e($key) ?>" name="limit_<?= e($key) ?>" type="number" min="0" step="1" inputmode="numeric" placeholder="No limit" value="<?= $limits[$key] !== null ? (int) $limits[$key] : '' ?>"><small>Leave empty for no limit.</small></div>
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
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save default plan</button><a class="btn" href="<?= e(url('/admin/schools')) ?>">Back to schools</a></div>
</form>

<form method="post" action="<?= e(url('/admin/schools/lock-message')) ?>" id="lock-message" style="margin-top:1.5rem">
<?= csrf_field() ?>
<section class="module-card"><header class="module-card__header"><div><h2>Message for locked features</h2><p>Shown to every school when it opens a feature that is not in its plan, and when its subscription has ended. Saving applies it to all schools straight away. Leave a box empty to use the wording shown in grey.</p></div></header><div class="module-card__body">
<?php $D = \App\Platform\Features::LOCK_DEFAULTS; ?>
<div class="filter-bar filter-bar--compact">
<div class="module-field"><label for="lock-title">Label</label><input class="module-input" id="lock-title" name="lock[title]" maxlength="80" placeholder="<?= e($D['title']) ?>" value="<?= e($lockMessage['title']) ?>"></div>
<div class="module-field"><label for="lock-whatsapp">WhatsApp number</label><input class="module-input" id="lock-whatsapp" name="lock[whatsapp]" type="tel" maxlength="20" placeholder="e.g. 923001234567" value="<?= e($lockMessage['whatsapp']) ?>"><small>With country code. Leave empty to hide the WhatsApp button.</small></div>
<div class="module-field"><label for="lock-button">Button text</label><input class="module-input" id="lock-button" name="lock[button]" maxlength="40" placeholder="<?= e($D['button']) ?>" value="<?= e($lockMessage['button']) ?>"></div>
</div>
<div class="module-field" style="margin-top:.75rem"><label for="lock-message-text">Message</label><textarea class="module-input" id="lock-message-text" name="lock[message]" rows="3" maxlength="400" placeholder="<?= e($D['message']) ?>"><?= e($lockMessage['message']) ?></textarea></div>
<div class="filter-actions" style="margin-top:1rem"><button class="btn btn--primary" type="submit">Save message for all schools</button></div>
</div></section>
</form>
</div></div></main>
