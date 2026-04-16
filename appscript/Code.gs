const SPREADSHEET_ID = '15-fEg0mC9oUcYi6WKW9hyrrtPyiLAvQk2Jhbk4uWYw8';
const LEADS_SHEET_NAME = 'Leads';
const QR_SCANS_SHEET_NAME = 'QR Scans';
const EVENTS_SHEET_NAME = 'EVENTS';
const EVENT_TRANSACTIONS_SHEET_NAME = 'EVENT_TRANSACTIONS';
const ADMIN_CASH_LEDGER_SHEET_NAME = 'ADMIN_CASH_LEDGER';
const SUPERADMIN_CASH_LEDGER_SHEET_NAME = 'SUPERADMIN_CASH_LEDGER';
const USERS_SHEET_NAME = 'Users';
const AUTH_AUDIT_SHEET_NAME = 'AuthAudit';
const AUTH_REVOKED_TOKENS_SHEET_NAME = 'AuthRevokedTokens';
const CRM_API_URL = 'https://admin.aibunty.com/api/automations/69db61c17e52a/execute';
const SPIN_COOLDOWN_HOURS = 24;
const ADMIN_PANEL_PASSCODE = '8442';
const AUTH_TOKEN_HOURS = 8;
const AUTH_LOCKOUT_MAX_ATTEMPTS = 5;
const AUTH_LOCKOUT_MINUTES = 15;
const AUTH_PASSWORD_MIN_LENGTH = 8;
const ADMIN_PERMISSION_KEYS = [
  'dashboard',
  'cashier',
  'verification',
  'eventGuests',
  'eventScanner',
  'eventManagement',
  'menuEditor',
  'cashApprovals',
  'userManagement'
];
const ADMIN_DEFAULT_PERMISSIONS = ['dashboard', 'cashier', 'verification', 'eventGuests', 'eventScanner', 'eventManagement', 'menuEditor'];
const MENU_EDITOR_ALLOWED_SHEETS = ['AWGNK MENU', 'BAR MENU NK'];
const MENU_EDITOR_VISIBILITY_HEADER = 'Availability';
const MANAGED_SCRIPT_SETTING_DEFS = [
  { key: 'RAZORPAY_KEY_ID', label: 'Razorpay Key ID', secret: false },
  { key: 'RAZORPAY_KEY_SECRET', label: 'Razorpay Key Secret', secret: true },
  { key: 'RAZORPAY_WEBHOOK_SECRET', label: 'Razorpay Webhook Secret', secret: true },
  { key: 'RAZORPAY_WEBHOOK_TOKEN', label: 'Webhook Route Token', secret: true },
  { key: 'CRM_API_TOKEN', label: 'CRM API Token', secret: true },
  { key: 'EVENT_QR_SIGNING_SECRET', label: 'Event QR Signing Secret', secret: true }
];

const USERS_HEADERS = [
  'Username',
  'Display Name',
  'Role',
  'Password Hash',
  'Password Salt',
  'Status',
  'Force Password Change',
  'Failed Attempts',
  'Lockout Until',
  'Last Login At',
  'Last Login IP',
  'Created At',
  'Created By',
  'Updated At',
  'Updated By',
  'Permissions'
];

const AUTH_AUDIT_HEADERS = [
  'Timestamp',
  'Action',
  'Username',
  'Outcome',
  'Source',
  'Details'
];

const AUTH_REVOKED_TOKENS_HEADERS = [
  'Token Hash',
  'Revoked At',
  'Expires At',
  'Username'
];

// QR Code Configuration
const QR_MENU_URL = 'https://namastekalyan.asianwokandgrill.in/scan.html';
const QR_TRACKING_URL_SUFFIX = '';

// Email Configuration
const EMAIL_FROM = 'noreply@dcoresystems.com';
const EMAIL_FROM_NAME = 'Dcore Systems Support';
const EMAIL_TO = 'support@dcoresystem.com';
const EMAIL_SCAN_INTERVAL = 100; // Send email every 100 scans
const SHEET_TIMEZONE = 'Asia/Kolkata';
const EVENT_VENUE_ADDRESS = 'Rockmount Commercial Hub, 4th Floor, Khadakpada Circle, Kalyan West, Thane, Maharashtra 421301';
const EVENT_BOOKING_PHONE = '9371519999';
const EVENT_TICKET_CONFIRMATION_CC_EMAIL = 'namastekalyan09@gmail.com';
const NO_REFUND_POLICY_TEXT = 'No refund once pass is purchased.';
const DEFAULT_EVENT_TIME_DISPLAY_FORMAT = '12h';

// SMTP Configuration (for future custom SMTP if needed)
const SMTP_HOST = 'smtp.dcoresystems.com';
const SMTP_PORT = 465;
const LEGACY_EVENTS_HEADERS = [
  'Event ID',
  'Title',
  'Subtitle',
  'Description',
  'Image URL',
  'Video URL',
  'Show Video',
  'CTA Text',
  'CTA URL',
  'Badge Text',
  'Start At',
  'End At',
  'Is Active',
  'Priority',
  'Popup Enabled',
  'Show Once Per Session',
  'Popup Delay Hours',
  'Popup Cooldown Hours',
  'Event Type',
  'Ticket Price',
  'Currency',
  'Max Tickets',
  'Payment Enabled',
  'Cancellation Policy Text',
  'Refund Policy'
];

const EVENTS_HEADERS = [
  'Event ID',
  'Title',
  'Subtitle',
  'Description',
  'Image URL',
  'Video URL',
  'Show Video',
  'CTA Text',
  'CTA URL',
  'Badge Text',
  'Start Date',
  'Start Time',
  'End Date',
  'End Time',
  'Time Display Format',
  'Is Active',
  'Priority',
  'Popup Enabled',
  'Show Once Per Session',
  'Popup Delay Hours',
  'Popup Cooldown Hours',
  'Event Type',
  'Ticket Price',
  'Currency',
  'Max Tickets',
  'Payment Enabled',
  'Cancellation Policy Text',
  'Refund Policy'
];

function doGet(e) {
  const params = (e && e.parameter) || {};
  const requestedTab = String(params.tab || '').trim();
  const shape = String(params.shape || 'grid').trim().toLowerCase();
  const qrTracking = String(params.qr || '').trim().toLowerCase();

  // Handle QR code scan tracking
  if (qrTracking === 'track') {
    const userAgent = String(e.userAgent || '');
    const referer = String(e.referer || '');
    const remoteAddr = String(e.remoteAddress || '');
    return handleQrScanTracking_(userAgent, referer, remoteAddr);
  }

  // Lead/admin endpoints use action parameter and do not require tab.
  if (!requestedTab && params.action) {
    return handleLeadGetAction(params);
  }

  if (!requestedTab) {
    return jsonResponse({
      ok: false,
      error: 'TAB_REQUIRED',
      message: 'Missing required query parameter: tab'
    });
  }

  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  const sheet = findSheetByNormalizedName(spreadsheet, requestedTab);

  if (!sheet) {
    return jsonResponse({
      ok: false,
      error: 'TAB_NOT_FOUND',
      requestedTab: requestedTab,
      availableTabs: spreadsheet.getSheets().map((item) => item.getName())
    });
  }

  const values = sheet.getDataRange().getDisplayValues();
  if (!values.length) {
    return jsonResponse({
      ok: false,
      error: 'EMPTY_SHEET',
      sourceTab: sheet.getName()
    });
  }

  const headers = values[0].map((header, index) => {
    const clean = String(header || '').trim();
    return clean || `__col_${index + 1}`;
  });

  const rows = values
    .slice(1)
    .filter((row) => row.some((cell) => String(cell || '').trim() !== ''));

  if (shape === 'records') {
    const records = rows.map((row) => {
      const record = {};
      headers.forEach((header, index) => {
        record[header] = row[index] || '';
      });
      return record;
    });

    return jsonResponse({
      ok: true,
      sourceTab: sheet.getName(),
      headers: headers,
      items: records,
      rowCount: records.length,
      fetchedAt: new Date().toISOString()
    });
  }

  return jsonResponse({
    ok: true,
    sourceTab: sheet.getName(),
    headers: headers,
    rows: rows,
    rowCount: rows.length,
    fetchedAt: new Date().toISOString()
  });
}

function doPost(e) {
  try {
    const actionHint = (e && e.parameter && e.parameter.action)
      ? String(e.parameter.action).trim().toLowerCase()
      : '';

    if (actionHint === 'razorpay_webhook') {
      return jsonResponse(handleRazorpayWebhook_(e));
    }

    const data = parseIncomingPostData_(e);

    const action = String(data.action || actionHint || '').trim().toLowerCase();

    if (action === 'auth_login') {
      return jsonResponse(handleAuthLogin_(data));
    }

    if (action === 'auth_logout') {
      return jsonResponse(handleAuthLogout_(data));
    }

    if (action === 'auth_me') {
      return jsonResponse(handleAuthMe_(data));
    }

    if (action === 'auth_change_password') {
      return jsonResponse(handleAuthChangePassword_(data));
    }

    if (action === 'auth_create_user') {
      return jsonResponse(handleAuthCreateUser_(data));
    }

    if (action === 'auth_set_user_status') {
      return jsonResponse(handleAuthSetUserStatus_(data));
    }

    if (action === 'auth_reset_password') {
      return jsonResponse(handleAuthResetPassword_(data));
    }

    if (action === 'auth_set_user_permissions') {
      return jsonResponse(handleAuthSetUserPermissions_(data));
    }

    if (action === 'auth_get_api_settings') {
      return jsonResponse(handleAuthGetApiSettings_(data));
    }

    if (action === 'auth_list_users') {
      return jsonResponse(handleAuthListUsers_(data));
    }

    if (action === 'auth_set_api_settings') {
      return jsonResponse(handleAuthSetApiSettings_(data));
    }

    if (action === 'register_free_event') {
      return jsonResponse(handleRegisterFreeEvent_(data));
    }

    if (action === 'create_event_order') {
      return jsonResponse(handleCreateEventOrder_(data));
    }

    if (action === 'confirm_event_payment') {
      return jsonResponse(handleConfirmEventPayment_(data));
    }

    if (action === 'resend_event_confirmation') {
      return jsonResponse(handleResendEventConfirmation_(data));
    }

    if (action === 'request_event_cancellation') {
      return jsonResponse(handleEventCancellationRequest_(data));
    }

    if (action === 'admin_create_event') {
      return jsonResponse(handleAdminCreateEvent_(data));
    }

    if (action === 'admin_update_event') {
      return jsonResponse(handleAdminUpdateEvent_(data));
    }

    if (action === 'admin_toggle_event') {
      return jsonResponse(handleAdminToggleEvent_(data));
    }

    if (action === 'admin_menu_editor_load') {
      return jsonResponse(handleAdminMenuEditorLoad_(data));
    }

    if (action === 'admin_menu_editor_save_changes') {
      return jsonResponse(handleAdminMenuEditorSaveChanges_(data));
    }

    if (action === 'admin_menu_editor_add_row') {
      return jsonResponse(handleAdminMenuEditorAddRow_(data));
    }

    if (action === 'admin_menu_editor_delete_rows') {
      return jsonResponse(handleAdminMenuEditorDeleteRows_(data));
    }

    if (action === 'admin_menu_editor_set_visibility') {
      return jsonResponse(handleAdminMenuEditorSetVisibility_(data));
    }

    if (action === 'admin_issue_cash_paid_pass') {
      return jsonResponse(handleAdminIssueCashPaidPass_(data));
    }

    if (action === 'admin_request_cash_handover') {
      return jsonResponse(handleAdminRequestCashHandover_(data));
    }

    if (action === 'admin_request_cash_cancel') {
      return jsonResponse(handleAdminRequestCashCancel_(data));
    }

    if (action === 'superadmin_approve_cash_handover') {
      return jsonResponse(handleSuperadminApproveCashHandover_(data));
    }

    if (action === 'superadmin_resolve_cash_cancel') {
      return jsonResponse(handleSuperadminResolveCashCancel_(data));
    }

    if (action === 'admin_preview_event_qr') {
      return jsonResponse(handleAdminPreviewEventQr_(data));
    }

    if (action === 'admin_batch_checkin_event_qr') {
      return jsonResponse(handleAdminBatchCheckinEventQr_(data));
    }

    if (action === 'verify_event_qr') {
      return jsonResponse(handleVerifyEventQr_(data));
    }

    // Route QR client-side scan tracking (from scan.html)
    if (action === 'qr_scan_client') {
      return handleQrScanClientTracking_(data);
    }

    const name = String(data.name || '').trim();
    const localPhone = normalizePhoneDigits_(data.phone);
    const countryCode = normalizeCountryCode_(data.countryCode);
    const phone = formatInternationalPhone_(countryCode, localPhone);
    const dob = normalizeDisplayDate_(data.dateOfBirth || data.dob || '');
    const anniversary = normalizeDisplayDate_(data.dateOfAnniversary || data.anniversary || '');
    const dobIso = toIsoDateString_(data.dateOfBirthIso || data.dobIso || dob || '');
    const anniversaryIso = toIsoDateString_(data.dateOfAnniversaryIso || data.anniversaryIso || anniversary || '');
    const source = String(data.source || 'menu-blocker-web').trim();

    if (!name || !isValidPhoneForCountry_(localPhone, countryCode)) {
      return jsonResponse({ ok: false, error: 'INVALID_INPUT', message: 'Name and valid phone are required.' });
    }

    const sheet = getOrCreateLeadsSheet_();
    const existing = findLeadByPhone_(sheet, phone);
    if (existing && isWithinHours_(existing.timestamp, SPIN_COOLDOWN_HOURS)) {
      const updatedVisitCount = incrementVisitCount_(sheet, existing.row, existing.visitCount);
      return jsonResponse({
        ok: true,
        result: 'duplicate',
        prize: existing.prize,
        status: existing.status,
        row: existing.row,
        visitCount: updatedVisitCount,
        phone: existing.phone
      });
    }

    const nextRow = sheet.getLastRow() + 1;
    const prize = computePrizeByRow_(nextRow);
    const couponCode = isWinnerPrize_(prize) ? generateCouponCode_(nextRow, prize, phone) : '';
    const timestamp = data.timestamp ? new Date(data.timestamp) : new Date();

    // Append data: [Timestamp, Name, Phone, Prize, Status, Date Of Birth, Date Of Anniversary, Source, Visit Count, CRM Sync Status, CRM Sync Code, CRM Sync Message, Coupon Code]
    sheet.appendRow([timestamp, name, phone, prize, 'Unredeemed', dob, anniversary, source, 1, 'Pending', '', '', couponCode]);

    const crmSync = pushLeadToCrm_(buildCrmLeadPayload_({
      name: name,
      phone: phone,
      localPhone: localPhone,
      countryCode: countryCode,
      prize: prize,
      status: 'Unredeemed',
      timestamp: timestamp,
      source: source,
      visitCount: 1,
      dob: dob,
      dobIso: dobIso,
      anniversary: anniversary,
      anniversaryIso: anniversaryIso
    }));

    updateCrmSyncColumns_(sheet, nextRow, crmSync);

    return jsonResponse({ ok: true, result: 'success', prize: prize, row: nextRow, visitCount: 1, phone: phone, couponCode: couponCode, crmSync: crmSync });
  } catch (err) {
    return jsonResponse({ ok: false, error: 'POST_FAILED', message: String(err) });
  }
}

