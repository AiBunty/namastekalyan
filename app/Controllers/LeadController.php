<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Middleware\AuthMiddleware;
use NK\Services\CrmContactExportService;
use NK\Services\CrmLeadExportService;
use NK\Services\LeadService;

class LeadController
{
    public static function submitLead(array $body, array $query): array
    {
        $service = new LeadService();
        return $service->submitLead($body);
    }

    public static function completeSpin(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->completeSpin($payload);
    }

    public static function verify(array $body, array $query): array
    {
        $service = new LeadService();
        return $service->verify($query);
    }

    public static function redeem(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->redeem($payload);
    }

    public static function regenCoupon(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->regenCoupon($payload);
    }

    public static function counter(array $body, array $query): array
    {
        $service = new LeadService();
        return $service->counter();
    }

    public static function qrScanClient(array $body, array $query): array
    {
        $service = new LeadService();
        return $service->qrScanClient($body);
    }

    public static function qrReport(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->qrReport($payload);
    }

    public static function resolveQrRedirect(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->resolveQrRedirect($payload);
    }

    public static function dashboardStats(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->dashboardStats($payload);
    }

    public static function initSchema(array $body, array $query): array
    {
        $service = new LeadService();
        return $service->initSchema();
    }

    public static function ensureQrSheet(array $body, array $query): array
    {
        $service = new LeadService();
        return $service->ensureQrSheet();
    }

    public static function addTestQrScan(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->addTestQrScan($payload);
    }

    public static function addTest25Coupon(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->addTest25Coupon($payload);
    }

    public static function addTestLead(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->addTestLead($payload);
    }

    public static function adminCrmPanelStatus(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminCrmPanelStatus($payload);
    }

    public static function adminTestCrmSync(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminTestCrmSync($payload);
    }

    public static function adminDeleteCrmTestLead(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminDeleteCrmTestLead($payload);
    }

    public static function adminListCrmContacts(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminListCrmContacts($payload);
    }

    public static function adminListCrmPushLogs(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminListCrmPushLogs($payload);
    }

    public static function adminBackfillCrmContacts(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminBackfillCrmContacts($payload);
    }

    public static function adminCrmLeadsStatus(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminCrmLeadsStatus($payload);
    }

    public static function adminListCrmLeads(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->adminListCrmLeads($payload);
    }

    public static function adminExportCrmContacts(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $service = new CrmContactExportService();

        try {
            $filePath = $service->export([
                'search' => trim((string) ($payload['search'] ?? '')),
                'source' => trim((string) ($payload['source'] ?? '')),
                'syncStatus' => trim((string) ($payload['syncStatus'] ?? '')),
                'fromDate' => trim((string) ($payload['fromDate'] ?? '')),
                'toDate' => trim((string) ($payload['toDate'] ?? '')),
            ]);

            if (!headers_sent()) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="crm_contacts_' . date('Ymd_His') . '.xlsx"');
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: max-age=0');
                readfile($filePath);
                @unlink($filePath);
                exit;
            }

            return ['ok' => true, 'message' => 'Export generated but could not stream (headers already sent).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'EXPORT_ERROR', 'message' => $e->getMessage()];
        }
    }

    public static function adminExportCrmLeads(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $service = new CrmLeadExportService();

        try {
            $filePath = $service->export([
                'search' => trim((string) ($payload['search'] ?? '')),
                'source' => trim((string) ($payload['source'] ?? '')),
                'syncStatus' => trim((string) ($payload['syncStatus'] ?? '')),
                'leadStatus' => trim((string) ($payload['leadStatus'] ?? '')),
                'outcome' => trim((string) ($payload['outcome'] ?? '')),
                'fromDate' => trim((string) ($payload['fromDate'] ?? '')),
                'toDate' => trim((string) ($payload['toDate'] ?? '')),
            ]);

            if (!headers_sent()) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="crm_leads_' . date('Ymd_His') . '.xlsx"');
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: max-age=0');
                readfile($filePath);
                @unlink($filePath);
                exit;
            }

            return ['ok' => true, 'message' => 'Export generated but could not stream (headers already sent).'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'EXPORT_ERROR', 'message' => $e->getMessage()];
        }
    }

    public static function syncCrmByPhone(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->syncCrmByPhone($payload);
    }
}
