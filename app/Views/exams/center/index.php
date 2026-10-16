<?php
$statusLabel = static function (array $x): array {
    if ($x['status'] === 'locked') return ['Locked', 'completed'];
    if ($x['status'] === 'published') return ['Published', 'published'];
    if ($x['paper_count'] > 0 && $x['published_papers'] === $x['paper_count']) return ['Published', 'published'];
    if ($x['published_papers'] > 0) return ['Partly published', 'partial'];
    if ($x['submitted_papers'] > 0) return ['Awaiting review', 'pending'];
    if ($x['paper_count'] === 0) return ['No papers yet', 'draft'];
    return ['Marking', 'upcoming'];
};
$date = static fn (string $d): string => date('j M Y', (int) strtotime($d));
?>
<main class="main"><div class="main__inner"><div class="module-page exam-center">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/dashboard')) ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Exams</span></nav><h1><?= $isTeacher ? 'Exams &amp; Marks' : 'Exams' ?></h1><p><?= $isTeacher ? 'Your papers in each exam. Open one to enter marks.' : 'Each exam holds its class and subject papers, marks and results. Open an exam to manage it.' ?></p></div></header>

<?php if ($isTeacher): ?>
<section class="module-card"><header class="module-card__header"><div><h2>My papers</h2><p>Papers for the classes and subjects you teach.</p></div></header><div class="module-card__body">
<?php if (!$myPapers): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="file-check-2"></i></span><strong>No papers for you yet</strong><p>When the admin adds a paper for a class and subject you teach, it appears here.</p></div></div>
<?php else: ?>
<div class="ec-paper-list">
<?php foreach ($myPapers as $p): $left = max(0, $p['roster'] - $p['marked']); ?>
<a class="ec-paper" href="<?= e(url('/admin/exams/papers/' . (int) $p['id'])) ?>">
  <div class="ec-paper__main"><small><?= e($p['exam_name']) ?></small><strong><?= e($p['subject_name']) ?> — <?= e($p['class_name']) ?></strong>
  <span class="ec-paper__meta"><?= (int) $p['roster'] ?> students · <?= (int) $p['marked'] ?> marked<?= $left ? ' · <b>' . $left . ' remaining</b>' : '' ?></span></div>
  <div class="ec-paper__side">
    <?php if ($p['visible']): ?><span class="status-badge status-badge--published">Published</span><?php elseif ($p['marks_status'] === 'submitted'): ?><span class="status-badge status-badge--pending">Sent for review</span><?php elseif ($left): ?><span class="btn btn--primary btn--sm">Continue marking</span><?php else: ?><span class="btn btn--secondary btn--sm">Review &amp; send</span><?php endif ?>
  </div>
</a>
<?php endforeach ?>
</div>
<?php endif ?>
</div></section>
<?php endif ?>

