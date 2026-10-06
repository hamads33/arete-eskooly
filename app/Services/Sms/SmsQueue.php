<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;
use DomainException;

/**
 * The SMS queue. Nothing here sends an SMS: messages wait as 'queued' until a paired Android
 * gateway reserves them through the API and reports back.
 *
 * Lifecycle:
 *   queued|scheduled --reserve--> reserved --processing--> processing --sent--> sent
 *   reserved|processing --failed (retryable, attempts left)--> queued (after retry delay)
 *   reserved|processing --failed (permanent or out of attempts)--> failed
 *   queued|scheduled|failed --cancel--> cancelled
 */
final class SmsQueue
{
    public const STATUSES = ['queued', 'scheduled', 'reserved', 'processing', 'sent', 'failed', 'cancelled'];
    public const TYPES = ['manual', 'attendance', 'fee', 'result', 'announcement', 'admission', 'general', 'system'];
    public const SOURCES = ['manual', 'bulk', 'automation', 'attendance_module', 'fee_module', 'exam_module', 'online_test_module', 'announcement_module', 'admission_module', 'api'];
    public const RECIPIENT_TYPES = ['student', 'guardian', 'staff', 'manual'];
    public const PRIORITIES = ['high' => 1, 'normal' => 2, 'low' => 3];
    /** Failures worth another attempt; anything else (INVALID_NUMBER, SMS_PERMISSION_DENIED...) is final. */
    public const RETRYABLE_ERRORS = ['NO_SIGNAL', 'DEVICE_TEMPORARILY_UNAVAILABLE', 'SIM_BUSY', 'NETWORK_ERROR', 'RADIO_OFF', 'GENERIC_FAILURE', 'TIMEOUT', 'RATE_LIMITED'];
    /** Gateways that report Android's raw SmsManager result names instead of our codes; unmapped, these were treated as permanent and the message was dropped. */
    private const ANDROID_CODES = ['RESULT_ERROR_NO_SERVICE' => 'NO_SIGNAL', 'RESULT_ERROR_RADIO_OFF' => 'RADIO_OFF', 'RESULT_ERROR_GENERIC_FAILURE' => 'GENERIC_FAILURE',
        'RESULT_ERROR_NULL_PDU' => 'GENERIC_FAILURE', 'RESULT_ERROR_LIMIT_EXCEEDED' => 'RATE_LIMITED', 'RESULT_ERROR_SHORT_CODE_NEVER_ALLOWED' => 'INVALID_NUMBER',
        'RESULT_NETWORK_ERROR' => 'NETWORK_ERROR', 'RESULT_NO_DEFAULT_SMS_APP' => 'GENERIC_FAILURE', 'RESULT_MODEM_ERROR' => 'GENERIC_FAILURE', 'RESULT_RADIO_NOT_AVAILABLE' => 'RADIO_OFF',
        'RESULT_SYSTEM_ERROR' => 'GENERIC_FAILURE', 'RESULT_NETWORK_REJECT' => 'NETWORK_ERROR', 'UNKNOWN' => 'GENERIC_FAILURE'];

    private SmsSettings $settings;
    private SmsLogger $log;

    public function __construct(private readonly Database $db, ?SmsSettings $settings = null)
    {
        $this->settings = $settings ?? new SmsSettings($db);
        $this->log = new SmsLogger($db);
    }

    public function settings(): SmsSettings
    {
        return $this->settings;
    }

