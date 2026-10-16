<?php
$examId = (int) $exam['id'];
$base = '/admin/exams/' . $examId;
$date = static fn (string $d): string => date('j M Y', (int) strtotime($d));
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
$examPublished = in_array($exam['status'], ['published', 'locked'], true);
$locked = $exam['status'] === 'locked';
$paperBadge = static function (array $p): string {
    if ($p['visible']) return '<span class="status-badge status-badge--published">Published</span>';
    if ($p['marks_status'] === 'submitted') return '<span class="status-badge status-badge--pending">Awaiting review</span>';
    if ($p['roster'] > 0 && $p['marked'] >= $p['roster']) return '<span class="status-badge status-badge--completed">Marked</span>';
    if ($p['marked'] > 0) return '<span class="status-badge status-badge--partial">Marking</span>';
    return '<span class="status-badge status-badge--draft">Not started</span>';
};
$byClass = [];
foreach ($papers as $p) $byClass[(int) $p['class_id']][] = $p;
$rosterByClass = [];
foreach ($roster as $r) $rosterByClass[(int) $r['class_id']][] = $r;
$totalPapers = count($papers);
$complete = count(array_filter($papers, static fn ($p) => $p['roster'] > 0 && $p['marked'] >= $p['roster']));
$published = count(array_filter($papers, static fn ($p) => $p['visible']));
$review = count(array_filter($papers, static fn ($p) => $p['marks_status'] === 'submitted' && !$p['visible']));
$tabs = ['overview' => 'Overview', 'papers' => 'Papers & marks', 'students' => 'Students', 'results' => 'Results'];
if ($isTeacher) unset($tabs['students']);
$classPills = static function (string $tab) use ($classes, $selectedClass, $base): string {
    if (count($classes) < 2) return '';
    $h = '<nav class="ec-pills" aria-label="Class">';
    foreach ($classes as $c) $h .= '<a href="' . e(url($base . '?tab=' . $tab . '&class=' . (int) $c['id'])) . '"' . ((int) $c['id'] === $selectedClass ? ' aria-current="page"' : '') . '>' . e($c['name']) . '</a>';
    return $h . '</nav>';
};
?>
<main class="main"><div class="main__inner"><div class="module-page exam-center">
<header class="module-page__header"><div>
<nav class="breadcrumb"><a href="<?= e(url('/admin/exams')) ?>">Exams</a><i data-lucide="chevron-right"></i><span><?= e($exam['name']) ?></span></nav>
<h1><?= e($exam['name']) ?></h1>
<p><?= e($date($exam['start_date'])) ?> – <?= e($date($exam['end_date'])) ?> · <?= count($classes) ?> class(es) · <?= count($roster) ?> student(s) · <?= $totalPapers ?> paper(s)
<?php if ($locked): ?> · <span class="status-badge status-badge--completed">Locked</span><?php elseif ($examPublished): ?> · <span class="status-badge status-badge--published">Published</span><?php endif ?></p>
</div></header>

<nav class="ec-tabs" aria-label="Exam sections">
<?php foreach ($tabs as $key => $label): ?><a href="<?= e(url($base . '?tab=' . $key . ($selectedClass ? '&class=' . $selectedClass : ''))) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($key === 'papers' && $review && !$isTeacher): ?> <span class="ec-dot" title="Awaiting review"><?= $review ?></span><?php endif ?></a><?php endforeach ?>
</nav>

