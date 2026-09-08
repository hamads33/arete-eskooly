<?php
declare(strict_types=1);
namespace App\Services\Google;
use Google\Service\Calendar;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;

/**
 * The real Google Calendar implementation of CalendarClient.
 *
 * Every Meet link in this product is created here, as a Calendar event carrying a
 * conferenceData.createRequest with the hangoutsMeet solution and conferenceDataVersion=1. Google
 * generates the conference asynchronously, so the caller (GoogleCalendarService) is responsible
 * for polling when conference_status comes back 'pending' (§17).
 *
 * The class's teacher and its creator are added as attendees, because the event lives on the
 * institute's calendar and Meet otherwise treats them as uninvited guests at their own lesson.
 * Students are still not attendees (§21): they are LMS participants, they may have no Google
 * address at all, and adding them would email every guardian's inbox on every edit.
 */
final class GoogleCalendarClient implements CalendarClient
{
    public function __construct(private readonly GoogleClientFactory $factory) {}

    public function createEvent(array $connection, array $event): array
    {
        return $this->call($connection, function (Calendar $service, string $calendarId) use ($event): array {
            $payload = $this->event($event);
            $payload->setConferenceData($this->conferenceRequest());
            // sendUpdates=all so the teacher actually receives the invitation; attendee membership
            // alone fixes Meet admission, but the invite is what puts the class on their calendar.
            $created = $service->events->insert($calendarId, $payload, ['conferenceDataVersion' => 1, 'sendUpdates' => 'all']);
            return $this->describe($created, $calendarId);
        });
    }

    public function updateEvent(array $connection, string $eventId, array $event): array
    {
        return $this->call($connection, function (Calendar $service, string $calendarId) use ($eventId, $event): array {
            // Patch, never insert-over: a full update without the existing conferenceData would
            // drop the Meet conference and invalidate the link students already hold (§28).
            $updated = $service->events->patch($calendarId, $eventId, $this->event($event), ['conferenceDataVersion' => 1, 'sendUpdates' => 'all']);
            return $this->describe($updated, $calendarId);
        });
    }

    public function fetchEvent(array $connection, string $eventId): array
    {
        return $this->call($connection, fn (Calendar $service, string $calendarId): array => $this->describe($service->events->get($calendarId, $eventId), $calendarId));
    }

    public function cancelEvent(array $connection, string $eventId): void
    {
        $this->call($connection, function (Calendar $service, string $calendarId) use ($eventId): array {
            // 'cancelled' keeps the event visible as cancelled on the institute's calendar and
            // notifies anyone who had it, which delete() would not do as clearly.
            $service->events->patch($calendarId, $eventId, new Event(['status' => 'cancelled']));
            return [];
        });
    }

    public function deleteEvent(array $connection, string $eventId): void
    {
        $this->call($connection, function (Calendar $service, string $calendarId) use ($eventId): array {
            $service->events->delete($calendarId, $eventId);
            return [];
        });
    }

    /** Shared plumbing: authorised service, calendar id, and Google-exception translation. */
    private function call(array $connection, callable $operation): array
    {
        $institute = (int) $connection['institute_id'];
        $calendarId = (string) ($connection['calendar_id'] ?? 'primary') ?: 'primary';
        $client = $this->factory->forInstitute($institute); // may throw GoogleReconnectRequired
        try {
            return $operation(new Calendar($client), $calendarId);
        } catch (\Google\Service\Exception $exception) {
            $code = $exception->getCode();
            if ($code === 401 || $code === 403) {
                $this->factory->connections()->markReconnectRequired($institute, 'Google rejected the stored authorisation.');
                throw new GoogleReconnectRequired('Google authorisation expired or was revoked.', 0, $exception);
            }
            // Google's own message is kept short and sanitised; the full trace stays in the log.
            throw new GoogleCalendarException($this->readableMessage($exception), 0, $exception);
        } catch (GoogleReconnectRequired | GoogleCalendarException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new GoogleCalendarException('Google Calendar could not be reached.', 0, $exception);
        }
    }

    private function event(array $event): Event
    {
        $google = new Event();
        $google->setSummary((string) $event['summary']);
        if (($event['description'] ?? null) !== null && $event['description'] !== '') {
            $google->setDescription((string) $event['description']);
        }
        $google->setStart($this->when((string) $event['start'], (string) $event['timezone']));
        $google->setEnd($this->when((string) $event['end'], (string) $event['timezone']));
        // Staff must be on the invite list or Meet makes them knock at a room whose organiser — the
        // institute account — is not there to let them in. Accepted on their behalf so the class
        // simply appears on their calendar, and allowed to invite so they can admit a straggler.
        $attendees = [];
        foreach ($event['host_emails'] ?? [] as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }
            $attendee = new EventAttendee();
            $attendee->setEmail($email);
            $attendee->setResponseStatus('accepted');
            $attendees[] = $attendee;
        }
        if ($attendees !== []) {
            $google->setAttendees($attendees);
            $google->setGuestsCanInviteOthers(true);
        }
        return $google;
    }

    /**
     * RFC3339 with an explicit timeZone. The wall-clock string is emitted in the institute's own
     * zone, so what the admin typed is exactly what Google stores — this is where a naive
     * implementation picks up its one-hour drift (§33).
     */
    private function when(string $wallClock, string $timezone): EventDateTime
    {
        $moment = new \DateTimeImmutable($wallClock, new \DateTimeZone($timezone));
        $value = new EventDateTime();
        $value->setDateTime($moment->format(\DateTimeInterface::RFC3339));
        $value->setTimeZone($timezone);
        return $value;
    }

    private function conferenceRequest(): ConferenceData
    {
        $key = new ConferenceSolutionKey();
        $key->setType('hangoutsMeet');
        $request = new CreateConferenceRequest();
        // Google deduplicates on this id, so it must be unique per event or a retry silently
        // reuses the previous conference.
        $request->setRequestId(bin2hex(random_bytes(16)));
        $request->setConferenceSolutionKey($key);
        $data = new ConferenceData();
        $data->setCreateRequest($request);
        return $data;
    }

    /** Normalises a Google event into the array shape CalendarClient promises. */
    private function describe(Event $event, string $calendarId): array
    {
        $conference = $event->getConferenceData();
        $status = 'pending';
        if ($conference !== null && $conference->getCreateRequest() !== null && $conference->getCreateRequest()->getStatus() !== null) {
            $status = (string) $conference->getCreateRequest()->getStatus()->getStatusCode();
        }
        $link = $event->getHangoutLink();
        if (!$link && $conference !== null) {
            foreach ($conference->getEntryPoints() ?? [] as $entryPoint) {
                if ($entryPoint->getEntryPointType() === 'video' && $entryPoint->getUri()) {
                    $link = $entryPoint->getUri();
                    break;
                }
            }
        }
        // A link that is actually present is the real signal, whatever the create-request says.
        if ($link) {
            $status = 'success';
        }
        return ['event_id' => (string) $event->getId(), 'calendar_id' => $calendarId, 'meet_link' => $link ?: null, 'conference_status' => $status];
    }

    private function readableMessage(\Google\Service\Exception $exception): string
    {
        $errors = $exception->getErrors();
        $message = is_array($errors) && isset($errors[0]['message']) ? (string) $errors[0]['message'] : 'Google Calendar rejected the request.';
        return mb_substr(strip_tags($message), 0, 200);
    }
}
