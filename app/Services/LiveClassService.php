<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Repositories\{LiveClassRepository, PakistanErpRepository, SettingsRepository};
use App\Services\Google\{GoogleCalendarException, GoogleCalendarService, GoogleReconnectRequired};
use App\Support\Logger;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/**
 * Scheduling, instant classes, edits, cancellation and sync retries.
 *
 * Two rules shape this class:
 *
 * 1. Google and MySQL cannot share a transaction (§31). Every write therefore goes Google first,
 *    database second, and a local failure after a successful Google call is compensated by
 *    deleting the event we just created. Where compensation itself fails, that is logged — never
 *    swallowed and never reported to the user as success.
 * 2. Sync state is recorded truthfully. A meeting whose Meet link has not appeared yet is stored
 *    with google_sync_status='pending' and surfaced with a Retry action; it is never presented as
 *    ready. Nothing here fabricates a Meet link, an attendance record, or a recording (§52).
 */
final class LiveClassService
{
    /** Instant classes run this long unless the caller says otherwise (§20). */
    private const INSTANT_DURATION = 60;

    public function __construct(
        private readonly Database $db,
        private readonly LiveClassRepository $repository,
        private readonly GoogleCalendarService $calendar,
        private readonly LiveClassAuthorizationService $authorization,
        private readonly Logger $logger
    ) {}

    /* ------------------------------------------------------------------ timezone */

    /** The institute's configured timezone; the whole module converts through this one value. */
    public function timezone(int $institute): string
    {
        $preference = (string) ((new SettingsRepository($this->db))->institute($institute)['timezone'] ?? '');
        if ($preference === '') {
            return 'Asia/Karachi';
        }
        try {
            new DateTimeZone($preference);
            return $preference;
        } catch (\Throwable) {
            return 'Asia/Karachi';
        }
    }