function handleLeadGetAction(params) {
  const action = String(params.action || '').trim().toLowerCase();

  if (action === 'auth_bootstrap_status') {
    return jsonResponse(getAuthBootstrapStatus_());
  }

  if (action === 'auth_list_users') {
    return jsonResponse(handleAuthListUsers_(params));
  }

  if (action === 'auth_me') {
    return jsonResponse(handleAuthMe_(params));
  }

  if (isAdminProtectedAction_(action)) {
    const adminAuth = authorizeAdminRequest_(params, 'admin');
    if (!adminAuth.ok) {
      return jsonResponse({ ok: false, error: adminAuth.error || 'UNAUTHORIZED', message: adminAuth.message || 'Admin authentication required' });
    }
    // Legacy passcode checks are still present below for backward compatibility.
    params.passcode = ADMIN_PANEL_PASSCODE;
  }

  const sheet = getOrCreateLeadsSheet_();

  if (action === 'add_test_lead' || action === 'add-test-lead') {
    const name = String(params.name || 'Test').trim();
    const source = String(params.source || 'manual-crm-confirmed').trim();
    const shouldSyncCrm = String(params.syncCrm || '1').trim() !== '0';
    const inputPhone = String(params.phone || '').trim();
    const countryCode = normalizeCountryCode_(params.countryCode);
    const digits = normalizePhoneDigits_(inputPhone);
    const localPhone = digits.startsWith(countryCode) && digits.length > 10
      ? digits.slice(countryCode.length)
      : digits;

    if (!name || !isValidPhoneForCountry_(localPhone, countryCode)) {
      return jsonResponse({ ok: false, error: 'INVALID_INPUT', message: 'name and valid phone are required' });
    }

    const rowData = appendManualLeadRow_(sheet, {
      name: name,
      phone: formatInternationalPhone_(countryCode, localPhone),
      source: source,
      crmStatus: String(params.crmStatus || 'Success'),
      crmCode: String(params.crmCode || '200'),
      crmMessage: String(params.crmMessage || 'Manual entry after CRM API success')
    });

    let crmSync = {
      attempted: false,
      success: false,
      status: '',
      message: 'CRM sync skipped for manual insert',
      attempts: []
    };

    if (shouldSyncCrm) {
      crmSync = pushLeadToCrm_(buildCrmLeadPayload_({
        name: rowData.name,
        phone: rowData.phone,
        localPhone: localPhone,
        countryCode: countryCode,
        prize: rowData.prize,
        status: rowData.status,
        timestamp: rowData.timestamp,
        source: rowData.source,
        visitCount: rowData.visitCount,
        dob: rowData.dob,
        dobIso: rowData.dobIso,
        anniversary: rowData.anniversary,
        anniversaryIso: rowData.anniversaryIso
      }));
      updateCrmSyncColumns_(sheet, rowData.row, crmSync);
    } else {
      updateCrmSyncColumns_(sheet, rowData.row, {
        attempted: false,
        success: false,
        status: rowData.crmCode,
        message: rowData.crmMessage,
        attempts: []
      });
    }

    return jsonResponse({
      ok: true,
      result: 'added',
      row: rowData.row,
      phone: rowData.phone,
      name: rowData.name,
      crmSync: crmSync
    });
  }

  if (action === 'sync_crm_by_phone' || action === 'sync-crm-by-phone') {
    const inputPhone = String(params.phone || '').trim();
    const normalizedPhone = normalizePhoneDigits_(inputPhone);
    if (normalizedPhone.length < 8) {
      return jsonResponse({ ok: false, error: 'INVALID_PHONE' });
    }

    const rowInfo = findLeadByPhone_(sheet, normalizedPhone);
    if (!rowInfo) {
      return jsonResponse({ ok: false, error: 'NOT_FOUND', message: 'Lead not found for given phone' });
    }

    const crmSync = pushLeadToCrm_(buildCrmLeadPayload_({
      name: rowInfo.name,
      phone: rowInfo.phone,
      localPhone: normalizePhoneDigits_(rowInfo.phone),
      countryCode: normalizeCountryCode_(rowInfo.phone),
      prize: rowInfo.prize,
      status: rowInfo.status,
      timestamp: rowInfo.timestamp,
      source: rowInfo.source,
      visitCount: rowInfo.visitCount,
      dob: rowInfo.dob,
      dobIso: rowInfo.dobIso,
      anniversary: rowInfo.anniversary,
      anniversaryIso: rowInfo.anniversaryIso
    }));

    updateCrmSyncColumns_(sheet, rowInfo.row, crmSync);
    return jsonResponse({ ok: true, result: 'crm_sync_attempted', row: rowInfo.row, phone: rowInfo.phone, crmSync: crmSync });
  }

  if (action === 'init_schema' || action === 'schema') {
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
    return jsonResponse({ ok: true, result: 'schema_initialized', headers: headers });
  }

  if (action === 'counter') {
    const lastRow = sheet.getLastRow();
    return jsonResponse({ ok: true, result: 'success', globalCounter: Math.max(0, lastRow - 1), nextRow: lastRow + 1 });
  }

  if (action === 'verify') {
    const phone = normalizePhoneDigits_(params.phone);
    if (phone.length < 8) {
      return jsonResponse({ ok: false, error: 'INVALID_PHONE' });
    }

    const rowInfo = findLeadByPhone_(sheet, phone);
    if (!rowInfo) {
      return jsonResponse({ ok: true, found: false, message: 'No record found' });
    }

    const couponCode = ensureCouponCodeForRow_(sheet, rowInfo);

    return jsonResponse({
      ok: true,
      found: true,
      row: rowInfo.row,
      timestamp: rowInfo.timestamp,
      name: rowInfo.name,
      phone: rowInfo.phone,
      prize: rowInfo.prize,
      status: rowInfo.status,
      dob: rowInfo.dob,
      anniversary: rowInfo.anniversary,
      source: rowInfo.source,
      couponCode: couponCode,
      visitCount: rowInfo.visitCount,
      crmSyncStatus: rowInfo.crmSyncStatus,
      crmSyncCode: rowInfo.crmSyncCode,
      crmSyncMessage: rowInfo.crmSyncMessage
    });
  }

  if (action === 'redeem') {
    const phone = normalizePhoneDigits_(params.phone);
    if (phone.length < 8) {
      return jsonResponse({ ok: false, error: 'INVALID_PHONE' });
    }

    const rowInfo = findLeadByPhone_(sheet, phone);
    if (!rowInfo) {
      return jsonResponse({ ok: true, result: 'not_found' });
    }

    if (String(rowInfo.status).toLowerCase() === 'redeemed') {
      return jsonResponse({ ok: true, result: 'already_redeemed', prize: rowInfo.prize, row: rowInfo.row, couponCode: ensureCouponCodeForRow_(sheet, rowInfo) });
    }

    sheet.getRange(rowInfo.row, 5).setValue('Redeemed');
    return jsonResponse({ ok: true, result: 'redeemed', prize: rowInfo.prize, row: rowInfo.row, couponCode: ensureCouponCodeForRow_(sheet, rowInfo) });
  }

  if (action === 'regen_coupon' || action === 'regenerate_coupon' || action === 'regen-coupon') {
    const phone = normalizePhoneDigits_(params.phone);
    if (phone.length < 8) {
      return jsonResponse({ ok: false, error: 'INVALID_PHONE' });
    }

    const rowInfo = findLeadByPhone_(sheet, phone);
    if (!rowInfo) {
      return jsonResponse({ ok: true, result: 'not_found' });
    }

    const overridePrize = normalizePrizeLabel_(params.giftItem || params.prizeOverride || params.prize || '');
    if (!isWinnerPrize_(rowInfo.prize)) {
      if (!overridePrize || !isWinnerPrize_(overridePrize)) {
        return jsonResponse({ ok: true, result: 'not_winner', prize: rowInfo.prize, row: rowInfo.row });
      }

      // Admin override: set winner gift in sheet before generating coupon.
      sheet.getRange(rowInfo.row, 4).setValue(overridePrize);
      rowInfo.prize = overridePrize;
    }

    const previousCode = String(rowInfo.couponCode || '').trim();
    const couponCode = ensureCouponCodeForRow_(sheet, rowInfo);
    return jsonResponse({
      ok: true,
      result: previousCode ? 'already_exists' : (overridePrize ? 'generated_with_prize_update' : 'generated'),
      row: rowInfo.row,
      phone: rowInfo.phone,
      prize: rowInfo.prize,
      couponCode: couponCode
    });
  }

  if (action === 'add_test_qr_scan' || action === 'test_qr_scan') {
    return jsonResponse(createTestQrScanEntry());
  }

  if (action === 'add_test_25_coupon' || action === 'test_25_coupon') {
    return jsonResponse(createTestEntry25Coupon());
  }

  if (action === 'qr_report') {
    return jsonResponse(getQrScanReport_());
  }

  if (action === 'ensure_qr_sheet' || action === 'init_qr_sheet' || action === 'create_qr_sheet') {
    try {
      const qrSheet = getOrCreateQrScansSheet_();
      return jsonResponse({
        ok: true,
        result: 'qr_sheet_ready',
        sheetName: qrSheet.getName(),
        totalScans: Math.max(0, qrSheet.getLastRow() - 1)
      });
    } catch (err) {
      return jsonResponse({ ok: false, error: 'QR_SHEET_INIT_FAILED', message: String(err) });
    }
  }

  if (action === 'qr_scan_report_html' || action === 'qr-scan-report-html') {
    const html = buildQrScanReportHtml_();
    return ContentService.createTextOutput(html).setMimeType(ContentService.MimeType.HTML);
  }

  if (action === 'download_qr_code') {
    return downloadQrCodeImage_();
  }

  if (action === 'events_list' || action === 'event_list') {
    const limitValue = Number(params.limit || 6);
    const limit = Number.isFinite(limitValue) && limitValue > 0 ? Math.min(Math.floor(limitValue), 20) : 6;
    const events = getActiveEvents_();
    return jsonResponse({
      ok: true,
      action: 'events_list',
      items: events.slice(0, limit),
      rowCount: events.length,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'event_popup') {
    const requestedId = String(params.eventId || params.id || '').trim();
    const popupEvents = getActiveEvents_().filter((item) => item.popupEnabled);
    const event = requestedId
      ? popupEvents.find((item) => String(item.id) === requestedId)
      : (popupEvents[0] || null);

    return jsonResponse({
      ok: true,
      action: 'event_popup',
      found: !!event,
      event: event,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'event_detail') {
    const requestedId = String(params.eventId || params.id || '').trim();
    if (!requestedId) {
      return jsonResponse({ ok: false, error: 'EVENT_ID_REQUIRED' });
    }

    const allEvents = getEventRecords_();
    const event = allEvents.find((item) => String(item.id) === requestedId) || null;

    return jsonResponse({
      ok: true,
      action: 'event_detail',
      found: !!event,
      event: event,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'migrate_events_sheet_format' || action === 'migrate_event_sheet_format') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const migration = migrateEventsSheetFormat_(true);
    return jsonResponse({
      ok: true,
      action: 'migrate_events_sheet_format',
      migratedRows: migration.migratedRows,
      previousFormat: migration.previousFormat,
      currentHeaders: EVENTS_HEADERS,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'reset_events_sheet_format' || action === 'reset_events_data') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const includePaidSample = String(params.includePaidSample || '').trim() === '1';
    const resetResult = resetEventsSheetData_(includePaidSample);
    return jsonResponse({
      ok: true,
      action: 'reset_events_sheet_format',
      clearedRows: resetResult.clearedRows,
      seeded: resetResult.seeded,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'seed_events_sample' || action === 'seed_event_sample') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const forceSeed = String(params.force || '').trim() === '1';
    const seeded = seedSampleEventRow_(forceSeed);
    return jsonResponse({
      ok: true,
      action: 'seed_events_sample',
      seeded: seeded.seeded,
      reason: seeded.reason,
      row: seeded.row,
      eventId: seeded.eventId,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'seed_dj_events_apr_2026' || action === 'seed_dj_events') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const seeded = seedDjEventsApr2026_();
    return jsonResponse({
      ok: true,
      action: 'seed_dj_events_apr_2026',
      inserted: seeded.inserted,
      updated: seeded.updated,
      total: seeded.total,
      eventIds: seeded.eventIds,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'seed_paid_event_sample' || action === 'seed_paid_event') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const price = Math.max(1, Number(params.price || 499));
    const maxTickets = Math.max(1, Math.floor(Number(params.maxTickets || 6)));
    const seeded = seedPaidEventSample_(price, maxTickets);
    return jsonResponse({
      ok: true,
      action: 'seed_paid_event_sample',
      inserted: seeded.inserted,
      updated: seeded.updated,
      row: seeded.row,
      eventId: seeded.eventId,
      price: seeded.price,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'create_test_paid_tx' || action === 'seed_test_paid_tx') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const eventId = String(params.eventId || '').trim();
    const qty = Math.max(1, Math.floor(Number(params.qty || 1)));
    const result = createTestPaidTransaction_(eventId, qty);
    return jsonResponse({
      ok: result.ok,
      action: 'create_test_paid_tx',
      message: result.message || '',
      transactionId: result.transactionId || '',
      eventId: result.eventId || '',
      orderId: result.orderId || '',
      paymentId: result.paymentId || '',
      status: result.status || '',
      qrUrl: result.qrUrl || '',
      verificationUrl: result.verificationUrl || '',
      payload: result.payload || null,
      error: result.error || '',
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'send_test_event_email' || action === 'test_event_email') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const recipient = String(params.email || params.to || '').trim().toLowerCase();
    if (!recipient) {
      return jsonResponse({ ok: false, error: 'EMAIL_REQUIRED', message: 'Pass email query param.' });
    }

    const preferredEventId = String(params.eventId || 'paid-test-2026').trim();
    const sendResult = sendTestEventEmail_(recipient, preferredEventId);
    return jsonResponse({
      ok: sendResult.ok,
      action: 'send_test_event_email',
      to: recipient,
      transactionId: sendResult.transactionId || '',
      eventId: sendResult.eventId || '',
      subject: sendResult.subject || '',
      message: sendResult.message || '',
      error: sendResult.error || '',
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'event_guest_report') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const requestedEventId = String(params.eventId || '').trim();
    const report = getEventGuestReport_(requestedEventId);
    return jsonResponse({
      ok: true,
      action: 'event_guest_report',
      report: report,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'event_transactions_report') {
    const suppliedPasscode = String(params.passcode || '').trim();
    if (!suppliedPasscode || suppliedPasscode !== ADMIN_PANEL_PASSCODE) {
      return jsonResponse({ ok: false, error: 'UNAUTHORIZED', message: 'Admin passcode required' });
    }

    const report = getEventTransactionsReport_();
    return jsonResponse({
      ok: true,
      action: 'event_transactions_report',
      report: report,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'admin_list_events' || action === 'admin_event_list') {
    const events = getAdminEventRecords_();
    return jsonResponse({
      ok: true,
      action: 'admin_list_events',
      items: events,
      rowCount: events.length,
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'admin_cash_summary') {
    const auth = authorizeAdminRequest_(params, 'admin');
    if (!auth.ok) {
      return jsonResponse({ ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' });
    }

    return jsonResponse({
      ok: true,
      action: 'admin_cash_summary',
      summary: getAdminCashSummary_(auth.user.username, params.ledgerDate || ''),
      fetchedAt: new Date().toISOString()
    });
  }

  if (action === 'superadmin_cash_dashboard') {
    const auth = authorizeAdminRequest_(params, 'superadmin');
    if (!auth.ok) {
      return jsonResponse({ ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' });
    }

    return jsonResponse({
      ok: true,
      action: 'superadmin_cash_dashboard',
      dashboard: getSuperadminCashDashboard_(params.ledgerDate || ''),
      fetchedAt: new Date().toISOString()
    });
  }

  return jsonResponse({ ok: false, error: 'INVALID_ACTION', action: action });
}

function getOrCreateLeadsSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(LEADS_SHEET_NAME);
  const headers = ['Timestamp', 'Name', 'Phone', 'Prize', 'Status', 'Date Of Birth', 'Date Of Anniversary', 'Source', 'Visit Count', 'CRM Sync Status', 'CRM Sync Code', 'CRM Sync Message', 'Coupon Code'];

  if (!sheet) {
    sheet = spreadsheet.insertSheet(LEADS_SHEET_NAME);
    sheet.appendRow(headers);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  } else {
    ensureLeadsSheetHeaders_(sheet, headers);
  }

  return sheet;
}

function ensureLeadsSheetHeaders_(sheet, expectedHeaders) {
  const current = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  for (let i = 0; i < expectedHeaders.length; i += 1) {
    if (String(current[i] || '').trim() !== expectedHeaders[i]) {
      sheet.getRange(1, i + 1).setValue(expectedHeaders[i]);
    }
  }
}

function computePrizeByRow_(nextRow) {
  // Priority order (highest value first):
  // 1. Every 500th: 25% OFF
  // 2. Every 300th: 20% OFF
  // 3. Every 125th: 15% OFF
  // 4. Every 51st: 10% OFF (nextRow % 50 === 1)
  // 5. Every 49th: Starter on the House (nextRow % 50 === 49)
  // 6. Every 18th alternating: Dessert / Aerated (nextRow % 10 === 8)
  // 7. Every 10th: Mocktail on the House (nextRow % 10 === 0)
  // 8. All others: Try Again

  if (nextRow % 500 === 0) return '25% OFF';
  if (nextRow % 300 === 0) return '20% OFF';
  if (nextRow % 125 === 0) return '15% OFF';
  if (nextRow % 50 === 1) return '10% OFF';
  if (nextRow % 50 === 49) return 'Starter on the House';

  if (nextRow % 10 === 8) {
    // Alternating Dessert (even cycle) and Aerated (odd cycle) on every 18th
    const cycleIndex = Math.floor((nextRow - 18) / 10);
    return cycleIndex % 2 === 0 ? 'Dessert on the House' : 'Aerated Drink on the House';
  }

  if (nextRow % 10 === 0) return 'Mocktail on the House';

  return 'Try Again';
}

function createTestEntry20Coupon() {
  const sheet = getOrCreateLeadsSheet_();
  const now = new Date();
  const phone = '91' + ('9' + Utilities.getUuid().replace(/\D/g, '').slice(0, 9));
  const name = 'Test 20 Coupon';
  const dob = '1999-01-01';
  const anniversary = '2020-01-01';
  const source = 'manual-test-20';

  sheet.appendRow([now, name, phone, '20% OFF', 'Unredeemed', dob, anniversary, source, 1, 'Manual', '', '', generateCouponCode_(sheet.getLastRow() + 1, '20% OFF', phone)]);
  const row = sheet.getLastRow();

  return {
    ok: true,
    row: row,
    name: name,
    phone: phone,
    prize: '20% OFF',
    status: 'Unredeemed',
    visitCount: 1
  };
}

function createTestEntry25Coupon() {
  const sheet = getOrCreateLeadsSheet_();
  const now = new Date();
  const phone = '91' + ('8' + Utilities.getUuid().replace(/\D/g, '').slice(0, 9));
  const name = 'Test 25 Coupon';
  const dob = '1998-01-01';
  const anniversary = '2021-01-01';
  const source = 'manual-test-25';

  sheet.appendRow([now, name, phone, '25% OFF', 'Unredeemed', dob, anniversary, source, 1, 'Manual', '', '', generateCouponCode_(sheet.getLastRow() + 1, '25% OFF', phone)]);
  const row = sheet.getLastRow();
  const couponCode = String(sheet.getRange(row, 13).getValue() || '');

  return {
    ok: true,
    row: row,
    name: name,
    phone: phone,
    prize: '25% OFF',
    status: 'Unredeemed',
    couponCode: couponCode,
    visitCount: 1
  };
}

function incrementVisitCount_(sheet, rowNumber, currentVisitCount) {
  const current = Number(currentVisitCount);
  const safeCurrent = Number.isFinite(current) && current > 0 ? Math.floor(current) : 1;
  const updated = safeCurrent + 1;
  sheet.getRange(rowNumber, 9).setValue(updated);
  return updated;
}

function updateCrmSyncColumns_(sheet, rowNumber, crmSync) {
  const status = crmSync && crmSync.success ? 'Success' : (crmSync && crmSync.attempted ? 'Failed' : 'Skipped');
  const attempts = (crmSync && Array.isArray(crmSync.attempts)) ? crmSync.attempts : [];
  const code = attempts.length
    ? attempts.map((a) => String(a.status || '')).filter((v) => v).join(' | ')
    : (crmSync && crmSync.status ? String(crmSync.status) : '');
  const message = attempts.length
    ? attempts
      .map((a, idx) => {
        const text = String(a.message || '').replace(/\s+/g, ' ').trim();
        return `A${idx + 1}:${a.success ? 'OK' : 'FAIL'}(${a.status || ''}) ${text}`;
      })
      .join(' || ')
      .slice(0, 500)
    : (crmSync && crmSync.message ? String(crmSync.message).slice(0, 500) : '');
  sheet.getRange(rowNumber, 10).setValue(status);
  sheet.getRange(rowNumber, 11).setValue(code);
  sheet.getRange(rowNumber, 12).setValue(message);
}

function findLeadByPhone_(sheet, phone) {
  const values = sheet.getDataRange().getValues();
  if (values.length <= 1) return null;

  const normalizedQuery = normalizePhoneDigits_(phone);

  for (let i = values.length - 1; i >= 1; i -= 1) {
    const rowPhone = String(values[i][2] || '').trim();
    const normalizedRowPhone = normalizePhoneDigits_(rowPhone);

    const exactMatch = normalizedRowPhone === normalizedQuery;
    const suffixMatch = normalizedQuery.length >= 8 && normalizedRowPhone.endsWith(normalizedQuery);

    if (exactMatch || suffixMatch) {
      const normalizedPrize = normalizePrizeLabel_(values[i][3] || 'Try Again');
      return {
        row: i + 1,
        timestamp: values[i][0],
        name: values[i][1],
        phone: rowPhone,
        prize: normalizedPrize,
        status: String(values[i][4] || 'Unredeemed'),
        dob: String(values[i][5] || ''),
        anniversary: String(values[i][6] || ''),
        dobIso: toIsoDateString_(values[i][5] || ''),
        anniversaryIso: toIsoDateString_(values[i][6] || ''),
        source: String(values[i][7] || ''),
        couponCode: String(values[i][12] || ''),
        visitCount: Number(values[i][8] || 1) || 1,
        crmSyncStatus: String(values[i][9] || ''),
        crmSyncCode: String(values[i][10] || ''),
        crmSyncMessage: String(values[i][11] || '')
      };
    }
  }

  return null;
}

function jsonResponse(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function findSheetByNormalizedName(spreadsheet, requestedTab) {
  const normalizedRequested = normalizeSheetName(requestedTab);
  const sheets = spreadsheet.getSheets();

  // Exact match first (fast path)
  const exact = spreadsheet.getSheetByName(requestedTab);
  if (exact) return exact;

  // Fallback: trim + collapse spaces + lowercase
  for (let i = 0; i < sheets.length; i++) {
    const candidate = sheets[i];
    if (normalizeSheetName(candidate.getName()) === normalizedRequested) {
      return candidate;
    }
  }

  return null;
}

function normalizeSheetName(name) {
  return String(name || '')
    .replace(/\u00a0/g, ' ')
    .trim()
    .replace(/\s+/g, ' ')
    .toLowerCase();
}

function parseIncomingPostData_(e) {
  const defaultPayload = {};
  const rawBody = (e && e.postData && e.postData.contents) ? String(e.postData.contents) : '';
  const params = (e && e.parameter) ? e.parameter : {};

  // Preferred path for browser-safe form posts: payload={json-string}
  if (params && params.payload) {
    try {
      return JSON.parse(String(params.payload));
    } catch (err) {
      throw new Error('INVALID_PAYLOAD_JSON');
    }
  }

  // Backward compatibility for direct JSON body posts.
  if (rawBody) {
    try {
      return JSON.parse(rawBody);
    } catch (err) {
      // Fallback parser in case content arrives as querystring text.
      const parsed = {};
      const parts = rawBody.split('&');
      for (let i = 0; i < parts.length; i += 1) {
        const part = parts[i];
        if (!part) continue;
        const kv = part.split('=');
        const key = decodeURIComponent(String(kv[0] || '').replace(/\+/g, ' '));
        const value = decodeURIComponent(String(kv.slice(1).join('=') || '').replace(/\+/g, ' '));
        parsed[key] = value;
      }
      if (parsed.payload) {
        try {
          return JSON.parse(String(parsed.payload));
        } catch (err2) {
          throw new Error('INVALID_PAYLOAD_JSON');
        }
      }
    }
  }

  return defaultPayload;
}

function getAuthBootstrapStatus_() {
  const bootstrap = ensureBootstrapSuperAdminUser_();
  return {
    ok: true,
    action: 'auth_bootstrap_status',
    ready: true,
    bootstrapCreatedNow: !!bootstrap.created,
    bootstrapUsername: bootstrap.username,
    passwordPolicy: {
      minLength: AUTH_PASSWORD_MIN_LENGTH,
      requiresLetters: true,
      requiresNumbers: true
    },
    lockoutPolicy: {
      maxAttempts: AUTH_LOCKOUT_MAX_ATTEMPTS,
      lockoutMinutes: AUTH_LOCKOUT_MINUTES
    },
    sessionHours: AUTH_TOKEN_HOURS
  };
}

function handleAuthLogin_(data) {
  ensureBootstrapSuperAdminUser_();

  const username = normalizeUsernameMobile_(data.username || data.mobile || data.phone || '');
  const password = String(data.password || '').trim();
  if (!username || !password) {
    return { ok: false, error: 'INVALID_INPUT', message: 'username and password are required.' };
  }

  const usersSheet = getOrCreateUsersSheet_();
  const userEntry = findUserByUsername_(usersSheet, username);
  if (!userEntry) {
    logAuthAudit_('auth_login', username, 'failed', 'user_not_found');
    return { ok: false, error: 'INVALID_CREDENTIALS', message: 'Invalid username or password.' };
  }

  const user = userEntry.user;
  if (String(user.status || '').toLowerCase() !== 'active') {
    logAuthAudit_('auth_login', username, 'failed', 'user_disabled');
    return { ok: false, error: 'USER_DISABLED', message: 'This user account is disabled.' };
  }

  if (isAccountLocked_(user.lockoutUntil)) {
    logAuthAudit_('auth_login', username, 'failed', 'account_locked');
    return {
      ok: false,
      error: 'ACCOUNT_LOCKED',
      message: `Too many failed attempts. Try again after ${formatLockoutDate_(user.lockoutUntil)}.`
    };
  }

  const hashedInput = hashPassword_(password, user.passwordSalt);
  if (hashedInput !== String(user.passwordHash || '')) {
    const lockoutInfo = registerFailedLoginAttempt_(usersSheet, userEntry.row, user);
    logAuthAudit_('auth_login', username, 'failed', lockoutInfo.locked ? 'invalid_password_locked' : 'invalid_password');
    return {
      ok: false,
      error: lockoutInfo.locked ? 'ACCOUNT_LOCKED' : 'INVALID_CREDENTIALS',
      message: lockoutInfo.locked
        ? `Too many failed attempts. Try again after ${formatLockoutDate_(lockoutInfo.lockoutUntil)}.`
        : 'Invalid username or password.'
    };
  }

  clearFailedLoginAttemptState_(usersSheet, userEntry.row, username);
  usersSheet.getRange(userEntry.row, 10).setValue(new Date());
  usersSheet.getRange(userEntry.row, 11).setValue(String(data.source || 'web'));

  const refreshed = findUserByUsername_(usersSheet, username);
  const tokenBundle = buildAuthToken_(refreshed.user);
  logAuthAudit_('auth_login', username, 'success', refreshed.user.forcePasswordChange ? 'force_password_change' : 'ok');

  return {
    ok: true,
    action: 'auth_login',
    token: tokenBundle.token,
    expiresAt: tokenBundle.expiresAt,
    sessionHours: AUTH_TOKEN_HOURS,
    forcePasswordChange: !!refreshed.user.forcePasswordChange,
    user: toPublicUser_(refreshed.user)
  };
}

function handleAuthLogout_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Unauthorized' };
  }

  if (auth.authMode === 'token') {
    const rawToken = extractAuthToken_(data || {});
    if (rawToken) {
      revokeAuthToken_(rawToken, auth.tokenPayload || null);
    }
  }

  logAuthAudit_('auth_logout', auth.user.username, 'success', auth.authMode || 'token');
  return { ok: true, action: 'auth_logout', message: 'Logged out.' };
}

function handleAuthMe_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Unauthorized' };
  }

  return {
    ok: true,
    action: 'auth_me',
    authMode: auth.authMode,
    user: toPublicUser_(auth.user)
  };
}

function handleAuthChangePassword_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Unauthorized' };
  }

  const currentPassword = String(data.currentPassword || '').trim();
  const newPassword = String(data.newPassword || '').trim();

  if (!currentPassword || !newPassword) {
    return { ok: false, error: 'INVALID_INPUT', message: 'currentPassword and newPassword are required.' };
  }

  const policy = validatePasswordPolicy_(newPassword);
  if (!policy.ok) {
    return { ok: false, error: 'WEAK_PASSWORD', message: policy.message };
  }

  const usersSheet = getOrCreateUsersSheet_();
  const userEntry = findUserByUsername_(usersSheet, auth.user.username);
  if (!userEntry) {
    return { ok: false, error: 'USER_NOT_FOUND', message: 'User account not found.' };
  }

  const currentHash = hashPassword_(currentPassword, userEntry.user.passwordSalt);
  if (currentHash !== String(userEntry.user.passwordHash || '')) {
    logAuthAudit_('auth_change_password', auth.user.username, 'failed', 'wrong_current_password');
    return { ok: false, error: 'INVALID_CREDENTIALS', message: 'Current password is incorrect.' };
  }

  setUserPassword_(usersSheet, userEntry.row, newPassword, false, auth.user.username);
  logAuthAudit_('auth_change_password', auth.user.username, 'success', 'ok');

  return { ok: true, action: 'auth_change_password', message: 'Password updated successfully.' };
}

function handleAuthCreateUser_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  const username = normalizeUsernameMobile_(data.username || data.mobile || data.phone || '');
  const displayName = String(data.displayName || data.name || '').trim() || `User ${username}`;
  const role = normalizeUserRole_(data.role || 'admin');
  const rawPassword = String(data.password || '').trim();

  if (!username || !rawPassword) {
    return { ok: false, error: 'INVALID_INPUT', message: 'username and password are required.' };
  }

  const roleAllowed = role === 'admin' || role === 'superadmin';
  if (!roleAllowed) {
    return { ok: false, error: 'INVALID_ROLE', message: 'role must be admin or superadmin.' };
  }

  const permissions = normalizeUserPermissions_(data.permissions, role, true);

  const policy = validatePasswordPolicy_(rawPassword);
  if (!policy.ok) {
    return { ok: false, error: 'WEAK_PASSWORD', message: policy.message };
  }

  const usersSheet = getOrCreateUsersSheet_();
  const existing = findUserByUsername_(usersSheet, username);
  if (existing) {
    return { ok: false, error: 'USER_EXISTS', message: 'User already exists.' };
  }

  const salt = generatePasswordSalt_();
  const hash = hashPassword_(rawPassword, salt);
  const now = new Date();
  usersSheet.appendRow([
    username,
    displayName,
    role,
    hash,
    salt,
    'Active',
    'Yes',
    0,
    '',
    '',
    '',
    now,
    auth.user.username,
    now,
    auth.user.username,
    serializeUserPermissions_(permissions)
  ]);

  logAuthAudit_('auth_create_user', auth.user.username, 'success', `${username}:${role}`);
  const created = findUserByUsername_(usersSheet, username);
  return {
    ok: true,
    action: 'auth_create_user',
    user: toPublicUser_(created.user)
  };
}

function handleAuthSetUserStatus_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  const username = normalizeUsernameMobile_(data.username || data.mobile || data.phone || '');
  const statusRaw = String(data.status || '').trim().toLowerCase();
  const status = statusRaw === 'active' ? 'Active' : (statusRaw === 'disabled' ? 'Disabled' : '');
  if (!username || !status) {
    return { ok: false, error: 'INVALID_INPUT', message: 'username and status(active|disabled) are required.' };
  }

  const usersSheet = getOrCreateUsersSheet_();
  const entry = findUserByUsername_(usersSheet, username);
  if (!entry) {
    return { ok: false, error: 'USER_NOT_FOUND', message: 'User not found.' };
  }

  if (username === auth.user.username && status === 'Disabled') {
    return { ok: false, error: 'INVALID_OPERATION', message: 'You cannot disable your own account.' };
  }

  usersSheet.getRange(entry.row, 6).setValue(status);
  usersSheet.getRange(entry.row, 8).setValue(0);
  usersSheet.getRange(entry.row, 9).setValue('');
  usersSheet.getRange(entry.row, 14).setValue(new Date());
  usersSheet.getRange(entry.row, 15).setValue(auth.user.username);

  logAuthAudit_('auth_set_user_status', auth.user.username, 'success', `${username}:${status}`);
  const updated = findUserByUsername_(usersSheet, username);
  return {
    ok: true,
    action: 'auth_set_user_status',
    user: toPublicUser_(updated.user)
  };
}

function handleAuthResetPassword_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  const username = normalizeUsernameMobile_(data.username || data.mobile || data.phone || '');
  const temporaryPassword = String(data.temporaryPassword || data.newPassword || '').trim();

  if (!username || !temporaryPassword) {
    return { ok: false, error: 'INVALID_INPUT', message: 'username and temporaryPassword are required.' };
  }

  const policy = validatePasswordPolicy_(temporaryPassword);
  if (!policy.ok) {
    return { ok: false, error: 'WEAK_PASSWORD', message: policy.message };
  }

  const usersSheet = getOrCreateUsersSheet_();
  const entry = findUserByUsername_(usersSheet, username);
  if (!entry) {
    return { ok: false, error: 'USER_NOT_FOUND', message: 'User not found.' };
  }

  setUserPassword_(usersSheet, entry.row, temporaryPassword, true, auth.user.username);
  usersSheet.getRange(entry.row, 6).setValue('Active');
  usersSheet.getRange(entry.row, 8).setValue(0);
  usersSheet.getRange(entry.row, 9).setValue('');

  logAuthAudit_('auth_reset_password', auth.user.username, 'success', username);
  return {
    ok: true,
    action: 'auth_reset_password',
    message: 'Password reset. User must change password at next login.'
  };
}

function handleAuthSetUserPermissions_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  const username = normalizeUsernameMobile_(data.username || data.mobile || data.phone || '');
  if (!username) {
    return { ok: false, error: 'INVALID_INPUT', message: 'username is required.' };
  }

  const usersSheet = getOrCreateUsersSheet_();
  const entry = findUserByUsername_(usersSheet, username);
  if (!entry) {
    return { ok: false, error: 'USER_NOT_FOUND', message: 'User not found.' };
  }

  if (normalizeUserRole_(entry.user.role) === 'superadmin') {
    return { ok: false, error: 'INVALID_OPERATION', message: 'SuperAdmin always has full access and cannot be permission-limited.' };
  }

  const permissions = normalizeUserPermissions_(data.permissions, entry.user.role, false);
  usersSheet.getRange(entry.row, 16).setValue(serializeUserPermissions_(permissions));
  usersSheet.getRange(entry.row, 14).setValue(new Date());
  usersSheet.getRange(entry.row, 15).setValue(auth.user.username);

  logAuthAudit_('auth_set_user_permissions', auth.user.username, 'success', `${username}:${permissions.join(',')}`);
  const updated = findUserByUsername_(usersSheet, username);
  return {
    ok: true,
    action: 'auth_set_user_permissions',
    user: toPublicUser_(updated.user)
  };
}

function getManagedScriptSettingDefMap_() {
  const map = {};
  for (let i = 0; i < MANAGED_SCRIPT_SETTING_DEFS.length; i += 1) {
    const item = MANAGED_SCRIPT_SETTING_DEFS[i] || {};
    const key = String(item.key || '').trim();
    if (!key) continue;
    map[key] = {
      key: key,
      label: String(item.label || key),
      secret: !!item.secret
    };
  }
  return map;
}

function maskScriptSettingValue_(value, isSecret) {
  const raw = String(value || '').trim();
  if (!raw) return '';

  if (isSecret) {
    return `set (${raw.length} chars)`;
  }

  if (raw.length <= 8) {
    return `${raw.slice(0, 2)}***${raw.slice(-1)}`;
  }

  return `${raw.slice(0, 4)}...${raw.slice(-4)}`;
}

function getManagedScriptSettingsView_() {
  const scriptProperties = PropertiesService.getScriptProperties();
  const items = [];

  for (let i = 0; i < MANAGED_SCRIPT_SETTING_DEFS.length; i += 1) {
    const def = MANAGED_SCRIPT_SETTING_DEFS[i] || {};
    const key = String(def.key || '').trim();
    if (!key) continue;

    const value = String(scriptProperties.getProperty(key) || '').trim();
    items.push({
      key: key,
      label: String(def.label || key),
      secret: !!def.secret,
      hasValue: !!value,
      valuePreview: maskScriptSettingValue_(value, !!def.secret),
      updatedViaPanel: true
    });
  }

  return items;
}

function handleAuthGetApiSettings_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  return {
    ok: true,
    action: 'auth_get_api_settings',
    items: getManagedScriptSettingsView_()
  };
}

function handleAuthSetApiSettings_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  const settings = (data && typeof data.settings === 'object' && data.settings) ? data.settings : {};
  const clearKeysInput = Array.isArray(data.clearKeys) ? data.clearKeys : [];
  const clearMap = {};
  for (let i = 0; i < clearKeysInput.length; i += 1) {
    const key = String(clearKeysInput[i] || '').trim();
    if (key) clearMap[key] = true;
  }

  const defMap = getManagedScriptSettingDefMap_();
  const managedKeys = Object.keys(defMap);
  if (!managedKeys.length) {
    return { ok: false, error: 'SETTINGS_NOT_CONFIGURED', message: 'No managed script settings configured.' };
  }

  const scriptProperties = PropertiesService.getScriptProperties();
  const updatedKeys = [];
  const clearedKeys = [];

  for (let i = 0; i < managedKeys.length; i += 1) {
    const key = managedKeys[i];
    const hasSettingValue = Object.prototype.hasOwnProperty.call(settings, key);
    const shouldClear = !!clearMap[key];

    if (!hasSettingValue && !shouldClear) continue;

    if (shouldClear) {
      scriptProperties.deleteProperty(key);
      clearedKeys.push(key);
      continue;
    }

    const value = String(settings[key] == null ? '' : settings[key]).trim();
    if (!value) {
      continue;
    }

    scriptProperties.setProperty(key, value);
    updatedKeys.push(key);
  }

  if (!updatedKeys.length && !clearedKeys.length) {
    return {
      ok: false,
      error: 'NO_SETTING_CHANGES',
      message: 'Provide at least one managed setting value or clear key.'
    };
  }

  const details = [];
  if (updatedKeys.length) details.push(`updated:${updatedKeys.join(',')}`);
  if (clearedKeys.length) details.push(`cleared:${clearedKeys.join(',')}`);
  logAuthAudit_('auth_set_api_settings', auth.user.username, 'success', details.join(' | '));

  return {
    ok: true,
    action: 'auth_set_api_settings',
    updatedKeys: updatedKeys,
    clearedKeys: clearedKeys,
    items: getManagedScriptSettingsView_(),
    message: 'API settings updated.'
  };
}

function handleAuthListUsers_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  ensureBootstrapSuperAdminUser_();
  const usersSheet = getOrCreateUsersSheet_();
  const values = usersSheet.getDataRange().getValues();
  const users = [];
  for (let i = 1; i < values.length; i += 1) {
    const user = userFromSheetRow_(values[i]);
    if (!user.username) continue;
    users.push(toPublicUser_(user));
  }

  return {
    ok: true,
    action: 'auth_list_users',
    items: users
  };
}

function authorizeAdminRequest_(requestData, requiredRole) {
  ensureBootstrapSuperAdminUser_();
  const required = normalizeUserRole_(requiredRole || 'admin');
  const requestedAction = String((requestData && requestData.action) || '').trim().toLowerCase();
  const token = extractAuthToken_(requestData || {});

  if (token) {
    const tokenCheck = verifyAuthToken_(token);
    if (!tokenCheck.ok) {
      return { ok: false, error: tokenCheck.error || 'UNAUTHORIZED', message: tokenCheck.message || 'Invalid session token.' };
    }

    const username = normalizeUsernameMobile_(tokenCheck.payload.sub || '');
    const usersSheet = getOrCreateUsersSheet_();
    const userEntry = findUserByUsername_(usersSheet, username);
    if (!userEntry || String(userEntry.user.status || '').toLowerCase() !== 'active') {
      return { ok: false, error: 'UNAUTHORIZED', message: 'User is inactive or missing.' };
    }

    if (!hasRoleAccess_(userEntry.user.role, required)) {
      return { ok: false, error: 'FORBIDDEN', message: 'Insufficient role permissions.' };
    }

    if (requestedAction && !hasUserPermissionForAction_(userEntry.user, requestedAction)) {
      return { ok: false, error: 'FORBIDDEN', message: 'You do not have permission to access this module.' };
    }

    return {
      ok: true,
      authMode: 'token',
      user: userEntry.user,
      tokenPayload: tokenCheck.payload
    };
  }

  const suppliedPasscode = String(requestData.passcode || '').trim();
  if (suppliedPasscode && suppliedPasscode === ADMIN_PANEL_PASSCODE) {
    return {
      ok: true,
      authMode: 'legacy-passcode',
      user: {
        username: 'legacy-passcode',
        displayName: 'Legacy Passcode',
        role: 'superadmin',
        status: 'Active',
        forcePasswordChange: false
      }
    };
  }

  return { ok: false, error: 'UNAUTHORIZED', message: 'Admin authentication required.' };
}

function getOrCreateUsersSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(USERS_SHEET_NAME);

  if (!sheet) {
    sheet = spreadsheet.insertSheet(USERS_SHEET_NAME);
    sheet.appendRow(USERS_HEADERS);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(USERS_HEADERS);
  } else {
    ensureLeadsSheetHeaders_(sheet, USERS_HEADERS);
  }

  return sheet;
}

function getOrCreateAuthAuditSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(AUTH_AUDIT_SHEET_NAME);

  if (!sheet) {
    sheet = spreadsheet.insertSheet(AUTH_AUDIT_SHEET_NAME);
    sheet.appendRow(AUTH_AUDIT_HEADERS);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(AUTH_AUDIT_HEADERS);
  } else {
    ensureLeadsSheetHeaders_(sheet, AUTH_AUDIT_HEADERS);
  }

  return sheet;
}

function getOrCreateAuthRevokedTokensSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(AUTH_REVOKED_TOKENS_SHEET_NAME);

  if (!sheet) {
    sheet = spreadsheet.insertSheet(AUTH_REVOKED_TOKENS_SHEET_NAME);
    sheet.appendRow(AUTH_REVOKED_TOKENS_HEADERS);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(AUTH_REVOKED_TOKENS_HEADERS);
  } else {
    ensureLeadsSheetHeaders_(sheet, AUTH_REVOKED_TOKENS_HEADERS);
  }

  return sheet;
}

function revokeAuthToken_(token, tokenPayload) {
  const rawToken = String(token || '').trim();
  if (!rawToken) return;

  const tokenHash = digestSha256Hex_(rawToken);
  const now = new Date();
  const expSec = tokenPayload && tokenPayload.exp ? Number(tokenPayload.exp) : 0;
  const expiresAt = Number.isFinite(expSec) && expSec > 0
    ? new Date(expSec * 1000)
    : new Date(now.getTime() + (AUTH_TOKEN_HOURS * 60 * 60 * 1000));
  const username = tokenPayload && tokenPayload.sub ? normalizeUsernameMobile_(tokenPayload.sub) : '';

  const sheet = getOrCreateAuthRevokedTokensSheet_();
  sheet.appendRow([tokenHash, now, expiresAt, username]);
}

function isAuthTokenRevoked_(token) {
  const rawToken = String(token || '').trim();
  if (!rawToken) return false;

  const tokenHash = digestSha256Hex_(rawToken);
  const sheet = getOrCreateAuthRevokedTokensSheet_();
  const values = sheet.getDataRange().getValues();
  if (values.length <= 1) return false;

  const nowMs = Date.now();
  for (let i = values.length - 1; i >= 1; i -= 1) {
    const rowHash = String(values[i][0] || '').trim();
    if (rowHash !== tokenHash) continue;

    const expDate = values[i][2] instanceof Date ? values[i][2] : new Date(values[i][2]);
    const expMs = expDate instanceof Date ? expDate.getTime() : NaN;
    if (Number.isFinite(expMs) && expMs > nowMs) {
      return true;
    }
    return false;
  }

  return false;
}

function logAuthAudit_(action, username, outcome, details) {
  try {
    const sheet = getOrCreateAuthAuditSheet_();
    sheet.appendRow([
      new Date(),
      String(action || '').trim(),
      normalizeUsernameMobile_(username || ''),
      String(outcome || '').trim(),
      'script',
      String(details || '').slice(0, 500)
    ]);
  } catch (err) {
    // Non-fatal: auth flow should proceed even if audit write fails.
  }
}

function ensureBootstrapSuperAdminUser_() {
  const usersSheet = getOrCreateUsersSheet_();
  const configuredMobile = String(PropertiesService.getScriptProperties().getProperty('BOOTSTRAP_SUPERADMIN_MOBILE') || EVENT_BOOKING_PHONE || '').trim();
  const username = normalizeUsernameMobile_(configuredMobile);
  const configuredPassword = String(PropertiesService.getScriptProperties().getProperty('BOOTSTRAP_SUPERADMIN_PASSWORD') || ADMIN_PANEL_PASSCODE).trim();
  const safePassword = configuredPassword || ADMIN_PANEL_PASSCODE;

  if (!username) {
    throw new Error('BOOTSTRAP_SUPERADMIN_MOBILE is not configured.');
  }

  const existing = findUserByUsername_(usersSheet, username);
  if (existing) {
    return { created: false, username: username };
  }

  const salt = generatePasswordSalt_();
  const hash = hashPassword_(safePassword, salt);
  const now = new Date();
  usersSheet.appendRow([
    username,
    'Super Admin',
    'superadmin',
    hash,
    salt,
    'Active',
    'Yes',
    0,
    '',
    '',
    '',
    now,
    'bootstrap',
    now,
    'bootstrap',
    serializeUserPermissions_(ADMIN_PERMISSION_KEYS)
  ]);

  logAuthAudit_('auth_bootstrap', username, 'success', 'created');
  return { created: true, username: username };
}

function findUserByUsername_(sheet, username) {
  const normalized = normalizeUsernameMobile_(username || '');
  if (!normalized) return null;
  const values = sheet.getDataRange().getValues();
  if (values.length <= 1) return null;

  for (let i = values.length - 1; i >= 1; i -= 1) {
    const rowUser = normalizeUsernameMobile_(values[i][0] || '');
    if (rowUser === normalized) {
      return {
        row: i + 1,
        user: userFromSheetRow_(values[i])
      };
    }
  }

  return null;
}

function userFromSheetRow_(row) {
  const role = normalizeUserRole_(row[2] || 'admin');
  return {
    username: normalizeUsernameMobile_(row[0] || ''),
    displayName: String(row[1] || '').trim(),
    role: role,
    passwordHash: String(row[3] || '').trim(),
    passwordSalt: String(row[4] || '').trim(),
    status: String(row[5] || '').trim() || 'Active',
    forcePasswordChange: toBoolean_(row[6], false),
    failedAttempts: Number(row[7] || 0) || 0,
    lockoutUntil: row[8] || '',
    lastLoginAt: row[9] || '',
    lastLoginIp: String(row[10] || '').trim(),
    createdAt: row[11] || '',
    createdBy: String(row[12] || '').trim(),
    updatedAt: row[13] || '',
    updatedBy: String(row[14] || '').trim(),
    permissions: normalizeUserPermissions_(row[15], role, true)
  };
}

function toPublicUser_(user) {
  const role = normalizeUserRole_(user.role || 'admin');
  const permissions = normalizeUserPermissions_(user.permissions, role, true);
  return {
    username: normalizeUsernameMobile_(user.username || ''),
    displayName: String(user.displayName || '').trim(),
    role: role,
    status: String(user.status || 'Active').trim(),
    forcePasswordChange: !!user.forcePasswordChange,
    lastLoginAt: user.lastLoginAt || '',
    createdAt: user.createdAt || '',
    updatedAt: user.updatedAt || '',
    permissions: permissions,
    hasFullAccess: role === 'superadmin'
  };
}

function normalizeUserPermissions_(value, role, useDefaultIfEmpty) {
  const normalizedRole = normalizeUserRole_(role || 'admin');
  if (normalizedRole === 'superadmin') {
    return ADMIN_PERMISSION_KEYS.slice();
  }

  let rawItems = [];
  if (Array.isArray(value)) {
    rawItems = value;
  } else {
    const text = String(value == null ? '' : value).trim();
    if (text) {
      try {
        const parsed = JSON.parse(text);
        rawItems = Array.isArray(parsed) ? parsed : [];
      } catch (err) {
        rawItems = text.split(/[|,\s]+/g);
      }
    }
  }

  const allowMap = {};
  const normalized = [];
  for (let i = 0; i < rawItems.length; i += 1) {
    const key = normalizePermissionKey_(rawItems[i]);
    if (!key || allowMap[key]) continue;
    allowMap[key] = true;
    normalized.push(key);
  }

  if (!normalized.length && useDefaultIfEmpty) {
    return ADMIN_DEFAULT_PERMISSIONS.slice();
  }

  return normalized;
}

function normalizePermissionKey_(value) {
  const key = String(value == null ? '' : value).trim();
  if (!key) return '';

  const aliases = {
    dashboard: 'dashboard',
    home: 'dashboard',
    cashier: 'cashier',
    verification: 'verification',
    coupons: 'verification',
    eventguests: 'eventGuests',
    eventguestreports: 'eventGuests',
    eventscanner: 'eventScanner',
    evententryscanner: 'eventScanner',
    evententry: 'eventScanner',
    scanner: 'eventScanner',
    eventmanagement: 'eventManagement',
    events: 'eventManagement',
    menueditor: 'menuEditor',
    menupriceeditor: 'menuEditor',
    menumanagement: 'menuEditor',
    cashapprovals: 'cashApprovals',
    approvals: 'cashApprovals',
    usermanagement: 'userManagement',
    users: 'userManagement'
  };

  const normalized = key.toLowerCase().replace(/[^a-z0-9]/g, '');
  const mapped = aliases[normalized] || key;
  for (let i = 0; i < ADMIN_PERMISSION_KEYS.length; i += 1) {
    if (ADMIN_PERMISSION_KEYS[i] === mapped) return mapped;
  }
  return '';
}

function serializeUserPermissions_(permissions) {
  const normalized = normalizeUserPermissions_(permissions, 'admin', false);
  return JSON.stringify(normalized);
}

function userHasPermission_(user, permissionKey) {
  const normalizedRole = normalizeUserRole_(user && user.role || 'admin');
  if (normalizedRole === 'superadmin') return true;

  const key = normalizePermissionKey_(permissionKey);
  if (!key) return true;

  const perms = normalizeUserPermissions_(user && user.permissions, normalizedRole, true);
  if (key === 'menuEditor' && perms.indexOf('eventManagement') !== -1) {
    return true;
  }
  if (key === 'eventScanner' && perms.indexOf('eventGuests') !== -1) {
    return true;
  }
  return perms.indexOf(key) !== -1;
}

function hasUserPermissionForAction_(user, action) {
  const actionValue = String(action || '').trim().toLowerCase();
  if (!actionValue) return true;

  const permissionByAction = {
    auth_me: 'dashboard',
    auth_logout: 'dashboard',
    auth_change_password: 'dashboard',
    admin_cash_summary: 'cashier',
    admin_issue_cash_paid_pass: 'cashier',
    admin_request_cash_handover: 'cashier',
    admin_request_cash_cancel: 'cashier',
    verify: 'verification',
    redeem: 'verification',
    regen_coupon: 'verification',
    regenerate_coupon: 'verification',
    'regen-coupon': 'verification',
    event_guest_report: 'eventGuests',
    admin_preview_event_qr: 'eventScanner',
    admin_batch_checkin_event_qr: 'eventScanner',
    admin_list_events: 'eventManagement',
    admin_event_list: 'eventManagement',
    admin_create_event: 'eventManagement',
    admin_update_event: 'eventManagement',
    admin_toggle_event: 'eventManagement',
    admin_menu_editor_load: 'menuEditor',
    admin_menu_editor_save_changes: 'menuEditor',
    admin_menu_editor_add_row: 'menuEditor',
    admin_menu_editor_delete_rows: 'menuEditor',
    admin_menu_editor_set_visibility: 'menuEditor',
    superadmin_cash_dashboard: 'cashApprovals',
    superadmin_approve_cash_handover: 'cashApprovals',
    superadmin_resolve_cash_cancel: 'cashApprovals',
    auth_list_users: 'userManagement',
    auth_create_user: 'userManagement',
    auth_set_user_status: 'userManagement',
    auth_reset_password: 'userManagement',
    auth_set_user_permissions: 'userManagement',
    auth_get_api_settings: 'userManagement',
    auth_set_api_settings: 'userManagement'
  };

  const permissionKey = permissionByAction[actionValue] || '';
  return userHasPermission_(user, permissionKey);
}

function setUserPassword_(sheet, row, password, forcePasswordChange, actorUsername) {
  const salt = generatePasswordSalt_();
  const hash = hashPassword_(password, salt);
  const now = new Date();

  sheet.getRange(row, 4).setValue(hash);
  sheet.getRange(row, 5).setValue(salt);
  sheet.getRange(row, 7).setValue(forcePasswordChange ? 'Yes' : 'No');
  sheet.getRange(row, 14).setValue(now);
  sheet.getRange(row, 15).setValue(String(actorUsername || '').trim());
}

function registerFailedLoginAttempt_(sheet, row, user) {
  const nextAttempts = (Number(user.failedAttempts || 0) || 0) + 1;
  const now = new Date();
  let lockoutUntil = '';
  let locked = false;
  let storedAttempts = nextAttempts;

  if (nextAttempts >= AUTH_LOCKOUT_MAX_ATTEMPTS) {
    lockoutUntil = new Date(now.getTime() + (AUTH_LOCKOUT_MINUTES * 60 * 1000));
    locked = true;
    storedAttempts = 0;
  }

  sheet.getRange(row, 8).setValue(storedAttempts);
  sheet.getRange(row, 9).setValue(lockoutUntil || '');
  sheet.getRange(row, 14).setValue(now);
  sheet.getRange(row, 15).setValue('auth-login-failure');

  return {
    locked: locked,
    lockoutUntil: lockoutUntil,
    failedAttempts: storedAttempts
  };
}

function clearFailedLoginAttemptState_(sheet, row, actor) {
  sheet.getRange(row, 8).setValue(0);
  sheet.getRange(row, 9).setValue('');
  sheet.getRange(row, 14).setValue(new Date());
  sheet.getRange(row, 15).setValue(String(actor || '').trim());
}

function validatePasswordPolicy_(password) {
  const value = String(password || '');
  if (value.length < AUTH_PASSWORD_MIN_LENGTH) {
    return { ok: false, message: `Password must be at least ${AUTH_PASSWORD_MIN_LENGTH} characters.` };
  }
  if (!/[A-Za-z]/.test(value) || !/\d/.test(value)) {
    return { ok: false, message: 'Password must include both letters and numbers.' };
  }
  return { ok: true };
}

function normalizeUsernameMobile_(value) {
  const digits = normalizePhoneDigits_(value);
  if (!digits) return '';
  if (digits.length < 10 || digits.length > 15) return '';
  return digits;
}

function normalizeUserRole_(value) {
  const role = String(value || '').trim().toLowerCase();
  return role === 'superadmin' ? 'superadmin' : 'admin';
}

function hasRoleAccess_(actualRole, requiredRole) {
  const actual = normalizeUserRole_(actualRole || 'admin');
  const required = normalizeUserRole_(requiredRole || 'admin');
  if (required === 'admin') return actual === 'admin' || actual === 'superadmin';
  return actual === 'superadmin';
}

function isAccountLocked_(lockoutUntil) {
  if (!lockoutUntil) return false;
  const asDate = lockoutUntil instanceof Date ? lockoutUntil : new Date(lockoutUntil);
  if (!(asDate instanceof Date) || Number.isNaN(asDate.getTime())) return false;
  return asDate.getTime() > Date.now();
}

function formatLockoutDate_(lockoutUntil) {
  const asDate = lockoutUntil instanceof Date ? lockoutUntil : new Date(lockoutUntil);
  if (!(asDate instanceof Date) || Number.isNaN(asDate.getTime())) return 'later';
  return Utilities.formatDate(asDate, SHEET_TIMEZONE, 'dd MMM yyyy, hh:mm a');
}

function generatePasswordSalt_() {
  return Utilities.getUuid().replace(/-/g, '');
}

function hashPassword_(password, salt) {
  let value = `${String(salt || '').trim()}:${String(password || '')}`;
  for (let i = 0; i < 1200; i += 1) {
    value = digestSha256Hex_(value);
  }
  return value;
}

function digestSha256Hex_(value) {
  const bytes = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, String(value || ''), Utilities.Charset.UTF_8);
  return bytesToHex_(bytes);
}

function bytesToHex_(bytes) {
  return bytes.map((byte) => {
    const safe = byte < 0 ? byte + 256 : byte;
    const hex = safe.toString(16);
    return hex.length === 1 ? `0${hex}` : hex;
  }).join('');
}

function extractAuthToken_(requestData) {
  const data = requestData || {};
  const authorization = String(data.authorization || data.Authorization || '').trim();
  if (/^bearer\s+/i.test(authorization)) {
    return authorization.replace(/^bearer\s+/i, '').trim();
  }

  return String(data.token || data.authToken || data.jwt || '').trim();
}

function getAuthJwtSecret_() {
  const configured = String(PropertiesService.getScriptProperties().getProperty('AUTH_JWT_SECRET') || '').trim();
  if (configured) return configured;
  return digestSha256Hex_(`${SPREADSHEET_ID}:${ADMIN_PANEL_PASSCODE}:namastekalyan-auth`);
}

function buildAuthToken_(user) {
  const nowSec = Math.floor(Date.now() / 1000);
  const expSec = nowSec + (AUTH_TOKEN_HOURS * 60 * 60);
  const header = { alg: 'HS256', typ: 'JWT' };
  const payload = {
    sub: normalizeUsernameMobile_(user.username || ''),
    role: normalizeUserRole_(user.role || 'admin'),
    name: String(user.displayName || '').trim(),
    iat: nowSec,
    exp: expSec
  };

  const encodedHeader = encodeTokenPart_(header);
  const encodedPayload = encodeTokenPart_(payload);
  const signingInput = `${encodedHeader}.${encodedPayload}`;
  const signature = hmacSha256Base64Url_(signingInput, getAuthJwtSecret_());

  return {
    token: `${signingInput}.${signature}`,
    expiresAt: new Date(expSec * 1000).toISOString()
  };
}

function verifyAuthToken_(token) {
  const raw = String(token || '').trim();
  if (!raw) return { ok: false, error: 'UNAUTHORIZED', message: 'Missing auth token.' };

  const parts = raw.split('.');
  if (parts.length !== 3) {
    return { ok: false, error: 'INVALID_TOKEN', message: 'Malformed token.' };
  }

  const signingInput = `${parts[0]}.${parts[1]}`;
  const expectedSignature = hmacSha256Base64Url_(signingInput, getAuthJwtSecret_());
  if (parts[2] !== expectedSignature) {
    return { ok: false, error: 'INVALID_TOKEN', message: 'Token signature mismatch.' };
  }

  let payload = {};
  try {
    payload = JSON.parse(decodeTokenPart_(parts[1]));
  } catch (err) {
    return { ok: false, error: 'INVALID_TOKEN', message: 'Token payload is invalid.' };
  }

  const nowSec = Math.floor(Date.now() / 1000);
  if (!payload.exp || Number(payload.exp) <= nowSec) {
    return { ok: false, error: 'TOKEN_EXPIRED', message: 'Session expired. Please login again.' };
  }

  if (!payload.sub) {
    return { ok: false, error: 'INVALID_TOKEN', message: 'Token subject is missing.' };
  }

  if (isAuthTokenRevoked_(raw)) {
    return { ok: false, error: 'TOKEN_REVOKED', message: 'Session expired. Please login again.' };
  }

  return { ok: true, payload: payload };
}

function encodeTokenPart_(obj) {
  return Utilities.base64EncodeWebSafe(JSON.stringify(obj), Utilities.Charset.UTF_8).replace(/=+$/g, '');
}

function decodeTokenPart_(encoded) {
  const bytes = Utilities.base64DecodeWebSafe(String(encoded || ''));
  return Utilities.newBlob(bytes).getDataAsString();
}

function hmacSha256Base64Url_(content, secret) {
  const signatureBytes = Utilities.computeHmacSha256Signature(String(content || ''), String(secret || ''), Utilities.Charset.UTF_8);
  return Utilities.base64EncodeWebSafe(signatureBytes).replace(/=+$/g, '');
}

function normalizePhoneDigits_(value) {
  return String(value || '').replace(/\D/g, '');
}

function normalizeCountryCode_(value) {
  const digits = normalizePhoneDigits_(value);
  return digits || '91';
}

function formatInternationalPhone_(countryCode, localPhone) {
  const cc = normalizeCountryCode_(countryCode);
  const phone = normalizePhoneDigits_(localPhone);
  if (!phone) return '';
  if (phone.startsWith(cc) && phone.length > 10) return phone;
  return `${cc}${phone}`;
}

function isValidPhoneForCountry_(localPhone, countryCode) {
  const phone = normalizePhoneDigits_(localPhone);
  const cc = normalizeCountryCode_(countryCode);
  if (!phone) return false;
  if (cc === '91') return /^\d{10}$/.test(phone);
  return /^\d{6,15}$/.test(phone);
}

function buildCrmLeadPayload_(lead) {
  const token = getCrmApiToken_();
  const dobIso = toIsoDateString_(lead.dobIso || lead.dob || '');
  const anniversaryIso = toIsoDateString_(lead.anniversaryIso || lead.anniversary || '');
  return {
    api_token: token,
    contact_name: lead.name,
    contact_email: '',
    contact_phone: toPlusInternationalPhone_(lead.phone),
    // Optional custom fields supported by API 1.0
    prize: lead.prize,
    status: lead.status,
    source: lead.source,
    visit_count: lead.visitCount,
    date_of_birth: dobIso,
    dob: dobIso,
    date_of_anniversary: anniversaryIso,
    anniversary_date: anniversaryIso,
    lead_timestamp: lead.timestamp
  };
}

function pushLeadToCrm_(payload) {
  if (!CRM_API_URL) {
    return { attempted: false, success: false, status: '', message: 'CRM API URL not configured', attempts: [] };
  }

  if (!payload.api_token) {
    return { attempted: false, success: false, status: '', message: 'CRM_API_TOKEN missing in Script Properties', attempts: [] };
  }

  const crmRequestPayload = {
    api_token: payload.api_token,
    contact_name: payload.contact_name,
    contact_email: payload.contact_email,
    contact_phone: payload.contact_phone,
    prize: payload.prize,
    status: payload.status,
    source: payload.source,
    visit_count: payload.visit_count,
    date_of_birth: payload.date_of_birth,
    dob: payload.dob,
    date_of_anniversary: payload.date_of_anniversary,
    anniversary_date: payload.anniversary_date,
    lead_timestamp: payload.lead_timestamp
  };

  const attempts = [];
  const first = executeCrmAttempt_(crmRequestPayload);
  attempts.push(first);

  if (!first.success) {
    Utilities.sleep(1200);
    const second = executeCrmAttempt_(crmRequestPayload);
    attempts.push(second);
  }

  const finalAttempt = attempts[attempts.length - 1] || { success: false, status: '', message: 'No attempt executed' };
  return {
    attempted: true,
    status: finalAttempt.status,
    success: finalAttempt.success,
    message: finalAttempt.message,
    attempts: attempts
  };
}

function executeCrmAttempt_(crmRequestPayload) {
  try {
    const response = UrlFetchApp.fetch(CRM_API_URL, {
      method: 'post',
      contentType: 'application/x-www-form-urlencoded',
      headers: {
        accept: 'application/json'
      },
      payload: crmRequestPayload,
      muteHttpExceptions: true
    });

    const status = response.getResponseCode();
    return {
      success: status >= 200 && status < 300,
      status: status,
      message: String(response.getContentText() || '').slice(0, 500)
    };
  } catch (err) {
    return {
      success: false,
      status: '',
      message: String(err)
    };
  }
}

function toPlusInternationalPhone_(value) {
  const digits = normalizePhoneDigits_(value);
  return digits ? `+${digits}` : '';
}

function getCrmApiToken_() {
  return String(PropertiesService.getScriptProperties().getProperty('CRM_API_TOKEN') || '').trim();
}

function appendManualLeadRow_(sheet, data) {
  const now = new Date();
  const status = 'Unredeemed';
  const prize = 'Try Again';
  const couponCode = '';
  const visitCount = 1;
  const dob = '';
  const anniversary = '';
  const dobIso = '';
  const anniversaryIso = '';
  const row = [
    now,
    data.name,
    data.phone,
    prize,
    status,
    dob,
    anniversary,
    data.source,
    visitCount,
    'Pending',
    '',
    '',
    couponCode
  ];

  sheet.appendRow(row);
  return {
    row: sheet.getLastRow(),
    name: data.name,
    phone: data.phone,
    prize: prize,
    status: status,
    timestamp: now,
    source: data.source,
    visitCount: visitCount,
    dob: dob,
    anniversary: anniversary,
    couponCode: couponCode,
    dobIso: dobIso,
    anniversaryIso: anniversaryIso,
    crmCode: data.crmCode,
    crmMessage: data.crmMessage
  };
}

function normalizeDisplayDate_(value) {
  const text = String(value || '').trim();
  if (!text) return '';

  const ddmmyyyy = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (ddmmyyyy) {
    return isValidDateParts_(Number(ddmmyyyy[3]), Number(ddmmyyyy[2]), Number(ddmmyyyy[1])) ? text : '';
  }

  const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (iso && isValidDateParts_(Number(iso[1]), Number(iso[2]), Number(iso[3]))) {
    return `${iso[3]}/${iso[2]}/${iso[1]}`;
  }

  return '';
}

function toIsoDateString_(value) {
  const text = String(value || '').trim();
  if (!text) return '';

  const iso = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (iso && isValidDateParts_(Number(iso[1]), Number(iso[2]), Number(iso[3]))) return text;

  const ddmmyyyy = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (ddmmyyyy && isValidDateParts_(Number(ddmmyyyy[3]), Number(ddmmyyyy[2]), Number(ddmmyyyy[1]))) {
    return `${ddmmyyyy[3]}-${ddmmyyyy[2]}-${ddmmyyyy[1]}`;
  }

  return '';
}

function isValidDateParts_(year, month, day) {
  if (!year || !month || !day) return false;
  const candidate = new Date(year, month - 1, day);
  return candidate.getFullYear() === year
    && candidate.getMonth() === (month - 1)
    && candidate.getDate() === day;
}

function isWithinHours_(timestampValue, hours) {
  if (!timestampValue) return false;

  const date = timestampValue instanceof Date ? timestampValue : new Date(timestampValue);
  if (!(date instanceof Date) || isNaN(date.getTime())) return false;

  const cutoffMs = Number(hours || 0) * 60 * 60 * 1000;
  return (new Date().getTime() - date.getTime()) < cutoffMs;
}

function isWinnerPrize_(prize) {
  const normalized = normalizePrizeLabel_(prize).toLowerCase();
  return normalized && !normalized.startsWith('try again');
}

function normalizePrizeLabel_(prize) {
  const text = String(prize || '').trim().replace(/\s+/g, ' ').toLowerCase();
  if (!text) return '';

  const map = {
    'dessert on the house': 'Dessert on the House',
    'mocktail on the house': 'Mocktail on the House',
    'aerated drink on the house': 'Aerated Drink on the House',
    'starter on the house': 'Starter on the House',
    '10% off': '10% OFF',
    '15% off': '15% OFF',
    '20% off': '20% OFF',
    '25% off': '25% OFF',
    'try again': 'Try Again',
    'try again next time': 'Try Again'
  };

  if (map[text]) return map[text];
  if (text.startsWith('try again')) return 'Try Again';
  return String(prize || '').trim();
}

function isAdminProtectedAction_(action) {
  const value = String(action || '').trim().toLowerCase();
  return value === 'verify'
    || value === 'redeem'
    || value === 'regen_coupon'
    || value === 'regenerate_coupon'
    || value === 'regen-coupon'
    || value === 'event_guest_report'
    || value === 'event_transactions_report'
    || value === 'migrate_events_sheet_format'
    || value === 'migrate_event_sheet_format'
    || value === 'reset_events_sheet_format'
    || value === 'reset_events_data'
    || value === 'seed_events_sample'
    || value === 'seed_event_sample'
    || value === 'seed_dj_events_apr_2026'
    || value === 'seed_dj_events'
    || value === 'seed_paid_event_sample'
    || value === 'seed_paid_event'
    || value === 'create_test_paid_tx'
    || value === 'seed_test_paid_tx'
    || value === 'admin_list_events'
    || value === 'admin_event_list'
    || value === 'send_test_event_email'
    || value === 'test_event_email';
}





function generateCouponCode_(rowNumber, prize, phone) {
  const prefixMap = {
    'dessert on the house': 'DESS',
    'mocktail on the house': 'MOCK',
    'aerated drink on the house': 'AERA',
    'starter on the house': 'STRT',
    '10% off': 'OFF10',
    '15% off': 'OFF15',
    '20% off': 'OFF20',
    '25% off': 'OFF25'
  };

  const normalizedPrize = String(prize || '').trim().toLowerCase();
  const prefix = prefixMap[normalizedPrize] || 'COUP';
  const phoneTail = normalizePhoneDigits_(phone).slice(-4).padStart(4, '0');
  const rowPart = String(Number(rowNumber || 0)).padStart(5, '0');

  return `NK-${prefix}-${rowPart}-${phoneTail}`;
}

function ensureCouponCodeForRow_(sheet, rowInfo) {
  if (!rowInfo || !sheet) return '';
  if (!isWinnerPrize_(rowInfo.prize)) return '';

  const existingCode = String(rowInfo.couponCode || '').trim();
  if (existingCode) return existingCode;

  const generated = generateCouponCode_(rowInfo.row, rowInfo.prize, rowInfo.phone);
  sheet.getRange(rowInfo.row, 13).setValue(generated);
  rowInfo.couponCode = generated;
  return generated;
}

// ==================== QR CODE TRACKING FUNCTIONS ====================

function createQrScansSheet() {
  const sheet = getOrCreateQrScansSheet_();
  return {
    ok: true,
    message: 'QR Scans sheet is ready',
    sheetName: sheet.getName(),
    totalRows: sheet.getLastRow(),
    totalScans: Math.max(0, sheet.getLastRow() - 1)
  };
}

function createTestQrScanEntry() {
  const sheet = getOrCreateQrScansSheet_();
  const timestamp = new Date();
  const scanNumber = sheet.getLastRow();
  const userAgent = 'Mozilla/5.0 (Linux; Android 14; Test Device) AppleWebKit/537.36 Chrome/123.0 Mobile Safari/537.36';
  const referer = 'manual-test';
  const ipAddress = '8.8.8.8';
  const location = lookupIpLocation_(ipAddress);

  sheet.appendRow([
    timestamp,
    userAgent,
    referer,
    ipAddress,
    scanNumber,
    location.city,
    location.region,
    location.country,
    'Mobile',        // device
    'Chrome',        // browser
    'Android',       // os
    'en-IN',         // language
    '393x851'        // screen
  ]);

  return {
    ok: true,
    row: sheet.getLastRow(),
    scanNumber: scanNumber,
    timestamp: timestamp.toISOString(),
    ipAddress: ipAddress,
    location: [location.city, location.region, location.country].filter((value) => value).join(', ')
  };
}

function getOrCreateQrScansSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(QR_SCANS_SHEET_NAME);
  const headers = ['Timestamp', 'User Agent', 'Referer', 'IP Address', 'Scan Number', 'City', 'Region', 'Country', 'Device', 'Browser', 'OS', 'Language', 'Screen'];

  if (!sheet) {
    sheet = spreadsheet.insertSheet(QR_SCANS_SHEET_NAME);
    sheet.appendRow(headers);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  } else {
    ensureLeadsSheetHeaders_(sheet, headers);
  }

  return sheet;
}

function handleQrScanTracking_(userAgent, referer, remoteAddr) {
  try {
    const sheet = getOrCreateQrScansSheet_();
    const timestamp = new Date();
    const scanNumber = sheet.getLastRow(); // Row number is the scan count (since row 1 is header)
    const ipAddress = String(remoteAddr || '').trim();
    const location = lookupIpLocation_(ipAddress);

    // Append scan record
    sheet.appendRow([
      timestamp,
      userAgent,
      referer,
      ipAddress,
      scanNumber,
      location.city,
      location.region,
      location.country,
      '', '', '', '', ''
    ]);

    // Check if we need to send email notification (every 100 scans)
    if (scanNumber % EMAIL_SCAN_INTERVAL === 0) {
      sendQrScanNotificationEmail_(scanNumber);
    }

    return jsonResponse({
      ok: true,
      result: 'qr_scan_tracked',
      scanNumber: scanNumber,
      timestamp: timestamp.toISOString(),
      emailNotificationSent: (scanNumber % EMAIL_SCAN_INTERVAL === 0)
    });
  } catch (err) {
    return jsonResponse({
      ok: false,
      error: 'QR_TRACKING_FAILED',
      message: String(err)
    });
  }
}

function handleQrScanClientTracking_(data) {
  try {
    const sheet = getOrCreateQrScansSheet_();
    const timestamp = new Date();
    const scanNumber = sheet.getLastRow(); // data rows = lastRow - 1 (header is row 1)

    sheet.appendRow([
      timestamp,
      String(data.userAgent  || '').slice(0, 500),
      String(data.referer    || 'QR Code Direct'),
      String(data.ip         || ''),
      scanNumber,
      String(data.city       || ''),
      String(data.region     || ''),
      String(data.country    || ''),
      String(data.device     || ''),
      String(data.browser    || ''),
      String(data.os         || ''),
      String(data.language   || ''),
      String(data.screen     || '')
    ]);

    if (scanNumber % EMAIL_SCAN_INTERVAL === 0 && scanNumber > 0) {
      sendQrScanNotificationEmail_(scanNumber);
    }

    return jsonResponse({
      ok: true,
      result: 'qr_scan_tracked',
      scanNumber: scanNumber,
      timestamp: timestamp.toISOString()
    });
  } catch (err) {
    return jsonResponse({ ok: false, error: 'QR_CLIENT_TRACKING_FAILED', message: String(err) });
  }
}

function lookupIpLocation_(ipAddress) {
  const defaultLocation = { city: '', region: '', country: '' };
  const ip = String(ipAddress || '').trim();
  if (!ip || !isPublicIp_(ip)) return defaultLocation;

  try {
    const response = UrlFetchApp.fetch(`https://ipapi.co/${encodeURIComponent(ip)}/json/`, {
      method: 'get',
      muteHttpExceptions: true,
      followRedirects: true
    });

    if (response.getResponseCode() < 200 || response.getResponseCode() >= 300) {
      return defaultLocation;
    }

    const payload = JSON.parse(String(response.getContentText() || '{}'));
    return {
      city: safeText_(payload.city),
      region: safeText_(payload.region),
      country: safeText_(payload.country_name)
    };
  } catch (err) {
    return defaultLocation;
  }
}

function isPublicIp_(ip) {
  const value = String(ip || '').trim();
  if (!value) return false;
  if (value === '::1' || value === '127.0.0.1') return false;
  if (/^10\./.test(value)) return false;
  if (/^192\.168\./.test(value)) return false;
  if (/^172\.(1[6-9]|2\d|3[0-1])\./.test(value)) return false;
  if (/^(fc|fd)/i.test(value)) return false;
  return true;
}

function safeText_(value) {
  return String(value || '').trim();
}

function sendQrScanNotificationEmail_(totalScans) {
  try {
    const subject = `🎉 Menu QR Code Milestone: ${totalScans} Scans!`;
    const htmlBody = buildQrEmailTemplate_(totalScans);

    MailApp.sendEmail(EMAIL_TO, subject, `Menu QR Code has been scanned ${totalScans} times.`, {
      from: EMAIL_FROM_NAME + ' <' + EMAIL_FROM + '>',
      htmlBody: htmlBody,
      name: EMAIL_FROM_NAME
    });

    return true;
  } catch (err) {
    Logger.log('Email sending failed: ' + String(err));
    return false;
  }
}

function buildQrEmailTemplate_(totalScans) {
  const menuUrl = QR_MENU_URL + QR_TRACKING_URL_SUFFIX;
  const currentDate = new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' });

  return `
    <html>
      <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0;">
          <h1 style="margin: 0; font-size: 32px;">🎉 Milestone Reached!</h1>
          <p style="margin: 10px 0 0 0; font-size: 18px;">Menu QR Code Engagement Report</p>
        </div>
        
        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #667eea;">
            <h2 style="margin: 0 0 10px 0; color: #667eea;">Total Scans: <span style="font-size: 36px; font-weight: bold;">${totalScans}</span></h2>
            <p style="margin: 0; color: #666;">Your Menu QR Code has reached <strong>${totalScans} scans!</strong></p>
          </div>

          <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #333;">📊 Quick Stats</h3>
            <ul style="margin: 0; padding-left: 20px; color: #666;">
              <li style="margin-bottom: 10px;">Current Total Scans: <strong>${totalScans}</strong></li>
              <li style="margin-bottom: 10px;">Last Milestone Email: <strong>${totalScans} scans</strong></li>
              <li style="margin-bottom: 10px;">Next Milestone: <strong>${totalScans + EMAIL_SCAN_INTERVAL} scans</strong></li>
              <li>Report Time: <strong>${currentDate}</strong></li>
            </ul>
          </div>

          <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #333;">🔗 QR Code Details</h3>
            <p style="margin: 0 0 10px 0; color: #666;">
              <strong>QR Code URL:</strong> ${QR_MENU_URL}
            </p>
            <p style="margin: 0; color: #666;">
              Each scan is tracked with timestamp, user agent, and IP address for analytics.
            </p>
          </div>

          <div style="background: #fffbea; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;">
            <p style="margin: 0; color: #664d03;">
              <strong>💡 Tip:</strong> You can view detailed scan analytics in the "QR Scans" sheet of your Google Spreadsheet.
            </p>
          </div>
        </div>

        <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
          <p style="margin: 0;">This is an automated notification from Dcore Systems.</p>
          <p style="margin: 10px 0 0 0;">Sent from: ${EMAIL_FROM}</p>
        </div>
      </body>
    </html>
  `;
}

function getQrScanReport_() {
  try {
    const sheet = getOrCreateQrScansSheet_();
    const totalScans = Math.max(0, sheet.getLastRow() - 1); // Subtract 1 for header row
    const recentScans = totalScans > 0 ? sheet.getRange(Math.max(2, sheet.getLastRow() - 9), 1, Math.min(10, sheet.getLastRow() - 1), 13).getValues() : [];

    return {
      ok: true,
      totalScans: totalScans,
      qrUrl: QR_MENU_URL + QR_TRACKING_URL_SUFFIX,
      generatedAt: new Date().toISOString(),
      recentScans: recentScans
    };
  } catch (err) {
    return {
      ok: false,
      error: 'REPORT_GENERATION_FAILED',
      message: String(err)
    };
  }
}

function buildQrScanReportHtml_() {
  try {
    const sheet = getOrCreateQrScansSheet_();
    const totalScans = Math.max(0, sheet.getLastRow() - 1);
    const currentDate = new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' });
    
    // Get all scans
    let scanData = [];
    if (totalScans > 0) {
      const allScans = sheet.getRange(2, 1, sheet.getLastRow() - 1, 13).getValues();
      scanData = allScans.map((row, idx) => ({
        number: idx + 1,
        timestamp: row[0],
        userAgent: String(row[1] || '').slice(0, 200),
        referer: String(row[2] || '').slice(0, 100) || 'Direct',
        ipAddress: String(row[3] || ''),
        scanNumber: row[4],
        city: String(row[5] || ''),
        region: String(row[6] || ''),
        country: String(row[7] || ''),
        device: String(row[8] || ''),
        browser: String(row[9] || ''),
        os: String(row[10] || ''),
        language: String(row[11] || ''),
        screen: String(row[12] || '')
      }));
    }

    // Calculate milestones
    const nextMilestone = Math.ceil((totalScans + 1) / EMAIL_SCAN_INTERVAL) * EMAIL_SCAN_INTERVAL;
    const scansUntilNext = nextMilestone - totalScans;
    const lastMilestone = Math.floor(totalScans / EMAIL_SCAN_INTERVAL) * EMAIL_SCAN_INTERVAL;

    // Build HTML
    let scanRowsHtml = '';
    if (scanData.length > 0) {
      // Show last 50 scans
      scanData.slice(-50).reverse().forEach((scan) => {
        const timestamp = scan.timestamp instanceof Date ? scan.timestamp.toLocaleString('en-IN') : String(scan.timestamp);
        // Prefer detected device field, fall back to UA sniff for old rows
        const deviceRaw = scan.device || (scan.userAgent.toLowerCase().includes('mobile') ? 'Mobile' : 'Desktop');
        const deviceIcon = deviceRaw === 'Mobile' ? '📱' : deviceRaw === 'Tablet' ? '📲' : '💻';
        const browserRaw = scan.browser || String(scan.userAgent.split('/')[0]).slice(0, 20);
        const osRaw = scan.os || '';
        scanRowsHtml += `
          <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #eee; color: #333; font-weight: 600;">
              <span style="background: #667eea; color: white; padding: 3px 7px; border-radius: 4px; font-size: 12px;">${scan.scanNumber}</span>
            </td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #eee; color: #666; font-size: 13px; white-space: nowrap;">${timestamp}</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">${deviceIcon} ${deviceRaw}</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">${browserRaw}${osRaw ? '<br><span style="font-size:11px;color:#aaa;">' + osRaw + '</span>' : ''}</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">${[scan.city, scan.region, scan.country].filter((v) => String(v || '').trim()).join(', ') || '-'}</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">${String(scan.ipAddress).slice(0, 20)}</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #eee; color: #666; font-size: 12px; color: #aaa;">${scan.language || '-'}</td>
          </tr>
        `;
      });
    } else {
      scanRowsHtml = '<tr><td colspan="7" style="padding: 20px; text-align: center; color: #999;">No scans yet. QR code tracking will start when first scan is detected.</td></tr>';
    }

    return `
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QR Scan Report - Namaste Kalyan</title>
        <style>
          * { margin: 0; padding: 0; box-sizing: border-box; }
          body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; color: #333; }
          .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
          .header h1 { font-size: 32px; margin-bottom: 10px; }
          .header p { font-size: 14px; opacity: 0.9; }
          .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
          .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
          .metric { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #667eea; }
          .metric h3 { font-size: 12px; text-transform: uppercase; color: #999; margin-bottom: 10px; font-weight: 600; letter-spacing: 1px; }
          .metric .value { font-size: 28px; font-weight: bold; color: #667eea; }
          .metric .sublabel { font-size: 12px; color: #666; margin-top: 5px; }
          .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
          .card h2 { font-size: 18px; margin-bottom: 20px; color: #333; }
          table { width: 100%; border-collapse: collapse; }
          table thead tr { background: #f8f9fa; }
          table th { padding: 12px; text-align: left; font-weight: 600; color: #333; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
          .milestone-reached { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-bottom: 20px; }
          .milestone-pending { background: #fff3cd; color: #664d03; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 20px; }
          .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; margin-top: 30px; border-top: 1px solid #eee; }
          .download-btn { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
          .download-btn:hover { background: #764ba2; }
          @media (max-width: 768px) { .metrics { grid-template-columns: 1fr; } table { font-size: 12px; } }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>🍜 Namaste Kalyan - QR Scan Report</h1>
          <p>Real-time Menu QR Code Analytics</p>
        </div>

        <div class="container">
          <div class="metrics">
            <div class="metric">
              <h3>Total Scans</h3>
              <div class="value">${totalScans}</div>
              <div class="sublabel">All-time scans</div>
            </div>
            <div class="metric">
              <h3>Last Milestone Email</h3>
              <div class="value">${lastMilestone === 0 ? '—' : lastMilestone}</div>
              <div class="sublabel">Scans (every ${EMAIL_SCAN_INTERVAL})</div>
            </div>
            <div class="metric">
              <h3>Next Email Alert</h3>
              <div class="value">${nextMilestone}</div>
              <div class="sublabel">${scansUntilNext} scans to go</div>
            </div>
            <div class="metric">
              <h3>Generated At</h3>
              <div class="value" style="font-size: 14px;">${currentDate}</div>
              <div class="sublabel">Report timestamp</div>
            </div>
          </div>

          ${totalScans > 0 && totalScans % EMAIL_SCAN_INTERVAL === 0 ? 
            `<div class="milestone-reached">
              🎉 <strong>Milestone Reached!</strong> Your QR code has been scanned ${totalScans} times. Email notification sent to support@dcoresystem.com
            </div>` : 
            `<div class="milestone-pending">
              ⏳ <strong>Next Email Milestone:</strong> ${nextMilestone} scans (${scansUntilNext} more to go)
            </div>`
          }

          <div class="card">
            <h2>📊 Recent Scans (Last 50)</h2>
            <table>
              <thead>
                <tr>
                  <th>Scan #</th>
                  <th>Timestamp</th>
                  <th>Device</th>
                  <th>Browser / OS</th>
                  <th>Location</th>
                  <th>IP Address</th>
                  <th>Language</th>
                </tr>
              </thead>
              <tbody>
                ${scanRowsHtml}
              </tbody>
            </table>
          </div>

          <div class="card">
            <h2>📱 QR Code Configuration</h2>
            <table>
              <tbody>
                <tr>
                  <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>QR Code URL:</strong></td>
                  <td style="padding: 12px; border-bottom: 1px solid #eee; color: #667eea; word-break: break-all;">${QR_MENU_URL + QR_TRACKING_URL_SUFFIX}</td>
                </tr>
                <tr>
                  <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>Landing Page:</strong></td>
                  <td style="padding: 12px; border-bottom: 1px solid #eee; color: #667eea; word-break: break-all;">${QR_MENU_URL}</td>
                </tr>
                <tr>
                  <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>Email Notifications:</strong></td>
                  <td style="padding: 12px; border-bottom: 1px solid #eee;">Every ${EMAIL_SCAN_INTERVAL} scans to support@dcoresystem.com</td>
                </tr>
                <tr>
                  <td style="padding: 12px;"><strong>Data Storage:</strong></td>
                  <td style="padding: 12px;">Google Sheet "QR Scans" (automated)</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="footer">
          <p>Namaste Kalyan QR Code Tracking System | Powered by Dcore Systems</p>
          <p style="margin-top: 10px; font-size: 11px;">This report updates automatically. Refresh to see latest data.</p>
        </div>

        <script>
          // Auto-refresh every 30 seconds
          setTimeout(() => { location.reload(); }, 30000);
        </script>
      </body>
      </html>
    `;
  } catch (err) {
    return `
      <html><body style="font-family: Arial; padding: 20px;">
        <h2>Error Generating Report</h2>
        <p>Error: ${String(err)}</p>
        <p><a href="javascript:history.back()">Go Back</a></p>
      </body></html>
    `;
  }
}

function ensureSpreadsheetTimezone_(spreadsheet) {
  if (!spreadsheet) return;
  try {
    if (String(spreadsheet.getSpreadsheetTimeZone() || '') !== SHEET_TIMEZONE) {
      spreadsheet.setSpreadsheetTimeZone(SHEET_TIMEZONE);
    }
  } catch (err) {
    // Non-fatal: writes can still proceed even if timezone update is blocked.
  }
}

function getOrCreateEventsSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(EVENTS_SHEET_NAME);

  if (!sheet) {
    sheet = spreadsheet.insertSheet(EVENTS_SHEET_NAME);
    sheet.appendRow(EVENTS_HEADERS);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(EVENTS_HEADERS);
  } else {
    ensureEventsSheetStructure_(sheet);
  }

  return sheet;
}

function ensureEventsSheetStructure_(sheet) {
  if (!sheet) return;

  const lastColumn = Math.max(sheet.getLastColumn(), 1);
  const currentHeaders = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  const normalized = currentHeaders.map((value) => normalizeHeaderKey_(value));
  const hasNewFormat = normalized.indexOf('startdate') !== -1
    && normalized.indexOf('starttime') !== -1
    && normalized.indexOf('enddate') !== -1
    && normalized.indexOf('endtime') !== -1
    && normalized.indexOf('timedisplayformat') !== -1;

  if (hasNewFormat) {
    ensureEventsSheetHeaders_(sheet, EVENTS_HEADERS);
    return;
  }

  migrateEventsSheetStructure_(sheet);
}

function ensureEventsSheetHeaders_(sheet, expectedHeaders) {
  const current = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  for (let i = 0; i < expectedHeaders.length; i += 1) {
    if (String(current[i] || '').trim() !== expectedHeaders[i]) {
      sheet.getRange(1, i + 1).setValue(expectedHeaders[i]);
    }
  }
}

function getEventRecords_() {
  const sheet = getOrCreateEventsSheet_();
  const lastRow = sheet.getLastRow();
  const lastColumn = Math.max(sheet.getLastColumn(), EVENTS_HEADERS.length);

  if (lastRow <= 1) return [];

  // Limit the fetch to avoid performance issues: fetch max 100 rows at a time
  const maxRowsToFetch = 100;
  const rowsToProcess = Math.min(lastRow - 1, maxRowsToFetch);

  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  const rows = sheet.getRange(2, 1, rowsToProcess, lastColumn).getValues();
  const records = [];

  for (let i = 0; i < rows.length; i += 1) {
    const normalized = normalizeEventRecord_(headers, rows[i], i + 2);
    if (normalized) records.push(normalized);
  }

  return records;
}

function getActiveEvents_() {
  const now = new Date();
  const records = getEventRecords_();
  return records
    .filter((item) => {
      if (!item.isActive) return false;
      if (item.endAt && now > item.endAt) return false;
      return true;
    })
    .sort((a, b) => {
      const priorityDiff = (Number(b.priority) || 0) - (Number(a.priority) || 0);
      if (priorityDiff !== 0) return priorityDiff;
      const aTime = a.startAt ? a.startAt.getTime() : 0;
      const bTime = b.startAt ? b.startAt.getTime() : 0;
      return aTime - bTime;
    });
}

function normalizeEventRecord_(headers, row, rowNumber) {
  const byKey = {};
  for (let i = 0; i < headers.length; i += 1) {
    const key = normalizeHeaderKey_(headers[i]);
    if (!key) continue;
    byKey[key] = row[i];
  }

  const id = cleanText_(pickEventValue_(byKey, ['eventid', 'id', 'slug'])) || `event-${rowNumber}`;
  const title = cleanText_(pickEventValue_(byKey, ['title', 'eventtitle', 'name']));
  if (!title) return null;

  const startDateValue = pickEventValue_(byKey, ['startdate']);
  const startTimeValue = pickEventValue_(byKey, ['starttime']);
  const endDateValue = pickEventValue_(byKey, ['enddate']);
  const endTimeValue = pickEventValue_(byKey, ['endtime']);
  const startAt = parseEventDateParts_(startDateValue, startTimeValue)
    || parseEventDate_(pickEventValue_(byKey, ['startat', 'startdatetime', 'start']))
    || parseEventDate_(startDateValue);
  const endAt = parseEventDateParts_(endDateValue, endTimeValue)
    || parseEventDate_(pickEventValue_(byKey, ['endat', 'enddatetime', 'end']))
    || parseEventDate_(endDateValue);
  const eventType = cleanText_(pickEventValue_(byKey, ['eventtype', 'type'])) || 'free';
  const ticketPrice = toNumber_(pickEventValue_(byKey, ['ticketprice', 'price', 'eventcost']), 0);
  const currency = cleanText_(pickEventValue_(byKey, ['currency'])) || 'INR';
  const paymentEnabledRaw = pickEventValue_(byKey, ['paymentenabled', 'ispaid', 'paid']);
  const paymentEnabled = String(paymentEnabledRaw || '').trim()
    ? toBoolean_(paymentEnabledRaw, false)
    : (String(eventType).toLowerCase() === 'paid');
  const timeDisplayFormat = normalizeTimeDisplayFormat_(pickEventValue_(byKey, ['timedisplayformat', 'timeformat', 'displaytimeformat']));

  return {
    id: id,
    title: title,
    subtitle: cleanText_(pickEventValue_(byKey, ['subtitle', 'tagline'])),
    description: cleanText_(pickEventValue_(byKey, ['description', 'details', 'summary'])),
    imageUrl: cleanText_(pickEventValue_(byKey, ['imageurl', 'image', 'imagepath', 'coverimage'])),
    videoUrl: cleanText_(pickEventValue_(byKey, ['videourl', 'video', 'videopath'])),
    showVideo: toBoolean_(pickEventValue_(byKey, ['showvideo', 'isvideoenabled']), false),
    ctaText: cleanText_(pickEventValue_(byKey, ['ctatext', 'buttontext', 'interestbuttontext'])) || 'I\'m Interested',
    ctaUrl: cleanText_(pickEventValue_(byKey, ['ctaurl', 'landingurl', 'buttonurl', 'url'])),
    badgeText: cleanText_(pickEventValue_(byKey, ['badgetext', 'badge', 'label'])),
    startAt: startAt,
    endAt: endAt,
    startAtIso: startAt ? startAt.toISOString() : '',
    endAtIso: endAt ? endAt.toISOString() : '',
    startDate: startAt ? formatEventSheetDate_(startAt) : cleanText_(startDateValue),
    startTime: startAt ? formatEventSheetTime_(startAt) : normalizeEventSheetTimeText_(startTimeValue),
    endDate: endAt ? formatEventSheetDate_(endAt) : cleanText_(endDateValue),
    endTime: endAt ? formatEventSheetTime_(endAt) : normalizeEventSheetTimeText_(endTimeValue),
    timeDisplayFormat: timeDisplayFormat,
    isActive: toBoolean_(pickEventValue_(byKey, ['isactive', 'active', 'enabled']), true),
    priority: toNumber_(pickEventValue_(byKey, ['priority', 'rank', 'weight']), 0),
    popupEnabled: toBoolean_(pickEventValue_(byKey, ['popupenabled', 'showpopup', 'popup']), true),
    showOncePerSession: toBoolean_(pickEventValue_(byKey, ['showoncepersession', 'oncepersession']), false),
    popupDelayHours: toNumber_(pickEventValue_(byKey, ['popupdelayhours', 'delayhours']), 0),
    popupCooldownHours: toNumber_(pickEventValue_(byKey, ['popupcooldownhours', 'cooldownhours']), 0),
    eventType: eventType,
    ticketPrice: ticketPrice,
    currency: currency,
    maxTickets: toNumber_(pickEventValue_(byKey, ['maxtickets', 'maxqty']), 0),
    paymentEnabled: paymentEnabled,
    cancellationPolicyText: cleanText_(pickEventValue_(byKey, ['cancellationpolicytext', 'cancellationpolicy'])) || NO_REFUND_POLICY_TEXT,
    refundPolicy: cleanText_(pickEventValue_(byKey, ['refundpolicy'])) || 'No Refund'
  };
}

function pickEventValue_(map, keys) {
  for (let i = 0; i < keys.length; i += 1) {
    if (Object.prototype.hasOwnProperty.call(map, keys[i])) {
      return map[keys[i]];
    }
  }
  return '';
}

function normalizeHeaderKey_(value) {
  return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

function cleanText_(value) {
  return String(value == null ? '' : value).trim();
}

function toBoolean_(value, defaultValue) {
  const text = String(value == null ? '' : value).trim().toLowerCase();
  if (!text) return !!defaultValue;
  return ['1', 'true', 'yes', 'y', 'on'].indexOf(text) !== -1;
}

function toNumber_(value, defaultValue) {
  const numeric = Number(String(value == null ? '' : value).trim());
  return Number.isFinite(numeric) ? numeric : defaultValue;
}

function normalizeTimeDisplayFormat_(value) {
  const text = String(value || '').trim().toLowerCase();
  if (text === '24' || text === '24h' || text === '24hr' || text === '24hrs') return '24h';
  return DEFAULT_EVENT_TIME_DISPLAY_FORMAT;
}

function normalizeEventSheetTimeText_(value) {
  const parts = parseEventTimeParts_(value);
  if (!parts) return cleanText_(value);
  return `${String(parts.hour).padStart(2, '0')}.${String(parts.minute).padStart(2, '0')}`;
}

function parseEventQrScanText_(scanText) {
  const text = String(scanText || '').trim();
  if (!text) return null;

  const candidates = [];
  candidates.push(text);

  const questionIndex = text.indexOf('?');
  if (questionIndex >= 0 && questionIndex < (text.length - 1)) {
    candidates.push(text.substring(questionIndex + 1));
  }

  for (let i = 0; i < candidates.length; i += 1) {
    let params = null;
    const candidate = String(candidates[i] || '').trim();
    if (!candidate) continue;

    try {
      if (/^https?:\/\//i.test(candidate)) {
        const url = new URL(candidate);
        params = url.searchParams;
      } else {
        params = new URLSearchParams(candidate.replace(/^[?#]/, ''));
      }
    } catch (err) {
      params = null;
    }

    if (!params) continue;

    const transactionId = String(params.get('tx') || params.get('transactionId') || '').trim();
    const eventId = String(params.get('eventId') || '').trim();
    const paymentId = String(params.get('paymentId') || params.get('pid') || '').trim();
    const guestId = String(params.get('guestId') || params.get('gid') || '').trim();
    const signature = String(params.get('sig') || params.get('signature') || '').trim();

    if (transactionId && eventId && paymentId && signature) {
      return {
        transactionId: transactionId,
        eventId: eventId,
        paymentId: paymentId,
        guestId: guestId,
        signature: signature,
        rawScan: text
      };
    }
  }

  return null;
}

function parseEventTimeParts_(value) {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return {
      hour: value.getHours(),
      minute: value.getMinutes(),
      second: value.getSeconds()
    };
  }

  const text = String(value == null ? '' : value).trim().toUpperCase();
  if (!text) return null;

  const match = text.match(/^(\d{1,2})(?:[.:](\d{2}))?(?:[.:](\d{2}))?\s*(AM|PM)?$/);
  if (!match) return null;

  let hour = Number(match[1]);
  const minute = Number(match[2] || 0);
  const second = Number(match[3] || 0);
  const meridiem = String(match[4] || '').trim();

  if (meridiem) {
    if (hour < 1 || hour > 12) return null;
    if (meridiem === 'AM') {
      hour = hour === 12 ? 0 : hour;
    } else {
      hour = hour === 12 ? 12 : hour + 12;
    }
  }

  if (hour < 0 || hour > 23 || minute < 0 || minute > 59 || second < 0 || second > 59) return null;

  return { hour: hour, minute: minute, second: second };
}

function parseEventDateParts_(dateValue, timeValue) {
  const rawDate = String(dateValue == null ? '' : dateValue).trim();
  if (!rawDate && !timeValue) return null;
  if (rawDate && /\d{4}-\d{2}-\d{2}[ T]\d{2}[:.]\d{2}/.test(rawDate) && !String(timeValue || '').trim()) {
    return parseEventDate_(rawDate);
  }

  let year = 0;
  let month = 0;
  let day = 0;

  if (dateValue instanceof Date && !Number.isNaN(dateValue.getTime())) {
    const formattedDate = Utilities.formatDate(dateValue, SHEET_TIMEZONE, 'yyyy-MM-dd');
    const formattedMatch = formattedDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (formattedMatch) {
      year = Number(formattedMatch[1]);
      month = Number(formattedMatch[2]);
      day = Number(formattedMatch[3]);
    }
  } else {
    const iso = rawDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    const dmy = rawDate.match(/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/);
    if (iso) {
      year = Number(iso[1]);
      month = Number(iso[2]);
      day = Number(iso[3]);
    } else if (dmy) {
      day = Number(dmy[1]);
      month = Number(dmy[2]);
      year = Number(dmy[3]);
    } else {
      return parseEventDate_(rawDate);
    }
  }

  if (!isValidDateParts_(year, month, day)) return null;

  const timeParts = parseEventTimeParts_(timeValue) || { hour: 0, minute: 0, second: 0 };
  const utcMs = Date.UTC(year, month - 1, day, timeParts.hour, timeParts.minute, timeParts.second) - (330 * 60 * 1000);
  return new Date(utcMs);
}

function parseEventDate_(value) {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    const formatted = Utilities.formatDate(value, SHEET_TIMEZONE, 'yyyy-MM-dd HH:mm:ss');
    const match = formatted.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/);
    if (match) {
      const year = Number(match[1]);
      const month = Number(match[2]);
      const day = Number(match[3]);
      const hour = Number(match[4]);
      const minute = Number(match[5]);
      const second = Number(match[6]);
      const utcMs = Date.UTC(year, month - 1, day, hour, minute, second) - (330 * 60 * 1000);
      return new Date(utcMs);
    }
    return value;
  }

  const text = String(value == null ? '' : value).trim();
  if (!text) return null;

  const isoLike = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
  if (isoLike) {
    const year = Number(isoLike[1]);
    const month = Number(isoLike[2]);
    const day = Number(isoLike[3]);
    const hour = Number(isoLike[4] || 0);
    const minute = Number(isoLike[5] || 0);
    const second = Number(isoLike[6] || 0);
    const utcMs = Date.UTC(year, month - 1, day, hour, minute, second) - (330 * 60 * 1000);
    return new Date(utcMs);
  }

  const dmy = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);
  if (dmy) {
    const day = Number(dmy[1]);
    const month = Number(dmy[2]);
    const year = Number(dmy[3]);
    const hour = Number(dmy[4] || 0);
    const minute = Number(dmy[5] || 0);
    const second = Number(dmy[6] || 0);
    const utcMs = Date.UTC(year, month - 1, day, hour, minute, second) - (330 * 60 * 1000);
    return new Date(utcMs);
  }

  const parsed = new Date(text);
  if (!Number.isNaN(parsed.getTime())) return parsed;
  return null;
}

function formatEventSheetDate_(value) {
  const date = value instanceof Date ? value : parseEventDate_(value);
  if (!date || Number.isNaN(date.getTime())) return '';
  return Utilities.formatDate(date, SHEET_TIMEZONE, 'yyyy-MM-dd');
}

function formatEventSheetTime_(value) {
  const date = value instanceof Date ? value : parseEventDate_(value);
  if (!date || Number.isNaN(date.getTime())) return '';
  return Utilities.formatDate(date, SHEET_TIMEZONE, 'HH.mm');
}

function buildEventSheetRow_(event) {
  const startAt = event && event.startAt ? event.startAt : parseEventDateParts_(event && event.startDate, event && event.startTime);
  const endAt = event && event.endAt ? event.endAt : parseEventDateParts_(event && event.endDate, event && event.endTime);

  return [
    String(event && event.id || '').trim(),
    String(event && event.title || '').trim(),
    String(event && event.subtitle || '').trim(),
    String(event && event.description || '').trim(),
    String(event && event.imageUrl || '').trim(),
    String(event && event.videoUrl || '').trim(),
    event && event.showVideo ? 'Yes' : 'No',
    String(event && event.ctaText || "I'm Interested").trim(),
    String(event && event.ctaUrl || '').trim(),
    String(event && event.badgeText || '').trim(),
    startAt ? formatEventSheetDate_(startAt) : cleanText_(event && event.startDate),
    startAt ? formatEventSheetTime_(startAt) : normalizeEventSheetTimeText_(event && event.startTime),
    endAt ? formatEventSheetDate_(endAt) : cleanText_(event && event.endDate),
    endAt ? formatEventSheetTime_(endAt) : normalizeEventSheetTimeText_(event && event.endTime),
    normalizeTimeDisplayFormat_(event && event.timeDisplayFormat),
    event && event.isActive ? 'Yes' : 'No',
    Number(event && event.priority || 0),
    event && event.popupEnabled ? 'Yes' : 'No',
    event && event.showOncePerSession ? 'Yes' : 'No',
    Number(event && event.popupDelayHours || 0),
    Number(event && event.popupCooldownHours || 0),
    String(event && event.eventType || 'free').trim(),
    Number(event && event.ticketPrice || 0),
    String(event && event.currency || 'INR').trim(),
    Number(event && event.maxTickets || 0),
    event && event.paymentEnabled ? 'Yes' : 'No',
    String(event && event.cancellationPolicyText || NO_REFUND_POLICY_TEXT).trim(),
    String(event && event.refundPolicy || 'No Refund').trim()
  ];
}

function getAdminEventRecords_() {
  return getEventRecords_().sort((a, b) => {
    if (!!a.isActive !== !!b.isActive) {
      return a.isActive ? -1 : 1;
    }

    const priorityDiff = (Number(b.priority) || 0) - (Number(a.priority) || 0);
    if (priorityDiff !== 0) return priorityDiff;

    const aTime = a.startAt ? a.startAt.getTime() : 0;
    const bTime = b.startAt ? b.startAt.getTime() : 0;
    return bTime - aTime;
  });
}

function findEventRowInfoById_(eventId) {
  const targetId = String(eventId || '').trim();
  if (!targetId) return null;

  const sheet = getOrCreateEventsSheet_();
  const lastRow = sheet.getLastRow();
  const lastColumn = Math.max(sheet.getLastColumn(), EVENTS_HEADERS.length);
  if (lastRow <= 1) return null;

  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  const rows = sheet.getRange(2, 1, lastRow - 1, lastColumn).getValues();

  for (let i = 0; i < rows.length; i += 1) {
    const record = normalizeEventRecord_(headers, rows[i], i + 2);
    if (record && String(record.id) === targetId) {
      return {
        sheet: sheet,
        rowNumber: i + 2,
        headers: headers,
        row: rows[i],
        record: record
      };
    }
  }

  return null;
}

function requestValueByKeys_(data, keys, fallbackValue) {
  const source = data || {};
  for (let i = 0; i < keys.length; i += 1) {
    if (Object.prototype.hasOwnProperty.call(source, keys[i])) {
      return source[keys[i]];
    }
  }
  return fallbackValue;
}

function slugifyEventId_(value) {
  const base = String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return base || 'event';
}

function eventIdExists_(eventId) {
  return !!findEventRowInfoById_(eventId);
}

function generateAdminEventId_(title) {
  const base = slugifyEventId_(title);
  let candidate = base;
  let index = 2;
  while (eventIdExists_(candidate)) {
    candidate = `${base}-${index}`;
    index += 1;
  }
  return candidate;
}

function normalizeAdminEventType_(value) {
  return String(value || '').trim().toLowerCase() === 'paid' ? 'paid' : 'free';
}

function normalizeNonNegativeNumber_(value, defaultValue, roundToInteger) {
  const numeric = toNumber_(value, defaultValue);
  const normalized = Math.max(0, numeric);
  return roundToInteger ? Math.floor(normalized) : normalized;
}

function buildAdminEventPayload_(data, existingRecord, options) {
  const settings = options || {};
  const existing = existingRecord || {};
  const title = cleanText_(requestValueByKeys_(data, ['title'], existing.title || ''));
  if (!title) {
    throw new Error('Title is required.');
  }

  const eventIdInput = cleanText_(requestValueByKeys_(data, ['eventId', 'id'], existing.id || ''));
  const eventId = eventIdInput || generateAdminEventId_(title);
  if (!eventId) {
    throw new Error('Event ID is required.');
  }

  const startDate = cleanText_(requestValueByKeys_(data, ['startDate'], existing.startDate || ''));
  const startTime = cleanText_(requestValueByKeys_(data, ['startTime'], existing.startTime || ''));
  if (!startDate) {
    throw new Error('Start date is required.');
  }

  const startAt = parseEventDateParts_(startDate, startTime);
  if (!startAt || Number.isNaN(startAt.getTime())) {
    throw new Error('Start date/time is invalid.');
  }

  const endDate = cleanText_(requestValueByKeys_(data, ['endDate'], existing.endDate || ''));
  const endTime = cleanText_(requestValueByKeys_(data, ['endTime'], existing.endTime || ''));
  const hasEndValue = !!endDate || !!endTime;
  const endAt = hasEndValue ? parseEventDateParts_(endDate || startDate, endTime) : null;
  if (hasEndValue && (!endAt || Number.isNaN(endAt.getTime()))) {
    throw new Error('End date/time is invalid.');
  }
  if (endAt && endAt.getTime() < startAt.getTime()) {
    throw new Error('End date/time cannot be before start date/time.');
  }

  const eventType = normalizeAdminEventType_(requestValueByKeys_(data, ['eventType'], existing.eventType || 'free'));
  const explicitPaymentEnabled = requestValueByKeys_(data, ['paymentEnabled'], existing.paymentEnabled);
  const paymentEnabled = String(explicitPaymentEnabled == null ? '' : explicitPaymentEnabled).trim()
    ? toBoolean_(explicitPaymentEnabled, eventType === 'paid')
    : (eventType === 'paid');
  const ticketPrice = normalizeNonNegativeNumber_(requestValueByKeys_(data, ['ticketPrice'], existing.ticketPrice || 0), 0, false);
  const popupDelayHours = normalizeNonNegativeNumber_(requestValueByKeys_(data, ['popupDelayHours'], existing.popupDelayHours || 0), 0, false);
  const popupCooldownHours = normalizeNonNegativeNumber_(requestValueByKeys_(data, ['popupCooldownHours'], existing.popupCooldownHours || 0), 0, false);
  const priority = normalizeNonNegativeNumber_(requestValueByKeys_(data, ['priority'], existing.priority || 0), 0, false);
  const maxTickets = normalizeNonNegativeNumber_(requestValueByKeys_(data, ['maxTickets'], existing.maxTickets || 0), 0, true);

  const payload = {
    id: eventId,
    title: title,
    subtitle: cleanText_(requestValueByKeys_(data, ['subtitle'], existing.subtitle || '')),
    description: cleanText_(requestValueByKeys_(data, ['description'], existing.description || '')),
    imageUrl: cleanText_(requestValueByKeys_(data, ['imageUrl'], existing.imageUrl || '')),
    videoUrl: cleanText_(requestValueByKeys_(data, ['videoUrl'], existing.videoUrl || '')),
    showVideo: toBoolean_(requestValueByKeys_(data, ['showVideo'], existing.showVideo), false),
    ctaText: cleanText_(requestValueByKeys_(data, ['ctaText'], existing.ctaText || "I'm Interested")) || "I'm Interested",
    ctaUrl: cleanText_(requestValueByKeys_(data, ['ctaUrl'], existing.ctaUrl || '')),
    badgeText: cleanText_(requestValueByKeys_(data, ['badgeText'], existing.badgeText || '')),
    startAt: startAt,
    endAt: endAt,
    startDate: formatEventSheetDate_(startAt),
    startTime: formatEventSheetTime_(startAt),
    endDate: endAt ? formatEventSheetDate_(endAt) : '',
    endTime: endAt ? formatEventSheetTime_(endAt) : '',
    timeDisplayFormat: normalizeTimeDisplayFormat_(requestValueByKeys_(data, ['timeDisplayFormat'], existing.timeDisplayFormat || DEFAULT_EVENT_TIME_DISPLAY_FORMAT)),
    isActive: toBoolean_(requestValueByKeys_(data, ['isActive'], existing.isActive), true),
    priority: priority,
    popupEnabled: toBoolean_(requestValueByKeys_(data, ['popupEnabled'], existing.popupEnabled), true),
    showOncePerSession: toBoolean_(requestValueByKeys_(data, ['showOncePerSession'], existing.showOncePerSession), false),
    popupDelayHours: popupDelayHours,
    popupCooldownHours: popupCooldownHours,
    eventType: eventType,
    ticketPrice: paymentEnabled ? ticketPrice : 0,
    currency: cleanText_(requestValueByKeys_(data, ['currency'], existing.currency || 'INR')) || 'INR',
    maxTickets: maxTickets,
    paymentEnabled: paymentEnabled,
    cancellationPolicyText: cleanText_(requestValueByKeys_(data, ['cancellationPolicyText'], existing.cancellationPolicyText || NO_REFUND_POLICY_TEXT)) || NO_REFUND_POLICY_TEXT,
    refundPolicy: cleanText_(requestValueByKeys_(data, ['refundPolicy'], existing.refundPolicy || 'No Refund')) || 'No Refund'
  };

  if (settings.requireUniqueId && payload.id !== String(existing.id || '') && eventIdExists_(payload.id)) {
    throw new Error('Event ID already exists.');
  }

  return payload;
}

function handleAdminCreateEvent_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  const payload = buildAdminEventPayload_(data || {}, null, { requireUniqueId: true });
  const sheet = getOrCreateEventsSheet_();
  sheet.appendRow(buildEventSheetRow_(payload));

  const created = findEventRowInfoById_(payload.id);
  return {
    ok: true,
    action: 'admin_create_event',
    event: created ? created.record : payload,
    message: 'Event created successfully.'
  };
}

function handleAdminUpdateEvent_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  const eventId = cleanText_(requestValueByKeys_(data, ['eventId', 'id'], ''));
  if (!eventId) {
    return { ok: false, error: 'EVENT_ID_REQUIRED', message: 'Event ID is required.' };
  }

  const rowInfo = findEventRowInfoById_(eventId);
  if (!rowInfo) {
    return { ok: false, error: 'EVENT_NOT_FOUND', message: 'Event not found.' };
  }

  const payload = buildAdminEventPayload_(data || {}, rowInfo.record, { requireUniqueId: false });
  rowInfo.sheet.getRange(rowInfo.rowNumber, 1, 1, EVENTS_HEADERS.length).setValues([buildEventSheetRow_(payload)]);

  const updated = findEventRowInfoById_(payload.id);
  return {
    ok: true,
    action: 'admin_update_event',
    event: updated ? updated.record : payload,
    message: 'Event updated successfully.'
  };
}

function handleAdminToggleEvent_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  const eventId = cleanText_(requestValueByKeys_(data, ['eventId', 'id'], ''));
  if (!eventId) {
    return { ok: false, error: 'EVENT_ID_REQUIRED', message: 'Event ID is required.' };
  }

  const rowInfo = findEventRowInfoById_(eventId);
  if (!rowInfo) {
    return { ok: false, error: 'EVENT_NOT_FOUND', message: 'Event not found.' };
  }

  const requestedActive = requestValueByKeys_(data, ['isActive'], null);
  const nextActive = String(requestedActive == null ? '' : requestedActive).trim()
    ? toBoolean_(requestedActive, !rowInfo.record.isActive)
    : !rowInfo.record.isActive;

  const payload = buildAdminEventPayload_({ isActive: nextActive }, rowInfo.record, { requireUniqueId: false });
  rowInfo.sheet.getRange(rowInfo.rowNumber, 1, 1, EVENTS_HEADERS.length).setValues([buildEventSheetRow_(payload)]);

  const updated = findEventRowInfoById_(payload.id);
  return {
    ok: true,
    action: 'admin_toggle_event',
    event: updated ? updated.record : payload,
    message: nextActive ? 'Event activated successfully.' : 'Event deactivated successfully.'
  };
}

