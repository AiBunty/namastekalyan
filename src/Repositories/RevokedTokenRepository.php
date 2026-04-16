<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class RevokedTokenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function revoke(string $tokenHash, string $expiresAt, string $username = ''): void
    {
        $sql = 'INSERT INTO revoked_tokens (token_hash, revoked_at, expires_at, username)
                VALUES (:token_hash, :revoked_at, :expires_at, :username)
                ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), username = VALUES(username)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':token_hash' => $tokenHash,
            ':revoked_at' => date('Y-m-d H:i:s'),
            ':expires_at' => $expiresAt,
            ':username'   => $username,
        ]);
    }

    public function isRevokedAndActive(string $tokenHash): bool
    {
        $sql = 'SELECT id
                FROM revoked_tokens
                WHERE token_hash = :token_hash
                  AND expires_at > :now
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':token_hash' => $tokenHash,
            ':now'        => date('Y-m-d H:i:s'),
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function purgeExpired(): int
    {
        $sql = 'DELETE FROM revoked_tokens WHERE expires_at <= :now';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':now' => date('Y-m-d H:i:s')]);
        return $stmt->rowCount();
    }
}
