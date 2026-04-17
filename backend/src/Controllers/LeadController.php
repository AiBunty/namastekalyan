<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Services\LeadService;

class LeadController
{
    public static function submitLead(array $body, array $query): array
    {
        $service = new LeadService();
        return $service->submitLead($body);
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

    public static function syncCrmByPhone(array $body, array $query): array
    {
        $service = new LeadService();
        $payload = array_merge($query, $body);
        return $service->syncCrmByPhone($payload);
    }
}
