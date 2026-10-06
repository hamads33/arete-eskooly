<?php if(!empty($studentNotifications)):$icons=['lecture'=>'clapperboard','homework'=>'notebook-pen','fee'=>'receipt-text'];?>
<div class="student-notify" data-student-notify hidden>
<div class="student-notify__backdrop" data-notify-close></div>
<section class="student-notify__card" role="dialog" aria-modal="true" aria-labelledby="studentNotifyTitle">
<button class="student-notify__x" type="button" data-notify-close aria-label="Close">&times;</button>
<span class="module-eyebrow">Notifications</span>
<h2 id="studentNotifyTitle">You have <?=count($studentNotifications)?> new update<?=count($studentNotifications)===1?'':'s'?></h2>
<ul class="student-notify__list">
<?php foreach(array_slice($studentNotifications,0,8) as$n):?>
<li><a class="student-notify__item student-notify__item--<?=e($n['type'])?>" href="<?=e($n['url'])?>" data-notify-link><span class="student-notify__icon"><i data-lucide="<?=e($icons[$n['type']]??'bell')?>"></i></span><span><strong><?=e($n['title'])?></strong><small><?=e($n['detail'])?></small></span></a></li>
<?php endforeach?>
</ul>
<?php if(count($studentNotifications)>8):?><p class="student-notify__more">+<?=count($studentNotifications)-8?> more</p><?php endif?>
<button class="btn btn--primary student-notify__ok" type="button" data-notify-close>Got it</button>
</section>
</div>
<style>
.student-notify{position:fixed;inset:0;z-index:10050;display:grid;place-items:center;padding:18px}
.student-notify[hidden]{display:none}
.student-notify__backdrop{position:absolute;inset:0;background:rgba(15,15,20,.45)}
.student-notify__card{position:relative;width:min(460px,100%);max-height:calc(100vh - 36px);overflow:auto;background:var(--surface,#fff);border-radius:16px;padding:22px;box-shadow:0 24px 60px rgba(0,0,0,.25)}
.student-notify__card h2{margin:4px 0 14px;font-size:19px}
.student-notify__x{position:absolute;top:10px;right:12px;border:0;background:none;font-size:24px;line-height:1;cursor:pointer;color:var(--text-muted,#777)}
.student-notify__list{list-style:none;margin:0 0 16px;padding:0;display:flex;flex-direction:column;gap:8px}
.student-notify__item{display:flex;gap:12px;align-items:center;padding:10px 12px;border:1px solid var(--border,#eee);border-radius:12px;color:inherit;text-decoration:none}
.student-notify__item:hover{border-color:var(--primary,#800020)}
.student-notify__item strong{display:block;font-size:14px}
.student-notify__item small{display:block;color:var(--text-muted,#777);font-size:12.5px;margin-top:2px}
.student-notify__icon{flex:0 0 38px;height:38px;border-radius:10px;display:grid;place-items:center;background:rgba(128,0,32,.08);color:var(--primary,#800020)}
.student-notify__item--fee .student-notify__icon{background:#fdecec;color:#c0392b}
.student-notify__item--homework .student-notify__icon{background:#eef4ff;color:#2f5fd0}
.student-notify__more{margin:-6px 0 14px;color:var(--text-muted,#777);font-size:13px}
.student-notify__ok{width:100%;justify-content:center}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const box=document.querySelector('[data-student-notify]');if(!box)return;
  const keys=<?=json_encode(array_column($studentNotifications,'key'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
  box.hidden=false;
  const markRead=()=>{const body=new FormData();body.append('_token',<?=json_encode(csrf_token(),JSON_HEX_TAG)?>);keys.forEach(k=>body.append('keys[]',k));return fetch(<?=json_encode(url('/portal/notifications/read'),JSON_HEX_TAG)?>,{method:'POST',body,credentials:'same-origin',keepalive:true}).catch(()=>{})};
  box.querySelectorAll('[data-notify-close]').forEach(b=>b.addEventListener('click',()=>{box.hidden=true;markRead()}));
  box.querySelectorAll('[data-notify-link]').forEach(a=>a.addEventListener('click',()=>markRead()));
});
</script>
<?php endif?>
