<?php

declare(strict_types=1);

namespace NK\Support;

class Response
{
    /**
     * Send a JSON response and terminate.
     */
    public static function send(array $payload, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Build a success payload (does NOT send — caller must return it).
     */
    public static function ok(array $extra = []): array
    {
        return array_merge(['ok' => true], $extra);
    }

    /**
     * Build an error payload (does NOT send — caller must return it).
     */
    public static function fail(string $error, string $message = '', array $extra = []): array
    {
        $payload = ['ok' => false, 'error' => $error];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        return array_merge($payload, $extra);
    }
}
