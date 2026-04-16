<?php

declare(strict_types=1);

namespace NK\Routes;

use NK\Controllers\AuthController;
use NK\Controllers\CashierController;
use NK\Controllers\EventController;
use NK\Controllers\LeadController;
use NK\Controllers\MenuController;
use NK\Controllers\WebhookController;

class ActionRouter
{
    /** POST action → [ControllerClass, method] */
    private static array $postActions = [

        // ── Auth ────────────────────────────────────────────────────────────────
        'auth_login'                    => [AuthController::class, 'login'],
        'auth_logout'                   => [AuthController::class, 'logout'],
        'auth_me'                       => [AuthController::class, 'me'],
        'auth_change_password'          => [AuthController::class, 'changePassword'],
        'auth_create_user'              => [AuthController::class, 'createUser'],
        'auth_set_user_status'          => [AuthController::class, 'setUserStatus'],
        'auth_reset_password'           => [AuthController::class, 'resetPassword'],
        'auth_set_user_permissions'     => [AuthController::class, 'setUserPermissions'],
        'auth_get_api_settings'         => [AuthController::class, 'getApiSettings'],
        'auth_set_api_settings'         => [AuthController::class, 'setApiSettings'],
        'auth_list_users'               => [AuthController::class, 'listUsers'],

        // ── Events (writes) ─────────────────────────────────────────────────────
        'register_free_event'           => [EventController::class, 'registerFreeEvent'],
        'create_event_order'            => [EventController::class, 'createEventOrder'],
        'confirm_event_payment'         => [EventController::class, 'confirmEventPayment'],
        'resend_event_confirmation'     => [EventController::class, 'resendEventConfirmation'],
        'request_event_cancellation'    => [EventController::class, 'requestEventCancellation'],
        'admin_create_event'            => [EventController::class, 'adminCreateEvent'],
        'admin_update_event'            => [EventController::class, 'adminUpdateEvent'],
        'admin_toggle_event'            => [EventController::class, 'adminToggleEvent'],
        'verify_event_qr'               => [EventController::class, 'verifyEventQr'],
        'admin_preview_event_qr'        => [EventController::class, 'adminPreviewEventQr'],
        'admin_batch_checkin_event_qr'  => [EventController::class, 'adminBatchCheckin'],

        // ── Menu editor ─────────────────────────────────────────────────────────
        'admin_menu_editor_load'             => [MenuController::class, 'load'],
        'admin_menu_editor_save_changes'     => [MenuController::class, 'saveChanges'],
        'admin_menu_editor_add_row'          => [MenuController::class, 'addRow'],
        'admin_menu_editor_delete_rows'      => [MenuController::class, 'deleteRows'],
        'admin_menu_editor_set_visibility'   => [MenuController::class, 'setVisibility'],

        // ── Cashier ─────────────────────────────────────────────────────────────
        'admin_issue_cash_paid_pass'         => [CashierController::class, 'issueCashPaidPass'],
        'admin_request_cash_handover'        => [CashierController::class, 'requestCashHandover'],
        'admin_request_cash_cancel'          => [CashierController::class, 'requestCashCancel'],
        'superadmin_approve_cash_handover'   => [CashierController::class, 'approveCashHandover'],
        'superadmin_resolve_cash_cancel'     => [CashierController::class, 'resolveCashCancel'],

        // ── Lead / Spin & Win ───────────────────────────────────────────────────
        'submit_lead'                   => [LeadController::class, 'submitLead'],
        'qr_scan_client'                => [LeadController::class, 'qrScanClient'],

        // ── Razorpay webhook ────────────────────────────────────────────────────
        'razorpay_webhook'              => [WebhookController::class, 'razorpayWebhook'],
    ];

    /** GET action → [ControllerClass, method] */
    private static array $getActions = [

        // ── Events (reads) ──────────────────────────────────────────────────────
        'events_list'               => [EventController::class, 'eventsList'],
        'event_list'                => [EventController::class, 'eventsList'],   // alias
        'event_popup'               => [EventController::class, 'eventPopup'],
        'event_detail'              => [EventController::class, 'eventDetail'],
        'event_guest_report'        => [EventController::class, 'eventGuestReport'],
        'event_transactions_report' => [EventController::class, 'eventTransactionsReport'],
        'admin_list_events'         => [EventController::class, 'adminListEvents'],

        // ── Cash (reads) ────────────────────────────────────────────────────────
        'admin_cash_summary'        => [CashierController::class, 'adminCashSummary'],
        'superadmin_cash_dashboard' => [CashierController::class, 'superadminCashDashboard'],

        // ── Lead (reads) ────────────────────────────────────────────────────────
        'verify'                    => [LeadController::class, 'verify'],
        'redeem'                    => [LeadController::class, 'redeem'],
        'regen_coupon'              => [LeadController::class, 'regenCoupon'],
        'regenerate_coupon'         => [LeadController::class, 'regenCoupon'],
        'counter'                   => [LeadController::class, 'counter'],
        'qr_report'                 => [LeadController::class, 'qrReport'],

        // ── Auth (reads) ────────────────────────────────────────────────────────
        'auth_bootstrap_status'     => [AuthController::class, 'bootstrapStatus'],
        'auth_list_users'           => [AuthController::class, 'listUsers'],
    ];

    /**
     * Dispatch an incoming request to the appropriate controller method.
     *
     * @param string $method HTTP method
     * @param string $action Action identifier
     * @param array  $body   Parsed request body
     * @param array  $query  Query string parameters
     * @return array Response payload
     */
    public static function dispatch(string $method, string $action, array $body, array $query): array
    {
        $action = strtolower(trim($action));

        if ($method === 'GET') {
            // Tab-based sheet reads used by menu.html and cocktail.html
            if (isset($query['tab'])) {
                return MenuController::getTab($query);
            }

            $route = self::$getActions[$action] ?? null;
        } else {
            // Some callers (e.g. webhook proxy) may pass action as query param on POST
            if ($action === '' && isset($query['action'])) {
                $action = strtolower(trim($query['action']));
            }

            $route = self::$postActions[$action] ?? null;
        }

        if (!$route) {
            return [
                'ok'      => false,
                'error'   => 'UNKNOWN_ACTION',
                'message' => "Unknown action: {$action}",
            ];
        }

        [$class, $methodName] = $route;
        return call_user_func([$class, $methodName], $body, $query);
    }
}
