<?php
declare(strict_types=1);
/**
 * DEMO DATA for a signed-up school, so it can be walked through end to end: classes, subjects,
 * teachers, ~40 students, two weeks of attendance, three months of fee invoices and payments,
 * exams with marks, class tests, salaries and income/expenses. Refuses to run on the main Arete
 * portal. Re-running skips what already exists.
 *
 *   php scripts/school.php /var/www/schools/<slug> scripts/seed_school_demo.php --seed
 */
use App\Repositories\{AcademicRepository,AccountRepository,AttendanceRepository,FeeRepository,SettingsRepository,StudentRepository,WorkflowRepository};
use App\Services\{AttendanceService,FeeService,StudentService};

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
$app = require BASE_PATH . '/bootstrap/app.php';
if (school_slug() === '') { fwrite(STDERR, "Refusing: this is not a signed-up school site.\n"); exit(1); }
if (!in_array('--seed', $argv, true)) { fwrite(STDERR, "Demo data for school " . school_slug() . ". Run with --seed\n"); exit(1); }

mt_srand(2026); // same data on every run
$db = $app->database();
$pdo = $db->connection();
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$institute = (int) (new SettingsRepository($db))->institute()['id'];
$admin = (int) $db->query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='admin' ORDER BY u.id LIMIT 1")->fetchColumn();
$one = static fn(string $sql, array $p = []) => $db->query($sql, $p)->fetchColumn();
$academic = new AcademicRepository($db);
$students = new StudentRepository($db);
$studentService = new StudentService($students);
$workflow = new WorkflowRepository($db);

