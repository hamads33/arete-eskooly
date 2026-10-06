<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;

/**
 * SMS audit trail. Details must never contain tokens or other secrets; callers pass only
 * identifiers, statuses and error codes.
 */
final class SmsLogger
{
    public function __construct(private readonly Database $db) {}

    /** @param array{type:string,id:?int,name:?string} $actor */
    public function log(int $institute, string $event, array $actor, ?int $messageId = null, ?int $deviceId = null, array $details = []): void
    {
        $this->db->query(
            'INSERT INTO sms_logs(institute_id,sms_message_id,gateway_device_id,event,actor_type,actor_id,actor_name,details_json,created_at) VALUES(:i,:m,:d,:e,:t,:a,:n,:j,:c)',
            ['i' => $institute, 'm' => $messageId, 'd' => $deviceId, 'e' => $event, 't' => $actor['type'], 'a' => $actor['id'] ?? null,
             'n' => isset($actor['name']) ? mb_substr((string) $actor['name'], 0, 160) : null, 'j' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, 'c' => date('Y-m-d H:i:s')]
        );
    }

    /** The signed-in user as an actor. */
    public static function user(): array
    {
        $u = function_exists('user') ? user() : null;
        return $u ? ['type' => 'user', 'id' => (int) $u['id'], 'name' => (string) ($u['name'] ?? '')] : self::system();
    }

    public static function system(): array
    {
        return ['type' => 'system', 'id' => null, 'name' => 'System'];
    }

    public static function device(array $device): array
    {
        return ['type' => 'device', 'id' => (int) $device['id'], 'name' => (string) $device['device_name']];
    }
}
