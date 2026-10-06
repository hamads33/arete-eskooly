<?php
$styles[] = 'css/fee-workspace.css?v=4';
$people = $employees; $kind = 'employee'; $idField = 'employee_ids[]';
$action = url('/admin/employees/statuses');
?>
<main class="main student-status-page"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><a href="<?= url('/admin/employees') ?>">Employees</a><i data-lucide="chevron-right"></i><span>Status</span></nav><h1>Active / Inactive / Suspended Employees</h1><p>Records up to the date you choose stay in attendance, salary and reports. What happens after it follows your <a href="<?= url('/admin/settings/status-rules') ?>">Inactive &amp; Suspended Rules</a>.</p></div><a class="btn btn--secondary" href="<?= url('/admin/settings/status-rules') ?>"><i data-lucide="sliders-horizontal"></i>Rules</a></header>
<form class="filter-bar filter-bar--compact" method="get"><div class="module-field"><label for="status-search">Search employees</label><input class="module-input" id="status-search" name="search" value="<?= e($search) ?>" placeholder="Name or employee number"></div><div></div><button class="btn btn--secondary">Search</button></form>
<?php require BASE_PATH . '/app/Views/partials/person-status-form.php'; ?>
</div></div></main>
