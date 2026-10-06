<?php
declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\{AcademicRepository,OnlineTestRepository,PortalRepository,UserRepository};
use App\Services\CredentialService;

/**
 * Editing an online test (details, time, questions; what locks once students have opened it),
 * re-scoring after the marks or MCQ answer key change, and extra time for one student or the
 * whole class.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['test edit: ' . $n] = $v; };
    $db = $app->database(); $pdo = $db->connection();
    $now = date('Y-m-d H:i:s'); $sfx = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $session = $app->session(); $users = new UserRepository($db); $adminContext = $users->findAuthContext($admin);
    $repo = new OnlineTestRepository($db); $portal = new PortalRepository($db);
    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'ED Class ' . $sfx, 1000, null);
    $classId = (int) $db->query('SELECT id FROM classes WHERE name=:n', ['n' => 'ED Class ' . $sfx])->fetchColumn();
    $academic->assign($institute, $classId, 'ED Subject ' . $sfx, 100);
    $subjectId = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $classId])->fetchColumn();
    $students = []; $userIds = [];
    foreach (['A', 'B'] as $n) {
        $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,"active",:x,:x2)', ['i' => $institute, 'c' => $classId, 'r' => "ED-$n-$sfx", 'n' => "ED Student $n $sfx", 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
        $sid = (int) $pdo->lastInsertId();
        $uid = (int) (new CredentialService($db))->create('student', $sid, $institute, "ed.$n.$sfx@example.test", $admin, 'Student#' . $sfx)['user_id'];
        $students[$n] = $sid; $userIds[$n] = $uid;
    }
    $stu = static fn(string $n) => $portal->studentByUser($userIds[$n]);
    $post = static fn(string $uri, array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $get = static fn(string $uri) => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $testIds = [];
    try {
        $session->set('auth_user', $adminContext);
        $id = $testIds[] = $repo->create($institute, ['title' => 'ED Test ' . $sfx, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 30], [
            ['text' => 'Pick A', 'type' => 'mcq', 'marks' => 2, 'options' => ['A', 'B', 'C'], 'correct' => 0],
            ['text' => 'Explain', 'type' => 'written', 'marks' => 5],
        ], $admin);
        $repo->setStatus($institute, $id, 'published');
        [$mcq, $written] = array_map('intval', $db->query('SELECT id FROM online_test_questions WHERE test_id=:t ORDER BY position', ['t' => $id])->fetchAll(PDO::FETCH_COLUMN));

        $page = $get('/admin/online-tests/' . $id . '/edit');
        $check('the edit page opens with the test filled in', $page->status() === 200 && str_contains($page->content(), 'ED Test ' . $sfx) && str_contains($page->content(), 'name="question[' . $mcq . '][options][]"'));
        $check('the tests list has an Edit button', str_contains($get('/admin/online-tests')->content(), '/admin/online-tests/' . $id . '/edit'));

        // ---- before anyone opens it: everything can change, including adding/removing questions
        $post('/admin/online-tests/' . $id . '/edit', ['title' => 'ED Renamed ' . $sfx, 'class_id' => $classId, 'subject_id' => $subjectId, 'answer_mode' => 'mixed', 'duration_minutes' => 45, 'max_pages' => 6, 'closes_at' => date('Y-m-d\TH:i', time() + 86400), 'instructions' => 'Be neat',
            'question' => [$mcq => ['text' => 'Pick A', 'marks' => 2, 'options' => ['A', 'B', 'C'], 'correct' => 0], $written => ['text' => 'Explain', 'marks' => 5]],
            'new_question_text' => ['Extra one'], 'new_question_type' => ['written'], 'new_question_marks' => ['1']]);
        $t = $repo->find($institute, $id);
        $check('details are saved (title, time, pages, closing time, instructions)', $t['title'] === 'ED Renamed ' . $sfx && (int) $t['duration_minutes'] === 45 && (int) $t['max_pages'] === 6 && $t['closes_at'] !== null && $t['instructions'] === 'Be neat');
        $extra = (int) $db->query("SELECT id FROM online_test_questions WHERE test_id=:t AND question_text='Extra one'", ['t' => $id])->fetchColumn();
        $check('a question can be added before students open the test', $extra > 0);
        $repo->update($institute, $id, ['title' => $t['title'], 'duration_minutes' => 45], [], [], [$extra]);
        $check('a question can be removed before students open the test', !(int) $db->query('SELECT COUNT(*) FROM online_test_questions WHERE id=:q', ['q' => $extra])->fetchColumn());
        $threw = false;
        try { $repo->update($institute, $id, ['title' => '', 'duration_minutes' => 45], []); } catch (\DomainException) { $threw = true; }
        $check('a blank title is refused', $threw);
        $threw = false;
        try { $repo->update($institute, $id, ['title' => 'X', 'duration_minutes' => 0], []); } catch (\DomainException) { $threw = true; }
        $check('a zero time allowance is refused', $threw);

        // ---- student A answers and submits (wrong MCQ under the current key), teacher marks
        $repo->start($id, $stu('A'));
        $repo->submit($id, $stu('A'), [$mcq => 1, $written => 'Because.']);
        $attemptA = (int) $db->query('SELECT id FROM online_test_attempts WHERE test_id=:t AND student_id=:s', ['t' => $id, 's' => $students['A']])->fetchColumn();
        $repo->mark($institute, $attemptA, [$written => 4], [], $admin);
        $check('marked total before the key fix is 4', (float) $db->query('SELECT total_score FROM online_test_attempts WHERE id=:a', ['a' => $attemptA])->fetchColumn() === 4.0);

        // ---- once students have opened it: structure is locked, content can still change
        $threw = false;
        try { $repo->update($institute, $id, ['title' => 'X', 'duration_minutes' => 45], [], [['text' => 'new', 'marks' => 1]]); } catch (\DomainException) { $threw = true; }
        $check('questions cannot be added after students opened the test', $threw);
        $threw = false;
        try { $repo->update($institute, $id, ['title' => 'X', 'duration_minutes' => 45, 'answer_mode' => 'upload_only'], []); } catch (\DomainException) { $threw = true; }
        $check('the answer mode cannot change after students opened the test', $threw);
        $edit = $get('/admin/online-tests/' . $id . '/edit')->content();
        $check('the edit page explains what is locked', str_contains($edit, 'Students have already opened this test') && !str_contains($edit, 'data-add-edit-question'));
        $rescored = $repo->update($institute, $id, ['title' => 'ED Renamed ' . $sfx, 'duration_minutes' => 45],
            [$mcq => ['text' => 'Pick B', 'marks' => 2, 'options' => ['A', 'B', 'C'], 'correct' => 1], $written => ['text' => 'Explain', 'marks' => 5]]);
        $check('fixing the MCQ answer key re-scores marked papers', $rescored === 1 && (float) $db->query('SELECT total_score FROM online_test_attempts WHERE id=:a', ['a' => $attemptA])->fetchColumn() === 6.0);
        $check('question wording can still be edited', $db->query('SELECT question_text FROM online_test_questions WHERE id=:q', ['q' => $mcq])->fetchColumn() === 'Pick B');
        $repo->update($institute, $id, ['title' => 'ED Renamed ' . $sfx, 'duration_minutes' => 45], [$written => ['text' => 'Explain', 'marks' => 3]]);
        $check('lowering a question\'s marks caps the scores already given', (float) $db->query('SELECT total_score FROM online_test_attempts WHERE id=:a', ['a' => $attemptA])->fetchColumn() === 5.0);
        $threw = false;
        try { $repo->update($institute, $id, ['title' => 'X', 'duration_minutes' => 45], [$mcq => ['text' => 'Pick', 'marks' => 2, 'options' => ['A', 'B'], 'correct' => 1]]); } catch (\DomainException) { $threw = true; }
        $check('MCQ options cannot be removed after students answered', $threw);

        // ---- extra time: one student
        $repo->start($id, $stu('B'));
        $attemptB = (int) $db->query('SELECT id FROM online_test_attempts WHERE test_id=:t AND student_id=:s', ['t' => $id, 's' => $students['B']])->fetchColumn();
        $db->query('UPDATE online_test_attempts SET started_at=:s WHERE id=:a', ['s' => date('Y-m-d H:i:s', time() - 50 * 60), 'a' => $attemptB]);
        $sub = $get('/admin/online-tests/' . $id . '/submissions')->content();
        $check('submissions page offers whole-class and per-student extra time', str_contains($sub, 'Give everyone extra time') && str_contains($sub, '/admin/online-tests/attempts/' . $attemptB . '/extend'));
        $post('/admin/online-tests/attempts/' . $attemptB . '/extend', ['minutes' => '10']);
        $b = $db->query('SELECT extra_minutes FROM online_test_attempts WHERE id=:a', ['a' => $attemptB])->fetchColumn();
        $check('one student can be given extra minutes', (int) $b === 10);
        $test = $repo->testForStudent($id, $stu('B'), false);
        $check('their allowance is the test time plus their extra time', OnlineTestRepository::allowance($test, $test['attempt']) === 55);
        $check('a student inside their extra time is not late', OnlineTestRepository::lateMinutes($test['attempt']['started_at'], OnlineTestRepository::allowance($test, $test['attempt'])) === 0);
        $session->set('auth_user', $users->findAuthContext($userIds['B']));
        $html = $get('/portal/student/online-tests/' . $id)->content();
        preg_match('/data-test-deadline="(\d+)"/', $html, $m);
        $check('the student\'s countdown includes the extra time', isset($m[1]) && abs((int) $m[1] - (strtotime((string) $test['attempt']['started_at']) + 55 * 60)) <= 1);
        $session->set('auth_user', $adminContext);
        $threw = false;
        try { $repo->extendAttempt($institute, $attemptA, 5); } catch (\DomainException) { $threw = true; }
        $check('a submitted paper cannot be given extra time', $threw);

        // ---- extra time: whole class
        $closesBefore = $repo->find($institute, $id)['closes_at'];
        $post('/admin/online-tests/' . $id . '/extend', ['minutes' => '15', 'move_closing' => '1']);
        $t = $repo->find($institute, $id);
        $check('the whole class can be given extra time', (int) $t['duration_minutes'] === 60);
        $check('the closing time moves by the same amount when asked', strtotime((string) $t['closes_at']) - strtotime((string) $closesBefore) === 15 * 60);
        $test = $repo->testForStudent($id, $stu('B'), false);
        $check('class extra time adds to a student\'s own extra time', OnlineTestRepository::allowance($test, $test['attempt']) === 70);
        $threw = false;
        try { $repo->extendTest($institute, $id, 0, false); } catch (\DomainException) { $threw = true; }
        $check('zero extra minutes is refused', $threw);
    } finally {
        $session->set('auth_user', $adminContext);
        foreach ($testIds as $t) {
            $db->query('DELETE FROM online_test_answers WHERE attempt_id IN (SELECT id FROM online_test_attempts WHERE test_id=:t)', ['t' => $t]);
            $db->query('DELETE FROM online_test_attempts WHERE test_id=:t', ['t' => $t]);
            $db->query('DELETE FROM online_test_questions WHERE test_id=:t', ['t' => $t]);
            $db->query('DELETE FROM online_tests WHERE id=:t', ['t' => $t]);
        }
        foreach ($userIds as $u) { foreach (['user_roles', 'institute_users', 'remember_tokens', 'student_accounts'] as $tbl) $db->query("DELETE FROM $tbl WHERE user_id=:u", ['u' => $u]); $db->query('DELETE FROM users WHERE id=:u', ['u' => $u]); }
        foreach ($students as $s) $db->query('DELETE FROM students WHERE id=:s', ['s' => $s]);
        $db->query('DELETE FROM class_subjects WHERE class_id=:c', ['c' => $classId]);
        $db->query('DELETE FROM classes WHERE id=:c', ['c' => $classId]);
    }
    return $out;
};