    /**
     * Adds one message. With a dedupe_key an identical event never queues twice: the existing
     * row's id is returned, and a cancelled row is revived only when $d['revive'] is set.
     *
     * @param array{phone_number:string,message:string,recipient_type?:string,recipient_id?:?int,recipient_name?:?string,
     *   message_type?:string,priority?:string,source?:string,template_id?:?int,batch_id?:?string,dedupe_key?:?string,
     *   scheduled_at?:?string,created_by?:?int,revive?:bool,skip_limits?:bool} $d
     * @return array{id:int,created:bool}
     */
    public function enqueue(int $institute, array $d, ?array $actor = null): array
    {
        $s = $this->settings->get($institute);
        if (!$s['sms_enabled']) {
            throw new DomainException('The SMS module is turned off in SMS Settings.');
        }
        $phone = PhoneNumber::normalize((string) $d['phone_number'], (string) $s['default_country_code']);
        $message = trim((string) $d['message']);
        if ($message === '') {
            throw new DomainException('Message text is required.');
        }
        if (mb_strlen($message) > SmsText::MAX_LENGTH) {
            throw new DomainException('Message is too long (maximum ' . SmsText::MAX_LENGTH . ' characters).');
        }
        if (preg_match('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', $message)) {
            throw new DomainException('The message still contains an unfilled {{variable}}.');
        }
        $priority = array_key_exists($d['priority'] ?? '', self::PRIORITIES) ? $d['priority'] : $s['default_priority'];
        $type = in_array($d['message_type'] ?? '', self::TYPES, true) ? $d['message_type'] : 'manual';
        $source = in_array($d['source'] ?? '', self::SOURCES, true) ? $d['source'] : 'manual';
        $recipientType = in_array($d['recipient_type'] ?? '', self::RECIPIENT_TYPES, true) ? $d['recipient_type'] : 'manual';
        $scheduled = null;
        if (!empty($d['scheduled_at'])) {
            $ts = strtotime((string) $d['scheduled_at']);
            if (!$ts) {
                throw new DomainException('Enter a valid schedule date and time.');
            }
            if ($ts <= time()) {
                throw new DomainException('The scheduled time must be in the future.');
            }
            $scheduled = date('Y-m-d H:i:s', $ts);
        }
        $dedupe = isset($d['dedupe_key']) && $d['dedupe_key'] !== '' ? mb_substr((string) $d['dedupe_key'], 0, 160) : null;
        if ($dedupe !== null) {
            $existing = $this->db->query('SELECT id,status FROM sms_messages WHERE institute_id=:i AND dedupe_key=:k', ['i' => $institute, 'k' => $dedupe])->fetch();
            if ($existing) {
                // A repeated event revives a cancelled alert, and also one that failed for good earlier
                // (no signal for too long, gateway timeout) — otherwise that parent is silently never told.
                if (in_array($existing['status'], ['cancelled', 'failed'], true) && !empty($d['revive'])) {
                    $this->db->query("UPDATE sms_messages SET status=:st,message=:m,phone_number=:p,scheduled_at=:sc,available_at=NULL,cancelled_at=NULL,failed_at=NULL,reserved_at=NULL,processing_at=NULL,failure_code=NULL,failure_reason=NULL,retry_count=0,gateway_device_id=NULL,updated_at=:u WHERE id=:id",
                        ['st' => $scheduled ? 'scheduled' : 'queued', 'm' => $message, 'p' => $phone, 'sc' => $scheduled, 'u' => date('Y-m-d H:i:s'), 'id' => $existing['id']]);
                    $this->log->log($institute, 'requeued', $actor ?? SmsLogger::system(), (int) $existing['id'], null, ['reason' => 'event repeated']);
                    return ['id' => (int) $existing['id'], 'created' => true];
                }
                return ['id' => (int) $existing['id'], 'created' => false];
            }
        }
        if (empty($d['skip_limits'])) {
            $this->assertWithinLimits($institute, 1);
        }
        $text = SmsText::analyse($message);
        $now = date('Y-m-d H:i:s');
        try {
            $this->db->query(
                'INSERT INTO sms_messages(institute_id,recipient_type,recipient_id,recipient_name,phone_number,message,message_type,priority,priority_rank,status,source,template_id,batch_id,dedupe_key,segments,is_unicode,scheduled_at,max_retries,created_by,created_at,updated_at)
                 VALUES(:i,:rt,:rid,:rn,:p,:m,:t,:pr,:rank,:st,:src,:tpl,:b,:k,:seg,:uni,:sc,:mr,:by,:c,:u)',
                ['i' => $institute, 'rt' => $recipientType, 'rid' => $d['recipient_id'] ?? null, 'rn' => isset($d['recipient_name']) ? mb_substr((string) $d['recipient_name'], 0, 160) : null,
                 'p' => $phone, 'm' => $message, 't' => $type, 'pr' => $priority, 'rank' => self::PRIORITIES[$priority], 'st' => $scheduled ? 'scheduled' : 'queued',
                 'src' => $source, 'tpl' => $d['template_id'] ?? null, 'b' => $d['batch_id'] ?? null, 'k' => $dedupe, 'seg' => max(1, min(255, $text['segments'])),
                 'uni' => (int) $text['unicode'], 'sc' => $scheduled, 'mr' => (int) $s['max_retries'], 'by' => $d['created_by'] ?? null, 'c' => $now, 'u' => $now]
            );
        } catch (\PDOException $e) {
            // Two requests raced on the same dedupe key: the other one won, which is the goal.
            if ($dedupe !== null && $e->getCode() === '23000') {
                $id = (int) $this->db->query('SELECT id FROM sms_messages WHERE institute_id=:i AND dedupe_key=:k', ['i' => $institute, 'k' => $dedupe])->fetchColumn();
                return ['id' => $id, 'created' => false];
            }
            throw $e;
        }
        $id = (int) $this->db->connection()->lastInsertId();
        $this->log->log($institute, $scheduled ? 'scheduled' : 'created', $actor ?? SmsLogger::system(), $id, null, ['type' => $type, 'source' => $source] + ($scheduled ? ['scheduled_at' => $scheduled] : []));
        return ['id' => $id, 'created' => true];
    }

