<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\ResolvesLiveClasses;
use App\Core\Request;
use App\Core\Response;
use App\Support\Flash;

/**
 * Read-only self-service portal for students. Every query is scoped to the student record
 * linked to the signed-in user; no route accepts a student identifier.
 */
final class StudentPortalController extends PortalController
{
    use ResolvesLiveClasses;

    public function dashboard(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $studentId = (int) $student['id'];
        $instituteId = (int) $student['institute_id'];
        $attendance = $this->repo()->studentAttendanceSummary($studentId, date('Y-01-01'), date('Y-m-d'));
        $results = $this->repo()->studentExamResults($studentId, $instituteId);

        return $this->portalView('portal/student/dashboard', 'student', [
            'pageTitle' => 'My Dashboard',
            'activeMenu' => 'portal-dashboard',
            'instituteId' => $instituteId,
            'student' => $student,
            'attendance' => $attendance,
            'fees' => $this->repo()->studentFeeTotals($studentId),
            'invoices' => array_slice($this->repo()->studentInvoices($studentId, 5), 0, 5),
            'homework' => array_slice($this->repo()->studentHomework((int) $student['class_id'], $instituteId, 5), 0, 5),
            'latestExam' => $this->latestExam($results),
            // Upcoming Live Classes tile (§22). Only classes that have not finished, so the
            // dashboard shows what the student can act on rather than a history list.
            'liveClasses' => $this->upcomingLiveClasses($studentId, $instituteId, 4),
            'joinWindowMinutes' => $this->joinWindowMinutes(),
            'styles' => ['css/live-classes.css?v=1'],
        ]);
    }

    public function profile(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        return $this->portalView('portal/student/profile', 'student', [
            'pageTitle' => 'My Profile',
            'activeMenu' => 'portal-profile',
            'instituteId' => (int) $student['institute_id'],
            'student' => $student,
        ]);
    }

    public function attendance(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        [$from, $to] = $this->range($request);
        $studentId = (int) $student['id'];
        return $this->portalView('portal/student/attendance', 'student', [
            'pageTitle' => 'My Attendance',
            'activeMenu' => 'portal-attendance',
            'instituteId' => (int) $student['institute_id'],
            'student' => $student,
            'from' => $from,
            'to' => $to,
            'summary' => $this->repo()->studentAttendanceSummary($studentId, $from, $to),
            'records' => $this->repo()->studentAttendanceRecords($studentId, $from, $to),
        ]);
    }

    public function fees(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $studentId = (int) $student['id'];
        return $this->portalView('portal/student/fees', 'student', [
            'pageTitle' => 'My Fees',
            'activeMenu' => 'portal-fees',
            'instituteId' => (int) $student['institute_id'],
            'student' => $student,
            'totals' => $this->repo()->studentFeeTotals($studentId),
            'invoices' => $this->repo()->studentInvoices($studentId),
            'payments' => $this->repo()->studentPayments($studentId),
        ]);
    }

    public function invoice(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $invoice = $this->repo()->studentInvoice((int) $student['id'], (int) $request->route('id'));
        if ($invoice === null) {
            return Response::html('Invoice not found', 404);
        }
        return $this->portalView('portal/student/invoice', 'student', [
            'pageTitle' => 'Fee Invoice ' . (string) $invoice['invoice_no'],
            'activeMenu' => 'portal-fees',
            'instituteId' => (int) $student['institute_id'],
            'student' => $student,
            'invoice' => $invoice,
            'items' => $this->repo()->invoiceItems((int) $invoice['id']),
        ]);
    }

