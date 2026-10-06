<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Controllers\Concerns\ResolvesLiveClasses;
use App\Core\{Request, Response};
use App\Services\Google\{GoogleCalendarException, GoogleReconnectRequired};
use App\Support\Flash;
use DomainException;

/**
 * Live classes inside the teacher workspace.
 *
 * The teacher never touches Google (§50): they hold no OAuth credentials and see none. Their
 * request runs a permission check, then a teacher_assignments scope check, and the institute's
 * single stored connection does the Calendar call on their behalf.
 *
 * Two identities are resolved from the session and never from the form: the employee record
 * (so teacher_id cannot be spoofed, §43) and the institute (so a meeting cannot be created in
 * another tenant). A submitted class_id is checked against teacher_assignments on every write,
 * so changing a POST value cannot widen a teacher's reach beyond what they are assigned (§19).
 */
final class TeacherLiveClassController extends PortalController
{
    use ResolvesLiveClasses;

    public function index(Request $r): Response
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->forbidden();
        }
        [$employeeId, $institute] = $teacher;
        $service = $this->liveClassService();
        $timezone = $service->timezone($institute);
        $window = $this->joinWindowMinutes();

        $upcoming = $past = [];
        foreach ($this->liveClassRepository()->forTeacher($institute, $employeeId) as $meeting) {
            $state = $service->state($meeting, $window);
            $meeting['state'] = $state;
            $meeting['local_start'] = $service->toLocal((string) $meeting['start_time'], $timezone);
            $meeting['can_manage'] = $this->liveClassAuthorization()->teacherMayManage($meeting, $employeeId);
            if (in_array($state['state'], ['completed', 'cancelled'], true)) {
                $past[] = $meeting;
            } else {
                $upcoming[] = $meeting;
            }
        }

        return $this->portalView('portal/teaching/live-classes', 'employee', [
            'pageTitle' => 'Live Classes',
            'activeMenu' => 'portal-teaching-live-classes',
            'instituteId' => $institute,
            'scope' => $this->scope($employeeId, $institute),
            'upcoming' => $upcoming,
            'past' => $past,
            'timezone' => $timezone,
            // A teacher is told whether live classes are available, never why in Google's terms.
            'available' => $this->calendar()->isConnected($institute),
            'styles' => ['css/live-classes.css?v=1'],
            'scripts' => ['js/live-classes.js?v=1'],
        ]);
    }

    public function store(Request $r): Response
    {
        return $this->persist($r, false);
    }

    public function instant(Request $r): Response
    {
        return $this->persist($r, true);
    }

    public function update(Request $r): Response
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->forbidden();
        }
        [$employeeId, $institute] = $teacher;
        $meeting = $this->liveClassRepository()->find((int) $r->route('id'), $institute);
        if ($meeting === null) {
            return Response::html('Live class not found', 404);
        }
        if (!$this->liveClassAuthorization()->teacherMayManage($meeting, $employeeId)) {
            return $this->forbidden();
        }
        if (($meeting['status'] ?? '') === 'cancelled') {
            Flash::add('error', 'A cancelled live class cannot be edited.');
            return Response::redirect(url('/portal/employee/teaching/live-classes'));
        }
        $service = $this->liveClassService();
        try {
            $normalised = $service->normalise($r->all(), $institute, $service->timezone($institute), false);
            $this->assertAssigned($employeeId, $institute, $normalised);
            $result = $service->edit($meeting, $normalised, $institute);
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect(url('/portal/employee/teaching/live-classes'));
        }
        $service->audit($institute, (int) user()['id'], 'live_class.updated', (int) $meeting['id'], ['by' => 'teacher', 'sync' => $result['sync_status']]);
        Flash::add($result['sync_status'] === 'failed' ? 'error' : 'success', $result['sync_status'] === 'failed'
            ? 'The live class was updated, but Google could not be reached. Please contact the administrator.'
            : 'Live class updated.');
        return Response::redirect(url('/portal/employee/teaching/live-classes'));
    }

    public function cancel(Request $r): Response
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->forbidden();
        }
        [$employeeId, $institute] = $teacher;
        $meeting = $this->liveClassRepository()->find((int) $r->route('id'), $institute);
        if ($meeting === null) {
            return Response::html('Live class not found', 404);
        }
        if (!$this->liveClassAuthorization()->teacherMayManage($meeting, $employeeId)) {
            return $this->forbidden();
        }
        $service = $this->liveClassService();
        $result = $service->cancel($meeting, $institute, (int) user()['id']);
        $service->audit($institute, (int) user()['id'], 'live_class.cancelled', (int) $meeting['id'], ['by' => 'teacher', 'sync' => $result['sync_status']]);
        Flash::add('success', 'Live class cancelled. Students can no longer join it.');
        return Response::redirect(url('/portal/employee/teaching/live-classes'));
    }

    private function persist(Request $r, bool $instant): Response
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->forbidden();
        }
        [$employeeId, $institute] = $teacher;
        $redirect = url('/portal/employee/teaching/live-classes');
        if (!$this->calendar()->isConnected($institute)) {
            // Deliberately non-technical: the fix is the administrator's, not the teacher's (§36).
            Flash::add('error', 'Google Live Classes are temporarily unavailable. Please contact the administrator.');
            return Response::redirect($redirect);
        }
        $service = $this->liveClassService();
        try {
            $normalised = $service->normalise($r->all(), $institute, $service->timezone($institute), $instant);
            $this->assertAssigned($employeeId, $institute, $normalised);
            $result = $service->schedule($normalised, $institute, (int) user()['id'], 'teacher', $employeeId);
        } catch (GoogleReconnectRequired | GoogleCalendarException) {
            Flash::add('error', 'Google Live Classes are temporarily unavailable. Please contact the administrator.');
            return Response::redirect($redirect);
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect($redirect);
        }
        $classCount = count($normalised['target_class_ids']);
        $service->audit($institute, (int) user()['id'], $instant ? 'live_class.instant_created' : 'live_class.created', $result['id'], ['by' => 'teacher', 'pending' => $result['pending'], 'class_count' => $classCount]);
        Flash::add($result['pending'] ? 'error' : 'success', $result['pending']
            ? 'The class was created, but its Google Meet link is still being generated. Please refresh shortly.'
            : ($instant
                ? 'Meeting ready for ' . $classCount . ' class' . ($classCount === 1 ? '' : 'es') . '. Open Google Meet from the class card.'
                : 'Live class scheduled for ' . $classCount . ' class' . ($classCount === 1 ? '' : 'es') . '.'));
        return Response::redirect($redirect);
    }

    /**
     * The rule that makes §19 true: a teacher assigned Grade 6 Mathematics cannot schedule Grade 9
     * Chemistry by editing the POST, because the class and subject are re-checked here against
     * teacher_assignments rather than being read back from the form.
     */
    private function assertAssigned(int $employeeId, int $institute, array $normalised): void
    {
        $classIds = $normalised['target_class_ids'] ?? [$normalised['target_class_id']];
        $multipleClasses = count($classIds) > 1;
        foreach ($classIds as $classId) {
            $allowed = $multipleClasses
                ? $this->liveClassAuthorization()->teacherIsAssignedToClass($employeeId, $institute, (int) $classId)
                : $this->liveClassAuthorization()->teacherMayHost($employeeId, $institute, (int) $classId, $normalised['subject_id'] !== null ? (int) $normalised['subject_id'] : null);
            if (!$allowed) {
                throw new DomainException('You are not assigned to every selected class or subject.');
            }
        }
    }

    /** @return array{0:int,1:int}|null [employeeId, instituteId] resolved from the session. */
    private function teacher(): ?array
    {
        $employee = $this->repo()->employeeByUser($this->userId());
        if ($employee === null) {
            return null;
        }
        return [(int) $employee['id'], (int) $employee['institute_id']];
    }

    /** Classes and subjects this teacher may pick from — the same source the checks use. */
    private function scope(int $employeeId, int $institute): array
    {
        $rows = $this->app->database()->query(
            'SELECT ta.class_id, ta.subject_id, ta.assignment_type, c.name AS class_name, s.name AS subject_name
             FROM teacher_assignments ta
             JOIN classes c ON c.id = ta.class_id
             LEFT JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.employee_id = :e AND ta.institute_id = :i AND ta.status = \'active\'
             ORDER BY c.name, s.name',
            ['e' => $employeeId, 'i' => $institute]
        )->fetchAll();
        $classes = [];
        foreach ($rows as $row) {
            $classId = (int) $row['class_id'];
            $classes[$classId] ??= ['id' => $classId, 'name' => $row['class_name'], 'subjects' => []];
            if ($row['subject_id'] !== null) {
                $classes[$classId]['subjects'][(int) $row['subject_id']] = $row['subject_name'];
            }
        }
        return $classes;
    }
}
