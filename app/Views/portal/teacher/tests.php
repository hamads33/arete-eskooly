<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/portal/employee/teaching'))?>">My Teaching</a><i data-lucide="chevron-right"></i><span>Class Tests</span></nav><h1>My Class Tests</h1><p>Create tests and record marks for your assigned classes.</p></div></header>

<?php require __DIR__.'/_scopebar.php'; ?>

<?php if($selectedClass && $subjects): ?>
<section class="module-card"><header class="module-card__header"><div><h2>Create Class Test</h2></div></header><div class="module-card__body">
<form method="post" action="<?=e(url('/portal/employee/teaching/tests'))?>" class="module-form">
<?=csrf_field()?>
<input type="hidden" name="class_id" value="<?=(int)$selectedClass?>">
<div class="module-field"><label for="subject_id">Subject</label><select class="module-input" id="subject_id" name="subject_id" required><?php foreach($subjects as $id=>$name):?><option value="<?=(int)$id?>"><?=e($name)?></option><?php endforeach?></select></div>
<div class="module-field"><label for="title">Title</label><input class="module-input" id="title" name="title" type="text" required maxlength="150" placeholder="e.g. Chapter 3 Quiz"></div>
<div class="module-field"><label for="date">Test Date</label><input class="module-input" id="date" name="date" type="date" required value="<?=e(date('Y-m-d'))?>"></div>
<div class="module-field"><label for="total_marks">Total Marks</label><input class="module-input" id="total_marks" name="total_marks" type="number" step="0.01" min="1" required value="20"></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Create Test</button></div>
</form>
</div></section>
<?php endif; ?>

<section class="module-card"><header class="module-card__header"><div><h2>Tests In My Classes</h2><p><?=count($tests)?> test(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Title</th><th>Class</th><th>Subject</th><th>Total</th><th>Marked</th><th></th></tr></thead><tbody>
<?php foreach($tests as $t):?><tr>
<td><?=e($t['test_date'])?></td><td><?=e($t['title'])?></td><td><?=e($t['class_name'])?></td><td><?=e($t['subject_name'])?></td>
<td><?=e((string)$t['total_marks'])?></td><td><?=(int)$t['marked']?></td>
<td><a href="<?=e(url('/portal/employee/teaching/tests/'.(int)$t['id'].'/marks'))?>">Enter marks</a></td>
</tr><?php endforeach?>
<?php if(!$tests):?><tr><td colspan="7"><div class="compact-empty"><h3>No Class Tests</h3><p>Tests you create will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