<section class="module-card"><header class="module-card__header"><div><h2><?= $isTeacher ? 'Exams with your classes' : 'All exams' ?></h2></div><?php if (!$isTeacher): ?><a class="btn btn--primary" href="#new-exam"><i data-lucide="plus"></i> New exam</a><?php endif ?></header><div class="module-card__body">
<?php if (!$exams): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="file-check-2"></i></span><strong>No exams yet</strong><p><?= $isTeacher ? 'None of your classes is in an exam yet.' : 'Create your first exam below.' ?></p></div></div>
<?php else: ?>
<div class="ec-scroll"><table class="data-table no-table-tools ec-table">
<thead><tr><th>Exam</th><th>Dates</th><th>Classes</th><th class="num">Students</th><th class="num">Papers</th><th>Marking</th><th>Results</th><th><span class="sr-only">Open</span></th></tr></thead>
<tbody>
<?php foreach ($exams as $x): [$label, $tone] = $statusLabel($x); $pct = $x['paper_count'] ? (int) round($x['complete_papers'] / $x['paper_count'] * 100) : 0; ?>
<tr>
<td data-label="Exam"><a class="ec-link" href="<?= e(url('/admin/exams/' . (int) $x['id'])) ?>"><?= e($x['name']) ?></a></td>
<td data-label="Dates"><?= e($date($x['start_date'])) ?> – <?= e($date($x['end_date'])) ?></td>
<td data-label="Classes"><?= $x['classes'] ? e(implode(', ', array_column($x['classes'], 'name'))) : '<span class="ec-muted">None yet</span>' ?></td>
<td data-label="Students" class="num"><?= (int) $x['student_count'] ?></td>
<td data-label="Papers" class="num"><?= (int) $x['paper_count'] ?></td>
<td data-label="Marking"><?php if ($x['paper_count']): ?><div class="ec-progress" title="<?= (int) $x['complete_papers'] ?> of <?= (int) $x['paper_count'] ?> papers fully marked"><span style="width:<?= $pct ?>%"></span></div><small><?= (int) $x['complete_papers'] ?>/<?= (int) $x['paper_count'] ?> complete</small><?php else: ?><span class="ec-muted">—</span><?php endif ?></td>
<td data-label="Results"><span class="status-badge status-badge--<?= e($tone) ?>"><?= e($label) ?></span></td>
<td><a class="btn btn--secondary btn--sm" href="<?= e(url('/admin/exams/' . (int) $x['id'])) ?>">Manage</a></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</div></section>

<?php if (!$isTeacher): ?>
<div class="ec-two">
<form class="module-card" id="new-exam" method="post" action="<?= e(url('/admin/exams/new')) ?>"><?= csrf_field() ?>
<header class="module-card__header"><div><h2>New exam</h2><p>For example "Mid Term 2026" or "Monthly Test – October".</p></div></header>
<div class="module-card__body">
<div class="module-form-grid">
<div class="module-field"><label for="ec-name">Exam name</label><input class="module-input" id="ec-name" name="name" maxlength="160" required value="<?= e((string) old('name', '')) ?>"></div>
<div class="module-field"><label for="ec-start">Start date</label><input class="module-input" id="ec-start" type="date" name="start_date" required value="<?= e((string) old('start_date', '')) ?>"></div>
<div class="module-field"><label for="ec-end">End date</label><input class="module-input" id="ec-end" type="date" name="end_date" required value="<?= e((string) old('end_date', '')) ?>"></div>
</div>
<fieldset class="ec-classes"><legend>Classes taking this exam <small>(optional now — you can add them later)</small></legend>
<div class="ec-check-grid">
<?php foreach ($classes as $c): ?><label class="ec-check"><input type="checkbox" name="classes[]" value="<?= (int) $c['id'] ?>" data-count="<?= (int) $c['active_students'] ?>"><span><?= e($c['name']) ?> <small>(<?= (int) $c['active_students'] ?>)</small></span></label><?php endforeach ?>
</div>
<p class="ec-muted" data-ec-class-total hidden></p>
</fieldset>
<button class="btn btn--primary" type="submit">Create exam</button>
</div></form>

<form class="module-card" id="exam-settings" method="post" action="<?= e(url('/admin/exams/settings')) ?>"><?= csrf_field() ?>
<header class="module-card__header"><div><h2>What teachers can do</h2><p>Teachers always enter marks only for the classes and subjects they are assigned to.</p></div></header>
<div class="module-card__body">
<label class="ec-toggle"><input type="checkbox" name="teachers_add_papers" value="1" <?= $settings['teachers_add_papers'] ? 'checked' : '' ?>><span><strong>Teachers can add exam papers</strong><small>For their own class and subject, inside an exam that already includes that class.</small></span></label>
<label class="ec-toggle"><input type="checkbox" name="teachers_publish" value="1" <?= $settings['teachers_publish'] ? 'checked' : '' ?>><span><strong>Teachers can publish results</strong><small>Off: teachers send marks to you for review and you publish. On: teachers publish their own subject when every student is marked.</small></span></label>
<button class="btn btn--primary" type="submit">Save</button>
</div></form>
</div>
<?php endif ?>
</div></div></main>
