<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Services\Google\{CalendarClient, GoogleCalendarException, GoogleReconnectRequired};

/**
 * Deterministic stand-in for Google Calendar (§45).
 *
 * The automated suite must never contact Google: a test that depends on a live API is slow,
 * needs credentials, and fails for reasons that have nothing to do with the code under test. This
 * records what it was asked to do and returns whatever the test told it to, including the two
 * awkward cases real Google produces — a conference that is still pending, and a revoked token.
 *
 * The live API is covered instead by the manual checklist in docs/GOOGLE-MEET-INTEGRATION.md.
 */
final class FakeCalendarClient implements CalendarClient
{
    /** @var list<array{op:string,event_id:?string,event:?array}> */
    public array $calls = [];
    public int $created = 0;
    public int $deleted = 0;

    /**
     * Event ids must be unique across runs, not just within one: the real Google never reissues an
     * id, and live_classes enforces UNIQUE(institute_id, google_event_id), so a counter alone
     * would collide with rows left by the previous run of this suite.
     */
    private readonly string $prefix;

    public function __construct(
        /** When true, createEvent returns no Meet link until fetchEvent is called $pendingFor times. */
        public bool $pending = false,
        public int $pendingFor = 0,
        /** Set to throw from every operation, for the failure paths. */
        public ?\Throwable $failure = null
    ) {
        $this->prefix = 'evt-' . bin2hex(random_bytes(6)) . '-';
    }

    public static function reconnectRequired(): self
    {
        return new self(failure: new GoogleReconnectRequired('Google authorisation expired or was revoked.'));
    }

    public static function unreachable(): self
    {
        return new self(failure: new GoogleCalendarException('Google Calendar could not be reached.'));
    }

    public function createEvent(array $connection, array $event): array
    {
        $this->guard();
        $this->created++;
        $this->calls[] = ['op' => 'create', 'event_id' => null, 'event' => $event];
        return $this->result($this->prefix . $this->created);
    }

    public function updateEvent(array $connection, string $eventId, array $event): array
    {
        $this->guard();
        $this->calls[] = ['op' => 'update', 'event_id' => $eventId, 'event' => $event];
        return $this->result($eventId);
    }

    public function fetchEvent(array $connection, string $eventId): array
    {
        $this->guard();
        $this->calls[] = ['op' => 'fetch', 'event_id' => $eventId, 'event' => null];
        if ($this->pendingFor > 0) {
            $this->pendingFor--;
        }
        return $this->result($eventId);
    }

    public function cancelEvent(array $connection, string $eventId): void
    {
        $this->guard();
        $this->calls[] = ['op' => 'cancel', 'event_id' => $eventId, 'event' => null];
    }

    public function deleteEvent(array $connection, string $eventId): void
    {
        $this->deleted++;
        $this->calls[] = ['op' => 'delete', 'event_id' => $eventId, 'event' => null];
    }

    /** The last event payload handed to Google — used to assert the timezone actually sent. */
    public function lastEvent(): ?array
    {
        for ($i = count($this->calls) - 1; $i >= 0; $i--) {
            if ($this->calls[$i]['event'] !== null) {
                return $this->calls[$i]['event'];
            }
        }
        return null;
    }

    public function operations(): array
    {
        return array_column($this->calls, 'op');
    }

    private function result(string $eventId): array
    {
        $stillPending = $this->pending && $this->pendingFor > 0;
        return [
            'event_id' => $eventId,
            'calendar_id' => 'primary',
            'meet_link' => $stillPending ? null : 'https://meet.google.com/' . substr(md5($eventId), 0, 12),
            'conference_status' => $stillPending ? 'pending' : 'success',
        ];
    }

    private function guard(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
