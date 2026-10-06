<?php
$school=array_values(array_filter($announcements,static fn($a)=>empty($a['teacher_id'])));
$teacher=array_values(array_filter($announcements,static fn($a)=>!empty($a['teacher_id'])));
$card=static function(array$a,bool$fromTeacher):void{?>
<article class="module-card announcement-card"><?php if($a['image_path']):?><img class="announcement-card__image" src="<?=url('/attachments/announcements/'.$a['id'])?>" alt=""><?php endif?><div class="module-card__body"><span class="module-eyebrow"><?=e(date('M j, Y',strtotime($a['publish_at'])))?><?php if($fromTeacher):?> · <?=e($a['teacher_name']??'Teacher')?><?php if(!empty($a['class_name'])):?> · <?=e($a['class_name'])?><?php endif?><?php endif?></span><h2><?=e($a['title'])?></h2><p><?=nl2br(e($a['message']))?></p></div></article>
<?php };
?>
<main class="main"><div class="main__inner"><div class="portal-page"><header class="portal-page__header"><div><span class="module-eyebrow">Stay informed</span><h1>Announcements</h1><p>Updates from your institute and your teachers.</p></div></header>
<?php if(!$announcements):?><section class="module-card"><div class="compact-empty"><h3>No announcements</h3><p>New updates will appear here.</p></div></section><?php endif?>
<?php if($school):?><h2 class="announcement-section-title"><i data-lucide="building-2"></i>School Announcements</h2><div class="portal-resource-grid"><?php foreach($school as$a)$card($a,false);?></div><?php endif?>
<?php if($teacher):?><h2 class="announcement-section-title"><i data-lucide="graduation-cap"></i>Teacher Announcements</h2><div class="portal-resource-grid"><?php foreach($teacher as$a)$card($a,true);?></div><?php endif?>
<style>.announcement-section-title{display:flex;align-items:center;gap:8px;font-size:17px;margin:22px 0 12px}.announcement-section-title svg{width:19px;height:19px;color:var(--primary)}</style>
</div></div></main>
