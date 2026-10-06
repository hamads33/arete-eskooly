<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\CSRF;
use App\Core\Request;
use App\Repositories\AcademicRepository;
use App\Repositories\OnlineTestRepository;
use App\Services\Sms\SmsAutomation;
use App\Services\Sms\SmsSettings;

/**
 * Publishing online test results sends the Result Published SMS home: once per attempt, whatever
 * the portal visibility, never again on re-publish, and cancelled when the result is withdrawn
 * before the gateway sends it.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['online result sms: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = bin2hex(random_bytes(4));
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $app->session()->set('auth_user', (new \App\Repositories\UserRepository($db))->findAuthContext($admin));
    $post = static fn(string $uri, array $body = []) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $flash = static fn() => (string) ($_SESSION['_flash_next']['notification']['message'] ?? '');
    $msgs = static fn(int $attempt) => $db->query('SELECT id,status,message,source,message_type FROM sms_messages WHERE institute_id=:i AND dedupe_key=:k', ['i' => $institute, 'k' => 'online_result:' . $attempt])->fetchAll();

    $savedSettings = $db->query('SELECT * FROM sms_settings WHERE institute_id=:i', ['i' => $institute])->fetch();
    (new SmsSettings($db))->save($institute, SmsSettings::DEFAULTS, $admin);
    $auto = new SmsAutomation($db);
    $auto->ensureDefaults($institute);
    $savedRule = $db->query("SELECT template_id,enabled FROM sms_automation_rules WHERE institute_id=:i AND event_type='result_published'", ['i' => $institute])->fetch();
    $tplId = (int) $db->query("SELECT id FROM sms_templates WHERE institute_id=:i AND slug='result-published'", ['i' => $institute])->fetchColumn();
    $savedTplActive = $db->query('SELECT is_active FROM sms_templates WHERE id=:id', ['id' => $tplId])->fetchColumn();
    $db->query('UPDATE sms_templates SET is_active=1 WHERE id=:id', ['id' => $tplId]);
    $auto->saveRule($institute, 'result_published', ['enabled' => 1, 'template_id' => $tplId], $admin);

    $repo = new OnlineTestRepository($db);
    $students = []; $testIds = []; $classId = 0;
    try {
        (new AcademicRepository($db))->createClass($institute, 'ORS ' . $suffix, 1000, null);
        $classId = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'ORS ' . $suffix])->fetchColumn();
        (new AcademicRepository($db))->assign($institute, $classId, 'ORS Subject ' . $suffix, 100);
        $subjectId = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $classId])->fetchColumn();
        $mk = static function (string $name, ?string $father) use ($db, $pdo, $institute, $classId, $suffix, $now, &$students): int {
            $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,father_name,father_mobile,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:f,:fm,"active",:x,:x2)',
                ['i' => $institute, 'c' => $classId, 'r' => 'ORS-' . $name . '-' . $suffix, 'n' => $name . ' ' . $suffix, 'd' => date('Y-m-d'), 'f' => 'Father ' . $name, 'fm' => $father, 'x' => $now, 'x2' => $now]);
            return $students[] = (int) $pdo->lastInsertId();
        };
        $withPhone = $mk('Phone', '03001234567');
        $noPhone = $mk('NoPhone', null);
        $testId = $testIds[] = $repo->create($institute, ['title' => 'Chem Ch1 ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 30, 'answer_mode' => 'mixed'], [['text' => 'Q1', 'type' => 'written', 'marks' => 30]], $admin);
        $repo->setStatus($institute, $testId, 'published');
        $attempt = static function (int $student, string $status, ?float $score) use ($db, $pdo, $testId, $now): int {
            $db->query('INSERT INTO online_test_attempts(test_id,student_id,status,started_at,submitted_at,marked_at,total_score) VALUES(:t,:s,:st,:n,:n2,:m,:sc)',
                ['t' => $testId, 's' => $student, 'st' => $status, 'n' => $now, 'n2' => $now, 'm' => $status === 'marked' ? $now : null, 'sc' => $score]);
            return (int) $pdo->lastInsertId();
        };
        $a1 = $attempt($withPhone, 'marked', 24.5);
        $a2 = $attempt($noPhone, 'marked', 10);

        // published to students only: the SMS still goes home
        $post('/admin/online-tests/' . $testId . '/publish', ['visible_to' => 'student']);
        $text = $flash();
        $m = $msgs($a1);
        $check('publishing an online test result queues the Result Published SMS home', count($m) === 1 && $m[0]['status'] === 'queued' && $m[0]['source'] === 'online_test_module' && $m[0]['message_type'] === 'result');
        $check('the SMS names the test and the percentage', $m && str_contains($m[0]['message'], 'Chem Ch1 ' . $suffix) && str_contains($m[0]['message'], '81.7%'));
        $check('a student with no usable number is skipped, not sent blank', $msgs($a2) === []);
        $check('the admin is told how many SMS were queued and who was skipped', str_contains($text, '1 result SMS queued') && str_contains($text, 'NOT sent for 1 student') && str_contains($text, 'NoPhone'));

        $post('/admin/online-tests/' . $testId . '/publish', ['visible_to' => 'both']);
        $check('re-publishing (or widening visibility) never sends a second SMS', count($msgs($a1)) === 1 && !str_contains($flash(), 'result SMS queued'));

        $post('/admin/online-tests/submissions/' . $a1 . '/withdraw');
        $check('withdrawing a result cancels its SMS if it has not gone out', ($msgs($a1)[0]['status'] ?? '') === 'cancelled' && str_contains($flash(), 'cancelled'));
        $post('/admin/online-tests/' . $testId . '/publish', ['visible_to' => 'both']);
        $m = $msgs($a1);
        $check('publishing it again queues that same SMS again (still one row)', count($m) === 1 && $m[0]['status'] === 'queued');

        $db->query("UPDATE sms_messages SET status='sent',sent_at=:n WHERE id=:id", ['n' => $now, 'id' => $m[0]['id']]);
        $post('/admin/online-tests/submissions/' . $a1 . '/withdraw');
        $post('/admin/online-tests/' . $testId . '/publish', ['visible_to' => 'both']);
        $m = $msgs($a1);
        $check('a result SMS that was already sent is never sent twice', count($m) === 1 && $m[0]['status'] === 'sent');

        $db->query("UPDATE sms_automation_rules SET enabled=0 WHERE institute_id=:i AND event_type='result_published'", ['i' => $institute]);
        $a3 = $attempt($mk('Later', '03007654321'), 'marked', 30);
        $post('/admin/online-tests/' . $testId . '/publish', ['visible_to' => 'both']);
        $check('with the automation switched off nothing is queued', $msgs($a3) === []);
    } finally {
        foreach ($testIds as $id) {
            $ids = $db->query('SELECT id FROM online_test_attempts WHERE test_id=:t', ['t' => $id])->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($ids as $aid) $db->query('DELETE FROM sms_messages WHERE institute_id=:i AND dedupe_key=:k', ['i' => $institute, 'k' => 'online_result:' . $aid]);
            $db->query('DELETE FROM online_test_attempts WHERE test_id=:t', ['t' => $id]);
            $db->query('DELETE FROM online_test_questions WHERE test_id=:t', ['t' => $id]);
            $db->query('DELETE FROM online_tests WHERE id=:t', ['t' => $id]);
        }
        foreach ($students as $id) $db->query('DELETE FROM students WHERE id=:s', ['s' => $id]);
        if ($classId) $db->query('DELETE FROM class_subjects WHERE class_id=:c', ['c' => $classId]);
        if ($classId) $db->query('DELETE FROM classes WHERE id=:c', ['c' => $classId]);
        $db->query('UPDATE sms_templates SET is_active=:a WHERE id=:id', ['a' => $savedTplActive, 'id' => $tplId]);
        if ($savedRule) $db->query("UPDATE sms_automation_rules SET template_id=:t,enabled=:e WHERE institute_id=:i AND event_type='result_published'", ['t' => $savedRule['template_id'], 'e' => $savedRule['enabled'], 'i' => $institute]);
        $db->query('DELETE FROM sms_settings WHERE institute_id=:i', ['i' => $institute]);
        if ($savedSettings) { $cols = array_keys($savedSettings); $db->query('INSERT INTO sms_settings(' . implode(',', $cols) . ') VALUES(:' . implode(',:', $cols) . ')', $savedSettings); }
    }
    return $out;
};
