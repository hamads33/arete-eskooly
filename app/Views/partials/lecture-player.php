<?php
/** On-site player for one lecture. Expects $lecture and $videoSrc (stream URL for uploaded files). */
use App\Support\LectureMedia;
$embed=LectureMedia::embedUrl($lecture['video_url']??null);
?>
<div class="lecture-stage">
<?php if(!empty($lecture['video_path'])):?>
<video autoplay controls playsinline preload="metadata" controlsList="nodownload" disablepictureinpicture oncontextmenu="return false" src="<?=e($videoSrc)?>"></video>
<?php elseif($embed):?>
<iframe src="<?=e($embed)?>" title="<?=e($lecture['title'])?>" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
<?php else:?>
<div class="lecture-stage__fallback"><div><i data-lucide="link-2"></i><h3>This lecture is hosted on another website</h3><p>It can't be played inside the portal, so it opens in a new tab.</p><a class="btn btn--primary" href="<?=e($lecture['video_url'])?>" target="_blank" rel="noopener noreferrer"><i data-lucide="external-link"></i>Open lecture</a></div></div>
<?php endif?>
</div>
