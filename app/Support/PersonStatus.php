<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * What an inactive or suspended student / employee still appears in — decided by the admin
 * (General Settings → Inactive & Suspended Rules) — and the date-aware visibility used by
 * registers and reports: a person always appears for any date or period that began before their
 * status changed, so deactivating someone never hides the history they already have.
 */
final class PersonStatus
{
    public const STATUSES = ['active', 'inactive', 'suspended'];
    public const TYPES = ['student', 'employee'];
    public const ACTIONS = ['attendance', 'billing', 'messages', 'login'];

    /** Defaults keep today's behaviour for inactive people; a suspension keeps them on the books but blocks sign-in. */
    public const DEFAULTS = [
        'student' => [
            'inactive' => ['attendance' => false, 'billing' => false, 'messages' => false, 'login' => true],
            'suspended' => ['attendance' => true, 'billing' => true, 'messages' => true, 'login' => false],
        ],
        'employee' => [
            'inactive' => ['attendance' => false, 'billing' => false, 'messages' => false, 'login' => true],
            'suspended' => ['attendance' => true, 'billing' => true, 'messages' => true, 'login' => false],
        ],
    ];

    /** @var array<int,array> */
    private static array $cache = [];

    /** @return array{student:array,employee:array} rules[type][status][action] => bool */
    public static function rules(Database $db, int $institute): array
    {
        if (isset(self::$cache[$institute])) return self::$cache[$institute];
        $rules = self::DEFAULTS;
        try {
            foreach ($db->query('SELECT * FROM person_status_rules WHERE institute_id=:i', ['i' => $institute])->fetchAll() as $r) {
                $rules[$r['person_type']][$r['status']] = ['attendance' => (bool) $r['in_attendance'], 'billing' => (bool) $r['in_billing'], 'messages' => (bool) $r['in_messages'], 'login' => (bool) $r['can_login']];
            }
        } catch (\PDOException) {
            // Table not migrated yet: defaults apply.
        }
        return self::$cache[$institute] = $rules;
    }

    public static function saveRules(Database $db, int $institute, array $input, int $user): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (self::TYPES as $type) foreach (['inactive', 'suspended'] as $status) {
            $v = static fn(string $a): int => !empty($input[$type][$status][$a]) ? 1 : 0;
            $db->query('INSERT INTO person_status_rules(institute_id,person_type,status,in_attendance,in_billing,in_messages,can_login,updated_by,updated_at) VALUES(:i,:t,:s,:a,:b,:m,:l,:u,:n)
                ON DUPLICATE KEY UPDATE in_attendance=VALUES(in_attendance),in_billing=VALUES(in_billing),in_messages=VALUES(in_messages),can_login=VALUES(can_login),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',
                ['i' => $institute, 't' => $type, 's' => $status, 'a' => $v('attendance'), 'b' => $v('billing'), 'm' => $v('messages'), 'l' => $v('login'), 'u' => $user, 'n' => $now]);
        }
        unset(self::$cache[$institute]);
    }

    /** Non-active statuses the admin keeps in $action (e.g. ['suspended'] for attendance). */
    public static function keptStatuses(Database $db, int $institute, string $type, string $action): array
    {
        $rules = self::rules($db, $institute)[$type];
        return array_values(array_filter(['inactive', 'suspended'], static fn($s) => !empty($rules[$s][$action])));
    }

    /**
     * SQL condition: the person (table alias $a) belongs in $action for a date / period that starts
     * at the bound parameter $since ('Y-m-d H:i:s'): active now, kept by the admin's rule, or their
     * status changed after the period started (so they were still active then).
     * Status values come from a fixed list, never from input.
     */
    public static function visibleSql(Database $db, int $institute, string $type, string $a, string $action, string $since): string
    {
        $kept = self::keptStatuses($db, $institute, $type, $action);
        $or = $kept ? " OR {$a}.status IN ('" . implode("','", $kept) . "')" : '';
        return "({$a}.status='active'{$or} OR ({$a}.status_changed_at IS NOT NULL AND {$a}.status_changed_at>{$since}))";
    }

    /** SQL condition without a date: active, or a status the admin keeps in $action. */
    public static function currentSql(Database $db, int $institute, string $type, string $a, string $action): string
    {
        $kept = self::keptStatuses($db, $institute, $type, $action);
        return $kept ? "{$a}.status IN ('active','" . implode("','", $kept) . "')" : "{$a}.status='active'";
    }

    /**
     * Changes the status of one or more students / employees, recording when it takes effect,
     * why, and who did it. Returns how many records actually changed.
     */
    public static function change(Database $db, int $institute, string $type, array $ids, string $status, ?string $reason, ?string $effectiveAt, ?int $user): int
    {
        if (!in_array($type, self::TYPES, true) || !in_array($status, self::STATUSES, true)) throw new \InvalidArgumentException('Invalid status.');
        $table = $type === 'student' ? 'students' : 'employees';
        $now = date('Y-m-d H:i:s');
        $when = $effectiveAt !== null && $effectiveAt !== '' ? date('Y-m-d H:i:s', (int) strtotime($effectiveAt)) : $now;
        if ($when > $now) throw new \InvalidArgumentException('The effective date cannot be in the future.');
        $reason = $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $changed = 0;
        $pdo = $db->connection();
        $pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $row = $db->query("SELECT id,status FROM {$table} WHERE id=:id AND institute_id=:i FOR UPDATE", ['id' => $id, 'i' => $institute])->fetch();
                if (!$row || $row['status'] === $status) continue;
                $db->query("UPDATE {$table} SET status=:s,status_changed_at=:w,status_reason=:r,updated_at=:u WHERE id=:id", ['s' => $status, 'w' => $when, 'r' => $reason, 'u' => $now, 'id' => $id]);
                $db->query('INSERT INTO person_status_history(institute_id,person_type,person_id,old_status,new_status,effective_at,reason,changed_by,created_at) VALUES(:i,:t,:p,:o,:n,:w,:r,:b,:c)',
                    ['i' => $institute, 't' => $type, 'p' => $id, 'o' => $row['status'], 'n' => $status, 'w' => $when, 'r' => $reason, 'b' => $user, 'c' => $now]);
                $changed++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return $changed;
    }

    public static function history(Database $db, string $type, int $id): array
    {
        return $db->query('SELECT h.*,u.name changed_by_name FROM person_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.person_type=:t AND h.person_id=:p ORDER BY h.effective_at DESC,h.id DESC', ['t' => $type, 'p' => $id])->fetchAll();
    }

    /** False when the signed-in user is a student / employee whose status the admin has set to block sign-in. */
    public static function loginAllowed(Database $db, int $userId): bool
    {
        try {
            foreach ([['student', 'student_accounts', 'students', 'student_id'], ['employee', 'employee_accounts', 'employees', 'employee_id']] as [$type, $link, $table, $key]) {
                $p = $db->query("SELECT x.status,x.institute_id FROM {$link} l JOIN {$table} x ON x.id=l.{$key} WHERE l.user_id=:u LIMIT 1", ['u' => $userId])->fetch();
                if ($p && $p['status'] !== 'active' && empty(self::rules($db, (int) $p['institute_id'])[$type][$p['status']]['login'])) return false;
            }
        } catch (\PDOException) {
            return true;
        }
        return true;
    }

    public static function label(string $status): string
    {
        return ['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'][$status] ?? ucfirst($status);
    }
}
