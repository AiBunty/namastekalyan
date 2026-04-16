<?php

declare(strict_types=1);

// Boot-time diagnostics are intentionally very early so even startup failures
// are captured in a local log file instead of becoming opaque Apache 500 pages.
$__nkLogDir = __DIR__ . '/logs';
if (!is_dir($__nkLogDir)) {
    @mkdir($__nkLogDir, 0750, true);
}
$__nkBootLog = $__nkLogDir . '/boot.log';

$__nkWriteBootLog = static function (string $label, array $context = []) use ($__nkBootLog): void {
    $entry = [
        'time' => date('Y-m-d H:i:s'),
        'label' => $label,
        'context' => $context,
    ];
    @file_put_contents($__nkBootLog, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
};

$__nkWriteBootLog('request_start', [
    'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
    'sapi' => PHP_SAPI,
    'php_version' => PHP_VERSION,
]);

$__nkSendStartupError = static function (string $error, string $message, array $context = []) use ($__nkWriteBootLog): void {
    $__nkWriteBootLog('startup_error', [
        'error' => $error,
        'message' => $message,
        'context' => $context,
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'ok' => false,
        'error' => $error,
        'message' => $message,
        'phpVersion' => PHP_VERSION,
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES);
    exit;
};

register_shutdown_function(static function () use ($__nkWriteBootLog): void {
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    $__nkWriteBootLog('fatal_shutdown', [
        'type' => (int) ($error['type'] ?? 0),
        'message' => (string) ($error['message'] ?? ''),
        'file' => (string) ($error['file'] ?? ''),
        'line' => (int) ($error['line'] ?? 0),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'FATAL_STARTUP_ERROR',
            'message' => 'A fatal error occurred during API startup.',
            'phpVersion' => PHP_VERSION,
        ], JSON_UNESCAPED_SLASHES);
    }
});

$bootstrapFile = __DIR__ . '/bootstrap.php';
if (!is_file($bootstrapFile)) {
    $__nkSendStartupError(
        'DEPLOYMENT_INCOMPLETE',
        'Required deployment files are missing from the API directory.',
        ['missing' => ['bootstrap.php']]
    );
}

$routerFile = __DIR__ . '/src/Routes/ActionRouter.php';
if (!is_file($routerFile)) {
    $__nkSendStartupError(
        'DEPLOYMENT_INCOMPLETE',
        'Required deployment files are missing from the API directory.',
        ['missing' => ['src/Routes/ActionRouter.php']]
    );
}

require_once $bootstrapFile;

use NK\Middleware\CorsMiddleware;
use NK\Routes\ActionRouter;
use NK\Support\Logger;
use NK\Support\Response;

// 1. Handle CORS preflight; add headers for all responses
CorsMiddleware::handle();

// 2. Parse request
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$query  = $_GET ?? [];
$body   = [];
$rawBody = '';

if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $rawBody = (string) file_get_contents('php://input');
    $body = json_decode($rawBody, true) ?? [];

    // Fallback to $_POST for form-encoded payloads (e.g. some Razorpay flows)
    if (empty($body) && !empty($_POST)) {
        $body = $_POST;
    }

    // Apps Script compatibility: payload={...json...}
    if (isset($body['payload']) && is_string($body['payload'])) {
        $decodedPayload = json_decode((string) $body['payload'], true);
        if (is_array($decodedPayload)) {
            $body = array_merge($body, $decodedPayload);
            unset($body['payload']);
        }
    }

    $body['_rawBody'] = $rawBody;
}

// 3. Determine action
$action = (string) ($body['action'] ?? $query['action'] ?? '');

// Direct Razorpay webhooks may not include action parameter.
if ($action === '' && isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) && $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] !== '') {
    $action = 'razorpay_webhook';
}

// 4. Dispatch
try {
    $result = ActionRouter::dispatch($method, $action, $body, $query);
    Response::send($result);
} catch (\Throwable $e) {
    $__nkWriteBootLog('unhandled_exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    Logger::error('Unhandled exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    Response::send(
        ['ok' => false, 'error' => 'INTERNAL_ERROR', 'message' => 'Internal server error.'],
        500
    );
}
