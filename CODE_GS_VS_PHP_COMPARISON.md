# Code.gs vs PHP Backend — Full Comparison

> **Generated from:** `appscript/Code.gs` (~7500 lines) and `backend/src/Routes/ActionRouter.php`  
> **PHP Backend port:** `localhost:8010` | **Frontend port:** `localhost:5500`

---

## 1. Data Architecture — Does Excel/Sheet Data Appear on the Frontend?

```
Excel File (manual source)
        │
        │  (manually pasted / imported into)
        ▼
Google Sheets  ◄──────────────────────────────────────────────────────────────
(Apps Script)       Apps Script reads/writes these sheets directly:            │
                    - Leads sheet              - AWGNK MENU sheet              │
                    - Events sheet             - BAR MENU NK sheet             │
                    - Event Transactions       - Users sheet                   │
                    - QR Scans sheet           - Admin/Super Cash Ledger        │
        │                                                                       │
        │  import_sheets_to_mysql.php                                          │
        │  (run manually or on schedule)                                        │
        ▼                                                                       │
    MySQL DB     ◄──────────────────────────────────────────────────────────────
(PHP Backend)       PHP reads/writes MySQL directly (serves same actions)
        │
        │  API calls (fetch/POST to localhost:8010)
        ▼
   Frontend
(localhost:5500)
   menu.html, admin-portal.html, cocktail.html, etc.
```

### Key Facts

| Question | Answer |
|----------|--------|
| Does Excel data go to database? | **Not automatically.** Excel → Google Sheet is manual. Sheet → MySQL requires running `import_sheets_to_mysql.php`. |
| Does the frontend read from Sheets? | **No.** Frontend always calls the PHP backend (`localhost:8010`), which reads MySQL. |
| Does the frontend reflect Google Sheet changes? | **Only after sync.** Editing a menu item in Google Sheets will NOT change what is displayed on the website until you sync to MySQL. |
| Does the admin portal (menu editor) write to Sheets or MySQL? | **MySQL only** (via PHP backend). The Apps Script version also exists but is a separate backend. |
| Are there two separate backends? | **Yes.** Apps Script (Google Sheets-backed) and PHP (MySQL-backed) implement the SAME API actions. Use one or the other, not both simultaneously. |

---

## 2. GET Actions Comparison

Actions routed through `handleLeadGetAction(params)` in Code.gs and `$getActions[]` in PHP ActionRouter.

| Action Name | Code.gs Function | PHP Controller::Method | Status |
|-------------|-----------------|----------------------|--------|
| `auth_bootstrap_status` | `getAuthBootstrapStatus_()` | `AuthController::bootstrapStatus` | ✅ Both |
| `auth_list_users` | `handleAuthListUsers_(params)` | `AuthController::listUsers` | ✅ Both |
| `auth_me` | `handleAuthMe_(params)` | *(also in POST)* | ✅ Both |
| `events_list` / `event_list` | `getActiveEvents_()` | `EventController::eventsList` | ✅ Both |
| `event_popup` | `getActiveEvents_()` → filter | `EventController::eventPopup` | ✅ Both |
| `event_detail` | `getEventRecords_()` → filter | `EventController::eventDetail` | ✅ Both |
| `event_guest_report` | `getEventGuestReport_()` | `EventController::eventGuestReport` | ✅ Both |
| `event_transactions_report` | `getEventTransactionsReport_()` | `EventController::eventTransactionsReport` | ✅ Both |
| `admin_list_events` / `admin_event_list` | `getAdminEventRecords_()` | `EventController::adminListEvents` | ✅ Both |
| `admin_cash_summary` | `getAdminCashSummary_()` | `CashierController::adminCashSummary` | ✅ Both |
| `superadmin_cash_dashboard` | `getSuperadminCashDashboard_()` | `CashierController::superadminCashDashboard` | ✅ Both |
| `verify` | `findLeadByPhone_()` | `LeadController::verify` | ✅ Both |
| `redeem` | sheet update | `LeadController::redeem` | ✅ Both |
| `regen_coupon` / `regenerate_coupon` / `regen-coupon` | `ensureCouponCodeForRow_()` | `LeadController::regenCoupon` | ✅ Both |
| `counter` | row count from Leads sheet | `LeadController::counter` | ✅ Both |
| `qr_report` | `getQrScanReport_()` | `LeadController::qrReport` | ✅ Both |
| `init_schema` / `schema` | Leads sheet headers | `LeadController::initSchema` | ✅ Both |
| `ensure_qr_sheet` / `init_qr_sheet` / `create_qr_sheet` | `getOrCreateQrScansSheet_()` | `LeadController::ensureQrSheet` | ✅ Both |
| `add_test_lead` / `add-test-lead` | `appendManualLeadRow_()` | `LeadController::addTestLead` | ✅ Both |
| `add_test_qr_scan` / `test_qr_scan` | `createTestQrScanEntry()` | `LeadController::addTestQrScan` | ✅ Both |
| `add_test_25_coupon` / `test_25_coupon` | `createTestEntry25Coupon()` | `LeadController::addTest25Coupon` | ✅ Both |
| `sync_crm_by_phone` / `sync-crm-by-phone` | `pushLeadToCrm_()` | `LeadController::syncCrmByPhone` | ✅ Both |
| `create_test_paid_tx` / `seed_test_paid_tx` | `createTestPaidTransaction_()` | `UtilityController::createTestPaidTx` | ✅ Both |
| `download_qr_code` | `downloadQrCodeImage_()` | `UtilityController::downloadQrCode` | ✅ Both |
| `qr_scan_report_html` / `qr-scan-report-html` | `buildQrScanReportHtml_()` | `UtilityController::qrScanReportHtml` | ✅ Both |
| `migrate_events_sheet_format` / `migrate_event_sheet_format` | `migrateEventsSheetFormat_()` | `UtilityController::migrateEventsSheetFormat` | ✅ Both |
| `reset_events_sheet_format` / `reset_events_data` | `resetEventsSheetData_()` | `UtilityController::resetEventsSheetFormat` | ✅ Both |
| `seed_events_sample` / `seed_event_sample` | `seedSampleEventRow_()` | `UtilityController::seedEventsSample` | ✅ Both |
| `seed_dj_events_apr_2026` / `seed_dj_events` | `seedDjEventsApr2026_()` | `UtilityController::seedDjEvents` | ✅ Both |
| `seed_paid_event_sample` / `seed_paid_event` | `seedPaidEventSample_()` | `UtilityController::seedPaidEventSample` | ✅ Both |
| `send_test_event_email` / `test_event_email` | `sendTestEventEmail_()` | `UtilityController::sendTestEventEmail` | ✅ Both |
| *(tab-based sheet read)* | `doGet(e)` with `?tab=` | `MenuController::getTab($query)` with `?tab=` | ✅ Both |

