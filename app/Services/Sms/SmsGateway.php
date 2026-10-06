<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;
use DomainException;

/**
 * Android gateway devices and their credentials.
 *
 * Two kinds of secret exist, and only their SHA-256 hashes are stored:
 *  - a pairing code: short-lived, single use, typed into the Android app once;
 *  - an access token: long-lived bearer credential the app sends on every API call.
 * A plaintext secret exists only in the response that creates it.
 */
final class SmsGateway
{
    public const PAIRING_TTL_MINUTES = 30;
    public const REQUESTS_PER_MINUTE = 120;

    private SmsLogger $log;

    public function __construct(private readonly Database $db, private readonly SmsSettings $settings)
    {
        $this->log = new SmsLogger($db);
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    // ---- admin: devices ------------------------------------------------------------------

    public function all(int $institute): array
    {
        $timeout = (int) $this->settings->get($institute)['offline_timeout_minutes'];
        $rows = $this->db->query(
            "SELECT d.*,(SELECT MAX(m.sent_at) FROM sms_messages m WHERE m.gateway_device_id=d.id AND m.status='sent') last_sent_at,
                    (SELECT COUNT(*) FROM sms_messages m WHERE m.gateway_device_id=d.id AND m.status='sent') sent_count,
                    (SELECT COUNT(*) FROM sms_gateway_tokens t WHERE t.gateway_device_id=d.id AND t.token_type='access' AND t.revoked_at IS NULL) active_tokens,
                    (SELECT MAX(t.expires_at) FROM sms_gateway_tokens t WHERE t.gateway_device_id=d.id AND t.token_type='pairing' AND t.revoked_at IS NULL AND t.expires_at>:now) pairing_expires_at
             FROM sms_gateway_devices d WHERE d.institute_id=:i ORDER BY d.created_at DESC",
            ['i' => $institute, 'now' => date('Y-m-d H:i:s')]
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['live_status'] = self::liveStatus($r, $timeout);
        }
        return $rows;
    }

    public function find(int $institute, int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM sms_gateway_devices WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $institute])->fetch();
        return $row ?: null;
    }

    /** Status derived from the last heartbeat, so no cron is needed to mark devices offline. */
    public static function liveStatus(array $device, int $offlineMinutes): string
    {
        if (!(int) $device['is_active']) {
            return 'disabled';
        }
        if (!$device['last_seen_at']) {
            return 'pending';
        }
        return strtotime((string) $device['last_seen_at']) >= time() - 60 * $offlineMinutes ? 'connected' : 'offline';
    }

    public function create(int $institute, string $name, ?string $simSlot, int $user): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainException('Device name is required (up to 120 characters).');
        }
        $slot = $simSlot !== null && $simSlot !== '' ? max(1, min(4, (int) $simSlot)) : null;
        $now = date('Y-m-d H:i:s');
        $this->db->query("INSERT INTO sms_gateway_devices(institute_id,device_name,sim_slot,status,is_active,created_by,created_at,updated_at) VALUES(:i,:n,:s,'pending',1,:u,:c,:c2)",
            ['i' => $institute, 'n' => $name, 's' => $slot, 'u' => $user, 'c' => $now, 'c2' => $now]);
        $id = (int) $this->db->connection()->lastInsertId();
        $this->log->log($institute, 'device_registered', SmsLogger::user(), null, $id, ['name' => $name]);
        return $id;
    }

    public function rename(int $institute, int $id, string $name, ?string $simSlot): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainException('Device name is required (up to 120 characters).');
        }
        $this->require($institute, $id);
        $slot = $simSlot !== null && $simSlot !== '' ? max(1, min(4, (int) $simSlot)) : null;
        $this->db->query('UPDATE sms_gateway_devices SET device_name=:n,sim_slot=:s,updated_at=:u WHERE id=:id AND institute_id=:i', ['n' => $name, 's' => $slot, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute]);
        $this->log->log($institute, 'device_updated', SmsLogger::user(), null, $id, ['name' => $name]);
    }

    public function setActive(int $institute, int $id, bool $active): void
    {
        $this->require($institute, $id);
        $this->db->query("UPDATE sms_gateway_devices SET is_active=:a,status=:s,updated_at=:u WHERE id=:id AND institute_id=:i",
            ['a' => (int) $active, 's' => $active ? 'pending' : 'disabled', 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute]);
        if (!$active) {
            // A disabled device must not keep messages it had reserved but not started.
            $this->db->query("UPDATE sms_messages SET status='queued',gateway_device_id=NULL,reserved_at=NULL,updated_at=:u WHERE gateway_device_id=:d AND status='reserved'", ['u' => date('Y-m-d H:i:s'), 'd' => $id]);
        }
        $this->log->log($institute, $active ? 'device_enabled' : 'device_disabled', SmsLogger::user(), null, $id);
    }

    /**
     * Remove a gateway registration while preserving message and audit history.
     * Reserved work is safe to return to the queue. Processing work is failed instead of
     * requeued because the phone may already have handed it to Android's SMS subsystem.
     */
    public function delete(int $institute, int $id): void
    {
        $device = $this->require($institute, $id);
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $released = $this->db->query("UPDATE sms_messages SET status='queued',gateway_device_id=NULL,reserved_at=NULL,updated_at=:u WHERE institute_id=:i AND gateway_device_id=:d AND status='reserved'", ['u' => $now, 'i' => $institute, 'd' => $id])->rowCount();
            $failed = $this->db->query("UPDATE sms_messages SET status='failed',failed_at=:f,failure_code='DEVICE_REMOVED',failure_reason='Gateway device removed while sending.',updated_at=:u WHERE institute_id=:i AND gateway_device_id=:d AND status='processing'", ['f' => $now, 'u' => $now, 'i' => $institute, 'd' => $id])->rowCount();
            $sent = (int) $this->db->query("SELECT COUNT(*) FROM sms_messages WHERE institute_id=:i AND gateway_device_id=:d AND status='sent'", ['i' => $institute, 'd' => $id])->fetchColumn();
            $this->db->query('DELETE FROM sms_gateway_devices WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $institute]);
            $this->log->log($institute, 'device_deleted', SmsLogger::user(), null, null, [
                'device_id' => $id,
                'device_name' => (string) $device['device_name'],
                'released_messages' => $released,
                'failed_messages' => $failed,
                'sent_messages_preserved' => $sent,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** A new single-use pairing code; earlier unused codes for the device stop working. */
    public function issuePairingCode(int $institute, int $id, int $user): array
    {
        $device = $this->require($institute, $id);
        if (!(int) $device['is_active']) {
            throw new DomainException('Enable the device before pairing it.');
        }
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $display = implode('-', str_split($code, 4));
        $now = date('Y-m-d H:i:s');
        $this->db->query("UPDATE sms_gateway_tokens SET revoked_at=:n WHERE gateway_device_id=:d AND token_type='pairing' AND revoked_at IS NULL", ['n' => $now, 'd' => $id]);
        $expires = date('Y-m-d H:i:s', time() + 60 * self::PAIRING_TTL_MINUTES);
        $this->db->query("INSERT INTO sms_gateway_tokens(gateway_device_id,token_type,token_hash,token_hint,expires_at,created_by,created_at) VALUES(:d,'pairing',:h,:hint,:e,:u,:c)",
            ['d' => $id, 'h' => self::hash($code), 'hint' => substr($code, -4), 'e' => $expires, 'u' => $user, 'c' => $now]);
        $this->log->log($institute, 'pairing_code_issued', SmsLogger::user(), null, $id, ['expires_at' => $expires]);
        return ['code' => $display, 'expires_at' => $expires];
    }

    /** Issues a fresh access token and revokes every earlier one for the device. */
    public function issueAccessToken(int $institute, int $id, ?int $user, ?array $actor = null): string
    {
        $device = $this->require($institute, $id);
        if (!(int) $device['is_active']) {
            throw new DomainException('Enable the device before issuing a token.');
        }
        $token = 'smsgw_' . bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $this->db->query("UPDATE sms_gateway_tokens SET revoked_at=:n WHERE gateway_device_id=:d AND token_type='access' AND revoked_at IS NULL", ['n' => $now, 'd' => $id]);
        $this->db->query("INSERT INTO sms_gateway_tokens(gateway_device_id,token_type,token_hash,token_hint,created_by,created_at) VALUES(:d,'access',:h,:hint,:u,:c)",
            ['d' => $id, 'h' => self::hash($token), 'hint' => substr($token, -4), 'u' => $user, 'c' => $now]);
        $this->log->log($institute, 'token_regenerated', $actor ?? SmsLogger::user(), null, $id);
        return $token;
    }

    public function revokeTokens(int $institute, int $id): void
    {
        $this->require($institute, $id);
        $this->db->query('UPDATE sms_gateway_tokens SET revoked_at=:n WHERE gateway_device_id=:d AND revoked_at IS NULL', ['n' => date('Y-m-d H:i:s'), 'd' => $id]);
        $this->log->log($institute, 'token_revoked', SmsLogger::user(), null, $id);
    }

    private function require(int $institute, int $id): array
    {
        $d = $this->find($institute, $id);
        if (!$d) {
            throw new DomainException('Device not found.');
        }
        return $d;
    }

    // ---- API: authentication and device reports -------------------------------------------

    /** Exchanges a pairing code for an access token and records the device's details. */
    public function pair(string $code, array $info, string $ip): array
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($code) !== 12) {
            throw new GatewayException('INVALID_PAIRING_CODE', 'The pairing code is invalid or has expired.', 401);
        }
        $row = $this->db->query("SELECT t.id token_id,d.* FROM sms_gateway_tokens t JOIN sms_gateway_devices d ON d.id=t.gateway_device_id
            WHERE t.token_hash=:h AND t.token_type='pairing' AND t.revoked_at IS NULL AND t.expires_at>:n", ['h' => self::hash($code), 'n' => date('Y-m-d H:i:s')])->fetch();
        if (!$row || !(int) $row['is_active']) {
            throw new GatewayException('INVALID_PAIRING_CODE', 'The pairing code is invalid or has expired.', 401);
        }
        $uuid = trim((string) ($info['device_uuid'] ?? ''));
        if ($uuid === '' || strlen($uuid) > 100 || !preg_match('/^[A-Za-z0-9._:\-]+$/', $uuid)) {
            throw new GatewayException('INVALID_DEVICE_UUID', 'device_uuid is required (letters, digits, . _ : -; up to 100 characters).', 422);
        }
        $other = $this->db->query('SELECT id FROM sms_gateway_devices WHERE device_uuid=:u AND id<>:id', ['u' => $uuid, 'id' => $row['id']])->fetchColumn();
        if ($other) {
            throw new GatewayException('DEVICE_ALREADY_REGISTERED', 'This phone is already paired as another gateway device. Remove that registration first.', 409);
        }
        // Consume the code before issuing a token so a replayed code cannot pair twice.
        $used = $this->db->query('UPDATE sms_gateway_tokens SET revoked_at=:n,last_used_at=:n2 WHERE id=:id AND revoked_at IS NULL', ['n' => date('Y-m-d H:i:s'), 'n2' => date('Y-m-d H:i:s'), 'id' => $row['token_id']])->rowCount();
        if (!$used) {
            throw new GatewayException('INVALID_PAIRING_CODE', 'The pairing code is invalid or has expired.', 401);
        }
        $this->db->query('UPDATE sms_gateway_devices SET device_uuid=:u,updated_at=:n WHERE id=:id', ['u' => $uuid, 'n' => date('Y-m-d H:i:s'), 'id' => $row['id']]);
        $device = $this->find((int) $row['institute_id'], (int) $row['id']);
        $this->recordInfo($device, $info, $ip, false);
        $token = $this->issueAccessToken((int) $row['institute_id'], (int) $row['id'], null, SmsLogger::device($device));
        $this->log->log((int) $row['institute_id'], 'device_paired', SmsLogger::device($device), null, (int) $row['id'], ['ip' => $ip]);
        return ['token' => $token, 'device' => $this->find((int) $row['institute_id'], (int) $row['id'])];
    }

    /** Resolves a bearer token to an active device, or throws a 401/403 GatewayException. */
    public function authenticate(?string $token): array
    {
        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 200) {
            throw new GatewayException('UNAUTHENTICATED', 'A valid device token is required.', 401);
        }
        $row = $this->db->query("SELECT t.id token_id,t.revoked_at,t.expires_at,d.* FROM sms_gateway_tokens t JOIN sms_gateway_devices d ON d.id=t.gateway_device_id WHERE t.token_hash=:h AND t.token_type='access'",
            ['h' => self::hash($token)])->fetch();
        if (!$row || $row['revoked_at'] !== null || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) <= time())) {
            throw new GatewayException('UNAUTHENTICATED', 'A valid device token is required.', 401);
        }
        if (!(int) $row['is_active']) {
            throw new GatewayException('DEVICE_DISABLED', 'This gateway device has been disabled by the institute.', 403);
        }
        $this->db->query('UPDATE sms_gateway_tokens SET last_used_at=:n WHERE id=:id', ['n' => date('Y-m-d H:i:s'), 'id' => $row['token_id']]);
        unset($row['token_id'], $row['revoked_at'], $row['expires_at']);
        return $row;
    }

    /** Fixed-window limiter; throws a 429 GatewayException past $max hits per minute. */
    public function throttle(string $bucket, int $max = self::REQUESTS_PER_MINUTE): void
    {
        $window = date('Y-m-d H:i:00');
        $bucket = substr($bucket, 0, 80);
        $this->db->query('INSERT INTO sms_api_rate_limits(bucket,window_start,hits) VALUES(:b,:w,1) ON DUPLICATE KEY UPDATE hits=hits+1', ['b' => $bucket, 'w' => $window]);
        $hits = (int) $this->db->query('SELECT hits FROM sms_api_rate_limits WHERE bucket=:b AND window_start=:w', ['b' => $bucket, 'w' => $window])->fetchColumn();
        if (random_int(1, 50) === 1) {
            $this->db->query('DELETE FROM sms_api_rate_limits WHERE window_start<:c', ['c' => date('Y-m-d H:i:s', time() - 3600)]);
        }
        if ($hits > $max) {
            throw new GatewayException('RATE_LIMITED', 'Too many requests. Slow down and retry in a minute.', 429);
        }
    }

    /** Heartbeat / device-info: marks the device connected and stores the details it may report. */
    public function recordInfo(array $device, array $info, string $ip, bool $logConnect = true): void
    {
        $fields = [];
        $args = [];
        $text = ['sim_name' => 80, 'manufacturer' => 80, 'model' => 80, 'android_version' => 20, 'app_version' => 20, 'network' => 20];
        foreach ($text as $k => $len) {
            if (isset($info[$k]) && is_scalar($info[$k]) && trim((string) $info[$k]) !== '') {
                $fields[] = "{$k}=:{$k}";
                $args[$k] = mb_substr(strip_tags(trim((string) $info[$k])), 0, $len);
            }
        }
        if (isset($info['battery_level']) && is_numeric($info['battery_level'])) {
            $fields[] = 'battery_level=:battery_level';
            $args['battery_level'] = max(0, min(100, (int) $info['battery_level']));
        }
        if (isset($info['sim_slot']) && is_numeric($info['sim_slot'])) {
            $fields[] = 'sim_slot=:sim_slot';
            $args['sim_slot'] = max(1, min(4, (int) $info['sim_slot']));
        }
        if (!empty($info['phone_number'])) {
            $phone = PhoneNumber::tryNormalize((string) $info['phone_number'], (string) $this->settings->get((int) $device['institute_id'])['default_country_code']);
            if ($phone) {
                $fields[] = 'phone_number=:phone_number';
                $args['phone_number'] = $phone;
            }
        }
        $wasConnected = self::liveStatus($device, (int) $this->settings->get((int) $device['institute_id'])['offline_timeout_minutes']) === 'connected';
        $fields[] = "status='connected'";
        $fields[] = 'last_seen_at=:seen';
        $fields[] = 'last_ip=:ip';
        $fields[] = 'updated_at=:upd';
        $args += ['seen' => date('Y-m-d H:i:s'), 'ip' => substr($ip, 0, 45), 'upd' => date('Y-m-d H:i:s'), 'id' => $device['id']];
        $this->db->query('UPDATE sms_gateway_devices SET ' . implode(',', $fields) . ' WHERE id=:id', $args);
        if ($logConnect && !$wasConnected) {
            $this->log->log((int) $device['institute_id'], 'device_connected', SmsLogger::device($device), null, (int) $device['id'], ['ip' => $ip]);
        }
    }
}
