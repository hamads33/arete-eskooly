<?php
$reportInstitute = $institute ?? [];
$reportLogo = ($reportInstitute['logo_path'] ?? '') !== '' ? (string) $reportInstitute['logo_path'] : 'images/arete-logo.png';
$reportContact = array_values(array_filter([$reportInstitute['address'] ?? null, $reportInstitute['phone'] ?? null, $reportInstitute['website'] ?? null]));
$reportFilters = [];
$reportAddFilter = static function (string $label, mixed $value) use (&$reportFilters): void {
    if ($value !== null && $value !== '' && $value !== 'all') {
        $reportFilters[] = [$label, (string) $value];
    }
};
$reportAddFilter('Period', $period ?? null);
$reportAddFilter('From', $from ?? $fromMonth ?? null);
$reportAddFilter('To', $to ?? $toMonth ?? null);
$reportAddFilter('Status', isset($status) ? ucfirst((string) $status) : null);
$reportAddFilter('Threshold', isset($threshold) ? ((string) $threshold . '%') : null);
if (isset($selectedClass) && is_array($selectedClass)) {
    $reportAddFilter('Class', $selectedClass['name'] ?? null);
} elseif (isset($class) && is_array($class)) {
    $reportAddFilter('Class', $class['name'] ?? null);
}
if (isset($student) && is_array($student)) {
    $reportAddFilter('Student', $student['name'] ?? $student['student_name'] ?? null);
}
?>
<nav class="report-toolbar admin-only-actions" aria-label="Report actions">
  <a class="btn btn--secondary" href="<?= e(url('/admin/reports')) ?>">Back to reports</a>
  <button class="btn btn--primary" type="button" onclick="window.print()">Print report</button>
</nav>
<section class="report-masthead" aria-label="Report identification">
  <div class="report-masthead__brand"><img src="<?= e(asset($reportLogo)) ?>" alt="<?= e($reportInstitute['name'] ?? 'Institute') ?> logo"><div><strong><?= e($reportInstitute['name'] ?? 'Arete Coaching Center') ?></strong><?php if($reportContact):?><span><?= e(implode(' | ', $reportContact)) ?></span><?php endif?></div></div>
  <dl class="report-masthead__meta"><div><dt>Generated</dt><dd><?= e(date('d M Y, h:i A')) ?></dd></div><?php if(!empty($reportInstitute['academic_session'])):?><div><dt>Academic Session</dt><dd><?=e($reportInstitute['academic_session'])?></dd></div><?php endif?></dl>
</section>
<section class="report-heading"><p>Official institutional report</p><h1><?= e(strip_tags((string)($pageTitle ?? 'Report'))) ?></h1><?php if(trim((string)($pageDescription ?? ''))!==''):?><div><?=e(strip_tags((string)$pageDescription))?></div><?php endif?></section>
<?php if ($reportFilters): ?><dl class="report-applied-filters" aria-label="Applied report filters"><?php foreach ($reportFilters as [$label, $value]): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach ?></dl><?php endif ?>
