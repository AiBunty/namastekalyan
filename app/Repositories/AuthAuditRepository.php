<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class AuthAuditRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function log(string $action, string $username, string $outcome, string $details = '', string $source = 'web'): void
    {
        $safeDetails = function_exists('mb_substr')
            ? mb_substr($details, 0, 500)
            : substr($details, 0, 500);

        $sql = 'INSERT INTO auth_audit (logged_at, action, username, outcome, source, details)
                VALUES (:logged_at, :action, :username, :outcome, :source, :details)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':logged_at' => date('Y-m-d H:i:s'),
            ':action'    => $action,
            ':username'  => $username,
            ':outcome'   => $outcome,
            ':source'    => $source,
            ':details'   => $safeDetails,
        ]);
    }
}
