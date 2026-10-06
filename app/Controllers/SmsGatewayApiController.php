<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{App,Request,Response};
use App\Services\Sms\{GatewayException,SmsGateway,SmsQueue,SmsSettings};

/**
 * REST API for the Android SMS bridge: /api/sms-gateway/v1/*.
 *
 * Authenticated by a per-device bearer token, never by an admin session, and exempt from CSRF
 * (no cookies are involved). Every response is JSON; errors carry a stable "code".
 * The contract is documented in docs/android-sms-gateway.md.
 */
final class SmsGatewayApiController
{
    public const API_VERSION = '1';

    private SmsSettings $settings;
    private SmsGateway $gateway;
    private SmsQueue $queue;

    public function __construct(private readonly App $app)
    {
        $db = $app->database();
        $this->settings = new SmsSettings($db);
        $this->gateway = new SmsGateway($db, $this->settings);
        $this->queue = new SmsQueue($db, $this->settings);
    }

    public function register(Request $r): Response
    {
        return $this->handle(function () use ($r) {
            $this->gateway->throttle('register:' . hash('sha256', $r->ip()), 10);
            $body = $r->json();
            $paired = $this->gateway->pair((string) ($body['pairing_code'] ?? ''), $body, $r->ip());
            $d = $paired['device'];
            return ['token' => $paired['token'], 'token_type' => 'Bearer', 'device' => $this->devicePayload($d), 'config' => $this->config((int) $d['institute_id'])];
        }, 201);
    }

    public function heartbeat(Request $r): Response
    {
        return $this->authed($r, function (array $device) use ($r) {
            $this->gateway->recordInfo($device, $r->json(), $r->ip());
            $counts = $this->app->database()->query("SELECT SUM(status IN('queued','scheduled') AND (scheduled_at IS NULL OR scheduled_at<=:n1) AND (available_at IS NULL OR available_at<=:n2)) ready,SUM(status IN('reserved','processing') AND gateway_device_id=:d) held FROM sms_messages WHERE institute_id=:i",
                ['d' => $device['id'], 'i' => $device['institute_id'], 'n1' => date('Y-m-d H:i:s'), 'n2' => date('Y-m-d H:i:s')])->fetch();
            return ['server_time' => date(DATE_ATOM), 'queued_messages' => (int) ($counts['ready'] ?? 0), 'held_by_device' => (int) ($counts['held'] ?? 0),
                'next_heartbeat_seconds' => (int) $this->settings->get((int) $device['institute_id'])['heartbeat_interval_seconds']];
        });
    }

    public function deviceInfo(Request $r): Response
    {
        return $this->authed($r, function (array $device) use ($r) {
            $this->gateway->recordInfo($device, $r->json(), $r->ip());
            return ['device' => $this->devicePayload($this->gateway->find((int) $device['institute_id'], (int) $device['id']))];
        });
    }

    public function config(int $institute): array
    {
        $s = $this->settings->get($institute);
        return ['api_version' => self::API_VERSION, 'sms_enabled' => (bool) $s['sms_enabled'], 'queue_processing_enabled' => (bool) $s['queue_processing_enabled'],
            'batch_size' => (int) $s['batch_size'], 'heartbeat_interval_seconds' => (int) $s['heartbeat_interval_seconds'],
            'poll_interval_seconds' => max(15, min(120, (int) $s['heartbeat_interval_seconds'])), 'reservation_timeout_minutes' => (int) $s['reservation_timeout_minutes'],
            'default_sim_slot' => (int) $s['default_sim_slot'] ?: null, 'quiet_hours' => $s['quiet_hours_enabled'] ? ['start' => $s['quiet_hours_start'], 'end' => $s['quiet_hours_end']] : null,
            'retryable_error_codes' => SmsQueue::RETRYABLE_ERRORS, 'server_time' => date(DATE_ATOM)];
    }

    public function getConfig(Request $r): Response
    {
        return $this->authed($r, fn(array $device) => ['config' => $this->config((int) $device['institute_id'])]);
    }

