<?php use App\Support\LectureMedia;$videoSrc=url('/portal/student/recorded-lectures/'.$lecture['id'].'/video');?>
<main class="main"><div class="main__inner"><div class="portal-page">
<a class="lecture-back" href="<?=url('/portal/student/recorded-lectures?subject='.($lecture['subject_id']?(int)$lecture['subject_id']:'general'))?>"><i data-lucide="arrow-left"></i><?=e($lecture['subject'])?> lectures</a>
<div class="lecture-watch">
<section>
<?php require BASE_PATH.'/app/Views/partials/lecture-player.php'?>
<div class="lecture-info">
<span class="lecture-tile__subject"><?=e($lecture['subject'])?></span>
<h1><?=e($lecture['title'])?></h1>
<div class="lecture-info__meta"><span><i data-lucide="book-marked"></i><?=e($lecture['chapter']?:'General topic')?></span><span><i data-lucide="calendar"></i><?=e(date('d M Y',strtotime($lecture['publish_date'])))?></span><?php if($lecture['teacher']):?><span><i data-lucide="user-round"></i><?=e($lecture['teacher'])?></span><?php endif?></div>
<?php if($lecture['description']):?><div class="lecture-info__desc"><?=e($lecture['description'])?></div><?php endif?>
</div>
</section>
<?php if(count($lectures)>1):?>
<aside class="lecture-queue" aria-label="More lectures"><h2>More in <?=e($lecture['subject'])?></h2><div class="lecture-queue__list">
<?php foreach($lectures as$l):$thumb=LectureMedia::thumbnail($l['video_url']);$cur=(int)$l['id']===(int)$lecture['id'];?>
<a class="lecture-queue__item<?=$cur?' is-current':''?>" href="<?=url('/portal/student/recorded-lectures/'.$l['id'])?>"<?=$cur?' aria-current="page"':''?>>
<span class="lecture-thumb"><?php if($thumb):?><img src="<?=e($thumb)?>" alt="" loading="lazy"><?php elseif(!empty($l['video_path'])):?><video src="<?=e(url('/portal/student/recorded-lectures/'.$l['id'].'/video'))?>#t=2" preload="metadata" muted playsinline tabindex="-1" aria-hidden="true"></video><?php endif?><span class="lecture-thumb__play"><i data-lucide="play"></i></span></span>
<span><?php if($cur):?><span class="lecture-now">Now playing</span><?php endif?><span class="lecture-queue__title"><?=e($l['title'])?></span><span class="lecture-queue__meta"><?=e($l['teacher']?:$l['subject'])?> · <?=e(LectureMedia::ago($l['publish_date']))?></span></span>
</a>
<?php endforeach?>
</div></aside>
<?php endif?>
</div>
</div></div></main>