function normalizeMenuEditorHeaderKey_(value) {
  return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
}

function normalizeMenuEditorSheetName_(value) {
  const requested = String(value || '').trim();
  if (!requested) return '';
  const requestedNormalized = normalizeSheetName(requested);
  for (let i = 0; i < MENU_EDITOR_ALLOWED_SHEETS.length; i += 1) {
    const candidate = String(MENU_EDITOR_ALLOWED_SHEETS[i] || '').trim();
    if (!candidate) continue;
    if (normalizeSheetName(candidate) === requestedNormalized) {
      return candidate;
    }
  }
  return '';
}

function isMenuEditorPriceColumn_(sheetName, normalizedHeaderKey) {
  const key = normalizeMenuEditorHeaderKey_(normalizedHeaderKey);
  const normalizedSheet = normalizeSheetName(sheetName);

  if (normalizedSheet === normalizeSheetName('AWGNK MENU')) {
    return /^(veg|jain|chicken|mutton|basa|fish|surmai|pomfret|crab|egg|prawn|prawns|prawans|half|full|plain|butter|medium|large)$/i.test(key)
      || /\b(pcs|pc|piece)\b/i.test(key);
  }

  if (normalizedSheet === normalizeSheetName('BAR MENU NK')) {
    return /^(glass|pint|pitcher|bottle|per bottle)$/i.test(key)
      || /\b(ml|shot|peg|large|medium|small|full|half)\b/i.test(key)
      || /^\d+\s*ml$/i.test(key);
  }

  return false;
}

