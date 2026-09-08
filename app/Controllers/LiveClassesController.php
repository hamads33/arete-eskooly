<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Controllers\Concerns\ResolvesLiveClasses;
use App\Core\{Request, Response};
use App\Services\Google\{GoogleCalendarException, GoogleReconnectRequired};
use App\Support\Flash;
use DomainException;

/**
 * Admin Live Classes: the institute-wide view of every Google Meet class, whoever created it.
 *
 * AdminAreaMiddleware has already required live-classes.view (GET) or live-classes.manage (POST)
 * from config/permissions.php before any action here runs, and CSRF is enforced by route
 * middleware, so these methods deal with intent rather than access.
 */
final class LiveClassesController extends AdminPageController
{
    use ResolvesLiveClasses;

    public function index(Request $r): Response
    {
        $institute = $this->instituteId();
        $service = $this->liveClassService();
        $timezone = $service->timezone($institute);
        $window = $this->joinWindowMinutes();
        $buckets = ['live' => [], 'upcoming' => [], 'past' => [], 'cancelled' => []];
        foreach ($this->liveClassRepository()->forInstitute($institute) as $meeting) {
            $state = $service->state($meeting, $window);
            $meeting['state'] = $state;
            $meeting['local_start'] = $service->toLocal((string) $meeting['start_time'], $timezone);
            $meeting['local_end'] = $meeting['end_time'] !== null ? $service->toLocal((string) $meeting['end_time'], $timezone) : null;
            $bucket = match ($state['state']) {
                'cancelled' => 'cancelled',
                'live', 'starting' => 'live',
                'completed' => 'past',
                default => 'upcoming',
            };
            $buckets[$bucket][] = $meeting;
        }
        return $this->render('live-classes/index', 'Live Classes', [
            'buckets' => $buckets,
            'connection' => $this->googleConnections()->safe($institute),
            'googleConfigured' => $this->googleFactory()->configured(),
            'timezone' => $timezone,
            'joinWindow' => $window,
            'classes' => $this->liveClassRepository()->classes($institute),
        ]);
    }

    public function create(Request $r): Response
    {
        $institute = $this->instituteId();
        $classId = (int) $r->query('class_id', 0);
        return $this->render('live-classes/create', 'Schedule Live Class', [
            'connection' => $this->googleConnections()->safe($institute),
            'timezone' => $this->liveClassService()->timezone($institute),
            'classes' => $this->liveClassRepository()->classes($institute),
            'sections' => $this->liveClassRepository()->sections($institute, $classId),
            'subjects' => $this->liveClassRepository()->subjects($institute, $classId),
            'students' => $classId > 0 ? $this->liveClassRepository()->studentsInClass($institute, $classId) : [],
            'selectedClass' => $classId,
            'meeting' => null,
        ]);
    }

    public function store(Request $r): Response
    {
        return $this->persist($r, false);
    }

    /** Start Instant Class (§20): same pipeline, start_time = now, default 60 minutes. */
    public function instant(Request $r): Response
    {
        return $this->persist($r, true);
    }

    public function edit(Request $r): Response
    {
        $institute = $this->instituteId();
        $meeting = $this->liveClassRepository()->find((int) $r->route('id'), $institute);
        if ($meeting === null) {
            return Response::html('Live class not found', 404);
        }
        $service = $this->liveClassService();
        $timezone = (string) ($meeting['timezone'] ?: $service->timezone($institute));
        $meeting['local_start'] = $service->toLocal((string) $meeting['start_time'], $timezone);
        $classId = (int) $meeting['target_class_id'];
        return $this->render('live-classes/create', 'Edit Live Class', [
            'connection' => $this->googleConnections()->safe($institute),
            'timezone' => $timezone,
            'classes' => $this->liveClassRepository()->classes($institute),
            'sections' => $this->liveClassRepository()->sections($institute, $classId),
            'subjects' => $this->liveClassRepository()->subjects($institute, $classId),
            'students' => $this->liveClassRepository()->studentsInClass($institute, $classId),
            'selectedClass' => $classId,
            'participants' => $this->liveClassRepository()->participantIds((int) $meeting['id']),
            'meeting' => $meeting,
        ]);
    }

    public function update(Request $r): Response
    {
        $institute = $this->instituteId();
        $meeting = $this->liveClassRepository()->find((int) $r->route('id'), $institute);
        if ($meeting === null) {
            return Response::html('Live class not found', 404);
        }
        if (($meeting['status'] ?? '') === 'cancelled') {
            Flash::add('error', 'A cancelled live class cannot be edited.');
            return Response::redirect(url('/admin/live-classes'));
        }
        $service = $this->liveClassService();
        try {
            $normalised = $service->normalise($r->all(), $institute, $service->timezone($institute), false);
            $result = $service->edit($meeting, $normalised, $institute);
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect(url('/admin/live-classes/' . (int) $meeting['id'] . '/edit'));
        }
        $service->audit($institute, (int) user()['id'], 'live_class.updated', (int) $meeting['id'], ['sync' => $result['sync_status']]);
        if ($result['sync_status'] === 'failed') {
            Flash::add('error', 'The live class was updated here, but Google Calendar could not be updated. Retry synchronisation from the list.');
        } else {
            Flash::add('success', 'Live class updated.');
        }
        return Response::redirect(url('/admin/live-classes'));
    }

