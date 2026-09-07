<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\{AttendanceRepository,PakistanErpRepository,SupportRepository,WorkflowRepository};
use App\Services\AttendanceService;
use App\Support\{Flash,UploadPolicy};
use DomainException;

/**
 * Teacher workspace inside the employee portal.
 *
 * Closes the "teacher authoring from employee portal" gap: until now the employee portal was
 * read-only, so a teacher could only author through the admin area. Every action here is
 * constrained by teacher_assignments via PakistanErpRepository::teacherCan(), so a teacher can
 * only touch the classes and subjects they are actually assigned to — the class and subject
 * are re-checked server-side on every write, never trusted from the form.
 */
final class TeacherWorkspaceController extends PortalController
{
    private ?PakistanErpRepository $erp = null;

    private function erp(): PakistanErpRepository
    {
        return $this->erp ??= new PakistanErpRepository($this->app->database());
    }

    private function workflow(): WorkflowRepository
    {
        return new WorkflowRepository($this->app->database());
    }

    /**
     * Resolves the signed-in teacher, or null when the user is not an employee of this
     * institute. Returns [employeeId, instituteId, employee].
     */
    private function teacher(): ?array
    {
        $employee = $this->repo()->employeeByUser($this->userId());
        if ($employee === null) {
            return null;
        }
        return [(int) $employee['id'], (int) $employee['institute_id'], $employee];
    }

    /** Distinct classes this teacher may act on, with the subjects allowed in each. */
    private function scope(int $employeeId, int $instituteId): array
    {
        $rows = $this->app->database()->query(
            'SELECT ta.class_id, ta.subject_id, ta.assignment_type, c.name AS class_name, s.name AS subject_name
             FROM teacher_assignments ta
             JOIN classes c ON c.id = ta.class_id
             LEFT JOIN subjects s ON s.id = ta.subject_id
             WHERE ta.employee_id = :e AND ta.institute_id = :i AND ta.status = \'active\'
             ORDER BY c.name, s.name',
            ['e' => $employeeId, 'i' => $instituteId]
        )->fetchAll();

        $classes = [];
        foreach ($rows as $row) {
            $classId = (int) $row['class_id'];
            $classes[$classId] ??= [
                'id' => $classId,
                'name' => $row['class_name'],
                'whole_class' => false,
                'subjects' => [],
            ];
            if (in_array($row['assignment_type'], ['whole_class', 'homeroom'], true)) {
                $classes[$classId]['whole_class'] = true;
            }
            if ($row['subject_id'] !== null) {
                $classes[$classId]['subjects'][(int) $row['subject_id']] = $row['subject_name'];
            }
        }
        return $classes;
    }

    /**
     * Subjects the teacher may author against in a class. A whole-class or homeroom
     * assignment grants every subject taught in that class.
     */
    private function subjectsFor(int $employeeId, int $instituteId, int $classId): array
    {
        $scope = $this->scope($employeeId, $instituteId);
        if (!isset($scope[$classId])) {
            return [];
        }
        if ($scope[$classId]['whole_class']) {
            $all = [];
            foreach ((new SupportRepository($this->app->database()))->subjects($instituteId, $classId) as $subject) {
                $all[(int) $subject['id']] = $subject['name'];
            }
            return $all;
        }
        return $scope[$classId]['subjects'];
    }

    /** Guard for subject-level writes (homework, notes, tests): assignment must exist now. */
    private function assertScope(int $employeeId, int $instituteId, int $classId, ?int $subjectId = null): void
    {
        if (!$this->erp()->teacherCan($employeeId, $instituteId, $classId, $subjectId)) {
            throw new DomainException('You are not assigned to that class or subject.');
        }
    }

    /**
     * Guard for class-level actions such as daily attendance.
     *
     * Deliberately broader than teacherCan(): that method answers "may this teacher teach this
     * subject", and returns false for a subject teacher when no subject is supplied. Daily
     * attendance is a class-scope action, so any active assignment to the class qualifies —
     * otherwise a subject teacher could never mark the register at all.
     */
    private function assertClassScope(int $employeeId, int $instituteId, int $classId): void
    {
        $allowed = $this->app->database()->query(
            'SELECT 1 FROM teacher_assignments
             WHERE employee_id = :e AND institute_id = :i AND class_id = :c AND status = \'active\' LIMIT 1',
            ['e' => $employeeId, 'i' => $instituteId, 'c' => $classId]
        )->fetchColumn();
        if (!$allowed) {
            throw new DomainException('You are not assigned to that class.');
        }
    }

    /* ------------------------------------------------------------------ overview */

