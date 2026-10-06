<?php
$total = array_sum(array_column($attempt['answers'], 'marks'));
$obtained = (float) $attempt['total_score'];
$pct = $total > 0 ? round($obtained / $total * 100, 1) : null;
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=url('/portal/student/online-tests')?>">Online Tests</a><i data-lucide="chevron-right"></i><span><?=e($attempt['title'])?></span></nav><h1><?=e($attempt['title'])?></h1><p>Marked result</p></div>
<div class="metric-card"><span>Score</span><strong><?=e((string)$obtained)?> / <?=e((string)$total)?><?=$pct!==null?' ('.$pct.'%)':''?></strong></div>
</header>

<?php if(trim((string)($attempt['teacher_comment']??''))!==''):?>
<section class="module-card teacher-comment-card"><div class="module-card__body"><h2><i data-lucide="message-square-text"></i> Your teacher's comments</h2><p class="teacher-comment-card__text"><?=nl2br(e((string)$attempt['teacher_comment']))?></p><?php if(!empty($attempt['teacher_comment_at'])):?><small class="teacher-comment-card__when"><?=e(date('d M Y',strtotime($attempt['teacher_comment_at'])))?></small><?php endif?></div></section>
<?php endif?>
<?php if($attempt['marked_files']):?>
<section class="module-card"><div class="module-card__body"><h2>Marked paper from your teacher</h2><div class="table-actions"><?php foreach($attempt['marked_files'] as$i=>$file):?><a class="btn btn--secondary" target="_blank" rel="noopener" href="<?=url('/attachments/test-marked/'.$attempt['id'].'/'.$i)?>">Open marked paper <?=($i+1)?></a><?php endforeach?></div></div></section>
<?php endif?>

<?php if($attempt['submission_files']&&!empty($attempt['files_purged_at'])):?>
<section class="module-card"><div class="module-card__body"><h2>Your submitted paper</h2><p>Your <?=count($attempt['submission_files'])?> uploaded page<?=count($attempt['submission_files'])===1?' was':'s were'?> removed from the portal after marking (<?=e(date('d M Y',strtotime($attempt['files_purged_at'])))?>). Your marks and feedback are kept.</p></div></section>
<?php elseif($attempt['submission_files']):?>
<section class="module-card"><div class="module-card__body"><h2>Your submitted paper</h2><div class="table-actions"><?php foreach($attempt['submission_files'] as$i=>$file):?><a class="btn btn--secondary" target="_blank" rel="noopener" href="<?=url('/attachments/test-papers/'.$attempt['id'].'/'.$i)?>">Open paper <?=($i+1)?></a><?php endforeach?></div></div></section>
<?php endif?>

<?php foreach($attempt['answers'] as$i=>$a):?>
<section class="module-card"><div class="module-card__body">
<h3><?=($i+1).'. '.e($a['question_text'])?></h3>
<?php if($a['question_type']==='mcq'):$opts=json_decode((string)$a['options_json'],true)?:[];$correct=(int)$a['correct_option'];$selected=$a['selected_option']!==null?(int)$a['selected_option']:null;?>
<p><strong>Your answer:</strong> <?=$selected!==null?e($opts[$selected]??'—'):'No answer'?><?=$selected!==null&&$selected!==$correct?' <span class="status-badge status-badge--inactive">Incorrect</span>':''?><?=$selected===$correct?' <span class="status-badge status-badge--active">Correct</span>':''?></p>
<?php if($selected!==$correct):?><p><strong>Correct answer:</strong> <?=e($opts[$correct]??'—')?></p><?php endif?>
<?php else:?>
<p><strong>Your answer:</strong> <?=nl2br(e($a['answer_text']?:'Answer provided as a file'))?></p>
<?php if($a['answer_attachments']&&empty($attempt['files_purged_at'])):$multi=count($a['answer_attachments'])>1;?><div class="table-actions"><?php foreach($a['answer_attachments']as$pi=>$path):?><a class="btn btn--secondary" target="_blank" rel="noopener" href="<?=url('/attachments/test-answers/'.$a['answer_id'].'/'.$pi)?>"><?=$multi?'Open page '.($pi+1):'Open your uploaded answer'?></a><?php endforeach?></div><?php endif?>
<?php endif?>
<p><strong>Marks:</strong> <?=e((string)$a['score'])?> / <?=e((string)$a['marks'])?></p>
<?php if($a['feedback']):?><p class="test-result-feedback"><strong>Teacher's feedback:</strong> <?=nl2br(e($a['feedback']))?></p><?php endif?>
</div></section>
<?php endforeach?>
</div></div></main>