---

## 3. POST Actions Comparison

Actions routed through `doPost(e)` in Code.gs and `$postActions[]` in PHP ActionRouter.

### Auth Actions

| Action Name | Code.gs Function | PHP Controller::Method | Status |
|-------------|-----------------|----------------------|--------|
| `auth_login` | `handleAuthLogin_(data)` line 1237 | `AuthController::login` | ✅ Both |
| `auth_logout` | `handleAuthLogout_(data)` line 1300 | `AuthController::logout` | ✅ Both |
| `auth_me` | `handleAuthMe_(data)` line 1317 | `AuthController::me` | ✅ Both |
| `auth_change_password` | `handleAuthChangePassword_(data)` line 1331 | `AuthController::changePassword` | ✅ Both |
| `auth_create_user` | `handleAuthCreateUser_(data)` line 1367 | `AuthController::createUser` | ✅ Both |
| `auth_set_user_status` | `handleAuthSetUserStatus_(data)` line 1431 | `AuthController::setUserStatus` | ✅ Both |
| `auth_reset_password` | `handleAuthResetPassword_(data)` line 1469 | `AuthController::resetPassword` | ✅ Both |
| `auth_set_user_permissions` | `handleAuthSetUserPermissions_(data)` line 1506 | `AuthController::setUserPermissions` | ✅ Both |
| `auth_get_api_settings` | `handleAuthGetApiSettings_(data)` line 1594 | `AuthController::getApiSettings` | ✅ Both |
| `auth_set_api_settings` | `handleAuthSetApiSettings_(data)` line 1607 | `AuthController::setApiSettings` | ✅ Both |
| `auth_list_users` | `handleAuthListUsers_(data)` line 1676 | `AuthController::listUsers` | ✅ Both |
| `auth_get_app_settings` | ❌ **NOT IN Code.gs** | `AuthController::getAppSettings` | ⚠️ PHP Only |
| `auth_set_app_settings` | ❌ **NOT IN Code.gs** | `AuthController::setAppSettings` | ⚠️ PHP Only |

### Event Actions (Writes)

| Action Name | Code.gs Function | PHP Controller::Method | Status |
|-------------|-----------------|----------------------|--------|
| `register_free_event` | `handleRegisterFreeEvent_(data)` line 5414 | `EventController::registerFreeEvent` | ✅ Both |
| `create_event_order` | `handleCreateEventOrder_(data)` line 5547 | `EventController::createEventOrder` | ✅ Both |
| `confirm_event_payment` | `handleConfirmEventPayment_(data)` line 5702 | `EventController::confirmEventPayment` | ✅ Both |
| `resend_event_confirmation` | `handleResendEventConfirmation_(data)` line 5721 | `EventController::resendEventConfirmation` | ✅ Both |
| `request_event_cancellation` | `handleEventCancellationRequest_(data)` line 5806 | `EventController::requestEventCancellation` | ✅ Both |
| `verify_event_qr` | `handleVerifyEventQr_()` | `EventController::verifyEventQr` | ✅ Both |
| `admin_preview_event_qr` | `handleAdminPreviewEventQr_()` | `EventController::adminPreviewEventQr` | ✅ Both |
| `admin_batch_checkin_event_qr` | `handleAdminBatchCheckinEventQr_()` | `EventController::adminBatchCheckin` | ✅ Both |
| `admin_create_event` | `handleAdminCreateEvent_(data)` line 3703 | `EventController::adminCreateEvent` | ✅ Both |
| `admin_update_event` | `handleAdminUpdateEvent_(data)` line 3722 | `EventController::adminUpdateEvent` | ✅ Both |
| `admin_toggle_event` | `handleAdminToggleEvent_(data)` line 3750 | `EventController::adminToggleEvent` | ✅ Both |