    /** @throws DomainException when adding $count messages would pass the daily or monthly limit */
    public function assertWithinLimits(int $institute, int $count): void
    {
        $s = $this->settings->get($institute);
        $usage = $this->usage($institute);
        if ($s['daily_limit'] > 0 && $usage['today'] + $count > $s['daily_limit']) {
            throw new DomainException("Daily SMS limit reached ({$usage['today']} of {$s['daily_limit']} used today; {$count} more requested).");
        }
        if ($s['monthly_limit'] > 0 && $usage['month'] + $count > $s['monthly_limit']) {
            throw new DomainException("Monthly SMS limit reached ({$usage['month']} of {$s['monthly_limit']} used this month; {$count} more requested).");
        }
    }

    /** Messages counted against the limits: everything created and not cancelled. */
    public function usage(int $institute): array
    {
        $row = $this->db->query("SELECT SUM(created_at>=:d) today,COUNT(*) month FROM sms_messages WHERE institute_id=:i AND created_at>=:m AND status<>'cancelled'",
            ['i' => $institute, 'd' => date('Y-m-d 00:00:00'), 'm' => date('Y-m-01 00:00:00')])->fetch();
        return ['today' => (int) ($row['today'] ?? 0), 'month' => (int) ($row['month'] ?? 0)];
    }

    // ---- gateway side --------------------------------------------------------------------

