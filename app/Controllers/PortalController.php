<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PortalRepository;

/**
 * Shared plumbing for the student and employee portals: identity resolution, the portal
 * shell, and the date-range filter both portals expose.
 */
abstract class PortalController extends Controller
{
    private ?PortalRepository $repository = null;

    protected function repo(): PortalRepository
    {
        return $this->repository ??= new PortalRepository($this->app->database());
    }

    /** The signed-in user's id. AuthMiddleware guarantees this exists. */
    protected function userId(): int
    {
        return (int) (user()['id'] ?? 0);
    }

    /**
     * Resolves who the signed-in portal user is. Returns null when the account holds a portal
     * role but no linked student/employee record (for example an administrator).
     *
     * @return array{audience:string,record:array,instituteId:int}|null
     */
    protected function identity(): ?array
    {
        if (hasRole('student') && ($student = $this->repo()->studentByUser($this->userId()))) {
            return ['audience' => 'student', 'record' => $student, 'instituteId' => (int) $student['institute_id']];
        }
        if (hasRole('employee') && ($employee = $this->repo()->employeeByUser($this->userId()))) {
            return ['audience' => 'employee', 'record' => $employee, 'instituteId' => (int) $employee['institute_id']];
        }
        if (hasRole('parent')) {
            $guardian = (new \App\Repositories\PakistanErpRepository($this->app->database()))->guardianByUser($this->userId());
            if ($guardian) return ['audience' => 'parent', 'record' => $guardian, 'instituteId' => (int) $guardian['institute_id']];
        }
        return null;
    }

    protected function forbidden(): Response
    {
        return Response::html(
            \App\Core\View::render('errors/403', ['pageTitle' => 'Access Denied'], 'layouts/error'),
            403
        );
    }

    /**
     * Renders a portal page with the maroon shell. Institute branding is read from the
     * owner record's institute, never from a request parameter.
     */
    protected function portalView(string $view, string $audience, array $data): Response
    {
        $instituteId = (int) ($data['instituteId'] ?? 0);
        $defaults = [
            'institute' => $instituteId > 0 ? $this->repo()->institute($instituteId) : [],
            'navigation' => $this->navigation($audience),
            'audience' => $audience,
            'demo' => [],
            'pageDescription' => '',
            // Same stack the admin shell loads, so the portal inherits the maroon theme.
            'styles' => [
                'css/variables.css', 'css/reset.css', 'css/base.css', 'css/layout.css',
                'css/components.css?v=6', 'css/account-settings.css?v=1', 'css/admin-pages.css?v=9',
                'css/dashboard.css', 'css/portal.css', 'css/responsive.css', 'css/print.css',
            ],
            'scripts' => [
                'vendor/lucide.min.js', 'js/dropdowns.js', 'js/sidebar.js?v=4', 'js/app.js',
            ],
        ];
        $announcementAudience=$audience==='employee'&&hasRole('teacher')?'teacher':$audience;
        $defaults['portalAnnouncements']=(new \App\Repositories\AnnouncementRepository($this->app->database()))->visible($instituteId,$announcementAudience,$this->announcementClassIds($audience));
        $defaults['studentNotifications']=[];
        if($audience==='student'&&($student=$data['student']??$this->repo()->studentByUser($this->userId()))){
            try{$defaults['studentNotifications']=(new \App\Services\StudentNotifications($this->app->database()))->unread($this->userId(),$student,(string)($defaults['institute']['currency_symbol']??'Rs')?:'Rs');}
            catch(\PDOException){/* notification table not migrated yet: the portal still renders */}
        }
        $payload = array_replace($defaults, $data);
        $payload['styles'] = array_values(array_unique(array_merge($defaults['styles'], $data['styles'] ?? [])));
        $payload['scripts'] = array_values(array_unique(array_merge($defaults['scripts'], $data['scripts'] ?? [])));
        return $this->view($view, $payload, 'layouts/portal');
    }