### Menu Editor Actions

| Action Name | Code.gs Function | PHP Controller::Method | Status |
|-------------|-----------------|----------------------|--------|
| `admin_menu_editor_load` | `handleAdminMenuEditorLoad_(data)` line 3970 | `MenuController::load` | ✅ Both |
| `admin_menu_editor_save_changes` | `handleAdminMenuEditorSaveChanges_(data)` line 4022 | `MenuController::saveChanges` | ✅ Both |
| `admin_menu_editor_add_row` | `handleAdminMenuEditorAddRow_(data)` line 4133 | `MenuController::addRow` | ✅ Both |
| `admin_menu_editor_delete_rows` | `handleAdminMenuEditorDeleteRows_(data)` line 4220 | `MenuController::deleteRows` | ✅ Both |
| `admin_menu_editor_set_visibility` | `handleAdminMenuEditorSetVisibility_(data)` line 4267 | `MenuController::setVisibility` | ✅ Both |
| `admin_menu_designer_load` | ❌ **NOT IN Code.gs** | `MenuController::designerLoad` | ⚠️ PHP Only |
| `admin_menu_designer_save_category_order` | ❌ **NOT IN Code.gs** | `MenuController::designerSaveCategoryOrder` | ⚠️ PHP Only |
| `admin_menu_designer_save_item_order` | ❌ **NOT IN Code.gs** | `MenuController::designerSaveItemOrder` | ⚠️ PHP Only |
| `admin_menu_designer_toggle_category` | ❌ **NOT IN Code.gs** | `MenuController::designerToggleCategory` | ⚠️ PHP Only |
| `admin_menu_designer_toggle_item` | ❌ **NOT IN Code.gs** | `MenuController::designerToggleItem` | ⚠️ PHP Only |

### Cashier Actions

| Action Name | Code.gs Function | PHP Controller::Method | Status |
|-------------|-----------------|----------------------|--------|
| `admin_issue_cash_paid_pass` | `handleAdminIssueCashPaidPass_(data)` | `CashierController::issueCashPaidPass` | ✅ Both |
| `admin_request_cash_handover` | `handleAdminRequestCashHandover_(data)` | `CashierController::requestCashHandover` | ✅ Both |
| `admin_request_cash_cancel` | `handleAdminRequestCashCancel_(data)` | `CashierController::requestCashCancel` | ✅ Both |
| `superadmin_approve_cash_handover` | `handleSuperadminApproveCashHandover_(data)` | `CashierController::approveCashHandover` | ✅ Both |
| `superadmin_resolve_cash_cancel` | `handleSuperadminResolveCashCancel_(data)` | `CashierController::resolveCashCancel` | ✅ Both |

### Lead / Spin & Win Actions (POST)

| Action Name | Code.gs Function | PHP Controller::Method | Status |
|-------------|-----------------|----------------------|--------|
| `submit_lead` | *Implicit fallback at bottom of `doPost`* | `LeadController::submitLead` | ⚠️ Code.gs: fallback only; PHP: explicit route |
| `qr_scan_client` | `handleQrScanClientTracking_(data)` line 2726 | `LeadController::qrScanClient` | ✅ Both |
| `add_test_qr_scan` / `test_qr_scan` | *(GET only in Code.gs)* | `LeadController::addTestQrScan` *(POST in PHP)* | ⚠️ Mismatch: GET in Code.gs, POST in PHP |
| `add_test_25_coupon` / `test_25_coupon` | *(GET only in Code.gs)* | `LeadController::addTest25Coupon` *(POST in PHP)* | ⚠️ Mismatch: GET in Code.gs, POST in PHP |
| `add_test_lead` / `add-test-lead` | *(GET only in Code.gs)* | `LeadController::addTestLead` *(POST in PHP)* | ⚠️ Mismatch: GET in Code.gs, POST in PHP |
| `sync_crm_by_phone` / `sync-crm-by-phone` | *(GET only in Code.gs)* | `LeadController::syncCrmByPhone` *(POST in PHP)* | ⚠️ Mismatch: GET in Code.gs, POST in PHP |

### Webhook Actions

| Action Name | Code.gs Function | PHP Controller::Method | Status |
|-------------|-----------------|----------------------|--------|
| `razorpay_webhook` | `handleRazorpayWebhook_(e)` line 5044 | `WebhookController::razorpayWebhook` | ✅ Both |

---

## 4. PHP-Only Actions (No Code.gs Equivalent)

These actions exist in the PHP backend but have **no handler in Code.gs**. They are PHP extensions of the API.

