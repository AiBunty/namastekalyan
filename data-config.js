const NK_RUNTIME_HOST = (window.location && window.location.hostname) ? String(window.location.hostname).toLowerCase() : '';
const NK_RUNTIME_PROTOCOL = (window.location && window.location.protocol) ? String(window.location.protocol).toLowerCase() : '';
const NK_IS_LOCAL_RUNTIME = NK_RUNTIME_PROTOCOL === 'file:' || NK_RUNTIME_HOST === 'localhost' || NK_RUNTIME_HOST === '127.0.0.1';
const NK_LOCAL_PHP_API_BASE = 'http://localhost:8010/';
const NK_REMOTE_PHP_API_BASE = 'https://namastekalyan.asianwokandgrill.in/backend/';

window.NK_DATA_API = window.NK_DATA_API || {
  // PHP-only backend for both local and remote.
  // Keep future Apps Script references disabled via hash-quoted placeholders.
  legacyAppsScriptUrl: '#https://script.google.com/macros/s/YOUR_DEPLOYMENT_ID/exec',
  legacyAppsScriptBackupUrl: '#https://script.google.com/macros/s/YOUR_BACKUP_DEPLOYMENT_ID/exec',
  // Keep backward-compatible key name, but always route to PHP API.
  appsScriptUrl: NK_IS_LOCAL_RUNTIME ? NK_LOCAL_PHP_API_BASE : NK_REMOTE_PHP_API_BASE,
  phpApiUrl: NK_IS_LOCAL_RUNTIME ? NK_LOCAL_PHP_API_BASE : NK_REMOTE_PHP_API_BASE,
  // Backend migration is complete and validated, so route configured actions to PHP.
  enablePhpForSelectedActions: true,
  phpRoutedActions: [
    // Auth
    'auth_login', 'auth_logout', 'auth_me', 'auth_change_password',
    'auth_create_user', 'auth_set_user_status', 'auth_reset_password',
    'auth_set_user_permissions', 'auth_get_api_settings', 'auth_set_api_settings',
    'auth_list_users', 'auth_bootstrap_status',
    // Events
    'events_list', 'event_list', 'event_detail', 'event_popup',
    'create_event_order', 'confirm_event_payment', 'register_free_event',
    'resend_event_confirmation', 'request_event_cancellation',
    'admin_create_event', 'admin_update_event', 'admin_toggle_event',
    'admin_list_events', 'event_guest_report', 'event_transactions_report',
    'verify_event_qr', 'admin_preview_event_qr', 'admin_batch_checkin_event_qr',
    // Menu editor
    'admin_menu_editor_load', 'admin_menu_editor_save_changes',
    'admin_menu_editor_add_row', 'admin_menu_editor_delete_rows',
    'admin_menu_editor_set_visibility',
    // Cashier
    'admin_issue_cash_paid_pass', 'admin_request_cash_handover',
    'admin_request_cash_cancel', 'superadmin_approve_cash_handover',
    'superadmin_resolve_cash_cancel', 'admin_cash_summary', 'superadmin_cash_dashboard',
    // Leads / Spin & Win
    'submit_lead', 'verify', 'redeem', 'regen_coupon', 'regenerate_coupon',
    'counter', 'qr_report', 'qr_scan_client'
  ],
  hotelWhatsappNo: '919371519999',
  adminPasscode: '8442'
};

// Backward-compatible global endpoint consumed by legacy and blocker scripts.
window.APPS_SCRIPT_URL = window.NK_DATA_API && window.NK_DATA_API.appsScriptUrl
  ? String(window.NK_DATA_API.appsScriptUrl).trim()
  : '';

window.NK_DATA_API.resolveApiBaseForAction = function resolveApiBaseForAction(actionName) {
  const config = window.NK_DATA_API || {};
  const defaultBase = String(config.appsScriptUrl || '').trim();
  const phpBase = String(config.phpApiUrl || '').trim();

  // PHP is the only backend now (local + remote).
  return phpBase || defaultBase;
};