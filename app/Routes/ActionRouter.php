<?php

declare(strict_types=1);

namespace NK\Routes;

use NK\Controllers\AuthController;
use NK\Controllers\CashierController;
use NK\Controllers\EventController;
use NK\Controllers\ImportController;
use NK\Controllers\LeadController;
use NK\Controllers\MenuController;
use NK\Controllers\UtilityController;
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
        'auth_get_whatsapp_workspace'   => [AuthController::class, 'getWhatsAppWorkspace'],
        'auth_save_whatsapp_config'     => [AuthController::class, 'saveWhatsAppConfig'],
        'auth_sync_whatsapp_templates'  => [AuthController::class, 'syncWhatsAppTemplates'],
        'auth_save_whatsapp_mapping'    => [AuthController::class, 'saveWhatsAppEventMapping'],
        'auth_send_test_whatsapp_template' => [AuthController::class, 'sendTestWhatsAppTemplate'],
        'auth_save_whatsapp_template_draft' => [AuthController::class, 'saveWhatsAppTemplateDraft'],
        'auth_submit_whatsapp_template_draft' => [AuthController::class, 'submitWhatsAppTemplateDraft'],
        'auth_run_whatsapp_scheduler'   => [AuthController::class, 'runWhatsAppScheduler'],
        'auth_get_app_settings'         => [AuthController::class, 'getAppSettings'],
        'auth_set_app_settings'         => [AuthController::class, 'setAppSettings'],
        'auth_get_qr_redirect_settings' => [AuthController::class, 'getQrRedirectSettings'],
        'auth_set_qr_redirect_settings' => [AuthController::class, 'setQrRedirectSettings'],
        'auth_list_qr_redirects'        => [AuthController::class, 'listQrRedirects'],
        'auth_save_qr_redirect'         => [AuthController::class, 'saveQrRedirect'],
        'auth_set_qr_redirect_active'   => [AuthController::class, 'setQrRedirectActive'],
        'auth_delete_qr_redirect'       => [AuthController::class, 'deleteQrRedirect'],
        'auth_list_users'               => [AuthController::class, 'listUsers'],
        'auth_delete_user'              => [AuthController::class, 'deleteUser'],

        // ── Events (writes) ─────────────────────────────────────────────────────
        'send_event_otp'                => [EventController::class, 'sendEventOtp'],
        'verify_event_otp'              => [EventController::class, 'verifyEventOtp'],
        'register_free_event'           => [EventController::class, 'registerFreeEvent'],
        'create_event_order'            => [EventController::class, 'createEventOrder'],
        'confirm_event_payment'         => [EventController::class, 'confirmEventPayment'],
        'resend_event_confirmation'     => [EventController::class, 'resendEventConfirmation'],
        'request_event_cancellation'    => [EventController::class, 'requestEventCancellation'],
        'admin_create_event'            => [EventController::class, 'adminCreateEvent'],
        'admin_update_event'            => [EventController::class, 'adminUpdateEvent'],
        'admin_toggle_event'            => [EventController::class, 'adminToggleEvent'],
        'admin_delete_event'            => [EventController::class, 'adminDeleteEvent'],
        'admin_clone_event'             => [EventController::class, 'adminCloneEvent'],
        'admin_event_image_upload'      => [EventController::class, 'adminUploadEventImage'],
        'verify_event_qr'               => [EventController::class, 'verifyEventQr'],
        'admin_preview_event_qr'        => [EventController::class, 'adminPreviewEventQr'],
        'admin_batch_checkin_event_qr'  => [EventController::class, 'adminBatchCheckin'],

        // ── Menu editor (legacy menu_items table) ───────────────────────────────
        'admin_menu_editor_load'             => [MenuController::class, 'load'],
        'admin_menu_editor_save_changes'     => [MenuController::class, 'saveChanges'],
        'admin_menu_editor_add_row'          => [MenuController::class, 'addRow'],
        'admin_menu_editor_delete_rows'      => [MenuController::class, 'deleteRows'],
        'admin_menu_editor_set_visibility'   => [MenuController::class, 'setVisibility'],

        // ── Food menu admin editor (new food_menu_items table) ───────────────
        'admin_food_menu_editor_load'        => [MenuController::class, 'adminFoodLoad'],
        'admin_food_menu_editor_save'        => [MenuController::class, 'adminFoodSave'],
        'admin_food_menu_editor_add_row'     => [MenuController::class, 'adminFoodAddRow'],
        'admin_food_menu_editor_delete'      => [MenuController::class, 'adminFoodDeleteRows'],
        'admin_food_menu_editor_visible'     => [MenuController::class, 'adminFoodSetVisibility'],

        // ── Bar menu admin editor (new bar_menu_items table) ─────────────────
        'admin_bar_menu_editor_load'         => [MenuController::class, 'adminBarLoad'],
        'admin_bar_menu_editor_save'         => [MenuController::class, 'adminBarSave'],
        'admin_bar_menu_editor_add_row'      => [MenuController::class, 'adminBarAddRow'],
        'admin_bar_menu_editor_delete'       => [MenuController::class, 'adminBarDeleteRows'],
        'admin_bar_menu_editor_visible'      => [MenuController::class, 'adminBarSetVisibility'],
        'admin_menu_designer_load'           => [MenuController::class, 'designerLoad'],
        'admin_menu_designer_save_category_order' => [MenuController::class, 'designerSaveCategoryOrder'],
        'admin_menu_designer_save_item_order' => [MenuController::class, 'designerSaveItemOrder'],
        'admin_menu_designer_toggle_category' => [MenuController::class, 'designerToggleCategory'],
        'admin_menu_designer_toggle_item'    => [MenuController::class, 'designerToggleItem'],
        'admin_menu_designer_clone_category' => [MenuController::class, 'designerCloneCategory'],
        'admin_menu_editor_add_column'       => [MenuController::class, 'addColumn'],
        'admin_menu_editor_rename_column'    => [MenuController::class, 'renameColumn'],

        // ── Import / Export / Snapshots / Images ─────────────────────────────────
        'admin_import_preview'               => [ImportController::class, 'importPreview'],
        'admin_import_execute'               => [ImportController::class, 'importExecute'],
        'admin_export_xlsx'                  => [ImportController::class, 'exportXlsx'],
        'admin_snapshot_list'                => [ImportController::class, 'snapshotList'],
        'admin_snapshot_restore'             => [ImportController::class, 'snapshotRestore'],
        'admin_image_upload'                 => [ImportController::class, 'imageUpload'],
        'admin_menu_item_upload_image'        => [ImportController::class, 'uploadItemImage'],
        'admin_download_template'             => [ImportController::class, 'downloadTemplate'],

        // ── Food menu (new DB-backed import / export) ─────────────────────────
        'admin_food_menu_import_preview'     => [ImportController::class, 'foodImportPreview'],
        'admin_food_menu_import_execute'     => [ImportController::class, 'foodImportExecute'],
        'admin_food_menu_export'             => [ImportController::class, 'exportFoodXlsx'],
        'admin_food_menu_template'           => [ImportController::class, 'downloadFoodTemplate'],

        // ── Bar menu (new DB-backed import / export) ──────────────────────────
        'admin_bar_menu_import_preview'      => [ImportController::class, 'barImportPreview'],
        'admin_bar_menu_import_execute'      => [ImportController::class, 'barImportExecute'],
        'admin_bar_menu_export'              => [ImportController::class, 'exportBarXlsx'],
        'admin_bar_menu_template'            => [ImportController::class, 'downloadBarTemplate'],

        // ── Cashier ─────────────────────────────────────────────────────────────
        'admin_issue_cash_paid_pass'         => [CashierController::class, 'issueCashPaidPass'],
        'admin_request_cash_handover'        => [CashierController::class, 'requestCashHandover'],
        'admin_request_cash_cancel'          => [CashierController::class, 'requestCashCancel'],
        'superadmin_approve_cash_handover'   => [CashierController::class, 'approveCashHandover'],
        'superadmin_resolve_cash_cancel'     => [CashierController::class, 'resolveCashCancel'],

        // ── Lead / Spin & Win ───────────────────────────────────────────────────
        'submit_lead'                   => [LeadController::class, 'submitLead'],
        'complete_spin'                 => [LeadController::class, 'completeSpin'],
        'qr_scan_client'                => [LeadController::class, 'qrScanClient'],
        'add_test_qr_scan'              => [LeadController::class, 'addTestQrScan'],
        'test_qr_scan'                  => [LeadController::class, 'addTestQrScan'],
        'add_test_25_coupon'            => [LeadController::class, 'addTest25Coupon'],
        'test_25_coupon'                => [LeadController::class, 'addTest25Coupon'],
        'add_test_lead'                 => [LeadController::class, 'addTestLead'],
        'add-test-lead'                 => [LeadController::class, 'addTestLead'],
        'admin_crm_panel_status'        => [LeadController::class, 'adminCrmPanelStatus'],
        'admin_test_crm_sync'           => [LeadController::class, 'adminTestCrmSync'],
        'admin_delete_crm_test_lead'    => [LeadController::class, 'adminDeleteCrmTestLead'],
        'admin_list_crm_contacts'       => [LeadController::class, 'adminListCrmContacts'],
        'admin_list_crm_push_logs'      => [LeadController::class, 'adminListCrmPushLogs'],
        'admin_backfill_crm_contacts'   => [LeadController::class, 'adminBackfillCrmContacts'],
        'admin_export_crm_contacts'     => [LeadController::class, 'adminExportCrmContacts'],
        'admin_crm_leads_status'        => [LeadController::class, 'adminCrmLeadsStatus'],
        'admin_list_crm_leads'          => [LeadController::class, 'adminListCrmLeads'],
        'admin_export_crm_leads'        => [LeadController::class, 'adminExportCrmLeads'],
        'sync_crm_by_phone'             => [LeadController::class, 'syncCrmByPhone'],
        'sync-crm-by-phone'             => [LeadController::class, 'syncCrmByPhone'],

        // ── Razorpay webhook ────────────────────────────────────────────────────
        'razorpay_webhook'              => [WebhookController::class, 'razorpayWebhook'],
        'whatsapp_webhook'              => [WebhookController::class, 'whatsAppWebhook'],
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
        'admin_mail_log_report'     => [EventController::class, 'adminMailLogReport'],
        'admin_list_events'         => [EventController::class, 'adminListEvents'],
        'admin_event_list'          => [EventController::class, 'adminListEvents'],

        // ── Cash (reads) ────────────────────────────────────────────────────────
        'admin_cash_summary'        => [CashierController::class, 'adminCashSummary'],
        'superadmin_cash_dashboard' => [CashierController::class, 'superadminCashDashboard'],

        // ── Lead (reads) ────────────────────────────────────────────────────────
        'verify'                    => [LeadController::class, 'verify'],
        'redeem'                    => [LeadController::class, 'redeem'],
        'regen_coupon'              => [LeadController::class, 'regenCoupon'],
        'regenerate_coupon'         => [LeadController::class, 'regenCoupon'],
        'regen-coupon'              => [LeadController::class, 'regenCoupon'],
        'counter'                   => [LeadController::class, 'counter'],
        'qr_report'                 => [LeadController::class, 'qrReport'],
        'qr_redirect_resolve'       => [LeadController::class, 'resolveQrRedirect'],
        'admin_dashboard_stats'     => [LeadController::class, 'dashboardStats'],
        'init_schema'               => [LeadController::class, 'initSchema'],
        'schema'                    => [LeadController::class, 'initSchema'],
        'ensure_qr_sheet'           => [LeadController::class, 'ensureQrSheet'],
        'init_qr_sheet'             => [LeadController::class, 'ensureQrSheet'],
        'create_qr_sheet'           => [LeadController::class, 'ensureQrSheet'],
        'create_test_paid_tx'       => [UtilityController::class, 'createTestPaidTx'],
        'seed_test_paid_tx'         => [UtilityController::class, 'createTestPaidTx'],
        'download_qr_code'          => [UtilityController::class, 'downloadQrCode'],
        'qr_scan_report_html'       => [UtilityController::class, 'qrScanReportHtml'],
        'qr-scan-report-html'       => [UtilityController::class, 'qrScanReportHtml'],
        'migrate_events_sheet_format' => [UtilityController::class, 'migrateEventsSheetFormat'],
        'migrate_event_sheet_format' => [UtilityController::class, 'migrateEventsSheetFormat'],
        'reset_events_sheet_format' => [UtilityController::class, 'resetEventsSheetFormat'],
        'reset_events_data'         => [UtilityController::class, 'resetEventsSheetFormat'],
        'seed_events_sample'        => [UtilityController::class, 'seedEventsSample'],
        'seed_event_sample'         => [UtilityController::class, 'seedEventsSample'],
        'seed_dj_events_apr_2026'   => [UtilityController::class, 'seedDjEvents'],
        'seed_dj_events'            => [UtilityController::class, 'seedDjEvents'],
        'seed_paid_event_sample'    => [UtilityController::class, 'seedPaidEventSample'],
        'seed_paid_event'           => [UtilityController::class, 'seedPaidEventSample'],
        'send_test_event_email'     => [UtilityController::class, 'sendTestEventEmail'],
        'test_event_email'          => [UtilityController::class, 'sendTestEventEmail'],

        // ── Auth (reads) ────────────────────────────────────────────────────────
        'auth_bootstrap_status'     => [AuthController::class, 'bootstrapStatus'],
        'auth_me'                   => [AuthController::class, 'me'],
        'auth_list_users'           => [AuthController::class, 'listUsers'],
        'whatsapp_webhook'          => [WebhookController::class, 'whatsAppWebhook'],

        // ── Public menu reads (new DB-backed) ────────────────────────────────
        'food_menu_items'           => [MenuController::class, 'publicFoodMenu'],
        'bar_menu_items'            => [MenuController::class, 'publicBarMenu'],
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
