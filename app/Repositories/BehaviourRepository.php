<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DomainException;

/**
 * Behaviour & Skills: definable traits per domain, per-student ratings for a period, and
 * free-text observations. Every method is institute-scoped.
 */
final class BehaviourRepository
{
    public const DOMAINS = ['affective', 'psychomotor'];

    /** Rating scale shared by both domains. */
    public const SCALE = [
        5 => 'Excellent',
        4 => 'Very Good',
        3 => 'Good',
        2 => 'Fair',
        1 => 'Needs Improvement',
    ];

    public function __construct(private readonly Database $database) {}

    /* ------------------------------------------------------------------- traits */

    public function traits(int $instituteId, string $domain = '', bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM behaviour_traits WHERE institute_id = :i';
        $params = ['i' => $instituteId];
        if ($domain !== '') {
            $this->assertDomain($domain);
            $sql .= ' AND domain = :d';
            $params['d'] = $domain;
        }
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        return $this->database->query($sql . ' ORDER BY domain, sort_order, name', $params)->fetchAll();
    }

    public function createTrait(int $instituteId, string $domain, string $name, string $description, int $sort): int
    {
        $this->assertDomain($domain);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainException('Enter a trait name of up to 120 characters.');
        }
        if ($this->database->query('SELECT 1 FROM behaviour_traits WHERE institute_id=:i AND domain=:d AND name=:n LIMIT 1', ['i' => $instituteId, 'd' => $domain, 'n' => $name])->fetchColumn()) {
            throw new DomainException('That trait already exists in this domain.');
        }
        $now = date('Y-m-d H:i:s');
        $this->database->query(
            'INSERT INTO behaviour_traits(institute_id,domain,name,description,sort_order,status,created_at,updated_at)
             VALUES(:i,:d,:n,:desc,:s,\'active\',:c,:u)',
            ['i' => $instituteId, 'd' => $domain, 'n' => $name, 'desc' => mb_substr(trim($description), 0, 255), 's' => max(0, min($sort, 65535)), 'c' => $now, 'u' => $now]
        );
        return (int) $this->database->connection()->lastInsertId();
    }

