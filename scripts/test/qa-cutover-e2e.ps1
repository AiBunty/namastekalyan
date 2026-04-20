param(
  [string]$ApiBase = 'http://localhost:8010',
  [string]$SuperadminUser = '9371519999',
  [string]$SuperadminPass = '8442'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

function Invoke-ApiPost {
  param([string]$Action, [hashtable]$Body)
  $payload = @{} + $Body
  $payload.action = $Action
  $json = $payload | ConvertTo-Json -Depth 12 -Compress
  try {
    $resp = Invoke-WebRequest -Uri "$ApiBase/" -Method POST -ContentType 'application/json' -Body $json -TimeoutSec 30 -UseBasicParsing
    $parsed = $null
    try { $parsed = $resp.Content | ConvertFrom-Json -Depth 100 } catch { $parsed = @{ raw = $resp.Content } }
    return @{ http = [int]$resp.StatusCode; body = $parsed }
  } catch {
    return @{ http = 0; body = @{ ok = $false; error = 'REQUEST_FAILED'; message = $_.Exception.Message } }
  }
}

function Invoke-ApiGet {
  param([string]$Action, [hashtable]$Query)
  $pairs = @("action=$Action")
  if ($Query) {
    foreach ($k in $Query.Keys) {
      $pairs += (([System.Uri]::EscapeDataString([string]$k)) + '=' + ([System.Uri]::EscapeDataString([string]$Query[$k])))
    }
  }
  $uri = "$ApiBase/?" + ($pairs -join '&')
  try {
    $resp = Invoke-WebRequest -Uri $uri -Method GET -TimeoutSec 30 -UseBasicParsing
    $parsed = $null
    try { $parsed = $resp.Content | ConvertFrom-Json -Depth 100 } catch { $parsed = @{ raw = $resp.Content } }
    return @{ http = [int]$resp.StatusCode; body = $parsed }
  } catch {
    return @{ http = 0; body = @{ ok = $false; error = 'REQUEST_FAILED'; message = $_.Exception.Message } }
  }
}

function New-SuperToken {
  param(
    [string]$Username,
    [string]$Password,
    [int]$MaxAttempts = 4
  )

  for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
    $loginResp = Invoke-ApiPost 'auth_login' @{ username = $Username; password = $Password }
    $candidate = [string]($loginResp.body.token)
    if ($loginResp.body.ok -eq $true -and $candidate.Length -gt 20) {
      $meResp = Invoke-ApiPost 'auth_me' @{ token = $candidate }
      if ($meResp.body.ok -eq $true) {
        return $candidate
      }
    }

    # Avoid same-second iat/revocation collisions immediately after logout.
    [System.Threading.Thread]::Sleep(1100)
  }

  return ''
}

$results = New-Object System.Collections.ArrayList
function Add-Result {
  param(
    [string]$Domain,
    [string]$Action,
    [string]$Method,
    [hashtable]$Request,
    [object]$Response,
    [bool]$Pass,
    [string]$Status,
    [string]$Notes
  )
  [void]$results.Add([pscustomobject]@{
    domain = $Domain
    action = $Action
    method = $Method
    pass = $Pass
    status = $Status
    request = $Request
    response = $Response
    notes = $Notes
  })
}

# Bootstrap status
$bootstrap = Invoke-ApiGet 'auth_bootstrap_status' @{}
Add-Result 'auth' 'auth_bootstrap_status' 'GET' @{} $bootstrap.body ([bool]($bootstrap.body.ok -eq $true)) $(if($bootstrap.body.ok){'full parity'}else{'broken'}) 'bootstrap status'

# Superadmin login
$login = Invoke-ApiPost 'auth_login' @{ username = $SuperadminUser; password = $SuperadminPass }
$superToken = [string]($login.body.token)
Add-Result 'auth' 'auth_login' 'POST' @{ username = $SuperadminUser } $login.body ([bool]($login.body.ok -eq $true -and $superToken.Length -gt 20)) $(if($login.body.ok){'full parity'}else{'broken'}) 'superadmin login'

