<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DomainException;

/**
 * Login access levels for portal accounts (students, employees, parents).
 *
 *   active    normal use
 *   suspended can sign in, but every page is replaced by a notice (students: fee amount due)
 *   disabled  blocked: sign-in is refused
 *
 * Students can also be restricted automatically by the unpaid-fee rule in Fee Automation:
 * suspended after N unpaid months, blocked after M. Automatic restrictions carry
 * access_source='fee' and are lifted again automatically once the dues are cleared. Anything an
 * admin sets is 'manual' and the automatic rule never overrides it, until the admin hands the
 * account back to the fee rules.
 */
final class AccountAccess
{
    public const TYPES = ['student' => ['students', 'student_accounts', 'student_id'], 'employee' => ['employees', 'employee_accounts', 'employee_id'], 'guardian' => ['guardians', 'guardian_accounts', 'guardian_id']];
    public const LEVELS = ['active', 'suspended', 'disabled'];

    public function __construct(private readonly Database $db) {}

    /** The login user id for a record, scoped to the institute. */
    public function userFor(string $type, int $recordId, int $institute): int
    {
        if (!isset(self::TYPES[$type])) {
            throw new DomainException('Invalid account type.');
        }
        [$table, $link, $column] = self::TYPES[$type];
        $user = (int) $this->db->query("SELECT a.user_id FROM {$link} a JOIN {$table} d ON d.id=a.{$column} WHERE a.{$column}=:id AND d.institute_id=:i", ['id' => $recordId, 'i' => $institute])->fetchColumn();
        if ($user <= 0) {
            throw new DomainException('This record has no login.');
        }
        return $user;
    }

    /** Admin action. $source null hands a student back to the automatic fee rules. */
    public function setManual(string $type, int $recordId, int $institute, string $level, string $reason = '', ?string $source = 'manual'): void
    {
        if (!in_array($level, self::LEVELS, true)) {
            throw new DomainException('Invalid access level.');
        }
        $user = $this->userFor($type, $recordId, $institute);
        $this->apply($user, $level, $source, trim($reason) ?: null);
        if ($source === null && $type === 'student') {
            $this->evaluateStudent($recordId, $institute);
        }
    }

    private function apply(int $user, string $level, ?string $source, ?string $reason): void
    {
        $this->db->query('UPDATE users SET status=:s,access_source=:src,access_reason=:r,access_changed_at=:n,updated_at=:u WHERE id=:id',
            ['s' => $level, 'src' => $level === 'active' && $source === 'fee' ? null : $source, 'r' => $reason ? mb_substr($reason, 0, 255) : null, 'n' => date('Y-m-d H:i:s'), 'u' => date('Y-m-d H:i:s'), 'id' => $user]);
        if ($level === 'disabled') {
            // "Remember me" must not keep a blocked account signed in.
            $this->db->query('DELETE FROM remember_tokens WHERE user_id=:id', ['id' => $user]);
        }
    }

    public function feeRule(int $institute): array
    {
        $row = $this->db->query('SELECT suspend_after_months,block_after_months FROM fee_settings WHERE institute_id=:i', ['i' => $institute])->fetch() ?: [];
        return ['suspend' => (int) ($row['suspend_after_months'] ?? 0), 'block' => (int) ($row['block_after_months'] ?? 0)];
    }

    /**
     * Consecutive unpaid months, counted back from the latest overdue invoice to the last paid one.
     * An invoice is overdue once its due date has passed (or, without a due date, once its month is
     * over). Carried-forward invoices count as unpaid: their balance moved to a later invoice.
     */
    public function unpaidMonths(int $studentId): int
    {
        $rows = $this->db->query("SELECT status FROM fee_invoices WHERE student_id=:s AND voided_at IS NULL
            AND ((due_date IS NOT NULL AND due_date<:today) OR (due_date IS NULL AND fee_month<:month)) ORDER BY fee_month DESC",
            ['s' => $studentId, 'today' => date('Y-m-d'), 'month' => date('Y-m')])->fetchAll(\PDO::FETCH_COLUMN);
        $months = 0;
        foreach ($rows as $status) {
            if ($status === 'paid') {
                break;
            }
            $months++;
        }
        return $months;
    }

    public function outstanding(int $studentId): float
    {
        return (float) $this->db->query('SELECT COALESCE(SUM(remaining_amount),0) FROM fee_invoices WHERE student_id=:s AND voided_at IS NULL AND remaining_amount>0', ['s' => $studentId])->fetchColumn();
    }

