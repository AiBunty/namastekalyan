<?php

declare(strict_types=1);

namespace NK\Controllers;

class UtilityController
{
    public static function createTestPaidTx(array $body, array $query): array
    {
        return self::deprecated('create_test_paid_tx');
    }

    public static function downloadQrCode(array $body, array $query): array
    {
        return self::deprecated('download_qr_code');
    }

    public static function qrScanReportHtml(array $body, array $query): array
    {
        return self::deprecated('qr_scan_report_html');
    }

    public static function migrateEventsSheetFormat(array $body, array $query): array
    {
        return self::deprecated('migrate_events_sheet_format');
    }

    public static function resetEventsSheetFormat(array $body, array $query): array
    {
        return self::deprecated('reset_events_sheet_format');
    }

    public static function seedEventsSample(array $body, array $query): array
    {
        return self::deprecated('seed_events_sample');
    }

    public static function seedDjEvents(array $body, array $query): array
    {
        return self::deprecated('seed_dj_events_apr_2026');
    }

    public static function seedPaidEventSample(array $body, array $query): array
    {
        return self::deprecated('seed_paid_event_sample');
    }

    public static function sendTestEventEmail(array $body, array $query): array
    {
        return self::deprecated('send_test_event_email');
    }

    private static function deprecated(string $action): array
    {
        return [
            'ok' => false,
            'action' => $action,
            'error' => 'LEGACY_UTILITY_DISABLED',
            'message' => 'This legacy Apps Script utility action is disabled in PHP-only runtime.',
            'phpOnly' => true,
        ];
    }
}