    public function index(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId, $employee] = $teacher;
        return $this->portalView('portal/teacher/index', 'employee', [
            'pageTitle' => 'My Teaching',
            'activeMenu' => 'portal-teaching',
            'instituteId' => $instituteId,
            'employee' => $employee,
            'scope' => $this->scope($employeeId, $instituteId),
        ]);
    }

    /* ---------------------------------------------------------------- attendance */

    public function attendance(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId, $employee] = $teacher;
        $scope = $this->scope($employeeId, $instituteId);
        $classId = (int) $request->query('class', 0);
        $date = (string) $request->query('date', date('Y-m-d'));
        if (!$this->isDate($date)) {
            $date = date('Y-m-d');
        }
        $rows = [];
        if ($classId > 0 && isset($scope[$classId])) {
            $rows = (new AttendanceRepository($this->app->database()))->students($classId, $date, $instituteId);
        } elseif ($classId > 0) {
            Flash::add('error', 'You are not assigned to that class.');
            $classId = 0;
        }
        return $this->portalView('portal/teacher/attendance', 'employee', [
            'pageTitle' => 'Mark Attendance',
            'activeMenu' => 'portal-teaching',
            'instituteId' => $instituteId,
            'employee' => $employee,
            'scope' => $scope,
            'selectedClass' => $classId,
            'date' => $date,
            'rows' => $rows,
        ]);
    }

    public function saveAttendance(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId] = $teacher;
        $classId = (int) $request->input('class');
        $date = (string) $request->input('date');
        $back = url('/portal/employee/teaching/attendance?class=' . $classId . '&date=' . urlencode($date));
        try {
            $this->assertClassScope($employeeId, $instituteId, $classId);
            if (!$this->isDate($date)) {
                throw new DomainException('Enter a valid attendance date.');
            }
            $repository = new AttendanceRepository($this->app->database());
            $existing = $repository->students($classId, $date, $instituteId);
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $existing);
            if ($ids === []) {
                throw new DomainException('This class has no active students.');
            }
            (new AttendanceService($repository))->saveStudents(
                $date,
                $ids,
                (array) $request->input('status', []),
                (array) $request->input('remarks', []),
                $this->userId()
            );
            Flash::add('success', 'Attendance saved for ' . count($ids) . ' student(s).');
        } catch (DomainException | \InvalidArgumentException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect($back);
    }

    /* ------------------------------------------------------------------ homework */

    public function homework(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId, $employee] = $teacher;
        $classId = (int) $request->query('class', 0);
        return $this->portalView('portal/teacher/homework', 'employee', [
            'pageTitle' => 'My Homework',
            'activeMenu' => 'portal-teaching',
            'instituteId' => $instituteId,
            'employee' => $employee,
            'scope' => $this->scope($employeeId, $instituteId),
            'selectedClass' => $classId,
            'subjects' => $classId > 0 ? $this->subjectsFor($employeeId, $instituteId, $classId) : [],
            'rows' => $this->workflow()->homework($instituteId, null, $classId, $employeeId),
        ]);
    }

    public function storeHomework(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId] = $teacher;
        $classId = (int) $request->input('class_id');
        $subjectId = (int) $request->input('subject_id');
        try {
            $this->assertScope($employeeId, $instituteId, $classId, $subjectId);
            $attachment = $this->upload($request, 'attachment', 'homework');
            // teacher_id is forced to the signed-in teacher: never taken from the form.
            $this->workflow()->createHomework($instituteId, [
                'date' => (string) $request->input('date'),
                'teacher_id' => $employeeId,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'text' => (string) $request->input('homework'),
                'attachment' => $attachment,
            ], $this->userId());
            Flash::add('success', 'Homework published.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/portal/employee/teaching/homework?class=' . $classId));
    }

    public function deleteHomework(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId] = $teacher;
        $id = (int) $request->route('id');
        $owned = $this->app->database()->query(
            'SELECT 1 FROM homework h JOIN classes c ON c.id = h.class_id
             WHERE h.id = :id AND h.teacher_id = :t AND c.institute_id = :i',
            ['id' => $id, 't' => $employeeId, 'i' => $instituteId]
        )->fetchColumn();
        if (!$owned) {
            Flash::add('error', 'You can only delete homework you assigned.');
        } else {
            $this->workflow()->deleteHomework($instituteId, $id);
            Flash::add('success', 'Homework deleted.');
        }
        return Response::redirect(url('/portal/employee/teaching/homework'));
    }

    /* --------------------------------------------------------------------- notes */

    public function notes(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId, $employee] = $teacher;
        $classId = (int) $request->query('class', 0);
        $mine = array_values(array_filter(
            $this->erp()->notes($instituteId, $classId),
            static fn (array $note): bool => (int) ($note['teacher_id'] ?? 0) === $employeeId
        ));
        return $this->portalView('portal/teacher/notes', 'employee', [
            'pageTitle' => 'My Class Notes',
            'activeMenu' => 'portal-teaching',
            'instituteId' => $instituteId,
            'employee' => $employee,
            'scope' => $this->scope($employeeId, $instituteId),
            'selectedClass' => $classId,
            'subjects' => $classId > 0 ? $this->subjectsFor($employeeId, $instituteId, $classId) : [],
            'rows' => $mine,
        ]);
    }

    public function storeNote(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId] = $teacher;
        $classId = (int) $request->input('class_id');
        $subjectId = (int) $request->input('subject_id');
        try {
            $this->assertScope($employeeId, $instituteId, $classId, $subjectId);
            $title = trim((string) $request->input('title'));
            if ($title === '' || mb_strlen($title) > 150) {
                throw new DomainException('Enter a note title of up to 150 characters.');
            }
            $publish = (string) $request->input('publish_date');
            if (!$this->isDate($publish)) {
                $publish = date('Y-m-d');
            }
            $status = $request->input('status') === 'draft' ? 'draft' : 'published';
            $this->erp()->saveNote($instituteId, [
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $employeeId,
                'chapter' => trim((string) $request->input('chapter')) ?: null,
                'title' => $title,
                'description' => trim((string) $request->input('description')) ?: null,
                'attachment_path' => $this->upload($request, 'attachment', 'notes'),
                'publish_date' => $publish,
                'status' => $status,
                'created_by' => $this->userId(),
            ]);
            Flash::add('success', 'Note saved.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/portal/employee/teaching/notes?class=' . $classId));
    }

    /* --------------------------------------------------------------------- tests */

    public function tests(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId, $employee] = $teacher;
        $scope = $this->scope($employeeId, $instituteId);
        $classId = (int) $request->query('class', 0);
        // Only tests for classes this teacher is assigned to.
        $tests = array_values(array_filter(
            $this->workflow()->classTests($instituteId, $classId),
            fn (array $test): bool => isset($scope[(int) $test['class_id']])
        ));
        return $this->portalView('portal/teacher/tests', 'employee', [
            'pageTitle' => 'My Class Tests',
            'activeMenu' => 'portal-teaching',
            'instituteId' => $instituteId,
            'employee' => $employee,
            'scope' => $scope,
            'selectedClass' => $classId,
            'subjects' => $classId > 0 ? $this->subjectsFor($employeeId, $instituteId, $classId) : [],
            'tests' => $tests,
        ]);
    }

    public function storeTest(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId] = $teacher;
        $classId = (int) $request->input('class_id');
        $subjectId = (int) $request->input('subject_id');
        try {
            $this->assertScope($employeeId, $instituteId, $classId, $subjectId);
            $this->workflow()->createClassTest(
                $instituteId,
                $classId,
                $subjectId,
                (string) $request->input('date'),
                (string) $request->input('title'),
                (float) $request->input('total_marks'),
                $this->userId()
            );
            Flash::add('success', 'Class test created. Enter marks when ready.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/portal/employee/teaching/tests?class=' . $classId));
    }

    public function testMarks(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId, $employee] = $teacher;
        $test = $this->workflow()->classTest($instituteId, (int) $request->route('id'));
        if ($test === null || !$this->erp()->teacherCan($employeeId, $instituteId, (int) $test['class_id'], (int) $test['subject_id'])) {
            return $this->forbidden();
        }
        return $this->portalView('portal/teacher/test-marks', 'employee', [
            'pageTitle' => 'Enter Test Marks',
            'activeMenu' => 'portal-teaching',
            'instituteId' => $instituteId,
            'employee' => $employee,
            'scope' => $this->scope($employeeId, $instituteId),
            'test' => $test,
            'sheet' => $this->workflow()->classTestMarkSheet($instituteId, (int) $test['id']),
        ]);
    }

    public function saveTestMarks(Request $request): Response
    {
        if (!$teacher = $this->teacher()) {
            return $this->forbidden();
        }
        [$employeeId, $instituteId] = $teacher;
        $id = (int) $request->route('id');
        $test = $this->workflow()->classTest($instituteId, $id);
        if ($test === null || !$this->erp()->teacherCan($employeeId, $instituteId, (int) $test['class_id'], (int) $test['subject_id'])) {
            return $this->forbidden();
        }
        try {
            $this->workflow()->saveClassTestMarks(
                $instituteId,
                $id,
                (array) $request->input('marks', []),
                $this->userId(),
                (array) $request->input('absent', []),
                (array) $request->input('remarks', [])
            );
            Flash::add('success', 'Test marks saved.');
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/portal/employee/teaching/tests/' . $id . '/marks'));
    }

    /* ------------------------------------------------------------------- helpers */

    /** Shared upload handling; rejects anything outside the policy allowlist. */
    private function upload(Request $request, string $field, string $folder): ?string
    {
        $file = $request->file($field);
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $policy = new UploadPolicy(
            ['application/pdf', 'image/jpeg', 'image/png'],
            ['pdf', 'jpg', 'jpeg', 'png'],
            5242880
        );
        if (!$policy->validate($file)) {
            throw new DomainException('Attachment must be a PDF, JPG or PNG no larger than 5 MB.');
        }
        $name = $policy->safeFilename(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $dir = BASE_PATH . '/public/uploads/' . $folder;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new DomainException('Attachment upload failed.');
        }
        return 'uploads/' . $folder . '/' . $name;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
