<?php
declare(strict_types=1);
/**
 * LOCAL DEMO DATA for walking through this release's features. Never run on production.
 *
 *   php database/seed_showcase.php --seed
 *
 * Idempotent: re-running refreshes the same records. Every login uses the password below.
 */
use App\Repositories\{AcademicRepository,PakistanErpRepository,RecordedLectureRepository,SettingsRepository,WorkflowRepository,AnnouncementRepository,OnlineTestRepository};
use App\Services\{AccountAccess,CredentialService};
use App\Services\Sms\{SmsAutomation,SmsQueue};

if (($argv[1] ?? '') !== '--seed') { fwrite(STDERR, "Local demo data. Run with --seed\n"); exit(1); }
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
$app = require BASE_PATH . '/bootstrap/app.php';
// Signed-up schools (SCHOOL_SLUG set) may load demo data; the main Arete portal in production never.
if ((string) $app->config('app.env', '') === 'production' && school_slug() === '') { fwrite(STDERR, "Refusing to seed demo data in production.\n"); exit(1); }

const PASSWORD = 'Showcase#2026';
$db = $app->database();
$pdo = $db->connection();
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$institute = (int) (new SettingsRepository($db))->institute()['id'];
$admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" ORDER BY u.id LIMIT 1')->fetchColumn();
$academic = new AcademicRepository($db);
$credentials = new CredentialService($db);
$erp = new PakistanErpRepository($db);
$one = static fn(string $sql, array $p = []) => $db->query($sql, $p)->fetchColumn();

