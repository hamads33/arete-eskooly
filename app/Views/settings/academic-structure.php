<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Academic Structure</span></nav><h1>Sections &amp; Streams</h1><p>Configure class sections and academic groups, then place students.</p></div><a class="btn btn--ghost" href="<?=e(url('/admin/settings/academic-placement'))?>">Place Students</a></header>

<section class="module-card"><header class="module-card__header"><div><h2>Add Section</h2><p>Sections belong to a class, for example Grade 9 &rarr; A.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/settings/academic-structure/sections'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="class_id">Class</label><select class="module-input" id="class_id" name="class_id" required><option value="">-- Select a class --</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>"><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="name">Section Name</label><input class="module-input" id="name" name="name" type="text" required maxlength="60" placeholder="e.g. A"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Add Section</button></div>
</form>
</div></section>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Filter by Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<section class="module-card"><header class="module-card__header"><div><h2>Class Sections</h2><p><?=count($sections)?> section(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Class</th><th>Section</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($sections as $s):?><tr>
<td><?=e($s['class_name'])?></td><td><?=e($s['name'])?></td><td><?=(int)$s['student_count']?></td>
<td><span class="status-badge"><?=e(ucfirst((string)$s['status']))?></span></td>
<td>
<form method="post" action="<?=e(url('/admin/settings/academic-structure/sections/'.(int)$s['id'].'/status'))?>" class="inline-form"><?=csrf_field()?><input type="hidden" name="status" value="<?=$s['status']==='active'?'inactive':'active'?>"><button class="text-button" type="submit"><?=$s['status']==='active'?'Deactivate':'Activate'?></button></form>
 &middot; <form method="post" action="<?=e(url('/admin/settings/academic-structure/sections/'.(int)$s['id'].'/delete'))?>" class="inline-form"><?=csrf_field()?><button class="text-button" type="submit" onclick="return confirm('Delete this section?')">Delete</button></form>
</td>
</tr><?php endforeach?>
<?php if(!$sections):?><tr><td colspan="5"><div class="compact-empty"><h3>No Sections</h3><p>Add a section to divide a class.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Academic Streams / Groups</h2><p>For example Pre-Medical, Pre-Engineering, Computer Science, Commerce.</p></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/settings/academic-structure/streams'))?>" class="module-form">
<?=csrf_field()?>
<div class="module-field"><label for="stream_name">Stream Name</label><input class="module-input" id="stream_name" name="name" type="text" required maxlength="80" placeholder="e.g. Pre-Medical"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Add Stream</button></div>
</form>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Stream</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($streams as $s):?><tr>
<td><?=e($s['name'])?></td><td><?=(int)$s['student_count']?></td>
<td><span class="status-badge"><?=e(ucfirst((string)$s['status']))?></span></td>
<td>
<form method="post" action="<?=e(url('/admin/settings/academic-structure/streams/'.(int)$s['id'].'/status'))?>" class="inline-form"><?=csrf_field()?><input type="hidden" name="status" value="<?=$s['status']==='active'?'inactive':'active'?>"><button class="text-button" type="submit"><?=$s['status']==='active'?'Deactivate':'Activate'?></button></form>
 &middot; <form method="post" action="<?=e(url('/admin/settings/academic-structure/streams/'.(int)$s['id'].'/delete'))?>" class="inline-form"><?=csrf_field()?><button class="text-button" type="submit" onclick="return confirm('Delete this stream?')">Delete</button></form>
</td>
</tr><?php endforeach?>
<?php if(!$streams):?><tr><td colspan="4"><div class="compact-empty"><h3>No Streams</h3><p>Add a stream if your institute groups senior classes.</p></div></td></tr><?php endif?>
</tbody></table></div>
</div></section>
</div></div></main>
