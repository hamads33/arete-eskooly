<?php $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Classes</span></nav><h1>My Classes</h1><p>Classes where you are recorded as the assigned teacher.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Assigned Classes</h2><p><?=count($classes)?> class(es).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Class</th><th>Active Students</th><th>Monthly Tuition</th></tr></thead><tbody>
<?php foreach($classes as $c):?><tr><td><?=e($c['name'])?></td><td><?=(int)$c['student_count']?></td><td><?=e($cur)?> <?=number_format((float)$c['monthly_tuition'],2)?></td></tr><?php endforeach?>
<?php if(!$classes):?><tr><td colspan="3"><div class="compact-empty"><h3>No Classes Assigned</h3><p>You are not currently recorded as the teacher for any class.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
