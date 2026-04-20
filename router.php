<?php
/**
 * PHP built-in server router for local development.
 * Usage: php -S localhost:3000 router.php
 *
 * Routes:
 *   /backend/  → root index.php (PHP API)
 *   everything else → public/ (static frontend)
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$normalizedUri = preg_replace('#^/public(?=/|$)#', '', (string) $uri);
if ($normalizedUri === '' || $normalizedUri === false) {
    $normalizedUri = '/';
}
$decodedUri = rawurldecode($normalizedUri);

// ── API: route /backend/* to root index.php ──────────────────────────────────
if (strpos($uri, '/backend') === 0) {
    require __DIR__ . '/index.php';
    return true;
}

// ── API: route any request with ?action= query string to PHP
//    (mirrors Apache .htaccess RewriteCond %{QUERY_STRING} action=)
$queryString = $_SERVER['QUERY_STRING'] ?? '';
if (preg_match('/(^|&)action=/i', $queryString)) {
    require __DIR__ . '/index.php';
    return true;
}

// ── API: route POST/PUT/PATCH/DELETE requests where no static file exists → PHP
//    (catches form-encoded API calls that POST to root or a non-file path)
$reqMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($reqMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $targetFile = __DIR__ . '/public' . $decodedUri;
    if (!file_exists($targetFile) || is_dir($targetFile)) {
        require __DIR__ . '/index.php';
        return true;
    }
}

// ── Serve root PHP files directly (e.g. api_settings.php) ───────────────────
//    Allows standalone PHP endpoints that live at the project root.
if (preg_match('/^\/[\w\-]+\.php$/', $uri)) {
    $rootPhpFile = __DIR__ . $uri;
    if (file_exists($rootPhpFile) && !is_dir($rootPhpFile)) {
        require $rootPhpFile;
        return true;
    }
}

// ── Static: map request path into public/ ────────────────────────────────────
$file = __DIR__ . '/public' . $decodedUri;

// Directory → serve index.html inside it
if (is_dir($file)) {
    $index = rtrim($file, '/') . '/index.html';
    if (file_exists($index)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($index);
        return true;
    }
}

// Exact file → detect MIME type and serve
if (file_exists($file) && !is_dir($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimeMap = [
        'html'  => 'text/html; charset=UTF-8',
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
        'ogg'   => 'video/ogg',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'xml'   => 'application/xml',
        'txt'   => 'text/plain',
        'pdf'   => 'application/pdf',
    ];
    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}

// ── Public QR slug routes: serve the shared scan page for /qr/{slug} ─────────
if (preg_match('#^/qr/[^/]+/?$#', $decodedUri)) {
    $scanPage = __DIR__ . '/public/qr/scan.html';
    if (is_file($scanPage)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($scanPage);
        return true;
    }
}

// Fallback → public/index.html
$fallback = __DIR__ . '/public/index.html';
if (file_exists($fallback)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($fallback);
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
