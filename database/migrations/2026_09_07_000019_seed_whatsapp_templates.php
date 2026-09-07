<?php
declare(strict_types=1);
/**
 * Seeds the default WhatsApp message templates.
 *
 * The template table and editor already existed but shipped empty, so every click-to-WhatsApp
 * message had to be written by hand. These are editable defaults, one per institute, covering
 * the events listed in the requirements. Placeholders are substituted by
 * WhatsAppService::render(); anything unknown renders as an empty string rather than leaking
 * the raw token.
 *
 * Additive and idempotent — an institute that has already edited a template keeps its wording.
 */
return static function (PDO $pdo): void {
    $now = date('Y-m-d H:i:s');

    $templates = [
        ['fee_invoice_generated', 'Fee Invoice Generated',
            "Dear {{father_name}},\n\nThe fee invoice for {{student_name}} ({{class_name}}) for {{month}} has been generated.\n\nInvoice: {{invoice_no}}\nAmount due: {{amount_due}}\nDue date: {{due_date}}\n\nRegards,\n{{institute_name}}"],
        ['fee_due_reminder', 'Fee Due Reminder',
            "Dear {{father_name}},\n\nThis is a reminder that the fee for {{student_name}} ({{class_name}}) for {{month}} is due.\n\nInvoice: {{invoice_no}}\nOutstanding: {{remaining}}\n\nKindly clear the balance at your earliest convenience.\n\nRegards,\n{{institute_name}}"],
        ['fee_defaulter_reminder', 'Fee Defaulter Reminder',
            "Dear {{father_name}},\n\nOur records show an outstanding balance of {{remaining}} for {{student_name}} ({{class_name}}) against invoice {{invoice_no}} for {{month}}.\n\nPlease contact the accounts office to settle the amount.\n\nRegards,\n{{institute_name}}"],
        ['payment_received', 'Payment Received',
            "Dear {{father_name}},\n\nWe have received {{amount_paid}} for {{student_name}} ({{class_name}}).\n\nInvoice: {{invoice_no}}\nRemaining balance: {{remaining}}\n\nThank you.\n{{institute_name}}"],
        ['attendance_absent', 'Attendance — Absent',
            "Dear {{father_name}},\n\n{{student_name}} ({{class_name}}) was marked absent today.\n\nIf this is unexpected, please contact the school office.\n\nRegards,\n{{institute_name}}"],
        ['attendance_late', 'Attendance — Late',
            "Dear {{father_name}},\n\n{{student_name}} ({{class_name}}) arrived late today.\n\nRegards,\n{{institute_name}}"],
        ['result_published', 'Result Published',
            "Dear {{father_name}},\n\nThe result for {{exam_name}} has been published for {{student_name}} ({{class_name}}).\n\nPlease collect the result card from the school office or view it in the parent portal.\n\nRegards,\n{{institute_name}}"],
        ['test_result_published', 'Test Result Published',
            "Dear {{father_name}},\n\nThe result for {{test_name}} has been recorded for {{student_name}} ({{class_name}}).\n\nRegards,\n{{institute_name}}"],
        ['homework_published', 'Homework Published',
            "Dear {{father_name}},\n\nNew homework has been assigned for {{student_name}} ({{class_name}}). Please check the student portal for details.\n\nRegards,\n{{institute_name}}"],
        ['general_notice', 'General Parent Notice',
            "Dear {{father_name}},\n\nThis is a message from {{institute_name}} regarding {{student_name}} ({{class_name}}).\n\n\n\nRegards,\n{{institute_name}}"],
    ];

    $statement = $pdo->prepare(
        'INSERT IGNORE INTO whatsapp_templates(institute_id,event_key,name,body,status,created_at,updated_at)
         SELECT id,:event_key,:name,:body,\'active\',:c,:u FROM institutes'
    );
    foreach ($templates as [$key, $name, $body]) {
        $statement->execute(['event_key' => $key, 'name' => $name, 'body' => $body, 'c' => $now, 'u' => $now]);
    }
};
