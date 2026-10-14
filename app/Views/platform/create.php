<?php
$field = static function (string $name, string $label, string $type = 'text', string $hint = '', array $attrs = []): void {
    $err = error($name);
    $extra = '';
    foreach ($attrs as $k => $v) $extra .= ' ' . $k . '="' . e((string) $v) . '"';
    echo '<div class="module-field"><label for="' . e($name) . '">' . e($label) . '</label><input class="module-input" id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '"' . ($type === 'password' ? '' : ' value="' . e((string) old($name, '')) . '"') . $extra . '>';
    if ($err) echo '<small style="color:#b4232f">' . e($err) . '</small>'; elseif ($hint !== '') echo '<small>' . e($hint) . '</small>';
    echo '</div>';
};
$oldEnabled = old('enabled', null);
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/schools')) ?>">School Sign-ups</a><i data-lucide="chevron-right"></i><span>Create a school</span></nav><h1>Create a School</h1><p>Sets up a new school portal straight away, with the features and limits you choose. You can change them any time from the school's Features page.</p></div></header>

<form method="post" action="<?= e(url('/admin/schools/new')) ?>" enctype="multipart/form-data">
<?= csrf_field() ?>
<section class="module-card"><header class="module-card__header"><div><h2>School</h2></div></header><div class="module-card__body"><div class="filter-bar filter-bar--compact">
<?php $field('school_name', 'School name', 'text', '', ['required' => 'required', 'maxlength' => 160]); ?>
<?php $field('slug', 'Portal address', 'text', 'e.g. brightfuture → brightfuture.' . \App\Platform\PlatformConfig::domain(), ['required' => 'required', 'maxlength' => 30, 'pattern' => '[a-z][a-z0-9-]{1,28}[a-z0-9]', 'autocapitalize' => 'none']); ?>
<?php $field('city', 'City', 'text', '', ['required' => 'required', 'maxlength' => 120]); ?>
<div class="module-field"><label for="school_logo">Logo (optional)</label><input class="module-input" id="school_logo" name="school_logo" type="file" accept="image/png,image/jpeg"><?php if ($err = error('school_logo')): ?><small style="color:#b4232f"><?= e($err) ?></small><?php else: ?><small>PNG or JPG, up to 1 MB.</small><?php endif ?></div>
</div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>School administrator</h2><p>They sign in at the school's address with this email and password.</p></div></header><div class="module-card__body"><div class="filter-bar filter-bar--compact">
<?php $field('admin_name', 'Full name', 'text', '', ['required' => 'required', 'maxlength' => 120]); ?>
<?php $field('email', 'Email', 'email', '', ['required' => 'required', 'maxlength' => 190]); ?>
<?php $field('phone', 'Mobile number', 'tel', '', ['required' => 'required', 'maxlength' => 40]); ?>
<?php $field('password', 'Password', 'password', 'At least 8 characters. Share it with the school privately.', ['required' => 'required', 'minlength' => 8, 'maxlength' => 72, 'autocomplete' => 'new-password']); ?>
</div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Limits</h2><p>Leave empty for no limit.</p></div></header><div class="module-card__body"><div class="filter-bar filter-bar--compact">
<?php foreach (\App\Platform\Features::LIMITS as $key => [, $label]) $field('limit_' . $key, 'Maximum ' . strtolower($label), 'number', '', ['min' => 0, 'step' => 1, 'placeholder' => 'No limit']); ?>
</div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Features</h2><p>Unticked features are locked for this school. Students, staff, classes, subjects, settings and roles are always included.</p></div></header><div class="module-card__body">
<div class="table-scroll"><table class="data-table"><thead><tr><th style="width:56px">On</th><th>Feature</th><th>What it covers</th></tr></thead><tbody>
<?php foreach ($modules as $key => [$label, $description]): $on = $oldEnabled === null || in_array($key, (array) $oldEnabled, true); ?>
<tr><td><input type="checkbox" id="feature-<?= e($key) ?>" name="enabled[]" value="<?= e($key) ?>" <?= $on ? 'checked' : '' ?>></td><td><label for="feature-<?= e($key) ?>"><strong><?= e($label) ?></strong></label></td><td><?= e($description) ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
</div></section>
<?php require __DIR__ . '/_pages.php'; ?>
<section><div><div class="filter-actions"><button class="btn btn--primary" type="submit">Create school portal</button><a class="btn" href="<?= e(url('/admin/schools')) ?>">Cancel</a></div>
</div></section>
</form>
</div></div></main>
