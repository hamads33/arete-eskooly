<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    public function __construct(private readonly Database $database) {}

    public function findByEmail(string $email): ?array
    {
        $row = $this->database->query('SELECT id, name, email, password_hash, status, last_login_at FROM users WHERE email = :email LIMIT 1', ['email' => strtolower($email)])->fetch();
        return is_array($row) ? $row : null;
    }

    public function findAuthContext(int $userId): ?array
    {
        $user = $this->database->query('SELECT id, name, email, status, last_login_at FROM users WHERE id = :id LIMIT 1', ['id' => $userId])->fetch();
        if (!is_array($user)) return null;
        $roles = $this->database->query('SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :id ORDER BY r.slug', ['id' => $userId])->fetchAll(\PDO::FETCH_COLUMN);
        $permissions = $this->database->query('SELECT DISTINCT p.slug FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id INNER JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = :id ORDER BY p.slug', ['id' => $userId])->fetchAll(\PDO::FETCH_COLUMN);
        $institute = $this->database->query('SELECT institute_id FROM institute_users WHERE user_id=:id', ['id'=>$userId])->fetchColumn();
        return $user + ['roles' => $roles, 'permissions' => $permissions, 'institute_id'=>(int)$institute];
    }

    /**
     * A linked institute identity is valid portal ownership even when an administrator has
     * replaced the generic employee role with a finer-grained staff role.
     */
    public function hasLinkedIdentity(int $userId): bool
    {
        foreach (['student_accounts', 'employee_accounts', 'guardian_accounts'] as $table) {
            try {
                if ($this->database->query("SELECT 1 FROM {$table} WHERE user_id=:u LIMIT 1", ['u' => $userId])->fetchColumn()) {
                    return true;
                }
            } catch (\Throwable) {
                // Older installations may not have every portal table yet.
            }
        }
        return false;
    }

    public function registerInstitute(string $name,string $institute,string $email,string $password): int
    {
        $pdo=$this->database->connection();
        $pdo->beginTransaction();
        try {
            $role=$this->database->query("SELECT id FROM roles WHERE slug='admin'")->fetchColumn();
            if(!$role) throw new \RuntimeException('Administrator role is unavailable.');
            $now=date('Y-m-d H:i:s');
            $this->database->query("INSERT INTO institutes(name,target_line,phone,address,country,created_at,updated_at) VALUES(:n,'','','','Pakistan',:c,:u)",['n'=>$institute,'c'=>$now,'u'=>$now]);
            $instituteId=(int)$pdo->lastInsertId();
            $this->database->query("INSERT INTO users(name,email,password_hash,status,created_at,updated_at) VALUES(:n,:e,:p,'active',:c,:u)",['n'=>$name,'e'=>$email,'p'=>password_hash($password,PASSWORD_DEFAULT),'c'=>$now,'u'=>$now]);
            $userId=(int)$pdo->lastInsertId();
            $this->database->query('INSERT INTO user_roles(user_id,role_id) VALUES(:u,:r)',['u'=>$userId,'r'=>$role]);
            $this->database->query('INSERT INTO institute_users(user_id,institute_id,created_at) VALUES(:u,:i,:c)',['u'=>$userId,'i'=>$instituteId,'c'=>$now]);
            $this->database->query("INSERT INTO institute_preferences(institute_id,timezone,currency,currency_symbol,created_at,updated_at) VALUES(:i,'Asia/Karachi','PKR','Rs',:c,:u)",['i'=>$instituteId,'c'=>$now,'u'=>$now]);
            $this->database->query("INSERT INTO account_heads(institute_id,name,type,status,created_at,updated_at) VALUES(:i,'General','both','active',:c,:u)",['i'=>$instituteId,'c'=>$now,'u'=>$now]);
            $pdo->commit();
            return $userId;
        } catch(\Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function updateLastLogin(int $userId): void
    {
        $this->database->query('UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id', ['last_login_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'), 'id' => $userId]);
    }

    public function updateAccount(int $id, string $email, ?string $password): void
    {
        $bindings = ['id' => $id, 'email' => strtolower($email), 'updated_at' => date('Y-m-d H:i:s')];
        $passwordSql = '';
        if ($password !== null) {
            $passwordSql = ', password_hash = :password_hash';
            $bindings['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $this->database->query("UPDATE users SET email = :email{$passwordSql}, updated_at = :updated_at WHERE id = :id", $bindings);
    }
}
