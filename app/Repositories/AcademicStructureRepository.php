<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DomainException;

/**
 * Class sections and academic streams/groups.
 *
 * The tables and the students.section_id / students.stream_id columns already existed but had
 * no administration surface, so sections and streams could never actually be configured or
 * assigned. Everything here is institute-scoped, and deletes are refused while students still
 * reference the record.
 */
final class AcademicStructureRepository
{
    public function __construct(private readonly Database $database) {}

    /* ------------------------------------------------------------------ sections */

    public function sections(int $instituteId, int $classId = 0): array
    {
        $sql = 'SELECT cs.*, c.name AS class_name,
                       (SELECT COUNT(*) FROM students s WHERE s.section_id = cs.id) AS student_count
                FROM class_sections cs
                JOIN classes c ON c.id = cs.class_id
                WHERE cs.institute_id = :i';
        $params = ['i' => $instituteId];
        if ($classId > 0) {
            $sql .= ' AND cs.class_id = :c';
            $params['c'] = $classId;
        }
        return $this->database->query($sql . ' ORDER BY c.name, cs.name', $params)->fetchAll();
    }

    public function createSection(int $instituteId, int $classId, string $name): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 60) {
            throw new DomainException('Enter a section name of up to 60 characters.');
        }
        if (!$this->database->query('SELECT 1 FROM classes WHERE id=:c AND institute_id=:i', ['c' => $classId, 'i' => $instituteId])->fetchColumn()) {
            throw new DomainException('Class not found.');
        }
        if ($this->database->query('SELECT 1 FROM class_sections WHERE institute_id=:i AND class_id=:c AND name=:n LIMIT 1', ['i' => $instituteId, 'c' => $classId, 'n' => $name])->fetchColumn()) {
            throw new DomainException('That section already exists for this class.');
        }
        $now = date('Y-m-d H:i:s');
        $this->database->query(
            'INSERT INTO class_sections(institute_id,class_id,name,status,created_at,updated_at) VALUES(:i,:c,:n,\'active\',:a,:b)',
            ['i' => $instituteId, 'c' => $classId, 'n' => $name, 'a' => $now, 'b' => $now]
        );
        return (int) $this->database->connection()->lastInsertId();
    }

    public function setSectionStatus(int $instituteId, int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Invalid status.');
        }
        return $this->database->query(
            'UPDATE class_sections SET status=:s,updated_at=:u WHERE id=:id AND institute_id=:i',
            ['s' => $status, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $instituteId]
        )->rowCount() > 0;
    }

    /** 'deleted' | 'in_use' | 'missing' — never orphans a student's section_id. */
    public function deleteSection(int $instituteId, int $id): string
    {
        if (!$this->database->query('SELECT 1 FROM class_sections WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId])->fetchColumn()) {
            return 'missing';
        }
        if ($this->database->query('SELECT 1 FROM students WHERE section_id=:id LIMIT 1', ['id' => $id])->fetchColumn()) {
            return 'in_use';
        }
        $this->database->query('DELETE FROM class_sections WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId]);
        return 'deleted';
    }

    /* ------------------------------------------------------------------- streams */

    public function streams(int $instituteId): array
    {
        return $this->database->query(
            'SELECT st.*, (SELECT COUNT(*) FROM students s WHERE s.stream_id = st.id) AS student_count
             FROM academic_streams st WHERE st.institute_id = :i ORDER BY st.name',
            ['i' => $instituteId]
        )->fetchAll();
    }

    public function createStream(int $instituteId, string $name): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new DomainException('Enter a stream name of up to 80 characters.');
        }
        if ($this->database->query('SELECT 1 FROM academic_streams WHERE institute_id=:i AND name=:n LIMIT 1', ['i' => $instituteId, 'n' => $name])->fetchColumn()) {
            throw new DomainException('That stream already exists.');
        }
        $now = date('Y-m-d H:i:s');
        $this->database->query(
            'INSERT INTO academic_streams(institute_id,name,status,created_at,updated_at) VALUES(:i,:n,\'active\',:a,:b)',
            ['i' => $instituteId, 'n' => $name, 'a' => $now, 'b' => $now]
        );
        return (int) $this->database->connection()->lastInsertId();
    }

    public function setStreamStatus(int $instituteId, int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Invalid status.');
        }
        return $this->database->query(
            'UPDATE academic_streams SET status=:s,updated_at=:u WHERE id=:id AND institute_id=:i',
            ['s' => $status, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $instituteId]
        )->rowCount() > 0;
    }

    public function deleteStream(int $instituteId, int $id): string
    {
        if (!$this->database->query('SELECT 1 FROM academic_streams WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId])->fetchColumn()) {
            return 'missing';
        }
        if ($this->database->query('SELECT 1 FROM students WHERE stream_id=:id LIMIT 1', ['id' => $id])->fetchColumn()) {
            return 'in_use';
        }
        $this->database->query('DELETE FROM academic_streams WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId]);
        return 'deleted';
    }

    /* -------------------------------------------------------------- assignment */

    /** Active sections of a class, for student admission/edit dropdowns. */
    public function sectionOptions(int $instituteId, int $classId): array
    {
        return $this->database->query(
            "SELECT id, name FROM class_sections WHERE institute_id=:i AND class_id=:c AND status='active' ORDER BY name",
            ['i' => $instituteId, 'c' => $classId]
        )->fetchAll();
    }

    public function streamOptions(int $instituteId): array
    {
        return $this->database->query(
            "SELECT id, name FROM academic_streams WHERE institute_id=:i AND status='active' ORDER BY name",
            ['i' => $instituteId]
        )->fetchAll();
    }

    /**
     * Assigns a student to a section and/or stream, verifying both belong to this institute
     * and that the section belongs to the student's own class.
     */
    public function assignStudent(int $instituteId, int $studentId, ?int $sectionId, ?int $streamId): void
    {
        $student = $this->database->query('SELECT class_id FROM students WHERE id=:s AND institute_id=:i', ['s' => $studentId, 'i' => $instituteId])->fetch();
        if (!is_array($student)) {
            throw new DomainException('Student not found.');
        }
        if ($sectionId !== null) {
            $ok = $this->database->query(
                'SELECT 1 FROM class_sections WHERE id=:id AND institute_id=:i AND class_id=:c',
                ['id' => $sectionId, 'i' => $instituteId, 'c' => (int) $student['class_id']]
            )->fetchColumn();
            if (!$ok) {
                throw new DomainException('That section does not belong to the student\'s class.');
            }
        }
        if ($streamId !== null && !$this->database->query('SELECT 1 FROM academic_streams WHERE id=:id AND institute_id=:i', ['id' => $streamId, 'i' => $instituteId])->fetchColumn()) {
            throw new DomainException('Stream not found.');
        }
        $this->database->query(
            'UPDATE students SET section_id=:sec,stream_id=:str,updated_at=:u WHERE id=:s AND institute_id=:i',
            ['sec' => $sectionId, 'str' => $streamId, 'u' => date('Y-m-d H:i:s'), 's' => $studentId, 'i' => $instituteId]
        );
    }
}
