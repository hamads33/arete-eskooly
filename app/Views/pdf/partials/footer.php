<?php
$showPages=!empty($template['show_page_numbers']);
$showStamp=!empty($template['show_generated_date']);
$footerLine=trim((string)($footerText??''));
?>
<htmlpagefooter name="reportFooter">
<div class="footer-rule"></div>
<?php $slim=!empty($report); ?>
<table class="doc-footer<?=$slim?' slim':''?>">
<tr>
<td class="footer-identity<?=$showPages?'':' footer-wide'?>">
<?php if($slim):?><strong><?=e($institute['name']??'Institute')?></strong><?php if($footerLine!==''):?> &nbsp;&#183;&nbsp; <?=$footerLine?><?php endif?>
<?php else:?><strong><?=e($institute['name']??'Institute')?></strong>
<?php if($footerLine!==''):?><br><?=$footerLine?><?php endif?>
<?php if($showStamp):?><br><span class="footer-stamp">Generated <?=e(date('d M Y, h:i A'))?></span><?php endif?>
<?php endif?>
</td>
<?php if($showPages):?><td class="page-number">Page {PAGENO} of {nbpg}</td><?php endif?>
</tr>
</table>
</htmlpagefooter>
<sethtmlpagefooter name="reportFooter" value="on" />
