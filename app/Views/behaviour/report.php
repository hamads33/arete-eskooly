<?php
$isSkills = $domain === 'psychomotor';
$title = $isSkills ? 'Psycomotor Domain Rating Report' : 'Affective Domain Rating Report';
$self = $isSkills ? '/admin/behaviour/reports/psychomotor' : '/admin/behaviour/reports/affective';
$band = static function (float $avg) use ($scale): string {
    $rounded = (int) round($avg);
    return $scale[$rounded] ?? '—';
};
$averages = array_map(static fn(array $r): float => (float) $r['average_rating'], $rows);
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=e(url('/admin/dashboard'))?>">Dashboard</a><i data-lucide="chevron-right"></i><span><?=e($title)?></span></nav><h1><?=e($title)?></h1><p><?=$isSkills?'Average psychomotor rating per student.':'Average affective rating per student.'?></p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<form class="filter-bar filter-bar--compact" method="get">
<div class="module-field"><label for="class">Class</label><select class="module-input" id="class" name="class"><option value="0">All classes</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label for="period">Period</label><input class="module-input" id="period" name="period" type="month" value="<?=e($period)?>"></div>
<div class="filter-actions"><button class="btn btn--primary">Apply</button></div>
</form>

<div class="metric-grid">
<div class="metric-card"><span>Students Rated</span><strong><?=count($rows)?></strong></div>
<div class="metric-card"><span>Group Average</span><strong><?=$averages?e((string)round(array_sum($averages)/count($averages),2)):'—'?></strong></div>
<div class="metric-card"><span>Highest</span><strong><?=$averages?e((string)max($averages)):'—'?></strong></div>
<div class="metric-card"><span>Lowest</span><strong><?=$averages?e((string)min($averages)):'—'?></strong></div>
</div>

<section class="module-card"><header class="module-card__header"><div><h2><?=e($title)?></h2><p>Period <?=e($period)?><?=$selectedClass?'':' · all classes'?>.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Class</th><th>Traits Rated</th><th>Average</th><th>Band</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr>
<td><?=e($r['registration_no'])?></td>
<td><?=e($r['student_name'])?></td>
<td><?=e($r['class_name']??'—')?></td>
<td><?=(int)$r['rated_traits']?></td>
<td><?=e((string)$r['average_rating'])?></td>
<td><span class="status-badge"><?=e($band((float)$r['average_rating']))?></span></td>
</tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="6"><div class="compact-empty"><h3>No Ratings Recorded</h3><p>No ratings match this class and period.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