<?php if ($tab === 'overview'): ?>
<div class="ec-metrics">
<div class="metric-card"><span>Students</span><strong><?= count($roster) ?></strong></div>
<div class="metric-card"><span>Papers</span><strong><?= $totalPapers ?></strong></div>
<div class="metric-card"><span>Fully marked</span><strong><?= $complete ?>/<?= $totalPapers ?></strong></div>
<div class="metric-card"><span>Published</span><strong><?= $published ?>/<?= $totalPapers ?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Progress by class</h2><p>Open a class's papers to enter marks, or its results to review and publish.</p></div></header><div class="module-card__body">
<?php if (!$classes): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users"></i></span><strong>No classes yet</strong><p><?= $isTeacher ? 'The admin has not added a class you teach.' : 'Add the classes taking this exam on the Students tab, or add papers directly on the Papers tab.' ?></p><?php if (!$isTeacher): ?><a class="btn btn--primary" href="<?= e(url($base . '?tab=students')) ?>">Add classes</a><?php endif ?></div></div>
<?php else: ?>
<div class="ec-scroll"><table class="data-table no-table-tools ec-table"><thead><tr><th>Class</th><th class="num">Students</th><th class="num">Papers</th><th>Marking</th><th>Published</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($classes as $c): $cp = $byClass[(int) $c['id']] ?? []; $done = count(array_filter($cp, static fn ($p) => $p['roster'] > 0 && $p['marked'] >= $p['roster'])); $pub = count(array_filter($cp, static fn ($p) => $p['visible'])); $pct = $cp ? (int) round($done / count($cp) * 100) : 0; ?>
<tr>
<td data-label="Class"><strong><?= e($c['name']) ?></strong></td>
<td data-label="Students" class="num"><?= count($rosterByClass[(int) $c['id']] ?? []) ?></td>
<td data-label="Papers" class="num"><?= count($cp) ?></td>
<td data-label="Marking"><?php if ($cp): ?><div class="ec-progress"><span style="width:<?= $pct ?>%"></span></div><small><?= $done ?>/<?= count($cp) ?> papers complete</small><?php else: ?><span class="ec-muted">No papers</span><?php endif ?></td>
<td data-label="Published"><?= $cp ? $pub . '/' . count($cp) : '—' ?></td>
<td class="ec-actions"><a class="btn btn--secondary btn--sm" href="<?= e(url($base . '?tab=papers&class=' . (int) $c['id'])) ?>">Papers</a> <a class="btn btn--secondary btn--sm" href="<?= e(url($base . '?tab=results&class=' . (int) $c['id'])) ?>">Results</a></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</div></section>

<?php if (!$isTeacher): ?>
<div class="ec-two">
<section class="module-card"><header class="module-card__header"><div><h2>Publish results</h2><p>Students and parents see a result only once it is published.</p></div></header><div class="module-card__body">
<?php if ($locked): ?>
<p>This exam is <strong>locked</strong>: results are published and marks can no longer change.</p>
<form method="post" action="<?= e(url($base . '/unlock')) ?>"><?= csrf_field() ?><button class="btn btn--secondary" type="submit">Unlock exam</button></form>
<?php elseif ($examPublished): ?>
<p>The whole exam is <strong>published</strong>.</p>
<div class="ec-actions">
<form method="post" action="<?= e(url($base . '/unpublish')) ?>" onsubmit="return confirm('Unpublish the whole exam? Students and parents will no longer see these results.')"><?= csrf_field() ?><button class="btn btn--secondary" type="submit">Unpublish</button></form>
<form method="post" action="<?= e(url($base . '/lock')) ?>" onsubmit="return confirm('Lock this exam? Nobody can change its marks until it is unlocked.')"><?= csrf_field() ?><button class="btn btn--secondary" type="submit"><i data-lucide="lock"></i> Lock marks</button></form>
</div>
<?php else: ?>
<p><?= $published ?> of <?= $totalPapers ?> paper(s) published<?= $review ? ', ' . $review . ' awaiting your review' : '' ?>. Papers with unmarked students are held back unless you tick "publish anyway".</p>
<form method="post" action="<?= e(url($base . '/publish')) ?>" onsubmit="return confirm('Publish the whole exam to <?= count($roster) ?> student(s) and their parents?')"><?= csrf_field() ?>
<label class="ec-check"><input type="checkbox" name="allow_incomplete" value="1"><span>Publish anyway, even where some students have no marks</span></label>
<button class="btn btn--primary" type="submit" <?= $totalPapers ? '' : 'disabled' ?>><i data-lucide="send"></i> Publish whole exam</button>
</form>
<?php endif ?>
</div></section>

<form class="module-card" method="post" action="<?= e(url($base . '/update')) ?>"><?= csrf_field() ?>
<header class="module-card__header"><div><h2>Exam details</h2></div></header><div class="module-card__body">
<div class="module-form-grid">
<div class="module-field"><label for="ec-name">Name</label><input class="module-input" id="ec-name" name="name" maxlength="160" required value="<?= e($exam['name']) ?>"></div>
<div class="module-field"><label for="ec-start">Start date</label><input class="module-input" id="ec-start" type="date" name="start_date" required value="<?= e($exam['start_date']) ?>"></div>
<div class="module-field"><label for="ec-end">End date</label><input class="module-input" id="ec-end" type="date" name="end_date" required value="<?= e($exam['end_date']) ?>"></div>
</div>
<div class="ec-actions"><button class="btn btn--primary" type="submit">Save details</button></div>
</div></form>
</div>
<form class="ec-danger" method="post" action="<?= e(url($base . '/delete')) ?>" onsubmit="return confirm('Delete <?= e($exam['name']) ?> with all its papers and marks? This cannot be undone.')"><?= csrf_field() ?><button class="btn btn--danger btn--sm" type="submit" <?= $locked ? 'disabled' : '' ?>><i data-lucide="trash-2"></i> Delete exam</button></form>
<?php endif ?>