function isValidMenuEditorNumeric_(value) {
  const normalized = String(value == null ? '' : value).replace(/[₹,\s]/g, '').trim();
  if (!normalized) return true;
  return /^\d+(\.\d+)?$/.test(normalized);
}

function buildMenuEditorEditableMeta_(sheetName, headers) {
  const editableMeta = {};
  const editableMetaByHeader = {};
  const editableColumns = [];

  for (let i = 0; i < headers.length; i += 1) {
    const header = String(headers[i] || '').trim();
    const key = normalizeMenuEditorHeaderKey_(header);
    if (!key) continue;

    if (key === 'item name' || key === 'description' || key === 'category' || key === 'image url') {
      editableMeta[key] = 'text';
      editableMetaByHeader[header] = 'text';
      editableColumns.push(header);
      continue;
    }

    if (key === 'spice level') {
      editableMeta[key] = 'spice';
      editableMetaByHeader[header] = 'spice';
      editableColumns.push(header);
      continue;
    }

    if (key === 'chef special' || key === "chef's special") {
      editableMeta[key] = 'chef';
      editableMetaByHeader[header] = 'chef';
      editableColumns.push(header);
      continue;
    }

    if (key === normalizeMenuEditorHeaderKey_(MENU_EDITOR_VISIBILITY_HEADER)) {
      editableMeta[key] = 'visibility';
      editableMetaByHeader[header] = 'visibility';
      editableColumns.push(header);
      continue;
    }

    if (isMenuEditorPriceColumn_(sheetName, key)) {
      editableMeta[key] = 'price';
      editableMetaByHeader[header] = 'price';
      editableColumns.push(header);
    }
  }

  return {
    editableMeta: editableMeta,
    editableMetaByHeader: editableMetaByHeader,
    editableColumns: editableColumns
  };
}

