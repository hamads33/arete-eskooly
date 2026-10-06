<?php
$total = array_sum(array_column($attempt['answers'], 'marks'));
$mcqAnswers = array_values(array_filter($attempt['answers'], static fn($a) => $a['question_type'] === 'mcq'));
$manualAnswers = array_values(array_filter($attempt['answers'], static fn($a) => $a['question_type'] !== 'mcq'));
$mcqScore = array_sum(array_column($mcqAnswers, 'score'));
$mcqTotal = array_sum(array_column($mcqAnswers, 'marks'));
$manualPending = count(array_filter($manualAnswers, static fn($a) => $a['score'] === null));
?>
<main class="main"><div class="main__inner"><div class="module-page review-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= url('/admin/online-tests') ?>">Online Tests</a><i data-lucide="chevron-right"></i><a href="<?= url('/admin/online-tests/' . $attempt['test_id'] . '/submissions') ?>"><?= e($attempt['title']) ?></a><i data-lucide="chevron-right"></i><span><?= e($attempt['student_name']) ?></span></nav><h1><?= e($attempt['title']) ?></h1><p>Marking submission by <strong><?= e($attempt['student_name']) ?></strong> (<?= e($attempt['registration_no']) ?>)</p></div></header>

<div class="review-summary">
<?php $late = $attempt['submitted_at'] ? \App\Repositories\OnlineTestRepository::lateMinutes($attempt['started_at'], \App\Repositories\OnlineTestRepository::allowance($attempt, $attempt), $attempt['submitted_at']) : 0; ?>
<div class="review-summary__stat"><span>Submitted</span><strong><?= $attempt['submitted_at'] ? e(date('d M, h:i A', strtotime($attempt['submitted_at']))) : '—' ?></strong><?php if ($late): ?><small class="late-badge">Late +<?= $late ?> min</small><?php endif ?></div>
<?php if ($mcqTotal > 0): ?><div class="review-summary__stat"><span>MCQ (auto-graded)</span><strong><?= e((string) $mcqScore) ?> / <?= e((string) $mcqTotal) ?></strong></div><?php endif ?>
<div class="review-summary__stat"><span>Needs your marking</span><strong><?= $manualPending ?> of <?= count($manualAnswers) ?></strong></div>
<div class="review-summary__stat review-summary__stat--total"><span>Total score</span><strong data-review-total><?= e((string) ($attempt['total_score'] ?? '0')) ?></strong><small>of <?= e((string) $total) ?></small></div>
<?php if ($attempt['published_at']): ?><div class="review-summary__stat"><span>Published</span><strong class="is-published"><i data-lucide="check-circle-2"></i> To <?= e($attempt['visible_to']) ?></strong></div><?php endif ?>
</div>

<form method="post" enctype="multipart/form-data" action="<?= url('/admin/online-tests/submissions/' . $attempt['id']) ?>" data-review-form>
<?= csrf_field() ?>

<?php if ($attempt['submission_files'] && !empty($attempt['files_purged_at'])): ?><section class="module-card"><div class="module-card__body"><h2>Submitted solved paper</h2><p class="purged-note"><i data-lucide="archive"></i>The <?= count($attempt['submission_files']) ?> uploaded page<?= count($attempt['submission_files']) === 1 ? ' was' : 's were' ?> removed on <?= e(date('d M Y', strtotime($attempt['files_purged_at']))) ?> under the upload retention setting. Marks and feedback are kept.</p></div></section><?php elseif ($attempt['submission_files']): ?><section class="module-card"><div class="module-card__body"><h2>Submitted solved paper</h2><p>Tap a page to view it full screen (swipe or use arrow keys to move between pages), review the student's work, then enter marks for every question.</p><div class="review-gallery" data-review-gallery><?php foreach ($attempt['submission_files'] as $i => $file): $u = url('/attachments/test-papers/' . $attempt['id'] . '/' . $i); $isImg = preg_match('/\.(jpe?g|png)$/i', (string) $file); ?><a class="review-gallery__item" href="<?= $u ?>" target="_blank" rel="noopener" <?= $isImg ? 'data-gallery-image' : '' ?>><?php if ($isImg): ?><img src="<?= $u ?>" alt="Page <?= $i + 1 ?>" loading="lazy"><?php else: ?><span class="review-gallery__doc"><i data-lucide="file-text"></i>PDF</span><?php endif ?><span class="review-gallery__cap">Page <?= $i + 1 ?></span></a><?php endforeach ?></div></div></section><?php endif ?>

