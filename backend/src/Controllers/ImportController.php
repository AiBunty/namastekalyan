<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Middleware\AuthMiddleware;
use NK\Repositories\MenuRepository;
use NK\Services\ImageService;
use NK\Services\ImportService;
use NK\Support\Response;

class ImportController
{
    // ─────────────────────────────────────────────────────── Import / Export ──

    /**
     * POST admin_import_preview
     * Expects multipart: file=<xlsx>, sheetType=food|bar
     */
    public static function importPreview(array $body, array $query): array
    {
        $auth = AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = self::resolveSheetType($body, $query);
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid or missing sheetType.'];
        }

        $tmpPath = self::getUploadedFilePath('file');
        if ($tmpPath === null) {
            return ['ok' => false, 'error' => 'NO_FILE', 'message' => 'No file uploaded. Send file as multipart field "file".'];
        }

        try {
            $service = new ImportService();
            return $service->previewImport($tmpPath, $sheetType);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'IMPORT_ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * POST admin_import_execute
     * Expects multipart: file=<xlsx>, sheetType=food|bar, takeSnapshot=1|0
     */
    public static function importExecute(array $body, array $query): array
    {
        $auth = AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = self::resolveSheetType($body, $query);
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid or missing sheetType.'];
        }

        // Execute accepts a pre-saved server-side path from a prior preview call (JSON POST),
        // or a fresh file upload (multipart). Both paths are validated to be inside storage/tmp.
        $tmpPath = self::resolveImportPath($body);
        if ($tmpPath === null) {
            return ['ok' => false, 'error' => 'NO_FILE', 'message' => 'No file available. Upload a file or run preview first.'];
        }

        $takeSnapshot = !isset($body['takeSnapshot']) || !in_array((string) $body['takeSnapshot'], ['0', 'false', 'no'], true);

        try {
            $service = new ImportService();
            return $service->executeImport($tmpPath, $sheetType, $takeSnapshot);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'IMPORT_ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * POST admin_export_xlsx
     * Streams the XLSX file back as a download.
     */
    public static function exportXlsx(array $body, array $query): array
    {
        $auth = AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = self::resolveSheetType($body, $query);
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid or missing sheetType.'];
        }

        try {
            $service  = new ImportService();
            $filePath = $service->exportToXlsx($sheetType);

            // Stream file directly — bypass Response::send()
            if (!headers_sent()) {
                $fileName = $sheetType . '_menu_' . date('Ymd_His') . '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: max-age=0');
                readfile($filePath);
                @unlink($filePath);
                exit;
            }

            // Headers already sent — return a link instead
            return ['ok' => true, 'message' => 'Export generated but could not stream (headers already sent).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'EXPORT_ERROR', 'message' => $e->getMessage()];
        }
    }

    // ───────────────────────────────────────────────────────────── Snapshots ──

    /**
     * POST admin_snapshot_list
     */
    public static function snapshotList(array $body, array $query): array
    {
        $auth = AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $repo      = new MenuRepository();
        $sheetType = self::resolveSheetType($body, $query);

        // If no sheetType provided, return all snapshots across all sheet types
        $snapshots = $sheetType !== null
            ? $repo->listSnapshots($sheetType)
            : $repo->listAllSnapshots();

        return [
            'ok'        => true,
            'sheetType' => $sheetType,
            'snapshots' => $snapshots,
        ];
    }

    /**
     * POST admin_snapshot_restore
     * Body: { snapshotId: int, sheetType: string }
     */
    public static function snapshotRestore(array $body, array $query): array
    {
        $auth = AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $snapshotId = (int) ($body['snapshotId'] ?? 0);
        if ($snapshotId <= 0) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'snapshotId is required.'];
        }

        $repo = new MenuRepository();

