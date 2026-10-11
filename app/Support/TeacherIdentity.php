<?php
declare(strict_types=1);
namespace App\Support;
use App\Core\Database;
use DomainException;

/** Resolve attribution from the signed-in account, never from a teacher's posted fields. */
final class TeacherIdentity
{
    public static function teacher(Database $db, int $institute): ?array
    {
        if (can('*')) return null;
        $employee = $db->query('SELECT e.id,e.name,e.role FROM employee_accounts ea JOIN employees e ON e.id=ea.employee_id WHERE ea.user_id=:u AND e.institute_id=:i LIMIT 1', ['u'=>(int)(user()['id']??0),'i'=>$institute])->fetch();
        if (!hasRole('teacher') && (!$employee || strtolower(trim((string)$employee['role'])) !== 'teacher')) return null;
        return $employee ?: ['id'=>0,'name'=>(string)(user()['name']??'Teacher')];
    }

    public static function author(Database $db, int $institute, ?int $requested): ?int
    {
        $teacher = self::teacher($db, $institute);
        if ($teacher === null) return $requested ?: null;
        if (!(int)$teacher['id']) throw new DomainException('Teacher account is not linked to an employee.');
        return (int)$teacher['id'];
    }
}