<div class="review-questions">
<?php foreach ($attempt['answers'] as $i => $a): $isMcq = $a['question_type'] === 'mcq'; ?>
<section class="review-question <?= $isMcq ? 'is-mcq' : 'is-manual' ?>">
<header class="review-question__head">
<span class="review-question__num"><?= $i + 1 ?></span>
<h3><?= e($a['question_text'] ?: 'See attached file') ?></h3>
<?php if ($isMcq): ?><span class="review-question__tag review-question__tag--auto"><i data-lucide="zap"></i>Auto-graded</span><?php else: ?><span class="review-question__tag<?= $a['score'] !== null ? ' review-question__tag--done' : ' review-question__tag--pending' ?>"><?= $a['score'] !== null ? 'Marked' : 'Needs marking' ?></span><?php endif ?>
</header>
<div class="review-question__body">
<?php if ($a['attachment_path']): ?><a class="btn btn--secondary btn--sm" target="_blank" rel="noopener" href="<?= url('/attachments/test-questions/' . $a['id']) ?>"><i data-lucide="file-text"></i>Open question file</a><?php endif ?>

<?php if ($isMcq): $opts = json_decode((string) $a['options_json'], true) ?: []; $correct = (int) $a['correct_option']; $selected = $a['selected_option'] !== null ? (int) $a['selected_option'] : null; ?>
<ul class="review-mcq-options">
<?php foreach ($opts as $idx => $opt): $isSelected = $selected === $idx; $isCorrect = $correct === $idx; ?>
<li class="<?= $isCorrect ? 'is-correct' : '' ?><?= $isSelected && !$isCorrect ? ' is-wrong' : '' ?>"><span class="review-mcq-options__letter"><?= chr(65 + $idx) ?></span><span><?= e($opt) ?></span><?php if ($isSelected): ?><span class="review-mcq-options__picked"><i data-lucide="<?= $isCorrect ? 'check' : 'x' ?>"></i><?= $isCorrect ? 'Student\'s answer — correct' : 'Student\'s answer' ?></span><?php elseif ($isCorrect): ?><span class="review-mcq-options__picked review-mcq-options__picked--muted"><i data-lucide="check"></i>Correct answer</span><?php endif ?></li>
<?php endforeach ?>
<?php if ($selected === null): ?><li class="is-unanswered"><i data-lucide="circle-slash"></i>No answer given</li><?php endif ?>
</ul>
<div class="review-question__score is-auto"><span>Awarded</span><strong><?= e((string) $a['score']) ?> / <?= e((string) $a['marks']) ?></strong><small>Calculated automatically — MCQ answers are not hand-marked.</small></div>

<?php else: ?>
<div class="review-answer-text"><?= nl2br(e($a['answer_text'] ?: 'No typed answer — see the uploaded file or attached paper.')) ?></div>
<?php if ($a['answer_attachments'] && !empty($attempt['files_purged_at'])): ?><p class="purged-note">Uploaded answer pages were removed under the upload retention setting.</p><?php elseif ($a['answer_attachments']): ?><div class="review-gallery review-gallery--sm" data-review-gallery><?php foreach ($a['answer_attachments'] as $pi => $path): $u = url('/attachments/test-answers/' . $a['answer_id'] . '/' . $pi); $isImg = preg_match('/\.(jpe?g|png)$/i', (string) $path); ?><a class="review-gallery__item" href="<?= $u ?>" target="_blank" rel="noopener" <?= $isImg ? 'data-gallery-image' : '' ?>><?php if ($isImg): ?><img src="<?= $u ?>" alt="Page <?= $pi + 1 ?>" loading="lazy"><?php else: ?><span class="review-gallery__doc"><i data-lucide="file-text"></i>PDF</span><?php endif ?><span class="review-gallery__cap">Page <?= $pi + 1 ?></span></a><?php endforeach ?></div><?php endif ?>
<div class="module-form-grid review-mark-grid">
<div class="module-field"><label>Score <small>(max <?= e($a['marks']) ?>)</small></label><input class="module-input" type="number" min="0" max="<?= e($a['marks']) ?>" step="0.01" name="score[<?= $a['id'] ?>]" value="<?= e($a['score'] ?? '') ?>" placeholder="0" data-review-score data-max="<?= e($a['marks']) ?>" required></div>
<div class="module-field field--wide"><label>Feedback <small>(optional, visible to the student once published)</small></label><input class="module-input" name="feedback[<?= $a['id'] ?>]" value="<?= e($a['feedback'] ?? '') ?>" placeholder="e.g. Good working, minor calculation slip in step 2"></div>
</div>
<?php endif ?>
</div>
</section>
<?php endforeach ?>
</div>