    public function cancel(Request $r): Response
    {
        $institute = $this->instituteId();
        $meeting = $this->liveClassRepository()->find((int) $r->route('id'), $institute);
        if ($meeting === null) {
            return Response::html('Live class not found', 404);
        }
        $service = $this->liveClassService();
        $result = $service->cancel($meeting, $institute, (int) user()['id']);
        $service->audit($institute, (int) user()['id'], 'live_class.cancelled', (int) $meeting['id'], ['sync' => $result['sync_status']]);
        if ($result['sync_status'] === 'failed') {
            Flash::add('error', 'The live class was cancelled here and students can no longer join, but Google Calendar still shows the event.');
        } else {
            Flash::add('success', 'Live class cancelled.');
        }
        return Response::redirect(url('/admin/live-classes'));
    }

    /** Retry Google Sync: for a meeting whose Meet link had not been generated yet (§17). */
    public function sync(Request $r): Response
    {
        $institute = $this->instituteId();
        $meeting = $this->liveClassRepository()->find((int) $r->route('id'), $institute);
        if ($meeting === null) {
            return Response::html('Live class not found', 404);
        }
        try {
            $result = $this->liveClassService()->retrySync($meeting, $institute);
            $this->liveClassService()->audit($institute, (int) user()['id'], 'live_class.sync_retried', (int) $meeting['id'], ['pending' => $result['pending']]);
            Flash::add($result['pending'] ? 'error' : 'success', $result['pending']
                ? 'Google has still not finished creating the Meet link. Try again shortly.'
                : 'Google Meet link synchronised.');
        } catch (GoogleReconnectRequired) {
            Flash::add('error', 'Google authorization expired. Please reconnect Google Calendar.');
        } catch (GoogleCalendarException | DomainException $e) {
            Flash::add('error', $e->getMessage());
        }
        return Response::redirect(url('/admin/live-classes'));
    }

    /** Shared create path for scheduled and instant classes. */
    private function persist(Request $r, bool $instant): Response
    {
        $institute = $this->instituteId();
        $service = $this->liveClassService();
        $redirect = url($instant ? '/admin/live-classes' : '/admin/live-classes/create');
        if (!$this->calendar()->isConnected($institute)) {
            Flash::add('error', 'Google Calendar is not connected. Connect the institute Google account first.');
            return Response::redirect($redirect);
        }
        try {
            $normalised = $service->normalise($r->all(), $institute, $service->timezone($institute), $instant);
            // An admin may nominate the hosting teacher; the value is validated against the
            // institute's employees, never taken on trust.
            $teacherId = (int) $r->input('teacher_id', 0) ?: null;
            if ($teacherId !== null && !$this->app->database()->query('SELECT 1 FROM employees WHERE id=:e AND institute_id=:i', ['e' => $teacherId, 'i' => $institute])->fetchColumn()) {
                $teacherId = null;
            }
            $result = $service->schedule($normalised, $institute, (int) user()['id'], 'admin', $teacherId);
        } catch (GoogleReconnectRequired) {
            Flash::add('error', 'Google authorization expired. Please reconnect Google Calendar.');
            return Response::redirect($redirect);
        } catch (GoogleCalendarException) {
            Flash::add('error', 'Google Calendar could not be reached. No local meeting changes were finalized.');
            return Response::redirect($redirect);
        } catch (DomainException $e) {
            Flash::add('error', $e->getMessage());
            return Response::redirect($redirect);
        }
        $service->audit($institute, (int) user()['id'], $instant ? 'live_class.instant_created' : 'live_class.created', $result['id'], ['pending' => $result['pending']]);
        Flash::add($result['pending'] ? 'error' : 'success', $result['pending']
            ? 'Google created the calendar event, but Meet link generation is still pending. Retry synchronisation from the list.'
            : ($instant ? 'Meeting ready. The Google Meet link is on the live class card.' : 'Live class scheduled.'));
        return Response::redirect(url('/admin/live-classes'));
    }

    /** Live Classes has no config/pages.php entry; page metadata is declared here instead. */
    private function render(string $view, string $title, array $data): Response
    {
        return $this->view($view, array_replace([
            'pageTitle' => $title,
            'pageDescription' => 'Google Meet live classes for this institute.',
            'breadcrumb' => ['Admin', 'Live Classes'],
            'activeMenu' => 'live-classes',
            'activeSubmenu' => 'live-classes',
            'styles' => ['css/live-classes.css?v=1'],
            'scripts' => ['js/live-classes.js?v=1'],
            'institute' => (new \App\Repositories\SettingsRepository($this->app->database()))->institute(),
        ], $data));
    }
}
