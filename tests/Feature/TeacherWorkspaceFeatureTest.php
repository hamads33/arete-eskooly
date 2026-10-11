<?php
declare(strict_types=1);

use App\Core\{App,CSRF,Request};
use App\Repositories\{AcademicRepository,PakistanErpRepository,UserRepository};
use App\Services\CredentialService;
use App\Services\AuthService;

/**
 * Teacher workspace: authoring from the employee portal, constrained by teacher_assignments.
 *
 * Asserts persisted state and scope denial, not just HTTP 200.
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

    // ---- fixtures: one assigned class, one unassigned class ------------------
    $academic = new AcademicRepository($db);
    $academic->createClass($institute, 'TW A ' . $suffix, 1000, null);
    $academic->createClass($institute, 'TW B ' . $suffix, 1000, null);
    $assigned = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'TW A ' . $suffix])->fetchColumn();
    $foreign = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'TW B ' . $suffix])->fetchColumn();
    $academic->assign($institute, $assigned, 'TW Math ' . $suffix, 100);
    $academic->assign($institute, $foreign, 'TW Sci ' . $suffix, 100);
    $subject = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $assigned])->fetchColumn();
    $foreignSubject = (int) $db->query('SELECT s.id FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c', ['c' => $foreign])->fetchColumn();

    $db->query('INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:e,:n,:d,"Teacher",50000,"active",:x,:y)', ['i' => $institute, 'e' => 'TW-' . $suffix, 'n' => 'TW Teacher ' . $suffix, 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
    $employee = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:x,:y)', ['i' => $institute, 'c' => $assigned, 'r' => 'TWS-' . $suffix, 'n' => 'TW Student', 'd' => date('Y-m-d'), 'x' => $now, 'y' => $now]);
    $student = (int) $pdo->lastInsertId();

    $credential = (new CredentialService($db))->create('employee', $employee, $institute, 'tw.' . $suffix . '@example.test', $admin, 'TeacherPass#' . $suffix);
    $user = (int) $credential['user_id'];

    // Subject-level assignment only: this teacher is NOT a homeroom teacher.
    (new PakistanErpRepository($db))->saveAssignment($institute, $employee, $assigned, $subject, null, 'subject', $admin);

    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));
    $get = static fn (string $uri): \App\Core\Response => $app->run(new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));
    $post = static fn (string $uri, array $body): \App\Core\Response => $app->run(new Request([], $body, [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));

    // ---- pages render --------------------------------------------------------
    $check('teacher workspace renders', $get('/portal/employee/teaching')->status() === 200);
    $check('teacher attendance page renders', $get('/portal/employee/teaching/attendance?class=' . $assigned)->status() === 200);
    $check('teacher homework workspace requires its view permission', $get('/portal/employee/teaching/homework?class=' . $assigned)->status() === 403);
    $check('teacher notes page renders', $get('/portal/employee/teaching/notes?class=' . $assigned)->status() === 200);
    $check('teacher tests page renders', $get('/portal/employee/teaching/tests?class=' . $assigned)->status() === 200);
    $check('teaching menu appears for assigned teacher', str_contains($get('/portal/employee')->content(), 'My Teaching'));

    // ---- CSRF ---------------------------------------------------------------
    $check('teacher attendance enforces CSRF', $post('/portal/employee/teaching/attendance', ['class' => $assigned])->status() === 419);

    // ---- attendance: a subject teacher may mark their class register ---------
    $post('/portal/employee/teaching/attendance', ['_token' => CSRF::token(), 'class' => $assigned, 'date' => date('Y-m-d'), 'status' => [$student => 'Late'], 'remarks' => [$student => 'late bus']]);
    $status = $db->query('SELECT status FROM student_attendance WHERE student_id=:s AND attendance_date=:d', ['s' => $student, 'd' => date('Y-m-d')])->fetchColumn();
    $check('subject teacher can mark class attendance', $status === 'Late');
    $check('Late is stored distinctly from Present', $status !== 'Present');

    // ---- attendance: cannot touch an unassigned class ------------------------
    $post('/portal/employee/teaching/attendance', ['_token' => CSRF::token(), 'class' => $foreign, 'date' => date('Y-m-d'), 'status' => []]);
    $leaked = (int) $db->query('SELECT COUNT(*) FROM student_attendance a JOIN students s ON s.id=a.student_id WHERE s.class_id=:c', ['c' => $foreign])->fetchColumn();
    $check('teacher cannot mark attendance for unassigned class', $leaked === 0);

    // ---- homework: authored as self, scoped ---------------------------------
    $post('/portal/employee/teaching/homework', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'date' => date('Y-m-d'), 'homework' => 'TW homework ' . $suffix]);
    $homework = (int) $db->query('SELECT COUNT(*) FROM homework WHERE teacher_id=:t AND class_id=:c', ['t' => $employee, 'c' => $assigned])->fetchColumn();
    $check('teacher without homework manage cannot publish homework to assigned class', $homework === 0);

    $post('/portal/employee/teaching/homework', ['_token' => CSRF::token(), 'class_id' => $foreign, 'subject_id' => $foreignSubject, 'date' => date('Y-m-d'), 'homework' => 'leak ' . $suffix]);
    $check('teacher cannot publish homework to unassigned class', (int) $db->query('SELECT COUNT(*) FROM homework WHERE class_id=:c', ['c' => $foreign])->fetchColumn() === 0);

    // ---- notes ---------------------------------------------------------------
    $post('/portal/employee/teaching/notes', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'title' => 'TW note ' . $suffix, 'publish_date' => date('Y-m-d'), 'status' => 'published']);
    $check('teacher can upload a class note', (int) $db->query('SELECT COUNT(*) FROM class_notes WHERE teacher_id=:t AND class_id=:c', ['t' => $employee, 'c' => $assigned])->fetchColumn() === 1);
    $post('/portal/employee/teaching/notes', ['_token' => CSRF::token(), 'class_id' => $foreign, 'subject_id' => $foreignSubject, 'title' => 'leak note ' . $suffix, 'publish_date' => date('Y-m-d')]);
    $check('teacher cannot upload notes to unassigned class', (int) $db->query('SELECT COUNT(*) FROM class_notes WHERE class_id=:c', ['c' => $foreign])->fetchColumn() === 0);

    // ---- class tests and absent marking --------------------------------------
    $post('/portal/employee/teaching/tests', ['_token' => CSRF::token(), 'class_id' => $assigned, 'subject_id' => $subject, 'title' => 'TW Quiz ' . $suffix, 'date' => date('Y-m-d'), 'total_marks' => 20]);
    $test = (int) $db->query('SELECT id FROM class_tests WHERE title=:t', ['t' => 'TW Quiz ' . $suffix])->fetchColumn();
    $check('teacher can create a class test', $test > 0);

    $post('/portal/employee/teaching/tests', ['_token' => CSRF::token(), 'class_id' => $foreign, 'subject_id' => $foreignSubject, 'title' => 'TW Leak ' . $suffix, 'date' => date('Y-m-d'), 'total_marks' => 20]);
    $check('teacher cannot create a test for unassigned class', (int) $db->query('SELECT COUNT(*) FROM class_tests WHERE title=:t', ['t' => 'TW Leak ' . $suffix])->fetchColumn() === 0);

    if ($test > 0) {
        $check('teacher marks page renders', $get('/portal/employee/teaching/tests/' . $test . '/marks')->status() === 200);
        $post('/portal/employee/teaching/tests/' . $test . '/marks', ['_token' => CSRF::token(), 'marks' => [$student => ''], 'absent' => [$student => '1']]);
        $absent = $db->query('SELECT is_absent FROM class_test_marks WHERE class_test_id=:t AND student_id=:s', ['t' => $test, 's' => $student])->fetchColumn();
        $check('absent is recorded rather than a zero mark', (int) $absent === 1);
    }

    // ---- a staff role alone must still open the portal ----------------------
    // An admin can grant "teacher" from Roles & Permissions without also granting the generic
    // "employee" role. Gating the portal on role slugs alone locked such users out of their
    // own workspace, so access is decided by the linked employee record.
    $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $user]);
    $db->query('INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug=:s', ['u' => $user, 's' => 'teacher']);
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));
    $roles = (array) (user()['roles'] ?? []);
    $check('staff role without the generic employee role is set up', in_array('teacher', $roles, true) && !in_array('employee', $roles, true));
    $app->session()->forget('auth_user');
    $loginResult = (new AuthService(new UserRepository($db), $app->session()))->attempt('tw.' . $suffix . '@example.test', 'TeacherPass#' . $suffix);
    $check('staff role without generic employee role can authenticate', $loginResult === 'authenticated');
    $app->session()->forget('auth_user');
    $loginResponse = $post('/login', ['_token' => CSRF::token(), 'email' => 'tw.' . $suffix . '@example.test', 'password' => 'TeacherPass#' . $suffix]);
    // Teachers see only their own assignments, so they land in their portal, not the institute dashboard.
    $check('teacher lands in the teacher portal', ($loginResponse->headers()['Location'] ?? '') === url('/portal/employee'));
    $check('teacher-only role can still open the employee portal', $get('/portal/employee')->status() === 200);
    $check('teacher-only role can still open the teaching workspace', $get('/portal/employee/teaching')->status() === 200);

    // Restore the employee role for the remaining assertions.
    $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $user]);
    $db->query('INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug=:s', ['u' => $user, 's' => 'employee']);
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));

    // ---- an employee with no assignment gets no workspace --------------------
    $db->query('DELETE FROM teacher_assignments WHERE employee_id=:e', ['e' => $employee]);
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));
    $check('unassigned employee sees no teaching menu', !str_contains($get('/portal/employee')->content(), 'My Teaching'));
    $post('/portal/employee/teaching/attendance', ['_token' => CSRF::token(), 'class' => $assigned, 'date' => date('Y-m-d'), 'status' => [$student => 'Absent']]);
    $check('unassigned employee cannot mark attendance', $db->query('SELECT status FROM student_attendance WHERE student_id=:s AND attendance_date=:d', ['s' => $student, 'd' => date('Y-m-d')])->fetchColumn() === 'Late');

    // Full homework grants use the shared admin workspace, regardless of assignments.
    $roleRepo = new \App\Repositories\RoleRepository($db);
    $homeworkRole = $roleRepo->create('Homework manager ' . $suffix);
    $permissionIds = array_map('intval', $db->query("SELECT id FROM permissions WHERE slug IN ('homework.view','homework.manage')")->fetchAll(PDO::FETCH_COLUMN));
    $roleRepo->syncPermissions($homeworkRole, $permissionIds);
    $roleRepo->assign($homeworkRole, $user, $institute);
    $app->session()->set('auth_user', (new UserRepository($db))->findAuthContext($user));
    $shared = $get('/admin/homework');
    $check('homework manager opens the shared workspace', $shared->status() === 200);
    $check('homework manager has all audience options and optional subject', str_contains($shared->content(), 'All students') && str_contains($shared->content(), 'Specific student') && str_contains($shared->content(), 'name="class_ids[]"') && str_contains($shared->content(), '>General</option>'));
    $check('teacher homework entry redirects to admin interface', ($get('/portal/employee/teaching/homework')->headers()['Location'] ?? '') === url('/admin/homework'));
    $check('employee homework entry redirects to admin interface', ($get('/portal/employee/homework')->headers()['Location'] ?? '') === url('/admin/homework'));
    $db->query("INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:n,:nm,:d,'Teacher',1,'active',:c,:u)",['i'=>$institute,'n'=>'HW-C-'.$suffix,'nm'=>'Homework colleague '.$suffix,'d'=>date('Y-m-d'),'c'=>$now,'u'=>$now]);
    $colleague=(int)$pdo->lastInsertId();
    $body = ['_token'=>CSRF::token(),'hwdate'=>date('Y-m-d'),'hwteacher'=>$colleague,'audience_type'=>'class','class_ids'=>[$assigned,$foreign],'hwsubject'=>'','homework'=>'Full homework ' . $suffix];
    $post('/admin/homework', $body);
    $fullId = (int)$db->query('SELECT id FROM homework WHERE homework_text=:t',['t'=>$body['homework']])->fetchColumn();
    $check('homework manager assigns multiple classes without assignment scope', $fullId > 0 && (int)$db->query('SELECT COUNT(*) FROM homework_classes WHERE homework_id=:h',['h'=>$fullId])->fetchColumn() === 2);
    $check('teacher cannot impersonate another author on creation', (int)$db->query('SELECT teacher_id FROM homework WHERE id=:h',['h'=>$fullId])->fetchColumn()===$employee);
    $check('teacher author control is read-only while full audience controls remain available', !preg_match('~<select[^>]*name="hwteacher"~',$get('/admin/homework')->content()) && str_contains($get('/admin/homework')->content(),'readonly'));
    $app->session()->set('auth_user',(new UserRepository($db))->findAuthContext($admin));
    $post('/admin/homework/'.$fullId,$body);
    $check('administrator can choose the teacher author', (int)$db->query('SELECT teacher_id FROM homework WHERE id=:h',['h'=>$fullId])->fetchColumn()===$colleague);
    $app->session()->set('auth_user',(new UserRepository($db))->findAuthContext($user));
    $editingResponse = $app->run(new Request(['edit'=>$fullId],[],[],['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/admin/homework']));
    $check('full homework access includes a colleague author', str_contains($get('/admin/homework')->content(),'Homework colleague '.$suffix));
    $check('multi-class homework edit form keeps both selections', substr_count($editingResponse->content(), 'checked') >= 2);
    $body['audience_type']='all';
    $body['homework']='All homework ' . $suffix;
    $post('/admin/homework/' . $fullId,$body);
    $saved=$db->query('SELECT audience_type,subject_id FROM homework WHERE id=:h',['h'=>$fullId])->fetch();
    $check('homework manager edits multi-class homework to all students with General subject', ($saved['audience_type']??'')==='all' && $saved['subject_id']===null && (int)$db->query('SELECT COUNT(*) FROM homework_classes WHERE homework_id=:h',['h'=>$fullId])->fetchColumn()===0);
    $body['audience_type']='student'; $body['student_id']=$student; $body['hwteacher']=$employee;
    $post('/admin/homework/' . $fullId,$body);
    $check('teacher editing homework preserves its original author despite tampering', (int)$db->query('SELECT teacher_id FROM homework WHERE id=:h',['h'=>$fullId])->fetchColumn()===$colleague);
    $check('homework manager edits a specific-student audience', (int)$db->query('SELECT student_id FROM homework WHERE id=:h',['h'=>$fullId])->fetchColumn()===$student);
    $app->session()->set('auth_user',(new UserRepository($db))->findAuthContext($admin));
    $adminPage=$get('/admin/homework')->content();
    $app->session()->set('auth_user',(new UserRepository($db))->findAuthContext($user));
    $teacherPage=$get('/admin/homework')->content();
    $extractMain=static function(string $html):string { preg_match('~<main\b.*?</main>~s',$html,$m);return$m[0]??''; };
    $check('admin and teacher share the homework interface with teacher attribution locked', str_contains($adminPage,'<h2>Add Homework') && str_contains($teacherPage,'<h2>Add Homework') && str_contains($teacherPage,'Specific student') && preg_match('~<select[^>]*name="hwteacher"~',$adminPage) && !preg_match('~<select[^>]*name="hwteacher"~',$teacherPage));
    $viewId=(int)$db->query("SELECT id FROM permissions WHERE slug='homework.view'")->fetchColumn();
    $roleRepo->syncPermissions($homeworkRole,[$viewId]);
    $readOnly=$get('/admin/homework');
    $check('view-only homework user sees records without mutation controls', $readOnly->status()===200 && !str_contains($readOnly->content(),'Add Homework') && !str_contains($readOnly->content(),'Delete</button>'));
    $check('view-only user cannot delete through admin endpoint', $post('/admin/homework/'.$fullId.'/delete',['_token'=>CSRF::token()])->status()===403);
    $check('view-only user cannot delete through old teacher endpoint', $post('/portal/employee/teaching/homework/'.$fullId.'/delete',['_token'=>CSRF::token()])->status()===403);
    $check('view-only user cannot post through old teacher endpoint', $post('/portal/employee/teaching/homework',$body)->status()===403);
    $roleRepo->syncPermissions($homeworkRole,$permissionIds);
    $post('/portal/employee/teaching/homework/'.$fullId.'/delete',['_token'=>CSRF::token()]);
    $check('full homework manager deletes through either entry point', !$db->query('SELECT id FROM homework WHERE id=:h',['h'=>$fullId])->fetchColumn());
    // Author fields in the other teaching modules follow the same signed-in identity.
    $resourceIds=array_map('intval',$db->query("SELECT id FROM permissions WHERE slug IN ('notes.view','notes.manage','recorded-lectures.view','recorded-lectures.manage')")->fetchAll(PDO::FETCH_COLUMN));
    $roleRepo->syncPermissions($homeworkRole,array_merge($permissionIds,$resourceIds));
    $resource=['_token'=>CSRF::token(),'audience_type'=>'all','teacher_id'=>$colleague,'title'=>'Identity resource '.$suffix,'publish_date'=>date('Y-m-d'),'status'=>'published','external_url'=>'https://example.test/note','video_url'=>'https://example.test/lecture'];
    $post('/admin/notes',$resource);
    $check('teacher notes reject another teacher attribution', (int)$db->query('SELECT teacher_id FROM class_notes WHERE title=:t',['t'=>$resource['title']])->fetchColumn()===$employee);
    $post('/admin/recorded-lectures',$resource);
    $check('teacher recorded lecture rejects another teacher attribution', (int)$db->query('SELECT teacher_id FROM recorded_lectures WHERE title=:t',['t'=>$resource['title']])->fetchColumn()===$employee);
    $check('teacher resource screens lock author selection', !preg_match('~<select[^>]*name="teacher_id"~',$get('/admin/notes')->content()) && !preg_match('~<select[^>]*name="teacher_id"~',$get('/admin/recorded-lectures')->content()));
    $check('live class author resolver uses signed-in employee', \App\Support\TeacherIdentity::author($db,$institute,$colleague)===$employee);
    $app->session()->set('auth_user',(new UserRepository($db))->findAuthContext($admin));
    $check('administrator author resolver keeps chosen teacher', \App\Support\TeacherIdentity::author($db,$institute,$colleague)===$colleague);
    $context=(new UserRepository($db))->findAuthContext($user);$context['roles'][]='teacher';
    $db->query('DELETE FROM employee_accounts WHERE employee_id=:e',['e'=>$employee]);
    $app->session()->set('auth_user',$context);
    $unlinkedDenied=false;try { \App\Support\TeacherIdentity::author($db,$institute,$colleague); } catch (\DomainException $e) { $unlinkedDenied=true; }
    $check('unlinked teacher cannot impersonate an employee', $unlinkedDenied);
    $db->query('DELETE FROM recorded_lectures WHERE title=:t',['t'=>$resource['title']]);
    $db->query('DELETE FROM employees WHERE id=:e',['e'=>$colleague]);
    $db->query('DELETE FROM user_roles WHERE role_id=:r',['r'=>$homeworkRole]);
    $roleRepo->delete($homeworkRole);

    // ---- cleanup -------------------------------------------------------------
    $db->query('DELETE FROM class_test_marks WHERE student_id=:s', ['s' => $student]);
    $db->query('DELETE FROM class_tests WHERE institute_id=:i AND title LIKE :t', ['i' => $institute, 't' => 'TW %' . $suffix]);
    $db->query('DELETE FROM class_notes WHERE teacher_id=:t', ['t' => $employee]);
    $db->query('DELETE FROM homework WHERE teacher_id=:t', ['t' => $employee]);
    $db->query('DELETE FROM student_attendance WHERE student_id=:s', ['s' => $student]);
    $db->query('DELETE FROM employee_accounts WHERE employee_id=:e', ['e' => $employee]);
    $db->query('DELETE FROM user_roles WHERE user_id=:u', ['u' => $user]);
    $db->query('DELETE FROM institute_users WHERE user_id=:u', ['u' => $user]);
    $db->query('DELETE FROM users WHERE id=:u', ['u' => $user]);
    $db->query('DELETE FROM students WHERE id=:s', ['s' => $student]);
    $db->query('DELETE FROM employees WHERE id=:e', ['e' => $employee]);
    $db->query('DELETE FROM class_subjects WHERE class_id IN (:a,:b)', ['a' => $assigned, 'b' => $foreign]);
    $db->query('DELETE FROM subjects WHERE id IN (:a,:b)', ['a' => $subject, 'b' => $foreignSubject]);
    $db->query('DELETE FROM classes WHERE id IN (:a,:b)', ['a' => $assigned, 'b' => $foreign]);

    return $out;
};
