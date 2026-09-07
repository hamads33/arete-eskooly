<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/class-tests'))?>">Class Tests</a><i data-lucide="chevron-right"></i><span>Test Marks</span></nav><h1>Manage Test Marks</h1><p>Open marks for any existing test, or create a new one below.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Enter Marks For An Existing Test</h2><p>Every test across all classes and subjects &mdash; pick one and go straight to its marks.</p></div></header><div class="module-card__body">
<?php if($allTests): ?>
<div class="module-form">
<div class="module-field"><label for="jump">Test</label>
<select class="module-input" id="jump" onchange="if(this.value)window.location.href=this.value">
<option value="">-- Select any test --</option>
<?php foreach($allTests as $t): ?>
<option value="<?=e(url('/admin/class-tests/'.(int)$t['id'].'/edit'))?>"><?=e($t['test_date'])?> &middot; <?=e($t['class_name'])?> &middot; <?=e($t['subject_name'])?> &middot; <?=e($t['title'])?> (<?=(int)$t['marked']?> marked)</option>
<?php endforeach; ?>
</select>
<small>Switching subject or class does not require reloading this page.</small>
</div>
</div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Title</th><th>Total</th><th>Marked</th><th></th></tr></thead><tbody>
<?php foreach(array_slice($allTests,0,25) as $t): ?><tr>
<td><?=e($t['test_date'])?></td><td><?=e($t['class_name'])?></td><td><?=e($t['subject_name'])?></td>
<td><?=e($t['title'])?></td><td><?=e((string)$t['total_marks'])?></td><td><?=(int)$t['marked']?></td>
<td><a href="<?=e(url('/admin/class-tests/'.(int)$t['id'].'/edit'))?>">Enter marks</a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="compact-empty"><h3>No Tests Yet</h3><p>Create your first class test below.</p></div>
<?php endif; ?>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Create A New Test</h2><p>A new test needs a class and one of its subjects.</p></div></header><div class="module-card__body">
<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class" required><option value="">Select Class</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="filter-actions"><button class="btn btn--secondary">Load Class</button></div>
</form>

<?php if($selectedClass): ?>
<form method="post" action="<?=e(url('/admin/class-tests/marks'))?>">
<?=csrf_field()?>
<input type="hidden" name="class" value="<?=(int)$selectedClass?>">
<div class="module-form-grid module-form-grid--three">
<div class="module-field"><label for="subject">Subject</label><select class="module-input" id="subject" name="subject" required><?php foreach($subjects as $s):?><option value="<?=(int)$s['id']?>"><?=e($s['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="date">Date</label><input class="module-input" id="date" type="date" name="date" value="<?=e(date('Y-m-d'))?>" required></div>
<div class="module-field"><label for="title">Title</label><input class="module-input" id="title" name="title" required maxlength="150"></div>
<div class="module-field"><label for="total_marks">Maximum Marks</label><input class="module-input" id="total_marks" type="number" name="total_marks" min="1" step="0.01" required></div>
</div>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Marks</th><th>Absent</th></tr></thead><tbody>
<?php foreach($students as $s):?><tr>
<td><?=e($s['registration_no'])?></td><td><?=e($s['student_name'])?></td>
<td><input class="module-input" type="number" min="0" step="0.01" name="marks[<?=(int)$s['id']?>]"></td>
<td><input type="checkbox" name="absent[<?=(int)$s['id']?>]" value="1"></td>
</tr><?php endforeach?>
<?php if(!$students):?><tr><td colspan="4"><div class="compact-empty"><h3>No Active Students</h3><p>This class has no active students.</p></div></td></tr><?php endif?>
</tbody></table></div>
<div class="filter-actions"><button class="btn btn--primary" <?=!$students||!$subjects?'disabled':''?>>Create Test &amp; Save Marks</button></div>
</form>
<?php endif ?>
</div></section>
</div></div></main>
