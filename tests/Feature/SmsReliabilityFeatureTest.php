<?php
declare(strict_types=1);

use App\Core\{App, CSRF, Request};
use App\Repositories\{AcademicRepository, FeeRepository, StudentRepository};
use App\Services\FeeService;
use App\Services\Sms\{SmsAutomation, SmsSettings, SmsTemplateRenderer, SmsTemplates};

/**
 * Regressions from the production SMS investigation (2026-10-05): fee-received SMS were skipped for
 * families whose only number is the student's Mobile Number, and for every payment once the template
 * used {{fee_month}} / {{date}}. Each automation must be able to fill every variable it allows.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['sms reliability: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $app->session()->set('auth_user', (new \App\Repositories\UserRepository($db))->findAuthContext($admin));
    $post = static fn(string $uri, array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $get = static fn(string $uri) => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $suffix = bin2hex(random_bytes(4));
    $now = date('Y-m-d H:i:s');

    // isolate: restore settings, rules and the payment template afterwards
    $savedSettings = $db->query('SELECT * FROM sms_settings WHERE institute_id=:i', ['i' => $institute])->fetch();
    (new SmsSettings($db))->save($institute, SmsSettings::DEFAULTS, $admin);
    $auto = new SmsAutomation($db);
    $auto->ensureDefaults($institute);
    $savedRules = $db->query('SELECT event_type,template_id,enabled,conditions_json FROM sms_automation_rules WHERE institute_id=:i', ['i' => $institute])->fetchAll();
    $tplId = (int) $db->query("SELECT id FROM sms_templates WHERE institute_id=:i AND slug='fee-payment'", ['i' => $institute])->fetchColumn();
    $savedTpl = $db->query('SELECT name,category,message_body,is_active FROM sms_templates WHERE id=:id', ['id' => $tplId])->fetch();
    $templates = new SmsTemplates($db);
    $studentIds = []; $class = 0;
    try {
        // every default template fits the automation that uses it
        $fits = true;
        foreach ($db->query('SELECT r.event_type,t.message_body FROM sms_automation_rules r JOIN sms_templates t ON t.id=r.template_id WHERE r.institute_id=:i', ['i' => $institute])->fetchAll() as $r) {
            try { SmsAutomation::assertTemplateFits($r['event_type'], $r['message_body']); } catch (\DomainException) { $fits = false; }
        }
        $check('every automation\'s template only uses variables that automation fills', $fits);

        // the client's real Payment Confirmation template (with {{fee_month}}, {{date}} and "Rs. {{amount_paid}}")
        $body = "Dear Parent/Guardian,\n\nFee received for {{student_name}}.\nFee Month: {{fee_month}}\nAmount Paid: Rs. {{amount_paid}}\nPayment Date: {{date}}\nReceipt No.: {{receipt_no}}\n\nArete Coaching Center";
        $templates->save($institute, $tplId, ['name' => $savedTpl['name'], 'category' => $savedTpl['category'], 'message_body' => $body, 'is_active' => true], $admin);
        $auto->saveRule($institute, 'fee_payment', ['enabled' => 1, 'template_id' => $tplId], $admin);

        (new AcademicRepository($db))->createClass($institute, 'SMSQ ' . $suffix, 3000, null);
        $class = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'SMSQ ' . $suffix])->fetchColumn();
        $mk = static function (string $name, ?string $father, ?string $mobile) use ($db, $pdo, $institute, $class, $suffix, $now, &$studentIds): int {
            $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,father_name,father_mobile,mobile,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:f,:fm,:m,"active",:x,:x2)',
                ['i' => $institute, 'c' => $class, 'r' => 'SMSQ-' . $name . '-' . $suffix, 'n' => $name . ' ' . $suffix, 'd' => date('Y-m-01'), 'f' => 'Father ' . $name, 'fm' => $father, 'm' => $mobile, 'x' => $now, 'x2' => $now]);
            return $studentIds[] = (int) $pdo->lastInsertId();
        };
        $onlyMobile = $mk('OnlyMobile', null, '03007770001');
        $noNumber = $mk('NoNumber', null, null);
        $students = new StudentRepository($db);
        $fees = new FeeService(new FeeRepository($db), $students);
        $month = date('Y-m');
        $sms = static fn(int $payment) => $db->query('SELECT * FROM sms_messages WHERE dedupe_key=:k', ['k' => 'payment:' . $payment])->fetch();

        // 1. the student's Mobile Number is used when no parent number exists
        $p1 = $fees->collect($students->find($onlyMobile, $institute), $institute, $month, 1000, 'Cash', null, $admin, date('Y-m-d', strtotime('-2 days')));
        $m1 = $sms($p1);
        $check('a family whose only number is the student Mobile Number gets the fee-received SMS', $m1 && $m1['phone_number'] === '+923007770001' && $m1['status'] === 'queued');
        $check('the SMS fills {{fee_month}} with the invoice month', $m1 && str_contains($m1['message'], 'Fee Month: ' . date('F Y')));
        $check('{{date}} is the (back-dated) payment date, not today', $m1 && str_contains($m1['message'], 'Payment Date: ' . date('d M Y', strtotime('-2 days'))));
        $check('"Rs. {{amount_paid}}" does not print the currency twice', $m1 && str_contains($m1['message'], 'Amount Paid: Rs. 1,000') && !preg_match('/Rs\.\s*(Pkr|Rs)/i', $m1['message']));
        $check('the payment itself is saved with the back date', $db->query('SELECT payment_date FROM fee_payments WHERE id=:i', ['i' => $p1])->fetchColumn() === date('Y-m-d', strtotime('-2 days')));
        $check('nothing was skipped for that payment', SmsAutomation::$lastSkipped === []);

        // 2. a family with no number at all: skipped, and the reason is reported
        $p2 = $fees->collect($students->find($noNumber, $institute), $institute, $month, 500, 'Cash', null, $admin);
        $check('a family with no number is skipped and the reason is kept for the admin', !$sms($p2) && SmsAutomation::$lastSkipped && str_contains(implode(' ', SmsAutomation::$lastSkipped), 'No phone number'));

        // 3. the receipt shows "Send SMS"; after adding a number it sends the missed SMS, then refuses duplicates
        $page = $get('/admin/fees/receipt/' . $p2)->content();
        $check('the receipt of a payment without an SMS offers "Send SMS"', str_contains($page, '/admin/fees/receipt/' . $p2 . '/sms'));
        $db->query('UPDATE students SET father_mobile=:m WHERE id=:id', ['m' => '03007770002', 'id' => $noNumber]);
        $post('/admin/fees/receipt/' . $p2 . '/sms', []);
        $m2 = $sms($p2);
        $check('"Send SMS" queues the missed fee-received SMS once the number is added', $m2 && $m2['phone_number'] === '+923007770002' && $m2['status'] === 'queued');
        $post('/admin/fees/receipt/' . $p2 . '/sms', []);
        $check('pressing "Send SMS" again does not queue a duplicate', (int) $db->query('SELECT COUNT(*) FROM sms_messages WHERE dedupe_key=:k', ['k' => 'payment:' . $p2])->fetchColumn() === 1);
        $check('a receipt whose SMS is queued shows that instead of the button', str_contains($get('/admin/fees/receipt/' . $p2)->content(), 'SMS queued'));

        // 4. templates that an automation cannot fill are refused (they used to fail silently on every send)
        $refused = false;
        try { $templates->save($institute, $tplId, ['name' => $savedTpl['name'], 'category' => $savedTpl['category'], 'message_body' => 'Result {{exam_name}} for {{student_name}}', 'is_active' => true], $admin); } catch (\DomainException $e) { $refused = str_contains($e->getMessage(), '{{exam_name}}'); }
        $check('editing the payment template to use {{exam_name}} is refused with the usable variables listed', $refused);
        $resultTpl = (int) $db->query("SELECT id FROM sms_templates WHERE institute_id=:i AND slug='result-published'", ['i' => $institute])->fetchColumn();
        $refused = false;
        try { $auto->saveRule($institute, 'fee_payment', ['enabled' => 1, 'template_id' => $resultTpl], $admin); } catch (\DomainException) { $refused = true; }
        $check('attaching the result template to the payment automation is refused', $refused);
        $refused = false;
        try { SmsAutomation::assertTemplateFits('result_published', 'Grade {{grade}}'); } catch (\DomainException) { $refused = true; }
        $check('{{grade}} is not offered to the result SMS because nothing fills it', $refused);

        // 5. collecting a fee for a family without a number warns the admin straight away
        $db->query('UPDATE students SET father_mobile=NULL WHERE id=:id', ['id' => $noNumber]);
        $post('/admin/fees/payment', ['registration' => 'SMSQ-NoNumber-' . $suffix, 'month' => $month, 'payment_amount' => 100, 'payment_method' => 'Cash']);
        $check('Collect Fees warns when the fee-received SMS could not be sent', str_contains((string) json_encode($_SESSION), 'fee-received SMS was NOT sent'));

        // 6. the student form refuses numbers that can never receive an SMS
        foreach (['030155144988' => 'one digit too many', '0320934428' => 'one digit short'] as $bad => $why) {
            $post('/admin/students', ['studentname' => 'Phone QA ' . $suffix, 'reg' => 'PQA-' . $suffix, 'doa' => date('Y-m-d'), 'class' => $class, 'discount_type' => 'percentage', 'discount' => 0, 'Gphone' => $bad]);
            $check("the student form refuses {$bad} ({$why})", !$db->query('SELECT 1 FROM students WHERE registration_no=:r', ['r' => 'PQA-' . $suffix])->fetchColumn());
        }

        // 7. the automations page tells the admin which variables each SMS can use
        $check('the automations page lists the usable variables', str_contains($get('/admin/sms/automations')->content(), 'This SMS can use: {{school_name}}'));
    } finally {
        foreach ($studentIds as $id) {
            $db->query("DELETE FROM sms_messages WHERE dedupe_key IN (SELECT CONCAT('payment:',id) FROM fee_payments WHERE student_id=:s)", ['s' => $id]);
            $db->query('DELETE FROM fee_payments WHERE student_id=:s', ['s' => $id]);
            $db->query('DELETE FROM fee_invoice_items WHERE fee_invoice_id IN (SELECT id FROM fee_invoices WHERE student_id=:s)', ['s' => $id]);
            $db->query('DELETE FROM fee_invoices WHERE student_id=:s', ['s' => $id]);
            $db->query('DELETE FROM students WHERE id=:s', ['s' => $id]);
        }
        $db->query('DELETE FROM students WHERE registration_no=:r', ['r' => 'PQA-' . $suffix]);
        if ($class) $db->query('DELETE FROM classes WHERE id=:c', ['c' => $class]);
        $db->query('UPDATE sms_templates SET name=:n,category=:c,message_body=:b,is_active=:a WHERE id=:id', ['n' => $savedTpl['name'], 'c' => $savedTpl['category'], 'b' => $savedTpl['message_body'], 'a' => $savedTpl['is_active'], 'id' => $tplId]);
        foreach ($savedRules as $r) $db->query('UPDATE sms_automation_rules SET template_id=:t,enabled=:e,conditions_json=:c WHERE institute_id=:i AND event_type=:ev', ['t' => $r['template_id'], 'e' => $r['enabled'], 'c' => $r['conditions_json'], 'i' => $institute, 'ev' => $r['event_type']]);
        $db->query('DELETE FROM sms_settings WHERE institute_id=:i', ['i' => $institute]);
        if ($savedSettings) { $cols = array_keys($savedSettings); $db->query('INSERT INTO sms_settings(' . implode(',', $cols) . ') VALUES(:' . implode(',:', $cols) . ')', $savedSettings); }
    }
    return $out;
};
