<?php

declare(strict_types=1);

namespace NK\Support;

class SiteUrl
{
    private const DEFAULT_PUBLIC_ORIGIN = 'https://namastekalyan.asianwokandgrill.in';

    private const LOCAL_ROUTE_MAP = [
        'home' => '',
        'menu' => 'public/menu/',
        'cocktail' => 'public/cocktails/cocktail.html',
        'admin' => 'public/admin/',
    ];

    private const LIVE_ROUTE_MAP = [
        'home' => '',
        'menu' => 'menu.html',
        'cocktail' => 'cocktail.html',
        'admin' => 'admin/',
    ];

    public static function resolve(string $key): string
    {
        $routeKey = strtolower(trim($key));
        $path = self::LIVE_ROUTE_MAP[$routeKey] ?? self::LIVE_ROUTE_MAP['home'];

        return self::buildPublicUrl($path);
    }

    public static function resolveRuntime(string $key): string
    {
        $routeKey = strtolower(trim($key));
        $routeMap = self::isLocalRequest() ? self::LOCAL_ROUTE_MAP : self::LIVE_ROUTE_MAP;
        $path = $routeMap[$routeKey] ?? $routeMap['home'];

        return self::buildRuntimeUrl($path);
    }

    private static function isLocalRequest(): bool
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
        if ($host === '') {
            return false;
        }

        $hostOnly = preg_replace('/:\d+$/', '', $host) ?: $host;

        return in_array($hostOnly, ['localhost', '127.0.0.1', '::1'], true);
    }

    private static function buildPublicUrl(string $path): string
    {
        $origin = self::publicOrigin();
        if ($path === '') {
            return $origin . '/';
        }

        return $origin . '/' . ltrim($path, '/');
    }

    private static function buildRuntimeUrl(string $path): string
    {
        $origin = self::detectOrigin();
        if ($origin === '') {
            return $path;
        }

        if ($path === '') {
            return $origin . '/';
        }

        return $origin . '/' . ltrim($path, '/');
    }

    private static function publicOrigin(): string
    {
        $configured = trim((string) ($_ENV['APP_PUBLIC_SITE_URL'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return self::DEFAULT_PUBLIC_ORIGIN;
    }

    private static function detectOrigin(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '') {
            return '';
        }

        $https = strtolower(trim((string) ($_SERVER['HTTPS'] ?? '')));
        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));

        $scheme = 'http';
        if ($https !== '' && $https !== 'off') {
            $scheme = 'https';
        } elseif ($forwardedProto !== '') {
            $scheme = $forwardedProto;
        } elseif ($requestScheme !== '') {
            $scheme = $requestScheme;
        }

        return $scheme . '://' . $host;
    }
}