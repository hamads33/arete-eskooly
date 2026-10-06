<?php
$t = $template ?? [];
$pageIntro = 'Use {{variable}} placeholders. Only the variables listed are allowed, and each must have a value when the SMS is queued.';
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>
<form method="post" action="<?= url($templateId ? '/admin/sms/templates/' . $templateId : '/admin/sms/templates') ?>" data-sms-compose data-sms-template-form data-sample='<?= e(json_encode([
    'school_name' => $institute['name'] ?? 'Your Institute', 'school_phone' => $institute['phone'] ?? '0300 0000000', 'recipient_name' => 'Imran Khan', 'student_name' => 'Ayesha Khan',
    'parent_name' => 'Imran Khan', 'class_name' => 'Grade 6', 'registration_no' => 'STU-001', 'date' => date('d M Y'), 'attendance_status' => 'Absent', 'fee_month' => date('F Y'),
    'fee_amount' => 'Rs 5,000', 'due_date' => date('d M Y', strtotime('+5 days')), 'invoice_no' => 'INV-001', 'amount_paid' => 'Rs 5,000', 'receipt_no' => 'RCPT-001',
    'exam_name' => 'Mid Term', 'marks' => '420/500', 'percentage' => '84', 'grade' => 'A', 'announcement_title' => 'School closed on Friday',
])) ?>'>
<?= csrf_field() ?>
<div class="sms-grid">
<section class="module-card">
  <div class="module-card__body">
    <div class="module-form-grid module-form-grid--two">
      <div class="module-field"><label for="tplName">Template Name</label><input class="module-input" id="tplName" name="name" maxlength="120" required value="<?= e($t['name'] ?? '') ?>"></div>
      <div class="module-field"><label for="tplCategory">Category</label><select class="module-select" id="tplCategory" name="category"><?php foreach ($categories as $v => $l): ?><option value="<?= $v ?>" <?= ($t['category'] ?? 'general') === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></div>
    </div>
    <div class="module-field">
      <label for="tplBody">Message Body</label>
      <textarea class="module-textarea sms-textarea" id="tplBody" name="message_body" rows="6" maxlength="1000" required data-sms-message><?= e($t['message_body'] ?? '') ?></textarea>
      <div class="sms-counter" data-sms-counter aria-live="polite"></div>
      <p class="sms-var-error" data-sms-var-error hidden></p>
    </div>
    <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= ($t['is_active'] ?? 1) ? 'checked' : '' ?>><span><strong>Active</strong><small>Inactive templates are hidden from Compose and Bulk SMS, and automations using them pause.</small></span></label>
  </div>
  <footer class="module-card__footer sms-footer"><a class="btn btn--secondary" href="<?= url('/admin/sms/templates') ?>">Cancel</a><button class="btn btn--primary" type="submit"><i data-lucide="save"></i>Save Template</button></footer>
</section>
<aside class="sms-side">
  <section class="module-card">
    <header class="module-card__header"><div><h2>Available Variables</h2><p>Click to insert.</p></div></header>
    <div class="module-card__body"><ul class="sms-var-table"><?php foreach ($variables as $name => $desc): ?><li><button type="button" class="sms-chip" data-sms-insert="{{<?= e($name) ?>}}">{{<?= e($name) ?>}}</button><span><?= e($desc) ?></span></li><?php endforeach ?></ul></div>
  </section>
  <section class="module-card sms-preview">
    <header class="module-card__header"><div><h2>Live Preview</h2><p>With sample values.</p></div></header>
    <div class="module-card__body"><div class="sms-bubble" data-sms-preview-text><span class="sms-muted">Start typing to preview.</span></div></div>
  </section>
</aside>
</div>
</form>
</div></div></main>
