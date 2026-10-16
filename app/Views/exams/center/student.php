<?php
$num = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
$date = static fn (string $d): string => date('j M Y', (int) strtotime($d));
?>
<main class="main"><div class="main__inner"><div class="module-page exam-center">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/admin/exams')) ?>">Exams</a><i data-lucide="chevron-right"></i><span>Student Result</span></nav><h1>Student Result</h1><p>Find a student by name, registration number or father's name to see every exam result and download result cards.</p></div></header>

<form class="module-card" method="get" action="<?= e(url('/admin/exams/student')) ?>"><div class="module-card__body">
<div class="ec-search ec-search--student">
<input class="module-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Student name, registration no. or father's name" aria-label="Search students" autofocus>
<select class="module-input" name="class" aria-label="Class"><option value="0">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $selectedClass === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select>
<button class="btn btn--primary" type="submit"><i data-lucide="search"></i> Find</button>
</div>
<?php if (($q !== '' || $selectedClass) && !$student): ?>
<?php if (!$matches): ?><p class="ec-muted" style="margin-top:12px">No student found.</p><?php else: ?>
<div class="ec-pick ec-pick--results">
<?php foreach ($matches as $m): ?>
<a class="ec-paper" href="<?= e(url('/admin/exams/student?student=' . (int) $m['id'] . '&q=' . rawurlencode($q) . '&class=' . $selectedClass)) ?>"><div class="ec-paper__main"><strong><?= e($m['student_name']) ?></strong><span class="ec-paper__meta"><?= e($m['registration_no']) ?> · <?= e($m['class_name'] ?? '—') ?><?= $m['status'] !== 'active' ? ' · ' . e(ucfirst($m['status'])) : '' ?></span></div><span class="btn btn--secondary btn--sm">View results</span></a>
<?php endforeach ?>
</div>
<?php endif ?>
<?php endif ?>
</div></form>

<?php if ($student): ?>
<section class="module-card"><header class="module-card__header"><div><h2><?= e($student['student_name']) ?></h2><p><?= e($student['registration_no']) ?> · <?= e($student['class_name'] ?? '—') ?><?= !empty($student['father_name']) ? ' · Father: ' . e($student['father_name']) : '' ?></p></div>
<div class="ec-actions"><a class="btn btn--secondary btn--sm" href="<?= e(url('/admin/reports/progress?student=' . (int) $student['id'])) ?>"><i data-lucide="line-chart"></i> Full progress report</a><?php if ($q !== '' || $selectedClass): ?><a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/exams/student?q=' . rawurlencode($q) . '&class=' . $selectedClass)) ?>">Back to search</a><?php endif ?></div></header>
<div class="module-card__body">
<?php if (!$results): ?>
<div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="file-check-2"></i></span><strong>No exam results yet</strong><p>Marks entered for this student in any exam will appear here.</p></div></div>
<?php else: ?>
<div class="ec-student-results">
<?php foreach ($results as $x): $r = $x['result']; $subjects = $r['subjects']; $allPublished = $x['published_count'] >= count($subjects); ?>
<article class="ec-result-card">
<header><div><h3><?= e($x['name']) ?></h3><small><?= e($date($x['start_date'])) ?> – <?= e($date($x['end_date'])) ?></small></div>
<div class="ec-result-card__score"><strong><?= e(number_format((float) $r['percentage'], 2)) ?>%</strong><small><?= e($num($r['obtained'])) ?> / <?= e($num($r['total'])) ?><?php if ($hasGrading && !empty($r['grade']['grade'])): ?> · Grade <?= e($r['grade']['grade']) ?><?php endif ?></small></div></header>
<div class="ec-scroll"><table class="data-table no-table-tools ec-mini"><thead><tr><th>Subject</th><th class="num">Marks</th><th class="num">Total</th><th class="num">%</th></tr></thead><tbody>
<?php foreach ($subjects as $s): $abs = $s['obtained_marks'] === 'Absent'; ?>
<tr><td><?= e($s['subject']) ?></td><td class="num"><?= $abs ? '<span class="status-badge status-badge--absent">Absent</span>' : e($num($s['obtained_marks'])) ?></td><td class="num"><?= e($num($s['total_marks'])) ?></td><td class="num"><?= $abs ? '—' : e((float) $s['total_marks'] > 0 ? number_format((float) $s['obtained_marks'] / (float) $s['total_marks'] * 100, 1) . '%' : '—') ?></td></tr>
<?php endforeach ?>
</tbody></table></div>
<footer>
<?php if ($allPublished): ?><span class="status-badge status-badge--published">Published</span><?php elseif ($x['published_count'] > 0): ?><span class="status-badge status-badge--partial"><?= (int) $x['published_count'] ?>/<?= count($subjects) ?> published</span><?php else: ?><span class="status-badge status-badge--draft">Not published — students can't see it yet</span><?php endif ?>
<span class="ec-actions">
<a class="btn btn--primary btn--sm" href="<?= e(url('/admin/reports/report-card/' . (int) $student['id'] . '/pdf?exam=' . (int) $x['id'])) ?>" target="_blank" rel="noopener"><i data-lucide="file-down"></i> Result card PDF</a>
<a class="btn btn--secondary btn--sm" href="<?= e(url('/admin/exams/' . (int) $x['id'] . '?tab=results&class=' . (int) $student['class_id'])) ?>">Class result</a>
</span>
</footer>
</article>
<?php endforeach ?>
</div>
<?php endif ?>
</div></section>
<?php endif ?>
</div></div></main>
