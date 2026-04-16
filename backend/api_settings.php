<?php
/**
 * Settings API - Manage WhatsApp number and Menu Blocker passcode
 * Endpoint: /backend/api_settings.php
 * Methods: GET (public read), POST (superadmin update)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use NK\Repositories\UserRepository;
use NK\Services\AuthService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$settingsFile = __DIR__ . '/config/app-settings.json';
$settingsDir = __DIR__ . '/config';
if (!is_dir($settingsDir)) {
    @mkdir($settingsDir, 0755, true);
}

$defaultSettings = [
    'hotelWhatsappNo' => '919371519999',
    'menuBlockerStaffCode' => 'NKSTAFF2026',
    'updatedAt' => date('Y-m-d H:i:s'),
    'updatedBy' => 'system'
];

function getSettingsValue(string $filePath, array $defaults): array
{
    if (is_file($filePath)) {
        $content = (string) file_get_contents($filePath);
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }
    }

    return $defaults;
}

function saveSettingsValue(string $filePath, array $settings): bool
{
    return file_put_contents(
        $filePath,
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    ) !== false;
}

function currentToken(array $body): string
{
    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($authHeader, 'Bearer ') === 0) {
        return trim(substr($authHeader, 7));
    }

    return AuthService::extractToken(array_merge($body, $_GET, ['authorization' => $authHeader]));
}

function requireSuperadminForUpdate(array $body): array
{
    $token = currentToken($body);
    $verified = AuthService::verifyToken($token);
    if (($verified['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'error' => 'UNAUTHORIZED',
            'message' => (string) ($verified['message'] ?? 'Missing or invalid auth token.')
        ];
    }

    $username = AuthService::normalizeUsername((string) ($verified['payload']['sub'] ?? ''));
    $repo = new UserRepository();
    $user = $repo->findByUsername($username);

    if (!$user || strtolower((string) ($user['role'] ?? '')) !== 'superadmin') {
        return [
            'ok' => false,
            'error' => 'FORBIDDEN',
            'message' => 'SuperAdmin access required.'
        ];
    }

    return ['ok' => true, 'username' => $username];
}

try {
    if ($method === 'GET') {
        $settings = getSettingsValue($settingsFile, $defaultSettings);
        echo json_encode([
            'success' => true,
            'data' => [
                'hotelWhatsappNo' => (string) ($settings['hotelWhatsappNo'] ?? ''),
                'menuBlockerStaffCode' => (string) ($settings['menuBlockerStaffCode'] ?? ''),
                'updatedAt' => (string) ($settings['updatedAt'] ?? ''),
            ]
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST ?: [];
        if (isset($input['payload']) && is_string($input['payload'])) {
            $decodedPayload = json_decode((string) $input['payload'], true);
            if (is_array($decodedPayload)) {
                $input = array_merge($input, $decodedPayload);
                unset($input['payload']);
            }
        }
    }

    $auth = requireSuperadminForUpdate($input);
    if (($auth['ok'] ?? false) !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $auth['message'] ?? 'Unauthorized']);
        exit;
    }

    $settings = getSettingsValue($settingsFile, $defaultSettings);
    $updated = false;

    if (array_key_exists('hotelWhatsappNo', $input) && (string) $input['hotelWhatsappNo'] !== '') {
        $wa = preg_replace('/\D/', '', (string) $input['hotelWhatsappNo']);
        if (!$wa || strlen($wa) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid WhatsApp number. Must be at least 10 digits.']);
            exit;
        }
        $settings['hotelWhatsappNo'] = $wa;
        $updated = true;
    }

    if (array_key_exists('menuBlockerStaffCode', $input) && (string) $input['menuBlockerStaffCode'] !== '') {
        $code = trim((string) $input['menuBlockerStaffCode']);
        if (strlen($code) < 4) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Staff code must be at least 4 characters.']);
            exit;
        }
        $settings['menuBlockerStaffCode'] = $code;
        $updated = true;
    }

    if (!$updated) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No valid settings to update']);
        exit;
    }

    $settings['updatedAt'] = date('Y-m-d H:i:s');
    $settings['updatedBy'] = 'admin_' . (string) ($auth['username'] ?? 'unknown');

    if (!saveSettingsValue($settingsFile, $settings)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save settings']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully',
        'data' => [
            'hotelWhatsappNo' => (string) ($settings['hotelWhatsappNo'] ?? ''),
            'menuBlockerStaffCode' => (string) ($settings['menuBlockerStaffCode'] ?? ''),
            'updatedAt' => (string) ($settings['updatedAt'] ?? ''),
        ]
    ], JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