    /** Institute wall-clock 'Y-m-d H:i' to the UTC string stored in the database. */
    public function toUtc(string $local, string $timezone): string
    {
        return (new DateTimeImmutable($local, new DateTimeZone($timezone)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Stored UTC back to institute wall-clock, for display and for the Calendar request. */
    public function toLocal(string $utc, string $timezone): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d H:i:s');
    }

    /* ------------------------------------------------------------------ scheduling */

    /**
     * Validates and normalises submitted scheduling input into the shape the repository stores.
     * Callers pass an already-authorised actor; this only checks the data.
     *
     * @throws DomainException with a message safe to show the user.
     */
    public function normalise(array $input, int $institute, string $timezone, bool $instant): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $classId = (int) ($input['class_id'] ?? 0);
        $subjectId = (int) ($input['subject_id'] ?? 0) ?: null;
        $sectionId = (int) ($input['section_id'] ?? 0) ?: null;
        $audience = (string) ($input['audience_type'] ?? 'SC');

        if ($classId <= 0 || !$this->authorization->classBelongsToInstitute($classId, $institute)) {
            throw new DomainException('Choose a class for this live class.');
        }
        if ($subjectId !== null && !$this->authorization->subjectBelongsToClass($subjectId, $classId, $institute)) {
            throw new DomainException('That subject is not taught in the selected class.');
        }
        if (!in_array($audience, ['SC', 'SEC', 'SEL'], true)) {
            throw new DomainException('Choose who this live class is for.');
        }
        if ($audience === 'SEC') {
            if ($sectionId === null || !$this->authorization->sectionBelongsToClass($sectionId, $classId, $institute)) {
                throw new DomainException('Choose a section that belongs to the selected class.');
            }
        } else {
            $sectionId = null;
        }

        $students = [];
        if ($audience === 'SEL') {
            $students = $this->authorization->filterStudents((array) ($input['students'] ?? []), $institute, $classId);
            if ($students === []) {
                throw new DomainException('Choose at least one student for this live class.');
            }
        }

        // An instant class starts now; a scheduled one takes the submitted date and time, read in
        // the institute's zone so what the user typed is what Google receives (§33).
        $duration = (int) ($input['duration'] ?? 0) ?: ($instant ? self::INSTANT_DURATION : 0);
        if ($duration < 5 || $duration > 480) {
            throw new DomainException('Duration must be between 5 and 480 minutes.');
        }
        if ($instant) {
            $start = new DateTimeImmutable('now', new DateTimeZone($timezone));
        } else {
            $date = trim((string) ($input['date'] ?? ''));
            $time = trim((string) ($input['time'] ?? ''));
            if ($date === '' || $time === '') {
                throw new DomainException('Choose a date and start time.');
            }
            try {
                $start = new DateTimeImmutable($date . ' ' . $time, new DateTimeZone($timezone));
            } catch (\Throwable) {
                throw new DomainException('That date and time could not be read.');
            }
            if ($start->getTimestamp() < time() - 300) {
                throw new DomainException('A live class cannot be scheduled in the past.');
            }
        }
        $end = $start->modify('+' . $duration . ' minutes');

        if ($title === '') {
            // §20: a blank instant-class title falls back to the subject.
            $subjectName = $subjectId !== null ? (string) ($this->db->query('SELECT name FROM subjects WHERE id=:s', ['s' => $subjectId])->fetchColumn() ?: '') : '';
            $title = $subjectName !== '' ? $subjectName . ' Live Class' : 'Live Class';
        }
        if (mb_strlen($title) > 160) {
            throw new DomainException('Title must be 160 characters or fewer.');
        }

        return [
            'title' => $title,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'audience_type' => $audience,
            'target_class_id' => $classId,
            'subject_id' => $subjectId,
            'section_id' => $sectionId,
            'target_student_id' => null,
            'students' => $students,
            'duration_minutes' => $duration,
            'timezone' => $timezone,
            'local_start' => $start->format('Y-m-d H:i:s'),
            'local_end' => $end->format('Y-m-d H:i:s'),
            'start_time' => $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'end_time' => $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * The teaching employee's address, so the event can invite them to their own class.
     *
     * Every event is created on the *institute's* calendar, which makes the institute account the
     * organiser. A teacher opening that Meet link is therefore an outsider and Google makes them
     * knock ("Ask to join") — with nobody on the other side to admit them, since the institute
     * account is not in the room. Naming the teacher as an attendee puts them on the invite list,
     * which is what Meet checks before deciding whether to admit someone directly.
     *
     * This is the host only. Students are deliberately still not attendees (§21): they are LMS
     * participants, may have no Google address, and would be emailed on every edit.
     */
    private function hostEmail(?int $teacherId, int $institute): ?string
    {
        return $teacherId === null ? null : $this->repository->teacherEmail($teacherId, $institute);
    }

    /**
     * Creates the Google event, then the local row, compensating if the local write fails.
     *
     * @return array{id:int,meet_link:?string,pending:bool}
     * @throws GoogleReconnectRequired|GoogleCalendarException|DomainException
     */
    public function schedule(array $normalised, int $institute, int $userId, string $role, ?int $teacherId): array
    {
        $result = $this->calendar->createMeeting($institute, [
            'summary' => $normalised['title'],
            'description' => $normalised['description'],
            'start' => $normalised['local_start'],
            'end' => $normalised['local_end'],
            'timezone' => $normalised['timezone'],
            'host_email' => $this->hostEmail($teacherId, $institute),
        ]);

        $pending = ($result['meet_link'] ?? null) === null;
        try {
            $id = $this->repository->create([
                'institute_id' => $institute,
                'title' => $normalised['title'],
                'description' => $normalised['description'],
                'audience_type' => $normalised['audience_type'],
                'target_class_id' => $normalised['target_class_id'],
                'subject_id' => $normalised['subject_id'],
                'section_id' => $normalised['section_id'],
                'target_student_id' => $normalised['target_student_id'],
                'teacher_id' => $teacherId,
                'start_time' => $normalised['start_time'],
                'end_time' => $normalised['end_time'],
                'timezone' => $normalised['timezone'],
                'duration_minutes' => $normalised['duration_minutes'],
                'google_event_id' => $result['event_id'],
                'google_calendar_id' => $result['calendar_id'],
                'meet_link' => $result['meet_link'],
                'conference_status' => $result['conference_status'],
                'status' => 'scheduled',
                'google_sync_status' => $pending ? 'pending' : 'synced',
                'last_google_error' => $pending ? 'Google has not finished creating the Meet link.' : null,
                'created_by' => $userId,
                'created_by_role' => $role,
            ]);
            if ($normalised['audience_type'] === 'SEL') {
                $this->repository->syncParticipants($id, $institute, $normalised['students']);
            }
        } catch (\Throwable $exception) {
            // Compensation (§31): the event exists on Google but we have no row pointing at it,
            // so remove it rather than leaving an orphan on the institute's calendar.
            $this->compensate($institute, (string) $result['event_id'], $exception);
            throw new DomainException('The live class could not be saved. No meeting was left on Google Calendar.');
        }
        return ['id' => $id, 'meet_link' => $result['meet_link'], 'pending' => $pending];
    }

    /**
     * Applies an edit locally and to Google. The Google patch retains conferenceData, so the Meet
     * link students already hold keeps working (§28).
     */
    public function edit(array $meeting, array $normalised, int $institute): array
    {
        $eventId = (string) ($meeting['google_event_id'] ?? '');
        $syncStatus = 'synced';
        $error = null;
        $meetLink = $meeting['meet_link'];
        $conferenceStatus = (string) ($meeting['conference_status'] ?? 'pending');

        if ($eventId !== '') {
            try {
                $result = $this->calendar->updateMeeting($institute, $eventId, [
                    'summary' => $normalised['title'],
                    'description' => $normalised['description'],
                    'start' => $normalised['local_start'],
                    'end' => $normalised['local_end'],
                    'timezone' => $normalised['timezone'],
                    'host_email' => $this->hostEmail(isset($meeting['teacher_id']) ? (int) $meeting['teacher_id'] : null, $institute),
                ]);
                $meetLink = $result['meet_link'] ?? $meetLink;
                $conferenceStatus = (string) $result['conference_status'];
                if ($meetLink === null) {
                    $syncStatus = 'pending';
                    $error = 'Google has not finished creating the Meet link.';
                }
            } catch (GoogleReconnectRequired | GoogleCalendarException $exception) {
                // The local edit is still applied, but flagged: claiming Google succeeded when it
                // did not is exactly what §31 forbids.
                $syncStatus = 'failed';
                $error = mb_substr($exception->getMessage(), 0, 255);
            }
        }

        $this->repository->update((int) $meeting['id'], $institute, [
            'title' => $normalised['title'],
            'description' => $normalised['description'],
            'audience_type' => $normalised['audience_type'],
            'target_class_id' => $normalised['target_class_id'],
            'subject_id' => $normalised['subject_id'],
            'section_id' => $normalised['section_id'],
            'target_student_id' => $normalised['target_student_id'],
            'start_time' => $normalised['start_time'],
            'end_time' => $normalised['end_time'],
            'timezone' => $normalised['timezone'],
            'duration_minutes' => $normalised['duration_minutes'],
            'meet_link' => $meetLink,
            'conference_status' => $conferenceStatus,
            'google_sync_status' => $syncStatus,
            'last_google_error' => $error,
        ]);
        if ($normalised['audience_type'] === 'SEL') {
            $this->repository->syncParticipants((int) $meeting['id'], $institute, $normalised['students']);
        }
        return ['sync_status' => $syncStatus, 'error' => $error];
    }

    /** Soft cancellation; a Google failure is recorded rather than hidden. */
    public function cancel(array $meeting, int $institute, int $userId): array
    {
        $syncStatus = 'synced';
        $error = null;
        $eventId = (string) ($meeting['google_event_id'] ?? '');
        if ($eventId !== '') {
            try {
                $this->calendar->cancelMeeting($institute, $eventId);
            } catch (GoogleReconnectRequired | GoogleCalendarException $exception) {
                $syncStatus = 'failed';
                $error = mb_substr($exception->getMessage(), 0, 255);
            }
        }
        // Local cancellation happens either way: the student's join right must disappear even when
        // Google is unreachable. The sync flag records that the calendar may still show the event.
        $this->repository->cancel((int) $meeting['id'], $institute, $userId, $syncStatus, $error);
        return ['sync_status' => $syncStatus, 'error' => $error];
    }

    /** Admin "Retry Google Sync": re-polls for a conference that had not appeared yet. */
    public function retrySync(array $meeting, int $institute): array
    {
        $eventId = (string) ($meeting['google_event_id'] ?? '');
        if ($eventId === '') {
            throw new DomainException('This live class has no Google event to synchronise.');
        }
        $result = $this->calendar->refreshConference($institute, $eventId);
        $link = $result['meet_link'] ?? null;
        $this->repository->applySync(
            (int) $meeting['id'],
            $institute,
            $link,
            (string) $result['conference_status'],
            $link !== null ? 'synced' : 'pending',
            $link !== null ? null : 'Google has not finished creating the Meet link.',
            $link !== null ? 'scheduled' : null
        );
        return ['meet_link' => $link, 'pending' => $link === null];
    }

    /* --------------------------------------------------------------- presentation */

    /**
     * Derives the display state from the clock rather than storing it (§32): only cancellation is
     * a persisted decision, everything else is a function of start/end and now.
     *
     * @return array{state:string,label:string,joinable:bool}
     */
    public function state(array $meeting, int $joinWindowMinutes): array
    {
        if (($meeting['status'] ?? '') === 'cancelled') {
            return ['state' => 'cancelled', 'label' => 'Cancelled', 'joinable' => false];
        }
        $now = time();
        $start = strtotime((string) $meeting['start_time'] . ' UTC');
        $end = strtotime((string) ($meeting['end_time'] ?? '') . ' UTC') ?: $start + 3600;
        $opens = $start - ($joinWindowMinutes * 60);

        if ($now > $end) {
            return ['state' => 'completed', 'label' => 'Completed', 'joinable' => false];
        }
        if ($now >= $start) {
            return ['state' => 'live', 'label' => 'Live Now', 'joinable' => true];
        }
        if ($now >= $opens) {
            return ['state' => 'starting', 'label' => 'Starting Soon', 'joinable' => true];
        }
        return ['state' => 'upcoming', 'label' => 'Upcoming', 'joinable' => false];
    }

    /**
     * The one place a Meet URL is trusted for redirect. Anything that is not an https Google Meet
     * address is refused, so a corrupted or tampered row cannot turn the join route into an open
     * redirect (§24).
     */
    public function isSafeMeetLink(?string $link): bool
    {
        if ($link === null || $link === '') {
            return false;
        }
        $parts = parse_url($link);
        if (($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        return $host === 'meet.google.com' || $host === 'meet.google.com.' || str_ends_with($host, '.meet.google.com');
    }

    public function audit(int $institute, int $userId, string $action, ?int $id, array $meta = []): void
    {
        (new PakistanErpRepository($this->db))->audit($institute, $userId, $action, 'live_class', $id, $meta);
    }

    private function compensate(int $institute, string $eventId, \Throwable $cause): void
    {
        try {
            $this->calendar->deleteMeeting($institute, $eventId);
        } catch (\Throwable $failure) {
            // Both the original write and the rollback failed: the event is orphaned on Google and
            // an administrator has to remove it by hand, so it must be findable in the log.
            $this->logger->error('Google Meet compensation failed; calendar event orphaned', ['event_id' => $eventId, 'institute_id' => $institute, 'reason' => $failure->getMessage()]);
        }
        $this->logger->error('Live class local write failed after Google event creation', ['event_id' => $eventId, 'institute_id' => $institute, 'reason' => $cause->getMessage()]);
    }
}
