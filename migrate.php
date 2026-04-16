<?php

/**
 * Database migration runner — CLI only.
 *
 * Usage:
 *   php backend/migrate.php
 *
 * Runs all .sql files in database/migrations/ in filename order.
 * Each file is run once; already-applied migrations are skipped via
 * the `migrations` tracking table.
 */

declare(strict_types=1);

// This script is CLI-only
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden');
}

require_once __DIR__ . '/bootstrap.php';

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

// ── Connect ───────────────────────────────────────────────────────────────────
$host = trim((string) ($_ENV['DB_HOST'] ?? 'localhost'));
$port = trim((string) ($_ENV['DB_PORT'] ?? '3306'));
$name = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';

if (strpos($host, ':') !== false) {
    [$hostPart, $portPart] = explode(':', $host, 2);
    if ($hostPart !== '') {
        $host = $hostPart;
    }
    if ($portPart !== '' && ctype_digit($portPart)) {
        $port = $portPart;
    }
}

$dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
$pdo  = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ── Migrations tracking table ─────────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id`          INT          UNSIGNED NOT NULL AUTO_INCREMENT,
        `filename`    VARCHAR(120) NOT NULL,
        `applied_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_filename` (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Fetch already-applied migrations
$applied = $pdo->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

// ── Run SQL files ─────────────────────────────────────────────────────────────
$migrationsDir = __DIR__ . '/database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found in {$migrationsDir}\n";
    exit(0);
}

$ran = 0;
foreach ($files as $file) {
    $filename = basename($file);

    if (isset($applied[$filename])) {
        echo "  [SKIP] {$filename}\n";
        continue;
    }

    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO migrations (filename) VALUES (:f)");
        $stmt->execute([':f' => $filename]);
        echo "  [ OK ] {$filename}\n";
        $ran++;
    } catch (\PDOException $e) {
        echo "  [FAIL] {$filename}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nDone. {$ran} migration(s) applied.\n";
