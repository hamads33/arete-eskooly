<?php $active=count(array_filter($rows, static fn(array $r):bool=>$r['status']==='active')); ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1>Student Register</h1><p>Active and inactive students by class, section and stream.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="status">Status</label><select class="module-input" id="status" name="status"><option value="">All</option><option value="active" <?=$status==='active'?'selected':''?>>Active</option><option value="inactive" <?=$status==='inactive'?'selected':''?>>Inactive</option></select></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<div class="metric-grid">
<div class="metric-card"><span>Students</span><strong><?=count($rows)?></strong></div>
<div class="metric-card"><span>Active</span><strong><?=$active?></strong></div>
<div class="metric-card"><span>Inactive</span><strong><?=count($rows)-$active?></strong></div>
<div class="metric-card"><span>Filter</span><strong><?=e($status?:'All')?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Student Register</h2><p><?=count($rows)?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Father / Guardian</th><th>Class</th><th>Section</th><th>Stream</th><th>Mobile</th><th>Admitted</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr>
<td><?=e($r['registration_no'])?></td>
<td><?=e($r['student_name'])?></td>
<td><?=e($r['father_name']?:'—')?></td>
<td><?=e($r['class_name']??'—')?></td>
<td><?=e($r['section_name']?:'—')?></td>
<td><?=e($r['stream_name']?:'—')?></td>
<td><?=e($r['mobile']?:'—')?></td>
<td><?=e($r['date_of_admission']?:'—')?></td>
<td><span class="status-badge"><?=e(ucfirst((string)$r['status']))?></span></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="9"><div class="compact-empty"><h3>No Students</h3><p>No students match this filter.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