// ---- classes & subjects --------------------------------------------------------------------
$classes = [];
foreach (['Grade 6 (Demo)' => 3000, 'Grade 7 (Demo)' => 3500, 'Grade 8 (Demo)' => 4000] as $name => $fee) {
    if (!$one('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => $name])) $academic->createClass($institute, $name, $fee, null);
    $classId = (int) $one('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => $name]);
    $subjects = [];
    foreach (['Mathematics', 'English', 'Science', 'Urdu', 'Islamiat'] as $subject) {
        $academic->assign($institute, $classId, $subject, 100);
        $subjects[$subject] = (int) $one('SELECT id FROM subjects WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => $subject]);
    }
    $classes[$name] = ['id' => $classId, 'fee' => $fee, 'subjects' => $subjects];
}

// ---- staff -------------------------------------------------------------------------------------
$employees = [];
foreach ([
    ['DEMO-E1', 'Ayesha Siddiqui', 'Principal', 120000], ['DEMO-E2', 'Usman Tariq', 'Teacher', 65000],
    ['DEMO-E3', 'Fatima Noor', 'Teacher', 60000], ['DEMO-E4', 'Bilal Hussain', 'Teacher', 58000],
    ['DEMO-E5', 'Saima Javed', 'Accountant', 55000],
] as [$no, $name, $role, $salary]) {
    $id = (int) $one('SELECT id FROM employees WHERE institute_id=:i AND employee_no=:n', ['i' => $institute, 'n' => $no]);
    if (!$id) {
        $db->query("INSERT INTO employees(institute_id,employee_no,name,joining_date,role,monthly_salary,status,created_at,updated_at) VALUES(:i,:no,:n,:d,:r,:s,'active',:c,:u)",
            ['i' => $institute, 'no' => $no, 'n' => $name, 'd' => date('Y-m-d', strtotime('-8 months')), 'r' => $role, 's' => $salary, 'c' => $now, 'u' => $now]);
        $id = (int) $pdo->lastInsertId();
    }
    $employees[$no] = ['id' => $id, 'salary' => $salary];
}

// ---- students (no phone numbers, so nothing can ever be texted to a real person) ---------------
$first = ['Ali', 'Hassan', 'Zain', 'Hamza', 'Umar', 'Saad', 'Ahmed', 'Rayyan', 'Ibrahim', 'Musa', 'Ayesha', 'Fatima', 'Zara', 'Maryam', 'Hira', 'Iqra', 'Amna', 'Sana', 'Noor', 'Eman'];
$last = ['Khan', 'Ahmed', 'Malik', 'Raza', 'Iqbal', 'Sheikh', 'Butt', 'Chaudhry', 'Qureshi', 'Mirza'];
$roster = [];
$n = 0;
foreach ($classes as $className => $class) {
    for ($k = 1; $k <= 13; $k++) {
        $n++;
        $reg = sprintf('DEMO-%03d', $n);
        $student = $students->byRegistration($reg, $institute);
        if (!$student) {
            $surname = $last[mt_rand(0, count($last) - 1)];
            $id = $studentService->create($studentService->data([
                'studentname' => $first[($n * 7) % count($first)] . ' ' . $surname, 'reg' => $reg,
                'doa' => date('Y-m-d', strtotime('-' . mt_rand(60, 400) . ' days')), 'class' => $class['id'],
                'discount' => $k % 6 === 0 ? 10 : 0, 'fname' => $first[mt_rand(0, 9)] . ' ' . $surname,
                'gender' => ($n * 7) % count($first) >= 10 ? 'Female' : 'Male', 'dob' => date('Y-m-d', strtotime('-' . mt_rand(11, 14) . ' years -' . mt_rand(0, 300) . ' days')),
            ], $institute));
            $student = $students->find($id, $institute);
        }
        $roster[] = $student + ['class_key' => $className];
    }
}

// ---- attendance: last 10 school days ---------------------------------------------------------------
$attendance = new AttendanceService(new AttendanceRepository($db));
$days = [];
for ($d = 0; count($days) < 10; $d++) {
    $date = date('Y-m-d', strtotime("-{$d} days"));
    if (date('N', strtotime($date)) < 6) $days[] = $date;
}
$studentIds = array_map(static fn($s) => (int) $s['id'], $roster);
$staffIds = array_map(static fn($e) => (int) $e['id'], $employees);
foreach ($days as $date) {
    $status = [];
    foreach ($studentIds as $id) { $r = mt_rand(1, 100); $status[$id] = $r <= 86 ? 'Present' : ($r <= 94 ? 'Absent' : 'Leave'); }
    $attendance->saveStudents($date, $studentIds, $status, [], $admin);
    $staff = [];
    foreach ($staffIds as $id) $staff[$id] = mt_rand(1, 100) <= 93 ? 'Present' : 'Leave';
    $attendance->saveEmployees($date, $staffIds, $staff, array_fill_keys($staffIds, '08:00'), array_fill_keys($staffIds, '14:30'), [], $admin);
}

// ---- fees: three months; most paid, some partial, some unpaid ----------------------------------
$fees = new FeeService(new FeeRepository($db), $students);
foreach ([2, 1, 0] as $ago) {
    $month = date('Y-m', strtotime("first day of -{$ago} month"));
    foreach ($roster as $i => $student) {
        $full = $students->find((int) $student['id'], $institute);
        $invoice = $fees->ensureInvoice($full, $institute, $month, $admin);
        if ((float) $invoice['paid_amount'] > 0) continue;
        $remaining = (float) $invoice['remaining_amount'];
        $pattern = ($i + $ago) % 10;
        $amount = $pattern < 7 ? $remaining : ($pattern < 9 ? round($remaining / 2) : 0);
        if ($ago === 0 && $pattern >= 5) $amount = 0; // this month is still being collected
        if ($amount > 0) $fees->collect($full, $institute, $month, $amount, $pattern % 2 ? 'Bank' : 'Cash', 'DEMO-' . $month . '-' . $student['registration_no'], $admin, $month . '-0' . mt_rand(3, 9));
    }
}

// ---- exams & class tests --------------------------------------------------------------------------
$examId = (int) $one("SELECT id FROM exams WHERE institute_id=:i AND name='Mid-Term Examination (Demo)'", ['i' => $institute]);
if (!$examId) $examId = $workflow->createExam($institute, 'Mid-Term Examination (Demo)', date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('-14 days')), $admin);
foreach ($classes as $className => $class) {
    $inClass = array_values(array_filter($roster, static fn($s) => $s['class_key'] === $className));
    foreach ($class['subjects'] as $subjectId) {
        $setup = (int) $one('SELECT id FROM exam_subjects WHERE exam_id=:e AND class_id=:c AND subject_id=:s', ['e' => $examId, 'c' => $class['id'], 's' => $subjectId]);
        if (!$setup) $setup = $workflow->setupExam($institute, $examId, $class['id'], $subjectId, 100);
        $marks = [];
        foreach ($inClass as $s) $marks[(int) $s['id']] = mt_rand(38, 98);
        $workflow->saveExamMarks($institute, $setup, $marks, $admin);
    }
    $title = 'Weekly Maths Quiz (Demo)';
    $test = (int) $one('SELECT id FROM class_tests WHERE institute_id=:i AND class_id=:c AND title=:t', ['i' => $institute, 'c' => $class['id'], 't' => $title]);
    if (!$test) $test = $workflow->createClassTest($institute, $class['id'], $class['subjects']['Mathematics'], date('Y-m-d', strtotime('-3 days')), $title, 20, $admin);
    $marks = [];
    foreach ($inClass as $s) $marks[(int) $s['id']] = mt_rand(9, 20);
    $workflow->saveClassTestMarks($institute, $test, $marks, $admin);
}

// ---- salaries (last month) & income / expenses ------------------------------------------------------
$lastMonth = date('Y-m', strtotime('first day of -1 month'));
foreach ($employees as $e) {
    if (!$one('SELECT 1 FROM salary_payments WHERE employee_id=:e AND salary_month=:m', ['e' => $e['id'], 'm' => $lastMonth])) {
        $workflow->paySalary($institute, $e['id'], $lastMonth, (float) $e['salary'], $lastMonth . '-28', $admin);
    }
}
$accounts = new AccountRepository($db);
foreach ([
    ['income', 'Admission fees (Demo)', 45000, -25], ['income', 'Uniform & books sale (Demo)', 18500, -12],
    ['expense', 'Electricity bill (Demo)', 23400, -18], ['expense', 'Stationery & printing (Demo)', 7800, -9],
    ['expense', 'Building maintenance (Demo)', 15000, -5], ['expense', 'Internet & phone (Demo)', 4500, -2],
] as [$type, $description, $amount, $offset]) {
    if (!$one('SELECT 1 FROM account_transactions WHERE institute_id=:i AND description=:d', ['i' => $institute, 'd' => $description])) {
        $accounts->create($institute, $type, date('Y-m-d', strtotime("{$offset} days")), $description, $amount, $admin, 'DEMO');
    }
}

// Demo data must never text anyone: drop anything the automations queued while seeding.
try { $db->query("DELETE FROM sms_messages WHERE status IN ('queued','pending','held')"); } catch (\Throwable) {}

echo 'Demo data ready for ' . school_slug() . ': ' . count($classes) . ' classes, ' . count($roster) . ' students, ' . count($employees) . ' staff, '
    . count($days) . " days of attendance, 3 months of fees, a mid-term exam, class tests, salaries and accounts.\n";
