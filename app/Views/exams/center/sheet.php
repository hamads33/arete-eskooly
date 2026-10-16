<?php
$pid = (int) $paper['id'];
$total = (float) $paper['total_marks'];
$num = static fn ($v): string => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
$entered = count(array_filter($rows, static fn ($r) => $r['entered']));
$absent = count(array_filter($rows, static fn ($r) => $r['is_absent']));
$examUrl = '/admin/exams/' . (int) $paper['exam_id'];
?>
<main class="main"><div class="main__inner"><div class="module-page exam-center">
<header class="module-page__header"><div>
<nav class="breadcrumb"><a href="<?= e(url('/admin/exams')) ?>">Exams</a><i data-lucide="chevron-right"></i><a href="<?= e(url($examUrl . ($isTeacher ? '' : '?tab=papers&class=' . (int) $paper['class_id']))) ?>"><?= e($paper['exam_name']) ?></a><i data-lucide="chevron-right"></i><span><?= e($paper['class_name']) ?></span><i data-lucide="chevron-right"></i><span><?= e($paper['subject_name']) ?></span></nav>
<h1><?= e($paper['subject_name']) ?> marks</h1>
</div></header>

<section class="ec-context">
<div><span>Exam</span><strong><?= e($paper['exam_name']) ?></strong></div>
<div><span>Class</span><strong><?= e($paper['class_name']) ?></strong></div>
<div><span>Subject</span><strong><?= e($paper['subject_name']) ?></strong></div>
<div><span>Total marks</span><strong><?= e($num($total)) ?></strong></div>
<div><span>Marked</span><strong data-ec-marked><?= $entered ?>/<?= count($rows) ?></strong></div>
<div><span>Status</span><strong><?php if ($paper['exam_status'] === 'locked'): ?><span class="status-badge status-badge--completed">Locked</span><?php elseif ($paper['visible']): ?><span class="status-badge status-badge--published">Published</span><?php elseif ($paper['marks_status'] === 'submitted'): ?><span class="status-badge status-badge--pending">Awaiting review</span><?php else: ?><span class="status-badge status-badge--draft">Draft</span><?php endif ?></strong></div>
</section>

<?php if (!$canEdit): ?>
<div class="ec-note"><i data-lucide="info"></i><span><?php if ($paper['exam_status'] === 'locked'): ?>This exam is locked, so these marks cannot be changed.<?php elseif ($isTeacher): ?>These marks have been <?= $paper['visible'] ? 'published' : 'sent for review' ?>, so they are read-only. Ask the admin to reopen the paper if something needs correcting.<?php endif ?></span></div>
<?php endif ?>

