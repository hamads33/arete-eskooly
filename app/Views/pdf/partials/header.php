<?php
$logo='';
if(!empty($institute['logo_path'])){$candidate=BASE_PATH.'/public/'.ltrim((string)$institute['logo_path'],'/');if(is_file($candidate)&&in_array(strtolower(pathinfo($candidate,PATHINFO_EXTENSION)),['png','jpg','jpeg'],true))$logo=$candidate;}
$contact=[];
if(!empty($template['show_address'])&&!empty($institute['address']))$contact[]=$institute['address'];
if(!empty($template['show_phone'])&&!empty($institute['phone']))$contact[]=$institute['phone'];
if(!empty($template['show_website'])&&!empty($institute['website']))$contact[]=$institute['website'];
?>
<htmlpageheader name="reportHeader"><table class="doc-header"><tr><?php if(!empty($template['show_logo'])&&$logo):?><td class="logo-cell"><img src="<?=e($logo)?>"></td><?php endif?><td><div class="institute-name"><?=e($institute['name']??'Institute')?></div><?php if(!empty($institute['target_line'])):?><div class="tagline"><?=e($institute['target_line'])?></div><?php endif?><div class="contact"><?=e(implode(' | ',$contact))?></div></td><td class="generated-cell">GENERATED<br><strong><?=e(date('d M Y'))?></strong><br><?=e(date('h:i A'))?></td></tr></table><div class="brand-rule"><span></span></div></htmlpageheader><sethtmlpageheader name="reportHeader" value="on" show-this-page="1" />
