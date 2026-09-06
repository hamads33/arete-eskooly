<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DomainException;

/**
 * Roles, permissions and their assignments.
 *
 * System roles (admin/student/employee) are protected: they cannot be deleted, and the admin
 * role's wildcard grant cannot be edited away, so an institute can never lock itself out.
 */
final class RoleRepository
{
    /** Roles whose permission set and existence are managed by migrations, not the UI. */
    public const SYSTEM_ROLES = ['admin', 'student', 'employee'];

    public function __construct(private readonly Database $database) {}

    public function all(): array
    {
        return $this->database->query(
            "SELECT r.id, r.name, r.slug, COALESCE(m.is_system,0) AS is_system, COALESCE(m.description,'') AS description,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count
             FROM roles r LEFT JOIN role_meta m ON m.role_id = r.id
             ORDER BY COALESCE(m.is_system,0) DESC, r.name"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $row = $this->database->query(
            "SELECT r.id, r.name, r.slug, COALESCE(m.is_system,0) AS is_system, COALESCE(m.description,'') AS description
             FROM roles r LEFT JOIN role_meta m ON m.role_id = r.id WHERE r.id = :id LIMIT 1",
            ['id' => $id]
        )->fetch();
        return is_array($row) ? $row : null;
    }

    /** Every assignable permission, grouped by the module prefix of its slug. */
    public function permissionGroups(): array
    {
        $groups = [];
        foreach ($this->database->query("SELECT id, name, slug FROM permissions WHERE slug <> '*' ORDER BY slug")->fetchAll() as $row) {
            $module = str_contains((string) $row['slug'], '.') ? explode('.', (string) $row['slug'])[0] : 'general';
            $groups[$module][] = $row;
        }
        ksort($groups);
        return $groups;
    }

