<?php $videoSrc=url('/admin/recorded-lectures/'.$lecture['id'].'/video');$audience=$lecture['audience_type']==='all'?'All students':($lecture['audience_type']==='student'?($lecture['student_name']??'Student'):($lecture['class_names']??'Class'));?>
<link rel="stylesheet" href="<?=e(asset('css/lectures.css?v=2'))?>">
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=url('/admin/dashboard')?>">Dashboard</a><i data-lucide="chevron-right"></i><a href="<?=url('/admin/recorded-lectures')?>">Recorded Lectures</a><i data-lucide="chevron-right"></i><span><?=e($lecture['title'])?></span></nav></div></header>
<div style="max-width:980px">
<?php require BASE_PATH.'/app/Views/partials/lecture-player.php'?>
<div class="lecture-info">
<span class="lecture-tile__subject"><?=e($lecture['subject'])?></span>
<h1><?=e($lecture['title'])?></h1>
<div class="lecture-info__meta"><span><i data-lucide="users-round"></i><?=e($audience)?></span><span><i data-lucide="book-marked"></i><?=e($lecture['chapter']?:'General topic')?></span><span><i data-lucide="calendar"></i><?=e(date('d M Y',strtotime($lecture['publish_date'])))?></span><?php if($lecture['teacher']):?><span><i data-lucide="user-round"></i><?=e($lecture['teacher'])?></span><?php endif?><span class="status-badge status-badge--<?=e($lecture['status'])?>"><?=e(ucfirst($lecture['status']))?></span></div>
<?php if($lecture['description']):?><div class="lecture-info__desc"><?=e($lecture['description'])?></div><?php endif?>
</div>
</div>
</div></div></main>