    public function setTraitStatus(int $instituteId, int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Invalid status.');
        }
        return $this->database->query(
            'UPDATE behaviour_traits SET status=:s,updated_at=:u WHERE id=:id AND institute_id=:i',
            ['s' => $status, 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $instituteId]
        )->rowCount() > 0;
    }

    public function deleteTrait(int $instituteId, int $id): string
    {
        if (!$this->database->query('SELECT 1 FROM behaviour_traits WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId])->fetchColumn()) {
            return 'missing';
        }
        if ($this->database->query('SELECT 1 FROM behaviour_ratings WHERE trait_id=:id LIMIT 1', ['id' => $id])->fetchColumn()) {
            return 'in_use';
        }
        $this->database->query('DELETE FROM behaviour_traits WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId]);
        return 'deleted';
    }

    /* ------------------------------------------------------------------ ratings */

    /**
     * Rating grid for a class: every active student crossed with every active trait in the
     * domain, carrying whatever rating already exists for the period.
     */
    public function sheet(int $instituteId, int $classId, string $domain, string $period): array
    {
        $this->assertDomain($domain);
        $this->assertPeriod($period);
        $students = $this->database->query(
            'SELECT id, registration_no, student_name FROM students
             WHERE institute_id=:i AND class_id=:c AND status=\'active\' ORDER BY student_name',
            ['i' => $instituteId, 'c' => $classId]
        )->fetchAll();
        $existing = [];
        foreach ($this->database->query(
            'SELECT r.student_id, r.trait_id, r.rating FROM behaviour_ratings r
             JOIN students s ON s.id = r.student_id
             WHERE r.institute_id=:i AND s.class_id=:c AND r.period=:p',
            ['i' => $instituteId, 'c' => $classId, 'p' => $period]
        )->fetchAll() as $row) {
            $existing[(int) $row['student_id']][(int) $row['trait_id']] = (int) $row['rating'];
        }
        foreach ($students as &$student) {
            $student['ratings'] = $existing[(int) $student['id']] ?? [];
        }
        unset($student);
        return $students;
    }

    /**
     * Upserts ratings for one class/domain/period.
     *
     * @param array<int,array<int,mixed>> $ratings student id => trait id => rating
     */
    public function saveRatings(int $instituteId, int $classId, string $domain, string $period, array $ratings, int $userId): int
    {
        $this->assertDomain($domain);
        $this->assertPeriod($period);
        if (!$this->database->query('SELECT 1 FROM classes WHERE id=:c AND institute_id=:i', ['c' => $classId, 'i' => $instituteId])->fetchColumn()) {
            throw new DomainException('Class not found.');
        }
        // Only students of this class and traits of this domain may be written, whatever the
        // form posted.
        $validStudents = array_map('intval', $this->database->query(
            "SELECT id FROM students WHERE institute_id=:i AND class_id=:c AND status='active'",
            ['i' => $instituteId, 'c' => $classId]
        )->fetchAll(\PDO::FETCH_COLUMN));
        $validTraits = array_map('intval', $this->database->query(
            "SELECT id FROM behaviour_traits WHERE institute_id=:i AND domain=:d AND status='active'",
            ['i' => $instituteId, 'd' => $domain]
        )->fetchAll(\PDO::FETCH_COLUMN));

        $now = date('Y-m-d H:i:s');
        $saved = 0;
        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            foreach ($ratings as $studentId => $traitRatings) {
                $studentId = (int) $studentId;
                if (!in_array($studentId, $validStudents, true) || !is_array($traitRatings)) {
                    continue;
                }
                foreach ($traitRatings as $traitId => $value) {
                    $traitId = (int) $traitId;
                    if (!in_array($traitId, $validTraits, true)) {
                        continue;
                    }
                    if ($value === '' || $value === null) {
                        $this->database->query('DELETE FROM behaviour_ratings WHERE student_id=:s AND trait_id=:t AND period=:p', ['s' => $studentId, 't' => $traitId, 'p' => $period]);
                        continue;
                    }
                    $rating = (int) $value;
                    if (!array_key_exists($rating, self::SCALE)) {
                        throw new DomainException('Ratings must be between 1 and 5.');
                    }
                    $this->database->query(
                        'INSERT INTO behaviour_ratings(institute_id,student_id,trait_id,period,rating,rated_by,created_at,updated_at)
                         VALUES(:i,:s,:t,:p,:r,:u,:c,:up)
                         ON DUPLICATE KEY UPDATE rating=VALUES(rating),rated_by=VALUES(rated_by),updated_at=VALUES(updated_at)',
                        ['i' => $instituteId, 's' => $studentId, 't' => $traitId, 'p' => $period, 'r' => $rating, 'u' => $userId, 'c' => $now, 'up' => $now]
                    );
                    $saved++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return $saved;
    }

    /** Per-student averages for a domain, used by the domain rating reports. */
    public function report(int $instituteId, string $domain, string $period, int $classId = 0): array
    {
        $this->assertDomain($domain);
        $sql = 'SELECT s.id, s.registration_no, s.student_name, c.name AS class_name,
                       COUNT(r.id) AS rated_traits, ROUND(AVG(r.rating),2) AS average_rating
                FROM behaviour_ratings r
                JOIN students s ON s.id = r.student_id
                JOIN behaviour_traits t ON t.id = r.trait_id
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE r.institute_id = :i AND t.domain = :d';
        $params = ['i' => $instituteId, 'd' => $domain];
        if ($period !== '') {
            $this->assertPeriod($period);
            $sql .= ' AND r.period = :p';
            $params['p'] = $period;
        }
        if ($classId > 0) {
            $sql .= ' AND s.class_id = :c';
            $params['c'] = $classId;
        }
        return $this->database->query($sql . ' GROUP BY s.id, s.registration_no, s.student_name, c.name ORDER BY average_rating DESC, s.student_name', $params)->fetchAll();
    }

    /** Trait-level detail for one student, used on the student portal and reports. */
    public function studentRatings(int $instituteId, int $studentId, string $period = ''): array
    {
        $sql = 'SELECT t.domain, t.name AS trait, r.rating, r.period
                FROM behaviour_ratings r JOIN behaviour_traits t ON t.id = r.trait_id
                WHERE r.institute_id = :i AND r.student_id = :s';
        $params = ['i' => $instituteId, 's' => $studentId];
        if ($period !== '') {
            $this->assertPeriod($period);
            $sql .= ' AND r.period = :p';
            $params['p'] = $period;
        }
        return $this->database->query($sql . ' ORDER BY r.period DESC, t.domain, t.sort_order', $params)->fetchAll();
    }

    /* ------------------------------------------------------------- observations */

    public function observations(int $instituteId, int $classId = 0, int $studentId = 0, int $limit = 200): array
    {
        $sql = 'SELECT o.*, s.registration_no, s.student_name, c.name AS class_name, u.name AS author
                FROM behaviour_observations o
                JOIN students s ON s.id = o.student_id
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN users u ON u.id = o.created_by
                WHERE o.institute_id = :i';
        $params = ['i' => $instituteId];
        if ($classId > 0) {
            $sql .= ' AND s.class_id = :c';
            $params['c'] = $classId;
        }
        if ($studentId > 0) {
            $sql .= ' AND o.student_id = :s';
            $params['s'] = $studentId;
        }
        return $this->database->query($sql . ' ORDER BY o.observation_date DESC, o.id DESC LIMIT ' . max(1, min($limit, 500)), $params)->fetchAll();
    }

    public function addObservation(int $instituteId, int $studentId, string $date, string $category, string $note, int $userId): int
    {
        if (!in_array($category, ['positive', 'concern', 'neutral'], true)) {
            throw new DomainException('Choose a valid observation category.');
        }
        if (!$this->isDate($date)) {
            throw new DomainException('Enter a valid observation date.');
        }
        $note = trim($note);
        if ($note === '') {
            throw new DomainException('Write an observation note.');
        }
        if (!$this->database->query('SELECT 1 FROM students WHERE id=:s AND institute_id=:i', ['s' => $studentId, 'i' => $instituteId])->fetchColumn()) {
            throw new DomainException('Student not found.');
        }
        $now = date('Y-m-d H:i:s');
        $this->database->query(
            'INSERT INTO behaviour_observations(institute_id,student_id,observation_date,category,note,created_by,created_at,updated_at)
             VALUES(:i,:s,:d,:cat,:n,:u,:c,:up)',
            ['i' => $instituteId, 's' => $studentId, 'd' => $date, 'cat' => $category, 'n' => $note, 'u' => $userId, 'c' => $now, 'up' => $now]
        );
        return (int) $this->database->connection()->lastInsertId();
    }

    public function deleteObservation(int $instituteId, int $id): bool
    {
        return $this->database->query('DELETE FROM behaviour_observations WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId])->rowCount() > 0;
    }

    /* ----------------------------------------------------------------- guards */

    private function assertDomain(string $domain): void
    {
        if (!in_array($domain, self::DOMAINS, true)) {
            throw new DomainException('Unknown behaviour domain.');
        }
    }

    private function assertPeriod(string $period): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new DomainException('Choose a valid period.');
        }
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
