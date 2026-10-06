<?php
$locked = (bool) $test['has_attempts'];
$uploadOnly = ($test['answer_mode'] ?? 'mixed') === 'upload_only';
$dt = static fn(?string $v): string => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$max = \App\Repositories\OnlineTestRepository::HARD_MAX_PAGES;
?>
<main class="main"><div class="main__inner"><div class="module-page online-test-page test-edit-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= url('/admin/online-tests') ?>">Online Tests</a><i data-lucide="chevron-right"></i><span>Edit</span></nav><h1>Edit test</h1><p><?= e($test['title']) ?> · <?= e($test['class_name']) ?><?= $test['subject_name'] ? ' · ' . e($test['subject_name']) : '' ?> · <span class="status-badge"><?= e(ucfirst($test['status'])) ?></span></p></div>
<div class="table-actions"><a class="btn btn--secondary" href="<?= url('/admin/online-tests/' . $test['id'] . '/submissions') ?>"><i data-lucide="clipboard-check"></i>Submissions &amp; extra time</a></div></header>

<?php if ($locked): ?><p class="test-edit-note"><i data-lucide="info"></i><span><strong>Students have already opened this test<?= $test['submitted_count'] ? ' (' . (int) $test['submitted_count'] . ' submitted)' : '' ?>.</strong> You can still change the details, time, page limit, question wording, marks, files and MCQ answers. If you change marks or a correct answer, submitted papers are re-scored automatically. Class, subject, answer mode and the number of questions stay as they are so nobody's paper changes under them.</span></p><?php endif ?>

