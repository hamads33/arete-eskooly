<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;

/**
 * Live-class persistence. All SQL for the module lives here; services orchestrate, controllers
 * only handle request and response.
 *
 * The student query deserves a note: audience matching is expressed as JOIN/WHERE conditions
 * rather than "load the row, then check it in PHP". A student who guesses an id gets an empty
 * result from the same query that builds their list, so there is no second code path to forget
 * a check in (§42).
 */
final class LiveClassRepository
{
    public function __construct(private readonly Database $db) {}

    private const SELECT = 'SELECT lc.*, c.name AS class_name, s.name AS subject_name, sec.name AS section_name, e.name AS teacher_name
        FROM live_classes lc
        LEFT JOIN classes c ON c.id = lc.target_class_id
        LEFT JOIN subjects s ON s.id = lc.subject_id
        LEFT JOIN class_sections sec ON sec.id = lc.section_id
        LEFT JOIN employees e ON e.id = lc.teacher_id';

    public function find(int $id, int $institute): ?array
    {
        $row = $this->db->query(self::SELECT . ' WHERE lc.id=:id AND lc.institute_id=:i LIMIT 1', ['id' => $id, 'i' => $institute])->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * The teaching employee's address, used to invite them to their own Google event.
     *
     * Scoped by institute for the same reason every other read here is: a teacher id from one
     * tenant must never resolve against another's employee row. Null when the employee has no
     * address on file, which the caller treats as "no host to invite".
     */
    public function teacherEmail(int $teacherId, int $institute): ?string
    {
        $email = $this->db->query('SELECT email FROM employees WHERE id=:e AND institute_id=:i LIMIT 1', ['e' => $teacherId, 'i' => $institute])->fetchColumn();
        return $this->usableEmail($email);
    }

    /**
     * The creating user's address, so whoever made the class can get into it.
     *
     * An administrator is no more the organiser than a teacher is — the event belongs to the
     * institute account — so they are invited on the same footing. Joined through institute_users
     * to keep the lookup inside the tenant.
     */
    public function userEmail(int $userId, int $institute): ?string
    {
        $email = $this->db->query(
            'SELECT u.email FROM users u JOIN institute_users iu ON iu.user_id = u.id WHERE u.id=:u AND iu.institute_id=:i LIMIT 1',
            ['u' => $userId, 'i' => $institute]
        )->fetchColumn();
        return $this->usableEmail($email);
    }

    /** An address is usable only if it is really there and really an address. */
    private function usableEmail(mixed $email): ?string
    {
        $email = is_string($email) ? trim($email) : '';
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Every Google-backed meeting for the institute, newest window first. */
    public function forInstitute(int $institute, array $filters = []): array
    {
        $where = ' WHERE lc.institute_id=:i AND lc.provider=\'google_meet\'';
        $bindings = ['i' => $institute];
        if (!empty($filters['teacher_id'])) { $where .= ' AND lc.teacher_id=:t'; $bindings['t'] = (int) $filters['teacher_id']; }
        if (!empty($filters['class_id'])) { $where .= ' AND lc.target_class_id=:c'; $bindings['c'] = (int) $filters['class_id']; }
        if (!empty($filters['status'])) { $where .= ' AND lc.status=:s'; $bindings['s'] = (string) $filters['status']; }
        return $this->db->query(self::SELECT . $where . ' ORDER BY lc.start_time DESC, lc.id DESC', $bindings)->fetchAll();
    }

    /**
     * Meetings a teacher may see: their own, plus any on a class/subject they are actively
     * assigned to. EXISTS rather than a join, so an assignment does not duplicate rows.
     */
    public function forTeacher(int $institute, int $employeeId): array
    {
        return $this->db->query(
            self::SELECT . ' WHERE lc.institute_id=:i AND lc.provider=\'google_meet\' AND (lc.teacher_id=:t OR EXISTS(
                SELECT 1 FROM teacher_assignments ta WHERE ta.employee_id=:t2 AND ta.institute_id=:i2 AND ta.status=\'active\'
                  AND ta.class_id=lc.target_class_id
                  AND (ta.assignment_type IN (\'whole_class\',\'homeroom\') OR ta.subject_id=lc.subject_id)))
             ORDER BY lc.start_time DESC, lc.id DESC',
            ['i' => $institute, 't' => $employeeId, 't2' => $employeeId, 'i2' => $institute]
        )->fetchAll();
    }

    /**
     * Meetings visible to one student. The audience test is the filter itself.
     *
     * AS  — every student in the institute
     * SC  — the student's class
     * SEC — the student's section
     * SS  — this student specifically
     * SEL — an explicit participant row
     *
     * Cancelled meetings are included on purpose: the student is told the class was cancelled
     * rather than left wondering where it went. Join is refused separately.
     */
    public function forStudent(int $studentId, int $institute): array
    {
        return $this->db->query(
            self::SELECT . ' JOIN students st ON st.id=:sid AND st.institute_id=:i AND st.status=\'active\'
             WHERE lc.institute_id=:i2 AND lc.provider=\'google_meet\' AND (
                lc.audience_type=\'AS\'
                OR (lc.audience_type=\'SC\' AND lc.target_class_id=st.class_id)
                OR (lc.audience_type=\'SEC\' AND lc.section_id IS NOT NULL AND lc.section_id=st.section_id)
                OR (lc.audience_type=\'SS\' AND lc.target_student_id=st.id)
                OR (lc.audience_type=\'SEL\' AND EXISTS(SELECT 1 FROM live_class_participants p WHERE p.live_class_id=lc.id AND p.student_id=st.id))
             ) ORDER BY lc.start_time ASC, lc.id ASC',
            ['sid' => $studentId, 'i' => $institute, 'i2' => $institute]
        )->fetchAll();
    }

    /** One meeting, only if this student is in its audience. Used by the join route. */
    public function findForStudent(int $id, int $studentId, int $institute): ?array
    {
        foreach ($this->forStudent($studentId, $institute) as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function create(array $data): int
    {
        $this->db->query(
            'INSERT INTO live_classes(institute_id,provider,title,description,room_code,audience_type,target_class_id,subject_id,section_id,target_student_id,target_employee_id,teacher_id,scheduled_at,start_time,end_time,timezone,duration_minutes,message,google_event_id,google_calendar_id,meet_link,conference_status,status,google_sync_status,last_google_error,created_by,created_by_role,created_at,updated_at)
             VALUES(:institute_id,\'google_meet\',:title,:description,NULL,:audience_type,:target_class_id,:subject_id,:section_id,:target_student_id,NULL,:teacher_id,:start_time,:start_time2,:end_time,:timezone,:duration_minutes,NULL,:google_event_id,:google_calendar_id,:meet_link,:conference_status,:status,:google_sync_status,:last_google_error,:created_by,:created_by_role,:now,:now2)',
            $data + ['start_time2' => $data['start_time'], 'now' => date('Y-m-d H:i:s'), 'now2' => date('Y-m-d H:i:s')]
        );
        return (int) $this->db->connection()->lastInsertId();
    }

    public function update(int $id, int $institute, array $data): void
    {
        $this->db->query(
            'UPDATE live_classes SET title=:title,description=:description,audience_type=:audience_type,target_class_id=:target_class_id,subject_id=:subject_id,section_id=:section_id,target_student_id=:target_student_id,scheduled_at=:start_time,start_time=:start_time2,end_time=:end_time,timezone=:timezone,duration_minutes=:duration_minutes,meet_link=:meet_link,conference_status=:conference_status,google_sync_status=:google_sync_status,last_google_error=:last_google_error,updated_at=:now WHERE id=:id AND institute_id=:i',
            $data + ['start_time2' => $data['start_time'], 'now' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute]
        );
    }

    /** Soft cancellation (§29/§30): history is retained, the join right is what goes away. */
    public function cancel(int $id, int $institute, int $userId, string $syncStatus, ?string $error): void
    {
        $this->db->query('UPDATE live_classes SET status=\'cancelled\',cancelled_at=:c,cancelled_by=:u,google_sync_status=:s,last_google_error=:e,updated_at=:c2 WHERE id=:id AND institute_id=:i', ['c' => date('Y-m-d H:i:s'), 'u' => $userId, 's' => $syncStatus, 'e' => $error, 'c2' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute]);
    }

    public function applySync(int $id, int $institute, ?string $meetLink, string $conferenceStatus, string $syncStatus, ?string $error, ?string $status = null): void
    {
        $sql = 'UPDATE live_classes SET meet_link=:m,conference_status=:c,google_sync_status=:s,last_google_error=:e' . ($status !== null ? ',status=:st' : '') . ',updated_at=:u WHERE id=:id AND institute_id=:i';
        $bindings = ['m' => $meetLink, 'c' => $conferenceStatus, 's' => $syncStatus, 'e' => $error, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute];
        if ($status !== null) { $bindings['st'] = $status; }
        $this->db->query($sql, $bindings);
    }

    /** Hard delete, used only to unwind a row whose Google event had to be abandoned. */
    public function delete(int $id, int $institute): void
    {
        $this->db->query('DELETE FROM live_classes WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $institute]);
    }

    /* --------------------------------------------------------------- participants */

    /** Replaces the explicit audience. Only students of this institute can be added. */
    public function syncParticipants(int $liveClassId, int $institute, array $studentIds): void
    {
        $this->db->query('DELETE FROM live_class_participants WHERE live_class_id=:l', ['l' => $liveClassId]);
        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_map('intval', $studentIds)) as $studentId) {
            if ($studentId > 0) {
                $this->db->query('INSERT IGNORE INTO live_class_participants(live_class_id,student_id,created_at) SELECT :l,id,:c FROM students WHERE id=:s AND institute_id=:i', ['l' => $liveClassId, 'c' => $now, 's' => $studentId, 'i' => $institute]);
            }
        }
    }

    public function participantIds(int $liveClassId): array
    {
        return array_map('intval', $this->db->query('SELECT student_id FROM live_class_participants WHERE live_class_id=:l', ['l' => $liveClassId])->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Records that the student clicked our Join action.
     *
     * This is an LMS link click, NOT verified Google Meet attendance (§15). No report may present
     * it as attendance, and the column comment in migration 000020 says the same thing.
     */
    public function recordJoinClick(int $liveClassId, int $studentId): void
    {
        // Upsert: a class-wide meeting has no participant row until the student clicks, and a
        // second click just refreshes the timestamp rather than failing on the unique key.
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO live_class_participants(live_class_id,student_id,join_clicked_at,created_at) VALUES(:l,:s,:j,:c) ON DUPLICATE KEY UPDATE join_clicked_at=VALUES(join_clicked_at)', ['l' => $liveClassId, 's' => $studentId, 'j' => $now, 'c' => $now]);
    }

    /* -------------------------------------------------------------------- pickers */

    public function classes(int $institute): array
    {
        return $this->db->query('SELECT id,name FROM classes WHERE institute_id=:i ORDER BY name', ['i' => $institute])->fetchAll();
    }

    public function sections(int $institute, int $classId = 0): array
    {
        $sql = 'SELECT id,class_id,name FROM class_sections WHERE institute_id=:i AND status=\'active\'';
        $bindings = ['i' => $institute];
        if ($classId > 0) { $sql .= ' AND class_id=:c'; $bindings['c'] = $classId; }
        return $this->db->query($sql . ' ORDER BY name', $bindings)->fetchAll();
    }

    public function subjects(int $institute, int $classId = 0): array
    {
        if ($classId > 0) {
            return $this->db->query('SELECT s.id,s.name FROM subjects s JOIN class_subjects cs ON cs.subject_id=s.id WHERE cs.class_id=:c AND s.institute_id=:i ORDER BY s.name', ['c' => $classId, 'i' => $institute])->fetchAll();
        }
        return $this->db->query('SELECT id,name FROM subjects WHERE institute_id=:i ORDER BY name', ['i' => $institute])->fetchAll();
    }

    public function studentsInClass(int $institute, int $classId): array
    {
        return $this->db->query('SELECT id,student_name,registration_no FROM students WHERE institute_id=:i AND class_id=:c AND status=\'active\' ORDER BY student_name', ['i' => $institute, 'c' => $classId])->fetchAll();
    }
}