        // Look up the snapshot to determine its sheet type (caller need not provide it)
        $snap = $repo->getSnapshot($snapshotId);
        if (!$snap) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => "Snapshot #{$snapshotId} not found."];
        }
        $sheetType = (string) ($snap['sheet_type'] ?? '');
        if (!in_array($sheetType, ['food', 'bar'], true)) {
            return ['ok' => false, 'error' => 'INVALID_SNAPSHOT', 'message' => 'Snapshot has an invalid sheet type.'];
        }

        // Safety: take a snapshot of the current state before overwriting
        $repo->createSnapshot($sheetType, 'Pre-restore backup', 'manual', $auth['user']['username'] ?? 'admin');

        $restored = $repo->restoreSnapshot($snapshotId, $sheetType);

        return [
            'ok'        => true,
            'sheetType' => $sheetType,
            'restored'  => $restored,
            'message'   => "Restored {$restored} rows from snapshot #{$snapshotId}.",
        ];
    }

    // ────────────────────────────────────────────────────────── Image upload ──

    /**
     * POST admin_image_upload
     * Expects multipart: file=<zip>, sheetType=food|bar
     */
    public static function imageUpload(array $body, array $query): array
    {
        $auth = AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = self::resolveSheetType($body, $query);
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid or missing sheetType.'];
        }

        $tmpPath = self::getUploadedFilePath('file');
        if ($tmpPath === null) {
            return ['ok' => false, 'error' => 'NO_FILE', 'message' => 'No file uploaded. Send ZIP as multipart field "file".'];
        }

        try {
            $service = new ImageService();
            return $service->processZipUpload($tmpPath, $sheetType);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'IMAGE_UPLOAD_ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * POST admin_menu_item_upload_image
     * Upload a single image for a specific menu item.
     * Expects multipart: file=<image>, itemId=<int>, sheetType=food|bar
     */
    public static function uploadItemImage(array $body, array $query): array
    {
        $auth = AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = self::resolveSheetType($body, $query);
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid or missing sheetType.'];
        }

        $itemId = (int) ($body['itemId'] ?? $body['item_id'] ?? 0);
        if ($itemId <= 0) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'itemId is required.'];
        }

        $tmpPath = self::getUploadedFilePath('file');
        if ($tmpPath === null) {
            return ['ok' => false, 'error' => 'NO_FILE', 'message' => 'No image uploaded. Send as multipart field "file".'];
        }

        try {
            $service = new ImageService();
            return $service->processSingleUpload($tmpPath, $itemId, $sheetType);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'IMAGE_UPLOAD_ERROR', 'message' => $e->getMessage()];
        }
    }

    /**
     * POST admin_download_template
     * Download an import template XLSX with the current schema headers + one sample row.
     * Expects: sheetType=food|bar
     */
    public static function downloadTemplate(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = self::resolveSheetType($payload, []);
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid or missing sheetType.'];
        }

        try {
            $service  = new ImportService();
            $filePath = $service->buildTemplateXlsx($sheetType);

            if (!headers_sent()) {
                $fileName = $sheetType . '_menu_template.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: max-age=0');
                readfile($filePath);
                @unlink($filePath);
                exit;
            }

            return ['ok' => true, 'message' => 'Template generated but headers already sent.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'TEMPLATE_ERROR', 'message' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────── Helpers ──

    /**
     * Resolve the import file path from either:
     *  (a) a pre-saved server path in $body['tmpPath'] — must be inside storage/tmp/
     *  (b) a freshly uploaded $_FILES['file']
     */
    private static function resolveImportPath(array $body): ?string
    {
        // (a) Server-side path from a prior preview call
        if (!empty($body['tmpPath'])) {
            $raw = (string) $body['tmpPath'];
            $storageDir = realpath(__DIR__ . '/../../storage/tmp');
            $candidate  = realpath($raw);

            // Security: reject paths outside storage/tmp
            if ($storageDir && $candidate && str_starts_with($candidate, $storageDir . DIRECTORY_SEPARATOR)) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        // (b) Fresh multipart upload
        return self::getUploadedFilePath('file');
    }

    private static function resolveSheetType(array $body, array $query): ?string
    {
        $raw = strtolower(trim((string) ($body['sheetType'] ?? $body['sheetName'] ?? $query['sheetType'] ?? $query['sheetName'] ?? '')));
        if ($raw === 'food' || $raw === 'awgnk menu') {
            return 'food';
        }
        if ($raw === 'bar' || $raw === 'bar menu nk') {
            return 'bar';
        }
        return null;
    }

    /**
     * Return the temp path of an uploaded file, or null if not present / invalid.
     * Validates: file exists, no upload error, MIME is either xlsx or zip.
     */
    private static function getUploadedFilePath(string $fieldName): ?string
    {
        if (!isset($_FILES[$fieldName])) {
            return null;
        }

        $file  = $_FILES[$fieldName];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return null;
        }

        return $tmpPath;
    }
}
