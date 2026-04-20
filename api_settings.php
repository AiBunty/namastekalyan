<?php

/**
 * Public app-settings endpoint.
 *
 * Returns a safe, public subset of runtime settings stored in
 * config/app-settings.json that the frontend menu-blocker and
 * other UI components need without authentication.
 *
 * Access: GET /api_settings.php
 */

declare(strict_types=1);

// CLI is not a valid caller for this endpoint.
if (PHP_SAPI === 'cli') {
    exit(1);
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ── CORS (allow same-origin + known frontend origins) ────────────────────────
$allowedOrigins = array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*')));
$requestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin !== '' && (in_array('*', $allowedOrigins, true) || in_array($requestOrigin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Load config ───────────────────────────────────────────────────────────────
$configFile = __DIR__ . '/config/app-settings.json';

if (!is_file($configFile)) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'settings' => new stdClass()]);
    exit;
}

$raw = @file_get_contents($configFile);
if ($raw === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CONFIG_READ_ERROR']);
    exit;
}

$all = json_decode($raw, true);
if (!is_array($all)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CONFIG_PARSE_ERROR']);
    exit;
}

// ── Return only publicly-safe keys ────────────────────────────────────────────
$public = [
    'hotelWhatsappNo'      => $all['hotelWhatsappNo']      ?? null,
    'menuBlockerStaffCode' => $all['menuBlockerStaffCode'] ?? null,
    'menuBlockerPages'     => $all['menuBlockerPages']     ?? [
        'home' => true,
        'menu' => false,
        'cocktail' => false,
    ],
    'scannerDestination'   => $all['scannerDestination']   ?? 'menu',
    'updatedAt'            => $all['updatedAt']            ?? '',
];

http_response_code(200);
echo json_encode(['ok' => true, 'settings' => $public]);
