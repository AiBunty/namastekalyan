(function (window) {
  'use strict';

  if (window.__NK_DATA_CONFIG_LOADED__) return;
  window.__NK_DATA_CONFIG_LOADED__ = true;

  const runtimeHost = (window.location && window.location.hostname)
    ? String(window.location.hostname).toLowerCase()
    : '';

  const runtimeProtocol = (window.location && window.location.protocol)
    ? String(window.location.protocol).toLowerCase()
    : '';

  const runtimePath = (window.location && window.location.pathname)
    ? String(window.location.pathname).toLowerCase()
    : '';

  const isLocalRuntime =
    runtimeProtocol === 'file:' ||
    runtimeHost === 'localhost' ||
    runtimeHost === '127.0.0.1';

  const runtimeOrigin = (window.location && window.location.origin)
    ? String(window.location.origin).trim()
    : '';

  const localPhpApiBase = runtimeProtocol === 'file:'
    ? (runtimePath.indexOf('/backend/') !== -1 ? 'http://localhost:8010/backend/' : 'http://localhost:8010/')
    : (runtimePath.indexOf('/backend/') !== -1 ? (runtimeOrigin + '/backend/') : (runtimeOrigin + '/backend/'));
  const remotePhpApiBase = 'https://namastekalyan.asianwokandgrill.in/backend/';
  const phpApiBase = isLocalRuntime ? localPhpApiBase : remotePhpApiBase;

  window.NK_DATA_API = window.NK_DATA_API || {};
  Object.assign(window.NK_DATA_API, {
    // PHP-only backend for both local and remote.
    // Legacy keys are kept only as inert aliases for compatibility.
    legacyAppsScriptUrl: '',
    legacyAppsScriptBackupUrl: '',
    // Keep backward-compatible key name, but always route to PHP API.
    appsScriptUrl: phpApiBase,
    phpApiUrl: phpApiBase,
    // Backend migration is complete and validated, so route configured actions to PHP.
    enablePhpForSelectedActions: true,
    phpRoutedActions: [
      // Auth
      'auth_login', 'auth_logout', 'auth_me', 'auth_change_password',
      'auth_create_user', 'auth_set_user_status', 'auth_reset_password',
      'auth_set_user_permissions', 'auth_get_api_settings', 'auth_set_api_settings',
      'auth_get_app_settings', 'auth_set_app_settings',
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
    hotelWhatsappNo: '919371519999'
  });

  // Backward-compatible global endpoint consumed by legacy and blocker scripts.
  window.APPS_SCRIPT_URL = String(window.NK_DATA_API.appsScriptUrl || '').trim();

  window.NK_DATA_API.resolveApiBaseForAction = function resolveApiBaseForAction() {
    const config = window.NK_DATA_API || {};
    const defaultBase = String(config.appsScriptUrl || '').trim();
    const phpBase = String(config.phpApiUrl || '').trim();
    // PHP is the only backend now (local + remote).
    return phpBase || defaultBase;
  };

  // Optional diagnostics (dev/local/diag=1) to trace runtime base resolution.
  try {
    const params = new URLSearchParams(window.location.search || '');
    const isDebug = params.get('diag') === '1' || isLocalRuntime;
    if (isDebug) {
      window.NK_DATA_API.debug = {
        isLocalRuntime: isLocalRuntime,
        runtimeHost: runtimeHost,
        phpApiBase: phpApiBase,
      };
      if (window.console && typeof window.console.info === 'function') {
        window.console.info('[NK_DATA_API] PHP-only base resolved', {
          phpApiBase: phpApiBase,
          appsScriptAlias: window.NK_DATA_API.appsScriptUrl,
          globalAlias: window.APPS_SCRIPT_URL
        });
      }
    }
  } catch (err) {
    // Ignore diagnostics failures.
  }
})(window);