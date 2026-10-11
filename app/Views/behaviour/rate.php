<?php $isSkills=$domain==='psychomotor'; $self=$isSkills?'/admin/behaviour/skills':'/admin/behaviour/rate'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Behaviour &amp; Skills</span></nav><h1><?=$isSkills?'Rate Skills':'Rate Behaviours'?></h1><p><?=$isSkills?'Psychomotor domain — physical, practical and artistic skill.':'Affective domain — attitude, discipline and conduct.'?></p></div></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class" required><option value="0">-- Select a class --</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="period">Period</label><input class="module-input" id="period" name="period" type="month" value="<?=e($period)?>"></div>
<div class="filter-actions"><button class="btn btn--primary">Load Students</button></div>
</form>

<?php if(!$traits): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="list-checks"></i></span><strong>No Active Traits</strong><p>Define traits for this domain before rating.</p><a class="btn btn--primary" href="<?=e(url('/admin/behaviour/traits'))?>">Manage Traits</a></div></div></div></section>
<?php elseif(!$selectedClass): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users-round"></i></span><strong>Choose a Class</strong><p>Select a class and period to rate its students.</p></div></div></div></section>
<?php elseif(!$sheet): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users-round"></i></span><strong>No Active Students</strong><p>This class has no active students to rate.</p></div></div></div></section>
<?php else: ?>
<section class="module-card"><header class="module-card__header"><div><h2><?=e(ucfirst($domain))?> Ratings</h2><p><?=count($sheet)?> student(s) · period <?=e($period)?>. Leave blank to clear a rating.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header><div class="module-card__body">
<form method="post" action="<?=e(url('/admin/behaviour/ratings'))?>">
<?=csrf_field()?>
<input type="hidden" name="domain" value="<?=e($domain)?>">
<input type="hidden" name="class" value="<?=(int)$selectedClass?>">
<input type="hidden" name="period" value="<?=e($period)?>">
<div class="table-scroll"><table class="data-table mobile-register"><thead><tr><th>Registration</th><th>Student</th><?php foreach($traits as $t):?><th><?=e($t['name'])?></th><?php endforeach?></tr></thead><tbody>
<?php foreach($sheet as $row):?><tr>
<td><?=e($row['registration_no'])?></td>
<td><?=e($row['student_name'])?></td>
<?php foreach($traits as $t): $current=$row['ratings'][(int)$t['id']]??''; ?>
<td><select class="module-input" name="ratings[<?=(int)$row['id']?>][<?=(int)$t['id']?>]"><option value="">—</option><?php foreach($scale as $value=>$label):?><option value="<?=(int)$value?>" <?=(string)$current===(string)$value?'selected':''?>><?=(int)$value?> · <?=e($label)?></option><?php endforeach?></select></td>
<?php endforeach; ?>
</tr><?php endforeach?>
</tbody></table></div>
<div class="filter-actions"><button class="btn btn--primary" type="submit">Save Ratings</button></div>
</form>
</div></section>
<?php endif; ?>
</div></div></main>
