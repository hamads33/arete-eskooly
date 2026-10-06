<?php
declare(strict_types=1);

use App\Core\{App,Database,Request};
use App\Services\AttendanceService;
use App\Repositories\AttendanceRepository;
use App\Services\Sms\{PhoneNumber,SmsAutomation,SmsDispatcher,SmsGateway,SmsQueue,SmsSettings,SmsTemplateRenderer,SmsText};

/**
 * SMS Center: phone normalisation, templates, queue, gateway API, reservation safety,
 * retries, bulk, automations, limits and permissions.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['sms: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $session = $app->session();
    $adminContext = (new \App\Repositories\UserRepository($db))->findAuthContext($admin);
    $session->set('auth_user', $adminContext);

    // Isolate: remember the settings row and everything that exists now, restore at the end.
    $savedSettings = $db->query('SELECT * FROM sms_settings WHERE institute_id=:i', ['i' => $institute])->fetch();
    $maxMessage = (int) $db->query('SELECT COALESCE(MAX(id),0) FROM sms_messages')->fetchColumn();
    $maxLog = (int) $db->query('SELECT COALESCE(MAX(id),0) FROM sms_logs')->fetchColumn();
    $maxDevice = (int) $db->query('SELECT COALESCE(MAX(id),0) FROM sms_gateway_devices')->fetchColumn();
    $settings = new SmsSettings($db);
    $base = SmsSettings::DEFAULTS;
    $configure = static function (array $overrides) use ($db, $institute, $admin, $base): void {
        (new SmsSettings($db))->save($institute, array_replace($base, $overrides), $admin);
    };
    $configure([]);
    // Park anything already queued so reservation tests only see this test's messages.
    $parked = $db->query("SELECT id,status FROM sms_messages WHERE institute_id=:i AND status IN('queued','scheduled','reserved','processing') AND id<=:m", ['i' => $institute, 'm' => $maxMessage])->fetchAll();
    $db->query("UPDATE sms_messages SET status='cancelled' WHERE institute_id=:i AND status IN('queued','scheduled','reserved','processing') AND id<=:m", ['i' => $institute, 'm' => $maxMessage]);

    try {
        // ---- phone numbers ----------------------------------------------------------------
        foreach (['03001234567', '3001234567', '+923001234567', '923001234567', '0300-123 4567', '0092 300 1234567'] as $in) {
            $check("normalises {$in}", PhoneNumber::normalize($in) === '+923001234567');
        }
        $check('keeps an international number', PhoneNumber::normalize('+447911123456') === '+447911123456');
        $bad = 0;
        foreach (['', '12345', '0421234567', 'abc0300', '+92300123'] as $in) {
            try { PhoneNumber::normalize($in); } catch (\DomainException) { $bad++; }
        }
        $check('rejects invalid and landline numbers', $bad === 5);
        $check('uses the configured country code', PhoneNumber::normalize('07911123456', '44') === '+447911123456');

        // ---- text and templates -----------------------------------------------------------
        $check('GSM text fits 160 in one segment', SmsText::analyse(str_repeat('a', 160))['segments'] === 1 && SmsText::analyse(str_repeat('a', 161))['segments'] === 2);
        $u = SmsText::analyse('آج اسکول بند ہے');
        $check('Urdu is detected as unicode', $u['unicode'] && $u['segments'] === 1);
        $check('renders known variables', SmsTemplateRenderer::render('Hi {{student_name}}, {{ school_name }}', ['student_name' => 'Ali', 'school_name' => 'Arete']) === 'Hi Ali, Arete');
        $threw = false;
        try { SmsTemplateRenderer::validate('Hello {{password}}'); } catch (\DomainException) { $threw = true; }
        $check('rejects unknown variables', $threw);
        $threw = false;
        try { SmsTemplateRenderer::render('Fee {{fee_amount}}', ['student_name' => 'Ali']); } catch (\DomainException) { $threw = true; }
        $check('rejects a variable with no value', $threw);

        // ---- queue ------------------------------------------------------------------------
        $queue = new SmsQueue($db);
        $m1 = $queue->enqueue($institute, ['phone_number' => '0300 1234567', 'message' => 'Queue test ' . $suffix]);
        $row = $db->query('SELECT * FROM sms_messages WHERE id=:id', ['id' => $m1['id']])->fetch();
        $check('queues with a normalised number', $m1['created'] && $row['status'] === 'queued' && $row['phone_number'] === '+923001234567');
        $d1 = $queue->enqueue($institute, ['phone_number' => '03001234567', 'message' => 'Dedupe', 'dedupe_key' => 'test:' . $suffix]);
        $d2 = $queue->enqueue($institute, ['phone_number' => '03001234567', 'message' => 'Dedupe', 'dedupe_key' => 'test:' . $suffix]);
        $check('dedupe key never queues twice', $d1['created'] && !$d2['created'] && $d1['id'] === $d2['id']);
        $threw = false;
        try { $queue->enqueue($institute, ['phone_number' => '03001234567', 'message' => 'Hi {{student_name}}']); } catch (\DomainException) { $threw = true; }
        $check('refuses a message with an unfilled variable', $threw);

        // ---- devices and gateway API ------------------------------------------------------
        $gw = new SmsGateway($db, $settings);
        $call = static function (string $method, string $uri, array $body = [], ?string $token = null) use ($app): array {
            $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '10.9.8.7'];
            if ($token !== null) {
                $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
            }
            parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
            $response = $app->run(new Request($query, $body, [], $server));
            return [$response->status(), json_decode($response->content(), true) ?? []];
        };
        $devA = $gw->create($institute, 'Test phone A ' . $suffix, null, $admin);
        $devB = $gw->create($institute, 'Test phone B ' . $suffix, '2', $admin);
        $codeA = $gw->issuePairingCode($institute, $devA, $admin)['code'];
        [$st, $reg] = $call('POST', '/api/sms-gateway/v1/register', ['pairing_code' => $codeA, 'device_uuid' => 'uuid-a-' . $suffix, 'model' => 'Pixel 7', 'android_version' => '14']);
        $tokenA = $reg['token'] ?? '';
        $check('pairing code registers a device and returns a token', $st === 201 && str_starts_with($tokenA, 'smsgw_'));
        $check('only the token hash is stored', !$db->query('SELECT 1 FROM sms_gateway_tokens WHERE token_hash=:t', ['t' => $tokenA])->fetchColumn()
            && (bool) $db->query('SELECT 1 FROM sms_gateway_tokens WHERE token_hash=:t', ['t' => hash('sha256', $tokenA)])->fetchColumn());
        [$st] = $call('POST', '/api/sms-gateway/v1/register', ['pairing_code' => $codeA, 'device_uuid' => 'uuid-x-' . $suffix]);
        $check('a pairing code works only once', $st === 401);
        $tokenB = $gw->issueAccessToken($institute, $devB, $admin);
        [$st] = $call('GET', '/api/sms-gateway/v1/config', [], 'smsgw_not_a_real_token');
        $check('an unknown token is rejected', $st === 401);
        [$st, $hb] = $call('POST', '/api/sms-gateway/v1/heartbeat', ['battery_level' => 81, 'network' => 'wifi', 'phone_number' => '03211234567', 'app_version' => '1.0.0'], $tokenA);
        $devRow = $gw->find($institute, $devA);
        $check('heartbeat marks the device connected', $st === 200 && ($hb['success'] ?? false) && SmsGateway::liveStatus($devRow, 5) === 'connected' && $devRow['phone_number'] === '+923211234567' && (int) $devRow['battery_level'] === 81);
        [$st, $cfg] = $call('GET', '/api/sms-gateway/v1/config', [], $tokenA);
        $check('config endpoint answers an authenticated device', $st === 200 && isset($cfg['config']['batch_size']));

        // ---- reservation --------------------------------------------------------------------
        $ids = [];
        for ($n = 0; $n < 3; $n++) {
            $ids[] = $queue->enqueue($institute, ['phone_number' => '0300765432' . $n, 'message' => "Reserve {$n} {$suffix}", 'priority' => $n === 2 ? 'high' : 'normal'])['id'];
        }
        [$st, $gotA] = $call('GET', '/api/sms-gateway/v1/messages?limit=2', [], $tokenA);
        $aIds = array_column($gotA['messages'] ?? [], 'id');
        [$st, $gotB] = $call('GET', '/api/sms-gateway/v1/messages?limit=10', [], $tokenB);
        $bIds = array_column($gotB['messages'] ?? [], 'id');
        $check('device A receives a batch', count($aIds) === 2 && in_array($ids[2], $aIds, true));
        $check('high priority is handed out first', ($aIds[0] ?? null) === $ids[2]);
        $check('two devices never receive the same message', !array_intersect($aIds, $bIds) && count(array_unique(array_merge($aIds, $bIds))) === count($aIds) + count($bIds));
        $keys = array_keys($gotA['messages'][0] ?? []);
        sort($keys);
        $check('API exposes only id, phone, message, priority and SIM', $keys === ['id', 'message', 'phone_number', 'priority', 'sim_slot']);

        // A row locked by another transaction is skipped, not waited on or double-assigned.
        $lockId = $queue->enqueue($institute, ['phone_number' => '03009998887', 'message' => 'Locked ' . $suffix])['id'];
        $freeId = $queue->enqueue($institute, ['phone_number' => '03009998886', 'message' => 'Free ' . $suffix])['id'];
        $other = new Database($app->config('database'));
        $other->connection()->beginTransaction();
        $other->query('SELECT id FROM sms_messages WHERE id=:id FOR UPDATE', ['id' => $lockId]);
        $gotLocked = array_column($queue->reserve($gw->authenticate($tokenB), 10), 'id');
        $other->connection()->rollBack();
        $check('a message locked by a concurrent reservation is skipped', in_array($freeId, $gotLocked, true) && !in_array($lockId, $gotLocked, true));

        // ---- callbacks ------------------------------------------------------------------------
        $sentId = $aIds[0];
        [$st] = $call('POST', "/api/sms-gateway/v1/messages/{$sentId}/processing", [], $tokenA);
        $check('processing callback', $st === 200 && $db->query('SELECT status FROM sms_messages WHERE id=:id', ['id' => $sentId])->fetchColumn() === 'processing');
        [$st] = $call('POST', "/api/sms-gateway/v1/messages/{$sentId}/sent", [], $tokenB);
        $check('another device cannot report on a message it does not hold', $st === 409);
        [$st, $sent] = $call('POST', "/api/sms-gateway/v1/messages/{$sentId}/sent", ['sent_at' => date(DATE_ATOM), 'sim_slot' => 1, 'android_message_reference' => 'ref-' . $suffix], $tokenA);
        $sentRow = $db->query('SELECT * FROM sms_messages WHERE id=:id', ['id' => $sentId])->fetch();
        $check('sent callback marks the message sent', $st === 200 && $sentRow['status'] === 'sent' && $sentRow['sent_at'] !== null && $sentRow['external_reference'] === 'ref-' . $suffix);
        [$st] = $call('POST', "/api/sms-gateway/v1/messages/{$sentId}/sent", [], $tokenA);
        $check('a repeated sent callback is harmless', $st === 200);

        $retryId = $aIds[1];
        [$st, $f1] = $call('POST', "/api/sms-gateway/v1/messages/{$retryId}/failed", ['error_code' => 'NO_SIGNAL', 'error_message' => 'Mobile network unavailable'], $tokenA);
        $rRow = $db->query('SELECT * FROM sms_messages WHERE id=:id', ['id' => $retryId])->fetch();
        $check('a temporary failure is queued for retry', $st === 200 && ($f1['will_retry'] ?? false) && $rRow['status'] === 'queued' && (int) $rRow['retry_count'] === 1 && $rRow['available_at'] > $now && $rRow['gateway_device_id'] === null);
        $check('a retry waits for the retry delay', !in_array($retryId, array_column($queue->reserve($gw->authenticate($tokenA), 10), 'id'), true));
        $permId = $bIds[0] ?? $ids[0];
        [$st, $f2] = $call('POST', "/api/sms-gateway/v1/messages/{$permId}/failed", ['error_code' => 'INVALID_NUMBER', 'error_message' => 'Bad number'], $tokenB);
        $check('a permanent failure is not retried', $st === 200 && ($f2['status'] ?? '') === 'failed' && $db->query('SELECT failure_code FROM sms_messages WHERE id=:id', ['id' => $permId])->fetchColumn() === 'INVALID_NUMBER');
        $db->query("UPDATE sms_messages SET status='reserved',gateway_device_id=:d,retry_count=max_retries,available_at=NULL WHERE id=:id", ['d' => $devA, 'id' => $retryId]);
        $queue->markFailed($gw->authenticate($tokenA), $retryId, 'NO_SIGNAL', 'still no signal');
        $check('retries stop after max retries', $db->query('SELECT status FROM sms_messages WHERE id=:id', ['id' => $retryId])->fetchColumn() === 'failed');
        $queue->retry($institute, $permId);
        $check('admin retry puts a failed message back in the queue', $db->query('SELECT status FROM sms_messages WHERE id=:id', ['id' => $permId])->fetchColumn() === 'queued');

        // ---- revocation / disable ------------------------------------------------------------
        $gw->revokeTokens($institute, $devB);
        [$st] = $call('GET', '/api/sms-gateway/v1/messages', [], $tokenB);
        $check('a revoked token is rejected', $st === 401);
        $gw->setActive($institute, $devA, false);
        [$st, $dis] = $call('GET', '/api/sms-gateway/v1/messages', [], $tokenA);
        $check('a disabled device is refused', $st === 403 && ($dis['error']['code'] ?? '') === 'DEVICE_DISABLED');
        $gw->setActive($institute, $devA, true);

        // ---- scheduled, stale, quiet hours, limits --------------------------------------------
        $device = $gw->authenticate($tokenA);
        $db->query("UPDATE sms_messages SET status='cancelled' WHERE institute_id=:i AND status IN('queued','scheduled') AND id>:m", ['i' => $institute, 'm' => $maxMessage]);
        $sch = $queue->enqueue($institute, ['phone_number' => '03001112223', 'message' => 'Later ' . $suffix, 'scheduled_at' => date('Y-m-d H:i:s', time() + 3600)])['id'];
        $check('a scheduled message waits', $db->query('SELECT status FROM sms_messages WHERE id=:id', ['id' => $sch])->fetchColumn() === 'scheduled' && !in_array($sch, array_column($queue->reserve($device, 10), 'id'), true));
        $db->query('UPDATE sms_messages SET scheduled_at=:t WHERE id=:id', ['t' => date('Y-m-d H:i:s', time() - 60), 'id' => $sch]);
        $check('a scheduled message is released at its time', in_array($sch, array_column($queue->reserve($device, 10), 'id'), true));
        $db->query("UPDATE sms_messages SET reserved_at=:t WHERE id=:id", ['t' => date('Y-m-d H:i:s', time() - 3600), 'id' => $sch]);
        $queue->releaseStale($institute);
        $check('a stale reservation returns to the queue', $db->query('SELECT status FROM sms_messages WHERE id=:id', ['id' => $sch])->fetchColumn() === 'queued');
        $configure(['quiet_hours_enabled' => true, 'quiet_hours_start' => date('H:i', time() - 3600), 'quiet_hours_end' => date('H:i', time() + 3600)]);
        $check('nothing is handed out during quiet hours', (new SmsQueue($db))->reserve($device, 10) === []);
        $usage = (new SmsQueue($db))->usage($institute)['today'];
        $configure(['daily_limit' => $usage + 1]);
        $limited = new SmsQueue($db);
        $limited->enqueue($institute, ['phone_number' => '03004445556', 'message' => 'Within limit']);
        $threw = false;
        try { $limited->enqueue($institute, ['phone_number' => '03004445557', 'message' => 'Over limit']); } catch (\DomainException) { $threw = true; }
        $check('the daily limit is enforced', $threw);
        $configure([]);

        // ---- bulk ------------------------------------------------------------------------------
        $academic = new \App\Repositories\AcademicRepository($db);
        $academic->createClass($institute, 'SMS ' . $suffix, 1000, 'Nobody');
        $classId = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'SMS ' . $suffix])->fetchColumn();
        $students = [];
        foreach ([['Aamir', '03001230001'], ['Bilal', '03001230002'], ['Chand', '']] as [$name, $phone]) {
            $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,father_name,father_mobile,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:f,:m,"active",:x,:x2)',
                ['i' => $institute, 'c' => $classId, 'r' => 'SMS-' . $name . '-' . $suffix, 'n' => $name . ' ' . $suffix, 'd' => $today, 'f' => 'Father ' . $name, 'm' => $phone, 'x' => $now, 'x2' => $now]);
            $students[$name] = (int) $pdo->lastInsertId();
        }
        $sms = new SmsDispatcher($db);
        $preview = $sms->bulkPreview($institute, 'parents', ['filter' => 'class', 'class_id' => $classId], 'Dear {{parent_name}}, {{student_name}} - {{school_name}}');
        $check('bulk preview counts valid and missing numbers', $preview['total'] === 3 && count($preview['valid']) === 2 && count($preview['skipped']) === 1);
        $bulk = $sms->bulkQueue($institute, 'parents', ['filter' => 'class', 'class_id' => $classId], 'Dear {{parent_name}}, {{student_name}} - {{school_name}}', [], $admin);
        $batchRows = $db->query('SELECT recipient_name,message,phone_number FROM sms_messages WHERE batch_id=:b ORDER BY id', ['b' => $bulk['batch_id']])->fetchAll();
        $check('bulk queues one row per recipient', $bulk['queued'] === 2 && count($batchRows) === 2 && str_contains($batchRows[0]['message'], 'Father Aamir') && $batchRows[1]['phone_number'] === '+923001230002');

        // ---- compose ---------------------------------------------------------------------------
        $cid = $sms->compose($institute, ['recipient_type' => 'guardian', 'recipient_id' => $students['Aamir'], 'message' => 'Hello {{parent_name}} of {{student_name}}'], $admin);
        $crow = $db->query('SELECT * FROM sms_messages WHERE id=:id', ['id' => $cid])->fetch();
        $check('compose fills the parent number and variables', $crow['phone_number'] === '+923001230001' && $crow['message'] === 'Hello Father Aamir of Aamir ' . $suffix && $crow['recipient_type'] === 'guardian');

        // ---- attendance automation ----------------------------------------------------------------
        $automation = new SmsAutomation($db);
        $automation->ensureDefaults($institute);
        $tpl = (int) $db->query("SELECT id FROM sms_templates WHERE institute_id=:i AND slug='attendance-absent'", ['i' => $institute])->fetchColumn();
        $automation->saveRule($institute, 'attendance_absent', ['enabled' => 1, 'template_id' => $tpl, 'statuses' => ['Absent']], $admin);
        $attendance = new AttendanceService(new AttendanceRepository($db));
        $ids3 = array_values($students);
        $mark = static fn(string $aamir) => $attendance->saveStudents($today, $ids3, [$students['Aamir'] => $aamir, $students['Bilal'] => 'Present', $students['Chand'] => 'Absent'], [], $admin);
        $alerts = static fn() => $db->query("SELECT id,status FROM sms_messages WHERE institute_id=:i AND dedupe_key=:k", ['i' => $institute, 'k' => "attendance:{$students['Aamir']}:{$today}"])->fetchAll();
        $mark('Absent');
        $mark('Absent');
        $a = $alerts();
        $check('absence queues one alert even when attendance is saved twice', count($a) === 1 && $a[0]['status'] === 'queued');
        $check('a student without a parent number is skipped, not failed', !$db->query("SELECT 1 FROM sms_messages WHERE dedupe_key=:k", ['k' => "attendance:{$students['Chand']}:{$today}"])->fetchColumn());
        $mark('Present');
        $check('correcting to present cancels the unsent alert', $alerts()[0]['status'] === 'cancelled');
        $mark('Absent');
        $a = $alerts();
        $check('marking absent again revives the same alert', count($a) === 1 && $a[0]['status'] === 'queued');
        $automation->saveRule($institute, 'attendance_absent', ['enabled' => 0, 'template_id' => $tpl], $admin);

        // ---- permissions ----------------------------------------------------------------------------
        $get = static fn(string $uri) => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
        $session->set('auth_user', $adminContext);
        $pagesOk = true;
        foreach (['/admin/sms', '/admin/sms/compose', '/admin/sms/bulk', '/admin/sms/templates', '/admin/sms/templates/create', '/admin/sms/queue', '/admin/sms/sent', '/admin/sms/failed',
                  '/admin/sms/scheduled', '/admin/sms/automations', '/admin/sms/devices', '/admin/sms/settings', '/admin/sms/logs', '/admin/sms/messages/' . $cid] as $page) {
            $res = $get($page);
            if ($res->status() !== 200 || str_contains($res->content(), 'Warning:')) {
                $pagesOk = false;
            }
        }
        $check('every SMS Center page renders for the admin', $pagesOk);
        $check('the gateway page never shows a stored token', !str_contains($get('/admin/sms/devices')->content(), $tokenA));
        $check('paired devices offer an administrator remove action', str_contains($get('/admin/sms/devices')->content(), 'Remove Device'));
        $removable = $gw->create($institute, 'Removable phone ' . $suffix, null, $admin);
        $removableToken = $gw->issueAccessToken($institute, $removable, $admin);
        $historyId = $queue->enqueue($institute, ['phone_number' => '03001239999', 'message' => 'Preserved history ' . $suffix, 'skip_limits' => true])['id'];
        $db->query("UPDATE sms_messages SET status='sent',gateway_device_id=:d,sent_at=:n WHERE id=:id", ['d' => $removable, 'n' => $now, 'id' => $historyId]);
        $gw->delete($institute, $removable);
        $history = $db->query('SELECT status,gateway_device_id FROM sms_messages WHERE id=:id', ['id' => $historyId])->fetch();
        $check('administrator can remove a paired device after it sent messages', $gw->find($institute, $removable) === null && $history['status'] === 'sent' && $history['gateway_device_id'] === null);
        try { $gw->authenticate($removableToken); $removedTokenWorks = true; } catch (\Throwable) { $removedTokenWorks = false; }
        $check('removing a paired device revokes its Android access', !$removedTokenWorks);
        // Real users: AuthMiddleware reloads roles and permissions from the database on every request.
        $roleId = (new \App\Repositories\RoleRepository($db))->create('SMS Viewer ' . $suffix, 'SMS test role');
        $db->query("INSERT INTO role_permissions(role_id,permission_id) SELECT :r,id FROM permissions WHERE slug='sms.view'", ['r' => $roleId]);
        $makeUser = static function (string $email, ?int $role, ?string $roleSlug) use ($db, $pdo, $now, $institute): int {
            $db->query("INSERT INTO users(name,email,password_hash,status,created_at,updated_at) VALUES('SMS Test',:e,'x','active',:c,:u)", ['e' => $email, 'c' => $now, 'u' => $now]);
            $id = (int) $pdo->lastInsertId();
            if ($role) $db->query('INSERT INTO user_roles(user_id,role_id) VALUES(:u,:r)', ['u' => $id, 'r' => $role]);
            if ($roleSlug) $db->query('INSERT INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug=:s', ['u' => $id, 's' => $roleSlug]);
            $db->query('INSERT INTO institute_users(user_id,institute_id,created_at) VALUES(:u,:i,:c)', ['u' => $id, 'i' => $institute, 'c' => $now]);
            return $id;
        };
        $viewerId = $makeUser('sms.viewer.' . $suffix . '@example.test', $roleId, null);
        $studentUserId = $makeUser('sms.student.' . $suffix . '@example.test', null, 'student');
        $as = static fn(int $id) => $session->set('auth_user', (new \App\Repositories\UserRepository($db))->findAuthContext($id));
        $as($viewerId);
        $check('sms.view can open the dashboard', $get('/admin/sms')->status() === 200);
        $check('sms.view alone cannot compose', $get('/admin/sms/compose')->status() === 403);
        $check('sms.view alone cannot open settings', $get('/admin/sms/settings')->status() === 403);
        $check('sms.view alone cannot change the queue', $app->run(new Request([], ['_token' => \App\Core\CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/sms/messages/' . $cid . '/cancel']))->status() === 403);
        $as($studentUserId);
        $check('a student cannot open the SMS Center', $get('/admin/sms')->status() === 403);
        $session->set('auth_user', $adminContext);
    } finally {
        // ---- cleanup ----------------------------------------------------------------------------
        $session->set('auth_user', $adminContext);
        $db->query('DELETE FROM sms_logs WHERE id>:m', ['m' => $maxLog]);
        $db->query('DELETE FROM sms_messages WHERE id>:m', ['m' => $maxMessage]);
        // Put back anything that was queued before the test started.
        foreach ($parked as $p) {
            $db->query('UPDATE sms_messages SET status=:s WHERE id=:id', ['s' => $p['status'], 'id' => $p['id']]);
        }
        $db->query('DELETE FROM sms_gateway_devices WHERE id>:m', ['m' => $maxDevice]);
        $db->query("DELETE FROM sms_api_rate_limits WHERE bucket LIKE 'register:%' OR bucket LIKE 'device:%'");
        if (isset($students)) {
            foreach ($students as $sid) {
                $db->query('DELETE FROM student_attendance WHERE student_id=:s', ['s' => $sid]);
                $db->query('DELETE FROM students WHERE id=:s', ['s' => $sid]);
            }
        }
        if (!empty($classId)) {
            $db->query('DELETE FROM classes WHERE id=:c', ['c' => $classId]);
        }
        foreach ([$viewerId ?? 0, $studentUserId ?? 0] as $uid) {
            if ($uid) {
                $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $uid]);
                $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $uid]);
                $db->query('DELETE FROM users WHERE id=:u', ['u' => $uid]);
            }
        }
        if (!empty($roleId)) {
            $db->query('DELETE FROM role_permissions WHERE role_id=:r', ['r' => $roleId]);
            $db->query('DELETE FROM role_meta WHERE role_id=:r', ['r' => $roleId]);
            $db->query('DELETE FROM roles WHERE id=:r', ['r' => $roleId]);
        }
        $db->query('DELETE FROM sms_settings WHERE institute_id=:i', ['i' => $institute]);
        if ($savedSettings) {
            $cols = array_keys($savedSettings);
            $db->query('INSERT INTO sms_settings(' . implode(',', $cols) . ') VALUES(:' . implode(',:', $cols) . ')', $savedSettings);
        }
    }
    return $out;
};
