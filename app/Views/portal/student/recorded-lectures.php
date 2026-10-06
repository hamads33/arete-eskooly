<?php use App\Support\LectureMedia;
$isNew=static fn(string$date):bool=>strtotime($date)>=strtotime('-3 days');
?>
<main class="main"><div class="main__inner"><div class="portal-page">
<?php if(!$folder):?>
<header class="portal-page__header"><div><span class="module-eyebrow">Learning resources</span><h1>Recorded Lectures</h1><p>Lectures for <?=e($student['class_name'])?>, organised by subject.</p></div></header>
<?php if(!$folders):?>
<div class="lecture-empty"><i data-lucide="folder-open"></i><h3>No subjects yet</h3><p>Subject folders appear here once subjects are assigned to your class.</p></div>
<?php else:?>
<div class="lecture-folders">
<?php foreach($folders as$f):$count=count($f['lectures']);$latest=$f['lectures'][0]['publish_date']??null;$fresh=0;foreach($f['lectures'] as$l)if($isNew($l['publish_date']))$fresh++;?>
<a class="lecture-folder" href="<?=url('/portal/student/recorded-lectures?subject='.urlencode($f['key']))?>">
<span class="lecture-folder__icon"><i data-lucide="<?=$count?'folder-open':'folder'?>"></i></span>
<span class="lecture-folder__body"><strong><?=e($f['name'])?></strong><small><?=$count?$count.' lecture'.($count===1?'':'s').' · Last added '.e(LectureMedia::ago($latest)):'No lectures yet'?></small></span>
<?php if($fresh):?><span class="lecture-folder__new"><?=$fresh?> new</span><?php endif?>
</a>
<?php endforeach?>
</div>
<?php endif?>
<?php else:
$months=[];foreach($folder['lectures'] as$l)$months[substr($l['publish_date'],0,7)]=date('F Y',strtotime($l['publish_date']));
?>
<a class="lecture-back" href="<?=url('/portal/student/recorded-lectures')?>"><i data-lucide="arrow-left"></i>All subjects</a>
<header class="portal-page__header"><div><span class="module-eyebrow">Recorded lectures</span><h1><?=e($folder['name'])?></h1><p><?=count($folder['lectures'])?> lecture<?=count($folder['lectures'])===1?'':'s'?>, arranged by date.</p></div></header>
<div class="lecture-chips lecture-chips--folders" aria-label="Switch subject"><?php foreach($folders as$f):?><a class="lecture-chip<?=$f['key']===$folder['key']?' is-active':''?>" href="<?=url('/portal/student/recorded-lectures?subject='.urlencode($f['key']))?>"><?=e($f['name'])?> <span class="lecture-chip__count"><?=count($f['lectures'])?></span></a><?php endforeach?></div>
<?php if(!$folder['lectures']):?>
<div class="lecture-empty"><i data-lucide="clapperboard"></i><h3>No lectures in <?=e($folder['name'])?> yet</h3><p>New lectures for this subject will appear here.</p></div>
<?php else:?>
<div class="lectures-toolbar">
<label class="lectures-search"><i data-lucide="search"></i><input class="module-input" type="search" placeholder="Search title, chapter or teacher" data-lecture-search aria-label="Search lectures"></label>
<?php if(count($months)>1):?><select class="module-select lectures-filter" data-lecture-month aria-label="Filter by month"><option value="">All months</option><?php foreach($months as$k=>$label):?><option value="<?=e($k)?>"><?=e($label)?></option><?php endforeach?></select><?php endif?>
<select class="module-select lectures-filter" data-lecture-sort aria-label="Sort lectures"><option value="desc">Newest first</option><option value="asc">Oldest first</option></select>
</div>
<div data-lecture-days>
<?php $days=[];foreach($folder['lectures'] as$l)$days[$l['publish_date']][]=$l;foreach($days as$date=>$items):?>
<section class="lecture-day" data-lecture-day data-date="<?=e($date)?>">
<h2 class="lecture-day__title"><i data-lucide="calendar-days"></i><?=e(date('l, d M Y',strtotime($date)))?><?php if($isNew($date)):?><span class="lecture-folder__new">New</span><?php endif?></h2>
<div class="lecture-grid">
<?php foreach($items as$l):$thumb=LectureMedia::thumbnail($l['video_url']);?>
<a class="lecture-tile" href="<?=url('/portal/student/recorded-lectures/'.$l['id'])?>" data-lecture-card data-text="<?=e(mb_strtolower($l['title'].' '.($l['chapter']??'').' '.($l['teacher']??'')))?>">
<span class="lecture-thumb"><?php if($thumb):?><img src="<?=e($thumb)?>" alt="" loading="lazy"><?php elseif(!empty($l['video_path'])):?><video src="<?=e(url('/portal/student/recorded-lectures/'.$l['id'].'/video'))?>#t=2" preload="metadata" muted playsinline tabindex="-1" aria-hidden="true"></video><?php else:?><i class="lecture-thumb__icon" data-lucide="clapperboard"></i><?php endif?><span class="lecture-thumb__play"><i data-lucide="play"></i></span></span>
<span class="lecture-tile__row"><span class="lecture-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr(trim((string)($l['teacher']?:$l['subject'])),0,1)))?></span>
<span class="lecture-tile__body"><span class="lecture-tile__title"><?=e($l['title'])?></span><span class="lecture-tile__meta"><?=e($l['teacher']?:'Arete')?></span><span class="lecture-tile__meta"><?=e($l['chapter']?:'General topic')?></span></span></span>
</a>
<?php endforeach?>
</div>
</section>
<?php endforeach?>
</div>
<div class="lecture-empty" data-lecture-none hidden><i data-lucide="search-x"></i><h3>No matching lectures</h3><p>Try another search term or month.</p></div>
<script>document.addEventListener('DOMContentLoaded',()=>{const q=document.querySelector('[data-lecture-search]'),m=document.querySelector('[data-lecture-month]'),sort=document.querySelector('[data-lecture-sort]'),wrap=document.querySelector('[data-lecture-days]'),none=document.querySelector('[data-lecture-none]');const days=[...wrap.querySelectorAll('[data-lecture-day]')];const apply=()=>{const t=q.value.trim().toLowerCase(),month=m?m.value:'';let shown=0;days.forEach(d=>{let n=0;d.querySelectorAll('[data-lecture-card]').forEach(c=>{const ok=!t||c.dataset.text.includes(t);c.hidden=!ok;if(ok)n++});const vis=n>0&&(!month||d.dataset.date.startsWith(month));d.hidden=!vis;if(vis)shown+=n});none.hidden=shown>0};q.addEventListener('input',apply);m&&m.addEventListener('change',apply);sort.addEventListener('change',()=>{days.sort((a,b)=>sort.value==='asc'?a.dataset.date.localeCompare(b.dataset.date):b.dataset.date.localeCompare(a.dataset.date)).forEach(d=>wrap.append(d))})});</script>
<?php endif?>
<?php endif?>
</div></div></main>
