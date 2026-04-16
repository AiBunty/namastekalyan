<?php

declare(strict_types=1);

namespace NK\Middleware;

class CorsMiddleware
{
    public static function handle(): void
    {
        $configuredOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*';
        $allowed = array_map('trim', explode(',', $configuredOrigins));

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $allowed, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif ($requestOrigin !== '' && in_array($requestOrigin, $allowed, true)) {
            header("Access-Control-Allow-Origin: {$requestOrigin}");
            header('Vary: Origin');
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
