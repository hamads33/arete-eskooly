<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Class Tests</span></nav><h1>Manage Class Tests</h1><p>Every recorded test, with marks entry and correction.</p></div><a class="btn btn--primary" href="<?=e(url('/admin/class-tests/marks'))?>">New Class Test</a></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<section class="module-card"><header class="module-card__header"><div><h2>Class Tests</h2><p><?=count($tests)?> test(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Title</th><th>Class</th><th>Subject</th><th>Total</th><th>Marked</th><th>Actions</th></tr></thead><tbody>
<?php foreach($tests as $t):?><tr>
<td><?=e($t['test_date'])?></td>
<td><?=e($t['title'])?></td>
<td><?=e($t['class_name'])?></td>
<td><?=e($t['subject_name'])?></td>
<td><?=e((string)$t['total_marks'])?></td>
<td><?=(int)$t['marked']?></td>
<td><a href="<?=e(url('/admin/class-tests/'.(int)$t['id'].'/edit'))?>">Edit marks</a> · <form method="post" action="<?=e(url('/admin/class-tests/'.(int)$t['id'].'/delete'))?>" class="inline-form"><?=csrf_field()?><button class="text-button" type="submit" onclick="return confirm('Delete this class test and its marks?')">Delete</button></form></td>
</tr><?php endforeach?>
<?php if(!$tests):?><tr><td colspan="7"><div class="compact-empty"><h3>No Class Tests</h3><p>Record a test from the New Class Test page.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
