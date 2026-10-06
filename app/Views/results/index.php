<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><span class="module-eyebrow">ONLINE TESTS</span><h1>Results Center</h1><p>Publish results to students and parents, download result PDFs, or jump into a test's submissions to mark and manage results one student at a time.</p></div><a class="btn btn--secondary" href="<?=url('/admin/online-tests')?>">Create &amp; manage tests</a></header>

<section class="module-card"><div class="module-card__body">
<form method="get" class="filter-bar">
<div class="module-field"><label>Class</label><select class="module-select" name="class"><option value="">All classes</option><?php foreach($classes as$c):?><option value="<?=$c['id']?>" <?=$classFilter===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach?></select></div>
<div class="module-field"><label>Status</label><select class="module-select" name="status">
<option value="" <?=$statusFilter===''?'selected':''?>>All tests</option>
<option value="needs_marking" <?=$statusFilter==='needs_marking'?'selected':''?>>Needs marking</option>
<option value="needs_publishing" <?=$statusFilter==='needs_publishing'?'selected':''?>>Marked, not published</option>
<option value="fully_published" <?=$statusFilter==='fully_published'?'selected':''?>>Fully published</option>
</select></div>
<div class="filter-actions"><button class="btn btn--primary">Filter</button><?php if($classFilter||$statusFilter):?><a class="btn btn--secondary" href="<?=url('/admin/results')?>">Clear</a><?php endif?></div>
</form>
</div></section>

<section class="module-card"><div class="module-card__body">
<?php if(!$tests):?>
<div class="compact-empty"><h3>No tests match these filters</h3><p><?=$canManageAll?'Create a test to see its results here.':'You can only manage results for tests you created or for classes you are assigned to teach.'?></p></div>
<?php else:?>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Test</th><th>Class</th><th>Subject</th><th>Submitted</th><th>Needs marking</th><th>Ready to publish</th><th>Publish</th><th></th></tr></thead><tbody>
<?php foreach($tests as$t):$submitted=(int)$t['submission_count'];$pending=(int)$t['pending_count'];$unpublished=(int)$t['unpublished_count'];?>
<tr>
<td data-label="Test"><strong><?=e($t['title'])?></strong></td>
<td data-label="Class"><?=e($t['class_name'])?></td>
<td data-label="Subject"><?=e($t['subject_name']?:'—')?></td>
<td data-label="Submitted"><?=$submitted?></td>
<td data-label="Needs marking"><?php if($pending):?><span class="status-badge status-badge--pending"><?=$pending?> to mark</span><?php else:?>—<?php endif?></td>
<td data-label="Ready to publish"><?php if($unpublished):?><span class="status-badge status-badge--pending"><?=$unpublished?> to publish</span><?php else:?>—<?php endif?></td>
<td data-label="Publish"><?php if($unpublished):?>
<form method="post" action="<?=url('/admin/results/'.$t['id'].'/publish')?>" class="results-publish-form" onsubmit="return confirm('Publish all marked results for &quot;<?=e(addslashes($t['title']))?>&quot; now?')">
<?=csrf_field()?>
<select class="module-select" name="visible_to"><option value="both">Students &amp; parents</option><option value="student">Students only</option><option value="parent">Parents only</option></select>
<button class="btn btn--primary">Publish</button>
</form>
<?php elseif($submitted):?><span class="status-badge status-badge--active">All published</span><?php else:?><span class="status-badge">No submissions yet</span><?php endif?></td>
<td data-label="Actions"><div class="table-actions">
<a class="btn btn--secondary" href="<?=url('/admin/online-tests/'.$t['id'].'/submissions')?>">Submissions</a>
<?php if($submitted):?><a class="btn btn--secondary" href="<?=url('/admin/results/'.$t['id'].'/pdf?download=1')?>" target="_blank" rel="noopener"><i data-lucide="file-down"></i>PDF</a><?php endif?>
</div></td>
</tr>
<?php endforeach?>
</tbody></table></div>
<p class="record-count"><?=count($tests)?> test<?=count($tests)===1?'':'s'?></p>
<?php endif?>
</div></section>
</div></div></main>
<style>.results-publish-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.results-publish-form .module-select{min-height:38px;padding:0 10px;font-size:12px}.results-publish-form .btn{min-height:38px;padding-inline:12px;font-size:12px}</style>
