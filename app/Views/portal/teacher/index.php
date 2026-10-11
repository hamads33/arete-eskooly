<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Teaching</span></nav><h1>My Teaching</h1><p>Classes and subjects you are assigned to.</p></div></header>

<?php if(!$scope): ?>
<section class="module-card"><div class="module-card__body"><div class="empty-state"><div><span class="empty-state__icon"><i data-lucide="school"></i></span><strong>No Assignments Yet</strong><p>An administrator has not assigned you to any class or subject. Once assigned, you can mark attendance and publish homework, notes and tests here.</p></div></div></div></section>
<?php else: ?>
<div class="metric-grid">
<div class="metric-card"><span>Assigned Classes</span><strong><?=count($scope)?></strong></div>
<div class="metric-card"><span>Subjects</span><strong><?=array_sum(array_map(static fn(array $c):int=>count($c['subjects']),$scope))?></strong></div>
<div class="metric-card"><span>Whole-Class Roles</span><strong><?=count(array_filter($scope,static fn(array $c):bool=>$c['whole_class']))?></strong></div>
<div class="metric-card metric-card--text"><span>Teacher</span><strong><?=e($employee['name'])?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2>Quick Actions</h2><p>Everything here is limited to your assigned classes.</p></div></header><div class="module-card__body">
<div class="filter-actions quick-actions">
<a class="btn btn--primary" href="<?=e(url('/portal/employee/teaching/attendance'))?>">Mark Attendance</a>
<a class="btn btn--ghost" href="<?=e(url('/portal/employee/teaching/homework'))?>">Homework</a>
<a class="btn btn--ghost" href="<?=e(url('/portal/employee/teaching/notes'))?>">Class Notes</a>
<a class="btn btn--ghost" href="<?=e(url('/portal/employee/teaching/tests'))?>">Class Tests</a>
</div>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>My Assignments</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Class</th><th>Scope</th><th>Subjects</th></tr></thead><tbody>
<?php foreach($scope as $c):?><tr>
<td><?=e($c['name'])?></td>
<td><span class="status-badge"><?=$c['whole_class']?'Whole class':'Subject teacher'?></span></td>
<td><?=$c['whole_class']?'All subjects in this class':($c['subjects']?e(implode(', ',$c['subjects'])):'—')?></td>
</tr><?php endforeach?>
</tbody></table></div></div></section>
<?php endif; ?>
</div></div></main>
