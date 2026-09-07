<?php $cur=$institute['currency_symbol']??'Rs'; $siblings=array_filter($families, static fn(array $c):bool=>count($c)>1); ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Family / Sibling Report</h1><p>Students grouped by family, with each child's outstanding balance.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<div class="metric-grid">
<div class="metric-card"><span>Families</span><strong><?=count($families)?></strong></div>
<div class="metric-card"><span>With Siblings</span><strong><?=count($siblings)?></strong></div>
<div class="metric-card"><span>Students Grouped</span><strong><?=array_sum(array_map('count',$families))?></strong></div>
<div class="metric-card"><span>Total Outstanding</span><strong><?=e($cur)?> <?=number_format(array_sum(array_map(static fn(array $c):float=>array_sum(array_map(static fn(array $s):float=>(float)$s['outstanding'],$c)),$families)),2)?></strong></div>
</div>

<?php foreach($families as $family => $children): $due=array_sum(array_map(static fn(array $s):float=>(float)$s['outstanding'],$children)); ?>
<section class="module-card"><header class="module-card__header"><div><h2><?=e($family)?></h2><p><?=count($children)?> student(s).</p></div><div class="metric-card"><span>Family Payable</span><strong><?=e($cur)?> <?=number_format($due,2)?></strong></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Father / Guardian</th><th>Class</th><th>Mobile</th><th>Outstanding</th></tr></thead><tbody>
<?php foreach($children as $s):?><tr>
<td><?=e($s['registration_no'])?></td>
<td><?=e($s['student_name'])?></td>
<td><?=e($s['father_name']?:'—')?></td>
<td><?=e($s['class_name']??'—')?></td>
<td><?=e($s['mobile']?:'—')?></td>
<td><?=e($cur)?> <?=number_format((float)$s['outstanding'],2)?></td>
</tr><?php endforeach?>
</tbody></table></div></div></section>
<?php endforeach; ?>

<?php if(!$families): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="users-round"></i></span><strong>No Families Recorded</strong><p>Assign a family name to students under Students &rarr; Manage Families to group siblings here.</p><a class="btn btn--primary" href="<?=e(url('/admin/students/families'))?>">Manage Families</a></div></div></div></section>
<?php endif; ?>
</div></div></main>