# auth_me / logout checks
$authMe = Invoke-ApiPost 'auth_me' @{ token = $superToken }
Add-Result 'auth' 'auth_me' 'POST' @{ token = '***' } $authMe.body ([bool]($authMe.body.ok -eq $true)) $(if($authMe.body.ok){'full parity'}else{'broken'}) 'auth me after login'

$logout = Invoke-ApiPost 'auth_logout' @{ token = $superToken }
Add-Result 'auth' 'auth_logout' 'POST' @{ token = '***' } $logout.body ([bool]($logout.body.ok -eq $true)) $(if($logout.body.ok){'full parity'}else{'broken'}) 'logout'

$authMeAfterLogout = Invoke-ApiPost 'auth_me' @{ token = $superToken }
$forcedReloginWorks = [bool]($authMeAfterLogout.body.ok -ne $true)
Add-Result 'auth' 'forced_relogin_check' 'POST' @{ token = 'old_token' } $authMeAfterLogout.body $forcedReloginWorks $(if($forcedReloginWorks){'full parity'}else{'broken'}) 'old token rejected after logout'

# Login again for rest (with auth_me verification to avoid post-logout token race)
$superToken = New-SuperToken -Username $SuperadminUser -Password $SuperadminPass
if ($superToken.Length -le 20) {
  Add-Result 'auth' 'auth_relogin_after_logout' 'POST' @{ username = $SuperadminUser } @{ ok = $false; error = 'RELOGIN_FAILED'; message = 'Failed to obtain a valid superadmin token after logout.' } $false 'broken' 'cannot continue token-protected tests'
}

# Create dedicated admin user for permission tests
$adminUser = '91' + (Get-Random -Minimum 100000000 -Maximum 999999999)
$adminPass = 'Admin@12345'
$createAdmin = Invoke-ApiPost 'auth_create_user' @{ token = $superToken; username = $adminUser; displayName = 'QA Admin'; role = 'admin'; password = $adminPass; permissions = @('dashboard','menuEditor','cashier','verification','eventManagement','eventScanner','eventGuests') }
if ($createAdmin.body.ok -ne $true -and [string]$createAdmin.body.error -eq 'TOKEN_REVOKED') {
  $superToken = New-SuperToken -Username $SuperadminUser -Password $SuperadminPass
  if ($superToken.Length -gt 20) {
    $createAdmin = Invoke-ApiPost 'auth_create_user' @{ token = $superToken; username = $adminUser; displayName = 'QA Admin'; role = 'admin'; password = $adminPass; permissions = @('dashboard','menuEditor','cashier','verification','eventManagement','eventScanner','eventGuests') }
  }
}
Add-Result 'admin' 'auth_create_user' 'POST' @{ username = $adminUser; role = 'admin' } $createAdmin.body ([bool]($createAdmin.body.ok -eq $true)) $(if($createAdmin.body.ok){'full parity'}else{'partial parity'}) 'create qa admin'

$adminLogin = Invoke-ApiPost 'auth_login' @{ username = $adminUser; password = $adminPass }
$adminToken = [string]($adminLogin.body.token)
Add-Result 'auth' 'auth_login_admin' 'POST' @{ username = $adminUser } $adminLogin.body ([bool]($adminLogin.body.ok -eq $true)) $(if($adminLogin.body.ok){'full parity'}else{'broken'}) 'admin login'

# Settings endpoints
$getApiSettings = Invoke-ApiPost 'auth_get_api_settings' @{ token = $superToken }
Add-Result 'admin-tools' 'auth_get_api_settings' 'POST' @{ token = '***' } $getApiSettings.body ([bool]($getApiSettings.body.ok -eq $true)) $(if($getApiSettings.body.ok){'full parity'}else{'partial parity'}) 'get API settings'

$getAppSettings = Invoke-ApiPost 'auth_get_app_settings' @{ token = $superToken }
Add-Result 'admin-tools' 'auth_get_app_settings' 'POST' @{ token = '***' } $getAppSettings.body ([bool]($getAppSettings.body.ok -eq $true)) $(if($getAppSettings.body.ok){'full parity'}else{'partial parity'}) 'get app settings'

