<?php

declare(strict_types=1);

namespace NK\Controllers;

class CashierController
{
    public static function issueCashPaidPass(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'admin_issue_cash_paid_pass is not implemented yet.'];
    }

    public static function requestCashHandover(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'admin_request_cash_handover is not implemented yet.'];
    }

    public static function requestCashCancel(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'admin_request_cash_cancel is not implemented yet.'];
    }

    public static function approveCashHandover(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'superadmin_approve_cash_handover is not implemented yet.'];
    }

    public static function resolveCashCancel(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'superadmin_resolve_cash_cancel is not implemented yet.'];
    }

    public static function adminCashSummary(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'admin_cash_summary is not implemented yet.'];
    }

    public static function superadminCashDashboard(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'superadmin_cash_dashboard is not implemented yet.'];
    }
}
