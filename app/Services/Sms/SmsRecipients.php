<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;
use DomainException;

/**
 * Finds SMS recipients in existing LMS records and the values their templates may use.
 *
 * Recipient kinds:
 *  - student  (recipient_id = students.id, phone = students.mobile)
 *  - guardian (recipient_id = students.id: the parent *of that student*; phone from the primary
 *             linked guardian, else the student's guardian / father / mother mobile)
 *  - staff    (recipient_id = employees.id, phone = employees.mobile)
 *  - manual   (a typed number; only institute-level variables resolve)
 */
final class SmsRecipients
{
    /** Bulk filters offered on the Bulk SMS page; every one maps to data the LMS really has. */
    public const FILTERS = [
        'all' => 'Everyone in the audience',
        'class' => 'A class',
        'section' => 'A section',
        'fee_outstanding' => 'Students with unpaid fees',
        'attendance' => 'Attendance status on a date',
        'exam' => 'Students with results in an exam',
    ];

    private array $instituteCache = [];

    public function __construct(private readonly Database $db, private readonly SmsSettings $settings) {}

    public function instituteVars(int $institute): array
    {
        if (!isset($this->instituteCache[$institute])) {
            $i = $this->db->query('SELECT i.name,i.phone,p.currency_symbol FROM institutes i LEFT JOIN institute_preferences p ON p.institute_id=i.id WHERE i.id=:i', ['i' => $institute])->fetch() ?: [];
            $this->instituteCache[$institute] = ['school_name' => $i['name'] ?? null, 'school_phone' => $i['phone'] ?? null, '_currency' => ($i['currency_symbol'] ?? '') ?: 'Rs'];
        }
        return $this->instituteCache[$institute];
    }

