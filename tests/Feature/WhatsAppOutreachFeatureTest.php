<?php
declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\UserRepository;
use App\Services\WhatsAppService;

/**
 * Click-to-WhatsApp outreach: the buttons rendered against parent contacts, the logged
 * open endpoint, and the guards around it.
 *
 * Complements PakistanErpMustHaveFeatureTest, which covers the provider abstraction.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out[$n] = $v; };
    $db = $app->database();
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($admin));
    $get = static fn (string $uri): \App\Core\Response => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $post = static fn (string $uri, array $body): \App\Core\Response => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));

    // ---- Pakistani number normalisation -------------------------------------
    $service = new WhatsAppService();
    $check('local 03xx number normalises', $service->normalize('0300-123 4567') === '923001234567');
    $check('bare 3xx number normalises', $service->normalize('3001234567') === '923001234567');
    $check('plus-prefixed number normalises', $service->normalize('+92 300 1234567') === '923001234567');
    $check('already-normalised number is unchanged', $service->normalize('923001234567') === '923001234567');

    $rejected = false;
    try {
        $service->normalize('not-a-number');
    } catch (\DomainException) {
        $rejected = true;
    }
    $check('invalid number is rejected', $rejected);

    // A valid international number must not be mangled into a Pakistani one.
    $check('international number is preserved', $service->normalize('+44 7700 900123') === '447700900123');

    // ---- click-to-chat URL ---------------------------------------------------
    $url = $service->clickUrl('03001234567', 'Fee due for ' . $suffix);
    $check('click url targets wa.me with the normalised number', str_starts_with($url, 'https://wa.me/923001234567?text='));
    $check('click url encodes the message', str_contains($url, rawurlencode('Fee due for ' . $suffix)));

    // ---- template rendering --------------------------------------------------
    $rendered = $service->render('Dear {{father_name}}, {{student_name}} owes {{remaining}}.', [
        'father_name' => 'Ali', 'student_name' => 'Sara', 'remaining' => 'Rs 500',
    ]);
    $check('template placeholders are substituted', $rendered === 'Dear Ali, Sara owes Rs 500.');
    $check('unknown placeholder renders empty, not raw', $service->render('A{{nope}}B', []) === 'AB');

    // ---- seeded templates ----------------------------------------------------
    $templates = (int) $db->query('SELECT COUNT(*) FROM whatsapp_templates')->fetchColumn();
    $check('default whatsapp templates are seeded', $templates >= 10);
    $check('fee reminder template exists', (bool) $db->query("SELECT 1 FROM whatsapp_templates WHERE event_key='fee_defaulter_reminder' LIMIT 1")->fetchColumn());

    // ---- open endpoint -------------------------------------------------------
    $check('open endpoint enforces CSRF', $post('/admin/whatsapp/open', ['phone' => '03001234567', 'message' => 'x'])->status() === 419);

    $before = (int) $db->query('SELECT COUNT(*) FROM whatsapp_logs')->fetchColumn();
    $response = $post('/admin/whatsapp/open', ['_token' => CSRF::token(), 'phone' => '03001234567', 'message' => 'Reminder ' . $suffix]);
    $after = (int) $db->query('SELECT COUNT(*) FROM whatsapp_logs')->fetchColumn();
    $check('open endpoint redirects to the chat', $response->status() === 302);
    $check('outreach attempt is logged', $after === $before + 1);

    $logged = $db->query('SELECT normalized_phone, message_hash FROM whatsapp_logs ORDER BY id DESC LIMIT 1')->fetch();
    $check('log stores the normalised number', is_array($logged) && $logged['normalized_phone'] === '923001234567');
    $check('log stores a hash rather than the message body', is_array($logged) && !str_contains((string) $logged['message_hash'], $suffix));

    $badBefore = (int) $db->query('SELECT COUNT(*) FROM whatsapp_logs')->fetchColumn();
    $post('/admin/whatsapp/open', ['_token' => CSRF::token(), 'phone' => 'abc', 'message' => 'x']);
    $check('invalid number is not logged as an outreach', (int) $db->query('SELECT COUNT(*) FROM whatsapp_logs')->fetchColumn() === $badBefore);

    // ---- buttons rendered against parent contacts ----------------------------
    $student = $db->query('SELECT id, father_mobile, father_name FROM students ORDER BY id LIMIT 1')->fetch();
    if (is_array($student)) {
        $db->query('UPDATE students SET father_mobile=:m, father_name=:n WHERE id=:s', ['m' => '03001234567', 'n' => 'WA Parent', 's' => $student['id']]);
        $check('students list offers a WhatsApp button for the parent', str_contains($get('/admin/students')->content(), '/admin/whatsapp/open'));
        $db->query('UPDATE students SET father_mobile=:m, father_name=:n WHERE id=:s', ['m' => $student['father_mobile'], 'n' => $student['father_name'], 's' => $student['id']]);
    }

    $db->query('DELETE FROM whatsapp_logs WHERE normalized_phone=:p', ['p' => '923001234567']);

    return $out;
};
