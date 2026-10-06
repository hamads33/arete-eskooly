<?php
$primary=$template['primary_color']??'#7A1632';
$accent=$template['accent_color']??'#D9A441';
$footerText=$templateService->renderText((string)($template['footer_text']??''),$placeholders,$type);
$achievement=trim((string)($record['achievement']??''));
// Size the frame from the real page geometry so it fills the sheet in any
// size/orientation, instead of the old hardcoded 165mm that only suited A4 portrait.
$portraitHeight=($template['page_size']??'A4')==='Letter'?279.4:297.0;
$portraitWidth=($template['page_size']??'A4')==='Letter'?215.9:210.0;
$pageHeight=($template['page_orientation']??'P')==='L'?$portraitWidth:$portraitHeight;
$compact=($template['margin_mode']??'normal')==='compact';
$frameHeight=max($pageHeight-($compact?29:34)-($compact?18:22)-31,70);
?>
<style>
body{font-family:dejavusans,sans-serif;color:#211A1D;font-size:10pt;line-height:1.6}

/* Two quiet rules rather than ornament: maroon frame, hairline gold inlay. */
.frame{border:2.2px solid <?=e($primary)?>;padding:2.6mm}
.inner{border:.6px solid <?=e($accent)?>;padding:10mm 14mm;text-align:center;height:<?=round($frameHeight,1)?>mm}

.doc-header{width:100%;border-collapse:collapse;text-align:left}
.logo-cell{width:22mm;padding-right:4mm;vertical-align:middle}
.logo-cell img{width:18mm;height:18mm;object-fit:contain}
.identity-cell{vertical-align:middle}
.identity-flush{padding-left:0}
.institute-name{font-size:16pt;font-weight:bold;color:<?=e($primary)?>;text-transform:uppercase;letter-spacing:.2px}
.tagline{font-size:8pt;color:#776D71;margin-top:.6mm}
.contact{font-size:7.4pt;color:#9B9195;margin-top:1mm}
.contact .sep{color:#DED3D8;padding:0 1.4mm}
.generated-cell{width:36mm;text-align:right;color:#9B9195;font-size:6.6pt;line-height:1.4;vertical-align:middle}
.running-title{color:<?=e($primary)?>;font-size:7pt;font-weight:bold;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1.2mm}
.generated-label{text-transform:uppercase;letter-spacing:.5px;font-size:6pt}
.generated-cell strong{color:#211A1D;font-size:7.4pt}
.brand-rule{height:1.4px;margin:3mm 0 0;background:<?=e($primary)?>}
.brand-rule span{display:block;width:22%;height:1.4px;background:<?=e($accent)?>}

.award{page-break-inside:avoid}
.title{font-size:22pt;color:<?=e($primary)?>;text-transform:uppercase;letter-spacing:2px;margin-top:10mm}
.title-rule{width:24mm;height:1.4px;background:<?=e($accent)?>;margin:3mm auto 0}
.presented{margin-top:9mm;color:#776D71;font-size:9.5pt;text-transform:uppercase;letter-spacing:1px}
.recipient{font-size:24pt;font-weight:bold;color:#541022;margin:4mm 10mm 0;padding-bottom:3mm;border-bottom:.8px solid #DED3D8;overflow-wrap:anywhere;line-height:1.25}
.achievement{font-size:11pt;line-height:1.75;margin:7mm 14mm 0;color:#211A1D}
.awarded{margin-top:8mm;font-size:9pt;color:#776D71}
.serial{font-size:7.5pt;color:#9B9195;margin-top:1.4mm}

.sign-row{width:100%;border-collapse:collapse;margin-top:14mm;page-break-inside:avoid}
.sign-row td{text-align:center;vertical-align:bottom;padding:0}
.sign-row td.sign-space{height:14mm;border-bottom:.8px solid #776D71}
.sign-row td.sign-gap{border:none}
.sign-row td.sign-label{padding-top:2.4mm;font-size:8pt;color:#776D71;text-transform:uppercase;letter-spacing:.6px}

.footer-rule{height:.5px;background:#DED3D8;margin-bottom:1.4mm}
.doc-footer{width:100%;border-collapse:collapse;color:#9B9195;font-size:6.8pt;line-height:1.4;text-align:left}
.doc-footer strong{color:#541022}
.footer-identity{vertical-align:top}
.footer-wide{width:100%}
.page-number{text-align:right;vertical-align:bottom;white-space:nowrap;width:34mm;color:#776D71}
</style>
<?php require BASE_PATH.'/app/Views/pdf/partials/footer.php'; ?>
<div class="frame"><div class="inner">
<?php require BASE_PATH.'/app/Views/pdf/partials/header.php'; ?>
<div class="award">
<div class="title"><?=e($template['title'])?></div>
<div class="title-rule"></div>
<?php if(!empty($template['subtitle'])):?><div class="presented"><?=e($template['subtitle'])?></div><?php else:?><div class="presented">This certificate is proudly presented to</div><?php endif?>
<div class="recipient"><?=e((string)($record['recipient_name']??''))?></div>
<div class="achievement"><?=e($achievement!==''?$achievement:'In recognition of achievement and dedication.')?></div>
<div class="awarded">Awarded on <?=e(date('d F Y',strtotime((string)$record['certificate_date'])))?></div>
<div class="serial">Certificate No. <?=e((string)$record['id'])?></div>
<table class="sign-row">
<tr><td class="sign-space" style="width:36%"></td><td class="sign-gap" style="width:28%"></td><td class="sign-space" style="width:36%"></td></tr>
<tr><td class="sign-label">Authorized Signature</td><td class="sign-gap"></td><td class="sign-label">Principal</td></tr>
</table>
</div>
</div></div>
