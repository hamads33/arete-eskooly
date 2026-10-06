<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;
use DomainException;

/**
 * SMS Center configuration, stored in the existing per-institute sms_settings row
 * (provider = 'android_gateway', options in configuration_json).
 */
final class SmsSettings
{
    public const PROVIDER = 'android_gateway';

    public const DEFAULTS = [
        'sms_enabled' => true,
        'default_country_code' => '92',
        'queue_processing_enabled' => true,
        'batch_size' => 10,
        'max_retries' => 3,
        'retry_delay_minutes' => 5,
        'default_priority' => 'normal',
        'daily_limit' => 0,
        'monthly_limit' => 0,
        'quiet_hours_enabled' => false,
        'quiet_hours_start' => '21:00',
        'quiet_hours_end' => '07:00',
        'allow_manual_sms' => true,
        'allow_bulk_sms' => true,
        'attendance_sms_enabled' => true,
        'fee_sms_enabled' => true,
        'result_sms_enabled' => true,
        'announcement_sms_enabled' => true,
        'offline_timeout_minutes' => 5,
        'heartbeat_interval_seconds' => 60,
        'reservation_timeout_minutes' => 10,
        'default_device_id' => 0,
        'default_sim_slot' => 0,
    ];

    private array $cache = [];

    public function __construct(private readonly Database $db) {}

    public function get(int $institute): array
    {
        if (isset($this->cache[$institute])) {
            return $this->cache[$institute];
        }
        $row = $this->db->query('SELECT provider,enabled,configuration_json FROM sms_settings WHERE institute_id=:i', ['i' => $institute])->fetch();
        $stored = [];
        if ($row && $row['provider'] === self::PROVIDER) {
            $stored = json_decode((string) $row['configuration_json'], true) ?: [];
            $stored['sms_enabled'] = (bool) $row['enabled'];
        }
        $settings = array_replace(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        return $this->cache[$institute] = $settings;
    }

    /** Validates and saves; returns the keys whose values changed (for the audit log). */
    public function save(int $institute, array $input, int $user): array
    {
        $old = $this->get($institute);
        $new = $old;
        foreach (self::DEFAULTS as $key => $default) {
            if (is_bool($default)) {
                $new[$key] = !empty($input[$key]);
            } elseif (array_key_exists($key, $input)) {
                $new[$key] = is_int($default) ? (int) $input[$key] : trim((string) $input[$key]);
            }
        }
        $new['default_country_code'] = preg_replace('/\D/', '', (string) $new['default_country_code']) ?? '';
        if ($new['default_country_code'] === '' || strlen($new['default_country_code']) > 4) {
            throw new DomainException('Enter a valid default country code, e.g. 92.');
        }
        foreach (['quiet_hours_start', 'quiet_hours_end'] as $k) {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $new[$k])) {
                throw new DomainException('Quiet hours must use HH:MM (24-hour) times.');
            }
        }
        if (!in_array($new['default_priority'], ['high', 'normal', 'low'], true)) {
            $new['default_priority'] = 'normal';
        }
        $bounds = ['batch_size' => [1, 100], 'max_retries' => [0, 10], 'retry_delay_minutes' => [1, 1440], 'daily_limit' => [0, 1000000],
            'monthly_limit' => [0, 10000000], 'offline_timeout_minutes' => [1, 1440], 'heartbeat_interval_seconds' => [15, 3600],
            'reservation_timeout_minutes' => [2, 240], 'default_sim_slot' => [0, 4], 'default_device_id' => [0, PHP_INT_MAX]];
        foreach ($bounds as $k => [$min, $max]) {
            if ($new[$k] < $min || $new[$k] > $max) {
                throw new DomainException(ucfirst(str_replace('_', ' ', $k)) . " must be between {$min} and {$max}.");
            }
        }
        if ($new['default_device_id'] && !$this->db->query('SELECT 1 FROM sms_gateway_devices WHERE id=:d AND institute_id=:i', ['d' => $new['default_device_id'], 'i' => $institute])->fetchColumn()) {
            throw new DomainException('Select a valid default device.');
        }
        $config = $new;
        unset($config['sms_enabled']);
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'INSERT INTO sms_settings(institute_id,provider,enabled,configuration_json,updated_by,created_at,updated_at) VALUES(:i,:p,:e,:j,:u,:n,:n2)
             ON DUPLICATE KEY UPDATE provider=VALUES(provider),enabled=VALUES(enabled),configuration_json=VALUES(configuration_json),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',
            ['i' => $institute, 'p' => self::PROVIDER, 'e' => (int) $new['sms_enabled'], 'j' => json_encode($config, JSON_THROW_ON_ERROR), 'u' => $user, 'n' => $now, 'n2' => $now]
        );
        unset($this->cache[$institute]);
        return array_keys(array_filter($new, static fn($v, $k) => $old[$k] !== $v, ARRAY_FILTER_USE_BOTH));
    }

    /** Whether $time (default now) falls inside the configured quiet hours; handles windows past midnight. */
    public function inQuietHours(int $institute, ?int $time = null): bool
    {
        $s = $this->get($institute);
        if (!$s['quiet_hours_enabled']) {
            return false;
        }
        $now = date('H:i', $time ?? time());
        [$start, $end] = [$s['quiet_hours_start'], $s['quiet_hours_end']];
        return $start <= $end ? ($now >= $start && $now < $end) : ($now >= $start || $now < $end);
    }
}
