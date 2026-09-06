<?php $me=(int)(user()['id']??0); ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>Messages</span></nav><h1>Messages</h1><p>Your conversations with the institute.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Start a Conversation</h2><p>Send a message to a member of the institute.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/portal/messages'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="recipient_id">Recipient</label><select class="module-input" id="recipient_id" name="recipient_id" required><option value="">-- Select a recipient --</option><?php foreach($recipients as $r):?><option value="<?=(int)$r['id']?>"><?=e($r['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="subject">Subject</label><input class="module-input" id="subject" name="subject" type="text" maxlength="150" placeholder="Optional"></div>
<div class="module-field"><label for="body">Message</label><textarea class="module-input" id="body" name="body" rows="3" required></textarea></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Send Message</button></div>
</form>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Inbox</h2><p><?=count($conversations)?> conversation(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Subject</th><th>With</th><th>Unread</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach($conversations as $c):?><tr><td><?=e($c['subject']?:'(no subject)')?></td><td><?=e($c['participant_name']?:'—')?></td><td><?=((int)$c['unread_count'])?'<span class="status-badge">'.(int)$c['unread_count'].' new</span>':'—'?></td><td><?=e($c['updated_at'])?></td><td><a href="<?=e(url('/portal/messages/'.(int)$c['id']))?>">Open</a></td></tr><?php endforeach?>
<?php if(!$conversations):?><tr><td colspan="5"><div class="compact-empty"><h3>No Conversations</h3><p>Messages you send or receive will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<?php if($thread): ?>
<section class="module-card"><header class="module-card__header"><div><h2>Conversation</h2><p><?=count($thread['messages'])?> message(s).</p></div></header><div class="module-card__body">
<div class="table-scroll"><table class="data-table"><thead><tr><th>From</th><th>Message</th><th>Sent</th></tr></thead><tbody>
<?php foreach($thread['messages'] as $m):?><tr><td><?=e($m['sender_name'])?><?=((int)$m['sender_id']===$me)?' (you)':''?></td><td><?=nl2br(e($m['body']))?></td><td><?=e($m['created_at'])?></td></tr><?php endforeach?>
<?php if(!$thread['messages']):?><tr><td colspan="3"><div class="compact-empty"><h3>No Messages</h3><p>This conversation is empty.</p></div></td></tr><?php endif?>
</tbody></table></div>
<form method="post" action="<?=e(url('/portal/messages/'.(int)$thread['id']))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="reply">Reply</label><textarea class="module-input" id="reply" name="body" rows="3" required></textarea></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Send Reply</button></div>
</form>
</div></section>
<?php endif; ?>
</div></div></main>
