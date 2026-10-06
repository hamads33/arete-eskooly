<?php
$tot=['students'=>0,'marked'=>0,'Present'=>0,'Absent'=>0,'Late'=>0,'Leave'=>0];
foreach($summary as$s){$tot['students']+=(int)$s['student_count'];$tot['marked']+=(int)$s['marked'];$tot['Present']+=(int)$s['present_count'];$tot['Absent']+=(int)$s['absent_count'];$tot['Late']+=(int)$s['late_count'];$tot['Leave']+=(int)$s['leave_count'];}
$tot['Not marked']=max(0,$tot['students']-$tot['marked']);$rate=$tot['marked']?round(($tot['Present']+$tot['Late'])/$tot['marked']*100,1):0;
$query=static fn(array$over):string=>url('/admin/attendance/daily-report?'.http_build_query(array_filter(array_replace(['date'=>$selectedDate,'class'=>$selectedClass?:null,'status'=>$selectedStatus?:null,'q'=>$search?:null],$over),static fn($v)=>$v!==null&&$v!=='')));
$badge=['Present'=>'published','Absent'=>'overdue','Late'=>'pending','Leave'=>'draft','Not marked'=>'inactive'];
?>
<main class="main"><div class="main__inner"><div class="module-page daily-attendance">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?=url('/admin/dashboard')?>">Dashboard</a><i data-lucide="chevron-right"></i><span>Daily Attendance Report</span></nav><h1>Daily Attendance Report</h1><p><?=e(date('l, d M Y',strtotime($selectedDate)))?><?=$selectedDate===date('Y-m-d')?' · Today':''?></p></div>
<div class="filter-actions no-print"><a class="btn btn--secondary" href="<?=e($query(['export'=>'csv']))?>"><i data-lucide="file-spreadsheet"></i>CSV</a><button class="btn btn--secondary" type="button" onclick="window.print()"><i data-lucide="printer"></i>Print</button></div></header>

<form class="filter-bar filter-bar--compact no-print" method="get">
<div class="module-field"><label>Date</label><input class="module-input" type="date" name="date" value="<?=e($selectedDate)?>" max="<?=date('Y-m-d')?>"></div>
<div class="module-field"><label>Class</label><select class="module-select" name="class"><option value="">All classes</option><?php foreach($classes as$c):?><option value="<?=$c['id']?>" <?=$selectedClass===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label>Status</label><select class="module-select" name="status"><option value="">All statuses</option><?php foreach(['Present','Absent','Late','Leave','Not marked'] as$st):?><option <?=$selectedStatus===$st?'selected':''?>><?=$st?></option><?php endforeach?></select></div>
<div class="module-field"><label>Search</label><input class="module-input" type="search" name="q" value="<?=e($search)?>" placeholder="Name or reg. no"></div>
<button class="btn btn--primary">Apply</button><?php if($selectedDate!==date('Y-m-d')):?><a class="btn btn--secondary" href="<?=url('/admin/attendance/daily-report')?>">Today</a><?php endif?>
</form>

<div class="metric-grid">
<?php foreach([['All students','',$tot['students']],['Present','Present',$tot['Present']],['Absent','Absent',$tot['Absent']],['Late','Late',$tot['Late']],['Leave','Leave',$tot['Leave']],['Not marked','Not marked',$tot['Not marked']]] as[$label,$key,$count]):?>
<a class="metric-card<?=$selectedStatus===$key?' is-active':''?>" href="<?=e($query(['status'=>$key]))?>"><span><?=$label?></span><strong><?=$count?></strong></a>
<?php endforeach?>
<div class="metric-card"><span>Attendance rate</span><strong><?=$rate?>%</strong></div>
</div>

<?php if(!$selectedClass):?>
<section class="module-card"><header class="module-card__header"><div><h2>Class-wise Summary</h2><p>Select a class to see only its students.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Class</th><th>Students</th><th>Present</th><th>Absent</th><th>Late</th><th>Leave</th><th>Not marked</th><th>Rate</th></tr></thead><tbody>
<?php foreach($summary as$s):$m=(int)$s['marked'];$rr=$m?round(((int)$s['present_count']+(int)$s['late_count'])/$m*100,1):0;?><tr><td><a href="<?=e($query(['class'=>(int)$s['id']]))?>"><strong><?=e($s['name'])?></strong></a></td><td><?=(int)$s['student_count']?></td><td><?=(int)$s['present_count']?></td><td><?=(int)$s['absent_count']?></td><td><?=(int)$s['late_count']?></td><td><?=(int)$s['leave_count']?></td><td><?=max(0,(int)$s['student_count']-$m)?></td><td><?=$m?$rr.'%':'—'?></td></tr><?php endforeach?>
<?php if(!$summary):?><tr><td colspan="8"><div class="compact-empty"><h3>No classes found</h3></div></td></tr><?php endif?>
</tbody></table></div></div></section>
<?php endif?>

<section class="module-card"><header class="module-card__header"><div><h2><?=$selectedStatus?e($selectedStatus).' students':'All students'?></h2><p><?=count($rows)?> student<?=count($rows)===1?'':'s'?><?=$selectedClass?' · '.e(array_column($classes,'name','id')[$selectedClass]??''):' · all classes'?></p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>#</th><th>Reg. No</th><th>Student</th><th>Class</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($rows as$n=>$row):?><tr><td><?=$n+1?></td><td><?=e($row['registration_no'])?></td><td><strong><?=e($row['student_name'])?></strong><?php require BASE_PATH.'/app/Views/partials/person-status-tag.php'; ?></td><td><?=e($row['class_name'])?></td><td><span class="status-badge status-badge--<?=$badge[$row['attendance_status']]??'draft'?>"><?=e($row['attendance_status'])?></span></td><td><?=e($row['remarks'])?></td></tr><?php endforeach?>
<?php if(!$rows):?><tr><td colspan="6"><div class="compact-empty"><h3>No students match</h3><p><?=$tot['marked']===0?'Attendance has not been marked for this day yet.':'Try another status or class.'?></p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
<style>.daily-attendance .metric-grid{grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}.daily-attendance a.metric-card{text-decoration:none;color:inherit}.daily-attendance a.metric-card.is-active{outline:2px solid var(--color-primary,#7A1632)}@media print{.no-print,.sidebar,.header,.app-header{display:none!important}.main{margin:0!important}}</style>
