<?php
$styles[] = 'css/fee-workspace.css?v=104';
$scripts[] = 'js/fee-workspace.js?v=2';
$scopeLabels = ['student' => 'Student Wise', 'class' => 'Class Wise', 'all' => 'All Students'];
$copySet = match ($copies) { 'three_copy' => ['bank', 'student', 'institute'], 'student_institute' => ['student', 'institute'], default => ['student'] };
?>
<main class="main"><div class="main__inner"><div class="module-page fee-voucher-page ek-fees">
<nav class="ek-crumbs" aria-label="Breadcrumb"><a href="<?= url('/admin/fees/collect') ?>"><i data-lucide="banknote"></i>Fees</a><i data-lucide="chevron-right"></i><span>Generate Fees Invoice</span></nav>
<section class="ek-card">
  <header class="ek-card__head"><h1>Generate Fees Invoice</h1><a class="btn btn--ghost btn--sm ek-change" href="<?= url('/admin/settings/fee-voucher') ?>"><i data-lucide="settings-2"></i>Voucher settings</a><p>Existing invoices and payments are kept. Missing invoices use your current fee structure.</p></header>
  <form class="ek-voucher" method="post" action="<?= url('/admin/fees/vouchers/pdf') ?>" target="_blank" data-voucher-selection>
    <?= csrf_field() ?>
    <input type="hidden" name="scope" value="<?= e($scope) ?>">
    <div class="ek-tabs" role="tablist" data-voucher-modes><?php foreach ($scopeLabels as $key => $label): ?><button type="button" role="tab" class="<?= $scope === $key ? 'is-active' : '' ?>" aria-selected="<?= $scope === $key ? 'true' : 'false' ?>" data-voucher-scope="<?= $key ?>"><i data-lucide="<?= ['student' => 'user-round', 'class' => 'users-round', 'all' => 'school'][$key] ?>"></i><?= $label ?></button><?php endforeach ?></div>
    <h2 class="ek-step"><span data-voucher-step>1</span><b data-voucher-title><?= e(['student' => 'Student Invoice', 'class' => 'Class Invoices', 'all' => 'All Students Invoices'][$scope] ?? 'Invoices') ?></b></h2>
    <div class="ek-grid">
      <div class="module-field"><label for="voucher_month">Fee month <span class="required">*</span></label><input class="module-input" type="month" id="voucher_month" name="month" value="<?= e($selectedMonth) ?>" required></div>
      <div class="module-field"><label for="voucher_due_date">Due date</label><input class="module-input" type="date" name="due_date" id="voucher_due_date"><small>Printed on every voucher you generate.</small></div>
      <div class="module-field"><label for="voucher_fine">Fine after due date</label><input class="module-input" type="number" min="0" max="1000000" step="1" name="fine_after_due" id="voucher_fine" value="<?= e(($voucherSettings['default_fine'] ?? 0) + 0) ?>" inputmode="numeric"><small>Shown as "Payable after due date".</small></div>
      <div class="module-field"><label for="voucher_bank">Select bank</label><select class="module-select" name="bank_id" id="voucher_bank"><option value="0">All configured accounts</option><?php foreach ($banks as $bank): ?><option value="<?= (int) $bank['id'] ?>"><?= e($bank['bank_name'] . ' · ' . $bank['account_number']) ?></option><?php endforeach ?></select></div>
      <div class="module-field ek-span2" data-scope-field="class"><label for="voucher_class">Select class <span class="required">*</span></label><select class="module-select" name="class" id="voucher_class"><option value="0">-- Select Class --</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= $selectedClass === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach ?></select></div>
      <div class="module-field ek-span2" data-scope-field="student"><label for="voucher_student_search">Search student <span class="required">*</span></label><input class="module-input" id="voucher_student_search" type="search" placeholder="Type student name or registration…" autocomplete="off" data-student-filter><select class="module-select" name="student_id" id="voucher_student" size="5" data-student-select><option value="0">Select student</option><?php foreach ($students as $student): ?><option value="<?= (int) $student['id'] ?>" <?= $selectedStudentId === (int) $student['id'] ? 'selected' : '' ?>><?= e($student['registration_no'] . ' - ' . $student['student_name'] . ' - ' . $student['class_name']) ?></option><?php endforeach ?></select></div>
    </div>
    <div class="module-field" data-scope-field-paid><input type="hidden" name="skip_paid" value="0"><label class="check-row"><input type="checkbox" name="skip_paid" value="1" checked> Skip students who have already paid this month <small>(Class Wise / All Students)</small></label></div>
    <?php if (can('*')): ?><fieldset class="ek-extras-charges" data-extra-charges><legend>Extra charges <small>(optional — e.g. Admission Fee for a new student: use <strong>Student Wise</strong>. Class Wise / All Students charges never go to students who have already paid.)</small></legend>
      <div class="ek-charge-rows" data-charge-rows><?php for ($x = 0; $x < 2; $x++): ?><div class="ek-charge"><input class="module-input" name="extra_label[]" maxlength="60" placeholder="Charge name, e.g. Exam Fee" list="ekChargeNames"><input class="module-input" name="extra_amount[]" type="number" min="1" step="1" inputmode="numeric" placeholder="Amount"></div><?php endfor ?></div>
      <datalist id="ekChargeNames"><option value="Fine"><option value="Exam Fee"><option value="Annual Charges"><option value="Books"><option value="Uniform"><option value="Transport"><option value="Trip / Event"><option value="Lab Charges"></datalist>
      <div class="module-field" style="margin-top:.75rem"><label for="confirm_extra_count">Confirm charge for Class Wise / All Students</label><input class="module-input" id="confirm_extra_count" name="confirm_extra_count" inputmode="numeric" autocomplete="off" placeholder="Type the number of students"><small>Only needed when a charge goes to more than one student: type how many students are selected. Student Wise charges need no confirmation.</small></div>
      <button class="ek-link" type="button" data-add-charge>+ Add another charge</button>
      <details class="ek-howto"><summary>How to add a charge</summary><ol>
        <li><strong>One student:</strong> choose the <em>Student Wise</em> tab, search and pick the student, choose the fee month.</li>
        <li><strong>Whole class:</strong> choose the <em>Class Wise</em> tab and pick the class. (<em>All Students</em> adds it to everyone.)</li>
        <li>Type the charge name (e.g. <em>Fine</em>, <em>Exam Fee</em>, <em>Trip</em>) and its amount. Use <em>+ Add another charge</em> for more.</li>
        <li>Press <strong>Generate Invoice</strong> and confirm. The charge is saved on each invoice and printed on the voucher.</li>
        <li>To change it later, generate again with the same name and the new amount, or edit the amount on <em>Collect Fees</em>.</li>
      </ol></details>
      <p class="fee-guidance"><i data-lucide="info"></i><span>Charges are saved into each invoice, so they also show on Collect Fees, receipts and reports. Generating again with the same charge name updates its amount instead of adding it twice.</span></p>
    </fieldset><?php endif ?>
    <fieldset class="ek-copies"><legend>Copies</legend><?php foreach (['bank' => 'Bank Copy', 'student' => 'Student Copy', 'institute' => 'Institute Copy'] as $key => $label): ?><label class="ek-switch"><input type="checkbox" name="copy[]" value="<?= $key ?>" <?= in_array($key, $copySet, true) ? 'checked' : '' ?>><span></span><?= $label ?></label><?php endforeach ?></fieldset>
    <div class="module-field" data-orientation-field style="max-width:320px"><label for="voucher_orientation">Page layout for a single copy</label><select class="module-select" name="orientation" id="voucher_orientation"><option value="L">Landscape</option><option value="P">Portrait</option></select><small>Two or three copies always print landscape, side by side.</small></div>
    <p class="fee-guidance" data-voucher-hint><i data-lucide="info"></i><span>All Students includes every active student. Class Wise includes every active student in that class. Voided invoices are excluded.</span></p>
    <div class="ek-submit ek-submit--row"><button class="btn btn--primary btn--lg" name="download" value="0"><i data-lucide="file-text"></i>Generate Invoice</button><button class="btn btn--secondary" name="download" value="1"><i data-lucide="download"></i>Download PDF</button><button class="btn btn--ghost" type="button" data-voucher-preview><i data-lucide="list-filter"></i>Preview students</button></div>
  </form>
</section>
<?php if (!empty($previewRequested) || $selectionError): ?>
<section class="ek-card"><header class="ek-card__head ek-card__head--left"><h2><?= count($voucherStudents) ?> student<?= count($voucherStudents) === 1 ? '' : 's' ?> selected</h2><p><?= e(date('F Y', strtotime($selectedMonth . '-01'))) ?> · <?= e($scopeLabels[$scope] ?? 'Selection') ?></p></header>
<?php if ($selectionError || !$voucherStudents): ?><div class="compact-empty"><h3><?= e($selectionError ?: 'No students found') ?></h3><p>Choose a class or student above.</p></div><?php else: ?><div class="table-scroll"><table class="data-table"><thead><tr><th>Registration</th><th>Student</th><th>Class</th></tr></thead><tbody><?php foreach ($voucherStudents as $student): ?><tr><td><?= e($student['registration_no']) ?></td><td><strong><?= e($student['student_name']) ?></strong></td><td><?= e($student['class_name']) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
</section>
<?php endif ?>
</div></div></main>