| Action | PHP Controller::Method | Description |
|--------|----------------------|-------------|
| `auth_get_app_settings` (POST) | `AuthController::getAppSettings` | App-level settings read (distinct from api_settings) |
| `auth_set_app_settings` (POST) | `AuthController::setAppSettings` | App-level settings write |
| `admin_menu_designer_load` (POST) | `MenuController::designerLoad` | Drag-and-drop menu designer — load layout |
| `admin_menu_designer_save_category_order` (POST) | `MenuController::designerSaveCategoryOrder` | Save reordered categories |
| `admin_menu_designer_save_item_order` (POST) | `MenuController::designerSaveItemOrder` | Save reordered items within a category |
| `admin_menu_designer_toggle_category` (POST) | `MenuController::designerToggleCategory` | Toggle category visibility in designer |
| `admin_menu_designer_toggle_item` (POST) | `MenuController::designerToggleItem` | Toggle item visibility in designer |

---

## 5. Code.gs-Only Behavior (No PHP Equivalent)

These are Code.gs behaviors that don't have a direct PHP equivalent action:

| Behavior | Code.gs Location | Notes |
|----------|-----------------|-------|
| QR scan URL tracking (`?qr=track`) | `doGet(e)` → `handleQrScanTracking_()` | Server-side QR scan counter using userAgent/IP. PHP would need a separate handler. |
| Raw `submit_lead` fallback | Bottom of `doPost(e)` | In Code.gs, any POST with name+phone falls through to lead creation even without `action: 'submit_lead'`. PHP requires the explicit `submit_lead` action. |
| `createTestEntry20Coupon()` | line 1025 | Apps Script dev/console helper — no HTTP route in either. |
| `createTestEntry25Coupon()` | line 1048 | Available via GET action `add_test_25_coupon`; no PHP POST route. |
| Spreadsheet timezone enforcement | `ensureSpreadsheetTimezone_()` line 3100 | Auto-runs on sheet init; no HTTP route. |
| QR scan email notification | `sendQrScanNotificationEmail_()` line 2805 | Fires automatically on QR scan; no frontend action needed. |
| CRM push on lead submit | `pushLeadToCrm_()` line 2350 | Fires automatically during lead submission; PHP may handle this internally. |

---

## 6. Code.gs Internal Function Inventory

These are internal helper functions — not API endpoints. Listed by domain group.

### Entry Points
| Function | Line | Purpose |
|----------|------|---------|
| `doGet(e)` | 154 | HTTP GET dispatcher: tab reads, QR tracking, GET actions |
| `doPost(e)` | 240 | HTTP POST dispatcher: all mutation actions + lead submit fallback |
| `handleLeadGetAction(params)` | 448 | Routes all GET `?action=` calls |

### Auth System
| Function | Line | Purpose |
|----------|------|---------|
| `handleAuthLogin_(data)` | 1237 | Validates credentials, issues JWT |
| `handleAuthLogout_(data)` | 1300 | Revokes token |
| `handleAuthMe_(data)` | 1317 | Returns current user |
| `handleAuthChangePassword_(data)` | 1331 | Self-service password change |
| `handleAuthCreateUser_(data)` | 1367 | Superadmin creates new user |
| `handleAuthSetUserStatus_(data)` | 1431 | Enable/disable user |
| `handleAuthResetPassword_(data)` | 1469 | Admin-forced password reset |
| `handleAuthSetUserPermissions_(data)` | 1506 | Edit user permissions |
| `handleAuthGetApiSettings_(data)` | 1594 | Read script/API settings |
| `handleAuthSetApiSettings_(data)` | 1607 | Write script/API settings |
| `handleAuthListUsers_(data)` | 1676 | List all users |
| `getAuthBootstrapStatus_()` | 1216 | Returns whether superadmin exists |
| `authorizeAdminRequest_(requestData, requiredRole)` | 1699 | Validates JWT + role for admin routes |
| `getOrCreateUsersSheet_()` | 1752 | Ensures Users sheet exists |
| `getOrCreateAuthAuditSheet_()` | 1769 | Ensures Auth Audit sheet exists |
| `getOrCreateAuthRevokedTokensSheet_()` | 1786 | Ensures Revoked Tokens sheet exists |
| `revokeAuthToken_(token, tokenPayload)` | 1803 | Writes token to revoked list |
| `isAuthTokenRevoked_(token)` | 1819 | Checks revocation list |
| `logAuthAudit_(action, username, outcome, details)` | 1844 | Writes to audit log |
| `ensureBootstrapSuperAdminUser_()` | 1860 | Creates default superadmin on first run |
| `findUserByUsername_(sheet, username)` | 1902 | Row lookup |
| `userFromSheetRow_(row)` | 1921 | Converts row array → user object |
| `toPublicUser_(user)` | 1943 | Strips sensitive fields |
| `normalizeUserPermissions_(value, role, useDefaultIfEmpty)` | 1960 | Parses permission map |
| `normalizePermissionKey_(value)` | 1997 | Normalizes e.g. `menu_editor` |
| `serializeUserPermissions_(permissions)` | 2032 | JSON-stringifies for sheet storage |
| `userHasPermission_(user, permissionKey)` | 2037 | Boolean permission check |
| `hasUserPermissionForAction_(user, action)` | 2054 | Action-level permission gate |
| `setUserPassword_(sheet, row, password, ...)` | 2100 | Hashes + stores password |
| `registerFailedLoginAttempt_(sheet, row, user)` | 2112 | Increments lockout counter |
| `clearFailedLoginAttemptState_(sheet, row, actor)` | 2137 | Resets lockout state |
| `validatePasswordPolicy_(password)` | 2144 | Length/complexity check |
| `normalizeUsernameMobile_(value)` | 2155 | Strips spaces/dashes from phone |
| `normalizeUserRole_(value)` | 2162 | `admin` / `superadmin` / `cashier` |
| `hasRoleAccess_(actualRole, requiredRole)` | 2167 | Role hierarchy check |
| `isAccountLocked_(lockoutUntil)` | 2174 | Checks lockout timestamp |
| `formatLockoutDate_(lockoutUntil)` | 2181 | Human-readable lockout time |
| `generatePasswordSalt_()` | 2187 | Random salt for hashing |
| `hashPassword_(password, salt)` | 2191 | SHA-256 based hash |
| `digestSha256Hex_(value)` | 2199 | Raw SHA-256 hex |
| `bytesToHex_(bytes)` | 2204 | Byte array → hex string |
| `extractAuthToken_(requestData)` | 2212 | Gets Bearer token from request |
| `getAuthJwtSecret_()` | 2222 | Reads JWT secret from Script Properties |
| `buildAuthToken_(user)` | 2228 | Signs and returns JWT |
| `verifyAuthToken_(token)` | 2251 | Validates JWT signature + expiry |
| `encodeTokenPart_(obj)` | 2289 | Base64url encode |
| `decodeTokenPart_(encoded)` | 2293 | Base64url decode |
| `hmacSha256Base64Url_(content, secret)` | 2298 | HMAC-SHA256 for JWT signing |
| `getManagedScriptSettingDefMap_()` | 1541 | Defines all configurable settings |
| `maskScriptSettingValue_(value, isSecret)` | 1556 | Masks secret values in API responses |
| `getManagedScriptSettingsView_()` | 1571 | Returns settings with masked values |

