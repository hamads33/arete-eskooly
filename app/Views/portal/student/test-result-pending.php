<?php
$submitted = !in_array($attempt['status'], ['submitted', 'marked'], true);
$marked = $attempt['status'] === 'marked';
if ($submitted) { $icon = 'clock'; $title = 'Still in progress'; $body = 'You have not submitted this test yet.'; }
elseif (!$marked) { $icon = 'hourglass'; $title = 'Being marked'; $body = 'Your teacher has received your submission and is marking it. Check back soon.'; }
else { $icon = 'lock'; $title = 'Result not published yet'; $body = 'Your test has been marked, but your teacher hasn\'t released the result yet. You\'ll be able to see your marks here once it\'s published.'; }
?>
<main class="main"><div class="main__inner"><div class="portal-page">
<div class="test-result-pending">
<span class="test-result-pending__icon"><i data-lucide="<?= e($icon) ?>"></i></span>
<h1><?= e($title) ?></h1>
<p><?= e($body) ?></p>
<a class="btn btn--primary" href="<?= url('/portal/student/online-tests') ?>"><i data-lucide="arrow-left"></i>Back to Online Tests</a>
</div>
</div></div></main>
<style>
.test-result-pending{max-width:420px;margin:60px auto;padding:36px 28px;text-align:center;border:1px solid var(--border);border-radius:16px;background:var(--surface)}
.test-result-pending__icon{display:grid;place-items:center;width:56px;height:56px;margin:0 auto 16px;border-radius:50%;background:var(--primary-50);color:var(--primary)}
.test-result-pending__icon svg{width:26px;height:26px}
.test-result-pending h1{margin:0 0 10px;font-size:19px}
.test-result-pending p{margin:0 0 22px;color:var(--text-secondary);font-size:13.5px;line-height:1.6}
</style>
