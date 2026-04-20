<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use NK\Support\Logger;

header('Content-Type: application/json; charset=utf-8');

function diagnostic_log(array $payload): void
{
    $logDir = NK_BASE_DIR . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $line = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }

    @file_put_contents($logDir . '/connection-diagnostic.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function dns_probe(string $host): array
{
    $host = trim($host);
    if ($host === '') {
        return [
            'ok' => false,
            'message' => 'Host is not configured.',
        ];
    }

    $resolved = gethostbyname($host);
    $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
    $ok = $isIp || $resolved !== $host;

    return [
        'ok' => $ok,
        'host' => $host,
        'resolved' => $ok ? $resolved : null,
        'message' => $ok ? 'DNS resolution succeeded.' : 'DNS resolution failed for configured host.',
    ];
}

function socket_probe(string $host, int $port, float $timeout = 10.0): array
{
    $errno = 0;
    $errstr = '';
    $target = sprintf('tcp://%s:%d', $host, $port);
    $stream = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

    if ($stream === false) {
        return [
            'ok' => false,
            'target' => $target,
            'errno' => $errno,
            'message' => $errstr !== '' ? $errstr : 'Socket connection failed.',
        ];
    }

    stream_set_timeout($stream, (int) ceil($timeout));
    fclose($stream);

    return [
        'ok' => true,
        'target' => $target,
        'errno' => 0,
        'message' => 'TCP socket connection succeeded.',
    ];
}

function ftp_read_response($stream): array
{
    $lines = [];
    $code = null;

    while (!feof($stream)) {
        $line = fgets($stream, 4096);
        if ($line === false) {
            break;
        }

        $line = rtrim($line, "\r\n");
        if ($line === '') {
            continue;
        }

        $lines[] = $line;
        if (preg_match('/^(\d{3})([\s-])(.*)$/', $line, $matches) === 1) {
            $code = (int) $matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        }
    }

    return [
        'code' => $code,
        'lines' => $lines,
    ];
}

function ftp_send_command($stream, string $command): array
{
    fwrite($stream, $command . "\r\n");
    return ftp_read_response($stream);
}

function database_diagnostic(): array
{
    $host = trim((string) ($_ENV['DB_HOST'] ?? ''));
    $port = (int) ($_ENV['DB_PORT'] ?? 3306);
    $name = trim((string) ($_ENV['DB_NAME'] ?? ''));
    $user = trim((string) ($_ENV['DB_USER'] ?? ''));

    $result = [
        'label' => 'MySQL',
        'host' => $host,
        'port' => $port,
        'database' => $name,
        'userConfigured' => $user !== '',
        'dns' => dns_probe($host),
    ];

    if (!$result['dns']['ok']) {
        $result['ok'] = false;
        $result['stage'] = 'dns';
        $result['message'] = 'Database host cannot be resolved from this server.';
        return $result;
    }

    $result['socket'] = socket_probe($host, $port, 12.0);
    if (!$result['socket']['ok']) {
        $result['ok'] = false;
        $result['stage'] = 'socket';
        $result['message'] = 'Database port is not reachable.';
        return $result;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO(
            $dsn,
            (string) ($_ENV['DB_USER'] ?? ''),
            (string) ($_ENV['DB_PASS'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 12,
            ]
        );

        $serverVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $row = $pdo->query('SELECT DATABASE() AS active_database, NOW() AS server_time')->fetch();

        $result['ok'] = true;
        $result['stage'] = 'pdo';
        $result['message'] = 'Database login succeeded.';
        $result['serverVersion'] = $serverVersion;
        $result['activeDatabase'] = (string) ($row['active_database'] ?? '');
        $result['serverTime'] = (string) ($row['server_time'] ?? '');
        return $result;
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['stage'] = 'pdo';
        $result['message'] = 'Database login failed.';
        $result['errorCode'] = (string) $e->getCode();
        $result['errorDetail'] = $e->getMessage();
        return $result;
    }
}

function ftp_diagnostic(): array
{
    $host = trim((string) ($_ENV['FTP_HOST'] ?? ''));
    $port = 21;
    $user = trim((string) ($_ENV['FTP_USER'] ?? ''));
    $pass = (string) ($_ENV['FTP_PASS'] ?? '');
    $remotePath = trim((string) ($_ENV['FTP_REMOTE_PATH'] ?? ''));

    $result = [
        'label' => 'FTP',
        'host' => $host,
        'port' => $port,
        'remotePath' => $remotePath,
        'userConfigured' => $user !== '',
        'passConfigured' => $pass !== '',
        'dns' => dns_probe($host),
    ];

    if (!$result['dns']['ok']) {
        $result['ok'] = false;
        $result['stage'] = 'dns';
        $result['message'] = 'FTP host cannot be resolved from this server.';
        return $result;
    }

    $result['socket'] = socket_probe($host, $port, 12.0);
    if (!$result['socket']['ok']) {
        $result['ok'] = false;
        $result['stage'] = 'socket';
        $result['message'] = 'FTP port is not reachable.';
        return $result;
    }

    $errno = 0;
    $errstr = '';
    $stream = @stream_socket_client(sprintf('tcp://%s:%d', $host, $port), $errno, $errstr, 12.0, STREAM_CLIENT_CONNECT);
    if ($stream === false) {
        $result['ok'] = false;
        $result['stage'] = 'connect';
        $result['message'] = $errstr !== '' ? $errstr : 'FTP socket negotiation failed.';
        $result['errno'] = $errno;
        return $result;
    }

    stream_set_timeout($stream, 12);
    $transcript = [];

    try {
        $banner = ftp_read_response($stream);
        $transcript[] = ['step' => 'banner', 'code' => $banner['code'], 'lines' => $banner['lines']];
        if (($banner['code'] ?? 0) < 200 || ($banner['code'] ?? 0) >= 400) {
            $result['ok'] = false;
            $result['stage'] = 'banner';
            $result['message'] = 'FTP server banner was not accepted.';
            $result['transcript'] = $transcript;
            return $result;
        }

        $userResponse = ftp_send_command($stream, 'USER ' . $user);
        $transcript[] = ['step' => 'user', 'code' => $userResponse['code'], 'lines' => $userResponse['lines']];
        if (($userResponse['code'] ?? 0) === 331) {
            $passResponse = ftp_send_command($stream, 'PASS ' . $pass);
            $transcript[] = ['step' => 'pass', 'code' => $passResponse['code'], 'lines' => $passResponse['lines']];
            if (($passResponse['code'] ?? 0) >= 400) {
                $result['ok'] = false;
                $result['stage'] = 'login';
                $result['message'] = 'FTP password was rejected.';
                $result['transcript'] = $transcript;
                ftp_send_command($stream, 'QUIT');
                fclose($stream);
                return $result;
            }
        } elseif (($userResponse['code'] ?? 0) >= 400) {
            $result['ok'] = false;
            $result['stage'] = 'login';
            $result['message'] = 'FTP username was rejected.';
            $result['transcript'] = $transcript;
            ftp_send_command($stream, 'QUIT');
            fclose($stream);
            return $result;
        }

        $pwdResponse = ftp_send_command($stream, 'PWD');
        $transcript[] = ['step' => 'pwd', 'code' => $pwdResponse['code'], 'lines' => $pwdResponse['lines']];

        if ($remotePath !== '') {
            $cwdResponse = ftp_send_command($stream, 'CWD ' . $remotePath);
            $transcript[] = ['step' => 'cwd', 'code' => $cwdResponse['code'], 'lines' => $cwdResponse['lines']];
            if (($cwdResponse['code'] ?? 0) >= 400) {
                $result['ok'] = false;
                $result['stage'] = 'path';
                $result['message'] = 'FTP login worked, but the configured remote path was rejected.';
                $result['transcript'] = $transcript;
                ftp_send_command($stream, 'QUIT');
                fclose($stream);
                return $result;
            }
        }

        $quitResponse = ftp_send_command($stream, 'QUIT');
        $transcript[] = ['step' => 'quit', 'code' => $quitResponse['code'], 'lines' => $quitResponse['lines']];
        fclose($stream);

        $result['ok'] = true;
        $result['stage'] = 'session';
        $result['message'] = 'FTP login and working-directory check succeeded.';
        $result['transcript'] = $transcript;
        return $result;
    } catch (Throwable $e) {
        fclose($stream);
        $result['ok'] = false;
        $result['stage'] = 'session';
        $result['message'] = 'FTP command session failed.';
        $result['errorCode'] = (string) $e->getCode();
        $result['errorDetail'] = $e->getMessage();
        $result['transcript'] = $transcript;
        return $result;
    }
}

$profile = trim((string) ($_ENV['NK_ENV_PROFILE'] ?? 'unknown'));
$startedAt = date('c');
$db = database_diagnostic();
$ftp = ftp_diagnostic();

$payload = [
    'ok' => ($db['ok'] ?? false) && ($ftp['ok'] ?? false),
    'generatedAt' => $startedAt,
    'profile' => $profile,
    'appEnv' => (string) ($_ENV['APP_ENV'] ?? ''),
    'appUrl' => (string) ($_ENV['APP_URL'] ?? ''),
    'siteUrl' => (string) ($_ENV['APP_PUBLIC_SITE_URL'] ?? ''),
    'database' => $db,
    'ftp' => $ftp,
];

Logger::info('Server connection diagnostic executed', [
    'profile' => $profile,
    'database_ok' => (bool) ($db['ok'] ?? false),
    'database_stage' => (string) ($db['stage'] ?? ''),
    'ftp_ok' => (bool) ($ftp['ok'] ?? false),
    'ftp_stage' => (string) ($ftp['stage'] ?? ''),
]);

diagnostic_log($payload);
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);