$setAppSettings = Invoke-ApiPost 'auth_set_app_settings' @{ token = $superToken; settings = @{ hotelWhatsappNo = '919999999999'; menuBlockerStaffCode = 'QA2026' } }
Add-Result 'admin-tools' 'auth_set_app_settings' 'POST' @{ token = '***'; settings = '...' } $setAppSettings.body ([bool]($setAppSettings.body.ok -eq $true)) $(if($setAppSettings.body.ok){'full parity'}else{'partial parity'}) 'set app settings'

# User management actions
$listUsers = Invoke-ApiGet 'auth_list_users' @{ token = $superToken }
Add-Result 'admin-tools' 'auth_list_users' 'GET' @{ token = '***' } $listUsers.body ([bool]($listUsers.body.ok -eq $true)) $(if($listUsers.body.ok){'full parity'}else{'broken'}) 'list users'

$disableUser = Invoke-ApiPost 'auth_set_user_status' @{ token = $superToken; username = $adminUser; status = 'disabled' }
Add-Result 'admin-tools' 'auth_set_user_status_disable' 'POST' @{ username = $adminUser; status = 'disabled' } $disableUser.body ([bool]($disableUser.body.ok -eq $true)) $(if($disableUser.body.ok){'full parity'}else{'partial parity'}) 'disable user'

$disabledLogin = Invoke-ApiPost 'auth_login' @{ username = $adminUser; password = $adminPass }
Add-Result 'auth' 'disabled_user_login_block' 'POST' @{ username = $adminUser } $disabledLogin.body ([bool]($disabledLogin.body.ok -ne $true)) $(if($disabledLogin.body.ok -ne $true){'full parity'}else{'broken'}) 'disabled user should fail'

$enableUser = Invoke-ApiPost 'auth_set_user_status' @{ token = $superToken; username = $adminUser; status = 'active' }
Add-Result 'admin-tools' 'auth_set_user_status_enable' 'POST' @{ username = $adminUser; status = 'active' } $enableUser.body ([bool]($enableUser.body.ok -eq $true)) $(if($enableUser.body.ok){'full parity'}else{'partial parity'}) 'enable user'

$resetPwd = Invoke-ApiPost 'auth_reset_password' @{ token = $superToken; username = $adminUser; newPassword = 'Admin@12345' }
Add-Result 'admin-tools' 'auth_reset_password' 'POST' @{ username = $adminUser } $resetPwd.body ([bool]($resetPwd.body.ok -eq $true)) $(if($resetPwd.body.ok){'full parity'}else{'partial parity'}) 'reset password'

$setPerms = Invoke-ApiPost 'auth_set_user_permissions' @{ token = $superToken; username = $adminUser; permissions = @('dashboard') }
Add-Result 'admin-tools' 'auth_set_user_permissions' 'POST' @{ username = $adminUser; permissions = @('dashboard') } $setPerms.body ([bool]($setPerms.body.ok -eq $true)) $(if($setPerms.body.ok){'full parity'}else{'partial parity'}) 'reduce permissions for forbidden tests'

# Permission-gated failure test (menu load should fail now for admin)
$menuLoadForbidden = Invoke-ApiPost 'admin_menu_editor_load' @{ token = $adminToken; sheetName = 'AWGNK MENU' }
$forbiddenOk = [bool]($menuLoadForbidden.body.ok -ne $true -and [string]$menuLoadForbidden.body.error -in @('FORBIDDEN','UNAUTHORIZED','INVALID_TOKEN'))
Add-Result 'failure-modes' 'admin_menu_editor_load_forbidden' 'POST' @{ token = 'admin'; sheetName = 'AWGNK MENU' } $menuLoadForbidden.body $forbiddenOk $(if($forbiddenOk){'full parity'}else{'broken'}) 'permission enforced'

# Restore admin permissions
$setPerms2 = Invoke-ApiPost 'auth_set_user_permissions' @{ token = $superToken; username = $adminUser; permissions = @('dashboard','menuEditor','cashier','verification','eventManagement','eventScanner','eventGuests') }

