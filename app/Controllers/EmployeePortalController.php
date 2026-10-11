<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Read-only self-service portal for employees. Scoped to the employee record linked to the
 * signed-in user; no route accepts an employee identifier.
 */
final class EmployeePortalController extends PortalController
{
    public function dashboard(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        $employeeId = (int) $employee['id'];
        $instituteId = (int) $employee['institute_id'];

        return $this->portalView('portal/employee/dashboard', 'employee', [
            'pageTitle' => 'My Dashboard',
            'activeMenu' => 'portal-dashboard',
            'instituteId' => $instituteId,
            'employee' => $employee,
            'attendance' => $this->repo()->employeeAttendanceSummary($employeeId, date('Y-01-01'), date('Y-m-d')),
            'salaryTotal' => $this->repo()->employeeSalaryTotal($employeeId),
            'salaries' => array_slice($this->repo()->employeeSalaryPayments($employeeId, 5), 0, 5),
            'classes' => $this->repo()->employeeClasses($instituteId, (string) $employee['name'], $employeeId),
            'homework' => array_slice($this->repo()->employeeHomework($employeeId, $instituteId, 5), 0, 5),
        ]);
    }

    public function profile(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        return $this->portalView('portal/employee/profile', 'employee', [
            'pageTitle' => 'My Profile',
            'activeMenu' => 'portal-profile',
            'instituteId' => (int) $employee['institute_id'],
            'employee' => $employee,
        ]);
    }

    public function attendance(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        [$from, $to] = $this->range($request);
        $employeeId = (int) $employee['id'];
        return $this->portalView('portal/employee/attendance', 'employee', [
            'pageTitle' => 'My Attendance',
            'activeMenu' => 'portal-attendance',
            'instituteId' => (int) $employee['institute_id'],
            'employee' => $employee,
            'from' => $from,
            'to' => $to,
            'summary' => $this->repo()->employeeAttendanceSummary($employeeId, $from, $to),
            'records' => $this->repo()->employeeAttendanceRecords($employeeId, $from, $to),
        ]);
    }

    public function salary(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        $employeeId = (int) $employee['id'];
        return $this->portalView('portal/employee/salary', 'employee', [
            'pageTitle' => 'My Salary',
            'activeMenu' => 'portal-salary',
            'instituteId' => (int) $employee['institute_id'],
            'employee' => $employee,
            'payments' => $this->repo()->employeeSalaryPayments($employeeId),
            'total' => $this->repo()->employeeSalaryTotal($employeeId),
        ]);
    }

    public function classes(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        return $this->portalView('portal/employee/classes', 'employee', [
            'pageTitle' => 'My Classes',
            'activeMenu' => 'portal-classes',
            'instituteId' => (int) $employee['institute_id'],
            'employee' => $employee,
            'classes' => $this->repo()->employeeClasses((int) $employee['institute_id'], (string) $employee['name'], (int)$employee['id']),
        ]);
    }

    /** Official documents the employee can generate for themselves. */
    public function documents(Request $request): Response
    {
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        return $this->portalView('portal/employee/documents', 'employee', [
            'pageTitle' => 'My Documents',
            'activeMenu' => 'portal-documents',
            'instituteId' => (int) $employee['institute_id'],
            'employee' => $employee,
            'payments' => $this->repo()->employeeSalaryPayments((int) $employee['id']),
        ]);
    }

    public function homework(Request $request): Response
    {
        if (can('homework.view')) {
            return Response::redirect(url('/admin/homework'));
        }
        if (!$employee = $this->repo()->employeeByUser($this->userId())) {
            return $this->forbidden();
        }
        return $this->portalView('portal/employee/homework', 'employee', [
            'pageTitle' => 'My Homework',
            'activeMenu' => 'portal-homework',
            'instituteId' => (int) $employee['institute_id'],
            'employee' => $employee,
            'homework' => $this->repo()->employeeHomework((int) $employee['id'], (int) $employee['institute_id']),
        ]);
    }
}