    /**
     * Atomically hands up to $limit due messages to one device. Rows are locked with
     * FOR UPDATE SKIP LOCKED inside a transaction and claimed with a status-guarded UPDATE, so
     * two devices polling at once can never be given the same message.
     *
     * @return list<array{id:int,phone_number:string,message:string,priority:string,sim_slot:?int}>
     */
    public function reserve(array $device, int $limit): array
    {
        $institute = (int) $device['institute_id'];
        $s = $this->settings->get($institute);
        if (!$s['sms_enabled'] || !$s['queue_processing_enabled'] || $this->settings->inQuietHours($institute)) {
            return [];
        }
        $this->releaseStale($institute);
        $limit = max(1, min($limit, (int) $s['batch_size'], 100));
        $now = date('Y-m-d H:i:s');
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            $ids = $this->db->query(
                "SELECT id FROM sms_messages WHERE institute_id=:i AND status IN('queued','scheduled')
                 AND (scheduled_at IS NULL OR scheduled_at<=:n) AND (available_at IS NULL OR available_at<=:n2)
                 ORDER BY priority_rank,COALESCE(scheduled_at,created_at),id LIMIT {$limit} FOR UPDATE SKIP LOCKED",
                ['i' => $institute, 'n' => $now, 'n2' => $now]
            )->fetchAll(\PDO::FETCH_COLUMN);
            if (!$ids) {
                $pdo->commit();
                return [];
            }
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE sms_messages SET status='reserved',gateway_device_id=?,reserved_at=?,updated_at=? WHERE id IN ($marks) AND status IN('queued','scheduled')");
            $stmt->execute(array_merge([(int) $device['id'], $now, $now], $ids));
            $rows = $pdo->prepare("SELECT id,phone_number,message,priority FROM sms_messages WHERE id IN ($marks) AND status='reserved' AND gateway_device_id=? ORDER BY priority_rank,id");
            $rows->execute(array_merge($ids, [(int) $device['id']]));
            $claimed = $rows->fetchAll();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $sim = (int) ($device['sim_slot'] ?? 0) ?: ((int) $s['default_sim_slot'] ?: null);
        foreach ($claimed as $m) {
            $this->log->log($institute, 'reserved', SmsLogger::device($device), (int) $m['id'], (int) $device['id']);
        }
        return array_map(static fn($m) => ['id' => (int) $m['id'], 'phone_number' => $m['phone_number'], 'message' => $m['message'], 'priority' => $m['priority'], 'sim_slot' => $sim], $claimed);
    }

    /**
     * A device that reserved messages and went silent must not strand them. Reserved-but-never-
     * started rows go back to the queue. Rows stuck in 'processing' may already have gone out,
     * so they are failed (not resent) and left for an admin to retry, to avoid double SMS.
     */
    public function releaseStale(int $institute): void
    {
        $cut = date('Y-m-d H:i:s', time() - 60 * (int) $this->settings->get($institute)['reservation_timeout_minutes']);
        $now = date('Y-m-d H:i:s');
        $released = $this->db->query("SELECT id,gateway_device_id FROM sms_messages WHERE institute_id=:i AND status='reserved' AND reserved_at<:c", ['i' => $institute, 'c' => $cut])->fetchAll();
        foreach ($released as $r) {
            $n = $this->db->query("UPDATE sms_messages SET status='queued',gateway_device_id=NULL,reserved_at=NULL,updated_at=:u WHERE id=:id AND status='reserved'", ['u' => $now, 'id' => $r['id']])->rowCount();
            if ($n) {
                $this->log->log($institute, 'reservation_expired', SmsLogger::system(), (int) $r['id'], $r['gateway_device_id'] ? (int) $r['gateway_device_id'] : null);
            }
        }
        $stuck = $this->db->query("SELECT id,gateway_device_id FROM sms_messages WHERE institute_id=:i AND status='processing' AND processing_at<:c", ['i' => $institute, 'c' => $cut])->fetchAll();
        foreach ($stuck as $r) {
            $n = $this->db->query("UPDATE sms_messages SET status='failed',failed_at=:u,failure_code='GATEWAY_TIMEOUT',failure_reason='The gateway did not confirm this message in time. It may or may not have been delivered.',updated_at=:u2 WHERE id=:id AND status='processing'",
                ['u' => $now, 'u2' => $now, 'id' => $r['id']])->rowCount();
            if ($n) {
                $this->log->log($institute, 'failed', SmsLogger::system(), (int) $r['id'], $r['gateway_device_id'] ? (int) $r['gateway_device_id'] : null, ['code' => 'GATEWAY_TIMEOUT']);
            }
        }
    }

    /** The message only if it is currently held by this device. */
    private function held(array $device, int $id, array $statuses): array
    {
        $marks = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $this->db->connection()->prepare("SELECT * FROM sms_messages WHERE id=? AND institute_id=? AND gateway_device_id=? AND status IN ($marks)");
        $stmt->execute(array_merge([$id, (int) $device['institute_id'], (int) $device['id']], $statuses));
        $row = $stmt->fetch();
        if (!$row) {
            throw new GatewayException('MESSAGE_NOT_ASSIGNED', 'This message is not assigned to this device, or is already finished.', 409);
        }
        return $row;
    }