    public function results(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $studentId = (int) $student['id'];
        $instituteId = (int) $student['institute_id'];
        $rows = $this->repo()->studentExamResults($studentId, $instituteId);

        $exams = [];
        foreach ($rows as $row) {
            $key = (int) $row['exam_id'];
            $exams[$key] ??= ['name' => $row['exam_name'], 'date' => $row['start_date'], 'subjects' => [], 'obtained' => 0.0, 'total' => 0.0];
            $exams[$key]['subjects'][] = $row;
            $exams[$key]['obtained'] += (float) $row['obtained_marks'];
            $exams[$key]['total'] += (float) $row['total_marks'];
        }
        foreach ($exams as &$exam) {
            $exam['percentage'] = $exam['total'] > 0 ? round($exam['obtained'] / $exam['total'] * 100, 1) : 0.0;
            $exam['grade'] = $this->grade($exam['percentage'], $instituteId);
        }
        unset($exam);

        return $this->portalView('portal/student/results', 'student', [
            'pageTitle' => 'My Results',
            'activeMenu' => 'portal-results',
            'instituteId' => $instituteId,
            'student' => $student,
            'exams' => $exams,
            'tests' => $this->repo()->studentClassTests($studentId, $instituteId),
            'certificates' => $this->repo()->studentCertificates($studentId, $instituteId),
        ]);
    }

    public function homework(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        return $this->portalView('portal/student/homework', 'student', [
            'pageTitle' => 'My Homework',
            'activeMenu' => 'portal-homework',
            'instituteId' => (int) $student['institute_id'],
            'student' => $student,
            'homework' => $this->repo()->studentHomework((int) $student['class_id'], (int) $student['institute_id']),
        ]);
    }

    public function notes(Request$request):Response
    {
        if(!$student=$this->repo()->studentByUser($this->userId()))return$this->forbidden();
        return $this->portalView('portal/student/notes','student',['pageTitle'=>'Class Notes','activeMenu'=>'portal-notes','instituteId'=>(int)$student['institute_id'],'student'=>$student,'notes'=>$this->repo()->studentNotes((int)$student['class_id'],(int)$student['institute_id'])]);
    }

    /** Official documents the student can generate for themselves. */
    public function documents(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $studentId = (int) $student['id'];
        $instituteId = (int) $student['institute_id'];

        // One row per exam the student actually sat, so no dead report-card links are offered.
        $exams = [];
        foreach ($this->repo()->studentExamResults($studentId, $instituteId) as $row) {
            $exams[(int) $row['exam_id']] ??= ['id' => (int) $row['exam_id'], 'name' => $row['exam_name'], 'date' => $row['start_date']];
        }

        return $this->portalView('portal/student/documents', 'student', [
            'pageTitle' => 'My Documents',
            'activeMenu' => 'portal-documents',
            'instituteId' => $instituteId,
            'student' => $student,
            'payments' => $this->repo()->studentPayments($studentId),
            'exams' => array_values($exams),
        ]);
    }

    /**
     * The student's live classes. The list comes from a single audience-matching query, so a
     * meeting the student is not in never enters this code path at all (§42).
     */
    public function liveClasses(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $instituteId = (int) $student['institute_id'];
        $service = $this->liveClassService();
        $timezone = $service->timezone($instituteId);
        $window = $this->joinWindowMinutes();
        $upcoming = $past = [];
        foreach ($this->liveClassRepository()->forStudent((int) $student['id'], $instituteId) as $meeting) {
            $card = $this->studentCard($meeting, $service, $timezone, $window);
            if (in_array($card['state']['state'], ['completed', 'cancelled'], true)) {
                $past[] = $card;
            } else {
                $upcoming[] = $card;
            }
        }
        return $this->portalView('portal/student/live-classes', 'student', [
            'pageTitle' => 'Live Classes',
            'activeMenu' => 'portal-live-classes',
            'instituteId' => $instituteId,
            'student' => $student,
            'upcoming' => $upcoming,
            'past' => array_slice(array_reverse($past), 0, 20),
            'timezone' => $timezone,
            'joinWindow' => $window,
            'styles' => ['css/live-classes.css?v=1'],
        ]);
    }