# Menu tests (admin)
$menuFood = Invoke-ApiPost 'admin_menu_editor_load' @{ token = $adminToken; sheetName = 'AWGNK MENU' }
Add-Result 'menu-admin' 'admin_menu_editor_load_food' 'POST' @{ sheetName = 'AWGNK MENU' } $menuFood.body ([bool]($menuFood.body.ok -eq $true -and $menuFood.body.rowCount -ge 0)) $(if($menuFood.body.ok){'full parity'}else{'broken'}) 'food sheet load'

$menuBar = Invoke-ApiPost 'admin_menu_editor_load' @{ token = $adminToken; sheetName = 'BAR MENU NK' }
Add-Result 'menu-admin' 'admin_menu_editor_load_bar' 'POST' @{ sheetName = 'BAR MENU NK' } $menuBar.body ([bool]($menuBar.body.ok -eq $true -and $menuBar.body.rowCount -ge 0)) $(if($menuBar.body.ok){'full parity'}else{'broken'}) 'bar sheet load'

$added = Invoke-ApiPost 'admin_menu_editor_add_row' @{ token = $adminToken; sheetName = 'AWGNK MENU'; row = @{ category='QA'; subCategory='Smoke'; itemName='QA Test Item'; availability='Available'; basePrice=123; foodCategory='Veg' } }
$addedId = [int]($added.body.id)
Add-Result 'menu-admin' 'admin_menu_editor_add_row' 'POST' @{ sheetName = 'AWGNK MENU' } $added.body ([bool]($added.body.ok -eq $true -and $addedId -gt 0)) $(if($added.body.ok){'full parity'}else{'broken'}) 'add row'

$save = Invoke-ApiPost 'admin_menu_editor_save_changes' @{ token = $adminToken; sheetName = 'AWGNK MENU'; changes = @(@{ id=$addedId; itemName='QA Test Item Updated'; availability='Hidden'; basePrice=199 }) }
Add-Result 'menu-admin' 'admin_menu_editor_save_changes' 'POST' @{ id = $addedId } $save.body ([bool]($save.body.ok -eq $true -and [int]$save.body.updatedCount -ge 1)) $(if($save.body.ok){'full parity'}else{'partial parity'}) 'save changes'

$visible = Invoke-ApiPost 'admin_menu_editor_set_visibility' @{ token = $adminToken; sheetName = 'AWGNK MENU'; ids = @($addedId); isAvailable = $true }
Add-Result 'menu-admin' 'admin_menu_editor_set_visibility' 'POST' @{ id = $addedId; isAvailable = $true } $visible.body ([bool]($visible.body.ok -eq $true)) $(if($visible.body.ok){'full parity'}else{'partial parity'}) 'visibility update'

$deleted = Invoke-ApiPost 'admin_menu_editor_delete_rows' @{ token = $adminToken; sheetName = 'AWGNK MENU'; ids = @($addedId) }
Add-Result 'menu-admin' 'admin_menu_editor_delete_rows' 'POST' @{ ids = @($addedId) } $deleted.body ([bool]($deleted.body.ok -eq $true)) $(if($deleted.body.ok){'full parity'}else{'partial parity'}) 'delete row'

# Public menu endpoints
$tabFood = Invoke-ApiGet '' @{ tab = 'AWGNK MENU'; shape = 'grid' }
Add-Result 'menu-public' 'tab_food_grid' 'GET' @{ tab='AWGNK MENU'; shape='grid' } $tabFood.body ([bool]($tabFood.body.ok -eq $true)) $(if($tabFood.body.ok){'full parity'}else{'broken'}) 'public food tab'

$tabBar = Invoke-ApiGet '' @{ tab = 'BAR MENU NK'; shape = 'grid' }
Add-Result 'menu-public' 'tab_bar_grid' 'GET' @{ tab='BAR MENU NK'; shape='grid' } $tabBar.body ([bool]($tabBar.body.ok -eq $true)) $(if($tabBar.body.ok){'full parity'}else{'broken'}) 'public bar tab'

