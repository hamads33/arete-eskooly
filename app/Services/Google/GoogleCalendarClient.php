<?php
declare(strict_types=1);
namespace App\Services\Google;
use Google\Service\Calendar;
use Google\Service\Calendar\ConferenceData;
use Google\Service\Calendar\ConferenceSolution;
use Google\Service\Calendar\ConferenceSolutionKey;
use Google\Service\Calendar\CreateConferenceRequest;
use Google\Service\Calendar\EntryPoint;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

/**
 * The real Google Calendar implementation of CalendarClient.
 *
 * Every Meet link in this product is created here. The link comes from a Meet space created with
 * accessType OPEN and attached to a Calendar event, so that holding the link is enough to walk in:
 * no invitation, no knocking, and no host needed before a student can enter. Access is decided by
 * this application's roles and audience rules, which is the only place it should be decided —
 * students are not Calendar attendees (§21) and frequently have no Google address at all.
 *
 * Where the space cannot be created the event falls back to conferenceData.createRequest, Google's
 * ordinary asynchronous conference; that path returns 'pending' until the conference exists, which
 * is why GoogleCalendarService still polls (§17).
 */
final class GoogleCalendarClient implements CalendarClient
{
    public function __construct(private readonly GoogleClientFactory $factory) {}

    public function createEvent(array $connection, array $event): array
    {
        return $this->call($connection, function (Calendar $service, string $calendarId) use ($connection, $event): array {
            $payload = $this->event($event);
            // Prefer a Meet space we have configured as OPEN: this application already decides who
            // may see a class, and a second gate at Google's door only strands students it has
            // authorised. Where the space cannot be made, fall back to letting Calendar mint an
            // ordinary conference — a class that makes people knock still beats no class at all.
            $space = $this->openSpace((int) $connection['institute_id']);
            $payload->setConferenceData($space === null ? $this->conferenceRequest() : $this->existingConference($space));
            $created = $service->events->insert($calendarId, $payload, ['conferenceDataVersion' => 1]);
            return $this->describe($created, $calendarId);
        });
    }

    /**
     * Creates a Meet space that anyone holding the link may enter.
     *
     * accessType OPEN is Google's own wording for "anyone with the join information can join
     * without knocking" — no invitation, no host waiting to admit people, which is what makes a
     * student's Join button work the moment they press it. Null when the space cannot be created
     * (scope not yet granted on an older connection, quota, an outage); the caller then degrades to
     * a Calendar-created conference rather than failing the whole booking.
     *
     * @return array{uri:string,code:string}|null
     */
    private function openSpace(int $institute): ?array
    {
        try {
            $response = $this->factory->forInstitute($institute)->authorize()->post('https://meet.googleapis.com/v2/spaces', [
                'json' => ['config' => ['accessType' => 'OPEN', 'entryPointAccess' => 'ALL']],
                'http_errors' => false,
                'timeout' => 15,
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $body = json_decode((string) $response->getBody(), true);
            $uri = is_array($body) ? (string) ($body['meetingUri'] ?? '') : '';
            $code = is_array($body) ? (string) ($body['meetingCode'] ?? '') : '';
            return $uri === '' ? null : ['uri' => $uri, 'code' => $code !== '' ? $code : $uri];
        } catch (\Throwable) {
            // Never fatal: the conference falls back, and the class is still created.
            return null;
        }
    }

    /** Attaches an already-created Meet space instead of asking Calendar to mint a new one. */
    private function existingConference(array $space): ConferenceData
    {
        $key = new ConferenceSolutionKey();
        $key->setType('hangoutsMeet');
        $solution = new ConferenceSolution();
        $solution->setKey($key);
        $solution->setName('Google Meet');
        $entryPoint = new EntryPoint();
        $entryPoint->setEntryPointType('video');
        $entryPoint->setUri($space['uri']);
        $entryPoint->setLabel($space['code']);
        $data = new ConferenceData();
        $data->setConferenceId($space['code']);
        $data->setConferenceSolution($solution);
        $data->setEntryPoints([$entryPoint]);
        return $data;
    }

    public function updateEvent(array $connection, string $eventId, array $event): array
    {
        return $this->call($connection, function (Calendar $service, string $calendarId) use ($eventId, $event): array {
            // Patch, never insert-over: a full update without the existing conferenceData would
            // drop the Meet conference and invalidate the link students already hold (§28).
            $updated = $service->events->patch($calendarId, $eventId, $this->event($event), ['conferenceDataVersion' => 1]);
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
