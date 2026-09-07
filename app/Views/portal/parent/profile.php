<?php $fallback = static fn ($value) => ($value === null || $value === '') ? '—' : $value; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><a href="<?= e(url('/portal/parent')) ?>">My Children</a><i data-lucide="chevron-right"></i><span>My Profile</span></nav><h1>My Profile</h1><p>Your guardian record as held by the institute.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Guardian Details</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><tbody>
<tr><th>Name</th><td><?= e($guardian['name']) ?></td><th>Relationship</th><td><?= e($fallback($guardian['relationship'] ?? null)) ?></td></tr>
<tr><th>CNIC</th><td><?= e($fallback($guardian['cnic'] ?? null)) ?></td><th>Status</th><td><span class="status-badge"><?= e(ucfirst((string) ($guardian['status'] ?? 'active'))) ?></span></td></tr>
<tr><th>Mobile</th><td><?= e($fallback($guardian['mobile'] ?? null)) ?></td><th>Email</th><td><?= e($fallback($guardian['email'] ?? null)) ?></td></tr>
<tr><th>Address</th><td colspan="3"><?= e($fallback($guardian['address'] ?? null)) ?></td></tr>
</tbody></table></div>
<p class="module-card__note">To correct any of these details, please contact the institute office. You can change your sign-in email and password under <a href="<?= e(url('/portal/account')) ?>">Account Settings</a>.</p>
</div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Linked Children</h2><p><?= count($children) ?> student(s) linked to you.</p></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Class</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($children as $child): ?><tr>
<td><?= e($child['registration_no']) ?></td>
<td><?= e($child['student_name']) ?></td>
<td><?= e($child['class_name'] ?? '—') ?></td>
<td><span class="status-badge"><?= e(ucfirst((string) ($child['status'] ?? 'active'))) ?></span></td>
<td><a href="<?= e(url('/portal/parent/children/' . (int) $child['id'])) ?>">Open</a></td>
</tr><?php endforeach ?>
<?php if (!$children): ?><tr><td colspan="5"><div class="compact-empty"><h3>No Linked Children</h3><p>No students are linked to your guardian record yet. Please contact the institute office.</p></div></td></tr><?php endif ?>
</tbody></table></div></div></section>
</div></div></main>
