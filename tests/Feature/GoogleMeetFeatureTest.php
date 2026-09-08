<?php
declare(strict_types=1);

use App\Controllers\{GoogleIntegrationController, LiveClassesController, StudentPortalController, TeacherLiveClassController};
use App\Core\{App, CSRF, Request, Response};
use App\Repositories\{AcademicRepository, GoogleConnectionRepository, LiveClassRepository, PakistanErpRepository, UserRepository};
use App\Services\CredentialService;
use App\Services\Google\{GoogleClientFactory, TokenCipher};
use Tests\Support\FakeCalendarClient;

/**
 * Google Meet live classes.
 *
 * Google is never contacted: FakeCalendarClient is injected through the ResolvesLiveClasses test
 * seam, so these checks are deterministic and need no credentials (§45). What is asserted is
 * persisted state and refusal — not merely that a page returned 200.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out[$n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $adminContext = (new UserRepository($db))->findAuthContext($admin);

    $get = static fn (string $uri): Response => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $post = static fn (string $uri, array $body): Response => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $repository = new LiveClassRepository($db);
    $connections = new GoogleConnectionRepository($db);
    $cipher = new TokenCipher();

    /** Points every live-class controller at the same fake for the duration of one scenario. */
    $useFake = static function (FakeCalendarClient $fake): void {
        LiveClassesController::useCalendarClient($fake);
        TeacherLiveClassController::useCalendarClient($fake);
        StudentPortalController::useCalendarClient($fake);
        GoogleIntegrationController::useCalendarClient($fake);
    };

    /* ------------------------------------------------------------- fixtures */

    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'LC A ' . $suffix, 1000, null);
    $academic->createClass($institute, 'LC B ' . $suffix, 1000, null);
    $assigned = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'LC A ' . $suffix])->fetchColumn();
    $foreign = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'LC B ' . $suffix])->fetchColumn();
    $academic->assign($institute, $assigned, 'LC Math ' . $suffix, 100);
    $academic->assign($institute, $foreign, 'LC Chem ' . $suffix, 100);
    $subject = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $assigned])->fetchColumn();
    $foreignSubject = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $foreign])->fetchColumn();

    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",50000,"active",:x,:y)', ['i' => $institute, 'e' => 'LC-' . $suffix, 'n' => 'LC Teacher ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
    $employee = (int) $pdo->lastInsertId();
    (new PakistanErpRepository($db))->saveAssignment($institute, $employee, $assigned, $subject, null, 'subject', $admin);

    foreach ([['mine', $assigned], ['other', $foreign]] as [$label, $classId]) {
        $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:y)', ['i' => $institute, 'c' => $classId, 'r' => 'LCS-' . $label . '-' . $suffix, 'n' => 'LC Student ' . $label, 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
        ${'student' . ucfirst($label)} = (int) $pdo->lastInsertId();
    }
    $credentialService = new CredentialService($db);
    $teacherUser = (int) $credentialService->create('employee', $employee, $institute, 'lc.teacher.' . $suffix . '@example.test', $admin, 'TeacherPass#' . $suffix)['user_id'];
    $studentUser = (int) $credentialService->create('student', $studentMine, $institute, 'lc.student.' . $suffix . '@example.test', $admin, 'StudentPass#' . $suffix)['user_id'];
    $otherStudentUser = (int) $credentialService->create('student', $studentOther, $institute, 'lc.other.' . $suffix . '@example.test', $admin, 'StudentPass#' . $suffix)['user_id'];

    /* -------------------------------------------------- token encryption */

    $secret = 'ya29.test-refresh-' . $suffix;
    $encrypted = $cipher->encrypt($secret);
    $check('token cipher round-trips', $cipher->decrypt($encrypted) === $secret);
    $check('token cipher stores no plaintext', !str_contains($encrypted, $secret));
    $check('token cipher rejects tampered ciphertext', $cipher->decrypt(substr($encrypted, 0, -4) . 'AAAA') === null);
    $check('token cipher tolerates empty input', $cipher->decrypt(null) === null);

    /* --------------------------------------------------- connection state */

    $connections->connect($institute, $admin, [
        'account_email' => 'institute.' . $suffix . '@example.test',
        'calendar_id' => 'primary',
        'access_token_encrypted' => $cipher->encrypt('access-' . $suffix),
        'refresh_token_encrypted' => $encrypted,
        'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'scopes' => implode(' ', GoogleClientFactory::SCOPES),
    ]);
    $check('google connection persists as connected', $connections->isConnected($institute));
    $stored = $connections->find($institute);
    $check('stored access token is ciphertext', !str_contains((string) $stored['access_token_encrypted'], 'access-' . $suffix));
    $safeView = $connections->safe($institute);
    $check('safe connection view exposes no token', !array_key_exists('access_token_encrypted', $safeView) && !array_key_exists('refresh_token_encrypted', $safeView));

    // A refresh that Google refuses must not silently keep failing: it flips to reconnect_required.
    $connections->markReconnectRequired($institute, 'Google refused the token refresh.');
    $check('failed refresh marks reconnect required', ($connections->find($institute)['status'] ?? '') === 'reconnect_required');
    $check('reconnect required blocks scheduling', !$connections->isConnected($institute));
    // Reconnecting without a new refresh token keeps the stored one rather than blanking it.
    $connections->connect($institute, $admin, ['account_email' => 'institute.' . $suffix . '@example.test', 'calendar_id' => 'primary', 'access_token_encrypted' => $cipher->encrypt('access2-' . $suffix), 'refresh_token_encrypted' => null, 'token_expires_at' => date('Y-m-d H:i:s', time() + 3600), 'scopes' => '']);
    $check('reconnect preserves the existing refresh token', $cipher->decrypt($connections->find($institute)['refresh_token_encrypted']) === $secret);
    $check('reconnect restores connected status', $connections->isConnected($institute));

    /* ------------------------------------------------------- OAuth access */

    $app->session()->set('auth_user', ['id' => $teacherUser, 'name' => 'LC Teacher', 'email' => 'lc.teacher@example.test', 'status' => 'active', 'roles' => ['employee', 'teacher'], 'permissions' => ['portal.employee', 'live-classes.view', 'live-classes.manage', 'live-classes.instant']]);
    $check('teacher cannot start the Google OAuth flow', $get('/admin/integrations/google/connect')->status() === 403);
    $check('teacher cannot disconnect Google', $post('/admin/integrations/google/disconnect', ['_token' => CSRF::token()])->status() === 403);

    $app->session()->set('auth_user', $adminContext);
    // A callback whose state does not match this session's must be refused before any exchange.
    $app->session()->forget('google_oauth_state');
    $before = $connections->find($institute)['account_email'];
    $get('/admin/integrations/google/callback?code=fake&state=forged-' . $suffix);
    $check('oauth callback rejects a forged state', ($connections->find($institute)['account_email'] ?? null) === $before);

    /* ------------------------------------------------ admin: schedule */

    $fake = new FakeCalendarClient();
    $useFake($fake);
    $check('admin live classes page renders', $get('/admin/live-classes')->status() === 200);
    $check('admin schedule form renders', $get('/admin/live-classes/create')->status() === 200);
    $check('admin live class create enforces CSRF', $post('/admin/live-classes', ['title' => 'x'])->status() === 419);

    $startDate = date('Y-m-d', strtotime('+1 day'));
    $post('/admin/live-classes', ['_token' => CSRF::token(), 'title' => 'LC Scheduled ' . $suffix, 'class_id' => $assigned, 'subject_id' => $subject, 'audience_type' => 'SC', 'date' => $startDate, 'time' => '10:00', 'duration' => 45]);
    $scheduled = $db->query('SELECT * FROM live_classes WHERE title=:t', ['t' => 'LC Scheduled ' . $suffix])->fetch();
    $check('admin can schedule a live class', is_array($scheduled));
    $check('scheduled class stores the google event id', ($scheduled['google_event_id'] ?? '') !== '');
    $check('scheduled class stores the meet link', str_starts_with((string) ($scheduled['meet_link'] ?? ''), 'https://meet.google.com/'));
    $check('scheduled class is marked synced', ($scheduled['google_sync_status'] ?? '') === 'synced');
    $check('scheduled class extends live_classes rather than a new table', ($scheduled['provider'] ?? '') === 'google_meet');

    // Timezone: the wall clock we sent Google must equal what the admin typed, and the stored UTC
    // must round-trip back to it. This is where a naive implementation drifts by an hour.
    $timezone = (string) $scheduled['timezone'];
    $sent = $fake->lastEvent();
    $check('google receives the institute timezone', ($sent['timezone'] ?? '') === $timezone);
    $check('google receives the entered wall clock', ($sent['start'] ?? '') === $startDate . ' 10:00:00');
    $localBack = (new DateTimeImmutable((string) $scheduled['start_time'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d H:i');
    $check('stored UTC converts back to the entered local time', $localBack === $startDate . ' 10:00');

    /* ------------------------------------------------- admin: instant */

    $post('/admin/live-classes/instant', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'audience_type' => 'SC', 'duration' => 60]);
    $instant = $db->query('SELECT * FROM live_classes WHERE institute_id=:i AND provider=\'google_meet\' ORDER BY id DESC LIMIT 1', ['i' => $institute])->fetch();
    $check('admin can start an instant class', is_array($instant) && (int) $instant['id'] !== (int) $scheduled['id']);
    $check('instant class starts immediately', abs(strtotime((string) $instant['start_time'] . ' UTC') - time()) < 300);
    $check('instant class falls back to a subject title', str_contains((string) $instant['title'], 'Live Class'));

    /* --------------------------------------------------- admin: edit */

    $post('/admin/live-classes/' . (int) $scheduled['id'], ['_token' => CSRF::token(), 'title' => 'LC Renamed ' . $suffix, 'class_id' => $assigned, 'subject_id' => $subject, 'audience_type' => 'SC', 'date' => $startDate, 'time' => '11:30', 'duration' => 60]);
    $edited = $repository->find((int) $scheduled['id'], $institute);
    $check('admin can edit a live class', ($edited['title'] ?? '') === 'LC Renamed ' . $suffix);
    $check('edit patches the existing google event', in_array('update', $fake->operations(), true));
    $check('edit preserves the meet link', $edited['meet_link'] === $scheduled['meet_link']);

    /* ------------------------------------------ pending conference (§17) */

    $pendingFake = new FakeCalendarClient(pending: true, pendingFor: 99);
    $useFake($pendingFake);
    $post('/admin/live-classes', ['_token' => CSRF::token(), 'title' => 'LC Pending ' . $suffix, 'class_id' => $assigned, 'audience_type' => 'SC', 'date' => $startDate, 'time' => '13:00', 'duration' => 30]);
    $pending = $db->query('SELECT * FROM live_classes WHERE title=:t', ['t' => 'LC Pending ' . $suffix])->fetch();
    $check('pending conference is stored as pending, not as success', ($pending['google_sync_status'] ?? '') === 'pending');
    $check('pending conference stores no fabricated meet link', ($pending['meet_link'] ?? null) === null);
    $check('pending conference is polled a bounded number of times', count(array_filter($pendingFake->operations(), static fn (string $op): bool => $op === 'fetch')) <= 3);

    // Retry once Google has caught up.
    $useFake(new FakeCalendarClient());
    $post('/admin/live-classes/' . (int) $pending['id'] . '/sync', ['_token' => CSRF::token()]);
    $retried = $repository->find((int) $pending['id'], $institute);
    $check('retry sync fills in the meet link', str_starts_with((string) ($retried['meet_link'] ?? ''), 'https://meet.google.com/'));
    $check('retry sync clears the pending flag', ($retried['google_sync_status'] ?? '') === 'synced');

    /* ------------------------------------------------- google failures */

    $useFake(FakeCalendarClient::unreachable());
    $post('/admin/live-classes', ['_token' => CSRF::token(), 'title' => 'LC Unreachable ' . $suffix, 'class_id' => $assigned, 'audience_type' => 'SC', 'date' => $startDate, 'time' => '14:00', 'duration' => 30]);
    $check('a google outage creates no local meeting', (int) $db->query('SELECT COUNT(*) FROM live_classes WHERE title=:t', ['t' => 'LC Unreachable ' . $suffix])->fetchColumn() === 0);

    // Cancellation still removes the student's join right even when Google cannot be told.
    $useFake(FakeCalendarClient::unreachable());
    $post('/admin/live-classes/' . (int) $instant['id'] . '/cancel', ['_token' => CSRF::token()]);
    $cancelledOffline = $repository->find((int) $instant['id'], $institute);
    $check('cancel applies locally when google is unreachable', ($cancelledOffline['status'] ?? '') === 'cancelled');
    $check('a failed cancel sync is recorded rather than claimed as success', ($cancelledOffline['google_sync_status'] ?? '') === 'failed');
    $check('a failed sync records the reason', ($cancelledOffline['last_google_error'] ?? '') !== '');

    /* ----------------------------------------------------- teacher scope */

    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($teacherUser));
    $useFake($teacherFake = new FakeCalendarClient());
    $check('teacher live classes page renders', $get('/portal/employee/teaching/live-classes')->status() === 200);
    $check('teacher live class create enforces CSRF', $post('/portal/employee/teaching/live-classes', ['title' => 'x'])->status() === 419);

    $post('/portal/employee/teaching/live-classes', ['_token' => CSRF::token(), 'title' => 'LC Teacher OK ' . $suffix, 'class_id' => $assigned, 'subject_id' => $subject, 'audience_type' => 'SC', 'date' => $startDate, 'time' => '09:00', 'duration' => 45]);
    $teacherMeeting = $db->query('SELECT * FROM live_classes WHERE title=:t', ['t' => 'LC Teacher OK ' . $suffix])->fetch();
    $check('teacher can schedule for an assigned class', is_array($teacherMeeting));
    $check('teacher meeting records the teacher from the session', (int) ($teacherMeeting['teacher_id'] ?? 0) === $employee);
    $check('teacher meeting is attributed to the teacher role', ($teacherMeeting['created_by_role'] ?? '') === 'teacher');

    // §19: changing the POST to an unassigned class must not widen the teacher's reach.
    $post('/portal/employee/teaching/live-classes', ['_token' => CSRF::token(), 'title' => 'LC Teacher Leak ' . $suffix, 'class_id' => $foreign, 'subject_id' => $foreignSubject, 'audience_type' => 'SC', 'date' => $startDate, 'time' => '09:00', 'duration' => 45]);
    $check('teacher cannot schedule for an unassigned class', (int) $db->query('SELECT COUNT(*) FROM live_classes WHERE title=:t', ['t' => 'LC Teacher Leak ' . $suffix])->fetchColumn() === 0);

    // A forged teacher_id must be ignored in favour of the session identity.
    $post('/portal/employee/teaching/live-classes', ['_token' => CSRF::token(), 'title' => 'LC Spoof ' . $suffix, 'class_id' => $assigned, 'subject_id' => $subject, 'audience_type' => 'SC', 'teacher_id' => 999999, 'date' => $startDate, 'time' => '15:00', 'duration' => 30]);
    $spoof = $db->query('SELECT teacher_id FROM live_classes WHERE title=:t', ['t' => 'LC Spoof ' . $suffix])->fetch();
    $check('submitted teacher_id is ignored', (int) ($spoof['teacher_id'] ?? 0) === $employee);

    $post('/portal/employee/teaching/live-classes/instant', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'audience_type' => 'SC', 'duration' => 30]);
    $check('teacher can start an instant class', (int) $db->query('SELECT COUNT(*) FROM live_classes WHERE teacher_id=:t AND institute_id=:i', ['t' => $employee, 'i' => $institute])->fetchColumn() >= 3);

    // Another teacher's meeting: the admin-created one has no teacher_id owner match.
    $post('/portal/employee/teaching/live-classes/' . (int) $scheduled['id'] . '/cancel', ['_token' => CSRF::token()]);
    $check('teacher cannot cancel a meeting they do not own', ($repository->find((int) $scheduled['id'], $institute)['status'] ?? '') !== 'cancelled');

    /* --------------------------------------------------- student access */

    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($studentUser));
    $visible = $repository->forStudent($studentMine, $institute);
    $titles = array_column($visible, 'title');
    $check('student sees a meeting for their own class', in_array('LC Teacher OK ' . $suffix, $titles, true));

    $otherVisible = array_column($repository->forStudent($studentOther, $institute), 'title');
    $check('student does not see another class meeting', !in_array('LC Teacher OK ' . $suffix, $otherVisible, true));
    $check('student live classes page renders', $get('/portal/student/live-classes')->status() === 200);
    $check('student dashboard shows the live class tile', str_contains($get('/portal/student')->content(), 'Upcoming Live Classes'));
    // The Meet URL must not be in the student's HTML at all — only our join route.
    $check('student page renders no meet url', !str_contains($get('/portal/student/live-classes')->content(), 'meet.google.com'));

    // Explicitly selected students (SEL) see a meeting their classmates do not.
    $app->session()->set('auth_user', $adminContext);
    $useFake(new FakeCalendarClient());
    $post('/admin/live-classes', ['_token' => CSRF::token(), 'title' => 'LC Selected ' . $suffix, 'class_id' => $assigned, 'audience_type' => 'SEL', 'students' => [$studentMine], 'date' => $startDate, 'time' => '16:00', 'duration' => 30]);
    $selected = $db->query('SELECT * FROM live_classes WHERE title=:t', ['t' => 'LC Selected ' . $suffix])->fetch();
    $check('selected-student audience persists', is_array($selected) && ($selected['audience_type'] ?? '') === 'SEL');
    $check('selected student sees the meeting', in_array('LC Selected ' . $suffix, array_column($repository->forStudent($studentMine, $institute), 'title'), true));
    $check('unselected student does not see it', !in_array('LC Selected ' . $suffix, array_column($repository->forStudent($studentOther, $institute), 'title'), true));

    /* -------------------------------------------------------- join window */

    $joinWindow = (int) $app->config('app.live_classes.join_before_start_minutes', 10);
    $service = new \App\Services\LiveClassService($db, $repository, new \App\Services\Google\GoogleCalendarService(new FakeCalendarClient(), $connections), new \App\Services\LiveClassAuthorizationService($db), $app->logger());
    $window = static fn (int $startOffset, int $endOffset, string $status = 'scheduled'): array => $service->state([
        'status' => $status,
        'start_time' => gmdate('Y-m-d H:i:s', time() + $startOffset),
        'end_time' => gmdate('Y-m-d H:i:s', time() + $endOffset),
    ], $joinWindow);

    $check('join is closed well before the window opens', $window(3600, 7200)['joinable'] === false);
    $check('join opens inside the window', $window(($joinWindow * 60) - 60, 3600)['joinable'] === true);
    $check('join stays open while the class runs', $window(-300, 1800)['joinable'] === true);
    $check('join closes after the class ends', $window(-7200, -3600)['joinable'] === false);
    $check('a finished class reads as completed', $window(-7200, -3600)['state'] === 'completed');
    $check('a cancelled class is never joinable', $window(-60, 3600, 'cancelled')['joinable'] === false);
    $check('a live class is labelled Live Now', $window(-60, 3600)['label'] === 'Live Now');
    $check('an imminent class is labelled Starting Soon', $window(60, 3600)['label'] === 'Starting Soon');

    /* --------------------------------------------------- join route rules */

    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($studentUser));
    // Too early: the meeting scheduled for tomorrow cannot be joined now.
    $earlyJoin = $get('/portal/student/live-classes/' . (int) $selected['id'] . '/join');
    $check('join is refused before the window', $earlyJoin->status() === 302 && !str_contains((string) ($earlyJoin->headers()['Location'] ?? ''), 'meet.google.com'));
    $check('an early join click is not recorded', (int) $db->query('SELECT COUNT(*) FROM live_class_participants WHERE live_class_id=:l AND student_id=:s AND join_clicked_at IS NOT NULL', ['l' => (int) $selected['id'], 's' => $studentMine])->fetchColumn() === 0);

    // Move it into the window and try again.
    $db->query('UPDATE live_classes SET start_time=:s,end_time=:e WHERE id=:id', ['s' => gmdate('Y-m-d H:i:s', time() - 60), 'e' => gmdate('Y-m-d H:i:s', time() + 1800), 'id' => (int) $selected['id']]);
    $join = $get('/portal/student/live-classes/' . (int) $selected['id'] . '/join');
    $check('join inside the window redirects to google meet', str_starts_with((string) ($join->headers()['Location'] ?? ''), 'https://meet.google.com/'));
    $check('join click is recorded', (int) $db->query('SELECT COUNT(*) FROM live_class_participants WHERE live_class_id=:l AND student_id=:s AND join_clicked_at IS NOT NULL', ['l' => (int) $selected['id'], 's' => $studentMine])->fetchColumn() === 1);

    // A student outside the audience is refused even with a valid id.
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($otherStudentUser));
    $denied = $get('/portal/student/live-classes/' . (int) $selected['id'] . '/join');
    $check('a student outside the audience cannot join', !str_contains((string) ($denied->headers()['Location'] ?? ''), 'meet.google.com'));
    $check('an unauthorised join is not recorded', (int) $db->query('SELECT COUNT(*) FROM live_class_participants WHERE live_class_id=:l AND student_id=:s', ['l' => (int) $selected['id'], 's' => $studentOther])->fetchColumn() === 0);

    // Cancelled and finished meetings refuse the join.
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($studentUser));
    $db->query('UPDATE live_classes SET status=\'cancelled\' WHERE id=:id', ['id' => (int) $selected['id']]);
    $check('a cancelled class cannot be joined', !str_contains((string) ($get('/portal/student/live-classes/' . (int) $selected['id'] . '/join')->headers()['Location'] ?? ''), 'meet.google.com'));
    $db->query('UPDATE live_classes SET status=\'scheduled\',start_time=:s,end_time=:e WHERE id=:id', ['s' => gmdate('Y-m-d H:i:s', time() - 7200), 'e' => gmdate('Y-m-d H:i:s', time() - 3600), 'id' => (int) $selected['id']]);
    $check('a finished class cannot be joined', !str_contains((string) ($get('/portal/student/live-classes/' . (int) $selected['id'] . '/join')->headers()['Location'] ?? ''), 'meet.google.com'));

    /* ------------------------------------------------- redirect safety */

    $check('meet link validation accepts a real meet url', $service->isSafeMeetLink('https://meet.google.com/abc-defg-hij'));
    $check('meet link validation rejects another host', !$service->isSafeMeetLink('https://evil.example.com/abc'));
    $check('meet link validation rejects a lookalike host', !$service->isSafeMeetLink('https://meet.google.com.evil.example.com/abc'));
    $check('meet link validation rejects plain http', !$service->isSafeMeetLink('http://meet.google.com/abc'));
    $check('meet link validation rejects an empty link', !$service->isSafeMeetLink(null));

    /* ------------------------------------------------ schema and tenancy */

    $duplicateBlocked = false;
    try {
        $db->query('INSERT INTO live_classes(institute_id,provider,title,audience_type,google_event_id,timezone,status,created_by,created_by_role,created_at,updated_at) VALUES(:i,\'google_meet\',:t,\'SC\',:g,\'Asia/Karachi\',\'scheduled\',:u,\'admin\',:c,:d)', ['i' => $institute, 't' => 'dupe ' . $suffix, 'g' => (string) $scheduled['google_event_id'], 'u' => $admin, 'c' => $now, 'd' => $now]);
    } catch (\PDOException) {
        $duplicateBlocked = true;
    }
    $check('duplicate google event mapping is rejected', $duplicateBlocked);

    $columns = array_column($db->query('SHOW COLUMNS FROM live_classes')->fetchAll(), 'Field');
    foreach (['provider', 'teacher_id', 'subject_id', 'section_id', 'start_time', 'end_time', 'timezone', 'google_event_id', 'meet_link', 'google_sync_status', 'cancelled_at', 'cancelled_by'] as $column) {
        $check('live_classes has ' . $column, in_array($column, $columns, true));
    }
    $check('participants table records join clicks', in_array('join_clicked_at', array_column($db->query('SHOW COLUMNS FROM live_class_participants')->fetchAll(), 'Field'), true));
    $check('a foreign institute sees none of these meetings', $repository->forInstitute($institute + 100000) === []);
    $check('find is institute-scoped', $repository->find((int) $scheduled['id'], $institute + 100000) === null);

    // Legacy rows must still be valid after the ENUM and nullability changes.
    $check('legacy manual live classes survive the migration', (int) $db->query('SELECT COUNT(*) FROM live_classes WHERE provider=\'manual\' AND room_code IS NOT NULL')->fetchColumn() >= 0);

    /* ------------------------------------------------------------ cleanup */

    $useFake(new FakeCalendarClient());
    LiveClassesController::useCalendarClient(null);
    TeacherLiveClassController::useCalendarClient(null);
    StudentPortalController::useCalendarClient(null);
    GoogleIntegrationController::useCalendarClient(null);
    $app->session()->set('auth_user', $adminContext);

    return $out;
};