<?php if (!$rows): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users"></i></span><strong>No students</strong><p>No active students are taking this paper.</p></div></div></div></section>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/marks')) ?>" class="ec-sheet" data-ec-sheet data-total="<?= e($num($total)) ?>"><?= csrf_field() ?>
<div class="ec-toolbar">
<input class="module-input" type="search" placeholder="Search name or registration" data-ec-filter="#ec-sheet-table" aria-label="Search students">
<label class="ec-check ec-check--inline"><input type="checkbox" data-ec-unmarked><span>Only unmarked</span></label>
<?php if ($canEdit): ?><button class="btn btn--ghost btn--sm" type="button" data-ec-absent-blank title="Mark every student without marks as absent">Blank → Absent</button><?php endif ?>
<span class="ec-muted ec-hint"><?= $canEdit ? 'Enter moves to the next student. You can paste a column of marks from Excel.' : '' ?></span>
</div>
<div class="ec-scroll"><table class="data-table no-table-tools ec-table ec-sheet-table" id="ec-sheet-table">
<thead><tr><th>Registration</th><th class="ec-sticky">Student</th><th class="num">Marks / <?= e($num($total)) ?></th><th>Absent</th><th class="num">%</th><th>Remarks</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): $sid = (int) $r['id']; $pct = !$r['is_absent'] && $r['obtained_marks'] !== null && $total > 0 ? round((float) $r['obtained_marks'] / $total * 100, 1) : null; ?>
<tr data-ec-row="<?= e(mb_strtolower($r['student_name'] . ' ' . $r['registration_no'])) ?>" class="<?= $r['entered'] ? '' : 'is-unmarked' ?>">
<td data-label="Registration" class="ec-reg"><?= e($r['registration_no']) ?></td>
<td class="ec-sticky" data-label="Student"><strong><?= e($r['student_name']) ?></strong><?= $r['source'] === 'added' ? '<small>added · own class ' . e($r['own_class_name'] ?? '—') . '</small>' : '' ?></td>
<td class="num" data-label="Marks"><input class="module-input ec-mark" type="text" inputmode="decimal" autocomplete="off" name="marks[<?= $sid ?>]" value="<?= $r['is_absent'] ? '' : e($num($r['obtained_marks'])) ?>" aria-label="Marks for <?= e($r['student_name']) ?>" <?= $r['is_absent'] || !$canEdit ? 'disabled' : '' ?>><?php if ($r['is_absent']): ?><input type="hidden" name="marks[<?= $sid ?>]" value="" data-ec-absent-shadow><?php endif ?></td>
<td data-label="Absent"><label class="ec-check ec-check--abs"><input type="checkbox" name="absent[<?= $sid ?>]" value="1" data-ec-absent <?= $r['is_absent'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>><span>ABS</span></label></td>
<td class="num" data-label="%" data-ec-pct><?= $r['is_absent'] ? '—' : ($pct !== null ? e((string) $pct) . '%' : '') ?></td>
<td data-label="Remarks"><input class="module-input ec-remark" type="text" maxlength="255" name="remarks[<?= $sid ?>]" value="<?= e((string) $r['remarks']) ?>" aria-label="Remarks for <?= e($r['student_name']) ?>" <?= $canEdit ? '' : 'disabled' ?>></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php if ($canEdit): ?>
<div class="ec-savebar">
<span class="ec-muted" data-ec-status><?= $entered ?> of <?= count($rows) ?> marked<?= $absent ? ' · ' . $absent . ' absent' : '' ?></span>
<span class="ec-error" data-ec-error hidden></span>
<button class="btn <?= $isTeacher ? 'btn--secondary' : 'btn--primary' ?>" type="submit" name="after" value="save"><?= $isTeacher ? 'Save draft' : 'Save marks' ?></button>
<?php if ($isTeacher): ?><button class="btn btn--primary" type="submit" name="after" value="submit" data-ec-final><?= $canPublish ? 'Save &amp; publish' : 'Save &amp; send for review' ?></button><?php endif ?>
</div>
<?php endif ?>
</form>
<?php if (!$isTeacher && !in_array($paper['exam_status'], ['published', 'locked'], true)): ?>
<div class="ec-after">
<?php if ($paper['visible']): ?>
<form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/unpublish')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="sheet"><button class="btn btn--secondary btn--sm" type="submit">Unpublish this paper</button></form>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/publish')) ?>" onsubmit="return confirm('Publish this paper to <?= count($rows) ?> student(s)? Save any changes first.')"><?= csrf_field() ?><input type="hidden" name="back" value="sheet"><label class="ec-check ec-check--inline"><input type="checkbox" name="allow_incomplete" value="1"><span>even if incomplete</span></label><button class="btn btn--secondary btn--sm" type="submit"><i data-lucide="send"></i> Publish this paper</button></form>
<?php endif ?>
<?php if ($paper['marks_status'] === 'submitted' || $paper['visible']): ?><form method="post" action="<?= e(url('/admin/exams/papers/' . $pid . '/reopen')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="sheet"><button class="btn btn--ghost btn--sm" type="submit">Reopen for the teacher</button></form><?php endif ?>
</div>
<?php endif ?>
<?php endif ?>
</div></div></main>