    /** Portal sidebar. Mirrors config/navigation.php's shape so the shared sidebar renders it. */
    protected function navigation(string $audience): array
    {
        $item = static fn (string $label, string $key, string $icon, string $route): array =>
            ['label' => $label, 'key' => $key, 'icon' => $icon, 'route' => $route, 'children' => [], 'locked' => false];

        if ($audience === 'student') {
            return [
                $item('Dashboard', 'portal-dashboard', 'layout-dashboard', '/portal/student'),
                $item('My Courses', 'portal-courses', 'graduation-cap', '/portal/student/courses'),
                $item('Announcements', 'portal-announcements', 'megaphone', '/portal/announcements'),
                $item('My Profile', 'portal-profile', 'user-round', '/portal/student/profile'),
                $item('My Attendance', 'portal-attendance', 'calendar-check', '/portal/student/attendance'),
                $item('My Fees', 'portal-fees', 'badge-dollar-sign', '/portal/student/fees'),
                $item('My Results', 'portal-results', 'graduation-cap', '/portal/student/results'),
                $item('Online Tests', 'portal-online-tests', 'clipboard-check', '/portal/student/online-tests'),
                $item('My Homework', 'portal-homework', 'book-open', '/portal/student/homework'),
                $item('Live Classes', 'portal-live-classes', 'video', '/portal/student/live-classes'),
                $item('Class Notes', 'portal-notes', 'notebook-tabs', '/portal/student/notes'),
                $item('Recorded Lectures', 'portal-recorded-lectures', 'clapperboard', '/portal/student/recorded-lectures'),
                $item('My Documents', 'portal-documents', 'file-text', '/portal/student/documents'),
                $item('Messages', 'portal-messages', 'messages-square', '/portal/messages'),
                $item('Account Settings', 'portal-account', 'settings-2', '/portal/account'),
            ];
        }

        if ($audience === 'parent') {
            return [
                $item('My Children', 'portal-dashboard', 'users-round', '/portal/parent'),
                $item('Announcements', 'portal-announcements', 'megaphone', '/portal/announcements'),
                $item('My Profile', 'portal-profile', 'user-round', '/portal/parent/profile'),
                $item('Messages', 'portal-messages', 'messages-square', '/portal/messages'),
                $item('Account Settings', 'portal-account', 'settings-2', '/portal/account'),
            ];
        }

        $employeeNav = [
            $item('Dashboard', 'portal-dashboard', 'layout-dashboard', '/portal/employee'),
            $item('Announcements', 'portal-announcements', 'megaphone', '/portal/announcements'),
            $item('My Profile', 'portal-profile', 'user-round', '/portal/employee/profile'),
            $item('My Attendance', 'portal-attendance', 'calendar-check', '/portal/employee/attendance'),
            $item('My Salary', 'portal-salary', 'badge-dollar-sign', '/portal/employee/salary'),
            $item('My Classes', 'portal-classes', 'school', '/portal/employee/classes'),
            $item('My Documents', 'portal-documents', 'file-text', '/portal/employee/documents'),
            $item('Messages', 'portal-messages', 'messages-square', '/portal/messages'),
            $item('Account Settings', 'portal-account', 'settings-2', '/portal/account'),
        ];

        // A teacher needs to *do* things, not just read them, so an assigned teacher gets the
        // authoring screens up front. Non-teaching staff only ever see the read-only list,
        // since they have no class they are allowed to publish to.
        if ($this->hasTeachingAssignments()) {
            array_splice($employeeNav, 1, 0, [
                $item('My Teaching', 'portal-teaching', 'graduation-cap', '/portal/employee/teaching'),
                $item('Mark Attendance', 'portal-teaching-attendance', 'clipboard-check', '/portal/employee/teaching/attendance'),
                $item('Assign Homework', 'portal-teaching-homework', 'book-open', '/portal/employee/teaching/homework'),
                $item('Class Notes', 'portal-teaching-notes', 'file-text', '/portal/employee/teaching/notes'),
                $item('Class Tests', 'portal-teaching-tests', 'clipboard-list', '/portal/employee/teaching/tests'),
                $item('Online Tests & Submissions', 'online-tests', 'clipboard-check', '/admin/online-tests'),
                $item('Exam Marks', 'exam-marks', 'file-check-2', '/admin/exams/marks'),
                $item('Live Classes', 'portal-teaching-live-classes', 'video', '/portal/employee/teaching/live-classes'),
                $item('Announcements', 'portal-teaching-announcements', 'megaphone', '/portal/employee/teaching/announcements'),
            ]);
        } else {
            array_splice($employeeNav, 5, 0, [
                $item('My Homework', 'portal-homework', 'book-open', '/portal/employee/homework'),
            ]);
        }

        return $employeeNav;
    }

    /** Whether the signed-in employee holds at least one active teaching assignment. */
    private function hasTeachingAssignments(): bool
    {
        return (bool) $this->app->database()->query(
            'SELECT 1 FROM teacher_assignments ta
             JOIN employee_accounts ea ON ea.employee_id = ta.employee_id
             WHERE ea.user_id = :u AND ta.status = \'active\' LIMIT 1',
            ['u' => $this->userId()]
        )->fetchColumn();
    }

    /**
     * Validated reporting window. Falls back to the calendar year to date and never lets a
     * malformed or reversed range through to a query.
     */
    protected function range(\App\Core\Request $request): array
    {
        $from = (string) $request->query('from', date('Y-01-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        if (!$this->isDate($from)) {
            $from = date('Y-01-01');
        }
        if (!$this->isDate($to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to];
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** Dismisses student pop-up notifications. */
    public function readNotifications(Request $request): Response
    {
        if (!$this->repo()->studentByUser($this->userId())) {
            return Response::json(['ok' => false], 403);
        }
        (new \App\Services\StudentNotifications($this->app->database()))->markRead($this->userId(), (array) $request->input('keys', []));
        return Response::json(['ok' => true]);
    }

    /** Classes whose class-targeted announcements this portal user should receive. */
    protected function announcementClassIds(string $audience): array
    {
        if ($audience === 'student') {
            return ($s = $this->repo()->studentByUser($this->userId())) ? [(int) $s['class_id']] : [];
        }
        if ($audience === 'parent') {
            return array_map('intval', $this->app->database()->query('SELECT DISTINCT s.class_id FROM guardian_accounts ga JOIN guardian_students gs ON gs.guardian_id=ga.guardian_id JOIN students s ON s.id=gs.student_id WHERE ga.user_id=:u', ['u' => $this->userId()])->fetchAll(\PDO::FETCH_COLUMN));
        }
        return [];
    }
}
