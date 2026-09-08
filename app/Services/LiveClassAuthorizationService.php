<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Repositories\PakistanErpRepository;

/**
 * Who may act on which live class.
 *
 * This exists as its own service because the same questions are asked from three places (admin
 * controller, teacher controller, student join route) and a check that lives in only one of them
 * is a check the other two will eventually skip. Every method re-derives the answer from the
 * database; none of them trusts a submitted class_id, subject_id or teacher_id (§19, §43).
 */
final class LiveClassAuthorizationService
{
    public function __construct(private readonly Database $db) {}

    /**
     * May this teacher host a meeting for this class and subject?
     *
     * Delegates to the existing teacher_assignments rule so live classes cannot drift away from
     * the scope that already governs attendance, homework and tests. A whole-class or homeroom
     * assignment covers any subject; a subject assignment covers only that subject.
     */
    public function teacherMayHost(int $employeeId, int $institute, int $classId, ?int $subjectId): bool
    {
        if ($classId <= 0) {
            return false;
        }
        return (new PakistanErpRepository($this->db))->teacherCan($employeeId, $institute, $classId, $subjectId);
    }

    /**
     * May this teacher edit or cancel this meeting? Ownership by default; anything wider requires
     * live-classes.manage-all, which is not granted to the teacher role.
     */
    public function teacherMayManage(array $meeting, int $employeeId): bool
    {
        if (can('*') || can('live-classes.manage-all')) {
            return true;
        }
        return (int) ($meeting['teacher_id'] ?? 0) === $employeeId;
    }

    /** A section must belong to the class it is being attached to — no cross-class audiences. */
    public function sectionBelongsToClass(int $sectionId, int $classId, int $institute): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM class_sections WHERE id=:s AND class_id=:c AND institute_id=:i LIMIT 1', ['s' => $sectionId, 'c' => $classId, 'i' => $institute])->fetchColumn();
    }

    /** A subject must actually be taught in the class it is being attached to. */
    public function subjectBelongsToClass(int $subjectId, int $classId, int $institute): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM class_subjects cs JOIN subjects s ON s.id=cs.subject_id WHERE cs.subject_id=:s AND cs.class_id=:c AND s.institute_id=:i LIMIT 1', ['s' => $subjectId, 'c' => $classId, 'i' => $institute])->fetchColumn();
    }

    public function classBelongsToInstitute(int $classId, int $institute): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM classes WHERE id=:c AND institute_id=:i LIMIT 1', ['c' => $classId, 'i' => $institute])->fetchColumn();
    }

    /** Filters a submitted student list down to real, active students of this institute. */
    public function filterStudents(array $studentIds, int $institute, int $classId = 0): array
    {
        $ids = array_values(array_filter(array_map('intval', $studentIds), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id FROM students WHERE institute_id=? AND status='active' AND id IN ({$placeholders})";
        $bindings = array_merge([$institute], $ids);
        if ($classId > 0) {
            $sql .= ' AND class_id=?';
            $bindings[] = $classId;
        }
        return array_map('intval', $this->db->query($sql, $bindings)->fetchAll(\PDO::FETCH_COLUMN));
    }
}
