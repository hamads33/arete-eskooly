<?php
$total = count($submissions);
$markedUnpublished = count(array_filter($submissions, static fn($s) => $s['status'] === 'marked' && $s['published_at'] === null));
$published = count(array_filter($submissions, static fn($s) => $s['published_at'] !== null));
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><span class="module-eyebrow">ONLINE TEST</span><h1><?=e($test['title'])?></h1><p><?=e($test['class_name'])?><?=$test['subject_name']?' · '.e($test['subject_name']):''?> · Open submitted papers, check answers and save marks for each student.</p></div><div class="table-actions"><a class="btn btn--secondary" href="<?=url('/admin/online-tests/'.$testId.'/edit')?>"><i data-lucide="pencil"></i>Edit test</a><a class="btn btn--secondary" href="<?=url('/admin/online-tests')?>">Back to tests</a></div></header>

<section class="module-card extra-time-card"><div class="module-card__body">
<div class="extra-time-card__now"><i data-lucide="timer"></i><span><strong><?=(int)$test['duration_minutes']?> minutes</strong> allowed per student<?=$test['closes_at']?' · closes '.e(date('d M, h:i A',strtotime($test['closes_at']))):''?></span></div>
<form class="extra-time-form" method="post" action="<?=url('/admin/online-tests/'.$testId.'/extend')?>" onsubmit="return confirm('Give the whole class '+this.minutes.value+' extra minutes?')"><?=csrf_field()?>
<label class="extra-time-form__minutes"><span>Extra time for the whole class</span><div class="input-suffix"><input class="module-input" type="number" name="minutes" min="1" max="600" value="15" required><small>min</small></div></label>
<?php if($test['closes_at']):?><label class="test-edit-check"><input type="checkbox" name="move_closing" value="1" checked> Also move the closing time</label><?php endif?>
<button class="btn btn--primary" type="submit"><i data-lucide="alarm-clock-plus"></i>Give everyone extra time</button>
</form>
<p class="extra-time-card__hint">Applies to students writing the test now and to anyone who has not started. To help just one student, use <strong>+ time</strong> on their row below.</p>
</div></section>

<?php if($markedUnpublished>0):?>
<section class="module-card"><div class="module-card__body publish-bar"><div><strong><?=$markedUnpublished?></strong> marked result<?=$markedUnpublished===1?'':'s'?> ready to publish.</div>
<form method="post" action="<?=url('/admin/online-tests/'.$testId.'/publish')?>" class="publish-bar__form" onsubmit="return confirm('Publish all marked results for this test now?')">
<?=csrf_field()?>
<select class="module-select" name="visible_to"><option value="both">Students &amp; parents</option><option value="student">Students only</option><option value="parent">Parents only</option></select>
<button class="btn btn--primary">Publish marked results</button>
</form></div></section>
<?php endif?>

<section class="module-card"><div class="module-card__body"><div class="table-scroll"><table class="data-table test-attempt-table"><thead><tr><th>Registration</th><th>Student</th><th>Started</th><th>Submitted</th><th>Status</th><th>Score</th><th>Published</th><th>Action</th></tr></thead><tbody>
<?php foreach($submissions as$s):$ready=in_array($s['status'],['submitted','marked'],true);?>
<tr>
<td data-label="Registration"><?=e($s['registration_no'])?></td>
<td data-label="Student"><strong><?=e($s['student_name'])?></strong></td>
<td data-label="Started"><?=e($s['started_at']?:'—')?><?php if((int)($s['extra_minutes']??0)>0):?><span class="extra-badge">+<?=(int)$s['extra_minutes']?> min</span><?php endif?></td>
<td data-label="Submitted"><?=e($s['submitted_at']?:'Not submitted yet')?><?php if($s['submitted_at']&&($late=\App\Repositories\OnlineTestRepository::lateMinutes($s['started_at'],\App\Repositories\OnlineTestRepository::allowance($test,$s),$s['submitted_at']))):?><span class="late-badge" title="Submitted after the <?=(int)$test['duration_minutes']?>-minute time allowance">Late +<?=$late?> min</span><?php endif?></td>
<td data-label="Status"><span class="status-badge"><?=e(ucfirst(str_replace('_',' ',$s['status'])))?></span></td>
<td data-label="Score"><?=e($s['total_score']??'Pending')?></td>
<td data-label="Published"><?php if($s['published_at']):?><span class="status-badge status-badge--active"><?=e(ucfirst($s['visible_to']))?></span><form method="post" action="<?=url('/admin/online-tests/submissions/'.$s['id'].'/withdraw')?>" onsubmit="return confirm('Withdraw this result? The student and parent will no longer be able to see it.')"><?=csrf_field()?><button class="link-button" type="submit">Withdraw</button></form><?php else:?><span class="status-badge">No</span><?php endif?></td>
<td data-label="Action"><?php if($ready):?><a class="btn btn--primary" href="<?=url('/admin/online-tests/submissions/'.$s['id'])?>"><?=$s['status']==='marked'?'Review marks':'Open & mark'?></a><?php else:?><span class="status-badge">Writing now<?php $left=strtotime((string)$s['started_at'])+\App\Repositories\OnlineTestRepository::allowance($test,$s)*60-time();echo $left>0?' · '.ceil($left/60).' min left':' · time is up';?></span>
<form class="extra-time-row" method="post" action="<?=url('/admin/online-tests/attempts/'.$s['id'].'/extend')?>"><?=csrf_field()?><input class="module-input" type="number" name="minutes" min="1" max="600" value="10" aria-label="Extra minutes for <?=e($s['student_name'])?>"><button class="btn btn--secondary" type="submit">+ time</button></form><?php endif?></td>
</tr>
<?php endforeach?>
<?php if(!$submissions):?><tr><td colspan="8"><div class="compact-empty"><h3>No student has started this test</h3><p>Attempts will appear here as soon as students open the test.</p></div></td></tr><?php endif?>
</tbody></table></div>
<p class="record-count"><?=$total?> attempt<?=$total===1?'':'s'?> · <?=$published?> published</p>
</div></section>
</div></div></main>
<style>.publish-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.publish-bar__form{display:flex;gap:8px;align-items:center}.link-button{background:none;border:0;padding:0;margin-top:4px;color:var(--text-secondary,#6f6569);font-size:12px;text-decoration:underline;cursor:pointer}</style>
