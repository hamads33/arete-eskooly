<?php
declare(strict_types=1);
namespace App\Services\Google;
use App\Repositories\GoogleConnectionRepository;

/**
 * Domain-level Calendar operations, sitting between LiveClassService and the raw CalendarClient.
 *
 * Its one substantive job beyond delegation is handling Google's asynchronous conference creation
 * (§17): an insert can return before the Meet conference exists, so a 'pending' result is re-fetched
 * with bounded backoff. Bounded is the point — three attempts, then the caller records a pending
 * sync state and offers the admin a retry, rather than looping and holding the request open.
 */
final class GoogleCalendarService
{
    /** Sleep between conference polls, in microseconds. Three tries, ~3.5s worst case. */
    private const BACKOFF = [500000, 1000000, 2000000];

    public function __construct(private readonly CalendarClient $client, private readonly GoogleConnectionRepository $connections) {}

    public function isConnected(int $institute): bool
    {
        return $this->connections->isConnected($institute);
    }

    /** @throws GoogleReconnectRequired|GoogleCalendarException */
    public function createMeeting(int $institute, array $event): array
    {
        return $this->settle($institute, $this->client->createEvent($this->connection($institute), $event));
    }

    /** @throws GoogleReconnectRequired|GoogleCalendarException */
    public function updateMeeting(int $institute, string $eventId, array $event): array
    {
        return $this->settle($institute, $this->client->updateEvent($this->connection($institute), $eventId, $event));
    }

    /** Re-polls an event whose conference was still pending when it was created (§17, admin retry). */
    public function refreshConference(int $institute, string $eventId): array
    {
        return $this->settle($institute, $this->client->fetchEvent($this->connection($institute), $eventId));
    }

    public function cancelMeeting(int $institute, string $eventId): void
    {
        $this->client->cancelEvent($this->connection($institute), $eventId);
    }

    /** Compensation only: undoes a Google event whose local row could not be written (§31). */
    public function deleteMeeting(int $institute, string $eventId): void
    {
        $this->client->deleteEvent($this->connection($institute), $eventId);
    }

    /**
     * Waits, briefly and finitely, for Google to finish creating the conference. Returns whatever
     * state it reached — the caller must not treat a pending result as success.
     */
    private function settle(int $institute, array $result): array
    {
        if (($result['meet_link'] ?? null) !== null || ($result['conference_status'] ?? '') === 'failure') {
            return $result;
        }
        $connection = $this->connection($institute);
        foreach (self::BACKOFF as $pause) {
            usleep($pause);
            try {
                $result = $this->client->fetchEvent($connection, (string) $result['event_id']);
            } catch (GoogleCalendarException) {
                // A failed poll is not a failed meeting: the event exists, so keep the last known
                // state and let the admin retry synchronisation.
                break;
            }
            if (($result['meet_link'] ?? null) !== null || ($result['conference_status'] ?? '') === 'failure') {
                break;
            }
        }
        return $result;
    }

    /** @throws GoogleReconnectRequired */
    private function connection(int $institute): array
    {
        $connection = $this->connections->find($institute);
        if ($connection === null || $connection['status'] !== 'connected') {
            throw new GoogleReconnectRequired('Google Calendar is not connected.');
        }
        return $connection;
    }
}
