<?php
$logo='';
if(!empty($institute['logo_path'])){$candidate=BASE_PATH.'/public/'.ltrim((string)$institute['logo_path'],'/');if(is_file($candidate)&&in_array(strtolower(pathinfo($candidate,PATHINFO_EXTENSION)),['png','jpg','jpeg'],true))$logo=$candidate;}
$showLogo=!empty($template['show_logo'])&&$logo!=='';
$showGenerated=!empty($template['show_generated_date']);
$contact=[];
if(!empty($template['show_address'])&&!empty($institute['address']))$contact[]=$institute['address'];
if(!empty($template['show_phone'])&&!empty($institute['phone']))$contact[]=$institute['phone'];
if(!empty($template['show_website'])&&!empty($institute['website']))$contact[]=$institute['website'];
$runningTitle=trim((string)($template['title']??''));
$hasAside=$showGenerated||$runningTitle!=='';
?>
<htmlpageheader name="reportHeader">
<?php $slim=!empty($report); ?>
<table class="doc-header<?=$slim?' slim':''?>">
<tr>
<?php if($showLogo):?><td class="logo-cell" style="width:<?=$slim?10:17?>mm;padding-right:<?=$slim?2:3.5?>mm;vertical-align:middle"><img src="<?=e($logo)?>" width="<?=$slim?30:52?>" height="<?=$slim?30:52?>" style="width:<?=$slim?8:14?>mm;height:<?=$slim?8:14?>mm"></td><?php endif?>
<td class="identity-cell<?=$showLogo?'':' identity-flush'?><?=$hasAside?'':' identity-wide'?>">
<div class="institute-name"><?=e($institute['name']??'Institute')?></div>
<?php if(!$slim&&!empty($institute['target_line'])):?><div class="tagline"><?=e($institute['target_line'])?></div><?php endif?>
<?php if($contact):?><div class="contact"><?=implode(' &nbsp;&#183;&nbsp; ',array_map('e',$contact))?></div><?php endif?>
</td>
<?php if($hasAside):?>
<td class="generated-cell">
<?php if($runningTitle!==''):?><div class="running-title"><?=e($runningTitle)?></div><?php endif?>
<?php if($showGenerated&&$slim):?><span class="generated-time">Generated <?=e(date('d M Y, h:i A'))?></span><?php elseif($showGenerated):?><div class="generated-label">Generated</div><strong><?=e(date('d M Y'))?></strong><br><span class="generated-time"><?=e(date('h:i A'))?></span><?php endif?>
</td>
<?php endif?>
</tr>
</table>
<div class="brand-rule<?=$slim?' slim':''?>"><span></span></div>
</htmlpageheader>
<sethtmlpageheader name="reportHeader" value="on" show-this-page="1" />
