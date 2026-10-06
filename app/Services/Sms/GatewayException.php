<?php
declare(strict_types=1);
namespace App\Services\Sms;

/** An error the gateway API reports as JSON: a stable machine code, a safe message and an HTTP status. */
final class GatewayException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