    /**
     * Applies the automatic fee rule to one student's login. Returns the level now in force,
     * or null when the student has no login or an admin decision takes precedence.
     */
    public function evaluateStudent(int $studentId, int $institute): ?string
    {
        $user = $this->db->query('SELECT u.id,u.status,u.access_source FROM student_accounts sa JOIN users u ON u.id=sa.user_id JOIN students s ON s.id=sa.student_id WHERE sa.student_id=:s AND s.institute_id=:i',
            ['s' => $studentId, 'i' => $institute])->fetch();
        if (!$user || $user['access_source'] === 'manual') {
            return null;
        }
        $rule = $this->feeRule($institute);
        $months = ($rule['suspend'] || $rule['block']) ? $this->unpaidMonths($studentId) : 0;
        $target = 'active';
        if ($rule['block'] && $months >= $rule['block']) {
            $target = 'disabled';
        } elseif ($rule['suspend'] && $months >= $rule['suspend']) {
            $target = 'suspended';
        }
        // A student made inactive/blocked by some other means (no source) is only ever tightened, never loosened, by the fee rule.
        $rank = ['active' => 0, 'suspended' => 1, 'disabled' => 2];
        if ($user['access_source'] === null && $user['status'] !== 'active' && $rank[$target] <= $rank[$user['status']]) {
            return $user['status'];
        }
        if ($target !== $user['status'] || ($target !== 'active' && $user['access_source'] !== 'fee')) {
            $this->apply((int) $user['id'], $target, $target === 'active' ? null : 'fee', $target === 'active' ? null : "Fees unpaid for {$months} month" . ($months === 1 ? '' : 's'));
        }
        return $target;
    }

    /** @return array{suspended:int,blocked:int,restored:int} */
    public function evaluateInstitute(int $institute): array
    {
        $out = ['suspended' => 0, 'blocked' => 0, 'restored' => 0];
        $students = $this->db->query("SELECT sa.student_id,u.status FROM student_accounts sa JOIN users u ON u.id=sa.user_id JOIN students s ON s.id=sa.student_id WHERE s.institute_id=:i AND (u.access_source IS NULL OR u.access_source='fee')",
            ['i' => $institute])->fetchAll();
        foreach ($students as $s) {
            $after = $this->evaluateStudent((int) $s['student_id'], $institute);
            if ($after !== null && $after !== $s['status']) {
                $out[$after === 'disabled' ? 'blocked' : ($after === 'suspended' ? 'suspended' : 'restored')]++;
            }
        }
        return $out;
    }

    /** Re-checks the student behind a login, e.g. at sign-in, so paying or falling behind takes effect at once. */
    public function evaluateUser(int $userId): void
    {
        $row = $this->db->query('SELECT sa.student_id,s.institute_id FROM student_accounts sa JOIN students s ON s.id=sa.student_id WHERE sa.user_id=:u', ['u' => $userId])->fetch();
        if ($row) {
            $this->evaluateStudent((int) $row['student_id'], (int) $row['institute_id']);
        }
    }

    /** What a suspended user is shown in place of every page. */
    public function notice(int $userId): array
    {
        $user = $this->db->query('SELECT name,access_source,access_reason FROM users WHERE id=:id', ['id' => $userId])->fetch() ?: [];
        $student = $this->db->query('SELECT s.id,s.student_name,s.institute_id FROM student_accounts sa JOIN students s ON s.id=sa.student_id WHERE sa.user_id=:u', ['u' => $userId])->fetch();
        $notice = ['name' => $user['name'] ?? '', 'reason' => $user['access_reason'] ?? null, 'fee' => false, 'outstanding' => 0.0, 'months' => 0, 'currency' => 'Rs', 'student_id' => null];
        if ($student) {
            $currency = $this->db->query('SELECT currency_symbol FROM institute_preferences WHERE institute_id=:i', ['i' => $student['institute_id']])->fetchColumn();
            $notice['student_id'] = (int) $student['id'];
            $notice['outstanding'] = $this->outstanding((int) $student['id']);
            $notice['months'] = $this->unpaidMonths((int) $student['id']);
            $notice['currency'] = $currency ?: 'Rs';
            $notice['fee'] = $user['access_source'] === 'fee' || $notice['outstanding'] > 0;
        }
        return $notice;
    }
}