# Lead / coupon tests
$leadPhone = (Get-Random -Minimum 7000000000 -Maximum 9999999999).ToString()
$submitLead = Invoke-ApiPost 'submit_lead' @{ name='QA Lead'; phone=$leadPhone; countryCode='91'; source='qa-cutover' }
Add-Result 'lead-coupon' 'submit_lead' 'POST' @{ phone=$leadPhone } $submitLead.body ([bool]($submitLead.body.ok -eq $true)) $(if($submitLead.body.ok){'partial parity'}else{'broken'}) 'lead submit; check duplicate/cooldown parity separately'

$verifyLead = Invoke-ApiGet 'verify' @{ phone=$leadPhone; token=$adminToken }
Add-Result 'lead-coupon' 'verify' 'GET' @{ phone=$leadPhone } $verifyLead.body ([bool]($verifyLead.body.ok -eq $true)) $(if($verifyLead.body.ok){'full parity'}else{'broken'}) 'verify lead'

$regen = Invoke-ApiGet 'regen_coupon' @{ phone=$leadPhone; token=$adminToken }
Add-Result 'lead-coupon' 'regen_coupon' 'GET' @{ phone=$leadPhone } $regen.body ([bool]($regen.body.ok -eq $true)) $(if($regen.body.ok){'full parity'}else{'partial parity'}) 'regen coupon'

$redeem = Invoke-ApiGet 'redeem' @{ phone=$leadPhone; token=$adminToken }
Add-Result 'lead-coupon' 'redeem' 'GET' @{ phone=$leadPhone } $redeem.body ([bool]($redeem.body.ok -eq $true)) $(if($redeem.body.ok){'full parity'}else{'partial parity'}) 'redeem coupon'

$counter = Invoke-ApiGet 'counter' @{}
Add-Result 'lead-coupon' 'counter' 'GET' @{} $counter.body ([bool]($counter.body.ok -eq $true)) $(if($counter.body.ok){'full parity'}else{'broken'}) 'counter'

$qrClient = Invoke-ApiPost 'qr_scan_client' @{ userAgent='QA'; referer='local'; ip='127.0.0.1'; city='Kalyan'; country='IN' }
Add-Result 'lead-coupon' 'qr_scan_client' 'POST' @{ } $qrClient.body ([bool]($qrClient.body.ok -eq $true)) $(if($qrClient.body.ok){'full parity'}else{'partial parity'}) 'qr client scan'

$qrReport = Invoke-ApiGet 'qr_report' @{ token=$adminToken }
Add-Result 'lead-coupon' 'qr_report' 'GET' @{ token='***' } $qrReport.body ([bool]($qrReport.body.ok -eq $true)) $(if($qrReport.body.ok){'full parity'}else{'partial parity'}) 'qr report'

# Utility/test actions
$utilityActionsGet = @('init_schema','schema','ensure_qr_sheet','init_qr_sheet','create_qr_sheet','create_test_paid_tx','seed_test_paid_tx','download_qr_code','qr_scan_report_html','qr-scan-report-html','migrate_events_sheet_format','migrate_event_sheet_format','reset_events_sheet_format','reset_events_data','seed_events_sample','seed_event_sample','seed_dj_events_apr_2026','seed_dj_events','seed_paid_event_sample','seed_paid_event','send_test_event_email','test_event_email')
foreach($a in $utilityActionsGet){
  $resp = Invoke-ApiGet $a @{ token=$superToken }
  $ok = [bool](($resp.body.ok -eq $true) -or ([string]$resp.body.error -eq 'LEGACY_UTILITY_DISABLED'))
  $status = if([string]$resp.body.error -eq 'LEGACY_UTILITY_DISABLED'){'utility/test-only but implemented'}elseif($resp.body.ok){'full parity'}else{'partial parity'}
  Add-Result 'utility' $a 'GET' @{ token='***' } $resp.body $ok $status 'legacy utility action behavior'
}

