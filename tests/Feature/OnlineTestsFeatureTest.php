<?php
declare(strict_types=1);

use App\Core\{App,Request};
use App\Repositories\{AcademicRepository,OnlineTestRepository,PakistanErpRepository,PortalRepository,UserRepository};
use App\Services\CredentialService;

/**
 * Online Tests / Exam Portal: creation, teacher scope, the attempt lifecycle, marking,
 * publish/withdraw visibility (student / parent / both), and downloadable attachments.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['online test: ' . $n] = $v; };
    $db = $app->database();
    $pdo = $db->connection();
    $now = date('Y-m-d H:i:s');
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $session = $app->session();
    $adminContext = (new UserRepository($db))->findAuthContext($admin);
    $academic = new AcademicRepository($db);
    $credentials = new CredentialService($db);
    $erp = new PakistanErpRepository($db);
    $portal = new PortalRepository($db);
    $repo = new OnlineTestRepository($db);

    $academic->createClass($institute, 'OT Class ' . $suffix, 1000, null);
    $classId = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'OT Class ' . $suffix])->fetchColumn();
    $academic->assign($institute, $classId, 'OT Subject ' . $suffix, 100);
    $subjectId = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $classId])->fetchColumn();

    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",1,"active",:x,:x2)', ['i' => $institute, 'e' => 'OT-T1-' . $suffix, 'n' => 'OT Teacher ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $teacherId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",1,"active",:x,:x2)', ['i' => $institute, 'e' => 'OT-T2-' . $suffix, 'n' => 'OT Other Teacher ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $otherTeacherId = (int) $pdo->lastInsertId();
    $erp->saveAssignment($institute, $teacherId, $classId, $subjectId, null, 'subject', $admin);
    $teacherUser = (int) $credentials->create('employee', $teacherId, $institute, 'ot.teacher.' . $suffix . '@example.test', $admin, 'Teacher#' . $suffix)['user_id'];
    $db->query("INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug='teacher'", ['u' => $teacherUser]);
    $otherTeacherUser = (int) $credentials->create('employee', $otherTeacherId, $institute, 'ot.other.' . $suffix . '@example.test', $admin, 'Teacher#' . $suffix)['user_id'];
    $db->query("INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug='teacher'", ['u' => $otherTeacherUser]);

    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,"active",:x,:x2)', ['i' => $institute, 'c' => $classId, 'r' => 'OT-S1-' . $suffix, 'n' => 'OT Student ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
    $studentId = (int) $pdo->lastInsertId();
    $studentUser = (int) $credentials->create('student', $studentId, $institute, 'ot.student.' . $suffix . '@example.test', $admin, 'Student#' . $suffix)['user_id'];
    $db->query('INSERT INTO guardians(institute_id,name,relationship,mobile,status,created_at,updated_at) VALUES(:i,:n,"Father","03001112222","active",:x,:x2)', ['i' => $institute, 'n' => 'OT Guardian ' . $suffix, 'x' => $now, 'x2' => $now]);
    $guardianId = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO guardian_students(guardian_id,student_id,is_primary,created_at) VALUES(:g,:s,1,:c)', ['g' => $guardianId, 's' => $studentId, 'c' => $now]);
    $guardianUser = (int) $credentials->create('guardian', $guardianId, $institute, 'ot.guardian.' . $suffix . '@example.test', $admin, 'Guardian#' . $suffix)['user_id'];

    $get = static fn (string $uri) => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $post = static fn (string $uri, array $body) => $app->run(new Request([], $body + ['_token' => \App\Core\CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
    $as = static fn (int $id) => $session->set('auth_user', (new UserRepository($db))->findAuthContext($id));

    $student = static function () use ($portal, $studentUser) { return $portal->studentByUser($studentUser); };
    $testIds = [];

    try {
        // ---- creation: validation and teacher scope --------------------------------------
        $threw = false;
        try { $repo->create($institute, ['title' => '', 'class_id' => $classId, 'subject_id' => $subjectId], [['text' => 'Q', 'marks' => 1]], $admin); } catch (\DomainException) { $threw = true; }
        $check('a test without a title is rejected', $threw);

        $threw = false;
        try { $repo->create($institute, ['title' => 'X ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId], [['text' => 'Q', 'marks' => 0]], $admin); } catch (\DomainException) { $threw = true; }
        $check('a question with zero marks is rejected', $threw);

        $threw = false;
        try { $repo->create($institute, ['title' => 'X ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId], [['text' => 'Q', 'type' => 'mcq', 'marks' => 1, 'options' => ['Only one'], 'correct' => 0]], $admin); } catch (\DomainException) { $threw = true; }
        $check('an MCQ with fewer than two options is rejected', $threw);

        $threw = false;
        try { $repo->create($institute, ['title' => 'Bad window ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'opens_at' => '2030-01-02T10:00', 'closes_at' => '2030-01-01T10:00'], [['text' => 'Q', 'marks' => 1]], $admin); } catch (\DomainException) { $threw = true; }
        $check('a closing time before the opening time is rejected', $threw);

        $check('the unassigned teacher is correctly reported as not assigned', $erp->teacherCan($otherTeacherId, $institute, $classId, $subjectId) === false);

        // ---- mixed-mode test (typed + MCQ + upload) ---------------------------------------
        $testId = $testIds[] = $repo->create($institute, [
            'title' => 'Mixed Test ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 30, 'answer_mode' => 'mixed',
        ], [
            ['text' => 'Capital of Pakistan?', 'type' => 'mcq', 'marks' => 5, 'options' => ['Lahore', 'Islamabad', 'Karachi'], 'correct' => 1],
            ['text' => 'Explain photosynthesis.', 'type' => 'written', 'marks' => 10],
        ], $teacherUser);
        $repo->setStatus($institute, $testId, 'published');
        $questions = array_column($db->query('SELECT * FROM online_test_questions WHERE test_id=:t ORDER BY position', ['t' => $testId])->fetchAll(), null, 'question_type');
        $mcqId = (int) $questions['mcq']['id'];
        $writtenId = (int) $questions['written']['id'];

        // ---- student availability and window gating ----------------------------------------
        $available = array_column($repo->available($student()), null, 'id');
        $check('a published open test is available to the student', isset($available[$testId]) && $available[$testId]['attempt_status'] === null);
        $check('an unmarked test has no visible score', $available[$testId]['result_visible'] === false && $available[$testId]['total_score'] === null);

        $started = $repo->start($testId, $student());
        $check('starting creates an in_progress attempt', $started['attempt']['status'] === 'in_progress');
        $again = $repo->start($testId, $student());
        $check('starting twice resumes the same attempt (no duplicate row)', (int) $again['attempt']['id'] === (int) $started['attempt']['id']);

        $threw = false;
        try { $repo->submit($testId, $student(), [$mcqId => 5]); } catch (\DomainException) { $threw = true; }
        $check('submitting with an out-of-range MCQ option is rejected', $threw);
        $threw = false;
        try { $repo->submit($testId, $student(), [$mcqId => 1]); } catch (\DomainException) { $threw = true; }
        $check('submitting with a missing written answer is rejected', $threw);

        $repo->submit($testId, $student(), [$mcqId => 1, $writtenId => 'Plants convert light to energy.']);
        $attempt = $repo->attempt($institute, (int) $started['attempt']['id']);
        $mcqAnswer = current(array_filter($attempt['answers'], static fn($a) => (int) $a['id'] === $mcqId));
        $check('a correct MCQ answer is auto-scored full marks', (float) $mcqAnswer['score'] === 5.0);

        $threw = false;
        try { $repo->submit($testId, $student(), [$mcqId => 1, $writtenId => 'again']); } catch (\DomainException) { $threw = true; }
        $check('a second submission is rejected', $threw);

        $available2 = array_column($repo->available($student()), null, 'id');
        $check('a submitted test still shows attempt status', $available2[$testId]['attempt_status'] === 'submitted');

        // ---- ownership: only the assigned teacher (or admin) may mark ---------------------
        $as($otherTeacherUser);
        $check('an unassigned teacher cannot see the submissions list via the HTTP route', $get('/admin/online-tests/' . $testId . '/submissions')->status() === 403);
        $check('an unassigned teacher cannot review the attempt', $get('/admin/online-tests/submissions/' . $started['attempt']['id'])->status() === 404);
        $threw = false;
        try { $repo->mark($institute, (int) $started['attempt']['id'], [], [], $otherTeacherUser); } catch (\DomainException) {
        } // repository itself has no ownership check by design (controller enforces it); confirm the controller does
        $check('the controller blocks marking by a non-owner via HTTP', $post('/admin/online-tests/submissions/' . $started['attempt']['id'], ['score' => []])->status() === 403);

        // ---- marking, clamping, and publish visibility -------------------------------------
        $as($teacherUser);
        $check('the assigned teacher can open the submissions list', $get('/admin/online-tests/' . $testId . '/submissions')->status() === 200);
        $repo->mark($institute, (int) $started['attempt']['id'], [$writtenId => 999, $mcqId => 5], ['written' => 'Good answer, but 999 should be clamped.'], $teacherUser, []);
        $marked = $repo->attempt($institute, (int) $started['attempt']['id']);
        $writtenAnswer = current(array_filter($marked['answers'], static fn($a) => (int) $a['id'] === $writtenId));
        $check('a score above the question maximum is clamped', (float) $writtenAnswer['score'] === 10.0);
        $check('total score is the sum of clamped scores', (float) $marked['total_score'] === 15.0);
        $check('marking alone does not publish the result', $marked['published_at'] === null);
        $check('an unpublished result is invisible to the student', $repo->resultsForStudent($student()) === []);
        $check('an unpublished result is invisible to the parent', $repo->resultsForGuardianChild($institute, $studentId) === []);

        $repo->publishResults($institute, $testId, 'student', $teacherUser);
        $check('publishing to "student" makes it visible to the student', count($repo->resultsForStudent($student())) === 1);
        $check('publishing to "student" does not reveal it to the parent', $repo->resultsForGuardianChild($institute, $studentId) === []);
        $check('resultForGuardianChild refuses when not published to the parent', $repo->resultForGuardianChild((int) $started['attempt']['id'], $institute, $studentId) === null);

        $repo->publishResults($institute, $testId, 'both', $teacherUser);
        $check('re-publishing with a wider scope now shows it to the parent too', count($repo->resultsForGuardianChild($institute, $studentId)) === 1);
        $resultDetail = $repo->resultForStudent((int) $started['attempt']['id'], $student());
        $check('the student can fetch their own full result detail', $resultDetail !== null && count($resultDetail['answers']) === 2);

        $as($studentUser);
        $check('the student result page renders with the score', str_contains($get('/portal/student/online-tests/' . $started['attempt']['id'] . '/result')->content(), '15'));

        $repo->withdrawResult($institute, (int) $started['attempt']['id']);
        $check('withdrawing hides the result from the student again', $repo->resultsForStudent($student()) === []);
        $as($studentUser);
        $withdrawnPage = $get('/portal/student/online-tests/' . $started['attempt']['id'] . '/result');
        $check('a withdrawn result shows a friendly pending page, not a bare 404', $withdrawnPage->status() === 200 && str_contains($withdrawnPage->content(), 'not published yet'));
        $check('a withdrawn result never leaks the score in that page', !str_contains($withdrawnPage->content(), '15 / 15'));
        $check('a made-up attempt id is refused, not a bare 404', $get('/portal/student/online-tests/999999999/result')->status() === 403);
        $repo->publishResults($institute, $testId, 'both', $teacherUser);

        // ---- report integration ------------------------------------------------------------
        $summary = $repo->progressSummary($institute, $studentId);
        $check('the progress summary counts the published result', $summary['tests'] === 1 && $summary['average_percentage'] === 100.0);

        // ---- window edge case: a test that has since closed still honours an existing attempt --
        $testId2 = $testIds[] = $repo->create($institute, ['title' => 'Closing Soon ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 120, 'answer_mode' => 'mixed'], [['text' => 'Q', 'type' => 'written', 'marks' => 4]], $teacherUser);
        $repo->setStatus($institute, $testId2, 'published');
        $q2 = (int) $db->query('SELECT id FROM online_test_questions WHERE test_id=:t', ['t' => $testId2])->fetchColumn();
        $attempt2 = $repo->start($testId2, $student());
        // A full day back, not just an hour: the app server and the DB server clocks are not
        // guaranteed to agree to the minute (this dev environment's MySQL runs UTC while PHP
        // runs Pakistan time), so a short offset is not reliably "the past" from SQL NOW()'s view.
        $db->query("UPDATE online_tests SET closes_at=:c WHERE id=:id", ['c' => date('Y-m-d H:i:s', time() - 86400), 'id' => $testId2]);
        $repo->submit($testId2, $student(), [$q2 => 'Answered just before/around the deadline.']);
        $check('a student mid-attempt can still submit after the test window closes', $db->query('SELECT status FROM online_test_attempts WHERE id=:id', ['id' => $attempt2['attempt']['id']])->fetchColumn() === 'submitted');
        $available3 = array_column($repo->available($student()), null, 'id');
        $check('a closed test with an existing attempt still appears in the student list', isset($available3[$testId2]));

        $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,"active",:x,:x2)', ['i' => $institute, 'c' => $classId, 'r' => 'OT-S2-' . $suffix, 'n' => 'OT Never Started ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'x2' => $now]);
        $neverStartedId = (int) $pdo->lastInsertId();
        $neverStarted = ['id' => $neverStartedId, 'institute_id' => $institute, 'class_id' => $classId];
        $check('a student with no attempt on a now-closed test does not see it', $repo->testForStudent($testId2, $neverStarted, true) === null && !in_array($testId2, array_column($repo->available($neverStarted), 'id'), true));
        $db->query('DELETE FROM students WHERE id=:s', ['s' => $neverStartedId]);

        // ---- upload-only mode ----------------------------------------------------------------
        $testId3 = $testIds[] = $repo->create($institute, ['title' => 'Upload Only ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 60, 'answer_mode' => 'upload_only'], [['text' => 'See attached paper.', 'marks' => 20]], $teacherUser);
        $repo->setStatus($institute, $testId3, 'published');
        $attempt3 = $repo->start($testId3, $student());
        $threw = false;
        try { $repo->submit($testId3, $student(), [], [], []); } catch (\DomainException) { $threw = true; }
        $check('an upload-only test cannot be submitted with no file', $threw);
        $repo->submit($testId3, $student(), [], [], ['storage/uploads/test-papers/fake-' . $suffix . '.pdf']);
        $check('an upload-only submission with a file succeeds', $db->query('SELECT status FROM online_test_attempts WHERE test_id=:t AND student_id=:s', ['t' => $testId3, 's' => $studentId])->fetchColumn() === 'submitted');

        // ---- time-limit expiry -----------------------------------------------------------------
        $testId4 = $testIds[] = $repo->create($institute, ['title' => 'Short Duration ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 1, 'answer_mode' => 'mixed'], [['text' => 'Q', 'type' => 'written', 'marks' => 2]], $teacherUser);
        $repo->setStatus($institute, $testId4, 'published');
        $q4 = (int) $db->query('SELECT id FROM online_test_questions WHERE test_id=:t', ['t' => $testId4])->fetchColumn();
        $attempt4 = $repo->start($testId4, $student());
        $db->query('UPDATE online_test_attempts SET started_at=:s WHERE id=:id', ['s' => date('Y-m-d H:i:s', time() - 600), 'id' => $attempt4['attempt']['id']]);
        $threw = false;
        // Past the time allowance but the test is still open: accepted and shown to the teacher as late.
        $repo->submit($testId4, $student(), [$q4 => 'late but open']);
        $late4 = $db->query('SELECT started_at,submitted_at,status FROM online_test_attempts WHERE id=:id', ['id' => $attempt4['attempt']['id']])->fetch();
        $check('a submission after the time allowance is accepted while the test is open', $late4['status'] === 'submitted');
        $check('a late submission is flagged with the minutes over', \App\Repositories\OnlineTestRepository::lateMinutes($late4['started_at'], 1, $late4['submitted_at']) >= 9);
        // Past the time allowance and the test is closed: refused.
        $testId6 = $testIds[] = $repo->create($institute, ['title' => 'Short Closed ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId, 'duration_minutes' => 1, 'answer_mode' => 'mixed'], [['text' => 'Q', 'type' => 'written', 'marks' => 2]], $teacherUser);
        $repo->setStatus($institute, $testId6, 'published');
        $q6 = (int) $db->query('SELECT id FROM online_test_questions WHERE test_id=:t', ['t' => $testId6])->fetchColumn();
        $attempt6 = $repo->start($testId6, $student());
        $db->query('UPDATE online_test_attempts SET started_at=:s WHERE id=:id', ['s' => date('Y-m-d H:i:s', time() - 600), 'id' => $attempt6['attempt']['id']]);
        $repo->setStatus($institute, $testId6, 'closed');
        try { $repo->submit($testId6, $student(), [$q6 => 'too late']); } catch (\DomainException) { $threw = true; }
        $check('submitting after the time allowance is rejected once the test is closed', $threw);

        // ---- delete rules --------------------------------------------------------------------
        $testId5 = $testIds[] = $repo->create($institute, ['title' => 'Deletable Draft ' . $suffix, 'class_id' => $classId, 'subject_id' => $subjectId], [['text' => 'Q', 'marks' => 1]], $teacherUser);
        $check('a pristine draft with no attempts can be deleted', $repo->delete($institute, $testId5));
        $testIds = array_values(array_filter($testIds, static fn($id) => $id !== $testId5));
        $threw = false;
        try { $repo->delete($institute, $testId); } catch (\DomainException) { $threw = true; }
        $check('a test with student attempts cannot be deleted', $threw);
    } finally {
        $session->set('auth_user', $adminContext);
        foreach ($testIds as $id) {
            $db->query('DELETE FROM online_test_answers WHERE attempt_id IN (SELECT id FROM online_test_attempts WHERE test_id=:t)', ['t' => $id]);
            $db->query('DELETE FROM online_test_attempts WHERE test_id=:t', ['t' => $id]);
            $db->query('DELETE FROM online_test_questions WHERE test_id=:t', ['t' => $id]);
            $db->query('DELETE FROM online_tests WHERE id=:t', ['t' => $id]);
        }
        $db->query('DELETE FROM guardian_students WHERE guardian_id=:g', ['g' => $guardianId]);
        foreach ([$teacherUser, $otherTeacherUser, $studentUser, $guardianUser] as $u) {
            $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $u]);
            $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $u]);
            $db->query('DELETE FROM remember_tokens WHERE user_id=:u', ['u' => $u]);
            $db->query('DELETE FROM users WHERE id=:u', ['u' => $u]);
        }
        $db->query('DELETE FROM employee_accounts WHERE employee_id IN (:a,:b)', ['a' => $teacherId, 'b' => $otherTeacherId]);
        $db->query('DELETE FROM student_accounts WHERE student_id=:s', ['s' => $studentId]);
        $db->query('DELETE FROM teacher_assignments WHERE employee_id IN (:a,:b)', ['a' => $teacherId, 'b' => $otherTeacherId]);
        $db->query('DELETE FROM guardians WHERE id=:g', ['g' => $guardianId]);
        $db->query('DELETE FROM students WHERE id=:s', ['s' => $studentId]);
        $db->query('DELETE FROM employees WHERE id IN (:a,:b)', ['a' => $teacherId, 'b' => $otherTeacherId]);
        $db->query('DELETE FROM class_subjects WHERE class_id=:c', ['c' => $classId]);
        $db->query('DELETE FROM classes WHERE id=:c', ['c' => $classId]);
        $session->forget('auth_user');
    }
    return $out;
};
