<?php
declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\{AcademicRepository,AcademicStructureRepository,UserRepository};

/**
 * Class sections and academic streams: configuration, student placement and the guards that
 * stop a section being attached to the wrong class or deleted while still in use.
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
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($admin));

    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'AS A ' . $suffix, 1000, null);
    $academic->createClass($institute, 'AS B ' . $suffix, 1000, null);
    $classA = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'AS A ' . $suffix])->fetchColumn();
    $classB = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'AS B ' . $suffix])->fetchColumn();

    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:y)', ['i' => $institute, 'c' => $classA, 'r' => 'ASS-' . $suffix, 'n' => 'AS Student', 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
    $studentA = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:y)', ['i' => $institute, 'c' => $classB, 'r' => 'ASO-' . $suffix, 'n' => 'AS Other', 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
    $studentB = (int) $pdo->lastInsertId();

    $get = static fn (string $uri): \App\Core\Response => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $post = static fn (string $uri, array $body): \App\Core\Response => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));

    $check('academic structure page renders', $get('/admin/settings/academic-structure')->status() === 200);
    $check('student placement page renders', $get('/admin/settings/academic-placement?class=' . $classA)->status() === 200);
    $check('academic structure enforces CSRF', $post('/admin/settings/academic-structure/sections', ['class_id' => $classA, 'name' => 'X'])->status() === 419);

    // ---- sections ------------------------------------------------------------
    $post('/admin/settings/academic-structure/sections', ['_token' => CSRF::token(), 'class_id' => $classA, 'name' => 'SEC-' . $suffix]);
    $section = (int) $db->query('SELECT id FROM class_sections WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'SEC-' . $suffix])->fetchColumn();
    $check('section is created for a class', $section > 0);

    $post('/admin/settings/academic-structure/sections', ['_token' => CSRF::token(), 'class_id' => $classA, 'name' => 'SEC-' . $suffix]);
    $check('duplicate section in same class is rejected', (int) $db->query('SELECT COUNT(*) FROM class_sections WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'SEC-' . $suffix])->fetchColumn() === 1);

    // ---- streams -------------------------------------------------------------
    $post('/admin/settings/academic-structure/streams', ['_token' => CSRF::token(), 'name' => 'STR-' . $suffix]);
    $stream = (int) $db->query('SELECT id FROM academic_streams WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'STR-' . $suffix])->fetchColumn();
    $check('stream is created', $stream > 0);

    // ---- placement -----------------------------------------------------------
    $post('/admin/settings/academic-placement', ['_token' => CSRF::token(), 'class' => $classA, 'section' => [$studentA => $section], 'stream' => [$studentA => $stream]]);
    $placed = $db->query('SELECT section_id, stream_id FROM students WHERE id=:s', ['s' => $studentA])->fetch();
    $check('student is placed into section and stream', (int) $placed['section_id'] === $section && (int) $placed['stream_id'] === $stream);

    // A section belongs to one class; a student of another class must be refused.
    $post('/admin/settings/academic-placement', ['_token' => CSRF::token(), 'class' => $classA, 'section' => [$studentB => $section], 'stream' => [$studentB => 0]]);
    $foreign = $db->query('SELECT section_id FROM students WHERE id=:s', ['s' => $studentB])->fetchColumn();
    $check('section from another class is refused', $foreign === null || (int) $foreign !== $section);

    // ---- destructive guards --------------------------------------------------
    $repository = new AcademicStructureRepository($db);
    $check('section delete refused while students reference it', $repository->deleteSection($institute, $section) === 'in_use');
    $check('stream delete refused while students reference it', $repository->deleteStream($institute, $stream) === 'in_use');

    $repository->setSectionStatus($institute, $section, 'inactive');
    $check('section can be deactivated instead of deleted', $db->query('SELECT status FROM class_sections WHERE id=:i', ['i' => $section])->fetchColumn() === 'inactive');

    $db->query('UPDATE students SET section_id=NULL, stream_id=NULL WHERE id IN (:a,:b)', ['a' => $studentA, 'b' => $studentB]);
    $check('section deletes once unused', $repository->deleteSection($institute, $section) === 'deleted');
    $check('stream deletes once unused', $repository->deleteStream($institute, $stream) === 'deleted');

    // ---- cleanup -------------------------------------------------------------
    $db->query('DELETE FROM students WHERE id IN (:a,:b)', ['a' => $studentA, 'b' => $studentB]);
    $db->query('DELETE FROM classes WHERE id IN (:a,:b)', ['a' => $classA, 'b' => $classB]);

    return $out;
};