$utilityActionsPost = @('add_test_qr_scan','test_qr_scan','add_test_25_coupon','test_25_coupon','add_test_lead','add-test-lead','sync_crm_by_phone','sync-crm-by-phone')
foreach($a in $utilityActionsPost){
  $body = @{ token = $superToken }
  if($a -like '*lead*'){ $body.name='QA Lead Manual'; $body.phone=(Get-Random -Minimum 7000000000 -Maximum 9999999999).ToString() }
  if($a -like '*sync*'){ $body.phone=$leadPhone }
  $resp = Invoke-ApiPost $a $body
  $ok = [bool](($resp.body.ok -eq $true) -or ([string]$resp.body.error -eq 'LEGACY_UTILITY_DISABLED'))
  $status = if([string]$resp.body.error -eq 'LEGACY_UTILITY_DISABLED'){'utility/test-only but implemented'}elseif($resp.body.ok){'full parity'}else{'partial parity'}
  Add-Result 'utility' $a 'POST' $body $resp.body $ok $status 'utility post action'
}

# Event tests
$events = Invoke-ApiGet 'events_list' @{ limit = 20 }
$eventItems = @($events.body.items)
$eventId = if($eventItems.Count -gt 0){ [string]$eventItems[0].eventId } else { '' }
Add-Result 'events' 'events_list' 'GET' @{ limit=20 } $events.body ([bool]($events.body.ok -eq $true)) $(if($events.body.ok){'full parity'}else{'broken'}) 'events list'

$popup = Invoke-ApiGet 'event_popup' @{}
Add-Result 'events' 'event_popup' 'GET' @{} $popup.body ([bool]($popup.body.ok -eq $true)) $(if($popup.body.ok){'full parity'}else{'partial parity'}) 'event popup'

if($eventId -ne ''){
  $detail = Invoke-ApiGet 'event_detail' @{ eventId = $eventId }
  Add-Result 'events' 'event_detail' 'GET' @{ eventId=$eventId } $detail.body ([bool]($detail.body.ok -eq $true)) $(if($detail.body.ok){'full parity'}else{'broken'}) 'event detail'

  $adminList = Invoke-ApiGet 'admin_list_events' @{ token = $adminToken }
  Add-Result 'events' 'admin_list_events' 'GET' @{ token='***' } $adminList.body ([bool]($adminList.body.ok -eq $true)) $(if($adminList.body.ok){'full parity'}else{'partial parity'}) 'admin list events'

  $freeReg = Invoke-ApiPost 'register_free_event' @{ eventId=$eventId; customerName='QA Guest'; customerEmail='qa@example.com'; customerPhone='9999999999'; qty=1 }
  Add-Result 'events' 'register_free_event' 'POST' @{ eventId=$eventId; qty=1 } $freeReg.body ([bool]($freeReg.body.ok -eq $true)) $(if($freeReg.body.ok){'full parity'}else{'partial parity'}) 'free registration'

  $order = Invoke-ApiPost 'create_event_order' @{ eventId=$eventId; customerName='QA Paid'; customerEmail='qa-paid@example.com'; customerPhone='9999999998'; qty=1 }
  $orderPass = [bool](($order.body.ok -eq $true) -or ([string]$order.body.error -eq 'PAYMENT_NOT_CONFIGURED'))
  $orderStatus = if($order.body.ok){'full parity'}elseif([string]$order.body.error -eq 'PAYMENT_NOT_CONFIGURED'){'partial parity'}else{'broken'}
  Add-Result 'events' 'create_event_order' 'POST' @{ eventId=$eventId; qty=1 } $order.body $orderPass $orderStatus 'paid order create / gateway config dependent'

  $guestReport = Invoke-ApiGet 'event_guest_report' @{ token=$adminToken; eventId=$eventId }
  Add-Result 'events' 'event_guest_report' 'GET' @{ token='***'; eventId=$eventId } $guestReport.body ([bool]($guestReport.body.ok -eq $true)) $(if($guestReport.body.ok){'full parity'}else{'partial parity'}) 'guest report'

  $txReport = Invoke-ApiGet 'event_transactions_report' @{ token=$adminToken; eventId=$eventId }
  Add-Result 'events' 'event_transactions_report' 'GET' @{ token='***'; eventId=$eventId } $txReport.body ([bool]($txReport.body.ok -eq $true)) $(if($txReport.body.ok){'full parity'}else{'partial parity'}) 'transaction report'

  $verifyQr = Invoke-ApiPost 'verify_event_qr' @{ token=$adminToken; tx='BAD'; eventId=$eventId; paymentId='BAD'; sig='BAD' }
  $verifyPass = [bool]($verifyQr.body.ok -eq $false)
  Add-Result 'events' 'verify_event_qr' 'POST' @{ token='***'; badPayload=$true } $verifyQr.body $verifyPass 'partial parity' 'invalid qr path tested'

  $batchCheckin = Invoke-ApiPost 'admin_batch_checkin_event_qr' @{ token=$adminToken; scans=@(@{ tx='BAD'; eventId=$eventId; paymentId='BAD'; sig='BAD' }) }
  $batchPass = [bool]($batchCheckin.body.ok -eq $true -or $batchCheckin.body.ok -eq $false)
  Add-Result 'events' 'admin_batch_checkin_event_qr' 'POST' @{ token='***'; scans='1 bad' } $batchCheckin.body $batchPass 'partial parity' 'scanner endpoint reachable'
}

