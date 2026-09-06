<?php $f=static fn($v)=>($v===null||$v==='')?'—':$v; ?>
<main class="main"><div class="main__inner"><div class="module-page">
<header class="module-page__header"><div><nav class="breadcrumb"><span>Portal</span><i data-lucide="chevron-right"></i><span>My Profile</span></nav><h1>My Profile</h1><p>Your admission record as held by the institute.</p></div></header>

<section class="module-card"><header class="module-card__header"><div><h2>Student Details</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><tbody>
<tr><th>Registration No</th><td><?=e($student['registration_no'])?></td><th>Name</th><td><?=e($student['student_name'])?></td></tr>
<tr><th>Class</th><td><?=e($f($student['class_name']??null))?></td><th>Date of Admission</th><td><?=e($f($student['date_of_admission']))?></td></tr>
<tr><th>Date of Birth</th><td><?=e($f($student['date_of_birth']))?></td><th>Gender</th><td><?=e($f($student['gender']))?></td></tr>
<tr><th>Blood Group</th><td><?=e($f($student['blood_group']))?></td><th>Religion</th><td><?=e($f($student['religion']))?></td></tr>
<tr><th>Mobile</th><td><?=e($f($student['mobile']))?></td><th>Status</th><td><span class="status-badge"><?=e(ucfirst((string)$student['status']))?></span></td></tr>
<tr><th>Address</th><td colspan="3"><?=e($f($student['address']))?></td></tr>
</tbody></table></div></div></section>

<section class="module-card"><header class="module-card__header"><div><h2>Guardian Details</h2></div></header><div class="module-card__body"><div class="table-scroll"><table class="data-table"><tbody>
<tr><th>Father Name</th><td><?=e($f($student['father_name']))?></td><th>Father Mobile</th><td><?=e($f($student['father_mobile']))?></td></tr>
<tr><th>Father Occupation</th><td><?=e($f($student['father_occupation']))?></td><th>Father NIC</th><td><?=e($f($student['father_nic']))?></td></tr>
<tr><th>Mother Name</th><td><?=e($f($student['mother_name']))?></td><th>Mother Mobile</th><td><?=e($f($student['mother_mobile']))?></td></tr>
<tr><th>Family</th><td><?=e($f($student['family']))?></td><th>Total Siblings</th><td><?=e($f($student['total_siblings']))?></td></tr>
</tbody></table></div></div></section>
</div></div></main>