function normalizeMenuEditorChefSpecialValue_(value) {
  return toBoolean_(value, false) ? 'Yes' : 'No';
}

function normalizeMenuEditorSpiceLevelValue_(value) {
  const text = String(value == null ? '' : value).trim();
  if (!text) return '';

  const key = text.toLowerCase();
  const map = {
    mild: 'Mild',
    low: 'Mild',
    medium: 'Medium',
    med: 'Medium',
    spicy: 'Spicy',
    hot: 'Hot',
    high: 'Hot'
  };

  return map[key] || text;
}

function buildMenuEditorHeaderIndexMap_(headers) {
  const map = {};
  for (let i = 0; i < headers.length; i += 1) {
    const key = normalizeMenuEditorHeaderKey_(headers[i]);
    if (!key || Object.prototype.hasOwnProperty.call(map, key)) continue;
    map[key] = i;
  }
  return map;
}

function ensureMenuEditorVisibilityColumn_(sheet, headers) {
  const safeHeaders = Array.isArray(headers) ? headers.slice() : [];
  const headerMap = buildMenuEditorHeaderIndexMap_(safeHeaders);
  const visibilityKey = normalizeMenuEditorHeaderKey_(MENU_EDITOR_VISIBILITY_HEADER);

  if (Object.prototype.hasOwnProperty.call(headerMap, visibilityKey)) {
    return {
      headers: safeHeaders,
      visibilityColumnIndex: Number(headerMap[visibilityKey]) + 1,
      created: false
    };
  }

  const nextCol = safeHeaders.length + 1;
  sheet.getRange(1, nextCol).setValue(MENU_EDITOR_VISIBILITY_HEADER);
  safeHeaders.push(MENU_EDITOR_VISIBILITY_HEADER);
  return {
    headers: safeHeaders,
    visibilityColumnIndex: nextCol,
    created: true
  };
}

function getMenuEditorSheetOrThrow_(requestedSheetName) {
  const normalized = normalizeMenuEditorSheetName_(requestedSheetName);
  if (!normalized) {
    throw new Error('Invalid sheet. Allowed: ' + MENU_EDITOR_ALLOWED_SHEETS.join(', '));
  }

  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  const sheet = findSheetByNormalizedName(spreadsheet, normalized);
  if (!sheet) {
    throw new Error('Sheet not found: ' + normalized);
  }

  return {
    spreadsheet: spreadsheet,
    sheet: sheet,
    sheetName: sheet.getName()
  };
}

function normalizeMenuEditorVisibilityValue_(visible) {
  return toBoolean_(visible, true) ? 'Yes' : 'No';
}

function resolveMenuEditorRequestedSheet_(data) {
  const payload = data && typeof data === 'object' ? data : {};

  const directSheet = String(payload.sheetName || payload.sheet || '').trim();
  if (directSheet) {
    return directSheet;
  }

  const type = String(payload.sheetType || payload.type || '').trim().toLowerCase();
  if (type === 'food') return 'AWGNK MENU';
  if (type === 'bar') return 'BAR MENU NK';

  return '';
}

function handleAdminMenuEditorLoad_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  try {
    const requestedSheet = resolveMenuEditorRequestedSheet_(data);
    const target = getMenuEditorSheetOrThrow_(requestedSheet);
    const sheet = target.sheet;
    const lastColumn = Math.max(sheet.getLastColumn(), 1);
    let headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0].map((value) => String(value || '').trim());

    const visibilityInfo = ensureMenuEditorVisibilityColumn_(sheet, headers);
    headers = visibilityInfo.headers;

    const editableInfo = buildMenuEditorEditableMeta_(target.sheetName, headers);
    const lastRow = sheet.getLastRow();
    const items = [];

    if (lastRow > 1) {
      const rows = sheet.getRange(2, 1, lastRow - 1, headers.length).getDisplayValues();
      for (let i = 0; i < rows.length; i += 1) {
        if (!rows[i] || !rows[i].some((cell) => String(cell || '').trim() !== '')) continue;
        const cells = {};
        for (let c = 0; c < headers.length; c += 1) {
          cells[headers[c]] = rows[i][c] || '';
        }
        items.push({
          rowNumber: i + 2,
          cells: cells
        });
      }
    }

    return {
      ok: true,
      action: 'admin_menu_editor_load',
      sheetName: target.sheetName,
      headers: headers,
      visibilityColumn: MENU_EDITOR_VISIBILITY_HEADER,
      editableColumns: editableInfo.editableColumns,
      editableMeta: editableInfo.editableMetaByHeader,
      items: items,
      rowCount: items.length,
      fetchedAt: new Date().toISOString()
    };
  } catch (err) {
    return { ok: false, error: 'MENU_EDITOR_LOAD_FAILED', message: String(err && err.message || err) };
  }
}

function handleAdminMenuEditorSaveChanges_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  const updates = Array.isArray(data && data.updates) ? data.updates : [];
  if (!updates.length) {
    return { ok: false, error: 'UPDATES_REQUIRED', message: 'No row updates provided.' };
  }

  try {
    const target = getMenuEditorSheetOrThrow_(data && (data.sheetName || data.sheet));
    const sheet = target.sheet;
    const lastColumn = Math.max(sheet.getLastColumn(), 1);
    let headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0].map((value) => String(value || '').trim());
    headers = ensureMenuEditorVisibilityColumn_(sheet, headers).headers;
    const headerMap = buildMenuEditorHeaderIndexMap_(headers);
    const editableInfo = buildMenuEditorEditableMeta_(target.sheetName, headers);
    const editableMeta = editableInfo.editableMeta;

    const lastRow = sheet.getLastRow();
    const errors = [];
    let updatedRows = 0;
    let updatedCells = 0;

    for (let i = 0; i < updates.length; i += 1) {
      const rowUpdate = updates[i] || {};
      const rowNumber = Number(rowUpdate.rowNumber || rowUpdate.row || 0);
      if (!Number.isFinite(rowNumber) || rowNumber < 2 || rowNumber > lastRow) {
        errors.push({ rowNumber: rowNumber || null, message: 'Invalid row number.' });
        continue;
      }

      const cells = rowUpdate.cells && typeof rowUpdate.cells === 'object' ? rowUpdate.cells : null;
      if (!cells) {
        errors.push({ rowNumber: rowNumber, message: 'Missing cells object.' });
        continue;
      }

      const current = sheet.getRange(rowNumber, 1, 1, headers.length).getDisplayValues()[0];
      const nextRow = current.slice();
      const rowErrors = [];
      let rowChanged = false;
      const keys = Object.keys(cells);

      for (let k = 0; k < keys.length; k += 1) {
        const requestedHeader = String(keys[k] || '').trim();
        const headerKey = normalizeMenuEditorHeaderKey_(requestedHeader);
        if (!headerKey || !Object.prototype.hasOwnProperty.call(headerMap, headerKey)) {
          rowErrors.push('Unknown column: ' + requestedHeader);
          continue;
        }

        const editType = editableMeta[headerKey] || '';
        if (!editType) {
          rowErrors.push('Column not editable: ' + requestedHeader);
          continue;
        }

        const colIndex = Number(headerMap[headerKey]);
        let nextValue = String(cells[keys[k]] == null ? '' : cells[keys[k]]).trim();

        if (editType === 'price' && !isValidMenuEditorNumeric_(nextValue)) {
          rowErrors.push('Invalid numeric value for ' + headers[colIndex]);
          continue;
        }

        if (editType === 'visibility') {
          nextValue = normalizeMenuEditorVisibilityValue_(nextValue);
        } else if (editType === 'chef') {
          nextValue = normalizeMenuEditorChefSpecialValue_(nextValue);
        } else if (editType === 'spice') {
          nextValue = normalizeMenuEditorSpiceLevelValue_(nextValue);
        }

        if (String(nextRow[colIndex] || '') !== nextValue) {
          nextRow[colIndex] = nextValue;
          rowChanged = true;
          updatedCells += 1;
        }
      }

      if (rowErrors.length) {
        errors.push({ rowNumber: rowNumber, message: rowErrors.join(' | ') });
        continue;
      }

      if (rowChanged) {
        sheet.getRange(rowNumber, 1, 1, headers.length).setValues([nextRow]);
        updatedRows += 1;
      }
    }

    return {
      ok: true,
      action: 'admin_menu_editor_save_changes',
      sheetName: target.sheetName,
      updatedRows: updatedRows,
      updatedCells: updatedCells,
      failedRows: errors.length,
      errors: errors,
      message: errors.length
        ? ('Saved with partial failures. Updated rows: ' + updatedRows + ', failed rows: ' + errors.length)
        : ('Saved successfully. Updated rows: ' + updatedRows)
    };
  } catch (err) {
    return { ok: false, error: 'MENU_EDITOR_SAVE_FAILED', message: String(err && err.message || err) };
  }
}

function handleAdminMenuEditorAddRow_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  try {
    const target = getMenuEditorSheetOrThrow_(data && (data.sheetName || data.sheet));
    const sheet = target.sheet;
    const lastColumn = Math.max(sheet.getLastColumn(), 1);
    let headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0].map((value) => String(value || '').trim());
    headers = ensureMenuEditorVisibilityColumn_(sheet, headers).headers;

    const headerMap = buildMenuEditorHeaderIndexMap_(headers);
    const editableInfo = buildMenuEditorEditableMeta_(target.sheetName, headers);
    const editableMeta = editableInfo.editableMeta;

    const row = new Array(headers.length).fill('');
    const itemNameKey = normalizeMenuEditorHeaderKey_('Item Name');
    const descriptionKey = normalizeMenuEditorHeaderKey_('Description');
    const categoryKey = normalizeMenuEditorHeaderKey_('Category');
    const chefSpecialKey = normalizeMenuEditorHeaderKey_('Chef Special');
    const chefSpecialAltKey = normalizeMenuEditorHeaderKey_("Chef's Special");
    const visibilityKey = normalizeMenuEditorHeaderKey_(MENU_EDITOR_VISIBILITY_HEADER);

    if (Object.prototype.hasOwnProperty.call(headerMap, itemNameKey)) {
      row[headerMap[itemNameKey]] = 'New Item';
    }
    if (Object.prototype.hasOwnProperty.call(headerMap, descriptionKey)) {
      row[headerMap[descriptionKey]] = '';
    }
    if (Object.prototype.hasOwnProperty.call(headerMap, categoryKey)) {
      row[headerMap[categoryKey]] = 'General';
    }
    if (Object.prototype.hasOwnProperty.call(headerMap, chefSpecialKey)) {
      row[headerMap[chefSpecialKey]] = 'No';
    } else if (Object.prototype.hasOwnProperty.call(headerMap, chefSpecialAltKey)) {
      row[headerMap[chefSpecialAltKey]] = 'No';
    }
    if (Object.prototype.hasOwnProperty.call(headerMap, visibilityKey)) {
      row[headerMap[visibilityKey]] = normalizeMenuEditorVisibilityValue_(true);
    }

    const provided = data && data.values && typeof data.values === 'object' ? data.values : {};
    const providedKeys = Object.keys(provided);
    for (let i = 0; i < providedKeys.length; i += 1) {
      const requestedHeader = String(providedKeys[i] || '').trim();
      const key = normalizeMenuEditorHeaderKey_(requestedHeader);
      if (!key || !Object.prototype.hasOwnProperty.call(headerMap, key)) continue;
      const editType = editableMeta[key] || '';
      if (!editType) continue;

      let nextValue = String(provided[providedKeys[i]] == null ? '' : provided[providedKeys[i]]).trim();
      if (editType === 'price' && !isValidMenuEditorNumeric_(nextValue)) {
        return { ok: false, error: 'INVALID_PRICE', message: 'Invalid numeric value for ' + requestedHeader };
      }
      if (editType === 'visibility') {
        nextValue = normalizeMenuEditorVisibilityValue_(nextValue);
      } else if (editType === 'chef') {
        nextValue = normalizeMenuEditorChefSpecialValue_(nextValue);
      } else if (editType === 'spice') {
        nextValue = normalizeMenuEditorSpiceLevelValue_(nextValue);
      }
      row[headerMap[key]] = nextValue;
    }

    sheet.appendRow(row);
    const rowNumber = sheet.getLastRow();

    const cells = {};
    for (let c = 0; c < headers.length; c += 1) {
      cells[headers[c]] = row[c] || '';
    }

    return {
      ok: true,
      action: 'admin_menu_editor_add_row',
      sheetName: target.sheetName,
      rowNumber: rowNumber,
      row: { rowNumber: rowNumber, cells: cells },
      message: 'Row added successfully.'
    };
  } catch (err) {
    return { ok: false, error: 'MENU_EDITOR_ADD_ROW_FAILED', message: String(err && err.message || err) };
  }
}

function handleAdminMenuEditorDeleteRows_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  try {
    const target = getMenuEditorSheetOrThrow_(data && (data.sheetName || data.sheet));
    const sheet = target.sheet;
    const lastRow = sheet.getLastRow();
    const inputRows = Array.isArray(data && data.rowNumbers) ? data.rowNumbers : [];

    if (!inputRows.length) {
      return { ok: false, error: 'ROWS_REQUIRED', message: 'No rows selected for deletion.' };
    }

    const unique = {};
    const rows = [];
    for (let i = 0; i < inputRows.length; i += 1) {
      const rowNumber = Number(inputRows[i]);
      if (!Number.isFinite(rowNumber) || rowNumber < 2 || rowNumber > lastRow) continue;
      if (unique[rowNumber]) continue;
      unique[rowNumber] = true;
      rows.push(rowNumber);
    }

    if (!rows.length) {
      return { ok: false, error: 'NO_VALID_ROWS', message: 'No valid rows available for deletion.' };
    }

    rows.sort((a, b) => b - a);
    for (let i = 0; i < rows.length; i += 1) {
      sheet.deleteRow(rows[i]);
    }

    return {
      ok: true,
      action: 'admin_menu_editor_delete_rows',
      sheetName: target.sheetName,
      deletedRows: rows.length,
      message: 'Rows deleted permanently: ' + rows.length
    };
  } catch (err) {
    return { ok: false, error: 'MENU_EDITOR_DELETE_FAILED', message: String(err && err.message || err) };
  }
}

function handleAdminMenuEditorSetVisibility_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  try {
    const target = getMenuEditorSheetOrThrow_(data && (data.sheetName || data.sheet));
    const sheet = target.sheet;
    const lastColumn = Math.max(sheet.getLastColumn(), 1);
    const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0].map((value) => String(value || '').trim());
    const visibilityInfo = ensureMenuEditorVisibilityColumn_(sheet, headers);

    const lastRow = sheet.getLastRow();
    const inputRows = Array.isArray(data && data.rowNumbers) ? data.rowNumbers : [];
    if (!inputRows.length) {
      return { ok: false, error: 'ROWS_REQUIRED', message: 'No rows selected for visibility update.' };
    }

    const unique = {};
    const rows = [];
    for (let i = 0; i < inputRows.length; i += 1) {
      const rowNumber = Number(inputRows[i]);
      if (!Number.isFinite(rowNumber) || rowNumber < 2 || rowNumber > lastRow) continue;
      if (unique[rowNumber]) continue;
      unique[rowNumber] = true;
      rows.push(rowNumber);
    }

    if (!rows.length) {
      return { ok: false, error: 'NO_VALID_ROWS', message: 'No valid rows available for visibility update.' };
    }

    const targetValue = normalizeMenuEditorVisibilityValue_(data && data.visible);
    for (let i = 0; i < rows.length; i += 1) {
      sheet.getRange(rows[i], visibilityInfo.visibilityColumnIndex).setValue(targetValue);
    }

    return {
      ok: true,
      action: 'admin_menu_editor_set_visibility',
      sheetName: target.sheetName,
      updatedRows: rows.length,
      visibility: targetValue,
      message: 'Visibility updated for rows: ' + rows.length
    };
  } catch (err) {
    return { ok: false, error: 'MENU_EDITOR_VISIBILITY_FAILED', message: String(err && err.message || err) };
  }
}

function migrateEventsSheetStructure_(sheet) {
  const lastRow = sheet.getLastRow();
  const lastColumn = Math.max(sheet.getLastColumn(), 1);
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  const normalizedHeaders = headers.map((value) => normalizeHeaderKey_(value));
  const previousFormat = normalizedHeaders.indexOf('startat') !== -1 || normalizedHeaders.indexOf('endat') !== -1 ? 'legacy' : 'unknown';
  const records = [];

  if (lastRow > 1) {
    const rows = sheet.getRange(2, 1, lastRow - 1, lastColumn).getValues();
    for (let i = 0; i < rows.length; i += 1) {
      if (!rows[i].some((cell) => String(cell == null ? '' : cell).trim() !== '')) continue;
      const record = normalizeEventRecord_(headers, rows[i], i + 2);
      if (record) records.push(record);
    }
  }

  const newRows = records.map((record) => buildEventSheetRow_(record));
  sheet.clearContents();
  sheet.getRange(1, 1, 1, EVENTS_HEADERS.length).setValues([EVENTS_HEADERS]);
  if (newRows.length) {
    sheet.getRange(2, 1, newRows.length, EVENTS_HEADERS.length).setValues(newRows);
  }

  return {
    migratedRows: newRows.length,
    previousFormat: previousFormat
  };
}

function migrateEventsSheetFormat_(forceRewrite) {
  const sheet = getOrCreateEventsSheet_();
  return forceRewrite ? migrateEventsSheetStructure_(sheet) : { migratedRows: Math.max(0, sheet.getLastRow() - 1), previousFormat: 'current' };
}

function resetEventsSheetData_(includePaidSample) {
  const sheet = getOrCreateEventsSheet_();
  const clearedRows = Math.max(0, sheet.getLastRow() - 1);
  sheet.clearContents();
  sheet.getRange(1, 1, 1, EVENTS_HEADERS.length).setValues([EVENTS_HEADERS]);

  const seeded = {
    djEvents: seedDjEventsApr2026_()
  };

  if (includePaidSample) {
    seeded.paidEvent = seedPaidEventSample_(499, 6);
  }

  return {
    clearedRows: clearedRows,
    seeded: seeded
  };
}

function seedSampleEventRow_(forceSeed) {
  const sheet = getOrCreateEventsSheet_();
  const dataRows = Math.max(0, sheet.getLastRow() - 1);
  if (dataRows > 0 && !forceSeed) {
    return { seeded: false, reason: 'EVENTS_ALREADY_HAS_DATA', row: 0, eventId: '' };
  }

  const now = new Date();
  const startAt = new Date(now.getTime() - (2 * 60 * 60 * 1000));
  const endAt = new Date(now.getTime() + (7 * 24 * 60 * 60 * 1000));
  const eventId = `sample-${Utilities.formatDate(now, SHEET_TIMEZONE, 'yyyyMMddHHmmss')}`;

  sheet.appendRow(buildEventSheetRow_({
    id: eventId,
    title: 'Chef\'s Tasting Weekend',
    subtitle: 'Limited seats, curated pairing menu',
    description: 'Join us for a curated tasting journey featuring seasonal chef specials and crafted beverages.',
    imageUrl: 'assets/Hotel Pics/WhatsApp Image 2026-03-20 at 5.24.50 PM.jpeg',
    videoUrl: '',
    showVideo: false,
    ctaText: 'I\'m Interested',
    ctaUrl: '',
    badgeText: 'Limited Offer',
    startAt: startAt,
    endAt: endAt,
    timeDisplayFormat: '12h',
    isActive: true,
    priority: 100,
    popupEnabled: true,
    showOncePerSession: true,
    popupDelayHours: 0,
    popupCooldownHours: 24,
    eventType: 'free',
    ticketPrice: 0,
    currency: 'INR',
    maxTickets: 0,
    paymentEnabled: false,
    cancellationPolicyText: NO_REFUND_POLICY_TEXT,
    refundPolicy: 'No Refund'
  }));

  return {
    seeded: true,
    reason: forceSeed ? 'SEEDED_WITH_FORCE' : 'SEEDED',
    row: sheet.getLastRow(),
    eventId: eventId
  };
}

function getOrCreateEventTransactionsSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(EVENT_TRANSACTIONS_SHEET_NAME);
  const headers = [
    'Transaction ID',
    'Event ID',
    'Event Title',
    'Customer Name',
    'Customer Email',
    'Customer Phone',
    'Qty',
    'Amount',
    'Currency',
    'Gateway',
    'Order ID',
    'Payment ID',
    'Status',
    'QR URL',
    'QR Payload',
    'CRM Sync Status',
    'CRM Sync Code',
    'CRM Sync Message',
    'Email Status',
    'Email Sent At',
    'Created At',
    'Paid At',
    'Cancel Requested At',
    'Cancelled At',
    'Refund Status',
    'Refund ID',
    'Check-In Status',
    'Checked-In At',
    'Verified By',
    'Attendee Details',
    'Guest Passes JSON',
    'Issued By',
    'Cancel Request By',
    'Cancel Request Reason',
    'Cancel Reviewed By',
    'Cancel Reviewed At',
    'Cancel Decision',
    'Cash Ledger Entry ID',
    'Checked-In Count'
  ];

  if (!sheet) {
    sheet = spreadsheet.insertSheet(EVENT_TRANSACTIONS_SHEET_NAME);
    sheet.appendRow(headers);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  } else {
    ensureLeadsSheetHeaders_(sheet, headers);
  }

  return sheet;
}

function getEventById_(eventId) {
  const id = String(eventId || '').trim();
  if (!id) return null;
  const records = getEventRecords_();
  return records.find((item) => String(item.id) === id) || null;
}

function getOrCreateAdminCashLedgerSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(ADMIN_CASH_LEDGER_SHEET_NAME);
  const headers = [
    'Ledger Entry ID',
    'Ledger Date',
    'Admin Username',
    'Admin Display Name',
    'Transaction ID',
    'Event ID',
    'Event Title',
    'Customer Name',
    'Customer Phone',
    'Qty',
    'Amount',
    'Currency',
    'Status',
    'Issued At',
    'Handover Requested At',
    'Handover Approved At',
    'Handover Approved By',
    'Handover Batch Key',
    'Notes'
  ];

  if (!sheet) {
    sheet = spreadsheet.insertSheet(ADMIN_CASH_LEDGER_SHEET_NAME);
    sheet.appendRow(headers);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  } else {
    ensureLeadsSheetHeaders_(sheet, headers);
  }

  return sheet;
}

function getOrCreateSuperadminCashLedgerSheet_() {
  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  ensureSpreadsheetTimezone_(spreadsheet);
  let sheet = spreadsheet.getSheetByName(SUPERADMIN_CASH_LEDGER_SHEET_NAME);
  const headers = [
    'Batch Key',
    'Ledger Date',
    'Admin Username',
    'Admin Display Name',
    'Total Transactions',
    'Total Amount',
    'Requested At',
    'Requested By',
    'Approved At',
    'Approved By',
    'Status',
    'Notes'
  ];

  if (!sheet) {
    sheet = spreadsheet.insertSheet(SUPERADMIN_CASH_LEDGER_SHEET_NAME);
    sheet.appendRow(headers);
  } else if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  } else {
    ensureLeadsSheetHeaders_(sheet, headers);
  }

  return sheet;
}

function getLedgerDateKey_(value) {
  const date = value instanceof Date ? value : new Date(value || '');
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
    return Utilities.formatDate(new Date(), SHEET_TIMEZONE, 'yyyy-MM-dd');
  }
  return Utilities.formatDate(date, SHEET_TIMEZONE, 'yyyy-MM-dd');
}

function buildCashBatchKey_(adminUsername, ledgerDate) {
  return `${getLedgerDateKey_(ledgerDate)}::${normalizeUsernameMobile_(adminUsername || '')}`;
}

function findSuperadminCashLedgerRow_(sheet, batchKey) {
  const target = String(batchKey || '').trim();
  const values = sheet.getDataRange().getValues();
  if (!target || values.length <= 1) return null;

  for (let i = values.length - 1; i >= 1; i -= 1) {
    if (String(values[i][0] || '').trim() === target) {
      return { row: i + 1, values: values[i] };
    }
  }

  return null;
}

function appendAdminCashLedgerRow_(entry) {
  const sheet = getOrCreateAdminCashLedgerSheet_();
  sheet.appendRow([
    entry.ledgerEntryId,
    entry.ledgerDate,
    entry.adminUsername,
    entry.adminDisplayName,
    entry.transactionId,
    entry.eventId,
    entry.eventTitle,
    entry.customerName,
    entry.customerPhone,
    entry.qty,
    entry.amount,
    entry.currency,
    entry.status,
    entry.issuedAt,
    entry.handoverRequestedAt || '',
    entry.handoverApprovedAt || '',
    entry.handoverApprovedBy || '',
    entry.handoverBatchKey || '',
    entry.notes || ''
  ]);
  return { sheet: sheet, row: sheet.getLastRow() };
}

function findTransactionByOrderId_(sheet, orderId) {
  const values = sheet.getDataRange().getValues();
  const target = String(orderId || '').trim();
  if (!target || values.length <= 1) return null;

  for (let i = 1; i < values.length; i += 1) {
    if (String(values[i][10] || '').trim() === target) {
      return { row: i + 1, values: values[i] };
    }
  }

  return null;
}

function findTransactionById_(sheet, transactionId) {
  const values = sheet.getDataRange().getValues();
  const target = String(transactionId || '').trim();
  if (!target || values.length <= 1) return null;

  for (let i = 1; i < values.length; i += 1) {
    if (String(values[i][0] || '').trim() === target) {
      return { row: i + 1, values: values[i] };
    }
  }

  return null;
}

function findLatestDuplicateEventRegistration_(sheet, eventId, customerEmail, customerPhone) {
  const values = sheet.getDataRange().getValues();
  if (values.length <= 1) return null;

  const targetEventId = String(eventId || '').trim();
  const targetEmail = String(customerEmail || '').trim().toLowerCase();
  const targetPhone = normalizePhoneDigits_(customerPhone || '');

  if (!targetEventId || (!targetEmail && !targetPhone)) return null;

  for (let i = values.length - 1; i >= 1; i -= 1) {
    const row = values[i] || [];
    const rowEventId = String(row[1] || '').trim();
    if (rowEventId !== targetEventId) continue;

    const rowEmail = String(row[4] || '').trim().toLowerCase();
    const rowPhone = normalizePhoneDigits_(row[5] || '');
    const emailMatch = !!targetEmail && rowEmail === targetEmail;
    const phoneMatch = !!targetPhone && rowPhone === targetPhone;
    if (!emailMatch && !phoneMatch) continue;

    const status = String(row[12] || '').trim();
    if (status === 'CancelledNoRefund') {
      continue;
    }

    return { row: i + 1, values: row };
  }

  return null;
}

function buildAlreadyRegisteredResponse_(tx, event, actionName) {
  const txValues = tx && tx.values ? tx.values : [];
  const status = String(txValues[12] || '').trim() || 'Unknown';
  const checkInStatus = String(txValues[26] || '').trim() || 'NotCheckedIn';
  const qrUrl = String(txValues[13] || '').trim();
  const verificationUrl = String(txValues[14] || '').trim();
  const canResendEmail = !!String(txValues[4] || '').trim() && !!(qrUrl || verificationUrl);

  return {
    ok: false,
    error: 'ALREADY_REGISTERED',
    message: 'You have already registered for this event with the details below.',
    allowRegisterAgain: true,
    canResendEmail: canResendEmail,
    action: actionName,
    alreadyRegistered: {
      transactionId: String(txValues[0] || ''),
      eventId: String(txValues[1] || ''),
      eventTitle: String((event && event.title) || txValues[2] || ''),
      customerName: String(txValues[3] || ''),
      customerEmail: String(txValues[4] || ''),
      customerPhone: String(txValues[5] || ''),
      qty: Number(txValues[6] || 1) || 1,
      amount: Number(txValues[7] || 0) || 0,
      currency: String(txValues[8] || 'INR').trim().toUpperCase(),
      paymentGateway: String(txValues[9] || ''),
      status: status,
      checkInStatus: checkInStatus,
      createdAt: txValues[20] || '',
      paidAt: txValues[21] || '',
      emailStatus: String(txValues[18] || ''),
      qrUrl: qrUrl,
      verificationUrl: verificationUrl
    }
  };
}

function toBasicAuthHeader_(user, pass) {
  const token = Utilities.base64Encode(`${user}:${pass}`);
  return `Basic ${token}`;
}

function getRazorpayCredentials_() {
  return {
    keyId: String(PropertiesService.getScriptProperties().getProperty('RAZORPAY_KEY_ID') || '').trim(),
    keySecret: String(PropertiesService.getScriptProperties().getProperty('RAZORPAY_KEY_SECRET') || '').trim()
  };
}

function getRazorpayWebhookConfig_() {
  return {
    secret: String(PropertiesService.getScriptProperties().getProperty('RAZORPAY_WEBHOOK_SECRET') || '').trim(),
    token: String(PropertiesService.getScriptProperties().getProperty('RAZORPAY_WEBHOOK_TOKEN') || '').trim()
  };
}

function buildHexHmacSha256_(payload, secret) {
  const digest = Utilities.computeHmacSha256Signature(String(payload || ''), String(secret || ''), Utilities.Charset.UTF_8);
  return digest.map((byte) => {
    const v = (byte < 0 ? byte + 256 : byte).toString(16);
    return v.length === 1 ? `0${v}` : v;
  }).join('');
}

function verifyRazorpayWebhookSignature_(rawBody, signature) {
  const config = getRazorpayWebhookConfig_();
  if (!config.secret) return false;
  return String(signature || '').trim() === buildHexHmacSha256_(rawBody, config.secret);
}

function fetchRazorpayApi_(path, method, payload) {
  const credentials = getRazorpayCredentials_();
  if (!credentials.keyId || !credentials.keySecret) {
    return { ok: false, error: 'RAZORPAY_CONFIG_MISSING', message: 'Razorpay keys are not configured in Script Properties.' };
  }

  try {
    const options = {
      method: method || 'get',
      headers: {
        Authorization: toBasicAuthHeader_(credentials.keyId, credentials.keySecret)
      },
      muteHttpExceptions: true
    };

    if (payload != null) {
      options.contentType = 'application/json';
      options.payload = JSON.stringify(payload);
    }

    const response = UrlFetchApp.fetch(`https://api.razorpay.com${path}`, options);
    const status = response.getResponseCode();
    const bodyText = String(response.getContentText() || '');
    const body = bodyText ? JSON.parse(bodyText) : {};

    if (status < 200 || status >= 300) {
      return {
        ok: false,
        error: 'RAZORPAY_API_FAILED',
        status: status,
        message: body.error && body.error.description ? body.error.description : bodyText,
        body: body
      };
    }

    return { ok: true, status: status, body: body, keyId: credentials.keyId };
  } catch (err) {
    return { ok: false, error: 'RAZORPAY_REQUEST_FAILED', message: String(err) };
  }
}