// ---- class and subjects --------------------------------------------------------------------
$className = 'Grade 9 – Showcase';
if (!$one('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => $className])) {
    $academic->createClass($institute, $className, 4500, 'Showcase');
}
$classId = (int) $one('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => $className]);
$subjects = [];
foreach (['Mathematics', 'Physics', 'English', 'Urdu', 'Computer Science'] as $name) {
    $academic->assign($institute, $classId, $name, 100);
    $subjects[$name] = (int) $one('SELECT id FROM subjects WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => $name]);
}

// ---- teachers --------------------------------------------------------------------------------
$teachers = [];
foreach ([
    ['SHOW-T1', 'Dr. Sana Malik', 'PhD Applied Mathematics', 'teacher.showcase@arete.local', 'subject', ['Mathematics', 'Physics']],
    ['SHOW-T2', 'Mr. Kamran Yousaf', 'M.Phil English Literature', 'english.showcase@arete.local', 'subject', ['English']],
    ['SHOW-T3', 'Ms. Hina Tariq', 'M.Sc Computer Science', 'classteacher.showcase@arete.local', 'whole_class', []],
] as [$no, $name, $edu, $email, $type, $subs]) {
    $id = (int) $one('SELECT id FROM employees WHERE institute_id=:i AND employee_no=:n', ['i' => $institute, 'n' => $no]);
    if (!$id) {
        $db->query("INSERT INTO employees(institute_id,employee_no,name,mobile,joining_date,role,monthly_salary,education,status,created_at,updated_at) VALUES(:i,:no,:n,'03111234567',:d,'Teacher',80000,:e,'active',:c,:u)",
            ['i' => $institute, 'no' => $no, 'n' => $name, 'd' => $today, 'e' => $edu, 'c' => $now, 'u' => $now]);
        $id = (int) $pdo->lastInsertId();
    }
    $user = (int) $credentials->create('employee', $id, $institute, $email, $admin, PASSWORD)['user_id'];
    $db->query("INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug='teacher'", ['u' => $user]);
    $db->query('DELETE FROM teacher_assignments WHERE employee_id=:e', ['e' => $id]);
    foreach ($type === 'whole_class' ? [null] : array_map(fn($s) => $subjects[$s], $subs) as $subjectId) {
        $erp->saveAssignment($institute, $id, $classId, $subjectId, null, $type, $admin);
    }
    $teachers[$no] = ['id' => $id, 'user' => $user, 'email' => $email, 'name' => $name];
}

// ---- students & parent ---------------------------------------------------------------------------
$students = [];
foreach ([
    ['SHOW-S1', 'Ayesha Khan', 'student.showcase@arete.local', 'Imran Khan', '03001234501', 'active'],
    ['SHOW-S2', 'Bilal Ahmed', 'suspended.showcase@arete.local', 'Naveed Ahmed', '03001234502', 'suspended'],
    ['SHOW-S3', 'Hamza Raza', 'blocked.showcase@arete.local', 'Tariq Raza', '03001234503', 'disabled'],
] as [$reg, $name, $email, $father, $mobile, $level]) {
    $id = (int) $one('SELECT id FROM students WHERE institute_id=:i AND registration_no=:r', ['i' => $institute, 'r' => $reg]);
    if (!$id) {
        $db->query("INSERT INTO students(institute_id,class_id,registration_no,student_name,date_of_admission,father_name,father_mobile,mobile,status,created_at,updated_at) VALUES(:i,:c,:r,:n,:d,:f,:fm,:m,'active',:x,:x2)",
            ['i' => $institute, 'c' => $classId, 'r' => $reg, 'n' => $name, 'd' => $today, 'f' => $father, 'fm' => $mobile, 'm' => $mobile, 'x' => $now, 'x2' => $now]);
        $id = (int) $pdo->lastInsertId();
    }
    $credentials->create('student', $id, $institute, $email, $admin, PASSWORD);
    $students[$reg] = ['id' => $id, 'email' => $email, 'name' => $name, 'level' => $level];
}
// Bilal: three overdue unpaid months, suspended with the fee notice. Hamza: blocked.
$bilal = $students['SHOW-S2']['id'];
foreach ([1, 2, 3] as $n) {
    $month = date('Y-m', strtotime("first day of -{$n} month"));
    if (!$one('SELECT 1 FROM fee_invoices WHERE student_id=:s AND fee_month=:m', ['s' => $bilal, 'm' => $month])) {
        $db->query("INSERT INTO fee_invoices(student_id,fee_month,invoice_no,subtotal,discount,previous_balance,total_amount,paid_amount,remaining_amount,status,due_date,created_by,created_at,updated_at) VALUES(:s,:m,:no,4500,0,0,4500,0,4500,'unpaid',:d,:u,:x,:x2)",
            ['s' => $bilal, 'm' => $month, 'no' => 'SHOW-' . $month, 'd' => $month . '-10', 'u' => $admin, 'x' => $now, 'x2' => $now]);
    }
}
$access = new AccountAccess($db);
$access->setManual('student', $bilal, $institute, 'suspended', 'Fees unpaid for 3 months');
$access->setManual('student', $students['SHOW-S3']['id'], $institute, 'disabled', 'Left the institute');
$access->setManual('student', $students['SHOW-S1']['id'], $institute, 'active', '', null);
$gid = (int) $one("SELECT id FROM guardians WHERE institute_id=:i AND name='Imran Khan (Showcase)'", ['i' => $institute]);
if (!$gid) {
    $db->query("INSERT INTO guardians(institute_id,name,relationship,mobile,status,created_at,updated_at) VALUES(:i,'Imran Khan (Showcase)','Father','03001234501','active',:c,:u)", ['i' => $institute, 'c' => $now, 'u' => $now]);
    $gid = (int) $pdo->lastInsertId();
    $db->query('INSERT INTO guardian_students(guardian_id,student_id,is_primary,created_at) VALUES(:g,:s,1,:c)', ['g' => $gid, 's' => $students['SHOW-S1']['id'], 'c' => $now]);
}
$credentials->create('guardian', $gid, $institute, 'parent.showcase@arete.local', $admin, PASSWORD);

// ---- learning content ------------------------------------------------------------------------------
$t1 = $teachers['SHOW-T1']['id'];
$lectures = new RecordedLectureRepository($db);
$db->query("DELETE FROM recorded_lectures WHERE institute_id=:i AND title LIKE '%(Showcase)'", ['i' => $institute]);
foreach ([
    ['Mathematics', 'Quadratic equations (Showcase)', 'Chapter 2', 0, $t1], ['Mathematics', 'Factorisation basics (Showcase)', 'Chapter 1', 3, $t1],
    ['Mathematics', 'Real numbers review (Showcase)', 'Chapter 1', 12, $t1], ['Physics', 'Newton\'s laws of motion (Showcase)', 'Chapter 3', 1, $t1],
    ['Physics', 'Measurements and units (Showcase)', 'Chapter 1', 20, $t1], ['English', 'Essay writing structure (Showcase)', 'Writing', 2, $teachers['SHOW-T2']['id']],
] as [$sub, $title, $chapter, $ago, $teacher]) {
    $lectures->save($institute, ['audience_type' => 'class', 'class_ids' => [$classId], 'subject_id' => $subjects[$sub], 'teacher_id' => $teacher, 'title' => $title, 'chapter' => $chapter,
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'publish_date' => date('Y-m-d', strtotime("-{$ago} days")), 'status' => 'published', 'created_by' => $admin]);
}
$workflow = new WorkflowRepository($db);
$db->query("DELETE FROM homework WHERE homework_text LIKE '%(Showcase)' AND teacher_id IN(SELECT id FROM employees WHERE institute_id=:i)", ['i' => $institute]);
foreach ([['Mathematics', 'Solve exercise 2.1, questions 1–10 (Showcase)', $t1], ['Physics', 'Write three examples of Newton\'s third law (Showcase)', $t1], ['English', 'Write a 200-word essay on your hometown (Showcase)', $teachers['SHOW-T2']['id']]] as [$sub, $text, $teacher]) {
    $workflow->createHomework($institute, ['date' => $today, 'teacher_id' => $teacher, 'class_id' => $classId, 'subject_id' => $subjects[$sub], 'text' => $text], $admin);
}
$db->query("DELETE FROM class_notes WHERE institute_id=:i AND title LIKE '%(Showcase)'", ['i' => $institute]);
$erp->saveNote($institute, ['audience_type' => 'class', 'class_ids' => [$classId], 'subject_id' => $subjects['Mathematics'], 'teacher_id' => $t1, 'title' => 'Formula sheet (Showcase)', 'external_url' => 'https://example.com/formulas', 'publish_date' => $today, 'status' => 'published', 'created_by' => $admin]);
$tests = new OnlineTestRepository($db);
$db->query("DELETE FROM online_tests WHERE institute_id=:i AND title LIKE '%(Showcase)'", ['i' => $institute]);
$testId = $tests->create($institute, ['title' => 'Chapter 2 quiz (Showcase)', 'class_id' => $classId, 'subject_id' => $subjects['Mathematics'], 'duration_minutes' => 20, 'closes_at' => date('Y-m-d H:i', strtotime('+5 days'))],
    [['type' => 'mcq', 'text' => 'What is the discriminant of x² + 2x + 1?', 'marks' => 2, 'options' => ['0', '1', '4'], 'correct' => 0]], (int) $teachers['SHOW-T1']['user']);
$tests->setStatus($institute, $testId, 'published');
$ann = new AnnouncementRepository($db);
$db->query("DELETE FROM announcements WHERE institute_id=:i AND title LIKE '%(Showcase)'", ['i' => $institute]);
$ann->create($institute, ['title' => 'Mid-term schedule (Showcase)', 'message' => 'Mid-term exams start next Monday. Timetable is on the notice board.', 'audience' => 'all', 'status' => 'published'], $admin);
$ann->create($institute, ['title' => 'Bring your geometry box (Showcase)', 'message' => 'Tomorrow\'s maths class needs a compass and protractor.', 'audience' => 'students', 'teacher_id' => $t1, 'class_id' => $classId, 'status' => 'published'], (int) $teachers['SHOW-T1']['user']);
// One live class on air right now, for the Physics card.
$db->query("DELETE FROM live_classes WHERE institute_id=:i AND title LIKE '%(Showcase)'", ['i' => $institute]);
$db->query("INSERT INTO live_classes(institute_id,provider,title,audience_type,target_class_id,subject_id,teacher_id,scheduled_at,start_time,end_time,timezone,meet_link,status,google_sync_status,duration_minutes,created_by,created_by_role,created_at,updated_at)
    VALUES(:i,'google_meet','Physics revision (Showcase)','SC',:c,:s,:t,:st,:st2,:en,'Asia/Karachi','https://meet.google.com/abc-defg-hij','scheduled','not_applicable',60,:u,'teacher',:n,:n2)",
    ['i' => $institute, 'c' => $classId, 's' => $subjects['Physics'], 't' => $t1, 'st' => gmdate('Y-m-d H:i:s', time() - 600), 'st2' => gmdate('Y-m-d H:i:s', time() - 600), 'en' => gmdate('Y-m-d H:i:s', time() + 3000), 'u' => $admin, 'n' => $now, 'n2' => $now]);

// ---- SMS: a few messages so the SMS Center has something to show -----------------------------------
(new SmsAutomation($db))->ensureDefaults($institute);
$queue = new SmsQueue($db);
// Not on a signed-up school: its demo numbers must never be texted once it pairs a gateway.
if (school_slug() === '') foreach (['03001234501' => 'Dear Imran Khan, Ayesha was marked Absent today. (Showcase)', '03001234502' => 'Dear Naveed Ahmed, fee of Rs 13,500 for Bilal is pending. (Showcase)'] as $phone => $text) {
    $queue->enqueue($institute, ['phone_number' => $phone, 'message' => $text, 'recipient_type' => 'guardian', 'message_type' => 'general', 'dedupe_key' => 'showcase:' . $phone, 'created_by' => $admin]);
}

echo "Showcase data ready (password for every login: " . PASSWORD . ")\n";
foreach ($teachers as $t) echo "  teacher  {$t['email']}  ({$t['name']})\n";
foreach ($students as $s) echo "  student  {$s['email']}  ({$s['name']}, {$s['level']})\n";
echo "  parent   parent.showcase@arete.local\n";
