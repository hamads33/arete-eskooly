<?php $f=static fn($v)=>($v===null||$v==='')?'—':$v; $cur=$institute['currency_symbol']??'Rs'; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Profile</span></nav><h1>My Profile</h1><p>Your employment record as held by the institute.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Employment Details</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-details"><tbody>
<tr><th>Employee No</th><td><?=e($employee['employee_no'])?></td><th>Name</th><td><?=e($employee['name'])?></td></tr>
<tr><th>Designation</th><td><?=e($f($employee['role']))?></td><th>Joining Date</th><td><?=e($f($employee['joining_date']))?></td></tr>
<tr><th>Monthly Salary</th><td><?=e($cur)?> <?=number_format((float)$employee['monthly_salary'],2)?></td><th>Status</th><td><span class="status-badge"><?=e(ucfirst((string)$employee['status']))?></span></td></tr>
<tr><th>Education</th><td><?=e($f($employee['education']))?></td><th>Experience</th><td><?=e($f($employee['experience']))?></td></tr>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Personal Details</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table mobile-details"><tbody>
<tr><th>Father / Husband Name</th><td><?=e($f($employee['father_husband_name']))?></td><th>National ID</th><td><?=e($f($employee['national_id']))?></td></tr>
<tr><th>Date of Birth</th><td><?=e($f($employee['date_of_birth']))?></td><th>Gender</th><td><?=e($f($employee['gender']))?></td></tr>
<tr><th>Blood Group</th><td><?=e($f($employee['blood_group']))?></td><th>Religion</th><td><?=e($f($employee['religion']))?></td></tr>
<tr><th>Mobile</th><td><?=e($f($employee['mobile']))?></td><th>Email</th><td><?=e($f($employee['email']))?></td></tr>
<tr><th>Address</th><td colspan="3"><?=e($f($employee['home_address']))?></td></tr>
</tbody></table></div></div></section>
</div></div></main>