function fetchRazorpayPayment_(paymentId) {
  const safePaymentId = String(paymentId || '').trim();
  if (!safePaymentId) {
    return { ok: false, error: 'PAYMENT_ID_REQUIRED', message: 'paymentId is required.' };
  }
  return fetchRazorpayApi_(`/v1/payments/${encodeURIComponent(safePaymentId)}`, 'get');
}

function fetchRazorpayOrderPayments_(orderId) {
  const safeOrderId = String(orderId || '').trim();
  if (!safeOrderId) {
    return { ok: false, error: 'ORDER_ID_REQUIRED', message: 'orderId is required.' };
  }
  return fetchRazorpayApi_(`/v1/orders/${encodeURIComponent(safeOrderId)}/payments`, 'get');
}

function getLatestCapturedRazorpayOrderPayment_(orderId) {
  const response = fetchRazorpayOrderPayments_(orderId);
  if (!response.ok) return response;

  const items = response.body && Array.isArray(response.body.items) ? response.body.items : [];
  for (let i = 0; i < items.length; i += 1) {
    const item = items[i] || {};
    if (String(item.status || '').toLowerCase() === 'captured') {
      return { ok: true, payment: item };
    }
  }

  return { ok: false, error: 'PAYMENT_NOT_CAPTURED', message: 'No captured payment found for order.' };
}

function verifyRazorpayPaymentState_(orderId, paymentId, expectedAmountInPaise, expectedCurrency) {
  const paymentResponse = fetchRazorpayPayment_(paymentId);
  if (!paymentResponse.ok) return paymentResponse;

  const payment = paymentResponse.body || {};
  const paymentStatus = String(payment.status || '').trim().toLowerCase();
  if (paymentStatus !== 'captured') {
    return {
      ok: false,
      error: 'PAYMENT_NOT_CAPTURED',
      message: `Payment status is ${payment.status || 'unknown'}.`
    };
  }

  if (String(payment.order_id || '').trim() !== String(orderId || '').trim()) {
    return { ok: false, error: 'ORDER_PAYMENT_MISMATCH', message: 'Payment does not belong to this order.' };
  }

  if (Number(payment.amount || 0) !== Number(expectedAmountInPaise || 0)) {
    return { ok: false, error: 'AMOUNT_MISMATCH', message: 'Payment amount does not match the booking amount.' };
  }

  if (String(payment.currency || '').trim().toUpperCase() !== String(expectedCurrency || 'INR').trim().toUpperCase()) {
    return { ok: false, error: 'CURRENCY_MISMATCH', message: 'Payment currency does not match the booking currency.' };
  }

  return { ok: true, payment: payment };
}

function createRazorpayOrder_(amountInPaise, currency, receipt) {
  const credentials = getRazorpayCredentials_();
  const keyId = credentials.keyId;
  const keySecret = credentials.keySecret;

  if (!keyId || !keySecret) {
    return { ok: false, error: 'RAZORPAY_CONFIG_MISSING', message: 'Razorpay keys are not configured in Script Properties.' };
  }

  try {
    const response = UrlFetchApp.fetch('https://api.razorpay.com/v1/orders', {
      method: 'post',
      contentType: 'application/json',
      headers: {
        Authorization: toBasicAuthHeader_(keyId, keySecret)
      },
      payload: JSON.stringify({
        amount: amountInPaise,
        currency: currency,
        receipt: receipt,
        payment_capture: 1
      }),
      muteHttpExceptions: true
    });

    const status = response.getResponseCode();
    const bodyText = String(response.getContentText() || '');
    const body = bodyText ? JSON.parse(bodyText) : {};

    if (status < 200 || status >= 300 || !body.id) {
      return {
        ok: false,
        error: 'RAZORPAY_ORDER_CREATE_FAILED',
        status: status,
        message: body.error && body.error.description ? body.error.description : bodyText
      };
    }

    return { ok: true, keyId: keyId, order: body };
  } catch (err) {
    return { ok: false, error: 'RAZORPAY_REQUEST_FAILED', message: String(err) };
  }
}

function verifyRazorpaySignature_(orderId, paymentId, signature) {
  const keySecret = getRazorpayCredentials_().keySecret;
  if (!keySecret) return false;

  const payload = `${orderId}|${paymentId}`;
  const expected = buildHexHmacSha256_(payload, keySecret);

  return String(signature || '').trim() === expected;
}

function finalizeEventPaymentByOrderId_(orderId, paymentId, options) {
  const settings = options || {};
  const sheet = getOrCreateEventTransactionsSheet_();
  const tx = findTransactionByOrderId_(sheet, orderId);
  if (!tx) {
    return { ok: false, error: 'ORDER_NOT_FOUND', message: 'Transaction not found for order id.' };
  }

  const currentStatus = String(tx.values[12] || '').trim();
  if (currentStatus === 'Paid' || currentStatus === 'CheckedIn') {
    return {
      ok: true,
      action: settings.action || 'confirm_event_payment',
      idempotent: true,
      transactionId: String(tx.values[0] || ''),
      qrUrl: String(tx.values[13] || ''),
      verificationUrl: String(tx.values[14] || ''),
      status: currentStatus
    };
  }

  const amount = Number(tx.values[7] || 0);
  const currency = String(tx.values[8] || 'INR').trim().toUpperCase();
  const amountInPaise = Math.round(amount * 100);
  const verification = verifyRazorpayPaymentState_(orderId, paymentId, amountInPaise, currency);
  if (!verification.ok) {
    return verification;
  }

  const transactionId = String(tx.values[0] || '');
  const eventId = String(tx.values[1] || '');
  const event = getEventById_(eventId);
  const eventTitle = String((event && event.title) || tx.values[2] || '');
  const eventSubtitle = String((event && event.subtitle) || '');
  const customerName = String(tx.values[3] || '');
  const customerEmail = String(tx.values[4] || '');
  const customerPhone = String(tx.values[5] || '');
  const qty = Number(tx.values[6] || 1);
  const paidAt = verification.payment && verification.payment.created_at
    ? new Date(Number(verification.payment.created_at) * 1000)
    : new Date();
  const qr = buildEventQrPayload_(transactionId, eventId, paymentId);
  const attendeeNames = normalizeAttendeeNames_(String(tx.values[29] || '').trim());
  const guestPasses = [];

  updateTransactionColumns_(sheet, tx.row, {
    paymentId: paymentId,
    status: 'Paid',
    qrUrl: qr.qrUrl,
    qrPayload: qr.verificationUrl,
    paidAt: paidAt,
    refundStatus: 'NoRefundPolicy',
    checkInStatus: 'NotCheckedIn',
    guestPassesJson: serializeGuestPasses_(guestPasses)
  });

  const crmSync = pushPaidEventToCrm_({
    customerName: customerName,
    customerEmail: customerEmail,
    customerPhone: customerPhone,
    eventId: eventId,
    eventTitle: eventTitle,
    transactionId: transactionId,
    amount: amount,
    qty: qty,
    paymentId: paymentId,
    orderId: orderId
  });

  updateTransactionColumns_(sheet, tx.row, {
    crmSyncStatus: crmSync && crmSync.success ? 'Success' : (crmSync && crmSync.attempted ? 'Failed' : 'Skipped'),
    crmSyncCode: crmSync && crmSync.status ? crmSync.status : '',
    crmSyncMessage: crmSync && crmSync.message ? crmSync.message : ''
  });

  const emailResult = sendEventTicketEmail_(customerEmail, {
    customerName: customerName,
    eventTitle: eventTitle,
    eventSubtitle: eventSubtitle,
    eventImageUrl: event && event.imageUrl ? event.imageUrl : '',
    transactionId: transactionId,
    qty: qty,
    amount: amount,
    currency: currency,
    eventStartAt: event && event.startAt ? event.startAt : '',
    eventEndAt: event && event.endAt ? event.endAt : '',
    createdAt: tx.values[20] || new Date(),
    paidAt: paidAt,
    qrUrl: qr.qrUrl,
    verificationUrl: qr.verificationUrl,
    attendeeNames: attendeeNames,
    guestPasses: guestPasses
  });

  updateTransactionColumns_(sheet, tx.row, {
    emailStatus: emailResult.ok ? 'Sent' : `Failed: ${emailResult.error || ''}`,
    emailSentAt: emailResult.ok ? new Date() : ''
  });

  return {
    ok: true,
    action: settings.action || 'confirm_event_payment',
    transactionId: transactionId,
    status: 'Paid',
    qrUrl: qr.qrUrl,
    verificationUrl: qr.verificationUrl,
    emailSent: !!emailResult.ok,
    policy: NO_REFUND_POLICY_TEXT,
    paymentVerifiedBy: settings.paymentVerifiedBy || 'razorpay_api'
  };
}

function parseRazorpayWebhookRequest_(e) {
  const params = (e && e.parameter) ? e.parameter : {};
  const rawBody = (e && e.postData && e.postData.contents) ? String(e.postData.contents) : '';

  if (rawBody) {
    try {
      return { ok: true, rawBody: rawBody, payload: JSON.parse(rawBody), params: params };
    } catch (err) {
      return { ok: false, error: 'INVALID_WEBHOOK_JSON', message: 'Webhook body is not valid JSON.', params: params };
    }
  }

  if (params.payload) {
    try {
      const payloadText = String(params.payload);
      return { ok: true, rawBody: payloadText, payload: JSON.parse(payloadText), params: params };
    } catch (err2) {
      return { ok: false, error: 'INVALID_WEBHOOK_JSON', message: 'Webhook payload is not valid JSON.', params: params };
    }
  }

  return { ok: false, error: 'WEBHOOK_PAYLOAD_REQUIRED', message: 'Webhook payload is required.', params: params };
}

function extractRazorpayWebhookRefs_(payload) {
  const root = payload || {};
  const envelope = root.payload || {};
  const payment = envelope.payment && envelope.payment.entity ? envelope.payment.entity : null;
  const order = envelope.order && envelope.order.entity ? envelope.order.entity : null;

  return {
    eventName: String(root.event || '').trim(),
    orderId: String((payment && payment.order_id) || (order && order.id) || root.order_id || '').trim(),
    paymentId: String((payment && payment.id) || root.payment_id || '').trim(),
    payment: payment,
    order: order
  };
}

function handleRazorpayWebhook_(e) {
  const parsed = parseRazorpayWebhookRequest_(e);
  if (!parsed.ok) {
    return { ok: false, error: parsed.error, message: parsed.message };
  }

  const config = getRazorpayWebhookConfig_();
  const params = parsed.params || {};
  const suppliedToken = String(params.webhookToken || params.token || '').trim();
  if (config.token && suppliedToken !== config.token) {
    return { ok: false, error: 'INVALID_WEBHOOK_TOKEN', message: 'Webhook token mismatch.' };
  }

  const suppliedSignature = String(params.signature || params.razorpay_signature || '').trim();
  const signatureVerified = suppliedSignature && config.secret
    ? verifyRazorpayWebhookSignature_(parsed.rawBody, suppliedSignature)
    : false;

  if (suppliedSignature && config.secret && !signatureVerified) {
    return { ok: false, error: 'INVALID_WEBHOOK_SIGNATURE', message: 'Webhook signature mismatch.' };
  }

  const refs = extractRazorpayWebhookRefs_(parsed.payload);
  if (!refs.orderId) {
    return { ok: true, action: 'razorpay_webhook', ignored: true, reason: 'ORDER_ID_NOT_FOUND', event: refs.eventName || '' };
  }

  let paymentId = refs.paymentId;
  if (!paymentId) {
    const latestPayment = getLatestCapturedRazorpayOrderPayment_(refs.orderId);
    if (!latestPayment.ok) {
      return latestPayment;
    }
    paymentId = String(latestPayment.payment && latestPayment.payment.id || '').trim();
  }

  if (!paymentId) {
    return { ok: false, error: 'PAYMENT_ID_REQUIRED', message: 'Unable to determine payment id from webhook payload.' };
  }

  const supportedEvent = refs.eventName === 'payment.captured' || refs.eventName === 'order.paid' || refs.eventName === 'payment.authorized';
  if (!supportedEvent) {
    return {
      ok: true,
      action: 'razorpay_webhook',
      ignored: true,
      event: refs.eventName || '',
      reason: 'EVENT_NOT_ACTIONED'
    };
  }

  const finalized = finalizeEventPaymentByOrderId_(refs.orderId, paymentId, {
    action: 'razorpay_webhook',
    paymentVerifiedBy: signatureVerified ? 'webhook_signature+razorpay_api' : 'webhook_token+razorpay_api'
  });

  if (!finalized.ok) {
    return finalized;
  }

  finalized.event = refs.eventName || '';
  finalized.signatureVerified = !!signatureVerified;
  return finalized;
}

function buildEventQrPayload_(transactionId, eventId, paymentId) {
  return buildGuestEventQrPayload_(transactionId, eventId, paymentId, '');
}

