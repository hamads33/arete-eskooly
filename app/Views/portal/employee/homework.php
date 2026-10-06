<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Homework</span></nav><h1>My Homework</h1><p>Homework you have assigned.</p></div><button class="export-button" type="button" onclick="window.print()">Print</button></header>

<section class="module-card"><header class="module-card__header"><div><h2>Assigned Homework</h2><p><?=count($homework)?> record(s).</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-records"><thead><tr><th>Date</th><th>Class</th><th>Subject</th><th>Homework</th><th>Attachment</th></tr></thead><tbody>
<?php foreach($homework as $h):?><tr><td data-label="Date"><?=e($h['homework_date'])?></td><td data-label="Class" class="record-title"><?=e($h['class_name'])?></td><td data-label="Subject" class="record-title"><?=e($h['subject']??'—')?></td><td data-label="Homework" class="record-wide"><?=e($h['homework_text'])?></td><td data-label="Attachment" class="record-wide"><?php if(!empty($h['attachment_path'])):?><a href="<?=e(url('/'.ltrim((string)$h['attachment_path'],'/')))?>" target="_blank" rel="noopener">Download</a><?php else:?>—<?php endif?></td></tr><?php endforeach?>
<?php if(!$homework):?><tr><td colspan="5"><div class="compact-empty"><h3>No Homework Assigned</h3><p>Homework you assign will appear here.</p></div></td></tr><?php endif?>
</tbody></table></div></div></section>
</div></div></main>
