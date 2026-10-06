<?php
$primary=$template['primary_color']??'#7A1632';
$accent=$template['accent_color']??'#D9A441';
$footerText=$templateService->renderText((string)($template['footer_text']??''),$placeholders,$type);
$empty='—';
$columns=$columns??[];$rows=$rows??[];$meta=$meta??[];$signatures=$signatures??[];$summary=$summary??'';$intro=$intro??'';$notice=$notice??'';
$letterTypes=['admission-letter','job-letter'];
$financialTypes=['fee-receipt','fee-invoice','salary-slip','salary-sheet','account-statement'];
$slipTypes=['fee-receipt','fee-invoice','salary-slip','report-card'];
$layout=in_array($type,$letterTypes,true)?'letter':(in_array($type,$slipTypes,true)?'slip':($columns?'table':'letter'));
$financial=in_array($type,$financialTypes,true);
$report=$layout==='table'&&($template['page_orientation']??'P')==='L'; // landscape list = compact style; portrait = classic
$kicker=$layout==='letter'?'Official Correspondence':($financial?'Official Financial Record':'Official Institutional Report');
// A column reads as numeric when most of its filled values are amounts, counts or percentages; those get right-aligned tabular treatment.
$numeric=[];
foreach($columns as $label=>$key){
    $seen=0;$hits=0;
    foreach($rows as $row){$value=trim((string)($row[$key]??''));if($value==='')continue;$seen++;if(preg_match('/^[^\d\-]{0,4}\s*-?[\d,]+(\.\d+)?\s*%?$/u',$value))$hits++;}
    $numeric[$key]=$seen>0&&$hits/$seen>=0.6;
}
$wide=count($columns)>=7;$dense=$report;
// Column widths are derived from real content length so mPDF never has to guess and
// shrink or overflow the table; fixed layout then guarantees the sum stays on the page.
$widths=[];
if($columns){
    $weights=[];
    foreach($columns as $label=>$key){
        // Header words must never break, so the label's longest word sets a hard minimum.
        $labelWord=0;
        foreach(preg_split('/\s+/',(string)$label)?:[] as $word){$labelWord=max($labelWord,mb_strlen($word));}
        // Atomic values (IDs, amounts, dates) cannot wrap, so they claim width ahead of
        // multi-word name columns, which stay readable when they do wrap.
        $valueWord=0;$valueFull=0;
        foreach($rows as $row){
            $value=trim((string)($row[$key]??''));
            $valueFull=max($valueFull,mb_strlen($value));
            foreach(preg_split('/\s+/',$value)?:[] as $word){$valueWord=max($valueWord,mb_strlen($word));}
        }
        $weights[$key]=max((int)ceil($labelWord*1.5),(int)ceil($valueWord*1.3),min($valueFull,22),8);
    }
    $count=count($weights);
    $total=array_sum($weights)?:1;
    foreach($weights as $key=>$weight){$widths[$key]=$weight/$total*100;}
    // Guarantee every column a usable floor, then take the difference back from the widest ones.
    $floor=min(9.0,100/$count*0.75);
    $short=array_filter($widths,static fn($w)=>$w<$floor);
    if($short){
        $deficit=array_sum(array_map(static fn($w)=>$floor-$w,$short));
        $spare=array_sum(array_filter($widths,static fn($w)=>$w>=$floor))?:1;
        foreach($widths as $key=>$w){$widths[$key]=$w<$floor?$floor:$w-$deficit*($w/$spare);}
    }
    foreach($widths as $key=>$w){$widths[$key]=round($w,2);}
}
?>
<style>
body{font-family:dejavusans,sans-serif;color:#211A1D;font-size:9pt;line-height:1.5}

/* ---------- running header: logo | identity | running head ---------- */
.doc-header{width:100%;border-collapse:collapse}
.logo-cell{width:17mm;padding-right:3.5mm;vertical-align:middle}
.logo-cell img{width:14mm;height:14mm;object-fit:contain}
.identity-cell{vertical-align:middle}
.institute-name{font-size:12.5pt;font-weight:bold;color:<?=e($primary)?>;letter-spacing:.3px;text-transform:uppercase;line-height:1.15}
.tagline{font-size:7pt;color:#776D71;margin-top:.8mm;font-style:italic}
.contact{margin-top:1.1mm;font-size:6.8pt;color:#9B9195;letter-spacing:.15px}
.generated-cell{width:34mm;text-align:right;vertical-align:middle}
.running-title{color:<?=e($primary)?>;font-size:6.8pt;font-weight:bold;text-transform:uppercase;letter-spacing:.7px;line-height:1.3}
.generated-label{text-transform:uppercase;letter-spacing:.6px;font-size:5.6pt;color:#9B9195;margin-top:1.6mm}
.generated-cell strong{color:#211A1D;font-size:7pt;font-weight:bold}
.generated-time{color:#9B9195;font-size:6.2pt}
.brand-rule{height:2px;margin:2.2mm 0 0;background:<?=e($primary)?>}
.brand-rule span{display:block;width:16%;height:2px;background:<?=e($accent)?>}

/* ---------- running footer ---------- */
.footer-rule{height:.5px;background:#ECE5E8;margin-bottom:1.6mm}
.doc-footer{width:100%;border-collapse:collapse;color:#9B9195;font-size:6.4pt;line-height:1.45}
.doc-footer strong{color:#776D71;font-size:6.6pt;letter-spacing:.2px}
.footer-identity{vertical-align:top}
.footer-wide{width:100%}
.page-number{text-align:right;vertical-align:bottom;white-space:nowrap;width:32mm;color:#776D71;font-size:6.6pt}

/* ---------- masthead ---------- */
.masthead{margin:0 0 6mm;page-break-after:avoid}
.document-kicker{text-align:center;text-transform:uppercase;letter-spacing:2px;color:<?=e($accent)?>;font-size:6pt;font-weight:bold;margin:0 0 1.6mm}
.document-title{text-align:center;text-transform:uppercase;color:<?=e($primary)?>;font-size:16pt;letter-spacing:1.2px;font-weight:bold;margin:0;line-height:1.15}
.title-rule{width:14mm;height:1.6px;background:<?=e($accent)?>;margin:2.6mm auto 0}
.subtitle{text-align:center;color:#776D71;font-size:8pt;margin-top:2.4mm;font-style:italic}
</style>
<style>
/* ---------- detail grid (letters + slips) ---------- */
.meta{width:100%;border-collapse:collapse;margin:0 0 6mm;page-break-inside:avoid}
.doc-notice{margin:0 0 4mm;padding:2.6mm 3mm;border:1px solid #E7B7BF;background:#FDECEE;color:#9B1C31;font-size:9.5pt;font-weight:bold;text-align:center;letter-spacing:.3px}
.meta th{width:17%;padding:2.1mm 2.4mm;border:.5px solid #ECE5E8;background:#FAF7F8;color:#9B9195;font-size:6.2pt;text-align:left;text-transform:uppercase;letter-spacing:.6px;vertical-align:top;font-weight:bold}
.meta td{width:33%;padding:2.1mm 2.4mm;border:.5px solid #ECE5E8;background:#fff;color:#211A1D;font-size:8.2pt;font-weight:bold;overflow-wrap:anywhere;vertical-align:top}
.meta td.num{text-align:right;white-space:nowrap;color:#541022}

/* ---------- filter strip (tabular reports) ---------- */
.filters{margin:0 0 4.5mm;padding:2.4mm 3mm;background:#FAF7F8;border-left:2.5px solid <?=e($accent)?>;font-size:7.2pt;color:#776D71;page-break-inside:avoid;page-break-after:avoid}
.filters .label{text-transform:uppercase;letter-spacing:.6px;font-size:6.2pt;color:#9B9195}
.filters .value{color:#211A1D;font-weight:bold;font-size:7.6pt}

/* ---------- body copy ---------- */
.content{margin:0 0 5mm}
.content h2,.content h3{color:<?=e($primary)?>;font-size:9pt;text-transform:uppercase;letter-spacing:.8px;margin:6mm 0 2.4mm;padding-bottom:1.4mm;border-bottom:.5px solid #ECE5E8;page-break-after:avoid}
.content p{margin:0 0 2.8mm}
.content ul,.content ol{margin:0 0 3mm;padding-left:6mm}
.content li{margin-bottom:1.4mm}
.letter-body{font-size:9.5pt;line-height:1.8;text-align:justify}
.letter-body p{margin:0 0 3.6mm}
.letter-body li{margin-bottom:1.8mm;line-height:1.7}
</style>
<style>
/* ---------- data table ---------- */
.data-table{width:100%;border-collapse:collapse;margin:0;table-layout:fixed}
.data-table thead{display:table-header-group;page-break-inside:avoid;page-break-after:avoid}
.data-table th{background:<?=e($primary)?>;color:#FFFFFF;border:.5px solid <?=e($primary)?>;padding:2.2mm 2mm;text-align:left;font-size:6.8pt;font-weight:bold;text-transform:uppercase;letter-spacing:.5px;line-height:1.25}
.data-table td{border:.5px solid #ECE5E8;padding:1.9mm 2mm;font-size:7.6pt;line-height:1.4;vertical-align:top;overflow-wrap:break-word;word-break:normal;color:#211A1D}
.data-table tr{page-break-inside:avoid}
.data-table tr.zebra td{background:#FAF7F8}
.data-table th.num,.data-table td.num{text-align:right;white-space:nowrap;overflow-wrap:normal;word-break:keep-all}
.data-table td.num{font-weight:bold;color:#211A1D}
.data-table.compact th{font-size:6.2pt;padding:1.9mm 1.5mm;letter-spacing:.3px}
.data-table.compact td{font-size:6.9pt;padding:1.6mm 1.5mm}
/* dense = list reports: one tight line per record, like a spreadsheet */
.data-table.dense th{font-size:6.1pt;padding:1.1mm 1.2mm;letter-spacing:.25px;line-height:1.15}
.data-table.dense td{font-size:6.9pt;padding:.65mm 1.2mm;line-height:1.15;border-color:#E6DFE2}
.data-table.dense td.num{font-weight:normal}
.data-table.dense .empty-row td{padding:6mm 2mm}
/* slim report title: title and filters on one line */
.report-strip{width:100%;border-collapse:collapse;margin:0 0 2mm}
.report-strip td{padding:0 0 1.2mm;vertical-align:bottom;border-bottom:.6px solid #E6DFE2}
.report-strip .rs-title{font-size:10.5pt;font-weight:bold;color:<?=e($primary)?>;text-transform:uppercase;letter-spacing:.6px;white-space:nowrap;padding-right:4mm}
.report-strip .rs-filters{font-size:6.6pt;color:#776D71;text-align:right}
.report-strip .rs-filters b{color:#211A1D}
.record-count.slim,.totals.slim{padding:1.3mm 2mm;font-size:7pt}
/* slim running header + footer for list reports */
.doc-header.slim .institute-name{font-size:9.5pt;letter-spacing:.2px}
.doc-header.slim .contact{margin-top:.4mm;font-size:6pt}
.doc-header.slim .running-title{font-size:6.6pt}
.doc-header.slim .generated-cell{width:52mm}
.doc-header.slim .generated-time{font-size:6pt}
.brand-rule.slim{height:1.2px;margin-top:1.2mm}
.brand-rule.slim span{height:1.2px}
.doc-footer.slim{font-size:5.9pt;line-height:1.2}
.record-count{margin:0;padding:2mm 3mm;border:.5px solid #ECE5E8;border-top:none;background:#FAF7F8;color:#776D71;font-size:6.8pt;text-align:right;letter-spacing:.3px;page-break-inside:avoid}
.data-table .empty-row td{text-align:center;color:#9B9195;padding:12mm 2mm;font-size:8pt;font-style:italic;background:#fff}

/* ---------- totals ---------- */
.totals{margin:0;background:#541022;color:#FFFFFF;padding:2.6mm 3mm;text-align:right;font-weight:bold;font-size:8.2pt;letter-spacing:.3px;page-break-inside:avoid}
.totals.money{white-space:nowrap}

/* ---------- signatures ---------- */
.signature-block{page-break-inside:avoid;margin-top:18mm}
.signature-table{width:100%;border-collapse:collapse;page-break-inside:avoid}
.signature-table td{padding:0;text-align:center;vertical-align:bottom}
.signature-table td.sign-gap{border:none;background:none}
.signature-table td.sign-space{height:12mm;border-bottom:.6px solid #9B9195}
.signature-table td.sign-label{padding-top:2.4mm;font-size:7.6pt;color:#776D71;text-transform:uppercase;letter-spacing:.5px}
.stamp-note{margin-top:12mm;font-size:6.6pt;color:#9B9195;font-style:italic;page-break-inside:avoid}
</style>
<?php
require BASE_PATH.'/app/Views/pdf/partials/header.php';
require BASE_PATH.'/app/Views/pdf/partials/footer.php';

/** Shared masthead: kicker, title, gold rule, optional subtitle. */
$renderMasthead=static function()use($template,$kicker){?>
<div class="masthead">
<div class="document-kicker"><?=e($kicker)?></div>
<h1 class="document-title"><?=e($template['title'])?></h1>
<div class="title-rule"></div>
<?php if(!empty($template['subtitle'])):?><div class="subtitle"><?=e($template['subtitle'])?></div><?php endif?>
</div>
<?php };

/** Detail grid used by letters and slips. */
$renderMeta=static function()use($meta,$empty){if(!$meta)return;?>
<table class="meta">
<?php foreach(array_chunk($meta,2,true) as $pair):?>
<tr>
<?php foreach($pair as $label=>$value):$value=trim((string)$value);$isNum=$value!==''&&preg_match('/^[^\d\-]{0,4}\s*-?[\d,]+(\.\d+)?\s*%?$/u',$value);?>
<th><?=e($label)?></th><td class="<?=$isNum?'num':''?>"><?=e($value!==''?$value:$empty)?></td>
<?php endforeach?>
<?php if(count($pair)===1):?><th></th><td></td><?php endif?>
</tr>
<?php endforeach?>
</table>
<?php };

/** Filter/context strip used by tabular reports. */
$renderFilters=static function()use($meta,$empty){if(!$meta)return;?>
<div class="filters"><?php foreach($meta as $label=>$value):$value=trim((string)$value);?><span class="item"><span class="label"><?=e($label)?>:</span> <span class="value"><?=e($value!==''?$value:$empty)?></span></span><?php endforeach?></div>
<?php };

/** Data table with per-column numeric alignment and zebra rows. */
$renderTable=static function()use($columns,$rows,$numeric,$empty,$wide,$widths,$dense){if(!$columns)return;?>
<div class="table-wrap">
<table class="data-table<?=$wide?' compact':''?><?=$dense?' dense':''?>">
<thead><tr><?php foreach($columns as $label=>$key):?><th class="<?=$numeric[$key]?'num':''?>" style="width:<?=$widths[$key]?>%"><?=e($label)?></th><?php endforeach?></tr></thead>
<tbody>
<?php foreach(array_values($rows) as $index=>$row):?>
<tr class="<?=$index%2===1?'zebra':''?>"><?php foreach($columns as $label=>$key):$value=trim((string)($row[$key]??''));?><td class="<?=$numeric[$key]?'num':''?>"><?=e($value!==''?$value:$empty)?></td><?php endforeach?></tr>
<?php endforeach?>
<?php if(!$rows):?><tr class="empty-row"><td colspan="<?=count($columns)?>">No records match the selected criteria.</td></tr><?php endif?>
</tbody>
</table>
</div>
<?php };

/** Row count, closing off a table that has no totals line of its own. */
$renderCount=static function()use($rows,$summary,$report){if(!$rows||!empty($summary))return;?>
<div class="record-count<?=$report?' slim':''?>"><?=count($rows)?> record<?=count($rows)===1?'':'s'?></div>
<?php };

/** Totals / summary bar. */
$renderTotals=static function()use($summary,$financial,$report){if(empty($summary))return;?>
<div class="totals<?=$financial?' money':''?><?=$report?' slim':''?>"><?=e($summary)?></div>
<?php };

/** Signature row, kept whole so it never orphans onto its own page. */
$renderSignatures=static function()use($signatures,$layout){if(!$signatures)return;
$count=count($signatures);
// Gap cells keep each rule separate; collapsed borders would otherwise run them together.
$gap=$count>1?8:0;$width=(100-$gap*($count-1))/$count;?>
<div class="signature-block">
<table class="signature-table">
<?php if($count===1): // a lone signature (e.g. Accounts Office) sits on the right, like a stamp box ?>
<tr><td class="sign-gap" style="width:60%"></td><td class="sign-space" style="width:40%"></td></tr>
<tr><td class="sign-gap"></td><td class="sign-label"><?=e(reset($signatures))?></td></tr>
<?php else: ?>
<tr><?php foreach($signatures as $i=>$label):?><?php if($i):?><td class="sign-gap" style="width:<?=$gap?>%"></td><?php endif?><td class="sign-space" style="width:<?=round($width,2)?>%"></td><?php endforeach?></tr>
<tr><?php foreach($signatures as $i=>$label):?><?php if($i):?><td class="sign-gap"></td><?php endif?><td class="sign-label"><?=e($label)?></td><?php endforeach?></tr>
<?php endif ?>
</table>
<?php if($layout==='letter'):?><div class="stamp-note">This document is valid only when bearing the official stamp of the institute.</div><?php endif?>
</div>
<?php };
?>
<?php if(!$report)$renderMasthead(); ?>
<?php if($notice!==''):?><div class="doc-notice"><?=e($notice)?></div><?php endif?>

<?php if($layout==='letter'): // ---- letter: masthead + reference grid + body copy + signature block ---- ?>
<?php $renderMeta(); ?>
<div class="content letter-body">
<?php if(!empty($template['body_html'])):?><?=$templateService->renderText($template['body_html'],$placeholders,$type)?><?php endif?>
<?php if(!empty($intro)):?><?=$intro?><?php endif?>
</div>
<?php $renderTable(); ?>
<?php $renderTotals(); ?>
<?php $renderSignatures(); ?>

<?php elseif($layout==='slip'): // ---- slip: masthead + detail grid + line items + total ---- ?>
<?php $renderMeta(); ?>
<?php if(!empty($template['body_html'])):?><div class="content"><?=$templateService->renderText($template['body_html'],$placeholders,$type)?></div><?php endif?>
<?php $renderTable(); ?>
<?php $renderTotals(); ?>
<?php if(!empty($intro)):?><div class="content"><?=$intro?></div><?php endif?>
<?php $renderSignatures(); ?>

<?php else: // ---- table: masthead + filter strip + data table + totals ---- ?>
<?php if($report):?><table class="report-strip"><tr><td class="rs-title"><?=e($template['title'])?></td><td class="rs-filters"><?php foreach($meta as $label=>$value):$value=trim((string)$value);if($value==='')continue;?><span><?=e($label)?>: <b><?=e($value)?></b></span> &nbsp;&#183;&nbsp; <?php endforeach?><?=e(count($rows))?> record<?=count($rows)===1?'':'s'?></td></tr></table><?php else: $renderFilters(); endif?>
<?php if(!empty($template['body_html'])):?><div class="content"><?=$templateService->renderText($template['body_html'],$placeholders,$type)?></div><?php endif?>
<?php if(!empty($intro)):?><div class="content"><?=$intro?></div><?php endif?>
<?php $renderTable(); ?>
<?php $renderCount(); ?>
<?php $renderTotals(); ?>
<?php $renderSignatures(); ?>
<?php endif ?>
