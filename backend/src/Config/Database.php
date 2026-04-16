<?php

declare(strict_types=1);

namespace NK\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Returns the shared PDO instance (lazy-initialized singleton).
     */
    public static function connection(): PDO
    {
        if (self::$instance === null) {
            if (!extension_loaded('pdo_mysql')) {
                throw new \RuntimeException('Database driver pdo_mysql is not installed.');
            }

            $host = trim((string) ($_ENV['DB_HOST'] ?? 'localhost'));
            $port = trim((string) ($_ENV['DB_PORT'] ?? '3306'));
            $name = $_ENV['DB_NAME'] ?? '';
            $user = $_ENV['DB_USER'] ?? '';
            $pass = $_ENV['DB_PASS'] ?? '';

            // Some providers expose host as "hostname:port" in DB_HOST.
            if (strpos($host, ':') !== false) {
                [$hostPart, $portPart] = explode(':', $host, 2);
                if ($hostPart !== '') {
                    $host = $hostPart;
                }
                if ($portPart !== '' && ctype_digit($portPart)) {
                    $port = $portPart;
                }
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
            }

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Never expose credentials in error messages
                throw new \RuntimeException('Database connection failed: ' . $e->getCode());
            }
        }

        return self::$instance;
    }

    /** Force a new connection (useful in tests / CLI scripts). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
