<?php
declare(strict_types=1);
namespace App\Services\Google;
use RuntimeException;

/**
 * The institute's Google authorisation is gone — revoked, expired beyond refresh, or never
 * granted. Callers turn this into the single admin-facing sentence asking for a reconnect; it
 * never carries a Google exception trace.
 */
final class GoogleReconnectRequired extends RuntimeException
{
}
