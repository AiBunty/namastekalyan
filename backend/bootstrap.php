<?php

declare(strict_types=1);

define('NK_BASE_DIR', __DIR__);
define('NK_SRC_DIR',  NK_BASE_DIR . '/src');

// Composer autoloader if available
$composerAutoload = NK_BASE_DIR . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Lightweight PSR-4 autoloader fallback for NK\ namespace.
spl_autoload_register(static function (string $class): void {
    $prefix = 'NK\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = NK_SRC_DIR . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

// Load .env values into $_ENV if present.
$envFile = NK_BASE_DIR . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim((string) $line);
        if ($trimmed === '' || substr($trimmed, 0, 1) === '#') {
            continue;
        }
        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim((string) $parts[0]);
        $value = trim((string) $parts[1]);
        if ($key === '') {
            continue;
        }
        $value = trim($value, "\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

// Timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata');

// Error reporting
$isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
if ($isDev) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
