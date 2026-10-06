<?php
use App\Support\PersonStatus;
$styles[] = 'css/fee-workspace.css?v=4';
$admin = can('*');
$actions = [
    'attendance' => ['Attendance register', 'Shown when marking attendance and in attendance reports after the status date.'],
    'billing' => ['Fee vouchers / salary list', 'Students: included when generating vouchers for a class or all students. Employees: listed as salary pending.'],
    'messages' => ['SMS &amp; announcements', 'Can receive bulk SMS, automatic alerts and announcements.'],
    'login' => ['Can sign in to the portal', 'Student / teacher portal login. Turn off to block sign-in.'],
];
$typeLabels = ['student' => 'Students', 'employee' => 'Employees'];
?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>General Settings</span><i data-lucide="chevron-right"></i><span>Inactive &amp; Suspended Rules</span></nav><h1>Inactive &amp; Suspended Rules</h1><p>Decide what happens when you set a student or employee to <strong>Inactive</strong> or <strong>Suspended</strong>.</p></div>
<div class="filter-actions"><a class="btn btn--secondary" href="<?= url('/admin/students/status') ?>"><i data-lucide="users-round"></i>Student status</a><a class="btn btn--secondary" href="<?= url('/admin/employees/status') ?>"><i data-lucide="briefcase"></i>Employee status</a></div></header>

<section class="module-card"><div class="module-card__body"><div class="notice-panel status-rules-note"><i data-lucide="shield-check"></i><div><strong>History is never hidden.</strong> A student or employee always appears for every date and month <em>before</em> their status changed — attendance, fees, salary and reports up to that day stay visible and editable. The rules below only decide what happens <em>after</em> the change.</div></div></div></section>

<form method="post" action="<?= url('/admin/settings/status-rules') ?>"><?= csrf_field() ?>
<?php foreach ($typeLabels as $type => $typeLabel): ?>
<section class="module-card"><header class="module-card__header"><div><h2><?= $typeLabel ?></h2><p><?= (int) ($counts[$type]['active'] ?? 0) ?> active · <?= (int) ($counts[$type]['inactive'] ?? 0) ?> inactive · <?= (int) ($counts[$type]['suspended'] ?? 0) ?> suspended</p></div></header><div class="module-card__body">
<div class="table-scroll"><table class="data-table no-table-tools status-rules-table"><thead><tr><th>After the status date…</th><th>Inactive</th><th>Suspended</th></tr></thead><tbody>
<?php foreach ($actions as $action => [$label, $help]): if ($type === 'employee' && $action === 'billing') { $label = 'Salary pending list'; $help = 'Listed on Pay Salary as pending for the month.'; } ?>
<tr><td><strong><?= $label ?></strong><small><?= $help ?></small></td>
<?php foreach (['inactive', 'suspended'] as $st): ?><td data-label="<?= $st === 'inactive' ? 'Inactive' : 'Suspended' ?>"><label class="ek-switch"><input type="checkbox" name="rules[<?= $type ?>][<?= $st ?>][<?= $action ?>]" value="1" <?= !empty($rules[$type][$st][$action]) ? 'checked' : '' ?> <?= $admin ? '' : 'disabled' ?> aria-label="<?= e(strip_tags($label)) ?> for <?= $st ?> <?= $type ?>s"><span></span><?= !empty($rules[$type][$st][$action]) ? 'Yes' : 'No' ?></label></td><?php endforeach ?></tr>
<?php endforeach ?>
</tbody></table></div></div></section>
<?php endforeach ?>
<?php if ($admin): ?><div class="ek-submit ek-submit--row"><button class="btn btn--primary btn--lg"><i data-lucide="save"></i>Save rules</button></div><?php endif ?>
</form>

<section class="module-card"><header class="module-card__header"><div><h2>Recent status changes</h2><p>Who changed what, effective from when, and why.</p></div></header><div class="module-card__body">
<?php if (!$recent): ?><div class="compact-empty"><h3>No status changes yet</h3></div><?php else: ?><div class="table-scroll"><table class="data-table"><thead><tr><th>Person</th><th>Change</th><th>Effective from</th><th>Reason</th><th>By</th></tr></thead><tbody>
<?php foreach ($recent as $h): ?><tr><td><strong><?= e($h['person_name'] ?? 'Deleted record') ?></strong><small class="account-row-head"><?= e(ucfirst($h['person_type'])) ?> · <?= e($h['person_no'] ?? '') ?></small></td><td><span class="student-status student-status--<?= e($h['old_status']) ?>"><?= e(PersonStatus::label($h['old_status'])) ?></span> → <span class="student-status student-status--<?= e($h['new_status']) ?>"><?= e(PersonStatus::label($h['new_status'])) ?></span></td><td><?= e(date('d M Y, h:i A', strtotime($h['effective_at']))) ?></td><td><?= e($h['reason'] ?? '—') ?></td><td><?= e($h['changed_by_name'] ?? '—') ?></td></tr><?php endforeach ?>
</tbody></table></div><?php endif ?>
</div></section>
</div></div></main>
<script>document.querySelectorAll('.status-rules-table .ek-switch input').forEach(i=>i.addEventListener('change',()=>{i.parentElement.lastChild.textContent=i.checked?'Yes':'No'}));</script>