<?php elseif ($tab === 'papers'): ?>
<?= $classPills('papers') ?>
<?php $canAdd = !$isTeacher || $settings['teachers_add_papers']; ?>
<section class="module-card"><header class="module-card__header"><div><h2>Papers<?= $selectedClass && count($classes) > 1 ? '' : '' ?></h2><p>Each paper is one subject for one class. Open it to enter marks.</p></div></header><div class="module-card__body">
<?php $shown = $selectedClass ? ($byClass[$selectedClass] ?? []) : $papers; if (count($classes) < 2) $shown = $papers; ?>
<?php if (!$shown): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="file-plus-2"></i></span><strong>No papers yet</strong><p><?= $canAdd ? 'Add the subjects below.' : 'The admin has not added a paper for your subjects yet.' ?></p></div></div>
<?php else: ?>
<div class="ec-scroll"><table class="data-table no-table-tools ec-table"><thead><tr><th>Subject</th><th>Class</th><th class="num">Total marks</th><th>Marked</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($shown as $p): $pid = (int) $p['id']; ?>
<tr>
<td data-label="Subject"><a class="ec-link" href="<?= e(url('/admin/exams/papers/' . $pid)) ?>"><?= e($p['subject_name']) ?></a></td>
<td data-label="Class"><?= e($p['class_name']) ?></td>
<td data-label="Total marks" class="num"><?php if (!$isTeacher && !$locked): ?><form class="ec-inline" method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/total')) ?>"><?= csrf_field() ?><input class="module-input ec-total" type="number" name="total_marks" min="0.01" step="0.01" value="<?= e($num($p['total_marks'])) ?>" aria-label="Total marks for <?= e($p['subject_name']) ?>"><button class="btn btn--ghost btn--sm" type="submit" title="Save total">Save</button></form><?php else: ?><?= e($num($p['total_marks'])) ?><?php endif ?></td>
<td data-label="Marked"><?= (int) $p['marked'] ?>/<?= (int) $p['roster'] ?></td>
<td data-label="Status"><?= $paperBadge($p) ?></td>
<td class="ec-actions">
<a class="btn btn--primary btn--sm" href="<?= e(url('/admin/exams/papers/' . $pid)) ?>"><?= $locked || $p['visible'] || ($isTeacher && $p['marks_status'] === 'submitted') ? 'View marks' : 'Enter marks' ?></a>
<?php if (!$locked && !$examPublished): ?>
<?php if ($p['visible'] && (!$isTeacher || $settings['teachers_publish'])): ?><form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/unpublish')) ?>"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit">Unpublish</button></form>
<?php elseif (!$p['visible'] && (!$isTeacher || $settings['teachers_publish'])): ?><form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/publish')) ?>" onsubmit="return confirm('Publish <?= e($p['subject_name']) ?> for <?= e($p['class_name']) ?> (<?= (int) $p['roster'] ?> students)?')"><?= csrf_field() ?><button class="btn btn--secondary btn--sm" type="submit">Publish</button></form><?php endif ?>
<?php if (!$isTeacher && ($p['marks_status'] === 'submitted' || $p['visible'])): ?><form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/reopen')) ?>"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit" title="Let the teacher change the marks again">Reopen</button></form><?php endif ?>
<?php if (!$isTeacher): ?><form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/delete')) ?>" onsubmit="return confirm('Delete the <?= e($p['subject_name']) ?> paper for <?= e($p['class_name']) ?>?<?= $p['marked'] ? ' Its ' . (int) $p['marked'] . ' mark(s) will also be deleted.' : '' ?>')"><?= csrf_field() ?><button class="btn btn--danger btn--sm" type="submit" aria-label="Delete paper"><i data-lucide="trash-2"></i></button></form><?php endif ?>
<?php endif ?>
</td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</div></section>

