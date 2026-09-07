<?php

declare(strict_types=1);

$item = static fn (string $label, string $key, string $icon, ?string $route = null, array $children = [], bool $locked = false): array => compact('label', 'key', 'icon', 'route', 'children', 'locked');
$child = static fn (string $label, string $key, ?string $route = null, bool $locked = false): array => compact('label', 'key', 'route', 'locked');

return [
    $item('Dashboard', 'dashboard', 'layout-dashboard', '/admin/dashboard'),
    $item('General Settings', 'general-settings', 'settings-2', null, [
        $child('Institute Profile', 'institute-profile', '/admin/settings/institute-profile'),
        $child('Fees Particulars', 'fee-particulars', '/admin/settings/fee-particulars'),
        $child('Fees Structure', 'fee-structure', '/admin/settings/fee-structure'),
        $child('Discount Type', 'discount-type', '/admin/settings/discount-types'),
        $child('Accounts For Fees Invoice', 'fee-invoice-accounts', '/admin/settings/fee-invoice-accounts'),
        $child('Rules & Regulations', 'rules-regulations', '/admin/settings/rules-regulations'),
        $child('Marks Grading', 'marks-grading', '/admin/settings/marks-grading'),
        $child('Academic Configuration', 'academic-profiles', '/admin/settings/academic-profiles'),
        $child('Report Templates', 'report-templates', '/admin/settings/report-templates'),
        $child('Sections & Streams', 'academic-structure', '/admin/settings/academic-structure'),
        $child('Theme & Language', 'theme-language', '/admin/settings/theme-language'),
        $child('Account Settings', 'account-settings', '/admin/settings/account'),
        $child('Logout', 'logout'),
    ]),
    $item('Classes', 'classes', 'school', null, [
        $child('All Classes', 'classes', '/admin/classes'), $child('New Class', 'class-add', '/admin/classes/create'),
        $child('Teacher Assignments', 'teacher-assignments', '/admin/teacher-assignments'),
    ]),
    $item('Subjects', 'subjects', 'book-open', null, [
        $child('Classes With Subjects', 'class-subjects', '/admin/subjects'), $child('Assign Subjects', 'subject-assign', '/admin/subjects/assign'),
    ]),
    $item('Students', 'students', 'users-round', null, [
        $child('All Students', 'students', '/admin/students'), $child('Add New', 'student-add', '/admin/students/create'),
        $child('Bulk Import CSV', 'student-import', '/admin/students#bulk-student-import'),
        $child('Manage Families', 'student-families', '/admin/students/families'), $child('Active / Inactive', 'student-status', '/admin/students/status'),
        $child('Admission Letter', 'student-admission-letter', '/admin/students/admission-letter'),
        $child('ID Cards', 'student-id-cards', '/admin/students/id-cards'), $child('Print Basic List', 'student-basic-list', '/admin/students/basic-list'),
        $child('Manage Login', 'student-logins', '/admin/students/logins'), $child('Promote Students', 'student-promotions', '/admin/students/promotions'),
        $child('Report Card', 'student-report-card', '/admin/students/report-card'),
        $child('Parents & Guardians', 'guardians', '/admin/guardians'),
    ]),
    $item('Employees', 'employees', 'briefcase-business', null, [
        $child('All Employees', 'employees', '/admin/employees'), $child('Add New', 'employee-add', '/admin/employees/create'),
        $child('Job Letter', 'employee-job-letter', '/admin/employees/job-letter'),
        $child('Manage Login', 'staff-logins', '/admin/employees/logins'),
    ]),
    $item('Accounts', 'accounts', 'landmark', null, [
        $child('Chart Of Account', 'accounts-chart', '/admin/accounts'), $child('Add Income', 'income-add', '/admin/accounts/income/create'),
        $child('Add Expense', 'expense-add', '/admin/accounts/expense/create'), $child('Account Statement', 'account-statement', '/admin/accounts/statement'),
    ]),
    $item('Fees', 'fees', 'badge-dollar-sign', null, [
        $child('Generate Fees Invoice', 'fees-generate', '/admin/fees/collect'), $child('Collect Fees', 'fees-collect', '/admin/fees/collect'),
        $child('Fees Paid Slip', 'fees-receipt', '/admin/fees/receipt'), $child('Fees Defaulters', 'fees-defaulters', '/admin/fees/defaulters'),
        $child('Fees Report', 'fees-report', '/admin/fees/report'), $child('Delete Fees', 'fees-delete', '/admin/fees/delete'),
        $child('Fee Automation', 'fee-automation', '/admin/fees/automation'),
    ]),
    $item('Salary', 'salary', 'wallet-cards', null, [
        $child('Pay Salary', 'salary-pay', '/admin/salary/pay'), $child('Salary Paid Slip', 'salary-receipt', '/admin/salary/receipt'),
    ]),
    $item('Attendance', 'attendance', 'clipboard-check', null, [
        $child('Students', 'attendance-students', '/admin/attendance/students'), $child('Employees', 'attendance-employees', '/admin/attendance/employees'),
        $child('Classwise', 'attendance-classwise', '/admin/attendance/classwise'), $child('Student Report', 'attendance-student-report', '/admin/attendance/student-report'),
        $child('Employee Report', 'attendance-employee-report', '/admin/attendance/employee-report'),
    ]),
    $item('Homework', 'homework', 'notebook-pen', null, [
        $child('Homework', 'homework', '/admin/homework'), $child('Class Notes', 'notes', '/admin/notes'),
    ]),
    $item('Messaging', 'messaging', 'messages-square', '/admin/communication/messaging'),
    $item('WhatsApp', 'whatsapp', 'message-circle', '/admin/whatsapp'),
    $item('SMS', 'sms', 'message-square-text', null, [
        $child('Gateway', 'sms-gateway', null, true),
    ]),
    $item('Live Class', 'live-class', 'video', null, [], true),
    $item('Exams', 'exams', 'file-check-2', null, [
        $child('Create New Exam', 'exam-create', '/admin/exams/create'), $child('Exam Setup', 'exam-setup', '/admin/exams/setup'),
        $child('Add / Update Marks', 'exam-marks', '/admin/exams/marks'), $child('Result Card', 'exam-results', '/admin/exams/results'),
    ]),
    $item('Class Tests', 'class-tests', 'clipboard-list', null, [
        $child('Manage Class Tests', 'test-manage', '/admin/class-tests'),
        $child('New Test Marks', 'test-marks', '/admin/class-tests/marks'), $child('Test Result', 'test-results', '/admin/class-tests/results'),
    ]),
    $item('Behaviour & Skills', 'behaviour', 'smile', null, [
        $child('Rate Behaviours', 'behaviour-rate', '/admin/behaviour/rate'),
        $child('Rate Skills', 'behaviour-skills', '/admin/behaviour/skills'),
        $child('Observations', 'behaviour-observations', '/admin/behaviour/observations'),
        $child('Traits & Skills Setup', 'behaviour-traits', '/admin/behaviour/traits'),
        $child('Affective Domain Report', 'behaviour-report-affective', '/admin/behaviour/reports/affective'),
        $child('Psycomotor Domain Report', 'behaviour-report-psychomotor', '/admin/behaviour/reports/psychomotor'),
    ]),
    $item('Reports', 'reports', 'chart-no-axes-combined', null, [
        $child('Admission Report', 'report-admissions', '/admin/reports/admissions'), $child('Contact Report', 'report-contacts', '/admin/reports/contacts'),
        $child('Students Monthly Attendance', 'report-student-attendance', '/admin/reports/student-attendance'),
        $child('Staff Monthly Attendance', 'report-employee-attendance', '/admin/reports/employee-attendance'),
        $child('Fee Collection Report', 'report-fee-collection', '/admin/reports/fee-collection'),
        $child('Accounts Report', 'report-accounts', '/admin/reports/accounts'),
        $child('Salary Report', 'report-salary', '/admin/reports/salary'),
        $child('Student Progress Report', 'report-progress', '/admin/reports/progress'),
        $child('Attendance Shortage', 'report-attendance-shortage', '/admin/reports/attendance-shortage'),
        $child('Student Test History', 'report-test-history', '/admin/reports/test-history'),
        $child('Student Register', 'report-student-register', '/admin/reports/student-register'),
        $child('Family / Sibling Report', 'report-families', '/admin/reports/families'),
        $child('Academic Activity', 'report-academic', '/admin/reports/academic'),
    ]),
    $item('Certificates', 'certificates', 'award', null, [], true),
    $item('Roles & Permissions', 'roles', 'shield-check', '/admin/roles'),
];
