<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM users ORDER BY id ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO users (
            username, display_name, role, password_hash, password_salt, status,
            force_password_change, failed_attempts, lockout_until, last_login_at,
            last_login_ip, created_at, created_by, updated_at, updated_by, permissions
        ) VALUES (
            :username, :display_name, :role, :password_hash, :password_salt, :status,
            :force_password_change, :failed_attempts, :lockout_until, :last_login_at,
            :last_login_ip, :created_at, :created_by, :updated_at, :updated_by, :permissions
        )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':username'              => $payload['username'],
            ':display_name'          => $payload['display_name'],
            ':role'                  => $payload['role'],
            ':password_hash'         => $payload['password_hash'],
            ':password_salt'         => $payload['password_salt'],
            ':status'                => $payload['status'],
            ':force_password_change' => (int) ($payload['force_password_change'] ?? 0),
            ':failed_attempts'       => (int) ($payload['failed_attempts'] ?? 0),
            ':lockout_until'         => $payload['lockout_until'] ?? null,
            ':last_login_at'         => $payload['last_login_at'] ?? null,
            ':last_login_ip'         => $payload['last_login_ip'] ?? null,
            ':created_at'            => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            ':created_by'            => $payload['created_by'] ?? null,
            ':updated_at'            => $payload['updated_at'] ?? null,
            ':updated_by'            => $payload['updated_by'] ?? null,
            ':permissions'           => json_encode($payload['permissions'] ?? []),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateLoginState(
        int $id,
        int $failedAttempts,
        ?string $lockoutUntil,
        ?string $lastLoginAt,
        ?string $lastLoginIp
    ): void {
        $sql = 'UPDATE users
                SET failed_attempts = :failed_attempts,
                    lockout_until = :lockout_until,
                    last_login_at = :last_login_at,
                    last_login_ip = :last_login_ip,
                    updated_at = :updated_at
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':failed_attempts' => $failedAttempts,
            ':lockout_until'   => $lockoutUntil,
            ':last_login_at'   => $lastLoginAt,
            ':last_login_ip'   => $lastLoginIp,
            ':updated_at'      => date('Y-m-d H:i:s'),
            ':id'              => $id,
        ]);
    }

    public function updatePassword(
        int $id,
        string $passwordHash,
        string $passwordSalt,
        bool $forcePasswordChange,
        ?string $updatedBy = null
    ): void {
        $sql = 'UPDATE users
                SET password_hash = :hash,
                    password_salt = :salt,
                    force_password_change = :force_password_change,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':hash'                  => $passwordHash,
            ':salt'                  => $passwordSalt,
            ':force_password_change' => $forcePasswordChange ? 1 : 0,
            ':updated_at'            => date('Y-m-d H:i:s'),
            ':updated_by'            => $updatedBy,
            ':id'                    => $id,
        ]);
    }

    public function setStatus(int $id, string $status, ?string $updatedBy = null): void
    {
        $sql = 'UPDATE users
                SET status = :status,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status'     => $status,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':updated_by' => $updatedBy,
            ':id'         => $id,
        ]);
    }

    public function setPermissions(int $id, array $permissions, ?string $updatedBy = null): void
    {
        $sql = 'UPDATE users
                SET permissions = :permissions,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':permissions' => json_encode($permissions),
            ':updated_at'  => date('Y-m-d H:i:s'),
            ':updated_by'  => $updatedBy,
            ':id'          => $id,
        ]);
    }
}