### Lead / Spin & Win System
| Function | Line | Purpose |
|----------|------|---------|
| `getOrCreateLeadsSheet_()` | 970 | Ensures Leads sheet + headers |
| `ensureLeadsSheetHeaders_(sheet, expectedHeaders)` | 988 | Adds missing header columns |
| `computePrizeByRow_(nextRow)` | 997 | Assigns prize based on row modulo |
| `createTestEntry20Coupon()` | 1025 | Dev console: inserts 20% coupon entry |
| `createTestEntry25Coupon()` | 1048 | Dev console: inserts 25% coupon entry |
| `incrementVisitCount_(sheet, rowNumber, currentVisitCount)` | 1073 | Bumps visit counter on duplicate |
| `updateCrmSyncColumns_(sheet, rowNumber, crmSync)` | 1081 | Writes CRM sync result columns |
| `findLeadByPhone_(sheet, phone)` | 1101 | Searches Leads by phone number |
| `appendManualLeadRow_(sheet, data)` | 2431 | Manually inserts lead row |
| `generateCouponCode_(rowNumber, prize, phone)` | 2585 | Generates unique coupon string |
| `ensureCouponCodeForRow_(sheet, rowInfo)` | 2605 | Creates coupon if missing |
| `isWinnerPrize_(prize)` | 2527 | Checks if prize is a winner |
| `normalizePrizeLabel_(prize)` | 2532 | Normalizes prize name |
| `isAdminProtectedAction_(action)` | 2554 | Returns true for admin-only actions |

### CRM Integration
| Function | Line | Purpose |
|----------|------|---------|
| `buildCrmLeadPayload_(lead)` | 2328 | Constructs CRM API request body |
| `pushLeadToCrm_(payload)` | 2350 | POSTs to external CRM API |
| `executeCrmAttempt_(crmRequestPayload)` | 2395 | Single CRM attempt with retry logic |
| `toPlusInternationalPhone_(value)` | 2422 | Formats `+91NNNNNNNNNN` |
| `getCrmApiToken_()` | 2427 | Reads CRM token from Script Properties |
| `normalizePhoneDigits_(value)` | 2303 | Strips to digits only |
| `normalizeCountryCode_(value)` | 2307 | Extracts country code |
| `formatInternationalPhone_(countryCode, localPhone)` | 2312 | Combines into full E.164 |
| `isValidPhoneForCountry_(localPhone, countryCode)` | 2320 | Length/format validation |

### QR Scan Tracking
| Function | Line | Purpose |
|----------|------|---------|
| `getOrCreateQrScansSheet_()` | 2666 | Ensures QR Scans sheet |
| `handleQrScanTracking_(userAgent, referer, remoteAddr)` | 2684 | Server-side scan logger |
| `handleQrScanClientTracking_(data)` | 2726 | Client-reported scan logger |
| `lookupIpLocation_(ipAddress)` | 2763 | IP geolocation via external API |
| `isPublicIp_(ip)` | 2790 | Filters out private IPs |
| `sendQrScanNotificationEmail_(totalScans)` | 2805 | Fires email alert at milestones |
| `buildQrEmailTemplate_(totalScans)` | 2823 | HTML email for QR alerts |
| `getQrScanReport_()` | 2877 | Returns scan stats |
| `buildQrScanReportHtml_()` | 2899 | Generates HTML report page |
| `createQrScansSheet()` | 2620 | Dev console: creates QR sheet manually |
| `createTestQrScanEntry()` | 2631 | Dev console: inserts test scan |
| `safeText_(value)` | 2801 | Sanitizes text for sheet storage |

