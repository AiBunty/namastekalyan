<?php

declare(strict_types=1);

namespace NK\Support;

class Logger
{
    private static function write(string $level, string $message, array $context = []): void
    {
        $logDir = NK_BASE_DIR . '/logs';

        if (!is_dir($logDir)) {
            // Suppress: if the dir can't be created we skip logging rather than crashing
            @mkdir($logDir, 0750, true);
        }

        if (!is_dir($logDir)) {
            return;
        }

        $entry = [
            'time'    => date('Y-m-d H:i:s'),
            'level'   => strtoupper($level),
            'message' => $message,
        ];

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        $logFile = $logDir . '/' . date('Y-m-d') . '.log';
        @file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (($_ENV['APP_ENV'] ?? '') === 'development') {
            self::write('debug', $message, $context);
        }
    }
}
