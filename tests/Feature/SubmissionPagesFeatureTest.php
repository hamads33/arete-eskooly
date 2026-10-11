<?php
declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\{AcademicRepository,OnlineTestRepository,PortalRepository,UserRepository};
use App\Services\{CredentialService,TestUploadCleanup};

/**
 * Student answer pages: page limits (institute default + per test), late-but-open submissions,
 * the JSON responses the in-page uploader relies on, upload retention clean-up, and the
 * app-owned session folder that stops students being signed out in the middle of a test.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['submission pages: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $session = $app->session();
    $users = new UserRepository($db);
    $adminContext = $users->findAuthContext($admin);
    $repo = new OnlineTestRepository($db);
    $savedSettings = $db->query('SELECT * FROM online_test_settings WHERE institute_id=:i', ['i' => $institute])->fetch() ?: null;

    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'SP Class ' . $suffix, 1000, null);
    $classId = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'SP Class ' . $suffix])->fetchColumn();
    $academic->assign($institute, $classId, 'SP Subject ' . $suffix, 100);
    $subjectId = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $classId])->fetchColumn();
    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,"active",:x,:x2)', ['i' => $institute, 'c' => $classId, 'r' => 'SP-S1-' . $suffix, 'n' => 'SP Student ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $studentId = (int) $pdo->lastInsertId();
    $studentUser = (int) (new CredentialService($db))->create('student', $studentId, $institute, 'sp.student.' . $suffix . '@example.test', $admin, 'Student#' . $suffix)['user_id'];
    $student = static fn () => (new PortalRepository($db))->studentByUser($studentUser);
    $as = static fn (int $id) => $session->set('auth_user', $users->findAuthContext($id));
    $json = ['HTTP_ACCEPT' => 'application/json'];
    $run = static fn (string $method, string $uri, array $body = [], array $server = []) => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri] + $server));
    $testIds = [];
    $files = [];
    $paper = static function (string $name, int $ageDays) use (&$files): string {
        $dir = BASE_PATH . '/storage/uploads/test-papers';
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        file_put_contents($dir . '/' . $name, 'x');
        touch($dir . '/' . $name, time() - $ageDays * 86400);
        return $files[] = 'storage/uploads/test-papers/' . $name;
    };

    try {
        // ---- sessions live in the app's own folder (Ubuntu's 24-minute cleaner never sees them)
        $check('sessions are stored in storage/sessions', str_ends_with(str_replace(chr(92), '/', (string) session_save_path()), 'storage/sessions'));
        $check('the session config points at the app folder by default', str_ends_with(str_replace(chr(92), '/', (string) (require BASE_PATH . '/config/session.php')['save_path']), 'storage/sessions'));

        // ---- settings and page limits -------------------------------------------------------
        $db->query('DELETE FROM online_test_settings WHERE institute_id=:i', ['i' => $institute]);
        $defaults = $repo->settings($institute);
        $check('defaults are 20 pages and 30 days', $defaults['max_pages'] === 20 && $defaults['retention_days'] === 30);
        foreach ([[0, 30], [41, 30], [10, -1], [10, 4000]] as [$p, $d]) {
            $threw = false;
            try { $repo->saveSettings($institute, $p, $d); } catch (\DomainException) { $threw = true; }
            $check("settings {$p} pages / {$d} days are rejected", $threw);
        }
        $repo->saveSettings($institute, 3, 30);
        $check('saved settings are read back', $repo->settings($institute)['max_pages'] === 3);

        $testId = $testIds[] = $repo->create($institute, ['title' => 'SP Upload ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 60, 'answer_mode' => 'upload_only'], [['text' => 'See paper', 'marks' => 10]], $admin);
        $repo->setStatus($institute, $testId, 'published');
        $test = $repo->find($institute, $testId);
        $check('a test without its own limit uses the institute default', $repo->maxPages($test) === 3);
        $repo->setMaxPages($institute, $testId, 2);
        $check('a per-test limit overrides the default', $repo->maxPages($repo->find($institute, $testId)) === 2);
        $check('per-test limits are capped at 40', OnlineTestRepository::pageLimit(99) === 40 && OnlineTestRepository::pageLimit('') === null);
        $created = $repo->create($institute, ['title' => 'SP Limit ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'max_pages' => 7], [['text' => 'Q', 'marks' => 1]], $admin);
        $testIds[] = $created;
        $check('a page limit chosen when creating a test is saved', (int) $repo->find($institute, $created)['max_pages'] === 7);

        $repo->start($testId, $student());
        $threw = false;
        try { $repo->submit($testId, $student(), [], [], [$paper("sp-a-{$suffix}.jpg", 0), $paper("sp-b-{$suffix}.jpg", 0), $paper("sp-c-{$suffix}.jpg", 0)]); } catch (\DomainException $e) { $threw = str_contains($e->getMessage(), 'at most 2 pages'); }
        $check('more pages than the test allows are refused', $threw);

        // ---- the page the student sees ------------------------------------------------------
        $as($studentUser);
        $page = $run('GET', '/portal/student/online-tests/' . $testId);
        $html = $page->content();
        $check('the test page loads the camera/page uploader', $page->status() === 200 && str_contains($html, 'submission-pages.js') && str_contains($html, 'submission-pages.css'));
        $check('the upload box carries the page limit', str_contains($html, 'data-max-pages="2"') && str_contains($html, 'up to 2 pages'));
        $check('the plain file input is still there for browsers without JavaScript', str_contains($html, 'name="solved_paper[]"'));
        $check('the page shows a countdown', str_contains($html, 'data-test-deadline=') && str_contains($html, 'data-server-now='));
        $check('the draft key ties drafts to this student and test', str_contains($html, 'data-draft-key="s' . $studentId . '-t' . $testId . '"'));
        $list = $run('GET', '/portal/student/online-tests')->content();
        $check('the tests list can show unsent drafts', str_contains($list, 'data-submission-drafts') && str_contains($list, 'data-owner="s' . $studentId . '"'));
        $dashboard = $run('GET', '/portal/student')->content();
        $check('the dashboard can show unsent drafts', str_contains($dashboard, 'data-submission-drafts'));
        $check('dashboard omits the intrusive install banner', !str_contains($dashboard, 'data-pwa-install-banner'));
        $pwa = (string) file_get_contents(BASE_PATH . '/public/assets/js/pwa.js');
        $check('dismissing the install banner is remembered for a week and works by swiping down', str_contains($pwa, 'arete-install-later-until') && str_contains($pwa, "querySelectorAll('[data-pwa-install-later]')") && str_contains($pwa, 'touchend'));
        $check('the install banner is not shown over the test submit bar', !str_contains($html, 'data-pwa-install-banner'));

        // ---- JSON answers for the in-page uploader -----------------------------------------
        $res = $run('POST', '/portal/student/online-tests/' . $testId, ['_token' => CSRF::token(), 'page_count' => '3'], $json);
        $body = json_decode($res->content(), true);
        $check('pages that did not reach the server are reported, not silently dropped', $res->status() === 422 && ($body['ok'] ?? null) === false && str_contains($body['message'] ?? '', 'Only 0 of 3 pages'));
        $res = $run('POST', '/portal/student/online-tests/' . $testId, ['_token' => CSRF::token(), 'page_count' => '0'], $json);
        $check('an upload-only test with no pages gets a JSON error', $res->status() === 422 && str_contains((string) (json_decode($res->content(), true)['message'] ?? ''), 'at least one'));
        $res = $run('POST', '/portal/student/online-tests/' . $testId, ['_token' => 'bad'], $json);
        $check('an expired security token answers JSON 419 the uploader can recover from', $res->status() === 419 && (json_decode($res->content(), true)['reason'] ?? '') === 'csrf');
        $check('a plain form post still gets the old 419 page', $run('POST', '/portal/student/online-tests/' . $testId, ['_token' => 'bad'])->status() === 419);
        $res = $run('POST', '/portal/student/online-tests/' . $testId, ['_token' => CSRF::token(), 'page_count' => '0']);
        $check('a refused plain form post returns to the test page, not the dashboard', $res->status() === 302 && str_ends_with((string) ($res->headers()['Location'] ?? ''), '/portal/student/online-tests/' . $testId));
        $session->forget('auth_user');
        $res = $run('POST', '/portal/student/online-tests/' . $testId, ['_token' => CSRF::token()], $json);
        $check('a signed-out upload gets JSON 401 instead of a redirect to the sign-in page', $res->status() === 401 && (json_decode($res->content(), true)['reason'] ?? '') === 'signed_out');
        $as($studentUser);

        // ---- late but open, then a successful JSON submission ----------------------------
        $db->query('UPDATE online_test_attempts SET started_at=:s WHERE test_id=:t AND student_id=:st', ['s' => date('Y-m-d H:i:s', time() - 7200), 't' => $testId, 'st' => $studentId]);
        $check('an attempt past its time is still submittable while the test is open', is_array($repo->assertSubmittable($testId, $student())));
        $repo->submit($testId, $student(), [], [], [$paper("sp-d-{$suffix}.jpg", 0), $paper("sp-e-{$suffix}.jpg", 0)]);
        $attempt = $db->query('SELECT * FROM online_test_attempts WHERE test_id=:t AND student_id=:s', ['t' => $testId, 's' => $studentId])->fetch();
        $check('the pages are stored in the order the student chose', json_decode((string) $attempt['submission_files_json'], true) === ["storage/uploads/test-papers/sp-d-{$suffix}.jpg", "storage/uploads/test-papers/sp-e-{$suffix}.jpg"]);
        $threw = false;
        try { $repo->assertSubmittable($testId, $student()); } catch (\DomainException $e) { $threw = str_contains($e->getMessage(), 'already submitted'); }
        $check('a second submission is refused before any file is stored', $threw);
        $session->set('auth_user', $adminContext);
        $sub = $run('GET', '/admin/online-tests/' . $testId . '/submissions')->content();
        $check('the teacher sees the submission marked late', str_contains($sub, 'Late +'));
        $review = $run('GET', '/admin/online-tests/submissions/' . $attempt['id'])->content();
        $check('the teacher review shows the pages in a gallery and the late flag', str_contains($review, 'data-review-gallery') && str_contains($review, 'Late +'));

        // ---- admin settings screen -------------------------------------------------------
        $index = $run('GET', '/admin/online-tests')->content();
        $check('the admin sees the upload settings card', str_contains($index, 'id="uploadSettings"') && str_contains($index, 'name="retention_days"'));
        $check('each saved test has a page-limit control', str_contains($index, '/admin/online-tests/' . $testId . '/pages'));
        $run('POST', '/admin/online-tests/settings', ['_token' => CSRF::token(), 'max_pages' => '12', 'retention_days' => '45']);
        $check('the admin can save the default pages and retention days', $repo->settings($institute)['max_pages'] === 12 && $repo->settings($institute)['retention_days'] === 45);
        $run('POST', '/admin/online-tests/' . $testId . '/pages', ['_token' => CSRF::token(), 'max_pages' => '5']);
        $check('the admin can change one test\'s page limit', (int) $repo->find($institute, $testId)['max_pages'] === 5);

        // ---- teacher comments --------------------------------------------------------------
        $run('POST', '/admin/online-tests/submissions/' . $attempt['id'], ['_token' => CSRF::token(), 'teacher_comment' => 'Good effort. Revise chapter 2.']);
        $row = $db->query('SELECT status,teacher_comment,teacher_comment_at FROM online_test_attempts WHERE id=:id', ['id' => $attempt['id']])->fetch();
        $check('the teacher can add a comment while marking', $row['status'] === 'marked' && $row['teacher_comment'] === 'Good effort. Revise chapter 2.' && $row['teacher_comment_at'] !== null);
        $check('the marking page shows the comment box with the saved comment', str_contains($run('GET', '/admin/online-tests/submissions/' . $attempt['id'])->content(), 'name="teacher_comment"'));
        $as($studentUser);
        $check('a comment stays hidden until the result is published', !str_contains($run('GET', '/portal/student/online-tests')->content(), 'Revise chapter 2'));
        $session->set('auth_user', $adminContext);
        $repo->publishResults($institute, $testId, 'both', $admin);
        $run('POST', '/admin/online-tests/submissions/' . $attempt['id'], ['_token' => CSRF::token(), 'teacher_comment' => "Good effort.\nRevise chapter 2 definitions."]);
        $check('the teacher can change the comment after marking and publishing', $db->query('SELECT teacher_comment FROM online_test_attempts WHERE id=:id', ['id' => $attempt['id']])->fetchColumn() === "Good effort.\nRevise chapter 2 definitions.");
        $as($studentUser);
        $list = $run('GET', '/portal/student/online-tests')->content();
        $check('the student sees the comment on the tests list', str_contains($list, 'Revise chapter 2 definitions') && str_contains($list, 'View Result &amp; Comments') || str_contains($list, 'View Result & Comments'));
        $check('the tests list opens the student\'s own result (attempt id, not test id)', str_contains($list, '/portal/student/online-tests/' . $attempt['id'] . '/result'));
        $result = $run('GET', '/portal/student/online-tests/' . $attempt['id'] . '/result');
        $check('the student sees the full comment on the result page', $result->status() === 200 && str_contains($result->content(), 'teacher-comment-card') && str_contains($result->content(), 'Good effort.<br />'));
        $check('the student sees the comment on My Results', str_contains($run('GET', '/portal/student/results')->content(), 'Revise chapter 2'));
        $session->set('auth_user', $adminContext);

        // ---- retention clean-up ----------------------------------------------------------
        $repo->saveSettings($institute, 20, 30);
        $repo->mark($institute, (int) $attempt['id'], [], [], $admin);
        $cleanup = new TestUploadCleanup($db);
        $orphanNew = $paper("sp-orphan-new-{$suffix}.jpg", 1);
        $cleanup->run($institute);
        $check('a recently submitted marked paper is kept', is_file(BASE_PATH . "/storage/uploads/test-papers/sp-d-{$suffix}.jpg"));
        $db->query('UPDATE online_test_attempts SET submitted_at=:s WHERE id=:id', ['s' => date('Y-m-d H:i:s', time() - 31 * 86400), 'id' => $attempt['id']]);
        $orphanOld = $paper("sp-orphan-old-{$suffix}.jpg", 40);
        $repo->saveSettings($institute, 20, 0);
        $cleanup->run($institute);
        $check('retention 0 keeps everything', is_file(BASE_PATH . "/storage/uploads/test-papers/sp-d-{$suffix}.jpg") && is_file(BASE_PATH . '/' . $orphanOld));
        $repo->saveSettings($institute, 20, 30);
        $removed = $cleanup->run($institute);
        $after = $db->query('SELECT files_purged_at,total_score,status FROM online_test_attempts WHERE id=:id', ['id' => $attempt['id']])->fetch();
        $check('a marked paper older than the retention period is removed', !is_file(BASE_PATH . "/storage/uploads/test-papers/sp-d-{$suffix}.jpg") && !is_file(BASE_PATH . "/storage/uploads/test-papers/sp-e-{$suffix}.jpg") && $removed >= 2);
        $check('the marks stay and the attempt records when its pages were removed', $after['files_purged_at'] !== null && $after['status'] === 'marked');
        $check('an old file no record points to is removed', !is_file(BASE_PATH . '/' . $orphanOld));
        $check('a recent unreferenced file is kept', is_file(BASE_PATH . '/' . $orphanNew));
        $check('the last clean-up is recorded', $repo->settings($institute)['last_cleanup_at'] !== null);
        $review = $run('GET', '/admin/online-tests/submissions/' . $attempt['id'])->content();
        $check('the teacher is told the pages were removed instead of seeing broken images', str_contains($review, 'removed on') && !str_contains($review, 'data-gallery-image'));

        // an unmarked old submission is never removed
        $test2 = $testIds[] = $repo->create($institute, ['title' => 'SP Unmarked ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'answer_mode' => 'upload_only'], [['text' => 'P', 'marks' => 5]], $admin);
        $repo->setStatus($institute, $test2, 'published');
        $repo->start($test2, $student());
        $repo->submit($test2, $student(), [], [], [$keep = $paper("sp-unmarked-{$suffix}.jpg", 60)]);
        $db->query('UPDATE online_test_attempts SET submitted_at=:s WHERE test_id=:t', ['s' => date('Y-m-d H:i:s', time() - 60 * 86400), 't' => $test2]);
        $cleanup->run($institute);
        $check('a paper still waiting for marking is never removed', is_file(BASE_PATH . '/' . $keep));
    } finally {
        $session->set('auth_user', $adminContext);
        foreach ($files as $f) @unlink(BASE_PATH . '/' . $f);
        foreach ($testIds as $id) {
            $db->query('DELETE FROM online_test_answers WHERE attempt_id IN (SELECT id FROM online_test_attempts WHERE test_id=:t)', ['t' => $id]);
            $db->query('DELETE FROM online_test_attempts WHERE test_id=:t', ['t' => $id]);
            $db->query('DELETE FROM online_test_questions WHERE test_id=:t', ['t' => $id]);
            $db->query('DELETE FROM online_tests WHERE id=:t', ['t' => $id]);
        }
        $db->query('DELETE FROM online_test_settings WHERE institute_id=:i', ['i' => $institute]);
        if ($savedSettings) {
            $db->query('INSERT INTO online_test_settings(institute_id,max_pages,retention_days,last_cleanup_at,last_cleanup_files,updated_at) VALUES(:i,:p,:r,:l,:f,:u)', ['i' => $institute, 'p' => $savedSettings['max_pages'], 'r' => $savedSettings['retention_days'], 'l' => $savedSettings['last_cleanup_at'], 'f' => $savedSettings['last_cleanup_files'], 'u' => $savedSettings['updated_at']]);
        }
        $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $studentUser]);
        $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $studentUser]);
        $db->query('DELETE FROM remember_tokens WHERE user_id=:u', ['u' => $studentUser]);
        $db->query('DELETE FROM student_accounts WHERE student_id=:s', ['s' => $studentId]);
        $db->query('DELETE FROM users WHERE id=:u', ['u' => $studentUser]);
        $db->query('DELETE FROM students WHERE id=:s', ['s' => $studentId]);
        $db->query('DELETE FROM class_subjects WHERE class_id=:c', ['c' => $classId]);
        $db->query('DELETE FROM classes WHERE id=:c', ['c' => $classId]);
    }
    return $out;
};