    public function markProcessing(array $device, int $id): void
    {
        $this->held($device, $id, ['reserved', 'processing']);
        $now = date('Y-m-d H:i:s');
        $this->db->query("UPDATE sms_messages SET status='processing',processing_at=COALESCE(processing_at,:n),updated_at=:u WHERE id=:id AND status IN('reserved','processing')", ['n' => $now, 'u' => $now, 'id' => $id]);
        $this->log->log((int) $device['institute_id'], 'processing', SmsLogger::device($device), $id, (int) $device['id']);
    }

    public function markSent(array $device, int $id, array $data = []): void
    {
        $m = $this->held($device, $id, ['reserved', 'processing', 'sent']);
        if ($m['status'] === 'sent') {
            return; // A repeated confirmation (e.g. after a network retry) is harmless.
        }
        $sentAt = !empty($data['sent_at']) && strtotime((string) $data['sent_at']) ? date('Y-m-d H:i:s', strtotime((string) $data['sent_at'])) : date('Y-m-d H:i:s');
        if ($sentAt > date('Y-m-d H:i:s', time() + 300)) {
            $sentAt = date('Y-m-d H:i:s');
        }
        $sim = isset($data['sim_slot']) && is_numeric($data['sim_slot']) ? max(0, min(9, (int) $data['sim_slot'])) : null;
        $ref = isset($data['android_message_reference']) ? mb_substr(trim((string) $data['android_message_reference']), 0, 120) : null;
        $this->db->query("UPDATE sms_messages SET status='sent',sent_at=:s,sim_slot=:sim,external_reference=:r,failure_code=NULL,failure_reason=NULL,updated_at=:u WHERE id=:id AND status IN('reserved','processing')",
            ['s' => $sentAt, 'sim' => $sim, 'r' => $ref ?: null, 'u' => date('Y-m-d H:i:s'), 'id' => $id]);
        $this->log->log((int) $device['institute_id'], 'sent', SmsLogger::device($device), $id, (int) $device['id'], array_filter(['sim_slot' => $sim, 'reference' => $ref]));
    }