    /** @return int[] permission ids granted to the role */
    public function permissionIds(int $roleId): array
    {
        return array_map('intval', $this->database->query(
            'SELECT permission_id FROM role_permissions WHERE role_id = :r',
            ['r' => $roleId]
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function hasWildcard(int $roleId): bool
    {
        return (bool) $this->database->query(
            "SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :r AND p.slug = '*' LIMIT 1",
            ['r' => $roleId]
        )->fetchColumn();
    }

    public function create(string $name, string $description = ''): int
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new DomainException('Enter a role name of up to 80 characters.');
        }
        $slug = $this->slug($name);
        if ($slug === '') {
            throw new DomainException('Enter a role name that contains letters or numbers.');
        }
        if ($this->database->query('SELECT 1 FROM roles WHERE slug = :s OR name = :n LIMIT 1', ['s' => $slug, 'n' => $name])->fetchColumn()) {
            throw new DomainException('A role with that name already exists.');
        }
        $now = date('Y-m-d H:i:s');
        $this->database->query('INSERT INTO roles(name,slug,created_at,updated_at) VALUES(:n,:s,:c,:u)', ['n' => $name, 's' => $slug, 'c' => $now, 'u' => $now]);
        $id = (int) $this->database->connection()->lastInsertId();
        $this->database->query('INSERT INTO role_meta(role_id,is_system,description) VALUES(:r,0,:d)', ['r' => $id, 'd' => mb_substr(trim($description), 0, 255)]);
        return $id;
    }

    public function update(int $id, string $name, string $description): void
    {
        $role = $this->find($id);
        if ($role === null) {
            throw new DomainException('Role not found.');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new DomainException('Enter a role name of up to 80 characters.');
        }
        // A system role keeps its slug and name; only its description is editable.
        if ((int) $role['is_system'] === 1) {
            $this->database->query('UPDATE role_meta SET description=:d WHERE role_id=:r', ['d' => mb_substr(trim($description), 0, 255), 'r' => $id]);
            return;
        }
        if ($this->database->query('SELECT 1 FROM roles WHERE name = :n AND id <> :id LIMIT 1', ['n' => $name, 'id' => $id])->fetchColumn()) {
            throw new DomainException('A role with that name already exists.');
        }
        $this->database->query('UPDATE roles SET name=:n,updated_at=:u WHERE id=:id', ['n' => $name, 'u' => date('Y-m-d H:i:s'), 'id' => $id]);
        $this->database->query(
            'INSERT INTO role_meta(role_id,is_system,description) VALUES(:r,0,:d) ON DUPLICATE KEY UPDATE description=VALUES(description)',
            ['r' => $id, 'd' => mb_substr(trim($description), 0, 255)]
        );
    }

    /**
     * Replaces a role's permission set. The admin role is refused outright: its wildcard is a
     * structural guarantee, not a preference.
     *
     * @param int[] $permissionIds
     */
    public function syncPermissions(int $roleId, array $permissionIds): int
    {
        $role = $this->find($roleId);
        if ($role === null) {
            throw new DomainException('Role not found.');
        }
        if ($role['slug'] === 'admin') {
            throw new DomainException('The administrator role always has full access and cannot be edited.');
        }
        $valid = array_map('intval', $this->database->query(
            "SELECT id FROM permissions WHERE slug <> '*'"
        )->fetchAll(\PDO::FETCH_COLUMN));
        $wanted = array_values(array_intersect(array_map('intval', $permissionIds), $valid));

        $pdo = $this->database->connection();
        $pdo->beginTransaction();
        try {
            $this->database->query('DELETE FROM role_permissions WHERE role_id = :r', ['r' => $roleId]);
            foreach ($wanted as $permissionId) {
                $this->database->query('INSERT IGNORE INTO role_permissions(role_id,permission_id) VALUES(:r,:p)', ['r' => $roleId, 'p' => $permissionId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return count($wanted);
    }

    public function delete(int $id): void
    {
        $role = $this->find($id);
        if ($role === null) {
            throw new DomainException('Role not found.');
        }
        if ((int) $role['is_system'] === 1 || in_array($role['slug'], self::SYSTEM_ROLES, true)) {
            throw new DomainException('Built-in roles cannot be deleted.');
        }
        if ($this->database->query('SELECT 1 FROM user_roles WHERE role_id = :r LIMIT 1', ['r' => $id])->fetchColumn()) {
            throw new DomainException('Unassign this role from all users before deleting it.');
        }
        $this->database->query('DELETE FROM roles WHERE id = :id', ['id' => $id]);
    }

    /** Users currently holding the role, restricted to the given institute. */
    public function members(int $roleId, int $instituteId): array
    {
        return $this->database->query(
            'SELECT u.id, u.name, u.email FROM user_roles ur
             JOIN users u ON u.id = ur.user_id
             JOIN institute_users iu ON iu.user_id = u.id
             WHERE ur.role_id = :r AND iu.institute_id = :i ORDER BY u.name',
            ['r' => $roleId, 'i' => $instituteId]
        )->fetchAll();
    }

    /** Institute users who could be given the role. */
    public function assignableUsers(int $roleId, int $instituteId): array
    {
        return $this->database->query(
            'SELECT u.id, u.name, u.email FROM users u
             JOIN institute_users iu ON iu.user_id = u.id
             WHERE iu.institute_id = :i AND u.status = "active"
               AND NOT EXISTS(SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = :r)
             ORDER BY u.name LIMIT 500',
            ['i' => $instituteId, 'r' => $roleId]
        )->fetchAll();
    }

    public function assign(int $roleId, int $userId, int $instituteId): void
    {
        if (!$this->database->query('SELECT 1 FROM institute_users WHERE user_id=:u AND institute_id=:i', ['u' => $userId, 'i' => $instituteId])->fetchColumn()) {
            throw new DomainException('That user does not belong to this institute.');
        }
        if ($this->find($roleId) === null) {
            throw new DomainException('Role not found.');
        }
        $this->database->query('INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(:u,:r)', ['u' => $userId, 'r' => $roleId]);
    }

    /**
     * Removes a role from a user, refusing to remove the last administrator so the institute
     * cannot be locked out of its own settings.
     */
    public function unassign(int $roleId, int $userId, int $instituteId): void
    {
        $role = $this->find($roleId);
        if ($role === null) {
            throw new DomainException('Role not found.');
        }
        if ($role['slug'] === 'admin') {
            $remaining = (int) $this->database->query(
                'SELECT COUNT(*) FROM user_roles ur JOIN institute_users iu ON iu.user_id = ur.user_id
                 WHERE ur.role_id = :r AND iu.institute_id = :i AND ur.user_id <> :u',
                ['r' => $roleId, 'i' => $instituteId, 'u' => $userId]
            )->fetchColumn();
            if ($remaining === 0) {
                throw new DomainException('This is the last administrator and cannot be removed.');
            }
        }
        $this->database->query('DELETE FROM user_roles WHERE role_id=:r AND user_id=:u', ['r' => $roleId, 'u' => $userId]);
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? ''), '-');
        return mb_substr($slug, 0, 80);
    }
}