### Events System
| Function | Line | Purpose |
|----------|------|---------|
| `getOrCreateEventsSheet_()` | 3111 | Ensures Events sheet |
| `ensureEventsSheetStructure_(sheet)` | 3128 | Validates event sheet columns |
| `ensureEventsSheetHeaders_(sheet, expectedHeaders)` | 3148 | Adds missing headers |
| `getEventRecords_()` | 3157 | Reads all events (incl. inactive) |
| `getActiveEvents_()` | 3180 | Reads active events only |
| `normalizeEventRecord_(headers, row, rowNumber)` | 3198 | Row array → event object |
| `pickEventValue_(map, keys)` | 3265 | Multi-key header lookup |
| `normalizeHeaderKey_(value)` | 3274 | Lowercases + strips spaces |
| `cleanText_(value)` | 3278 | Trims whitespace |
| `toBoolean_(value, defaultValue)` | 3282 | YES/1/true → boolean |
| `toNumber_(value, defaultValue)` | 3288 | Safe numeric parse |
| `normalizeTimeDisplayFormat_(value)` | 3293 | `12h` / `24h` enum |
| `normalizeEventSheetTimeText_(value)` | 3299 | Parses flexible time strings |
| `parseEventQrScanText_(scanText)` | 3305 | Decodes QR scan payload |
| `parseEventTimeParts_(value)` | 3356 | Splits `HH:MM` |
| `parseEventDateParts_(dateValue, timeValue)` | 3390 | Builds Date from parts |
| `parseEventDate_(value)` | 3432 | Parses ISO or display dates |
| `formatEventSheetDate_(value)` | 3481 | Date → `YYYY-MM-DD` |
| `formatEventSheetTime_(value)` | 3487 | Date → `HH:MM` |
| `buildEventSheetRow_(event)` | 3493 | Event object → row array |
| `ensureSpreadsheetTimezone_(spreadsheet)` | 3100 | Sets IST timezone |

### Admin Events
| Function | Line | Purpose |
|----------|------|---------|
| `getAdminEventRecords_()` | 3529 | All events for admin panel |
| `findEventRowInfoById_(eventId)` | 3544 | Finds event row by ID |
| `buildAdminEventPayload_(data, existingRecord, options)` | 3616 | Validates + normalizes 28-field event |
| `handleAdminCreateEvent_(data)` | 3703 | Creates new event row |
| `handleAdminUpdateEvent_(data)` | 3722 | Updates existing event row |
| `handleAdminToggleEvent_(data)` | 3750 | Flips isActive flag |
| `requestValueByKeys_(data, keys, fallbackValue)` | 3572 | Multi-key field lookup |
| `slugifyEventId_(value)` | 3582 | Title → URL-safe ID |
| `eventIdExists_(eventId)` | 3591 | Uniqueness check |
| `generateAdminEventId_(title)` | 3595 | Auto-generates slug ID |
| `normalizeAdminEventType_(value)` | 3606 | `paid` / `free` enum |
| `normalizeNonNegativeNumber_(value, ...)` | 3610 | Clamps to ≥0 |

### Menu Editor
| Function | Line | Purpose |
|----------|------|---------|
| `handleAdminMenuEditorLoad_(data)` | 3970 | Returns sheet headers + items |
| `handleAdminMenuEditorSaveChanges_(data)` | 4022 | Batch cell updates |
| `handleAdminMenuEditorAddRow_(data)` | 4133 | Appends blank row |
| `handleAdminMenuEditorDeleteRows_(data)` | 4220 | Deletes by row numbers |
| `handleAdminMenuEditorSetVisibility_(data)` | 4267 | Shows/hides items |
| `buildMenuEditorEditableMeta_(sheetName, headers)` | 3825 | Returns `{header → type}` map |
| `buildMenuEditorHeaderIndexMap_(headers)` | 3899 | Returns `{header → colIndex}` |
| `ensureMenuEditorVisibilityColumn_(sheet, headers)` | 3909 | Adds Availability column if missing |
| `getMenuEditorSheetOrThrow_(requestedSheetName)` | 3932 | Sheet lookup or throws |
| `normalizeMenuEditorVisibilityValue_(visible)` | 3951 | `YES` / `NO` string |
| `resolveMenuEditorRequestedSheet_(data)` | 3955 | Resolves sheet from request |
| `normalizeMenuEditorHeaderKey_(value)` | 3783 | Lowercases + strips spaces |
| `normalizeMenuEditorSheetName_(value)` | 3787 | `AWGNK MENU` / `BAR MENU NK` |
| `isMenuEditorPriceColumn_(sheetName, normalizedHeaderKey)` | 3801 | Detects price columns by regex |
| `isValidMenuEditorNumeric_(value)` | 3819 | Validates price value |
| `normalizeMenuEditorChefSpecialValue_(value)` | 3877 | `YES` / `NO` |
| `normalizeMenuEditorSpiceLevelValue_(value)` | 3881 | `Mild`/`Medium`/`Hot`/`Extra Hot`/`None` |

