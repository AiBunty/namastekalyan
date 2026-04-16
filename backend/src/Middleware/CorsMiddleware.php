<?php

declare(strict_types=1);

namespace NK\Middleware;

class CorsMiddleware
{
    private static function originMatches(string $requestOrigin, string $allowedOrigin): bool
    {
        if ($requestOrigin === '' || $allowedOrigin === '') {
            return false;
        }

        if (strcasecmp($requestOrigin, $allowedOrigin) === 0) {
            return true;
        }

        $requestParts = parse_url($requestOrigin);
        $allowedParts = parse_url($allowedOrigin);

        if (!is_array($requestParts) || !is_array($allowedParts)) {
            return false;
        }

        $requestScheme = strtolower((string) ($requestParts['scheme'] ?? ''));
        $allowedScheme = strtolower((string) ($allowedParts['scheme'] ?? ''));
        $requestHost = strtolower((string) ($requestParts['host'] ?? ''));
        $allowedHost = strtolower((string) ($allowedParts['host'] ?? ''));

        if ($requestScheme === '' || $allowedScheme === '' || $requestHost === '' || $allowedHost === '') {
            return false;
        }

        if ($requestScheme !== $allowedScheme || $requestHost !== $allowedHost) {
            return false;
        }

        $allowedPort = isset($allowedParts['port']) ? (int) $allowedParts['port'] : null;
        $requestPort = isset($requestParts['port']) ? (int) $requestParts['port'] : null;

        // If allowed origin has no explicit port, allow same host+scheme across local dev ports.
        if ($allowedPort === null) {
            return true;
        }

        return $allowedPort === $requestPort;
    }

    public static function handle(): void
    {
        $configuredOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*';
        $allowed = array_map('trim', explode(',', $configuredOrigins));

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $allowed, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif ($requestOrigin !== '') {
            foreach ($allowed as $allowedOrigin) {
                if (self::originMatches($requestOrigin, (string) $allowedOrigin)) {
                    header("Access-Control-Allow-Origin: {$requestOrigin}");
                    header('Vary: Origin');
                    break;
                }
            }
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        // Reply to preflight and stop
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
