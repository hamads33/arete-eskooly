<?php
$printInstitute = $institute ?? [];
$printName = $printInstitute['name'] ?? 'Institute';
$printBits = array_values(array_filter([$printInstitute['address'] ?? null,$printInstitute['phone'] ?? null,$printInstitute['website'] ?? null]));
?>
<div class="print-letterhead" aria-hidden="true"><div class="print-letterhead__row">
  <img class="print-letterhead__logo" src="<?=e(($printInstitute['logo_path']??'')!==''?url('/'.ltrim((string)$printInstitute['logo_path'],'/')):brand()['logo'])?>" alt="">
  <div class="print-letterhead__identity"><p class="print-letterhead__name"><?=e($printName)?></p><?php if($printBits):?><p class="print-letterhead__meta"><?=e(implode(' · ',$printBits))?></p><?php endif?></div>
  <div class="print-letterhead__generated"><span>Generated</span><strong><?=e(date('d M Y'))?></strong><small><?=e(date('h:i A'))?></small></div>
</div><div class="print-letterhead__title"><?=e(html_entity_decode(strip_tags((string)($pageTitle??'')),ENT_QUOTES|ENT_HTML5,'UTF-8'))?></div></div>
<div class="print-footer" aria-hidden="true"><span><strong><?=e($printName)?></strong><br>Confidential Academic Record</span><span>Generated <?=e(date('d M Y, h:i A'))?><br><span class="print-footer__page"></span></span></div>
