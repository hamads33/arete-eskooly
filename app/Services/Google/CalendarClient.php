<?php
declare(strict_types=1);
namespace App\Services\Google;

/**
 * The seam between our domain and Google Calendar.
 *
 * Everything above this interface speaks plain arrays, so the automated suite can substitute
 * FakeCalendarClient and stay deterministic without ever contacting Google (§45). The real
 * implementation is GoogleCalendarClient; the manual checklist in docs/GOOGLE-MEET-INTEGRATION.md
 * is what exercises the live API.
 *
 * Every method returns, or throws:
 *   - GoogleReconnectRequired when the institute's authorisation is gone
 *   - GoogleCalendarException for any other Google-side failure
 *
 * Event shape returned by createEvent/updateEvent/fetchEvent:
 *   ['event_id'=>string,'calendar_id'=>string,'meet_link'=>?string,'conference_status'=>string]
 * conference_status is Google's own vocabulary: 'success', 'pending', or 'failure'.
 */
interface CalendarClient
{
    /**
     * @param array{summary:string,description:?string,start:string,end:string,timezone:string,host_email?:?string} $event
     *        start/end are 'Y-m-d H:i:s' wall-clock times in $event['timezone'].
     *        host_email, when present, is the teaching employee: they are invited to the event so
     *        Meet admits them to their own class instead of making them ask to join.
     */
    public function createEvent(array $connection, array $event): array;

    public function updateEvent(array $connection, string $eventId, array $event): array;

    /** Used to poll for a conference that Google had not finished creating (§17). */
    public function fetchEvent(array $connection, string $eventId): array;

    /** Cancels rather than hard-deletes where the API allows it, so the calendar keeps the record. */
    public function cancelEvent(array $connection, string $eventId): void;

    /** Compensation for a failed local write after Google already created the event (§31). */
    public function deleteEvent(array $connection, string $eventId): void;
}