function buildGuestEventQrPayload_(transactionId, eventId, paymentId, guestId) {
  const signingSecret = String(PropertiesService.getScriptProperties().getProperty('EVENT_QR_SIGNING_SECRET') || ADMIN_PANEL_PASSCODE).trim();
  const safeGuestId = String(guestId || '').trim();
  const raw = `${transactionId}|${eventId}|${paymentId}|${safeGuestId}`;
  const digest = Utilities.computeHmacSha256Signature(raw, signingSecret);
  const signature = digest.map((byte) => {
    const v = (byte < 0 ? byte + 256 : byte).toString(16);
    return v.length === 1 ? `0${v}` : v;
  }).join('');

  const payload = {
    tx: transactionId,
    eventId: eventId,
    paymentId: paymentId,
    sig: signature
  };

  if (safeGuestId) {
    payload.guestId = safeGuestId;
  }

  const query = Object.keys(payload).map((key) => `${encodeURIComponent(key)}=${encodeURIComponent(payload[key])}`).join('&');
  const verificationUrl = `https://namastekalyan.asianwokandgrill.in/event-verification.html?${query}`;
  const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=${encodeURIComponent(verificationUrl)}`;

  return { qrUrl: qrUrl, verificationUrl: verificationUrl, signature: signature };
}

function normalizeGuestPasses_(value) {
  if (Array.isArray(value)) {
    return value
      .map((item) => item && typeof item === 'object' ? item : null)
      .filter((item) => item && String(item.guestId || '').trim());
  }

  const text = String(value || '').trim();
  if (!text) return [];

  try {
    return normalizeGuestPasses_(JSON.parse(text));
  } catch (err) {
    return [];
  }
}

function buildGuestPasses_(transactionId, eventId, paymentId, attendeeNames, isFreeRegistration) {
  return normalizeAttendeeNames_(attendeeNames).map((guestName, index) => {
    const guestId = `guest-${index + 1}`;
    const qr = buildGuestEventQrPayload_(transactionId, eventId, paymentId, guestId);

    return {
      guestId: guestId,
      guestLabel: `Guest Pass ${index + 1}`,
      guestName: String(guestName || `Guest ${index + 1}`).trim(),
      qrUrl: qr.qrUrl,
      verificationUrl: qr.verificationUrl,
      signature: qr.signature,
      status: isFreeRegistration ? 'RegisteredFree' : 'Paid',
      checkInStatus: 'NotCheckedIn',
      checkedInAt: '',
      verifiedBy: ''
    };
  });
}

function serializeGuestPasses_(guestPasses) {
  return JSON.stringify(normalizeGuestPasses_(guestPasses));
}

function getGuestPassesFromTransactionRow_(rowValues) {
  const stored = normalizeGuestPasses_(rowValues && rowValues[30]);
  if (stored.length) return stored;
  return [];
}

function buildGuestPassEmailBlocks_(guestPasses) {
  const passes = normalizeGuestPasses_(guestPasses);
  if (!passes.length) return '';

  return passes.map((guestPass) => `
    <div style="margin:0 0 18px 0;padding:14px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
        <div>
          <p style="margin:0;font-size:12px;letter-spacing:0.5px;text-transform:uppercase;color:#64748b;"><strong>${escapeHtml_(guestPass.guestLabel || 'Guest Pass')}</strong></p>
          <p style="margin:4px 0 0 0;font-size:16px;color:#111827;"><strong>${escapeHtml_(guestPass.guestName || 'Guest')}</strong></p>
        </div>
        <p style="margin:0;font-size:12px;color:#475569;">Status: <strong>${escapeHtml_(guestPass.checkInStatus || 'NotCheckedIn')}</strong></p>
      </div>
      <div style="text-align:center;padding:12px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;">
        <p style="margin:0 0 8px 0;font-size:12px;color:#475569;">Present this pass at the entry desk</p>
        <img src="${escapeHtml_(guestPass.qrUrl || '')}" alt="${escapeHtml_(guestPass.guestLabel || 'Guest Pass')} QR" style="width:220px;height:220px;border-radius:10px;border:1px solid #d1d5db;background:#ffffff;" />
        <p style="margin:10px 0 0 0;font-size:11px;word-break:break-all;"><a href="${escapeHtml_(guestPass.verificationUrl || '')}" style="color:#1d4ed8;text-decoration:none;">${escapeHtml_(guestPass.verificationUrl || '')}</a></p>
      </div>
    </div>
  `).join('');
}

function sendEventTicketEmail_(email, payload) {
  const to = String(email || '').trim();
  if (!to) {
    return { ok: false, error: 'EMAIL_REQUIRED', message: 'Customer email is required for sending ticket.' };
  }

  const isFreeRegistration = !!payload.isFreeRegistration;
  const policyText = isFreeRegistration ? 'Free entry registration confirmed.' : NO_REFUND_POLICY_TEXT;
  const subject = isFreeRegistration
    ? `Your Free Event Pass - ${payload.eventTitle}`
    : `Your Event Pass - ${payload.eventTitle}`;
  const html = buildEventTicketEmailHtml_(payload);
  const attachments = [];
  const guestPasses = normalizeGuestPasses_(payload.guestPasses || []);

  if (guestPasses.length) {
    guestPasses.forEach((guestPass, index) => {
      try {
        const qrBlob = UrlFetchApp.fetch(String(guestPass.qrUrl || ''), {
          method: 'get',
          muteHttpExceptions: true
        }).getBlob();
        qrBlob.setName(`Event-QR-${String(payload.transactionId || 'ticket')}-Pass-${index + 1}.png`);
        attachments.push(qrBlob);
      } catch (err) {
        // Attachment failure should not block ticket email delivery.
      }
    });
  } else {
    try {
      const qrBlob = UrlFetchApp.fetch(String(payload.qrUrl || ''), {
        method: 'get',
        muteHttpExceptions: true
      }).getBlob();
      qrBlob.setName(`Event-QR-${String(payload.transactionId || 'ticket')}.png`);
      attachments.push(qrBlob);
    } catch (err) {
      // Attachment failure should not block ticket email delivery.
    }
  }

  const textBody = [
    `Hello ${String(payload.customerName || 'Guest')},`,
    isFreeRegistration
      ? `Your free registration is confirmed for ${String(payload.eventTitle || 'the event')}.`
      : `Your pass is confirmed for ${String(payload.eventTitle || 'the event')}.`,
    `Transaction ID: ${String(payload.transactionId || '')}`,
    guestPasses.length ? `Guest passes attached: ${guestPasses.length}` : '',
    `Venue: ${EVENT_VENUE_ADDRESS}`,
    `Booking Phone: ${EVENT_BOOKING_PHONE}`,
    `Policy: ${policyText}`,
    `Verification Link: ${String(payload.verificationUrl || '')}`
  ].filter((line) => line).join('\n');

  try {
    MailApp.sendEmail(to, subject, textBody, {
      htmlBody: html,
      name: EMAIL_FROM_NAME,
      cc: EVENT_TICKET_CONFIRMATION_CC_EMAIL,
      attachments: attachments
    });
    return { ok: true };
  } catch (err) {
    return { ok: false, error: 'EMAIL_SEND_FAILED', message: String(err) };
  }
}

function buildEventTicketEmailHtml_(payload) {
  const customerName = escapeHtml_(String(payload.customerName || 'Guest'));
  const eventTitle = escapeHtml_(String(payload.eventTitle || 'Special Event'));
  const eventSubtitle = escapeHtml_(String(payload.eventSubtitle || ''));
  const transactionId = escapeHtml_(String(payload.transactionId || '-'));
  const qty = Number(payload.qty || 1);
  const amount = Number(payload.amount || 0);
  const currency = escapeHtml_(String(payload.currency || 'INR'));
  const isFreeRegistration = !!payload.isFreeRegistration;
  const eventStartAt = formatEventDateForEmail_(payload.eventStartAt);
  const eventEndAt = formatEventDateForEmail_(payload.eventEndAt);
  const registeredAt = formatEventDateForEmail_(payload.createdAt);
  const paidAt = formatEventDateForEmail_(payload.paidAt);
  const verificationUrl = String(payload.verificationUrl || '');
  const qrUrl = String(payload.qrUrl || '');
  const eventImageUrl = String(payload.eventImageUrl || '').trim();
  const logoUrl = 'https://namastekalyan.asianwokandgrill.in/assets/Logo/Namaste%20Kalyan%20by%20AWG%20-02.png';
  const guestPasses = normalizeGuestPasses_(payload.guestPasses || []);
  const policyText = isFreeRegistration ? 'Free entry registration confirmed.' : NO_REFUND_POLICY_TEXT;
  const amountLabel = isFreeRegistration ? 'Entry Type' : 'Amount Paid';
  const amountValue = isFreeRegistration ? 'Free Entry' : `${currency} ${amount.toFixed(2)}`;
  const idLabel = isFreeRegistration ? 'Registration ID' : 'Transaction ID';
  const confirmationLabel = isFreeRegistration ? 'Registration Confirmed At' : 'Payment Confirmed At';
  const summaryLine = guestPasses.length
    ? `${guestPasses.length} individual guest pass${guestPasses.length > 1 ? 'es have' : ' has'} been generated for this booking.`
    : 'Your QR event pass is ready.';
  const attendeeLines = normalizeAttendeeNames_(payload.attendeeNames || [])
    .map((name) => `<li style="margin:0 0 4px 0;">${escapeHtml_(name)}</li>`)
    .join('');
  const guestPassBlocks = buildGuestPassEmailBlocks_(guestPasses);
  const eventHeroBlock = eventImageUrl
    ? `<div style="margin:0 0 18px 0;overflow:hidden;border-radius:14px;border:1px solid #402726;background:#120d0f;">
        <img src="${escapeHtml_(eventImageUrl)}" alt="${eventTitle}" style="display:block;width:100%;max-height:260px;object-fit:cover;" />
      </div>`
    : '';

  return `
    <html>
      <body style="margin:0;padding:0;background:#080506;font-family:Segoe UI, Arial, sans-serif;color:#f7f2eb;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080506;padding:20px 10px;">
          <tr>
            <td align="center">
              <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#130d10;border-radius:16px;overflow:hidden;border:1px solid #3b2527;">
                <tr>
                  <td style="padding:24px;background:linear-gradient(135deg,#330003,#130d10 70%,#1b0e12);color:#f7f2eb;border-bottom:1px solid #5c3f20;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0;">
                      <tr>
                        <td style="vertical-align:middle;padding:0 12px 0 0;">
                          <img src="${logoUrl}" alt="Namaste Kalyan" style="display:block;height:56px;width:auto;max-width:230px;" />
                        </td>
                        <td style="vertical-align:middle;text-align:right;">
                          <p style="margin:0;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:#f2c48a;">${isFreeRegistration ? 'Free Registration Confirmed' : 'Booking Confirmed'}</p>
                          <h1 style="margin:6px 0 0 0;font-size:22px;line-height:1.2;color:#f7f2eb;">Event Ticket</h1>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:24px;">
                    ${eventHeroBlock}
                    <p style="margin:0 0 12px 0;font-size:16px;color:#f7f2eb;">Hello <strong>${customerName}</strong>,</p>
                    <p style="margin:0 0 18px 0;font-size:15px;line-height:1.6;color:#eadfd3;">${isFreeRegistration ? `Thanks for registering with us. Your free entry pass is confirmed for <strong style="color:#f2c48a;">${eventTitle}</strong>.` : `Thanks for booking with us. Your pass is confirmed for <strong style="color:#f2c48a;">${eventTitle}</strong>.`}</p>
                    <p style="margin:0 0 18px 0;font-size:14px;color:#d2c4b8;">${summaryLine}</p>
                    ${eventSubtitle ? `<p style="margin:0 0 16px 0;font-size:13px;color:#d2c4b8;">${eventSubtitle}</p>` : ''}
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #3b2527;border-radius:10px;overflow:hidden;margin-bottom:18px;background:#1b0e12;">
                      <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">${idLabel}</td><td style="padding:12px 14px;font-size:14px;font-weight:600;color:#f7f2eb;">${transactionId}</td></tr>
                      <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Registered At</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">${registeredAt}</td></tr>
                      <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">${confirmationLabel}</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">${paidAt}</td></tr>
                      <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Event Starts</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">${eventStartAt}</td></tr>
                      <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Event Ends</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">${eventEndAt}</td></tr>
                      <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">Tickets</td><td style="padding:12px 14px;font-size:14px;color:#f7f2eb;">${qty}</td></tr>
                      <tr><td style="padding:12px 14px;background:#2a1519;font-size:13px;color:#d2c4b8;">${amountLabel}</td><td style="padding:12px 14px;font-size:14px;color:#f2c48a;">${amountValue}</td></tr>
                    </table>
                    ${attendeeLines ? `<div style="margin:0 0 16px 0;padding:10px 12px;background:#261116;border:1px solid #5c3f20;border-radius:10px;"><p style="margin:0 0 8px 0;font-size:13px;color:#f2c48a;"><strong>Attendees</strong></p><ul style="margin:0;padding-left:18px;font-size:13px;color:#eadfd3;">${attendeeLines}</ul></div>` : ''}
                    <p style="margin:0 0 8px 0;font-size:14px;color:#eadfd3;"><strong>Venue:</strong> ${escapeHtml_(EVENT_VENUE_ADDRESS)}</p>
                    <p style="margin:0 0 18px 0;font-size:14px;color:#eadfd3;"><strong>Support:</strong> ${escapeHtml_(EVENT_BOOKING_PHONE)} | <strong>Policy:</strong> ${escapeHtml_(policyText)}</p>
                    ${guestPassBlocks || `<div style="text-align:center;padding:14px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;">
                      <p style="margin:0 0 10px 0;font-size:13px;color:#475569;">Show this QR code at entry</p>
                      <img src="${escapeHtml_(qrUrl)}" alt="Event QR" style="width:240px;height:240px;border-radius:10px;border:1px solid #d1d5db;background:#ffffff;" />
                      <p style="margin:10px 0 0 0;font-size:12px;word-break:break-all;"><a href="${escapeHtml_(verificationUrl)}" style="color:#1d4ed8;text-decoration:none;">${escapeHtml_(verificationUrl)}</a></p>
                    </div>`}
                  </td>
                </tr>
                <tr>
                  <td style="padding:16px 24px;background:#1b0e12;border-top:1px solid #3b2527;font-size:12px;color:#d2c4b8;">
                    Please keep this email for your records. Share this confirmation at the entry desk if needed.
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </body>
    </html>
  `;
}

function formatEventDateForEmail_(value) {
  const date = value instanceof Date ? value : new Date(value || '');
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '-';
  return Utilities.formatDate(date, SHEET_TIMEZONE, 'dd MMM yyyy, hh:mm a');
}

function escapeHtml_(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function pushPaidEventToCrm_(payload) {
  const token = getCrmApiToken_();
  if (!token) {
    return { attempted: false, success: false, status: '', message: 'CRM_API_TOKEN missing in Script Properties', attempts: [] };
  }

  const crmPayload = {
    api_token: token,
    contact_name: payload.customerName,
    contact_email: payload.customerEmail,
    contact_phone: toPlusInternationalPhone_(payload.customerPhone),
    source: 'paid-event',
    event_id: payload.eventId,
    event_title: payload.eventTitle,
    transaction_id: payload.transactionId,
    amount_paid: payload.amount,
    quantity: payload.qty,
    payment_id: payload.paymentId,
    order_id: payload.orderId
  };

  return pushLeadToCrm_(crmPayload);
}

function handleRegisterFreeEvent_(data) {
  const eventId = String(data.eventId || '').trim();
  const customerName = String(data.customerName || data.name || '').trim();
  const customerEmail = String(data.customerEmail || data.email || '').trim();
  const customerPhone = normalizePhoneDigits_(data.customerPhone || data.phone || '');
  const qty = Math.max(1, Math.floor(Number(data.qty || 1)));
  const allowDuplicate = data.allowDuplicate === true || String(data.allowDuplicate || '').trim().toLowerCase() === 'true';
  let attendeeNames = normalizeAttendeeNames_(data.attendeeNames || data.attendees || '');

  if (!eventId || !customerName || !customerEmail || customerPhone.length < 8) {
    return { ok: false, error: 'INVALID_INPUT', message: 'eventId, name, email and valid phone are required.' };
  }

  if (qty === 1 && attendeeNames.length === 0) {
    attendeeNames = [customerName];
  }

  if (attendeeNames.length !== qty) {
    return {
      ok: false,
      error: 'ATTENDEE_COUNT_MISMATCH',
      message: `Please provide exactly ${qty} attendee name(s).`
    };
  }

  const event = getActiveEvents_().find((item) => String(item.id) === eventId);
  if (!event) {
    return { ok: false, error: 'EVENT_NOT_ACTIVE', message: 'Event is not active or not found.' };
  }

  if (event.paymentEnabled || String(event.eventType || '').toLowerCase() === 'paid') {
    return { ok: false, error: 'EVENT_REQUIRES_PAYMENT', message: 'This event requires payment. Please use the paid booking flow.' };
  }

  const sheet = getOrCreateEventTransactionsSheet_();
  const duplicateTx = findLatestDuplicateEventRegistration_(sheet, event.id, customerEmail, customerPhone);
  if (duplicateTx && !allowDuplicate) {
    return buildAlreadyRegisteredResponse_(duplicateTx, event, 'register_free_event');
  }

  const maxTickets = Number(event.maxTickets || 0);
  if (maxTickets > 0 && qty > maxTickets) {
    return { ok: false, error: 'QTY_EXCEEDS_LIMIT', message: `Maximum ${maxTickets} guest pass(es) allowed per registration.` };
  }

  const transactionId = `FREE-${Utilities.getUuid()}`;
  const freeReferenceId = `free_ref_${Utilities.getUuid().replace(/-/g, '').slice(0, 12)}`;
  const qr = buildEventQrPayload_(transactionId, event.id, freeReferenceId);
  const guestPasses = [];
  const createdAt = new Date();
  const attendeeDetails = attendeeNames.join(', ');

  sheet.appendRow([
    transactionId,
    event.id,
    event.title,
    customerName,
    customerEmail,
    customerPhone,
    qty,
    0,
    String(event.currency || 'INR').toUpperCase(),
    'free',
    '',
    freeReferenceId,
    'RegisteredFree',
    qr.qrUrl,
    qr.verificationUrl,
    'Skipped',
    '',
    'Free event registration',
    '',
    '',
    createdAt,
    createdAt,
    '',
    '',
    'NotApplicable',
    '',
    'NotCheckedIn',
    '',
    '',
    attendeeDetails,
    serializeGuestPasses_(guestPasses)
  ]);

  const rowNumber = sheet.getLastRow();
  const emailResult = sendEventTicketEmail_(customerEmail, {
    customerName: customerName,
    eventTitle: event.title,
    eventSubtitle: event.subtitle,
    eventImageUrl: event.imageUrl || '',
    transactionId: transactionId,
    qty: qty,
    amount: 0,
    currency: String(event.currency || 'INR'),
    eventStartAt: event.startAt || '',
    eventEndAt: event.endAt || '',
    createdAt: createdAt,
    paidAt: createdAt,
    qrUrl: qr.qrUrl,
    verificationUrl: qr.verificationUrl,
    attendeeNames: attendeeNames,
    guestPasses: guestPasses,
    isFreeRegistration: true
  });

  updateTransactionColumns_(sheet, rowNumber, {
    emailStatus: emailResult.ok ? 'Sent' : `Failed: ${emailResult.error || ''}`,
    emailSentAt: emailResult.ok ? new Date() : ''
  });

  return {
    ok: true,
    action: 'register_free_event',
    transactionId: transactionId,
    status: 'RegisteredFree',
    qrUrl: qr.qrUrl,
    verificationUrl: qr.verificationUrl,
    emailSent: !!emailResult.ok,
    guestPassCount: guestPasses.length,
    policy: 'Free registration confirmed. Your QR entry pass has been prepared for email delivery.',
    attendeeNames: attendeeNames,
    event: {
      id: event.id,
      title: event.title,
      subtitle: event.subtitle,
      startAtIso: event.startAtIso,
      endAtIso: event.endAtIso
    }
  };
}

function handleCreateEventOrder_(data) {
  const eventId = String(data.eventId || '').trim();
  const customerName = String(data.customerName || data.name || '').trim();
  const customerEmail = String(data.customerEmail || data.email || '').trim();
  const customerPhone = normalizePhoneDigits_(data.customerPhone || data.phone || '');
  const qty = Math.max(1, Math.floor(Number(data.qty || 1)));
  const allowDuplicate = data.allowDuplicate === true || String(data.allowDuplicate || '').trim().toLowerCase() === 'true';
  let attendeeNames = normalizeAttendeeNames_(data.attendeeNames || data.attendees || '');

  if (!eventId || !customerName || !customerEmail || customerPhone.length < 8) {
    return { ok: false, error: 'INVALID_INPUT', message: 'eventId, name, email and valid phone are required.' };
  }

  if (qty === 1 && attendeeNames.length === 0) {
    attendeeNames = [customerName];
  }

  if (attendeeNames.length !== qty) {
    return {
      ok: false,
      error: 'ATTENDEE_COUNT_MISMATCH',
      message: `Please provide exactly ${qty} attendee name(s).`
    };
  }

  const event = getActiveEvents_().find((item) => String(item.id) === eventId);
  if (!event) {
    return { ok: false, error: 'EVENT_NOT_ACTIVE', message: 'Event is not active or not found.' };
  }

  if (!event.paymentEnabled || String(event.eventType || '').toLowerCase() !== 'paid') {
    return { ok: false, error: 'EVENT_NOT_PAID', message: 'This event does not require payment.' };
  }

  const sheet = getOrCreateEventTransactionsSheet_();
  const duplicateTx = findLatestDuplicateEventRegistration_(sheet, event.id, customerEmail, customerPhone);
  if (duplicateTx && !allowDuplicate) {
    return buildAlreadyRegisteredResponse_(duplicateTx, event, 'create_event_order');
  }

  const maxTickets = Number(event.maxTickets || 0);
  if (maxTickets > 0 && qty > maxTickets) {
    return { ok: false, error: 'QTY_EXCEEDS_LIMIT', message: `Maximum ${maxTickets} ticket(s) allowed per booking.` };
  }

  const ticketPrice = Number(event.ticketPrice || 0);
  if (!Number.isFinite(ticketPrice) || ticketPrice <= 0) {
    return { ok: false, error: 'INVALID_EVENT_PRICE', message: 'Ticket price is not configured.' };
  }

  const amount = Number((ticketPrice * qty).toFixed(2));
  const amountInPaise = Math.round(amount * 100);
  const transactionId = `EVT-${Utilities.getUuid()}`;
  const receipt = transactionId.slice(0, 40);
  const currency = String(event.currency || 'INR').toUpperCase();

  const razorpayOrder = createRazorpayOrder_(amountInPaise, currency, receipt);
  if (!razorpayOrder.ok) {
    return razorpayOrder;
  }

  const createdAt = new Date();
  const attendeeDetails = attendeeNames.join(', ');
  sheet.appendRow([
    transactionId,
    event.id,
    event.title,
    customerName,
    customerEmail,
    customerPhone,
    qty,
    amount,
    currency,
    'razorpay',
    razorpayOrder.order.id,
    '',
    'PendingPayment',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    createdAt,
    '',
    '',
    '',
    'NotApplicable',
    '',
    'NotCheckedIn',
    '',
    '',
    attendeeDetails,
    ''
  ]);

  return {
    ok: true,
    action: 'create_event_order',
    transactionId: transactionId,
    keyId: razorpayOrder.keyId,
    order: razorpayOrder.order,
    amount: amount,
    amountInPaise: amountInPaise,
    currency: currency,
    policy: NO_REFUND_POLICY_TEXT,
    event: {
      id: event.id,
      title: event.title,
      subtitle: event.subtitle,
      startAtIso: event.startAtIso,
      endAtIso: event.endAtIso
    },
    attendeeNames: attendeeNames
  };
}

function updateTransactionColumns_(sheet, row, updates) {
  const map = {
    paymentId: 12,
    status: 13,
    qrUrl: 14,
    qrPayload: 15,
    crmSyncStatus: 16,
    crmSyncCode: 17,
    crmSyncMessage: 18,
    emailStatus: 19,
    emailSentAt: 20,
    paidAt: 22,
    cancelRequestedAt: 23,
    cancelledAt: 24,
    refundStatus: 25,
    refundId: 26,
    checkInStatus: 27,
    checkedInAt: 28,
    verifiedBy: 29,
    guestPassesJson: 31,
    issuedBy: 32,
    cancelRequestBy: 33,
    cancelRequestReason: 34,
    cancelReviewedBy: 35,
    cancelReviewedAt: 36,
    cancelDecision: 37,
    cashLedgerEntryId: 38,
    checkedInCount: 39
  };

  Object.keys(updates || {}).forEach((key) => {
    if (Object.prototype.hasOwnProperty.call(map, key)) {
      sheet.getRange(row, map[key]).setValue(updates[key]);
    }
  });
}

function handleConfirmEventPayment_(data) {
  const orderId = String(data.orderId || data.razorpay_order_id || '').trim();
  const paymentId = String(data.paymentId || data.razorpay_payment_id || '').trim();
  const signature = String(data.signature || data.razorpay_signature || '').trim();

  if (!orderId || !paymentId || !signature) {
    return { ok: false, error: 'INVALID_INPUT', message: 'orderId, paymentId and signature are required.' };
  }

  if (!verifyRazorpaySignature_(orderId, paymentId, signature)) {
    return { ok: false, error: 'SIGNATURE_MISMATCH', message: 'Payment verification failed.' };
  }

  return finalizeEventPaymentByOrderId_(orderId, paymentId, {
    action: 'confirm_event_payment',
    paymentVerifiedBy: 'checkout_signature+razorpay_api'
  });
}

function handleResendEventConfirmation_(data) {
  const transactionId = String(data.transactionId || '').trim();
  const eventId = String(data.eventId || '').trim();
  const email = String(data.customerEmail || data.email || '').trim().toLowerCase();
  const phone = normalizePhoneDigits_(data.customerPhone || data.phone || '');

  if (!transactionId || (!email && !phone)) {
    return { ok: false, error: 'INVALID_INPUT', message: 'transactionId and registered email/phone are required.' };
  }

  const sheet = getOrCreateEventTransactionsSheet_();
  const tx = findTransactionById_(sheet, transactionId);
  if (!tx) {
    return { ok: false, error: 'TX_NOT_FOUND', message: 'Transaction not found.' };
  }

  const txValues = tx.values || [];
  const txEventId = String(txValues[1] || '').trim();
  if (eventId && txEventId && eventId !== txEventId) {
    return { ok: false, error: 'EVENT_MISMATCH', message: 'Transaction does not belong to this event.' };
  }

  const txEmail = String(txValues[4] || '').trim().toLowerCase();
  const txPhone = normalizePhoneDigits_(txValues[5] || '');
  const emailMatch = !!email && txEmail === email;
  const phoneMatch = !!phone && txPhone === phone;
  if (!emailMatch && !phoneMatch) {
    return { ok: false, error: 'IDENTITY_MISMATCH', message: 'Registration ownership verification failed.' };
  }

  const txStatus = String(txValues[12] || '').trim();
  const qrUrl = String(txValues[13] || '').trim();
  const verificationUrl = String(txValues[14] || '').trim();
  if (!qrUrl && !verificationUrl) {
    return {
      ok: false,
      error: 'TICKET_NOT_READY',
      message: txStatus === 'PendingPayment'
        ? 'Payment is pending, so confirmation email cannot be sent yet.'
        : 'Ticket confirmation is not ready yet.'
    };
  }

  const event = getEventById_(txEventId);
  const attendeeNames = normalizeAttendeeNames_(String(txValues[29] || '').trim());
  const guestPasses = getGuestPassesFromTransactionRow_(txValues);
  const isFreeRegistration = String(txValues[9] || '').trim().toLowerCase() === 'free' || txStatus === 'RegisteredFree';
  const payload = {
    customerName: String(txValues[3] || ''),
    eventTitle: String((event && event.title) || txValues[2] || ''),
    eventSubtitle: String((event && event.subtitle) || ''),
    eventImageUrl: event && event.imageUrl ? event.imageUrl : '',
    transactionId: String(txValues[0] || ''),
    qty: Number(txValues[6] || 1) || 1,
    amount: Number(txValues[7] || 0) || 0,
    currency: String(txValues[8] || 'INR').trim().toUpperCase(),
    eventStartAt: event && event.startAt ? event.startAt : '',
    eventEndAt: event && event.endAt ? event.endAt : '',
    createdAt: txValues[20] || new Date(),
    paidAt: txValues[21] || txValues[20] || new Date(),
    qrUrl: qrUrl,
    verificationUrl: verificationUrl,
    attendeeNames: attendeeNames,
    guestPasses: guestPasses,
    isFreeRegistration: isFreeRegistration
  };

  const emailResult = sendEventTicketEmail_(txEmail, payload);
  updateTransactionColumns_(sheet, tx.row, {
    emailStatus: emailResult.ok ? 'Sent (Resent)' : `Failed: ${emailResult.error || ''}`,
    emailSentAt: emailResult.ok ? new Date() : ''
  });

  if (!emailResult.ok) {
    return { ok: false, error: emailResult.error || 'EMAIL_SEND_FAILED', message: emailResult.message || 'Unable to resend email.' };
  }

  return {
    ok: true,
    action: 'resend_event_confirmation',
    transactionId: String(txValues[0] || ''),
    message: 'Confirmation email has been sent again successfully.'
  };
}

function handleEventCancellationRequest_(data) {
  const transactionId = String(data.transactionId || '').trim();
  const phone = normalizePhoneDigits_(data.phone || '');
  const email = String(data.email || '').trim().toLowerCase();

  if (!transactionId || (!phone && !email)) {
    return { ok: false, error: 'INVALID_INPUT', message: 'transactionId and phone/email are required.' };
  }

  const sheet = getOrCreateEventTransactionsSheet_();
  const tx = findTransactionById_(sheet, transactionId);
  if (!tx) {
    return { ok: false, error: 'TX_NOT_FOUND', message: 'Transaction not found.' };
  }

  const txPhone = normalizePhoneDigits_(tx.values[5] || '');
  const txEmail = String(tx.values[4] || '').trim().toLowerCase();
  if ((phone && txPhone !== phone) && (email && txEmail !== email)) {
    return { ok: false, error: 'IDENTITY_MISMATCH', message: 'Transaction ownership validation failed.' };
  }

  const status = String(tx.values[12] || '').trim();
  if (status === 'CheckedIn') {
    return { ok: false, error: 'ALREADY_CHECKED_IN', message: 'Ticket already checked in and cannot be cancelled.' };
  }
  if (status === 'CancelledNoRefund') {
    return { ok: true, action: 'request_event_cancellation', idempotent: true, status: status, policy: NO_REFUND_POLICY_TEXT };
  }

  updateTransactionColumns_(sheet, tx.row, {
    status: 'CancelledNoRefund',
    cancelRequestedAt: new Date(),
    cancelledAt: new Date(),
    checkInStatus: 'Invalidated'
  });

  return {
    ok: true,
    action: 'request_event_cancellation',
    transactionId: transactionId,
    status: 'CancelledNoRefund',
    refundEligible: false,
    policy: NO_REFUND_POLICY_TEXT
  };
}

function validateEventQrSignature_(transactionId, eventId, paymentId, signature) {
  return validateGuestEventQrSignature_(transactionId, eventId, paymentId, '', signature);
}

function validateGuestEventQrSignature_(transactionId, eventId, paymentId, guestId, signature) {
  const signingSecret = String(PropertiesService.getScriptProperties().getProperty('EVENT_QR_SIGNING_SECRET') || ADMIN_PANEL_PASSCODE).trim();
  const raw = `${transactionId}|${eventId}|${paymentId}|${String(guestId || '').trim()}`;
  const digest = Utilities.computeHmacSha256Signature(raw, signingSecret);
  const expected = digest.map((byte) => {
    const v = (byte < 0 ? byte + 256 : byte).toString(16);
    return v.length === 1 ? `0${v}` : v;
  }).join('');
  return String(signature || '').trim() === expected;
}

function buildNormalizedEventQrRequest_(requestData) {
  const parsed = parseEventQrScanText_(requestData && (requestData.scanText || requestData.rawScan || requestData.qrText || ''));
  return {
    transactionId: String(parsed && parsed.transactionId || requestData.tx || requestData.transactionId || '').trim(),
    eventId: String(parsed && parsed.eventId || requestData.eventId || '').trim(),
    paymentId: String(parsed && parsed.paymentId || requestData.paymentId || requestData.pid || '').trim(),
    guestId: String(parsed && parsed.guestId || requestData.guestId || requestData.gid || '').trim(),
    signature: String(parsed && parsed.signature || requestData.sig || requestData.signature || '').trim(),
    rawScan: String(parsed && parsed.rawScan || requestData.scanText || requestData.rawScan || requestData.qrText || '').trim()
  };
}

function evaluateEventQrRequest_(requestData, options) {
  const settings = options || {};
  const auth = settings.auth || authorizeAdminRequest_(requestData, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Staff authentication required.' };
  }

  const verifiedBy = String(settings.verifiedBy || requestData.verifiedBy || (auth.user && auth.user.username) || 'staff').trim() || 'staff';
  const normalized = buildNormalizedEventQrRequest_(requestData || {});

  if (!normalized.transactionId || !normalized.eventId || !normalized.paymentId || !normalized.signature) {
    return { ok: false, error: 'INVALID_QR', message: 'Incomplete QR data.' };
  }

  if (!validateGuestEventQrSignature_(normalized.transactionId, normalized.eventId, normalized.paymentId, normalized.guestId, normalized.signature)) {
    return { ok: false, error: 'TAMPERED_QR', message: 'QR signature validation failed.' };
  }

  const sheet = getOrCreateEventTransactionsSheet_();
  const tx = findTransactionById_(sheet, normalized.transactionId);
  if (!tx) {
    return { ok: false, error: 'TX_NOT_FOUND', message: 'Transaction not found.' };
  }

  const txEventId = String(tx.values[1] || '').trim();
  const txPaymentId = String(tx.values[11] || '').trim();
  const status = String(tx.values[12] || '').trim();
  const statusKey = String(status || '').trim().toLowerCase();
  const checkInStatus = String(tx.values[26] || '').trim();
  const checkInStatusKey = String(checkInStatus || '').trim().toLowerCase();
  const gateway = String(tx.values[9] || '').trim().toLowerCase();
  const isFreeRegistration = gateway === 'free' || statusKey === 'registeredfree' || statusKey === 'checkedinfree';
  const guestPasses = getGuestPassesFromTransactionRow_(tx.values);
  const event = getEventById_(txEventId);
  const qty = Math.max(1, Number(tx.values[6] || 1) || 1);
  const checkedInCountRaw = Math.max(0, Math.floor(Number(tx.values[38] || 0) || 0));
  const checkedInCount = Math.min(qty, checkedInCountRaw);
  const remainingEntries = Math.max(0, qty - checkedInCount);
  const requestedAdmitCount = Math.max(1, Math.floor(Number(requestData.admittedCount || requestData.arrivedCount || requestData.count || 1) || 1));
  const basePreview = {
    ok: true,
    transactionId: normalized.transactionId,
    eventId: normalized.eventId,
    eventTitle: String((event && event.title) || tx.values[2] || ''),
    customerName: String(tx.values[3] || ''),
    customerEmail: String(tx.values[4] || ''),
    customerPhone: String(tx.values[5] || ''),
    qty: qty,
    checkedInCount: checkedInCount,
    remainingEntries: remainingEntries,
    admittedCount: normalized.guestId ? 1 : requestedAdmitCount,
    amount: Number(tx.values[7] || 0) || 0,
    currency: String(tx.values[8] || 'INR').trim().toUpperCase(),
    bookingType: isFreeRegistration ? 'Free' : 'Paid',
    gateway: gateway || (isFreeRegistration ? 'free' : ''),
    status: status,
    checkInStatus: checkInStatus,
    guestId: normalized.guestId,
    guestPassCount: guestPasses.length,
    duplicateKey: normalized.guestId ? `${normalized.transactionId}::${normalized.guestId}` : normalized.transactionId,
    verifiedBy: verifiedBy,
    canCheckIn: false,
    rawScan: normalized.rawScan,
    createdAt: tx.values[20] || '',
    checkedInAt: tx.values[27] || ''
  };

  if (txEventId !== normalized.eventId || txPaymentId !== normalized.paymentId) {
    return { ok: false, error: 'QR_TX_MISMATCH', message: 'QR does not match transaction data.' };
  }

  if (status !== 'Paid' && status !== 'CheckedIn' && status !== 'RegisteredFree' && status !== 'CheckedInFree') {
    return { ok: false, error: 'INVALID_STATUS', message: `Ticket is not valid for check-in. Current status: ${status}` };
  }

  if (normalized.guestId) {
    const guestPassIndex = guestPasses.findIndex((item) => String(item.guestId || '').trim() === normalized.guestId);
    if (guestPassIndex === -1) {
      return { ok: false, error: 'GUEST_PASS_NOT_FOUND', message: 'Guest pass not found for this QR.' };
    }

    const guestPass = guestPasses[guestPassIndex];
    basePreview.guestLabel = guestPass.guestLabel || 'Guest Pass';
    basePreview.guestName = guestPass.guestName || '';
    basePreview.checkInStatus = String(guestPass.checkInStatus || '').trim() || checkInStatus;
    basePreview.checkedInAt = guestPass.checkedInAt || '';
    basePreview.remainingGuestPasses = guestPasses.filter((item) => String(item.checkInStatus || '').trim() !== 'CheckedIn').length;

    if (String(guestPass.checkInStatus || '').trim() === 'CheckedIn') {
      return {
        ok: false,
        error: 'ALREADY_USED',
        message: `${guestPass.guestLabel || 'Guest pass'} has already been checked in.`,
        transactionId: basePreview.transactionId,
        guestId: basePreview.guestId,
        duplicateKey: basePreview.duplicateKey
      };
    }

    basePreview.canCheckIn = true;

    if (!settings.commit) {
      return Object.assign({
        action: settings.action || 'admin_preview_event_qr',
        previewOnly: true,
        message: `${guestPass.guestLabel || 'Guest pass'} is ready for entry confirmation.`
      }, basePreview);
    }

    guestPass.checkInStatus = 'CheckedIn';
    guestPass.checkedInAt = new Date().toISOString();
    guestPass.verifiedBy = verifiedBy;
    const nextCheckedCount = Math.max(
      checkedInCount,
      guestPasses.filter((item) => String(item.checkInStatus || '').trim() === 'CheckedIn').length
    );
    updateTransactionColumns_(sheet, tx.row, {
      guestPassesJson: serializeGuestPasses_(guestPasses),
      checkedInCount: nextCheckedCount
    });

    const allCheckedIn = guestPasses.length && guestPasses.every((item) => String(item.checkInStatus || '').trim() === 'CheckedIn');
    const finalStatus = allCheckedIn ? (isFreeRegistration ? 'CheckedInFree' : 'CheckedIn') : status;
    updateTransactionColumns_(sheet, tx.row, {
      status: finalStatus,
      checkInStatus: allCheckedIn ? 'CheckedIn' : 'PartiallyCheckedIn',
      checkedInAt: allCheckedIn ? new Date() : (tx.values[27] || ''),
      verifiedBy: verifiedBy
    });

    return Object.assign({
      action: settings.action || 'verify_event_qr',
      previewOnly: false,
      status: finalStatus,
      checkInStatus: allCheckedIn ? 'CheckedIn' : 'PartiallyCheckedIn',
      checkedInAt: guestPass.checkedInAt,
      checkedInCount: nextCheckedCount,
      remainingEntries: Math.max(0, qty - nextCheckedCount),
      remainingGuestPasses: guestPasses.filter((item) => String(item.checkInStatus || '').trim() !== 'CheckedIn').length,
      message: `${guestPass.guestLabel || 'Guest pass'} checked in successfully.`
    }, basePreview);
  }

  if (remainingEntries <= 0 || checkInStatusKey === 'checkedin' || statusKey === 'checkedin' || statusKey === 'checkedinfree') {
    return { ok: false, error: 'ALREADY_USED', message: 'All allowed entries for this QR are already used.', transactionId: basePreview.transactionId, duplicateKey: basePreview.duplicateKey };
  }

  if (requestedAdmitCount > remainingEntries) {
    return {
      ok: false,
      error: 'ADMISSION_EXCEEDS_REMAINING',
      message: `Only ${remainingEntries} entr${remainingEntries === 1 ? 'y is' : 'ies are'} remaining for this QR.`,
      transactionId: basePreview.transactionId,
      duplicateKey: basePreview.duplicateKey,
      remainingEntries: remainingEntries,
      checkedInCount: checkedInCount,
      qty: qty
    };
  }

  basePreview.canCheckIn = true;

  if (!settings.commit) {
    return Object.assign({
      action: settings.action || 'admin_preview_event_qr',
      previewOnly: true,
      message: `Ticket is ready for entry confirmation (${requestedAdmitCount} now, ${remainingEntries} remaining before save).`
    }, basePreview);
  }

  const nextCheckedInCount = Math.min(qty, checkedInCount + requestedAdmitCount);
  const fullyCheckedIn = nextCheckedInCount >= qty;
  const finalStatus = fullyCheckedIn ? (isFreeRegistration ? 'CheckedInFree' : 'CheckedIn') : status;
  const nextCheckInStatus = fullyCheckedIn ? 'CheckedIn' : 'PartiallyCheckedIn';
  const checkedInAtValue = tx.values[27] || new Date();

  updateTransactionColumns_(sheet, tx.row, {
    status: finalStatus,
    checkInStatus: nextCheckInStatus,
    checkedInAt: checkedInAtValue,
    checkedInCount: nextCheckedInCount,
    verifiedBy: verifiedBy
  });

  const remainingAfter = Math.max(0, qty - nextCheckedInCount);
  return Object.assign({
    action: settings.action || 'verify_event_qr',
    previewOnly: false,
    status: finalStatus,
    checkInStatus: nextCheckInStatus,
    checkedInAt: checkedInAtValue instanceof Date ? checkedInAtValue.toISOString() : String(checkedInAtValue),
    checkedInCount: nextCheckedInCount,
    remainingEntries: remainingAfter,
    admittedCount: requestedAdmitCount,
    message: remainingAfter > 0
      ? `${requestedAdmitCount} entr${requestedAdmitCount === 1 ? 'y' : 'ies'} confirmed. ${remainingAfter} remaining on this QR.`
      : 'Ticket checked in successfully.'
  }, basePreview);
}

function handleVerifyEventQr_(data) {
  return evaluateEventQrRequest_(data, {
    action: 'verify_event_qr',
    commit: true
  });
}

function handleAdminPreviewEventQr_(data) {
  return evaluateEventQrRequest_(data, {
    action: 'admin_preview_event_qr',
    commit: false
  });
}

function handleAdminBatchCheckinEventQr_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Staff authentication required.' };
  }

  const verifiedBy = String(data.verifiedBy || (auth.user && auth.user.username) || 'staff').trim() || 'staff';
  const scans = Array.isArray(data.scans) ? data.scans : [];
  if (!scans.length) {
    return { ok: false, error: 'SCANS_REQUIRED', message: 'At least one QR scan is required.' };
  }

  const seenKeys = {};
  const results = scans.map((item) => {
    const requestData = typeof item === 'string'
      ? { action: 'admin_batch_checkin_event_qr', scanText: item, verifiedBy: verifiedBy }
      : Object.assign({}, item || {}, { action: 'admin_batch_checkin_event_qr', verifiedBy: verifiedBy });

    const preview = evaluateEventQrRequest_(requestData, {
      action: 'admin_batch_checkin_event_qr',
      commit: false,
      auth: auth,
      verifiedBy: verifiedBy
    });

    if (!preview.ok) {
      return Object.assign({ committed: false }, preview);
    }

    const duplicateKey = String(preview.duplicateKey || '').trim();
    if (duplicateKey && seenKeys[duplicateKey]) {
      return {
        ok: false,
        committed: false,
        error: 'DUPLICATE_SCAN_IN_BATCH',
        message: 'Same guest/ticket was scanned multiple times in this batch.',
        transactionId: preview.transactionId,
        guestId: preview.guestId || '',
        duplicateKey: duplicateKey
      };
    }
    if (duplicateKey) seenKeys[duplicateKey] = true;

    const committed = evaluateEventQrRequest_(requestData, {
      action: 'admin_batch_checkin_event_qr',
      commit: true,
      auth: auth,
      verifiedBy: verifiedBy
    });
    return Object.assign({ committed: !!committed.ok }, committed);
  });

  const totals = results.reduce((acc, item) => {
    if (item && item.ok) acc.success += 1;
    else acc.failed += 1;
    return acc;
  }, { success: 0, failed: 0 });

  return {
    ok: true,
    action: 'admin_batch_checkin_event_qr',
    verifiedBy: verifiedBy,
    totals: totals,
    results: results
  };
}

function getEventTransactionsReport_() {
  const sheet = getOrCreateEventTransactionsSheet_();
  const values = sheet.getDataRange().getValues();
  if (values.length <= 1) {
    return {
      total: 0,
      byStatus: {},
      recent: []
    };
  }

  const byStatus = {};
  const rows = values.slice(1);
  rows.forEach((row) => {
    const status = String(row[12] || 'Unknown').trim() || 'Unknown';
    byStatus[status] = (byStatus[status] || 0) + 1;
  });

  const recent = rows.slice(-20).reverse().map((row) => ({
    transactionId: row[0],
    eventId: row[1],
    eventTitle: row[2],
    customerName: row[3],
    customerEmail: row[4],
    amount: row[7],
    status: row[12],
    createdAt: row[20],
    paidAt: row[21],
    checkedInAt: row[27]
  }));

  return {
    total: rows.length,
    byStatus: byStatus,
    recent: recent
  };
}

function getAdminCashSummary_(adminUsername, ledgerDate) {
  const safeUsername = normalizeUsernameMobile_(adminUsername || '');
  const targetDate = getLedgerDateKey_(ledgerDate || new Date());
  const ledgerSheet = getOrCreateAdminCashLedgerSheet_();
  const txSheet = getOrCreateEventTransactionsSheet_();
  const superSheet = getOrCreateSuperadminCashLedgerSheet_();

  const ledgerRows = ledgerSheet.getDataRange().getValues().slice(1).filter((row) => {
    return String(row[2] || '').trim() === safeUsername && String(row[1] || '').trim() === targetDate;
  });

  const totals = ledgerRows.reduce((acc, row) => {
    const amount = Number(row[10] || 0) || 0;
    const status = String(row[12] || 'Pending').trim() || 'Pending';
    acc.totalAmount += amount;
    acc.totalTransactions += 1;
    if (status === 'Approved') acc.approvedAmount += amount;
    if (status === 'Requested') acc.requestedAmount += amount;
    if (status === 'Pending') acc.pendingAmount += amount;
    return acc;
  }, { totalAmount: 0, totalTransactions: 0, approvedAmount: 0, requestedAmount: 0, pendingAmount: 0 });

  const txRows = txSheet.getDataRange().getValues().slice(1).filter((row) => {
    return String(row[9] || '').trim().toLowerCase() === 'cash'
      && String(row[31] || '').trim() === safeUsername;
  });

  const recentTransactions = txRows.slice(-20).reverse().map((row) => ({
    transactionId: String(row[0] || ''),
    eventId: String(row[1] || ''),
    eventTitle: String(row[2] || ''),
    customerName: String(row[3] || ''),
    customerPhone: String(row[5] || ''),
    qty: Number(row[6] || 1) || 1,
    amount: Number(row[7] || 0) || 0,
    status: String(row[12] || ''),
    createdAt: row[20] || '',
    paidAt: row[21] || '',
    cancelDecision: String(row[36] || ''),
    cashLedgerEntryId: String(row[37] || '')
  }));

  const handoverHistory = superSheet.getDataRange().getValues().slice(1)
    .filter((row) => String(row[2] || '').trim() === safeUsername)
    .slice(-15)
    .reverse()
    .map((row) => ({
      batchKey: String(row[0] || ''),
      ledgerDate: String(row[1] || ''),
      totalTransactions: Number(row[4] || 0) || 0,
      totalAmount: Number(row[5] || 0) || 0,
      requestedAt: row[6] || '',
      approvedAt: row[8] || '',
      approvedBy: String(row[9] || ''),
      status: String(row[10] || '')
    }));

  return {
    ledgerDate: targetDate,
    totals: totals,
    recentTransactions: recentTransactions,
    handoverHistory: handoverHistory
  };
}

function getSuperadminCashDashboard_(ledgerDate) {
  const targetDate = ledgerDate ? getLedgerDateKey_(ledgerDate) : '';
  const superSheet = getOrCreateSuperadminCashLedgerSheet_();
  const txSheet = getOrCreateEventTransactionsSheet_();
  const rows = superSheet.getDataRange().getValues().slice(1);

  const pendingHandovers = rows
    .filter((row) => (!targetDate || String(row[1] || '').trim() === targetDate) && String(row[10] || '').trim() === 'Requested')
    .map((row) => ({
      batchKey: String(row[0] || ''),
      ledgerDate: String(row[1] || ''),
      adminUsername: String(row[2] || ''),
      adminDisplayName: String(row[3] || ''),
      totalTransactions: Number(row[4] || 0) || 0,
      totalAmount: Number(row[5] || 0) || 0,
      requestedAt: row[6] || '',
      requestedBy: String(row[7] || '')
    }))
    .sort((a, b) => String(b.requestedAt || '').localeCompare(String(a.requestedAt || '')));

  const recentApprovals = rows
    .filter((row) => String(row[10] || '').trim() === 'Approved')
    .slice(-20)
    .reverse()
    .map((row) => ({
      batchKey: String(row[0] || ''),
      ledgerDate: String(row[1] || ''),
      adminUsername: String(row[2] || ''),
      adminDisplayName: String(row[3] || ''),
      totalTransactions: Number(row[4] || 0) || 0,
      totalAmount: Number(row[5] || 0) || 0,
      approvedAt: row[8] || '',
      approvedBy: String(row[9] || '')
    }));

  const cancelRequests = txSheet.getDataRange().getValues().slice(1)
    .filter((row) => String(row[9] || '').trim().toLowerCase() === 'cash' && String(row[36] || '').trim() === 'Pending')
    .map((row) => ({
      transactionId: String(row[0] || ''),
      eventId: String(row[1] || ''),
      eventTitle: String(row[2] || ''),
      customerName: String(row[3] || ''),
      amount: Number(row[7] || 0) || 0,
      status: String(row[12] || ''),
      cancelRequestedAt: row[22] || '',
      cancelRequestBy: String(row[32] || ''),
      cancelRequestReason: String(row[33] || ''),
      issuedBy: String(row[31] || '')
    }))
    .sort((a, b) => String(b.cancelRequestedAt || '').localeCompare(String(a.cancelRequestedAt || '')));

  return {
    ledgerDate: targetDate || getLedgerDateKey_(new Date()),
    pendingHandovers: pendingHandovers,
    recentApprovals: recentApprovals,
    cancelRequests: cancelRequests
  };
}

function handleAdminIssueCashPaidPass_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  const eventId = String(data.eventId || '').trim();
  const customerName = String(data.customerName || data.name || '').trim();
  const customerEmail = String(data.customerEmail || data.email || '').trim();
  const customerPhone = normalizePhoneDigits_(data.customerPhone || data.phone || '');
  const qty = Math.max(1, Math.floor(Number(data.qty || 1)));
  const cashNotes = String(data.notes || '').trim();
  let attendeeNames = normalizeAttendeeNames_(data.attendeeNames || data.attendees || '');

  if (!eventId || !customerName || customerPhone.length < 8) {
    return { ok: false, error: 'INVALID_INPUT', message: 'eventId, customerName and valid phone are required.' };
  }

  if (qty === 1 && attendeeNames.length === 0) attendeeNames = [customerName];
  if (attendeeNames.length !== qty) {
    return { ok: false, error: 'ATTENDEE_COUNT_MISMATCH', message: `Please provide exactly ${qty} attendee name(s).` };
  }

  const event = getActiveEvents_().find((item) => String(item.id) === eventId);
  if (!event) {
    return { ok: false, error: 'EVENT_NOT_ACTIVE', message: 'Event is not active or not found.' };
  }
  if (!event.paymentEnabled || String(event.eventType || '').toLowerCase() !== 'paid') {
    return { ok: false, error: 'EVENT_NOT_PAID', message: 'Cash issue is only allowed for paid events.' };
  }

  const maxTickets = Number(event.maxTickets || 0);
  if (maxTickets > 0 && qty > maxTickets) {
    return { ok: false, error: 'QTY_EXCEEDS_LIMIT', message: `Maximum ${maxTickets} ticket(s) allowed per booking.` };
  }

  const ticketPrice = Number(event.ticketPrice || 0);
  if (!Number.isFinite(ticketPrice) || ticketPrice <= 0) {
    return { ok: false, error: 'INVALID_EVENT_PRICE', message: 'Ticket price is not configured.' };
  }

  const amount = Number((ticketPrice * qty).toFixed(2));
  const createdAt = new Date();
  const currency = String(event.currency || 'INR').toUpperCase();
  const transactionId = `CASH-${Utilities.getUuid()}`;
  const paymentId = `CASHPAY-${Utilities.getUuid().replace(/-/g, '').slice(0, 12).toUpperCase()}`;
  const ledgerEntryId = `LEDGER-${Utilities.getUuid().replace(/-/g, '').slice(0, 12).toUpperCase()}`;
  const qr = buildEventQrPayload_(transactionId, event.id, paymentId);
  const guestPasses = [];
  const attendeeDetails = attendeeNames.join(', ');
  const txSheet = getOrCreateEventTransactionsSheet_();

  txSheet.appendRow([
    transactionId,
    event.id,
    event.title,
    customerName,
    customerEmail,
    customerPhone,
    qty,
    amount,
    currency,
    'cash',
    '',
    paymentId,
    'Paid',
    qr.qrUrl,
    qr.verificationUrl,
    'Skipped',
    '',
    cashNotes || 'Cash payment issued by admin',
    '',
    '',
    createdAt,
    createdAt,
    '',
    '',
    'NoRefundPolicy',
    '',
    'NotCheckedIn',
    '',
    '',
    attendeeDetails,
    serializeGuestPasses_(guestPasses),
    String(auth.user && auth.user.username || ''),
    '',
    '',
    '',
    '',
    '',
    ledgerEntryId
  ]);

  appendAdminCashLedgerRow_({
    ledgerEntryId: ledgerEntryId,
    ledgerDate: getLedgerDateKey_(createdAt),
    adminUsername: String(auth.user && auth.user.username || ''),
    adminDisplayName: String(auth.user && auth.user.displayName || auth.user.username || ''),
    transactionId: transactionId,
    eventId: event.id,
    eventTitle: event.title,
    customerName: customerName,
    customerPhone: customerPhone,
    qty: qty,
    amount: amount,
    currency: currency,
    status: 'Pending',
    issuedAt: createdAt,
    notes: cashNotes
  });

  let emailSent = false;
  if (customerEmail && customerEmail.indexOf('@') !== -1) {
    const emailResult = sendEventTicketEmail_(customerEmail, {
      customerName: customerName,
      eventTitle: event.title,
      eventSubtitle: event.subtitle,
      eventImageUrl: event.imageUrl || '',
      transactionId: transactionId,
      qty: qty,
      amount: amount,
      currency: currency,
      eventStartAt: event.startAt || '',
      eventEndAt: event.endAt || '',
      createdAt: createdAt,
      paidAt: createdAt,
      qrUrl: qr.qrUrl,
      verificationUrl: qr.verificationUrl,
      attendeeNames: attendeeNames,
      guestPasses: guestPasses
    });
    emailSent = !!emailResult.ok;
    updateTransactionColumns_(txSheet, txSheet.getLastRow(), {
      emailStatus: emailResult.ok ? 'Sent' : `Failed: ${emailResult.error || ''}`,
      emailSentAt: emailResult.ok ? new Date() : ''
    });
  }

  return {
    ok: true,
    action: 'admin_issue_cash_paid_pass',
    transactionId: transactionId,
    ledgerEntryId: ledgerEntryId,
    amount: amount,
    currency: currency,
    qrUrl: qr.qrUrl,
    verificationUrl: qr.verificationUrl,
    emailSent: emailSent,
    status: 'Paid',
    attendeeNames: attendeeNames,
    event: {
      id: event.id,
      title: event.title,
      startAtIso: event.startAtIso,
      endAtIso: event.endAtIso
    }
  };
}

function handleAdminRequestCashHandover_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  const ledgerDate = getLedgerDateKey_(data.ledgerDate || new Date());
  const adminUsername = String(auth.user && auth.user.username || '');
  const adminDisplayName = String(auth.user && auth.user.displayName || adminUsername);
  const batchKey = buildCashBatchKey_(adminUsername, ledgerDate);
  const adminSheet = getOrCreateAdminCashLedgerSheet_();
  const superSheet = getOrCreateSuperadminCashLedgerSheet_();
  const values = adminSheet.getDataRange().getValues();
  const now = new Date();
  const targetRows = [];
  let totalAmount = 0;

  for (let i = 1; i < values.length; i += 1) {
    if (String(values[i][2] || '').trim() !== adminUsername) continue;
    if (String(values[i][1] || '').trim() !== ledgerDate) continue;
    if (String(values[i][12] || '').trim() !== 'Pending') continue;
    targetRows.push(i + 1);
    totalAmount += Number(values[i][10] || 0) || 0;
  }

  if (!targetRows.length) {
    return { ok: false, error: 'NOTHING_TO_HANDOVER', message: 'No pending cash entries found for this admin and date.' };
  }

  targetRows.forEach((rowNumber) => {
    adminSheet.getRange(rowNumber, 13).setValue('Requested');
    adminSheet.getRange(rowNumber, 15).setValue(now);
    adminSheet.getRange(rowNumber, 18).setValue(batchKey);
  });

  const existing = findSuperadminCashLedgerRow_(superSheet, batchKey);
  if (existing) {
    superSheet.getRange(existing.row, 5).setValue(targetRows.length);
    superSheet.getRange(existing.row, 6).setValue(totalAmount);
    superSheet.getRange(existing.row, 7).setValue(now);
    superSheet.getRange(existing.row, 8).setValue(adminUsername);
    superSheet.getRange(existing.row, 11).setValue('Requested');
  } else {
    superSheet.appendRow([
      batchKey,
      ledgerDate,
      adminUsername,
      adminDisplayName,
      targetRows.length,
      totalAmount,
      now,
      adminUsername,
      '',
      '',
      'Requested',
      ''
    ]);
  }

  return {
    ok: true,
    action: 'admin_request_cash_handover',
    batchKey: batchKey,
    ledgerDate: ledgerDate,
    totalTransactions: targetRows.length,
    totalAmount: totalAmount,
    status: 'Requested'
  };
}

function handleAdminRequestCashCancel_(data) {
  const auth = authorizeAdminRequest_(data, 'admin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'Admin authentication required.' };
  }

  const transactionId = String(data.transactionId || '').trim();
  const reason = String(data.reason || '').trim();
  if (!transactionId || !reason) {
    return { ok: false, error: 'INVALID_INPUT', message: 'transactionId and reason are required.' };
  }

  const sheet = getOrCreateEventTransactionsSheet_();
  const tx = findTransactionById_(sheet, transactionId);
  if (!tx) return { ok: false, error: 'TX_NOT_FOUND', message: 'Transaction not found.' };
  if (String(tx.values[9] || '').trim().toLowerCase() !== 'cash') {
    return { ok: false, error: 'CASH_ONLY', message: 'Cancel request is supported only for cash-issued paid passes.' };
  }
  if (String(tx.values[31] || '').trim() !== String(auth.user && auth.user.username || '')) {
    return { ok: false, error: 'FORBIDDEN', message: 'You can only request cancel for your own issued cash pass.' };
  }
  if (String(tx.values[12] || '').trim() === 'CheckedIn') {
    return { ok: false, error: 'ALREADY_USED', message: 'Checked-in passes cannot be cancelled.' };
  }

  updateTransactionColumns_(sheet, tx.row, {
    status: 'CancelRequested',
    cancelRequestedAt: new Date(),
    cancelRequestBy: String(auth.user && auth.user.username || ''),
    cancelRequestReason: reason,
    cancelDecision: 'Pending'
  });

  return {
    ok: true,
    action: 'admin_request_cash_cancel',
    transactionId: transactionId,
    status: 'CancelRequested'
  };
}

function handleSuperadminApproveCashHandover_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  const adminUsername = normalizeUsernameMobile_(data.adminUsername || data.username || '');
  const ledgerDate = getLedgerDateKey_(data.ledgerDate || new Date());
  if (!adminUsername) {
    return { ok: false, error: 'INVALID_INPUT', message: 'adminUsername is required.' };
  }

  const batchKey = buildCashBatchKey_(adminUsername, ledgerDate);
  const adminSheet = getOrCreateAdminCashLedgerSheet_();
  const superSheet = getOrCreateSuperadminCashLedgerSheet_();
  const adminValues = adminSheet.getDataRange().getValues();
  const now = new Date();
  const targetRows = [];
  let totalAmount = 0;
  let adminDisplayName = adminUsername;

  for (let i = 1; i < adminValues.length; i += 1) {
    if (String(adminValues[i][2] || '').trim() !== adminUsername) continue;
    if (String(adminValues[i][1] || '').trim() !== ledgerDate) continue;
    const status = String(adminValues[i][12] || '').trim();
    if (status !== 'Requested') continue;
    targetRows.push(i + 1);
    totalAmount += Number(adminValues[i][10] || 0) || 0;
    adminDisplayName = String(adminValues[i][3] || adminDisplayName);
  }

  if (!targetRows.length) {
    return { ok: false, error: 'HANDOVER_NOT_FOUND', message: 'No requested handover found for this admin/date.' };
  }

  targetRows.forEach((rowNumber) => {
    adminSheet.getRange(rowNumber, 13).setValue('Approved');
    adminSheet.getRange(rowNumber, 16).setValue(now);
    adminSheet.getRange(rowNumber, 17).setValue(String(auth.user && auth.user.username || ''));
    adminSheet.getRange(rowNumber, 18).setValue(batchKey);
  });

  const existing = findSuperadminCashLedgerRow_(superSheet, batchKey);
  if (existing) {
    superSheet.getRange(existing.row, 4).setValue(adminDisplayName);
    superSheet.getRange(existing.row, 5).setValue(targetRows.length);
    superSheet.getRange(existing.row, 6).setValue(totalAmount);
    superSheet.getRange(existing.row, 9).setValue(now);
    superSheet.getRange(existing.row, 10).setValue(String(auth.user && auth.user.username || ''));
    superSheet.getRange(existing.row, 11).setValue('Approved');
  } else {
    superSheet.appendRow([
      batchKey,
      ledgerDate,
      adminUsername,
      adminDisplayName,
      targetRows.length,
      totalAmount,
      '',
      adminUsername,
      now,
      String(auth.user && auth.user.username || ''),
      'Approved',
      ''
    ]);
  }

  return {
    ok: true,
    action: 'superadmin_approve_cash_handover',
    batchKey: batchKey,
    ledgerDate: ledgerDate,
    totalTransactions: targetRows.length,
    totalAmount: totalAmount,
    status: 'Approved'
  };
}

function handleSuperadminResolveCashCancel_(data) {
  const auth = authorizeAdminRequest_(data, 'superadmin');
  if (!auth.ok) {
    return { ok: false, error: auth.error || 'UNAUTHORIZED', message: auth.message || 'SuperAdmin access required.' };
  }

  const transactionId = String(data.transactionId || '').trim();
  const decision = String(data.decision || '').trim().toLowerCase();
  const note = String(data.note || '').trim();
  if (!transactionId || (decision !== 'approve' && decision !== 'reject')) {
    return { ok: false, error: 'INVALID_INPUT', message: 'transactionId and decision (approve/reject) are required.' };
  }

  const sheet = getOrCreateEventTransactionsSheet_();
  const tx = findTransactionById_(sheet, transactionId);
  if (!tx) return { ok: false, error: 'TX_NOT_FOUND', message: 'Transaction not found.' };
  if (String(tx.values[9] || '').trim().toLowerCase() !== 'cash') {
    return { ok: false, error: 'CASH_ONLY', message: 'Only cash-issued passes are supported here.' };
  }

  if (decision === 'approve') {
    updateTransactionColumns_(sheet, tx.row, {
      status: 'CancelledBySuperAdmin',
      cancelledAt: new Date(),
      cancelReviewedBy: String(auth.user && auth.user.username || ''),
      cancelReviewedAt: new Date(),
      cancelDecision: 'Approved',
      crmSyncMessage: note || 'Cancelled by superadmin. No refund.'
    });
  } else {
    updateTransactionColumns_(sheet, tx.row, {
      status: 'Paid',
      cancelReviewedBy: String(auth.user && auth.user.username || ''),
      cancelReviewedAt: new Date(),
      cancelDecision: 'Rejected',
      crmSyncMessage: note || 'Cancel request rejected by superadmin.'
    });
  }

  return {
    ok: true,
    action: 'superadmin_resolve_cash_cancel',
    transactionId: transactionId,
    decision: decision,
    status: decision === 'approve' ? 'CancelledBySuperAdmin' : 'Paid'
  };
}

function getEventGuestReport_(requestedEventId) {
  const targetEventId = String(requestedEventId || '').trim();
  const sheet = getOrCreateEventTransactionsSheet_();
  const values = sheet.getDataRange().getValues();
  const rows = values.length > 1 ? values.slice(1) : [];
  const summaryByEvent = {};

  getEventRecords_().forEach((event) => {
    const eventId = String(event && event.id || '').trim();
    if (!eventId) return;
    summaryByEvent[eventId] = {
      eventId: eventId,
      eventTitle: String(event && event.title || eventId),
      eventType: String(event && event.eventType || '').trim().toLowerCase() === 'paid' ? 'paid' : 'free',
      paymentEnabled: !!(event && event.paymentEnabled),
      registrations: 0,
      guests: 0,
      freeRegistrations: 0,
      paidRegistrations: 0,
      checkedInGuests: 0,
      razorpayCollectedAmount: 0,
      cashCollectedAmount: 0
    };
  });

  rows.forEach((row) => {
    const eventId = String(row[1] || '').trim() || 'unknown-event';
    const eventTitle = String(row[2] || '').trim() || 'Untitled Event';
    const qty = Math.max(1, Number(row[6] || 1) || 1);
    const gateway = String(row[9] || '').trim().toLowerCase();
    const status = String(row[12] || '').trim() || 'Unknown';
    const statusKey = String(status || '').trim().toLowerCase();
    const checkInStatus = String(row[26] || '').trim();
    const checkInStatusKey = String(checkInStatus || '').trim().toLowerCase();
    const amount = Number(row[7] || 0) || 0;
    const isFreeRegistration = gateway === 'free'
      || statusKey === 'registeredfree'
      || statusKey === 'checkedinfree';
    const checkedIn = statusKey.indexOf('checkedin') === 0 || checkInStatusKey.indexOf('checkedin') === 0;
    const isCollectedPaid = !isFreeRegistration && (statusKey === 'paid' || statusKey.indexOf('checkedin') === 0);

    if (!summaryByEvent[eventId]) {
      summaryByEvent[eventId] = {
        eventId: eventId,
        eventTitle: eventTitle,
        eventType: isFreeRegistration ? 'free' : 'paid',
        paymentEnabled: !isFreeRegistration,
        registrations: 0,
        guests: 0,
        freeRegistrations: 0,
        paidRegistrations: 0,
        checkedInGuests: 0,
        razorpayCollectedAmount: 0,
        cashCollectedAmount: 0
      };
    }

    summaryByEvent[eventId].registrations += 1;
    summaryByEvent[eventId].guests += qty;
    summaryByEvent[eventId].checkedInGuests += checkedIn ? qty : 0;
    if (isFreeRegistration) {
      summaryByEvent[eventId].freeRegistrations += 1;
    } else {
      summaryByEvent[eventId].paidRegistrations += 1;
    }
    if (isCollectedPaid && gateway === 'razorpay') {
      summaryByEvent[eventId].razorpayCollectedAmount += amount;
    }
    if (isCollectedPaid && gateway === 'cash') {
      summaryByEvent[eventId].cashCollectedAmount += amount;
    }
  });

  const eventSummary = Object.keys(summaryByEvent)
    .map((key) => summaryByEvent[key])
    .sort((a, b) => a.eventTitle.localeCompare(b.eventTitle));

  const guests = rows
    .filter((row) => !targetEventId || String(row[1] || '').trim() === targetEventId)
    .map((row) => {
      const gateway = String(row[9] || '').trim().toLowerCase();
      const status = String(row[12] || '').trim();
      const statusKey = String(status || '').trim().toLowerCase();
      const amount = Number(row[7] || 0) || 0;
      const isFreeRegistration = gateway === 'free'
        || statusKey === 'registeredfree'
        || statusKey === 'checkedinfree';
      const bookingType = isFreeRegistration ? 'Free' : 'Paid';
      const collectionType = isFreeRegistration
        ? 'Free'
        : (gateway === 'cash' ? 'Cash' : (gateway === 'razorpay' ? 'Razorpay' : 'Other'));

      return {
        transactionId: String(row[0] || ''),
        eventId: String(row[1] || ''),
        eventTitle: String(row[2] || ''),
        customerName: String(row[3] || ''),
        customerEmail: String(row[4] || ''),
        customerPhone: String(row[5] || ''),
        qty: Number(row[6] || 1) || 1,
        amount: amount,
        currency: String(row[8] || 'INR'),
        gateway: gateway || (isFreeRegistration ? 'free' : ''),
        collectionType: collectionType,
        bookingType: bookingType,
        orderId: String(row[10] || ''),
        paymentId: String(row[11] || ''),
        status: status,
        qrUrl: String(row[13] || ''),
        emailStatus: String(row[18] || ''),
        createdAt: row[20] || '',
        confirmedAt: row[21] || '',
        refundStatus: String(row[24] || ''),
        checkInStatus: String(row[26] || ''),
        checkedInAt: row[27] || '',
        attendeeDetails: normalizeAttendeeNames_(String(row[29] || '').trim()),
        guestPasses: getGuestPassesFromTransactionRow_(row)
      };
    })
    .sort((a, b) => {
      const aTime = new Date(a.createdAt || 0).getTime() || 0;
      const bTime = new Date(b.createdAt || 0).getTime() || 0;
      return bTime - aTime;
    });

  const totals = guests.reduce((acc, item) => {
    const qty = Math.max(1, Number(item.qty || 1) || 1);
    const statusKey = String(item.status || '').trim().toLowerCase();
    acc.registrations += 1;
    acc.guests += qty;
    if (item.bookingType === 'Free') acc.freeRegistrations += 1;
    if (item.bookingType === 'Paid') acc.paidRegistrations += 1;
    if (statusKey.indexOf('checkedin') === 0 || String(item.checkInStatus || '').trim().toLowerCase().indexOf('checkedin') === 0) {
      acc.checkedInGuests += qty;
    }
    if (item.collectionType === 'Razorpay' && (statusKey === 'paid' || statusKey.indexOf('checkedin') === 0)) {
      acc.razorpayCollectedAmount += Number(item.amount || 0) || 0;
      acc.razorpayCollectedRegistrations += 1;
    }
    if (item.collectionType === 'Cash' && (statusKey === 'paid' || statusKey.indexOf('checkedin') === 0)) {
      acc.cashCollectedAmount += Number(item.amount || 0) || 0;
    }
    if (item.collectionType === 'Razorpay' && statusKey !== 'paid' && statusKey.indexOf('checkedin') !== 0 && statusKey.indexOf('cancelled') !== 0) {
      acc.razorpayPendingAmount += Number(item.amount || 0) || 0;
    }
    if (statusKey.indexOf('cancelled') === 0) {
      acc.cancelledRegistrations += 1;
    }
    return acc;
  }, {
    registrations: 0,
    guests: 0,
    freeRegistrations: 0,
    paidRegistrations: 0,
    checkedInGuests: 0,
    razorpayCollectedAmount: 0,
    razorpayCollectedRegistrations: 0,
    razorpayPendingAmount: 0,
    cashCollectedAmount: 0,
    cancelledRegistrations: 0
  });

  const reconciliationEntries = guests
    .filter((item) => item.collectionType === 'Razorpay')
    .map((item) => ({
      transactionId: item.transactionId,
      eventTitle: item.eventTitle,
      customerName: item.customerName,
      qty: item.qty,
      amount: item.amount,
      currency: item.currency,
      orderId: item.orderId,
      paymentId: item.paymentId,
      status: item.status,
      refundStatus: item.refundStatus,
      createdAt: item.createdAt,
      confirmedAt: item.confirmedAt
    }));

  return {
    selectedEventId: targetEventId,
    totals: totals,
    eventSummary: eventSummary,
    guests: guests,
    razorpayReconciliation: {
      totals: {
        collectedAmount: totals.razorpayCollectedAmount,
        collectedRegistrations: totals.razorpayCollectedRegistrations,
        pendingAmount: totals.razorpayPendingAmount,
        cashCollectedAmount: totals.cashCollectedAmount,
        cancelledRegistrations: totals.cancelledRegistrations
      },
      entries: reconciliationEntries
    }
  };
}

function upsertEventRowById_(sheet, eventRow) {
  const eventId = String(eventRow[0] || '').trim();
  if (!eventId) return { inserted: false, updated: false, row: 0 };

  const values = sheet.getDataRange().getValues();
  for (let i = 1; i < values.length; i += 1) {
    if (String(values[i][0] || '').trim() === eventId) {
      sheet.getRange(i + 1, 1, 1, eventRow.length).setValues([eventRow]);
      return { inserted: false, updated: true, row: i + 1 };
    }
  }

  sheet.appendRow(eventRow);
  return { inserted: true, updated: false, row: sheet.getLastRow() };
}

function seedDjEventsApr2026_() {
  const sheet = getOrCreateEventsSheet_();
  const rajStartAt = parseEventDateParts_('2026-04-15', '20.00');
  const rajEndAt = parseEventDateParts_('2026-04-16', '00.30');
  const adaaStartAt = parseEventDateParts_('2026-04-25', '20.00');
  const adaaEndAt = parseEventDateParts_('2026-04-26', '00.30');

  const commonDescription = (punchLine, dateText) => `${dateText}\n${punchLine}\nFor Booking Call ${EVENT_BOOKING_PHONE}\nVenue: ${EVENT_VENUE_ADDRESS}\nFree Event`;

  const rows = [
    buildEventSheetRow_({
      id: 'dj-raj-2026-apr',
      title: 'DJ Night by DJ Raj in the House with Mack',
      subtitle: 'April 15th, 8 PM onwards',
      description: commonDescription('Where Beats Drop and Stress Stops', 'April 15th, 8 PM onwards'),
      imageUrl: 'https://storagev2.files-vault.com/uploads/blacklabel-765/sub-account-82800/1776054554-WdMnjl2uZA.webp',
      videoUrl: '',
      showVideo: false,
      ctaText: 'I\'m Interested',
      ctaUrl: '',
      badgeText: 'Free Event',
      startAt: rajStartAt,
      endAt: rajEndAt,
      timeDisplayFormat: '12h',
      isActive: true,
      priority: 220,
      popupEnabled: true,
      showOncePerSession: false,
      popupDelayHours: 0,
      popupCooldownHours: 0,
      eventType: 'free',
      ticketPrice: 0,
      currency: 'INR',
      maxTickets: 0,
      paymentEnabled: false,
      cancellationPolicyText: NO_REFUND_POLICY_TEXT,
      refundPolicy: 'No Refund'
    }),
    buildEventSheetRow_({
      id: 'dj-adaa-2026-apr',
      title: 'Night With DJ Adaa',
      subtitle: '25th April, 8:00 PM onwards',
      description: commonDescription('Where Music Meets Madness', '25th April, 8:00 PM onwards'),
      imageUrl: 'https://storagev2.files-vault.com/uploads/blacklabel-765/sub-account-82800/1776054530-O5HQuYBXJh.webp',
      videoUrl: '',
      showVideo: false,
      ctaText: 'I\'m Interested',
      ctaUrl: '',
      badgeText: 'Free Event',
      startAt: adaaStartAt,
      endAt: adaaEndAt,
      timeDisplayFormat: '12h',
      isActive: true,
      priority: 210,
      popupEnabled: true,
      showOncePerSession: false,
      popupDelayHours: 0,
      popupCooldownHours: 0,
      eventType: 'free',
      ticketPrice: 0,
      currency: 'INR',
      maxTickets: 0,
      paymentEnabled: false,
      cancellationPolicyText: NO_REFUND_POLICY_TEXT,
      refundPolicy: 'No Refund'
    })
  ];

  let inserted = 0;
  let updated = 0;
  rows.forEach((row) => {
    const result = upsertEventRowById_(sheet, row);
    if (result.inserted) inserted += 1;
    if (result.updated) updated += 1;
  });

  return {
    inserted: inserted,
    updated: updated,
    total: rows.length,
    eventIds: rows.map((row) => row[0])
  };
}

function seedPaidEventSample_(ticketPrice, maxTickets) {
  const sheet = getOrCreateEventsSheet_();
  const now = new Date();
  const startAt = new Date(now.getTime() - (30 * 60 * 1000));
  const endAt = new Date(now.getTime() + (10 * 24 * 60 * 60 * 1000));
  const eventId = 'paid-test-2026';

  const row = buildEventSheetRow_({
    id: eventId,
    title: 'Bollywood Night - Paid Entry',
    subtitle: 'Live DJ, premium access',
    description: `Paid entry test event. For booking call ${EVENT_BOOKING_PHONE}. Venue: ${EVENT_VENUE_ADDRESS}`,
    imageUrl: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80',
    videoUrl: '',
    showVideo: false,
    ctaText: 'Book Now',
    ctaUrl: '',
    badgeText: 'Paid Event',
    startAt: startAt,
    endAt: endAt,
    timeDisplayFormat: '12h',
    isActive: true,
    priority: 300,
    popupEnabled: true,
    showOncePerSession: false,
    popupDelayHours: 0,
    popupCooldownHours: 0,
    eventType: 'paid',
    ticketPrice: Number(ticketPrice || 499),
    currency: 'INR',
    maxTickets: Number(maxTickets || 6),
    paymentEnabled: true,
    cancellationPolicyText: NO_REFUND_POLICY_TEXT,
    refundPolicy: 'No Refund'
  });

  const result = upsertEventRowById_(sheet, row);
  return {
    inserted: result.inserted,
    updated: result.updated,
    row: result.row,
    eventId: eventId,
    price: Number(ticketPrice || 499)
  };
}

function createTestPaidTransaction_(eventId, qty) {
  const targetId = String(eventId || '').trim();
  if (!targetId) {
    return { ok: false, error: 'EVENT_ID_REQUIRED', message: 'Pass eventId for test paid transaction.' };
  }

  const event = getEventById_(targetId);
  if (!event) {
    return { ok: false, error: 'EVENT_NOT_FOUND', message: 'Event not found.' };
  }

  const ticketPrice = Number(event.ticketPrice || 0);
  if (!Number.isFinite(ticketPrice) || ticketPrice <= 0) {
    return { ok: false, error: 'INVALID_EVENT_PRICE', message: 'Event price is not configured.' };
  }

  const safeQty = Math.max(1, Math.floor(Number(qty || 1)));
  const amount = Number((ticketPrice * safeQty).toFixed(2));
  const transactionId = `EVT-TEST-${Utilities.getUuid().slice(0, 8).toUpperCase()}`;
  const orderId = `order_test_${Utilities.getUuid().replace(/-/g, '').slice(0, 12)}`;
  const paymentId = `pay_test_${Utilities.getUuid().replace(/-/g, '').slice(0, 12)}`;
  const qr = buildEventQrPayload_(transactionId, targetId, paymentId);
  const attendeeNames = Array.from({ length: safeQty }, (_, index) => index === 0 ? 'QA Test User' : `QA Guest ${index + 1}`);
  const guestPasses = [];
  const sheet = getOrCreateEventTransactionsSheet_();
  const now = new Date();

  sheet.appendRow([
    transactionId,
    targetId,
    event.title,
    'QA Test User',
    'qa-test@example.com',
    '919876543210',
    safeQty,
    amount,
    String(event.currency || 'INR').toUpperCase(),
    'razorpay',
    orderId,
    paymentId,
    'Paid',
    qr.qrUrl,
    qr.verificationUrl,
    'Skipped',
    '',
    'Synthetic test transaction for QR verification',
    'Skipped',
    '',
    now,
    now,
    '',
    '',
    'NoRefundPolicy',
    '',
    'NotCheckedIn',
    '',
    'qa-seed',
    attendeeNames.join(', '),
    serializeGuestPasses_(guestPasses)
  ]);

  return {
    ok: true,
    message: 'Test paid transaction created successfully.',
    transactionId: transactionId,
    eventId: targetId,
    orderId: orderId,
    paymentId: paymentId,
    status: 'Paid',
    qrUrl: qr.qrUrl,
    verificationUrl: qr.verificationUrl,
    guestPassCount: guestPasses.length,
    payload: {
      tx: transactionId,
      eventId: targetId,
      paymentId: paymentId,
      sig: qr.signature
    }
  };
}

function getLatestPaidTransaction_(sheet, preferredEventId) {
  const targetEventId = String(preferredEventId || '').trim();
  const values = sheet.getDataRange().getValues();
  if (values.length <= 1) return null;

  for (let i = values.length - 1; i >= 1; i -= 1) {
    const row = values[i];
    const status = String(row[12] || '').trim();
    const eventId = String(row[1] || '').trim();
    if (status !== 'Paid' && status !== 'CheckedIn') continue;
    if (targetEventId && eventId !== targetEventId) continue;
    return { row: i + 1, values: row };
  }

  return null;
}

function normalizeAttendeeNames_(value) {
  if (Array.isArray(value)) {
    return value
      .map((item) => String(item || '').trim())
      .filter((item) => item);
  }

  const text = String(value || '').trim();
  if (!text) return [];

  return text
    .split(/[\n,;]+/)
    .map((item) => String(item || '').trim())
    .filter((item) => item);
}

function sendTestEventEmail_(recipient, preferredEventId) {
  const sheet = getOrCreateEventTransactionsSheet_();
  let tx = getLatestPaidTransaction_(sheet, preferredEventId);

  if (!tx) {
    const seeded = createTestPaidTransaction_(preferredEventId || 'paid-test-2026', 1);
    if (!seeded.ok) {
      return seeded;
    }
    tx = findTransactionById_(sheet, seeded.transactionId);
  }

  if (!tx) {
    return { ok: false, error: 'TX_NOT_FOUND', message: 'No paid transaction available to send test email.' };
  }

  const eventId = String(tx.values[1] || '').trim();
  const event = getEventById_(eventId);
  const eventTitle = String((event && event.title) || tx.values[2] || 'Event Ticket');
  const eventSubtitle = String((event && event.subtitle) || '');
  const attendeeNames = normalizeAttendeeNames_(String(tx.values[29] || '').trim());
  const guestPasses = getGuestPassesFromTransactionRow_(tx.values);
  const createdAt = tx.values[20] || new Date();
  const paidAt = tx.values[21] || createdAt;
  const emailResult = sendEventTicketEmail_(recipient, {
    customerName: 'Parin',
    eventTitle: eventTitle,
    eventSubtitle: eventSubtitle,
    eventImageUrl: event && event.imageUrl ? event.imageUrl : '',
    transactionId: String(tx.values[0] || ''),
    qty: Number(tx.values[6] || 1),
    amount: Number(tx.values[7] || 0),
    currency: String(tx.values[8] || 'INR'),
    eventStartAt: event && event.startAt ? event.startAt : '',
    eventEndAt: event && event.endAt ? event.endAt : '',
    createdAt: createdAt,
    paidAt: paidAt,
    qrUrl: String(tx.values[13] || ''),
    verificationUrl: String(tx.values[14] || ''),
    attendeeNames: attendeeNames,
    guestPasses: guestPasses
  });

  if (emailResult.ok) {
    updateTransactionColumns_(sheet, tx.row, {
      emailStatus: `Sent(Test:${recipient})`,
      emailSentAt: new Date()
    });
  }

  return {
    ok: emailResult.ok,
    transactionId: String(tx.values[0] || ''),
    eventId: eventId,
    subject: `Your Event Pass - ${eventTitle}`,
    message: emailResult.ok ? 'Test ticket email sent.' : (emailResult.message || ''),
    error: emailResult.ok ? '' : (emailResult.error || 'EMAIL_SEND_FAILED')
  };
}

function sendTestEventEmailNow(recipient, preferredEventId) {
  return sendTestEventEmail_(recipient, preferredEventId || 'paid-test-2026');
}

function sendParinTestEventEmail() {
  return sendTestEventEmail_('parin11@gmail.com', 'paid-test-2026');
}

function sendBulkTestEventEmails(recipients, preferredEventId) {
  const list = Array.isArray(recipients)
    ? recipients
    : String(recipients || '').split(/[\n,;]+/).map((item) => String(item || '').trim()).filter((item) => item);

  const eventId = String(preferredEventId || 'paid-test-2026').trim();
  const results = list.map((email) => {
    const result = sendTestEventEmail_(email, eventId);
    return {
      email: email,
      ok: !!result.ok,
      transactionId: result.transactionId || '',
      message: result.message || '',
      error: result.error || ''
    };
  });

  return {
    ok: results.every((item) => item.ok),
    eventId: eventId,
    results: results
  };
}

function sendDevanshiAndDcubeTestEventEmail() {
  return sendBulkTestEventEmails([
    'devanshi.shah@gmail.com',
    'dcube95@gmail.com'
  ], 'paid-test-2026');
}