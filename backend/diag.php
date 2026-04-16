<?php
/**
 * Quick DB + environment diagnostic.
 * Usage: /backend/diag.php?token=YOUR_SETUP_TOKEN
 * Delete this file after diagnosing.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$setupToken = trim((string) ($_ENV['SETUP_TOKEN'] ?? ''));
$provided   = trim((string) ($_GET['token'] ?? ''));
if ($setupToken === '' || $provided === '' || !hash_equals($setupToken, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
    exit;
}

$info = [
    'php_version'   => PHP_VERSION,
    'pdo_mysql'     => extension_loaded('pdo_mysql'),
    'curl'          => extension_loaded('curl'),
    'db_host'       => $_ENV['DB_HOST'] ?? '(not set)',
    'db_port'       => $_ENV['DB_PORT'] ?? '(not set)',
    'db_name'       => $_ENV['DB_NAME'] ?? '(not set)',
    'db_user'       => $_ENV['DB_USER'] ?? '(not set)',
    'jwt_secret_len'=> strlen($_ENV['JWT_SECRET'] ?? ''),
    'app_env'       => $_ENV['APP_ENV'] ?? '(not set)',
    'app_url'       => $_ENV['APP_URL'] ?? '(not set)',
];

$dbResult = 'UNTESTED';
try {
    $host = trim((string) ($_ENV['DB_HOST'] ?? 'localhost'));
    $port = trim((string) ($_ENV['DB_PORT'] ?? '3306'));
    $name = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';
    if (strpos($host, ':') !== false) {
        [$h, $p] = explode(':', $host, 2);
        if ($h !== '') $host = $h;
        if ($p !== '' && ctype_digit($p)) $port = $p;
    }
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $dbResult = "CONNECTED – MySQL {$version}";
} catch (Throwable $e) {
    $dbResult = 'FAILED: ' . $e->getMessage() . ' (code ' . $e->getCode() . ')';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'     => str_starts_with($dbResult, 'CONNECTED'),
    'db'     => $dbResult,
    'env'    => $info,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