### Event Transactions & Payments
| Function | Line | Purpose |
|----------|------|---------|
| `getOrCreateEventTransactionsSheet_()` | 4422 | Ensures Transactions sheet |
| `getEventById_(eventId)` | 4480 | Finds event by ID from active events |
| `findTransactionByOrderId_(sheet, orderId)` | 4608 | Looks up by Razorpay orderId |
| `findTransactionById_(sheet, transactionId)` | 4622 | Looks up by transactionId |
| `findLatestDuplicateEventRegistration_(...)` | 4636 | Prevents double-booking |
| `buildAlreadyRegisteredResponse_(tx, event, actionName)` | 4668 | Standard duplicate response |
| `updateTransactionColumns_(sheet, row, updates)` | 5665 | Named-column updater |
| `finalizeEventPaymentByOrderId_(orderId, paymentId, options)` | 4893 | Marks payment as Paid, sends email |
| `buildEventQrPayload_(transactionId, eventId, paymentId)` | 5109 | Generates QR URL + signature |
| `buildGuestEventQrPayload_(transactionId, eventId, paymentId, guestId)` | 5113 | Per-guest QR payload |
| `normalizeGuestPasses_(value)` | 5141 | Parses JSON guest pass list |
| `buildGuestPasses_(transactionId, eventId, paymentId, ...)` | 5158 | Creates guest pass array |
| `serializeGuestPasses_(guestPasses)` | 5178 | JSON-stringifies for sheet |
| `getGuestPassesFromTransactionRow_(rowValues)` | 5182 | Parses from sheet row |
| `buildGuestPassEmailBlocks_(guestPasses)` | 5188 | HTML blocks for ticket email |
| `validateEventQrSignature_(...)` | 5852 | Single QR HMAC check |
| `validateGuestEventQrSignature_(...)` | 5856 | Per-guest QR HMAC check |
| `buildNormalizedEventQrRequest_(requestData)` | 5867 | Normalizes scan payload |
| `evaluateEventQrRequest_(requestData, options)` | 5879 | Core check-in logic (preview/commit) |
| `handleVerifyEventQr_()` | ~6090 | Calls evaluateEventQrRequest_ with commit:true |
| `handleAdminPreviewEventQr_()` | ~6097 | Calls with commit:false |
| `handleAdminBatchCheckinEventQr_()` | ~6104 | Batch QR check-in |

### Razorpay Integration
| Function | Line | Purpose |
|----------|------|---------|
| `getRazorpayCredentials_()` | 4710 | Reads key/secret from Script Properties |
| `getRazorpayWebhookConfig_()` | 4717 | Reads webhook secret |
| `buildHexHmacSha256_(payload, secret)` | 4724 | HMAC for webhook verification |
| `verifyRazorpayWebhookSignature_(rawBody, signature)` | 4732 | Validates incoming webhook |
| `fetchRazorpayApi_(path, method, payload)` | 4738 | Generic Razorpay API caller |
| `fetchRazorpayPayment_(paymentId)` | 4779 | Gets payment object |
| `fetchRazorpayOrderPayments_(orderId)` | 4787 | Gets all payments for order |
| `getLatestCapturedRazorpayOrderPayment_(orderId)` | 4795 | Latest captured payment |
| `verifyRazorpayPaymentState_(orderId, paymentId, ...)` | 4810 | State machine check |
| `createRazorpayOrder_(amountInPaise, currency, receipt)` | 4839 | Creates Razorpay order |
| `verifyRazorpaySignature_(orderId, paymentId, signature)` | 4883 | Frontend payment verification |
| `handleRazorpayWebhook_(e)` | 5044 | Routes webhook events |
| `parseRazorpayWebhookRequest_(e)` | 5005 | Parses webhook body |
| `extractRazorpayWebhookRefs_(payload)` | 5029 | Gets orderId/paymentId from webhook |
| `toBasicAuthHeader_(user, pass)` | 4705 | `Basic base64(user:pass)` |

### Email
| Function | Line | Purpose |
|----------|------|---------|
| `sendEventTicketEmail_(email, payload)` | 5210 | Sends ticket email with QR |
| `buildEventTicketEmailHtml_(payload)` | 5277 | Full HTML ticket template |
| `buildGuestPassEmailBlocks_(guestPasses)` | 5188 | Per-guest QR sections |
| `formatEventDateForEmail_(value)` | 5375 | Human-readable date |
| `escapeHtml_(value)` | 5381 | HTML entity encoding |
| `pushPaidEventToCrm_(payload)` | 5390 | CRM push for paid registrations |
| `sendTestEventEmail_(recipient, preferredEventId)` | ~7200 | Sends test ticket email |
| `sendTestEventEmailNow(recipient, preferredEventId)` | ~7300 | Dev console shortcut |
| `sendParinTestEventEmail()` | ~7310 | Hardcoded test recipient |
| `sendBulkTestEventEmails(recipients, preferredEventId)` | ~7320 | Dev bulk test emails |
| `sendDevanshiAndDcubeTestEventEmail()` | ~7340 | Hardcoded test recipients |