<?php if ($canAdd && !$locked): ?>
<form class="module-card" method="post" action="<?= e(url($base . '/papers')) ?>" data-ec-papers><?= csrf_field() ?>
<header class="module-card__header"><div><h2>Add papers</h2><p>Choose a class, tick its subjects and set the total marks. Only subjects taught in that class are listed.</p></div></header><div class="module-card__body">
<div class="module-form-grid">
<div class="module-field"><label for="ec-paper-class">Class</label><select class="module-input" id="ec-paper-class" name="class" required data-ec-class-select>
<option value="">Choose a class</option>
<?php foreach ($allClasses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $selectedClass ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
</select></div>
<div class="module-field"><label for="ec-paper-total">Total marks (each paper)</label><input class="module-input" id="ec-paper-total" type="number" name="total_marks" min="0.01" step="0.01" required placeholder="e.g. 100"></div>
</div>
<fieldset class="ec-classes"><legend>Subjects <button class="btn btn--ghost btn--sm" type="button" data-ec-all>Tick all</button></legend>
<?php foreach ($subjectsByClass as $cid => $subs): ?>
<div class="ec-check-grid" data-ec-subjects="<?= (int) $cid ?>" hidden>
<?php $have = array_map('intval', array_column($byClass[$cid] ?? [], 'subject_id')); ?>
<?php if (!$subs): ?><p class="ec-muted">No subjects are linked to this class. Add them under Classes → Assign Subjects.</p><?php endif ?>
<?php foreach ($subs as $s): $has = in_array((int) $s['id'], $have, true); ?><label class="ec-check<?= $has ? ' is-disabled' : '' ?>"><input type="checkbox" name="subjects[]" value="<?= (int) $s['id'] ?>" <?= $has ? 'disabled' : '' ?>><span><?= e($s['name']) ?><?= $has ? ' <small>(already added)</small>' : '' ?></span></label><?php endforeach ?>
</div>
<?php endforeach ?>
<p class="ec-muted" data-ec-subjects-hint>Choose a class to see its subjects.</p>
</fieldset>
<button class="btn btn--primary" type="submit"><i data-lucide="plus"></i> Add papers</button>
</div></form>
<?php elseif ($isTeacher && !$settings['teachers_add_papers']): ?>
<p class="ec-muted">Only the admin can add papers to this exam.</p>
<?php endif ?>

<?php elseif ($tab === 'students'): ?>
<div class="ec-two">
<section class="module-card"><header class="module-card__header"><div><h2>Classes</h2><p>Every active student of these classes takes the exam.</p></div></header><div class="module-card__body">
<?php if ($classes): ?>
<ul class="ec-list">
<?php foreach ($classes as $c): ?>
<li><span><strong><?= e($c['name']) ?></strong> <small class="ec-muted"><?= count($rosterByClass[(int) $c['id']] ?? []) ?> student(s)</small></span>
<?php if (empty($byClass[(int) $c['id']])): ?><form method="post" action="<?= e(url($base . '/students')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="remove_class"><input type="hidden" name="class" value="<?= (int) $c['id'] ?>"><button class="btn btn--ghost btn--sm" type="submit">Remove</button></form><?php else: ?><small class="ec-muted">has papers</small><?php endif ?></li>
<?php endforeach ?>
</ul>
<?php else: ?><p class="ec-muted">No classes yet.</p><?php endif ?>
<form method="post" action="<?= e(url($base . '/students')) ?>" class="ec-stack"><?= csrf_field() ?><input type="hidden" name="do" value="add_class">
<?php $inExam = array_map('intval', array_column($classes, 'id')); $others = array_filter($allClasses, static fn ($c) => !in_array((int) $c['id'], $inExam, true)); ?>
<?php if ($others): ?>
<fieldset class="ec-classes"><legend>Add classes</legend><div class="ec-check-grid">
<?php foreach ($others as $c): ?><label class="ec-check"><input type="checkbox" name="classes[]" value="<?= (int) $c['id'] ?>" data-count="<?= (int) $c['active_students'] ?>"><span><?= e($c['name']) ?> <small>(<?= (int) $c['active_students'] ?>)</small></span></label><?php endforeach ?>
</div><p class="ec-muted" data-ec-class-total hidden></p></fieldset>
<button class="btn btn--primary btn--sm" type="submit">Add selected classes</button>
<?php endif ?>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Add individual students</h2><p>For example a scholarship test, or a student sitting another class's papers. They sit the papers of the class you choose.</p></div></header><div class="module-card__body">
<form method="get" action="<?= e(url($base)) ?>" class="ec-search"><input type="hidden" name="tab" value="students">
<input class="module-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Name or registration no." aria-label="Search students">
<select class="module-input" name="from_class" aria-label="From class"><option value="0">Any class</option><?php foreach ($allClasses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $fromClass === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select>
<button class="btn btn--secondary" type="submit">Search</button></form>
<?php if ($q !== '' || $fromClass): ?>
<?php if (!$candidates): ?><p class="ec-muted">No matching students who are not already in this exam.</p>
<?php elseif (!$classes): ?><p class="ec-muted">Add a class to the exam first; added students sit that class's papers.</p>
<?php else: ?>
<form method="post" action="<?= e(url($base . '/students')) ?>" class="ec-stack"><?= csrf_field() ?><input type="hidden" name="do" value="include">
<div class="ec-pick">
<?php foreach ($candidates as $s): ?><label class="ec-check"><input type="checkbox" name="students[]" value="<?= (int) $s['id'] ?>"><span><?= e($s['student_name']) ?> <small><?= e($s['registration_no']) ?> · <?= e($s['class_name'] ?? '—') ?></small></span></label><?php endforeach ?>
</div>
<div class="module-field"><label for="ec-sit">Sits the papers of</label><select class="module-input" id="ec-sit" name="sit_class" required><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach ?></select></div>
<button class="btn btn--primary btn--sm" type="submit">Add ticked students</button>
</form>
<?php endif ?>
<?php endif ?>
</div></section>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Students taking this exam <span class="ec-count"><?= count($roster) ?></span></h2><p>Each student is counted once, even if added individually and through a class.</p></div>
<input class="module-input ec-filter" type="search" placeholder="Filter by name or registration" data-ec-filter="#ec-roster" aria-label="Filter students"></header><div class="module-card__body">
<?php if (!$roster): ?><p class="ec-muted">No students yet. Add a class above.</p><?php else: ?>
<div class="ec-scroll"><table class="data-table no-table-tools ec-table" id="ec-roster"><thead><tr><th>Registration</th><th>Student</th><th>Sits papers of</th><th>How</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($roster as $r): ?>
<tr data-ec-row="<?= e(mb_strtolower($r['student_name'] . ' ' . $r['registration_no'])) ?>">
<td data-label="Registration"><?= e($r['registration_no']) ?></td>
<td data-label="Student"><strong><?= e($r['student_name']) ?></strong></td>
<td data-label="Sits papers of"><?= e($r['class_name']) ?></td>
<td data-label="How"><?= $r['source'] === 'added' ? '<span class="status-badge status-badge--upcoming">Added individually</span><small class="ec-muted"> own class: ' . e($r['own_class_name'] ?? '—') . '</small>' : 'Class' ?></td>
<td class="ec-actions">
<?php if ($r['source'] === 'added'): ?><form method="post" action="<?= e(url($base . '/students')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="reset"><input type="hidden" name="student" value="<?= (int) $r['id'] ?>"><button class="btn btn--ghost btn--sm" type="submit">Remove</button></form>
<?php else: ?><form method="post" action="<?= e(url($base . '/students')) ?>" onsubmit="return confirm('Take <?= e($r['student_name']) ?> out of this exam?')"><?= csrf_field() ?><input type="hidden" name="do" value="exclude"><input type="hidden" name="student" value="<?= (int) $r['id'] ?>"><button class="btn btn--ghost btn--sm" type="submit">Exclude</button></form><?php endif ?>
</td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?php if ($excluded): ?>
<h3 class="ec-sub">Excluded (<?= count($excluded) ?>)</h3>
<ul class="ec-list">
<?php foreach ($excluded as $s): ?><li><span><?= e($s['student_name']) ?> <small class="ec-muted"><?= e($s['registration_no']) ?> · <?= e($s['class_name'] ?? '—') ?></small></span><form method="post" action="<?= e(url($base . '/students')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="reset"><input type="hidden" name="student" value="<?= (int) $s['id'] ?>"><button class="btn btn--ghost btn--sm" type="submit">Restore</button></form></li><?php endforeach ?>
</ul>
<?php endif ?>
</div></section>

<?php elseif ($tab === 'results'): ?>
<?= $classPills('results') ?>
<?php if (!$selectedClass): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="table"></i></span><strong>No classes yet</strong><p>Results appear once the exam has classes and papers.</p></div></div>
<?php else: $cls = array_values(array_filter($classes, static fn ($c) => (int) $c['id'] === $selectedClass))[0] ?? ['name' => '']; $rp = $result['papers']; $rows = $result['rows']; ?>
<section class="module-card"><header class="module-card__header"><div><h2><?= e($exam['name']) ?> · <?= e($cls['name']) ?></h2><p><?= count($rows) ?> student(s) · <?= count($rp) ?> subject(s). "—" means no mark has been entered yet; it is not counted as zero.</p></div>
<div class="ec-actions">
<?php if (!$isTeacher && $rp && !$examPublished): $allPub = count(array_filter($rp, static fn ($p) => $p['visible'])) === count($rp); ?>
<?php if ($allPub): ?><form method="post" action="<?= e(url($base . '/unpublish-class')) ?>"><?= csrf_field() ?><input type="hidden" name="class" value="<?= $selectedClass ?>"><input type="hidden" name="back" value="results"><button class="btn btn--secondary btn--sm" type="submit">Unpublish class</button></form>
<?php else: ?><form method="post" action="<?= e(url($base . '/publish-class')) ?>" onsubmit="return confirm('Publish <?= e($cls['name']) ?> results to <?= count($rows) ?> student(s)?')"><?= csrf_field() ?><input type="hidden" name="class" value="<?= $selectedClass ?>"><input type="hidden" name="back" value="results"><label class="ec-check ec-check--inline"><input type="checkbox" name="allow_incomplete" value="1"><span>even if incomplete</span></label><button class="btn btn--primary btn--sm" type="submit"><i data-lucide="send"></i> Publish class</button></form><?php endif ?>
<?php endif ?>
<?php if (!$isTeacher && $rp): ?><a class="btn btn--secondary btn--sm" href="<?= e(url('/admin/reports/exams/' . $examId . '/classes/' . $selectedClass . '/gazette/pdf')) ?>" target="_blank" rel="noopener"><i data-lucide="file-down"></i> PDF</a><?php endif ?>
</div></header><div class="module-card__body">
<?php if (!$rp): ?><p class="ec-muted">No papers for this class yet.</p>
<?php elseif (!$rows): ?><p class="ec-muted">No students in this class are taking the exam.</p>
<?php else: ?>
<div class="ec-scroll"><table class="data-table no-table-tools ec-table ec-results">
<thead><tr><th class="ec-sticky">Student</th>
<?php foreach ($rp as $p): ?><th class="num"><a href="<?= e(url('/admin/exams/papers/' . (int) $p['id'])) ?>" title="Open <?= e($p['subject_name']) ?> marks"><?= e($p['subject_name']) ?></a><small><?= e($num($p['total_marks'])) ?><?= $p['visible'] ? ' · published' : '' ?></small></th><?php endforeach ?>
<?php if (!$isTeacher): ?><th class="num">Total</th><th class="num">%</th><?php if ($hasGrading): ?><th>Grade</th><?php endif ?><th>Status</th><?php endif ?></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td class="ec-sticky" data-label="Student"><?php if (!$isTeacher): ?><a class="ec-link" href="<?= e(url('/admin/exams/student?student=' . (int) $r['id'])) ?>"><?= e($r['student_name']) ?></a><?php else: ?><strong><?= e($r['student_name']) ?></strong><?php endif ?><small><?= e($r['registration_no']) ?><?= $r['source'] === 'added' ? ' · added' : '' ?></small></td>
<?php foreach ($rp as $p): $cell = $r['cells'][(int) $p['id']] ?? null; ?>
<td class="num" data-label="<?= e($p['subject_name']) ?>"><?php if ($cell === null): ?><span class="ec-muted">—</span><?php elseif ($cell['absent']): ?><span class="status-badge status-badge--absent">Absent</span><?php else: ?><?= e($num($cell['marks'])) ?><?php endif ?></td>
<?php endforeach ?>
<?php if (!$isTeacher): ?>
<td class="num" data-label="Total"><?= $r['max'] > 0 ? e($num($r['obtained'])) . ' / ' . e($num($r['max'])) : '—' ?></td>
<td class="num" data-label="%"><?= $r['percentage'] !== null ? e(number_format($r['percentage'], 2)) . '%' : '—' ?></td>
<?php if ($hasGrading): ?><td data-label="Grade"><?= e($r['grade'] ?? '—') ?></td><?php endif ?>
<td data-label="Status"><?php switch ($r['state']) {
    case 'complete': echo '<span class="status-badge status-badge--completed">Complete</span>'; break;
    case 'absent': echo '<span class="status-badge status-badge--absent">Absent</span>'; break;
    case 'not_marked': echo '<span class="status-badge status-badge--draft">Not marked</span>'; break;
    default: echo '<span class="status-badge status-badge--partial">' . (int) $r['missing'] . ' not marked</span>';
} ?></td>
<?php endif ?>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
</div></section>
<?php endif ?>
<?php endif ?>
</div></div></main>