    /** A student's total unpaid fees across every open invoice (carried-forward months are already inside the newest one). */
    public function balance(int $institute, int $student): float
    {
        return (float) $this->db->query("SELECT COALESCE(SUM(fi.remaining_amount),0) FROM fee_invoices fi JOIN students s ON s.id=fi.student_id
            WHERE s.institute_id=:i AND fi.student_id=:s AND fi.voided_at IS NULL AND fi.status IN('unpaid','partial') AND fi.remaining_amount>0", ['i' => $institute, 's' => $student])->fetchColumn();
    }

    public function money(int $institute, float $amount): string
    {
        return $this->instituteVars($institute)['_currency'] . ' ' . number_format($amount, $amount == floor($amount) ? 0 : 2);
    }

    /** Autocomplete for the Compose page. */
    public function search(int $institute, string $type, string $q, int $limit = 15): array
    {
        $like = '%' . mb_substr(trim($q), 0, 60) . '%';
        $cc = (string) $this->settings->get($institute)['default_country_code'];
        $out = [];
        if ($type === 'staff') {
            $rows = $this->db->query("SELECT id,name,employee_no,role,mobile FROM employees WHERE institute_id=:i AND " . \App\Support\PersonStatus::currentSql($this->db, $institute, 'employee', 'employees', 'messages') . " AND (name LIKE :q OR employee_no LIKE :q2 OR mobile LIKE :q3) ORDER BY name LIMIT {$limit}",
                ['i' => $institute, 'q' => $like, 'q2' => $like, 'q3' => $like])->fetchAll();
            foreach ($rows as $r) {
                $out[] = ['id' => (int) $r['id'], 'label' => $r['name'], 'detail' => trim(($r['role'] ?? '') . ' · ' . $r['employee_no'], ' ·'), 'phone' => PhoneNumber::tryNormalize($r['mobile'], $cc), 'raw_phone' => $r['mobile']];
            }
            return $out;
        }
        $rows = $this->db->query("SELECT s.id FROM students s WHERE s.institute_id=:i AND " . \App\Support\PersonStatus::currentSql($this->db, $institute, 'student', 's', 'messages') . " AND s.archived_at IS NULL AND (s.student_name LIKE :q OR s.registration_no LIKE :q2 OR s.father_name LIKE :q3 OR s.guardian_name LIKE :q4) ORDER BY s.student_name LIMIT {$limit}",
            ['i' => $institute, 'q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like])->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($rows as $id) {
            $r = $this->resolve($institute, $type === 'guardian' ? 'guardian' : 'student', (int) $id);
            if ($r) {
                $out[] = ['id' => $r['recipient_id'], 'label' => $r['name'], 'detail' => $r['detail'], 'phone' => $r['phone'], 'raw_phone' => $r['raw_phone']];
            }
        }
        return $out;
    }

    /**
     * One recipient with their phone and template variables.
     * @return array{recipient_type:string,recipient_id:int,name:string,detail:string,phone:?string,raw_phone:?string,skip_reason:?string,vars:array}|null
     */
    public function resolve(int $institute, string $type, int $id): ?array
    {
        return match ($type) {
            'student', 'guardian' => ($s = $this->students($institute, [$id])) ? $this->forStudent($institute, $s[0], $type) : null,
            'staff' => ($e = $this->staff($institute, [$id])) ? $this->forStaff($institute, $e[0]) : null,
            default => null,
        };
    }

    /** @param list<int>|null $ids */
    private function students(int $institute, ?array $ids = null, string $extraWhere = '', array $args = []): array
    {
        $sql = "SELECT s.id,s.student_name,s.registration_no,s.mobile,s.father_name,s.father_mobile,s.mother_name,s.mother_mobile,s.guardian_name,s.guardian_mobile,c.name class_name,
                   (SELECT g.name FROM guardian_students gs JOIN guardians g ON g.id=gs.guardian_id WHERE gs.student_id=s.id AND g.status='active' AND COALESCE(g.mobile,'')<>'' ORDER BY gs.is_primary DESC,g.id LIMIT 1) linked_name,
                   (SELECT g.mobile FROM guardian_students gs JOIN guardians g ON g.id=gs.guardian_id WHERE gs.student_id=s.id AND g.status='active' AND COALESCE(g.mobile,'')<>'' ORDER BY gs.is_primary DESC,g.id LIMIT 1) linked_mobile
                FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.institute_id=? AND " . \App\Support\PersonStatus::currentSql($this->db, $institute, 'student', 's', 'messages') . " AND s.archived_at IS NULL";
        $params = [$institute];
        if ($ids !== null) {
            if (!$ids) {
                return [];
            }
            $sql .= ' AND s.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...array_map('intval', $ids));
        }
        $stmt = $this->db->connection()->prepare($sql . $extraWhere . ' ORDER BY c.name,s.student_name');
        $stmt->execute(array_merge($params, $args));
        return $stmt->fetchAll();
    }

    private function staff(int $institute, ?array $ids = null): array
    {
        $sql = "SELECT id,name,employee_no,role,mobile FROM employees WHERE institute_id=? AND " . \App\Support\PersonStatus::currentSql($this->db, $institute, 'employee', 'employees', 'messages');
        $params = [$institute];
        if ($ids !== null) {
            if (!$ids) {
                return [];
            }
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...array_map('intval', $ids));
        }
        $stmt = $this->db->connection()->prepare($sql . ' ORDER BY name');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * First non-empty parent contact: linked guardian, then guardian, father, mother on the admission
     * form, and finally the student's own Mobile Number — many families only fill that one in, and
     * without it every parent SMS for those students was skipped as "No phone number on record".
     */
    private function parentOf(array $s): array
    {
        foreach ([['linked_name', 'linked_mobile'], ['guardian_name', 'guardian_mobile'], ['father_name', 'father_mobile'], ['mother_name', 'mother_mobile']] as [$n, $m]) {
            if (trim((string) ($s[$m] ?? '')) !== '') {
                return [trim((string) ($s[$n] ?? '')) ?: 'Parent', (string) $s[$m]];
            }
        }
        $name = trim((string) (($s['guardian_name'] ?? '') ?: ($s['father_name'] ?? '') ?: ($s['mother_name'] ?? ''))) ?: 'Parent';
        return [$name, trim((string) ($s['mobile'] ?? '')) !== '' ? (string) $s['mobile'] : null];
    }

    private function forStudent(int $institute, array $s, string $type, array $extraVars = []): array
    {
        [$parentName, $parentPhone] = $this->parentOf($s);
        $raw = $type === 'guardian' ? $parentPhone : ($s['mobile'] ?: null);
        $name = $type === 'guardian' ? $parentName : $s['student_name'];
        return $this->entry($institute, $type, (int) $s['id'], $name,
            $type === 'guardian' ? 'Parent of ' . $s['student_name'] . ($s['class_name'] ? ' · ' . $s['class_name'] : '') : trim(($s['class_name'] ?? '') . ' · ' . $s['registration_no'], ' ·'),
            $raw,
            array_replace(['student_name' => $s['student_name'], 'parent_name' => $parentName, 'class_name' => $s['class_name'], 'registration_no' => $s['registration_no']], $extraVars));
    }

    private function forStaff(int $institute, array $e): array
    {
        return $this->entry($institute, 'staff', (int) $e['id'], $e['name'], trim(($e['role'] ?? '') . ' · ' . $e['employee_no'], ' ·'), $e['mobile'] ?: null, []);
    }

    private function entry(int $institute, string $type, int $id, string $name, string $detail, ?string $raw, array $vars): array
    {
        $phone = null;
        $skip = null;
        if ($raw === null || trim($raw) === '') {
            $skip = 'No phone number on record';
        } else {
            try {
                $phone = PhoneNumber::normalize($raw, (string) $this->settings->get($institute)['default_country_code']);
            } catch (DomainException $e) {
                $skip = $e->getMessage();
            }
        }
        $base = $this->instituteVars($institute);
        unset($base['_currency']);
        return ['recipient_type' => $type, 'recipient_id' => $id, 'name' => $name, 'detail' => $detail, 'phone' => $phone, 'raw_phone' => $raw,
            'skip_reason' => $skip, 'vars' => array_replace($base, ['recipient_name' => $name, 'date' => date('d M Y')], $vars)];
    }

    /**
     * Recipients for a bulk send.
     * @param string $audience students|parents|staff
     * @param array{filter?:string,class_id?:int,section_id?:int,date?:string,attendance_status?:string,exam_id?:int} $f
     * @return list<array> entries as returned by resolve()
     */
    public function group(int $institute, string $audience, array $f): array
    {
        $filter = $f['filter'] ?? 'all';
        if (!isset(self::FILTERS[$filter])) {
            throw new DomainException('Select a valid recipient filter.');
        }
        if ($audience === 'staff') {
            if ($filter !== 'all') {
                throw new DomainException('Staff can only be messaged as a whole group.');
            }
            return array_map(fn($e) => $this->forStaff($institute, $e), $this->staff($institute));
        }
        if (!in_array($audience, ['students', 'parents'], true)) {
            throw new DomainException('Select who should receive the SMS.');
        }
        $type = $audience === 'parents' ? 'guardian' : 'student';
        $where = '';
        $args = [];
        $extra = [];
        switch ($filter) {
            case 'class':
                if (empty($f['class_id'])) {
                    throw new DomainException('Select a class.');
                }
                $where = ' AND s.class_id=?';
                $args[] = (int) $f['class_id'];
                break;
            case 'section':
                if (empty($f['section_id'])) {
                    throw new DomainException('Select a section.');
                }
                $where = ' AND s.section_id=?';
                $args[] = (int) $f['section_id'];
                break;
            case 'fee_outstanding':
                $rows = $this->db->query("SELECT fi.student_id,SUM(fi.remaining_amount) due,MIN(fi.due_date) due_date,MIN(fi.fee_month) fee_month FROM fee_invoices fi JOIN students s ON s.id=fi.student_id
                    WHERE s.institute_id=:i AND fi.voided_at IS NULL AND fi.status IN('unpaid','partial') AND fi.remaining_amount>0 GROUP BY fi.student_id", ['i' => $institute])->fetchAll();
                foreach ($rows as $r) {
                    $extra[(int) $r['student_id']] = ['fee_amount' => $this->money($institute, (float) $r['due']), 'balance' => $this->money($institute, (float) $r['due']), 'due_date' => $r['due_date'] ? date('d M Y', strtotime($r['due_date'])) : null,
                        'fee_month' => date('F Y', strtotime($r['fee_month'] . '-01'))];
                }
                break;
            case 'attendance':
                $date = (string) ($f['date'] ?? '');
                $status = (string) ($f['attendance_status'] ?? 'Absent');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($status, ['Absent', 'Late', 'Leave', 'Present'], true)) {
                    throw new DomainException('Select an attendance date and status.');
                }
                $rows = $this->db->query('SELECT a.student_id FROM student_attendance a JOIN students s ON s.id=a.student_id WHERE s.institute_id=:i AND a.attendance_date=:d AND a.status=:s',
                    ['i' => $institute, 'd' => $date, 's' => $status])->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($rows as $sid) {
                    $extra[(int) $sid] = ['date' => date('d M Y', strtotime($date)), 'attendance_status' => $status];
                }
                break;
            case 'exam':
                $exam = $this->db->query('SELECT id,name FROM exams WHERE id=:e AND institute_id=:i', ['e' => (int) ($f['exam_id'] ?? 0), 'i' => $institute])->fetch();
                if (!$exam) {
                    throw new DomainException('Select an exam.');
                }
                $rows = $this->db->query('SELECT em.student_id,SUM(CASE WHEN em.is_absent=1 THEN 0 ELSE COALESCE(em.obtained_marks,0) END) obtained,SUM(es.total_marks) total
                    FROM exam_marks em JOIN exam_subjects es ON es.id=em.exam_subject_id WHERE es.exam_id=:e GROUP BY em.student_id', ['e' => $exam['id']])->fetchAll();
                foreach ($rows as $r) {
                    $pct = (float) $r['total'] > 0 ? round((float) $r['obtained'] / (float) $r['total'] * 100, 1) : 0;
                    $extra[(int) $r['student_id']] = ['exam_name' => $exam['name'], 'marks' => rtrim(rtrim(number_format((float) $r['obtained'], 2, '.', ''), '0'), '.') . '/' . rtrim(rtrim(number_format((float) $r['total'], 2, '.', ''), '0'), '.'), 'percentage' => (string) $pct];
                }
                break;
        }
        $students = $this->students($institute, in_array($filter, ['fee_outstanding', 'attendance', 'exam'], true) ? array_keys($extra) : null, $where, $args);
        return array_map(fn($s) => $this->forStudent($institute, $s, $type, $extra[(int) $s['id']] ?? []), $students);
    }

    /** Parent entries for students, with extra template values per student. Used by automations. */
    public function parentsOf(int $institute, array $studentIds, array $extraVars = []): array
    {
        return array_map(fn($s) => $this->forStudent($institute, $s, 'guardian', $extraVars[(int) $s['id']] ?? []), $this->students($institute, array_values(array_unique(array_map('intval', $studentIds)))));
    }

    public function classes(int $institute): array
    {
        return $this->db->query('SELECT id,name FROM classes WHERE institute_id=:i ORDER BY name', ['i' => $institute])->fetchAll();
    }

    public function sections(int $institute): array
    {
        return $this->db->query("SELECT cs.id,cs.name,c.name class_name FROM class_sections cs JOIN classes c ON c.id=cs.class_id WHERE cs.institute_id=:i AND cs.status='active' ORDER BY c.name,cs.name", ['i' => $institute])->fetchAll();
    }

    public function exams(int $institute): array
    {
        return $this->db->query('SELECT id,name,status FROM exams WHERE institute_id=:i ORDER BY start_date DESC,id DESC LIMIT 50', ['i' => $institute])->fetchAll();
    }
}