    /** @return string the resulting status: 'queued' when it will be retried, otherwise 'failed' */
    public function markFailed(array $device, int $id, string $code, string $reason): string
    {
        $m = $this->held($device, $id, ['reserved', 'processing']);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $code) ?: 'UNKNOWN');
        $code = self::ANDROID_CODES[$code] ?? $code;
        $code = substr($code, 0, 40);
        $reason = mb_substr(trim($reason) ?: 'The gateway reported a failure.', 0, 255);
        $attempts = (int) $m['retry_count'] + 1;
        $retry = in_array($code, self::RETRYABLE_ERRORS, true) && $attempts <= (int) $m['max_retries'];
        $now = date('Y-m-d H:i:s');
        $institute = (int) $device['institute_id'];
        if ($retry) {
            $delay = 60 * (int) $this->settings->get($institute)['retry_delay_minutes'] * $attempts;
            $this->db->query("UPDATE sms_messages SET status='queued',retry_count=:a,available_at=:av,gateway_device_id=NULL,reserved_at=NULL,processing_at=NULL,failure_code=:c,failure_reason=:r,updated_at=:u WHERE id=:id",
                ['a' => $attempts, 'av' => date('Y-m-d H:i:s', time() + $delay), 'c' => $code, 'r' => $reason, 'u' => $now, 'id' => $id]);
            $this->log->log($institute, 'retry_scheduled', SmsLogger::device($device), $id, (int) $device['id'], ['code' => $code, 'attempt' => $attempts]);
            return 'queued';
        }
        $this->db->query("UPDATE sms_messages SET status='failed',retry_count=:a,failed_at=:f,failure_code=:c,failure_reason=:r,updated_at=:u WHERE id=:id",
            ['a' => $attempts, 'f' => $now, 'c' => $code, 'r' => $reason, 'u' => $now, 'id' => $id]);
        $this->log->log($institute, 'failed', SmsLogger::device($device), $id, (int) $device['id'], ['code' => $code, 'attempt' => $attempts]);
        return 'failed';
    }

    // ---- admin side ----------------------------------------------------------------------

    public function find(int $institute, int $id): ?array
    {
        $row = $this->db->query('SELECT m.*,d.device_name,t.name template_name,u.name created_by_name FROM sms_messages m LEFT JOIN sms_gateway_devices d ON d.id=m.gateway_device_id LEFT JOIN sms_templates t ON t.id=m.template_id LEFT JOIN users u ON u.id=m.created_by WHERE m.id=:id AND m.institute_id=:i',
            ['id' => $id, 'i' => $institute])->fetch();
        return $row ?: null;
    }

    public function cancel(int $institute, int $id, ?array $actor = null): void
    {
        $n = $this->db->query("UPDATE sms_messages SET status='cancelled',cancelled_at=:n,updated_at=:u WHERE id=:id AND institute_id=:i AND status IN('queued','scheduled','failed')",
            ['n' => date('Y-m-d H:i:s'), 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute])->rowCount();
        if (!$n) {
            throw new DomainException('Only queued, scheduled or failed messages can be cancelled; a gateway may already be sending this one.');
        }
        $this->log->log($institute, 'cancelled', $actor ?? SmsLogger::user(), $id);
    }

    /** Puts a failed or cancelled message back in the queue with a fresh attempt budget. */
    public function retry(int $institute, int $id, ?array $actor = null): void
    {
        $this->assertWithinLimits($institute, 1);
        $n = $this->db->query("UPDATE sms_messages SET status='queued',retry_count=0,available_at=NULL,scheduled_at=IF(scheduled_at>:now,scheduled_at,NULL),gateway_device_id=NULL,reserved_at=NULL,processing_at=NULL,failed_at=NULL,cancelled_at=NULL,failure_code=NULL,failure_reason=NULL,updated_at=:u WHERE id=:id AND institute_id=:i AND status IN('failed','cancelled')",
            ['u' => date('Y-m-d H:i:s'), 'now' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute])->rowCount();
        if (!$n) {
            throw new DomainException('Only failed or cancelled messages can be retried.');
        }
        $this->log->log($institute, 'retried', $actor ?? SmsLogger::user(), $id);
    }

    /** Reschedules a scheduled message or edits its text before any gateway has it. */
    public function updateScheduled(int $institute, int $id, string $message, string $when, ?array $actor = null): void
    {
        $ts = strtotime($when);
        if (!$ts || $ts <= time()) {
            throw new DomainException('The scheduled time must be in the future.');
        }
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > SmsText::MAX_LENGTH || preg_match('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', $message)) {
            throw new DomainException('Enter a message without unfilled variables (up to ' . SmsText::MAX_LENGTH . ' characters).');
        }
        $t = SmsText::analyse($message);
        $n = $this->db->query("UPDATE sms_messages SET message=:m,segments=:s,is_unicode=:uc,scheduled_at=:sc,updated_at=:u WHERE id=:id AND institute_id=:i AND status='scheduled'",
            ['m' => $message, 's' => max(1, $t['segments']), 'uc' => (int) $t['unicode'], 'sc' => date('Y-m-d H:i:s', $ts), 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute])->rowCount();
        if (!$n) {
            throw new DomainException('Only messages still waiting for their scheduled time can be edited.');
        }
        $this->log->log($institute, 'rescheduled', $actor ?? SmsLogger::user(), $id, null, ['scheduled_at' => date('Y-m-d H:i:s', $ts)]);
    }

    /** Sent messages are the delivery record and in-flight ones belong to a device, so neither can be deleted. */
    public function delete(int $institute, int $id, ?array $actor = null): void
    {
        $n = $this->db->query("DELETE FROM sms_messages WHERE id=:id AND institute_id=:i AND status IN('queued','scheduled','failed','cancelled')", ['id' => $id, 'i' => $institute])->rowCount();
        if (!$n) {
            throw new DomainException('Sent messages and messages being sent cannot be deleted.');
        }
        $this->log->log($institute, 'deleted', $actor ?? SmsLogger::user(), $id);
    }

    /** Cancels unsent messages for a dedupe key, e.g. an absence alert after attendance is corrected. */
    public function cancelByKey(int $institute, string $key, string $reason): bool
    {
        $row = $this->db->query("SELECT id FROM sms_messages WHERE institute_id=:i AND dedupe_key=:k AND status IN('queued','scheduled')", ['i' => $institute, 'k' => $key])->fetch();
        if (!$row) {
            return false;
        }
        $n = $this->db->query("UPDATE sms_messages SET status='cancelled',cancelled_at=:n,updated_at=:u WHERE id=:id AND status IN('queued','scheduled')", ['n' => date('Y-m-d H:i:s'), 'u' => date('Y-m-d H:i:s'), 'id' => $row['id']])->rowCount();
        if ($n) {
            $this->log->log($institute, 'cancelled', SmsLogger::system(), (int) $row['id'], null, ['reason' => $reason]);
        }
        return (bool) $n;
    }

    /**
     * @param array{status?:string,statuses?:list<string>,type?:string,source?:string,device?:int,q?:string,from?:string,to?:string,order?:string} $f
     * @return array{rows:list<array>,total:int}
     */
    public function search(int $institute, array $f, int $page = 1, int $perPage = 25): array
    {
        $where = ['m.institute_id=?'];
        $args = [$institute];
        $statuses = $f['statuses'] ?? (!empty($f['status']) ? [$f['status']] : []);
        $statuses = array_values(array_intersect($statuses, self::STATUSES));
        if ($statuses) {
            $where[] = 'm.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
            array_push($args, ...$statuses);
        }
        if (!empty($f['type']) && in_array($f['type'], self::TYPES, true)) {
            $where[] = 'm.message_type=?';
            $args[] = $f['type'];
        }
        if (!empty($f['source']) && in_array($f['source'], self::SOURCES, true)) {
            $where[] = 'm.source=?';
            $args[] = $f['source'];
        }
        if (!empty($f['device'])) {
            $where[] = 'm.gateway_device_id=?';
            $args[] = (int) $f['device'];
        }
        if (!empty($f['q'])) {
            $where[] = '(m.recipient_name LIKE ? OR m.phone_number LIKE ? OR m.message LIKE ?)';
            $like = '%' . mb_substr(trim((string) $f['q']), 0, 60) . '%';
            array_push($args, $like, $like, $like);
        }
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            if (!empty($f[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f[$k])) {
                $where[] = "m.created_at {$op} ?";
                $args[] = $f[$k] . ($k === 'from' ? ' 00:00:00' : ' 23:59:59');
            }
        }
        $sql = ' FROM sms_messages m LEFT JOIN sms_gateway_devices d ON d.id=m.gateway_device_id LEFT JOIN users u ON u.id=m.created_by WHERE ' . implode(' AND ', $where);
        $pdo = $this->db->connection();
        $count = $pdo->prepare('SELECT COUNT(*)' . $sql);
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $order = match ($f['order'] ?? '') {
            'sent' => 'm.sent_at DESC,m.id DESC',
            'failed' => 'm.failed_at DESC,m.id DESC',
            'scheduled' => 'm.scheduled_at ASC,m.id ASC',
            default => 'm.id DESC',
        };
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT m.*,d.device_name,u.name created_by_name{$sql} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($args);
        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public function stats(int $institute): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) total,SUM(status='queued') queued,SUM(status IN('reserved','processing')) in_flight,SUM(status='sent') sent,SUM(status='failed') failed,SUM(status='scheduled') scheduled,
             SUM(created_at>=:d) today,SUM(created_at>=:m) month FROM sms_messages WHERE institute_id=:i",
            ['i' => $institute, 'd' => date('Y-m-d 00:00:00'), 'm' => date('Y-m-01 00:00:00')]
        )->fetch() ?: [];
        return array_map('intval', $row);
    }
}
