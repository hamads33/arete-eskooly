<main class="main"><div class="main__inner"><div class="module-page">
<?php require BASE_PATH . '/app/Views/partials/submission-drafts.php'; ?>
<header class="module-page__header"><div><h1>Online Tests</h1><p>Tests assigned to your class.</p></div></header>
<div class="dashboard-grid student-test-list">
<?php foreach($tests as$t):
    $attempted = in_array($t['attempt_status']??'', ['submitted','marked'], true);
    $inProgress = ($t['attempt_status']??'') === 'in_progress';
?>
<section class="module-card"><div class="module-card__body">
<h2><?=e($t['title'])?></h2>
<p><?=e($t['subject_name']?:'General')?></p>
<p><?=e($t['instructions']?:'Follow the instructions and submit your answers.')?></p>
<p><?=(int)$t['duration_minutes']?> minutes<?=$t['total_marks']?' · '.e((string)$t['total_marks']).' marks':''?></p>
<?php if($attempted):?>
  <?php if($t['result_visible']):?>
    <span class="status-badge status-badge--active">Marked · <?=e((string)$t['total_score'])?><?=$t['total_marks']?' / '.e((string)$t['total_marks']):''?></span>
    <?php if(trim((string)($t['teacher_comment']??''))!==''):?><p class="teacher-comment-snippet"><i data-lucide="message-square-text"></i><span><strong>Teacher's comment:</strong> <?=e(mb_strimwidth((string)$t['teacher_comment'],0,140,'…'))?></span></p><?php endif?>
    <a class="btn btn--secondary" href="<?=url('/portal/student/online-tests/'.$t['attempt_id'].'/result')?>">View Result<?=trim((string)($t['teacher_comment']??''))!==''?' & Comments':''?></a>
  <?php elseif($t['attempt_status']==='marked'):?>
    <span class="status-badge status-badge--pending">Marked · result not published yet</span>
  <?php else:?>
    <span class="status-badge status-badge--pending">Submitted · awaiting marking</span>
  <?php endif?>
<?php elseif($inProgress):?>
  <a class="btn btn--primary" href="<?=url('/portal/student/online-tests/'.$t['id'])?>">Continue test</a>
<?php else:?>
  <a class="btn btn--primary" href="<?=url('/portal/student/online-tests/'.$t['id'])?>">Start test</a>
<?php endif?>
</div></section>
<?php endforeach?>
<?php if(!$tests):?><div class="compact-empty"><h3>No tests available</h3><p>Tests your teacher assigns to your class will appear here.</p></div><?php endif?>
</div>
</div></div></main>
