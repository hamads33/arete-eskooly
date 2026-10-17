<?php
declare(strict_types=1);

/**
 * Fee SMS now mention the student's total remaining balance ({{balance}}). Only templates still
 * holding the original wording are updated; any template an admin has edited is left as it is.
 */
return static function (PDO $pdo): void {
    if (!$pdo->query("SHOW TABLES LIKE 'sms_templates'")->fetchColumn()) return;
    $changes = [
        'fee-due' => [
            'Dear {{parent_name}}, fee of {{fee_amount}} for {{student_name}} ({{fee_month}}) is due on {{due_date}}. - {{school_name}}',
            'Dear {{parent_name}}, fee of {{fee_amount}} for {{student_name}} ({{fee_month}}) is due on {{due_date}}. Total remaining balance: {{balance}}. - {{school_name}}',
        ],
        'fee-overdue' => [
            'Dear {{parent_name}}, fee of {{fee_amount}} for {{student_name}} ({{fee_month}}) was due on {{due_date}} and is still unpaid. Please clear it at the earliest. - {{school_name}}',
            'Dear {{parent_name}}, fee of {{fee_amount}} for {{student_name}} ({{fee_month}}) was due on {{due_date}} and is still unpaid. Total remaining balance: {{balance}}. Please clear it at the earliest. - {{school_name}}',
        ],
        'fee-payment' => [
            'Dear {{parent_name}}, we received {{amount_paid}} for {{student_name}} (receipt {{receipt_no}}). Thank you. - {{school_name}}',
            'Dear {{parent_name}}, we received {{amount_paid}} for {{student_name}} (receipt {{receipt_no}}). Remaining balance: {{balance}}. Thank you. - {{school_name}}',
        ],
    ];
    $stmt = $pdo->prepare('UPDATE sms_templates SET message_body=:new, variables_json=:vars, updated_at=:now WHERE slug=:slug AND message_body=:old');
    foreach ($changes as $slug => [$old, $new]) {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $new, $m);
        $stmt->execute(['new' => $new, 'vars' => json_encode(array_values(array_unique($m[1]))), 'now' => date('Y-m-d H:i:s'), 'slug' => $slug, 'old' => $old]);
    }

    // Templates the school rewrote over several lines: add one balance line right under the
    // amount line (or the receipt line), in the same "Rs." style. Everything else is kept.
    $anchors = ['fee-due' => ['{{fee_amount}}', 'Total Remaining Balance'], 'fee-overdue' => ['{{fee_amount}}', 'Total Remaining Balance'], 'fee-payment' => ['{{receipt_no}}', 'Remaining Balance']];
    $update = $pdo->prepare('UPDATE sms_templates SET message_body=:b, variables_json=:v, updated_at=:n WHERE id=:id');
    foreach ($pdo->query("SELECT id,slug,message_body FROM sms_templates WHERE slug IN ('fee-due','fee-overdue','fee-payment')")->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $body = (string) $t['message_body'];
        if (str_contains($body, '{{balance}}') || !str_contains($body, "\n")) continue;
        [$anchor, $label] = $anchors[$t['slug']];
        $lines = preg_split('/\r?\n/', $body);
        foreach ($lines as $k => $line) {
            if (!str_contains($line, $anchor)) continue;
            $rs = preg_match('/(Rs\.?|PKR)\s*\{\{/i', $line, $mm) ? $mm[1] . ' ' : '';
            array_splice($lines, $k + 1, 0, [$label . ': ' . $rs . '{{balance}}']);
            $newBody = implode("\n", $lines);
            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $newBody, $m);
            $update->execute(['b' => $newBody, 'v' => json_encode(array_values(array_unique($m[1]))), 'n' => date('Y-m-d H:i:s'), 'id' => $t['id']]);
            break;
        }
    }
};
