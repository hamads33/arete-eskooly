<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/portal/employee/teaching'))?>">My Teaching</a><i data-lucide="chevron-right"></i><span>Announcements</span></nav><h1>My Announcements</h1><p>Send an announcement to the students and parents of your classes. It appears under your name, separate from school announcements.</p></div></header>
<?php if(!$classes):?>
<section class="module-card"><div class="compact-empty"><h3>No assigned classes</h3><p>You can send announcements once you are assigned to a class.</p></div></section>
<?php else:?>
<form class="module-card" method="post" enctype="multipart/form-data" action="<?=e(url('/portal/employee/teaching/announcements'))?>"><?=csrf_field()?>
<header class="module-card__header"><div><h2>New Announcement</h2></div></header>
<div class="module-card__body"><div class="module-form-grid module-form-grid--three">
<div class="module-field"><label for="annTitle">Title</label><input class="module-input" id="annTitle" name="title" maxlength="180" required></div>
<div class="module-field"><label for="annClass">Class</label><select class="module-select" id="annClass" name="class_id" required><?php foreach($classes as$c):?><option value="<?=(int)$c['id']?>"><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="annAudience">Send to</label><select class="module-select" id="annAudience" name="audience"><option value="all">Students and parents</option><option value="students">Students only</option><option value="parents">Parents only</option></select></div>
<div class="module-field field--wide"><label for="annMessage">Message</label><textarea class="module-textarea" id="annMessage" name="message" rows="5" required></textarea></div>
<div class="module-field"><label>Image <small>(optional)</small></label><input class="module-input" type="file" name="image" accept=".jpg,.jpeg,.png"><small>JPG or PNG, maximum 5 MB.</small></div>
<div class="module-field"><label>Expires at <small>(optional)</small></label><input class="module-input" type="datetime-local" name="expires_at"></div>
<label class="checkbox-row"><input type="checkbox" name="show_popup" value="1"><span><strong>Show as popup</strong><small>Recipients see it when they open the portal.</small></span></label>
</div></div>
<footer class="module-card__footer"><button class="btn btn--primary"><i data-lucide="megaphone"></i>Send Announcement</button></footer>
</form>
<?php endif?>
<section class="module-card"><header class="module-card__header"><h2>Sent Announcements</h2></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Title</th><th>Class</th><th>Sent to</th><th>Posted by</th><th>Action</th></tr></thead><tbody>
<?php foreach($announcements as$a):?><tr><td><?=e(date('d M Y H:i',strtotime($a['publish_at'])))?></td><td><strong><?=e($a['title'])?></strong></td><td><?=e($a['class_name']??'All classes')?></td><td><?=e(['all'=>'Students and parents','students'=>'Students','parents'=>'Parents'][$a['audience']]??ucfirst($a['audience']))?></td><td><?=!empty($a['by_teacher'])?'You':'Administration'?></td><td><form method="post" action="<?=e(url('/portal/employee/teaching/announcements/'.$a['id'].'/delete'))?>" onsubmit="return confirm('Delete this announcement?')"><?=csrf_field()?><button class="class-action-button class-action-button--danger" aria-label="Delete announcement"><i data-lucide="trash-2"></i></button></form></td></tr><?php endforeach?>
<?php if(!$announcements):?><tr><td colspan="6"><div class="compact-empty"><h3>No announcements yet</h3></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