    public function messages(Request $r): Response
    {
        return $this->authed($r, function (array $device) use ($r) {
            $limit = (int) ($r->query('limit') ?? 10);
            if ($limit < 1 || $limit > 100) {
                throw new GatewayException('INVALID_LIMIT', 'limit must be between 1 and 100.', 422);
            }
            $this->gateway->recordInfo($device, [], $r->ip());
            return ['messages' => $this->queue->reserve($device, $limit)];
        });
    }

    public function processing(Request $r): Response
    {
        return $this->authed($r, function (array $device) use ($r) {
            $id = $this->messageId($r);
            $this->queue->markProcessing($device, $id);
            return ['id' => $id, 'status' => 'processing'];
        });
    }

    public function sent(Request $r): Response
    {
        return $this->authed($r, function (array $device) use ($r) {
            $id = $this->messageId($r);
            $this->queue->markSent($device, $id, $r->json());
            return ['id' => $id, 'status' => 'sent'];
        });
    }

    public function failed(Request $r): Response
    {
        return $this->authed($r, function (array $device) use ($r) {
            $id = $this->messageId($r);
            $body = $r->json();
            $status = $this->queue->markFailed($device, $id, (string) ($body['error_code'] ?? 'UNKNOWN'), (string) ($body['error_message'] ?? ''));
            return ['id' => $id, 'status' => $status, 'will_retry' => $status === 'queued'];
        });
    }

    /** Several outcomes in one call: {"results":[{"id":1,"status":"sent",...},{"id":2,"status":"failed","error_code":"NO_SIGNAL"}]} */
    public function bulkStatus(Request $r): Response
    {
        return $this->authed($r, function (array $device) use ($r) {
            $results = (array) ($r->json()['results'] ?? []);
            if (!$results || count($results) > 100) {
                throw new GatewayException('INVALID_RESULTS', 'Send between 1 and 100 results.', 422);
            }
            $out = [];
            foreach ($results as $item) {
                $id = (int) ($item['id'] ?? 0);
                try {
                    $status = match ($item['status'] ?? '') {
                        'processing' => (function () use ($device, $id) { $this->queue->markProcessing($device, $id); return 'processing'; })(),
                        'sent' => (function () use ($device, $id, $item) { $this->queue->markSent($device, $id, (array) $item); return 'sent'; })(),
                        'failed' => $this->queue->markFailed($device, $id, (string) ($item['error_code'] ?? 'UNKNOWN'), (string) ($item['error_message'] ?? '')),
                        default => throw new GatewayException('INVALID_STATUS', 'status must be processing, sent or failed.', 422),
                    };
                    $out[] = ['id' => $id, 'success' => true, 'status' => $status];
                } catch (GatewayException $e) {
                    $out[] = ['id' => $id, 'success' => false, 'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]];
                }
            }
            return ['results' => $out];
        });
    }

    // ---- plumbing ------------------------------------------------------------------------

    private function messageId(Request $r): int
    {
        $id = (string) $r->route('id');
        if (!ctype_digit($id)) {
            throw new GatewayException('NOT_FOUND', 'Unknown message.', 404);
        }
        return (int) $id;
    }

    private function devicePayload(array $d): array
    {
        return ['id' => (int) $d['id'], 'device_uuid' => $d['device_uuid'], 'device_name' => $d['device_name'], 'phone_number' => $d['phone_number'], 'sim_slot' => $d['sim_slot'] !== null ? (int) $d['sim_slot'] : null];
    }

    private function authed(Request $r, callable $fn): Response
    {
        return $this->handle(function () use ($r, $fn) {
            $device = $this->gateway->authenticate($r->bearerToken());
            $this->gateway->throttle('device:' . $device['id']);
            return $fn($device);
        });
    }

    private function handle(callable $fn, int $status = 200): Response
    {
        try {
            return $this->json(['success' => true] + $fn(), $status);
        } catch (GatewayException $e) {
            return $this->json(['success' => false, 'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->status);
        } catch (\Throwable $e) {
            // Never leak paths, SQL or stack traces to the device.
            $this->app->logger()->error('SMS gateway API error', ['exception' => $e::class, 'message' => $e->getMessage()]);
            return $this->json(['success' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'The server could not complete the request.']], 500);
        }
    }

    private function json(array $data, int $status): Response
    {
        return new Response((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status, [
            'Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function notFound(Request $r): Response
    {
        return $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Unknown API endpoint.']], 404);
    }
}