    /**
     * The join action. Every condition is re-checked here rather than trusted from the page that
     * rendered the button, and the Meet URL only leaves the server as a redirect — it is never
     * placed in the student's HTML (§24).
     */
    public function joinLiveClass(Request $request): Response
    {
        if (!$student = $this->repo()->studentByUser($this->userId())) {
            return $this->forbidden();
        }
        $instituteId = (int) $student['institute_id'];
        $meeting = $this->liveClassRepository()->findForStudent((int) $request->route('id'), (int) $student['id'], $instituteId);
        if ($meeting === null) {
            // Same response for "does not exist" and "not yours": an id guess learns nothing.
            Flash::add('error', 'You do not have access to this live class.');
            return Response::redirect(url('/portal/student/live-classes'));
        }
        $service = $this->liveClassService();
        $state = $service->state($meeting, $this->joinWindowMinutes());
        if ($state['state'] === 'cancelled') {
            Flash::add('error', 'This live class was cancelled.');
            return Response::redirect(url('/portal/student/live-classes'));
        }
        if ($state['state'] === 'completed') {
            Flash::add('error', 'This class has ended.');
            return Response::redirect(url('/portal/student/live-classes'));
        }
        if (!$state['joinable']) {
            Flash::add('error', 'Live class is not available yet.');
            return Response::redirect(url('/portal/student/live-classes'));
        }
        if (!$service->isSafeMeetLink($meeting['meet_link'] ?? null)) {
            Flash::add('error', 'Live class is not available yet.');
            return Response::redirect(url('/portal/student/live-classes'));
        }
        // Records the click only — this is not evidence the student entered the Meet (§15).
        $this->liveClassRepository()->recordJoinClick((int) $meeting['id'], (int) $student['id']);
        return Response::redirect((string) $meeting['meet_link']);
    }

    /** The next few live classes for the dashboard tile. */
    private function upcomingLiveClasses(int $studentId, int $instituteId, int $limit): array
    {
        $service = $this->liveClassService();
        $timezone = $service->timezone($instituteId);
        $window = $this->joinWindowMinutes();
        $cards = [];
        foreach ($this->liveClassRepository()->forStudent($studentId, $instituteId) as $meeting) {
            $card = $this->studentCard($meeting, $service, $timezone, $window);
            if (!in_array($card['state']['state'], ['completed', 'cancelled'], true)) {
                $cards[] = $card;
            }
            if (count($cards) >= $limit) {
                break;
            }
        }
        return $cards;
    }

    /** Presentation shape for one student-visible meeting; never carries the Meet URL. */
    private function studentCard(array $meeting, \App\Services\LiveClassService $service, string $timezone, int $window): array
    {
        $state = $service->state($meeting, $window);
        return [
            'id' => (int) $meeting['id'],
            'title' => (string) $meeting['title'],
            'subject' => $meeting['subject_name'],
            'teacher' => $meeting['teacher_name'],
            'class' => $meeting['class_name'],
            'local_start' => $service->toLocal((string) $meeting['start_time'], $timezone),
            'duration' => (int) ($meeting['duration_minutes'] ?? 0),
            'state' => $state,
        ];
    }

    /** Highest-percentage summary for the most recent exam, used by the dashboard tile. */
    private function latestExam(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $examId = (int) $rows[0]['exam_id'];
        $obtained = 0.0;
        $total = 0.0;
        foreach ($rows as $row) {
            if ((int) $row['exam_id'] !== $examId) {
                continue;
            }
            $obtained += (float) $row['obtained_marks'];
            $total += (float) $row['total_marks'];
        }
        return [
            'name' => $rows[0]['exam_name'],
            'percentage' => $total > 0 ? round($obtained / $total * 100, 1) : 0.0,
        ];
    }

    /** Resolves a percentage against the institute's configured grading rules. */
    private function grade(float $percentage, int $instituteId): string
    {
        foreach ($this->repo()->grades($instituteId) as $rule) {
            if ($percentage >= (float) $rule['range_from'] && $percentage <= (float) $rule['range_to']) {
                return (string) $rule['grade'];
            }
        }
        return '—';
    }
}
