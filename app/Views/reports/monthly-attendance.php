<?php
$isEmployee = ($audience ?? 'student') === 'employee';
$title = $isEmployee ? 'Staff Monthly Attendance Report' : 'Students Monthly Attendance Report';
$mark = static function (?string $status): string {
    return match ($status) { 'Present' => 'P', 'Absent' => 'A', 'Leave' => 'L', 'Late' => 'Lt', 'Half Day' => 'H', default => '·' };
};
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Reports</span></nav><h1><?=e($title)?></h1><p>One row per person, one column per day of the month.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="period">Month</label><input class="module-input" id="period" name="period" type="month" value="<?=e($period)?>"></div>
<?php if(!$isEmployee): ?>
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<?php endif; ?>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<?php $sumP=array_sum(array_column($rows,'present'));$sumA=array_sum(array_column($rows,'absent'));$sumL=array_sum(array_column($rows,'leave'));$sumT=array_sum(array_column($rows,'total'));?><div class="metric-grid"><div class="metric-card"><span><?=$isEmployee?'Staff':'Students'?></span><strong><?=count($rows)?></strong></div><div class="metric-card"><span>Present</span><strong><?=(int)$sumP?></strong></div><div class="metric-card"><span>Absent / Leave</span><strong><?=(int)$sumA?> / <?=(int)$sumL?></strong></div><div class="metric-card"><span>Average attendance</span><strong><?=$sumT>0?e((string)round($sumP/$sumT*100,1)):'0'?>%</strong></div></div>
<section class="module-card"><header class="module-card__header"><div><h2><?=e($title)?></h2><p><?=count($rows)?> record(s) · <?=e($period)?> · P present, A absent, L leave, · not marked.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr>
<th><?=$isEmployee?'Employee No':'Registration'?></th><th>Name</th><th><?=$isEmployee?'Designation':'Class'?></th>
<?php for($d=1;$d<=$days;$d++):?><th><?=$d?></th><?php endfor;?>
<th>P</th><th>A</th><th>L</th><th>%</th></tr></thead><tbody>
<?php foreach($rows as $row):?><tr>
<td><?=e($row['registration_no'])?></td>
<td><?=e($row['name'])?></td>
<td><?=e($row['group']??'—')?></td>
<?php for($d=1;$d<=$days;$d++):?><td><?=e($mark($row['days'][$d]??null))?></td><?php endfor;?>
<td><?=(int)$row['present']?></td><td><?=(int)$row['absent']?></td><td><?=(int)$row['leave']?></td>
<td><?=e((string)$row['percentage'])?>%</td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="<?=$days+7?>"><div class="compact-empty"><h3>No Records</h3><p>Nobody active matches this filter.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