### Cashier / Cash Ledger
| Function | Line | Purpose |
|----------|------|---------|
| `getOrCreateAdminCashLedgerSheet_()` | 4487 | Ensures Admin Cash Ledger sheet |
| `getOrCreateSuperadminCashLedgerSheet_()` | 4525 | Ensures Superadmin Cash Ledger sheet |
| `getLedgerDateKey_(value)` | 4556 | `YYYY-MM-DD` ledger date |
| `buildCashBatchKey_(adminUsername, ledgerDate)` | 4564 | `admin::date` batch identifier |
| `findSuperadminCashLedgerRow_(sheet, batchKey)` | 4568 | Looks up batch in superadmin ledger |
| `appendAdminCashLedgerRow_(entry)` | 4582 | Appends to admin ledger |
| `getAdminCashSummary_(adminUsername, ledgerDate)` | ~6195 | Builds cash summary for cashier |
| `getSuperadminCashDashboard_(ledgerDate)` | ~6270 | Builds pending handovers + approvals |
| `handleAdminIssueCashPaidPass_(data)` | ~6340 | Issues cash-paid event pass |
| `handleAdminRequestCashHandover_(data)` | ~6450 | Marks batch as Requested |
| `handleAdminRequestCashCancel_(data)` | ~6540 | Marks tx as CancelRequested |
| `handleSuperadminApproveCashHandover_(data)` | ~6590 | Approves cash handover batch |
| `handleSuperadminResolveCashCancel_(data)` | ~6680 | Approves/rejects cancel request |

### Migration / Seeding Utilities
| Function | Line | Purpose |
|----------|------|---------|
| `migrateEventsSheetStructure_(sheet)` | 4318 | Migrates old event columns |
| `migrateEventsSheetFormat_(forceRewrite)` | 4348 | Full sheet format migration |
| `resetEventsSheetData_(includePaidSample)` | 4353 | Clears events and optionally re-seeds |
| `seedSampleEventRow_(forceSeed)` | 4373 | Seeds one sample event |
| `upsertEventRowById_(sheet, eventRow)` | ~6960 | Insert-or-update by event ID |
| `seedDjEventsApr2026_()` | ~6970 | Seeds 2 April 2026 DJ events |
| `seedPaidEventSample_(ticketPrice, maxTickets)` | ~7060 | Seeds a paid event for testing |
| `createTestPaidTransaction_(eventId, qty)` | ~7120 | Creates synthetic paid transaction |
| `getLatestPaidTransaction_(sheet, preferredEventId)` | ~7200 | Finds latest Paid row |
| `normalizeAttendeeNames_(value)` | ~7210 | Parses comma/newline-delimited names |
| `getEventGuestReport_(requestedEventId)` | ~6730 | Full guest + reconciliation report |
| `getEventTransactionsReport_()` | ~6155 | Summary + recent 20 transactions |

### Shared Utilities
| Function | Line | Purpose |
|----------|------|---------|
| `jsonResponse(payload)` | 1140 | Wraps payload in `ContentService` JSON response |
| `findSheetByNormalizedName(spreadsheet, requestedTab)` | 1146 | Case-insensitive sheet lookup |
| `normalizeSheetName(name)` | 1165 | Lowercases + strips spaces for comparison |
| `parseIncomingPostData_(e)` | 1173 | Parses POST body (JSON or form) |
| `normalizeDisplayDate_(value)` | 2477 | Parses flexible date input |
| `toIsoDateString_(value)` | 2494 | Any date → `YYYY-MM-DD` |
| `isValidDateParts_(year, month, day)` | 2509 | Calendar validity check |
| `isWithinHours_(timestampValue, hours)` | 2517 | Recency guard (spin-again cooldown) |

---

## 7. Summary of Gaps

### Missing from Code.gs (PHP has implemented extras)
- `auth_get_app_settings` / `auth_set_app_settings` — app-level config storage
- Full **Menu Designer** suite (5 actions) — drag-and-drop layout ordering system
  - `admin_menu_designer_load`
  - `admin_menu_designer_save_category_order`
  - `admin_menu_designer_save_item_order`
  - `admin_menu_designer_toggle_category`
  - `admin_menu_designer_toggle_item`

### HTTP Method Mismatches (test/utility actions)
| Action | Code.gs | PHP |
|--------|---------|-----|
| `add_test_qr_scan` | GET | POST |
| `add_test_25_coupon` | GET | POST |
| `add_test_lead` | GET | POST |
| `sync_crm_by_phone` | GET | POST |

> These are dev/test utilities. The mismatch is not critical for production, but any admin module calling these must use `apiGet` when targeting Apps Script and `apiPost` when targeting the PHP backend.

### Lead submission
- Code.gs: Any POST with `name` + `phone` fields — even without `action: 'submit_lead'` — is treated as a lead submission (implicit fallback).
- PHP: Requires explicit `action: 'submit_lead'`.