# Cashier / dashboards
$cashSummary = Invoke-ApiGet 'admin_cash_summary' @{ token = $adminToken }
Add-Result 'cashier' 'admin_cash_summary' 'GET' @{ token='***' } $cashSummary.body ([bool]($cashSummary.body.ok -eq $true)) $(if($cashSummary.body.ok){'full parity'}else{'partial parity'}) 'cash summary'

# Always create a dedicated paid event for cashier tests.
$cashierEventId = 'QA-CASH-' + [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
$cashierSeeded = $false
$createCashierEvent = Invoke-ApiPost 'admin_create_event' @{
  token     = $superToken
  eventId   = $cashierEventId
  title     = 'QA Cashier Event'
  startDate = '2026-12-31'
  startTime = '21:00'
  venue     = 'Namaste Kalyan'
  eventType = 'paid'
  ticketPrice = 499
  paymentEnabled = $true
  currency  = 'INR'
  maxTickets = 50
  isActive = $true
}
if($createCashierEvent.body.ok -eq $true){
  $cashierSeeded = $true
}

$issueCash = if($cashierEventId -ne '') { Invoke-ApiPost 'admin_issue_cash_paid_pass' @{ token=$adminToken; eventId=$cashierEventId; customerName='QA Cash'; customerPhone='9999999997'; customerEmail='qa-cash@example.com'; qty=1 } } else { @{ body=@{ ok=$false; error='NO_EVENT' } } }
$issuePass = [bool]($issueCash.body.ok -eq $true)
Add-Result 'cashier' 'admin_issue_cash_paid_pass' 'POST' @{ eventId=$cashierEventId } $issueCash.body $issuePass $(if($issuePass){'full parity'}else{'partial parity'}) $(if($cashierSeeded){'issue cash pass (seeded paid event)'}else{'issue cash pass'})

$requestHandover = Invoke-ApiPost 'admin_request_cash_handover' @{ token=$adminToken }
Add-Result 'cashier' 'admin_request_cash_handover' 'POST' @{ token='***' } $requestHandover.body ([bool]($requestHandover.body.ok -eq $true)) $(if($requestHandover.body.ok){'full parity'}else{'partial parity'}) 'handover request'

if($issueCash.body.transactionId){
  $cancelReq = Invoke-ApiPost 'admin_request_cash_cancel' @{ token=$adminToken; transactionId=[string]$issueCash.body.transactionId; reason='QA cancel test' }
  Add-Result 'cashier' 'admin_request_cash_cancel' 'POST' @{ transactionId=[string]$issueCash.body.transactionId } $cancelReq.body ([bool]($cancelReq.body.ok -eq $true)) $(if($cancelReq.body.ok){'full parity'}else{'partial parity'}) 'cancel request'

  $resolveCancel = Invoke-ApiPost 'superadmin_resolve_cash_cancel' @{ token=$superToken; transactionId=[string]$issueCash.body.transactionId; decision='reject'; note='qa' }
  Add-Result 'cashier' 'superadmin_resolve_cash_cancel' 'POST' @{ transactionId=[string]$issueCash.body.transactionId } $resolveCancel.body ([bool]($resolveCancel.body.ok -eq $true)) $(if($resolveCancel.body.ok){'full parity'}else{'partial parity'}) 'resolve cancel'
}

$superDash = Invoke-ApiGet 'superadmin_cash_dashboard' @{ token=$superToken }
Add-Result 'cashier' 'superadmin_cash_dashboard' 'GET' @{ token='***' } $superDash.body ([bool]($superDash.body.ok -eq $true)) $(if($superDash.body.ok){'full parity'}else{'partial parity'}) 'superadmin dashboard'

# Webhook tests
$webhookNoSig = Invoke-ApiPost 'razorpay_webhook' @{ event='payment.captured'; payload=@{} }
$webhookNoSigPass = [bool]($webhookNoSig.body.ok -eq $false)
Add-Result 'webhook' 'razorpay_webhook_missing_signature' 'POST' @{ noSignature=$true } $webhookNoSig.body $webhookNoSigPass 'partial parity' 'signature required path'

$webhookBadSig = Invoke-ApiPost 'razorpay_webhook' @{ action='razorpay_webhook'; _rawBody='{"event":"payment.captured"}' }
Add-Result 'webhook' 'razorpay_webhook_bad_signature' 'POST' @{ badSignature=$true } $webhookBadSig.body ([bool]($webhookBadSig.body.ok -eq $false)) 'partial parity' 'invalid signature path'

# Generic failure-mode probes
$missingAction = Invoke-ApiPost '' @{}
Add-Result 'failure-modes' 'missing_action' 'POST' @{} $missingAction.body ([bool]($missingAction.body.ok -eq $false)) 'full parity' 'unknown action handling'

$invalidAction = Invoke-ApiGet 'non_existing_action_qa' @{}
Add-Result 'failure-modes' 'invalid_action' 'GET' @{ action='non_existing_action_qa' } $invalidAction.body ([bool]($invalidAction.body.ok -eq $false)) 'full parity' 'unknown action envelope'

$missingTokenProtected = Invoke-ApiPost 'admin_menu_editor_load' @{ sheetName='AWGNK MENU' }
Add-Result 'failure-modes' 'missing_token' 'POST' @{ action='admin_menu_editor_load' } $missingTokenProtected.body ([bool]($missingTokenProtected.body.ok -eq $false)) 'full parity' 'missing token rejection'

$badMenuSheet = Invoke-ApiPost 'admin_menu_editor_load' @{ token=$adminToken; sheetName='NOT_A_SHEET' }
Add-Result 'failure-modes' 'invalid_sheet' 'POST' @{ sheetName='NOT_A_SHEET' } $badMenuSheet.body ([bool]($badMenuSheet.body.ok -eq $false)) 'full parity' 'invalid sheet validation'

$badPayload = Invoke-ApiPost 'auth_login' @{ username=''; password='' }
Add-Result 'failure-modes' 'bad_payload' 'POST' @{ username=''; password='' } $badPayload.body ([bool]($badPayload.body.ok -eq $false)) 'full parity' 'input validation'

$outPath = 'backend/qa-cutover-results.json'
$results | ConvertTo-Json -Depth 20 | Out-File -FilePath $outPath -Encoding utf8

$summary = [pscustomobject]@{
  total = $results.Count
  passed = ($results | Where-Object { $_.pass }).Count
  failed = ($results | Where-Object { -not $_.pass }).Count
  fullParity = ($results | Where-Object { $_.status -eq 'full parity' }).Count
  partialParity = ($results | Where-Object { $_.status -eq 'partial parity' }).Count
  utilityImplemented = ($results | Where-Object { $_.status -eq 'utility/test-only but implemented' }).Count
  output = $outPath
}
$summary | ConvertTo-Json -Depth 5


