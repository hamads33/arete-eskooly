<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\{DashboardRepository,SettingsRepository,PakistanErpRepository};
use App\Support\Flash;

final class DashboardController extends AdminPageController
{
    /**
     * Which permission releases each panel of figures.
     *
     * dashboard.view only decides whether the page opens; every number on it is released by the
     * permission of the module it comes from. Without this split an administrator had to hand
     * over the whole financial overview just to let somebody run live classes.
     */
    private const PANELS = [
        'students'   => 'students.view',
        'employees'  => 'employees.view',
        'finance'    => 'accounts.view',
        'fees'       => 'fees.view',
        'attendance' => 'attendance.view',
        'classes'    => 'classes.view',
    ];

    public function index(Request $request): Response
    {
        // Teachers see only their own classes, so the institute dashboard is not theirs.
        if (hasRole('teacher') && !can('*') && !can('dashboard.view')) {
            return Response::redirect(url('/portal/employee'));
        }
        $institute = (new SettingsRepository($this->app->database()))->institute();
        $data = (new DashboardRepository($this->app->database()))->summary((int) $institute['id']);
        $currency = trim((string)($institute['currency_symbol'] ?? $institute['currency'] ?? 'PKR')) ?: 'PKR';

        $show = [];
        foreach (self::PANELS as $panel => $permission) {
            $show[$panel] = can($permission);
        }

        // Figures are removed from the payload rather than merely hidden in the template, so a
        // restricted user cannot read them out of the chart JSON embedded in the page.
        $data = $this->redact($data, $show);

        $students = [];
        foreach ($data['admissions'] as $row) {
            $name = (string) $row['student_name'];
            $students[] = [
                'name' => $name,
                'registration' => $row['registration_no'],
                'className' => $row['class_name'],
                'initials' => strtoupper(substr(preg_replace('/[^A-Za-z ]/', '', $name), 0, 1) . substr(strrchr(' ' . $name, ' '), 1, 1)),
            ];
        }

        $feeTotal = (float) $data['fee']['total'];

        return $this->renderPage('dashboard', [
            'show' => $show,
            'dashboardData' => [
                'totalStudents' => $data['students'],
                'totalEmployees' => $data['employees'],
                'revenue' => $data['revenue'],
                'profit' => $data['revenue'] - $data['costs'],
                'months' => $show['finance'] ? $data['months'] : [],
                'income' => $data['incomes'],
                'expense' => $data['expenses'],
                'feeCollected' => $feeTotal > 0 ? min(100, round(((float) $data['fee']['paid'] / $feeTotal) * 100)) : 0,
                'currencySymbol' => $currency,
                'classLabels' => array_column($data['classes'], 'name'),
                'classStrength' => array_map('intval', array_column($data['classes'], 'strength')),
                'students' => $students,
            ],
            'dashboardSummary' => $data,
            'institute' => $institute,
        ]);
    }

    /** Blanks every metric whose owning panel the user may not see. */
    private function redact(array $data, array $show): array
    {
        if (!$show['students']) {
            $data['students'] = 0;
            $data['admissions'] = [];
            $data['absentStudents'] = [];
        }
        if (!$show['employees']) {
            $data['employees'] = 0;
            $data['staffPresent'] = 0;
            $data['staffMarked'] = 0;
        }
        if (!$show['finance']) {
            $data['revenue'] = 0;
            $data['costs'] = 0;
            $data['incomes'] = [];
            $data['expenses'] = [];
        }
        if (!$show['fees']) {
            $data['fee'] = ['total' => 0, 'paid' => 0, 'remaining' => 0];
        }
        if (!$show['attendance']) {
            $data['present'] = 0;
            $data['absent'] = 0;
            $data['studentsMarked'] = 0;
            $data['absentStudents'] = [];
            $data['staffPresent'] = 0;
            $data['staffMarked'] = 0;
        }
        if (!$show['classes']) {
            $data['classes'] = [];
        }
        return $data;
    }

    public function rebuild(Request $request): Response
    {
        $institute = (new SettingsRepository($this->app->database()))->institute();
        // Metrics are calculated directly from institute-scoped business records. Re-running the
        // summary is therefore a safe rebuild and never deletes students, fees or attendance.
        (new DashboardRepository($this->app->database()))->summary((int) $institute['id']);
        (new PakistanErpRepository($this->app->database()))->audit((int) $institute['id'], (int) user()['id'], 'dashboard.statistics_rebuilt', 'dashboard', null);
        Flash::add('success', 'Dashboard statistics rebuilt from current records.');
        return Response::redirect(url('/admin/dashboard'));
    }
}