<section class="module-card teacher-comment-card"><div class="module-card__body">
<h2><label for="teacherComment">Comments for the student <i>(optional)</i></label></h2>
<p>Overall remarks on this paper — what went well and what to work on. The student<?= ($attempt['visible_to'] ?? 'both') !== 'student' ? ' and parent' : '' ?> will see it with the result<?= $attempt['published_at'] ? ' (already published, so a change shows straight away)' : ' once it is published' ?>. You can come back and change it any time.</p>
<textarea class="module-textarea" id="teacherComment" name="teacher_comment" rows="4" maxlength="2000" placeholder="e.g. Well done on the numericals. Revise chapter 2 definitions and show your working clearly."><?= e((string) ($attempt['teacher_comment'] ?? '')) ?></textarea>
<?php if (!empty($attempt['teacher_comment_at'])): ?><small class="teacher-comment-card__when">Last updated <?= e(date('d M Y, h:i A', strtotime($attempt['teacher_comment_at']))) ?></small><?php endif ?>
</div></section>

<section class="module-card"><div class="module-card__body">
<h2>Marked paper <i>(optional)</i></h2>
<p>Upload the annotated / marked version of the student's paper. It will be visible to the student once you publish the result.</p>
<?php if ($attempt['marked_files']): ?><div class="table-actions"><?php foreach ($attempt['marked_files'] as $i => $file): ?><a class="btn btn--secondary" target="_blank" rel="noopener" href="<?= url('/attachments/test-marked/' . $attempt['id'] . '/' . $i) ?>"><i data-lucide="file-check"></i>Open marked paper <?= ($i + 1) ?></a><?php endforeach ?></div><?php endif ?>
<label class="student-upload"><i data-lucide="upload"></i><span><strong>Add marked paper file(s)</strong><small>PDF, JPG or PNG · up to 5 MB each</small></span><input type="file" name="marked_paper[]" accept=".pdf,.jpg,.jpeg,.png" multiple></label>
</div></section>

<footer class="review-footer">
<div class="review-footer__total"><span>Total once saved</span><strong data-review-total-footer><?= e((string) ($attempt['total_score'] ?? '0')) ?></strong><small>of <?= e((string) $total) ?></small></div>
<label class="checkbox-row review-footer__publish"><input type="checkbox" name="publish" value="1"<?= $attempt['published_at'] ? ' checked' : '' ?>><span><strong>Publish this result now</strong><small>Otherwise it stays hidden from the student and parent until published from the submissions list.</small></span></label>
<select class="module-select review-footer__visible" name="visible_to" aria-label="Visible to"><option value="both"<?= ($attempt['visible_to'] ?? 'both') === 'both' ? ' selected' : '' ?>>Students &amp; parents</option><option value="student"<?= ($attempt['visible_to'] ?? '') === 'student' ? ' selected' : '' ?>>Students only</option><option value="parent"<?= ($attempt['visible_to'] ?? '') === 'parent' ? ' selected' : '' ?>>Parents only</option></select>
<button class="btn btn--primary" type="submit"><i data-lucide="save"></i>Save marks</button>
</footer>
</form>
</div></div></main>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('[data-review-form]');
  if (!form) return;
  var autoTotal = <?= json_encode($mcqScore) ?>;
  var totals = document.querySelectorAll('[data-review-total], [data-review-total-footer]');
  var recalc = function () {
    var sum = autoTotal;
    form.querySelectorAll('[data-review-score]').forEach(function (input) {
      var max = parseFloat(input.dataset.max || '0');
      var val = parseFloat(input.value);
      if (isNaN(val)) val = 0;
      if (val > max) { val = max; input.value = max; }
      if (val < 0) { val = 0; input.value = 0; }
      sum += val;
    });
    totals.forEach(function (el) { el.textContent = (Math.round(sum * 100) / 100).toString(); });
  };
  form.querySelectorAll('[data-review-score]').forEach(function (input) { input.addEventListener('input', recalc); });
  recalc();
});
</script>
