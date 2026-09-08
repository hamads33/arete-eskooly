<?php
declare(strict_types=1);
namespace App\Services\Google;
use RuntimeException;

/**
 * A Google-side failure that is not an authorisation problem: quota, network, malformed event,
 * calendar not found. The message is stored in live_classes.last_google_error and shown to
 * administrators; the original exception goes to storage/logs/app.log and no further.
 */
final class GoogleCalendarException extends RuntimeException
{
}