<form class="test-builder" method="post" enctype="multipart/form-data" action="<?= url('/admin/online-tests/' . $test['id'] . '/edit') ?>" data-test-edit><?= csrf_field() ?>
<section class="builder-step"><div class="builder-step__number">1</div><div class="builder-step__content"><header><div><span>TEST DETAILS</span><h2>Details &amp; time</h2><p>Changing the time allowed applies to every student, including anyone writing the test right now.</p></div></header><div class="builder-fields">
<label class="builder-field builder-field--wide"><span>Test title <b>*</b></span><input class="module-input" name="title" required maxlength="160" value="<?= e($test['title']) ?>"></label>
<?php if ($locked): ?>
<input type="hidden" name="class_id" value="<?= (int) $test['class_id'] ?>"><input type="hidden" name="subject_id" value="<?= (int) $test['subject_id'] ?>"><input type="hidden" name="answer_mode" value="<?= e($test['answer_mode']) ?>">
<div class="builder-field"><span>Class</span><div class="test-edit-fixed"><?= e($test['class_name']) ?></div></div>
<div class="builder-field"><span>Subject</span><div class="test-edit-fixed"><?= e($test['subject_name'] ?: '—') ?></div></div>
<?php else: ?>
<label class="builder-field"><span>Class <b>*</b></span><select class="module-select" id="onlineTestClass" name="class_id" required><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"<?= (int) $c['id'] === (int) $test['class_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label>
<label class="builder-field"><span>Subject <b>*</b></span><select class="module-select" name="subject_id" data-subject-filter="#onlineTestClass" required><?php foreach ($classes as $c): foreach ($c['subjects'] ?? [] as $s): ?><option value="<?= $s['id'] ?>" data-class="<?= $c['id'] ?>"<?= (int) $s['id'] === (int) $test['subject_id'] && (int) $c['id'] === (int) $test['class_id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; endforeach ?></select></label>
<input type="hidden" name="answer_mode" value="<?= e($test['answer_mode']) ?>">
<?php endif ?>
<label class="builder-field"><span>Time allowed <b>*</b></span><div class="input-suffix"><input class="module-input" type="number" name="duration_minutes" min="1" max="1440" value="<?= (int) $test['duration_minutes'] ?>" required><small>minutes</small></div></label>
<label class="builder-field"><span>Pages a student can upload</span><div class="input-suffix"><input class="module-input" type="number" name="max_pages" min="1" max="<?= $max ?>" value="<?= (int) ($test['max_pages'] ?: $uploadSettings['max_pages']) ?>"><small>pages</small></div></label>
<label class="builder-field"><span>Opens at <i>optional</i></span><input class="module-input" type="datetime-local" name="opens_at" value="<?= e($dt($test['opens_at'])) ?>"></label>
<label class="builder-field"><span>Closes at <i>optional</i></span><input class="module-input" type="datetime-local" name="closes_at" value="<?= e($dt($test['closes_at'])) ?>"></label>
<label class="builder-field builder-field--wide"><span>Instructions for students <i>optional</i></span><textarea class="module-textarea" name="instructions" rows="3"><?= e((string) $test['instructions']) ?></textarea></label>
</div></div></section>

<section class="builder-step"><div class="builder-step__number">2</div><div class="builder-step__content"><header><div><span>QUESTION PAPER</span><h2><?= $uploadOnly ? 'Question paper' : 'Questions' ?></h2><p><?= $uploadOnly ? 'Replace the paper file or change its total marks.' : 'Edit the wording, marks, files and MCQ answers.' ?></p></div></header>
<div class="test-edit-questions">
<?php foreach ($test['questions'] as $n => $q): $qid = (int) $q['id']; $mcq = $q['question_type'] === 'mcq'; ?>
<article class="test-edit-question" data-edit-question>
<header><span class="question-number"><span>Q</span><strong><?= $n + 1 ?></strong></span><small><?= $uploadOnly ? 'Paper' : ($mcq ? 'Multiple choice' : 'Written') ?></small>
<?php if (!$locked && count($test['questions']) > 1): ?><label class="test-edit-remove"><input type="checkbox" name="remove_question[]" value="<?= $qid ?>"> Remove this question</label><?php endif ?></header>
<?php if (!$uploadOnly): ?><label class="builder-field builder-field--wide"><span>Question</span><textarea class="module-textarea" name="question[<?= $qid ?>][text]" rows="3"><?= e((string) $q['question_text']) ?></textarea></label><?php else: ?><input type="hidden" name="question[<?= $qid ?>][text]" value="<?= e((string) $q['question_text']) ?>"><?php endif ?>
<div class="test-edit-question__row">
<label class="builder-field"><span><?= $uploadOnly ? 'Total marks' : 'Marks' ?> <b>*</b></span><input class="module-input" type="number" name="question[<?= $qid ?>][marks]" min="0.01" step="0.01" value="<?= e((string) (float) $q['marks']) ?>" required></label>
<div class="builder-field test-edit-file"><span><?= $uploadOnly ? 'Question paper file' : 'Question file' ?></span>
<?php if ($q['attachment_path']): ?><a class="btn btn--secondary btn--sm" target="_blank" rel="noopener" href="<?= url('/attachments/test-questions/' . $qid) ?>"><i data-lucide="eye"></i>Open current file</a><?php if (!$uploadOnly): ?><label class="test-edit-check"><input type="checkbox" name="question[<?= $qid ?>][remove_attachment]" value="1"> Remove file</label><?php endif ?><?php endif ?>
<input class="module-input" type="file" name="question_file[<?= $qid ?>]" accept=".pdf,.jpg,.jpeg,.png" aria-label="<?= $q['attachment_path'] ? 'Replace' : 'Add' ?> the file for question <?= $n + 1 ?>"><small><?= $q['attachment_path'] ? 'Choose a file only to replace it' : 'PDF, JPG or PNG · optional' ?></small></div>
</div>
<?php if ($mcq): $opts = $q['options']; $slots = max(4, count($opts)); ?>
<fieldset class="test-edit-options"><legend>Answer choices — select the correct one</legend>
<?php for ($o = 0; $o < $slots; $o++): ?><label class="mcq-option"><input type="radio" name="question[<?= $qid ?>][correct]" value="<?= $o ?>"<?= (int) $q['correct_option'] === $o ? ' checked' : '' ?> aria-label="Option <?= chr(65 + $o) ?> is correct"><span><?= chr(65 + $o) ?></span><input class="module-input" type="text" name="question[<?= $qid ?>][options][]" value="<?= e((string) ($opts[$o] ?? '')) ?>" placeholder="Option <?= chr(65 + $o) ?>"<?= $locked && isset($opts[$o]) ? ' required' : '' ?>></label><?php endfor ?>
</fieldset>
<?php endif ?>
</article>
<?php endforeach ?>
</div>
<?php if (!$locked && !$uploadOnly): ?>
<div data-new-questions></div>
<button class="add-question-button" type="button" data-add-edit-question><i data-lucide="plus"></i><span><strong>Add a question</strong><small>Written answer or multiple choice</small></span></button>
<template id="newQuestionTemplate"><article class="test-edit-question is-new" data-new-question><header><span class="question-number"><span>Q</span><strong>+</strong></span><select class="module-select" name="new_question_type[]" data-new-type aria-label="Question type"><option value="written">Written</option><option value="mcq">Multiple choice</option></select><button class="btn btn--secondary btn--sm" type="button" data-discard-new>Discard</button></header>
<label class="builder-field builder-field--wide"><span>Question</span><textarea class="module-textarea" name="new_question_text[]" rows="3" placeholder="Type the question..."></textarea></label>
<div class="test-edit-question__row"><label class="builder-field"><span>Marks <b>*</b></span><input class="module-input" type="number" name="new_question_marks[]" min="0.01" step="0.01" value="1"></label><div class="builder-field test-edit-file"><span>Question file</span><input class="module-input" type="file" data-new-file accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG or PNG · optional</small></div></div>
<fieldset class="test-edit-options" data-new-options hidden><legend>Answer choices — select the correct one</legend><?php for ($o = 0; $o < 4; $o++): ?><label class="mcq-option"><input type="radio" data-new-correct value="<?= $o ?>"<?= $o === 0 ? ' checked' : '' ?> aria-label="Option <?= chr(65 + $o) ?> is correct"><span><?= chr(65 + $o) ?></span><input class="module-input" type="text" data-new-option placeholder="Option <?= chr(65 + $o) ?>"></label><?php endfor ?></fieldset>
</article></template>
<?php endif ?>
</div></section>

<section class="builder-publish"><div><i data-lucide="save"></i><span><strong>Save your changes</strong><small><?= $test['status'] === 'published' ? 'The test is live — students see the changes straight away.' : 'Publish it from the tests list when ready.' ?></small></span></div><button class="btn btn--primary" type="submit"><i data-lucide="check"></i>Save changes</button></section>
</form>
</div></div></main>
