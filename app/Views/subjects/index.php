<main class="main" id="classes-with-subjects"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Subjects</span><i data-lucide="chevron-right"></i><span aria-current="page">Classes With Subjects</span></nav><h1>Classes With Subjects</h1><p>Review, rename and remove subject assignments</p></div><a class="btn btn--primary" href="<?= url('/admin/subjects/assign') ?>"><i data-lucide="plus"></i>Assign Subjects</a></header>

<section class="module-card"><header class="module-card__header"><div><h2>Class &amp; Subject Directory</h2><p>Persisted subject associations by class</p></div></header><div class="module-card__body">
<?php if(!$classes): ?>
<div class="compact-empty"><div><span class="compact-empty__icon"><i data-lucide="book-open"></i></span><h3>No Classes Found</h3><p>Create a class before assigning subjects.</p></div></div>
<?php else: ?>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Class</th><th>Subjects</th><th>Remove From Class</th></tr></thead><tbody>
<?php foreach($classes as $class): ?><tr>
<td><?= e($class['name']) ?></td>
<td><?php if(!$class['subjects']): ?>No subjects assigned<?php else: ?><?php foreach($class['subjects'] as $subject): ?><span class="status-badge"><?= e($subject['name']) ?> (<?= e($subject['marks']) ?>)</span> <?php endforeach ?><?php endif ?></td>
<td>
<?php foreach($class['subjects'] as $subject): ?>
<form method="post" action="<?= e(url('/admin/subjects/'.(int)$subject['id'].'/unassign')) ?>" class="inline-form">
<?= csrf_field() ?><input type="hidden" name="class_id" value="<?= (int)$class['id'] ?>">
<button class="text-button" type="submit" onclick="return confirm('Remove <?= e($subject['name']) ?> from <?= e($class['name']) ?>?')">Remove <?= e($subject['name']) ?></button>
</form>
<?php endforeach ?>
<?php if(!$class['subjects']): ?>&mdash;<?php endif ?>
</td>
</tr><?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>All Subjects</h2><p>Rename a subject everywhere, or delete one that is no longer used.</p></div></header><div class="module-card__body">
<div class="table-scroll"><table class="data-table"><thead><tr><th>Subject</th><th>Classes</th><th>Rename</th><th>Actions</th></tr></thead><tbody>
<?php foreach(($subjects ?? []) as $s): ?><tr>
<td><?= e($s['name']) ?></td>
<td><?= (int)$s['class_count'] ?></td>
<td>
<form method="post" action="<?= e(url('/admin/subjects/'.(int)$s['id'].'/rename')) ?>" class="inline-form">
<?= csrf_field() ?>
<input class="module-input" type="text" name="name" value="<?= e($s['name']) ?>" maxlength="120" required>
<button class="text-button" type="submit">Save</button>
</form>
</td>
<td>
<form method="post" action="<?= e(url('/admin/subjects/'.(int)$s['id'].'/delete')) ?>" class="inline-form">
<?= csrf_field() ?>
<button class="text-button" type="submit" onclick="return confirm('Delete this subject permanently?')">Delete</button>
</form>
</td>
</tr><?php endforeach ?>
<?php if(!($subjects ?? [])): ?><tr><td colspan="4"><div class="compact-empty"><h3>No Subjects</h3><p>Assign a subject to a class to create one.</p></div></td></tr><?php endif ?>
</tbody></table></div>
</div></section>
</div></div></main>
