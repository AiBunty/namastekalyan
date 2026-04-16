param(
  [string]$BaseUrl = 'http://localhost:8010',
  [string]$SuperadminUser = '9371519999',
  [string]$SuperadminPassword = '8442',
  [string]$DockerDbContainer = 'namastekalyan-mysql'
)

$ErrorActionPreference = 'Stop'

$state = [ordered]@{
  DummyUser = ''
  DummyPhone = ''
  DummyEventId = ''
  DummyMenuRowId = 0
  DummyBatchKey = ''
}

function Invoke-Api {
  param(
    [Parameter(Mandatory=$true)][ValidateSet('GET','POST')] [string]$Method,
    [Parameter(Mandatory=$true)][string]$Action,
    [hashtable]$Body = @{},
    [hashtable]$Query = @{}
  )

  $queryParts = @("action=$([uri]::EscapeDataString($Action))")
  foreach($k in $Query.Keys){
    $queryParts += ("{0}={1}" -f [uri]::EscapeDataString([string]$k), [uri]::EscapeDataString([string]$Query[$k]))
  }
  $url = "$($BaseUrl.TrimEnd('/'))/?" + ($queryParts -join '&')

  if($Method -eq 'GET'){
    $resp = Invoke-WebRequest -Uri $url -Method GET -UseBasicParsing -TimeoutSec 45
  } else {
    $payload = @{} + $Body
    $payload['action'] = $Action
    $json = $payload | ConvertTo-Json -Compress -Depth 10
    $resp = Invoke-WebRequest -Uri $url -Method POST -UseBasicParsing -ContentType 'application/json' -Body $json -TimeoutSec 45
  }

  $parsed = $null
  if([string]::IsNullOrWhiteSpace($resp.Content)){
    $parsed = @{}
  } else {
    $parsed = $resp.Content | ConvertFrom-Json
  }

  return [pscustomobject]@{
    Url = $url
    StatusCode = $resp.StatusCode
    Json = $parsed
  }
}

function Assert-True {
  param(
    [Parameter(Mandatory=$true)][bool]$Condition,
    [Parameter(Mandatory=$true)][string]$Message
  )
  if(-not $Condition){ throw $Message }
}

function Assert-Ok {
  param(
    [Parameter(Mandatory=$true)]$Response,
    [Parameter(Mandatory=$true)][string]$Step
  )
  $ok = $false
  if($null -ne $Response.Json -and $Response.Json.PSObject.Properties.Name -contains 'ok'){
    $ok = [bool]$Response.Json.ok
  }
  Assert-True -Condition $ok -Message ("$Step failed. URL=$($Response.Url), Body=$($Response.Json | ConvertTo-Json -Compress -Depth 10)")
}

function Wait-Api {
  param([string]$HealthAction = 'auth_bootstrap_status')
  for($i=0; $i -lt 20; $i++){
    try {
      $r = Invoke-Api -Method GET -Action $HealthAction
      if($r.StatusCode -eq 200){ return }
    } catch {}
    Start-Sleep -Milliseconds 500
  }
  throw "Local API is not reachable at $BaseUrl"
}

Write-Host "[1/8] Waiting for local API..." -ForegroundColor Cyan
Wait-Api

$results = New-Object System.Collections.Generic.List[object]

try {
  Write-Host "[2/8] Auth + user lifecycle tests..." -ForegroundColor Cyan

  $login = Invoke-Api -Method POST -Action 'auth_login' -Body @{ username = $SuperadminUser; password = $SuperadminPassword }
  Assert-Ok $login 'auth_login(superadmin)'
  $superToken = [string]$login.Json.token
  Assert-True ($superToken.Length -gt 20) 'Missing superadmin token'
  $results.Add([pscustomobject]@{step='auth_login_superadmin'; ok=$true}) | Out-Null

  $seed = (Get-Random -Minimum 100000 -Maximum 999999)
  $state.DummyUser = '9' + $seed.ToString() + '001'
  $dummyInitialPass = 'Dummy@12345'
  $dummyResetPass = 'Dummy@54321'
  $dummyFinalPass = 'Dummy@67890'

  $createUser = Invoke-Api -Method POST -Action 'auth_create_user' -Body @{
    token = $superToken
    username = $state.DummyUser
    displayName = 'DUMMY E2E USER'
    role = 'admin'
    password = $dummyInitialPass
    permissions = @('dashboard','cashier','verification','eventGuests','eventScanner','eventManagement','menuEditor')
  }
  Assert-Ok $createUser 'auth_create_user'
  $results.Add([pscustomobject]@{step='auth_create_user'; ok=$true}) | Out-Null

  $setPerm = Invoke-Api -Method POST -Action 'auth_set_user_permissions' -Body @{
    token = $superToken
    username = $state.DummyUser
    permissions = @('dashboard','cashier','verification','eventGuests','eventScanner','eventManagement','menuEditor')
  }
  Assert-Ok $setPerm 'auth_set_user_permissions'
  $results.Add([pscustomobject]@{step='auth_set_user_permissions'; ok=$true}) | Out-Null

  $disableUser = Invoke-Api -Method POST -Action 'auth_set_user_status' -Body @{ token = $superToken; username = $state.DummyUser; status='disabled' }
  Assert-Ok $disableUser 'auth_set_user_status(disabled)'
  $enableUser = Invoke-Api -Method POST -Action 'auth_set_user_status' -Body @{ token = $superToken; username = $state.DummyUser; status='active' }
  Assert-Ok $enableUser 'auth_set_user_status(active)'
  $results.Add([pscustomobject]@{step='auth_set_user_status_toggle'; ok=$true}) | Out-Null

  $resetPass = Invoke-Api -Method POST -Action 'auth_reset_password' -Body @{ token = $superToken; username = $state.DummyUser; newPassword = $dummyResetPass }
  Assert-Ok $resetPass 'auth_reset_password'
  $results.Add([pscustomobject]@{step='auth_reset_password'; ok=$true}) | Out-Null

  $loginDummy = Invoke-Api -Method POST -Action 'auth_login' -Body @{ username = $state.DummyUser; password = $dummyResetPass }
  Assert-Ok $loginDummy 'auth_login(dummy after reset)'
  $dummyToken = [string]$loginDummy.Json.token
  Assert-True ($dummyToken.Length -gt 20) 'Missing dummy token'

  $changePass = Invoke-Api -Method POST -Action 'auth_change_password' -Body @{ token=$dummyToken; currentPassword=$dummyResetPass; newPassword=$dummyFinalPass }
  Assert-Ok $changePass 'auth_change_password(dummy)'
  $results.Add([pscustomobject]@{step='auth_change_password'; ok=$true}) | Out-Null

  $loginDummyFinal = Invoke-Api -Method POST -Action 'auth_login' -Body @{ username = $state.DummyUser; password = $dummyFinalPass }
  Assert-Ok $loginDummyFinal 'auth_login(dummy final)'
  $dummyToken = [string]$loginDummyFinal.Json.token

  Write-Host "[3/8] Settings update tests..." -ForegroundColor Cyan
  $settingsAction = Invoke-Api -Method POST -Action 'auth_set_api_settings' -Body @{
    token = $superToken
    settings = @{ CRM_API_TOKEN = 'DUMMY_TOKEN'; SMTP_PASS = 'DUMMY_PASS' }
  }
  Assert-Ok $settingsAction 'auth_set_api_settings'
  $results.Add([pscustomobject]@{step='auth_set_api_settings'; ok=$true}) | Out-Null

  Write-Host "[4/8] Event create/update/toggle/report tests..." -ForegroundColor Cyan
  $state.DummyEventId = ('dummy-e2e-' + (Get-Date -Format 'yyyyMMddHHmmss'))

  $createEvent = Invoke-Api -Method POST -Action 'admin_create_event' -Body @{
    token = $dummyToken
    event_id = $state.DummyEventId
    title = 'DUMMY E2E EVENT'
    subtitle = 'E2E create'
    startDate = (Get-Date).ToString('yyyy-MM-dd')
    startTime = '20:00:00'
    endDate = (Get-Date).AddDays(1).ToString('yyyy-MM-dd')
    endTime = '23:00:00'
    eventType = 'paid'
    ticketPrice = 99
    paymentEnabled = $true
    popupEnabled = $false
    isActive = $true
    currency = 'INR'
  }
  Assert-Ok $createEvent 'admin_create_event'

  $updateEvent = Invoke-Api -Method POST -Action 'admin_update_event' -Body @{
    token = $dummyToken
    eventId = $state.DummyEventId
    title = 'DUMMY E2E EVENT UPDATED'
    subtitle = 'E2E updated'
    ticketPrice = 149
    paymentEnabled = $true
    eventType = 'paid'
    isActive = $true
  }
  Assert-Ok $updateEvent 'admin_update_event'

  $toggleOff = Invoke-Api -Method POST -Action 'admin_toggle_event' -Body @{ token=$dummyToken; eventId=$state.DummyEventId; isActive=$false }
  Assert-Ok $toggleOff 'admin_toggle_event(false)'
  $toggleOn = Invoke-Api -Method POST -Action 'admin_toggle_event' -Body @{ token=$dummyToken; eventId=$state.DummyEventId; isActive=$true }
  Assert-Ok $toggleOn 'admin_toggle_event(true)'

  $listEvents = Invoke-Api -Method GET -Action 'admin_list_events' -Query @{ token=$dummyToken }
  Assert-Ok $listEvents 'admin_list_events'

  $guestReport = Invoke-Api -Method GET -Action 'event_guest_report' -Query @{ token=$dummyToken; eventId=$state.DummyEventId }
  Assert-Ok $guestReport 'event_guest_report'
  $results.Add([pscustomobject]@{step='event_create_update_toggle_reports'; ok=$true}) | Out-Null

  Write-Host "[5/8] Menu add/update/visibility/delete tests..." -ForegroundColor Cyan
  $addMenu = Invoke-Api -Method POST -Action 'admin_menu_editor_add_row' -Body @{
    token = $dummyToken
    sheetType = 'food'
    row = @{ category='Test'; subCategory='E2E'; itemName='DUMMY_E2E_MENU_ITEM'; availability='Available'; basePrice=123; foodCategory='Veg'; sortOrder=9999 }
  }
  Assert-Ok $addMenu 'admin_menu_editor_add_row'
  $state.DummyMenuRowId = [int]$addMenu.Json.id
  Assert-True ($state.DummyMenuRowId -gt 0) 'Menu row id invalid'

  $saveMenu = Invoke-Api -Method POST -Action 'admin_menu_editor_save_changes' -Body @{
    token = $dummyToken
    sheetType = 'food'
    changes = @(@{ id=$state.DummyMenuRowId; itemName='DUMMY_E2E_MENU_ITEM_UPDATED'; category='Test'; subCategory='E2E'; availability='Available'; basePrice=321; foodCategory='Veg' })
  }
  Assert-Ok $saveMenu 'admin_menu_editor_save_changes'

  $setHidden = Invoke-Api -Method POST -Action 'admin_menu_editor_set_visibility' -Body @{ token=$dummyToken; sheetType='food'; ids=@($state.DummyMenuRowId); isAvailable=$false }
  Assert-Ok $setHidden 'admin_menu_editor_set_visibility(false)'

  $setVisible = Invoke-Api -Method POST -Action 'admin_menu_editor_set_visibility' -Body @{ token=$dummyToken; sheetType='food'; ids=@($state.DummyMenuRowId); isAvailable=$true }
  Assert-Ok $setVisible 'admin_menu_editor_set_visibility(true)'

  $deleteMenu = Invoke-Api -Method POST -Action 'admin_menu_editor_delete_rows' -Body @{ token=$dummyToken; sheetType='food'; ids=@($state.DummyMenuRowId) }
  Assert-Ok $deleteMenu 'admin_menu_editor_delete_rows'
  $state.DummyMenuRowId = 0
  $results.Add([pscustomobject]@{step='menu_add_update_visibility_delete'; ok=$true}) | Out-Null

  Write-Host "[6/8] Lead verify/redeem/regen + QR tests..." -ForegroundColor Cyan
  $state.DummyPhone = ('98' + (Get-Random -Minimum 10000000 -Maximum 99999999).ToString())

  $leadSubmit = Invoke-Api -Method POST -Action 'submit_lead' -Body @{ name='DUMMY E2E LEAD'; phone=$state.DummyPhone; countryCode='91'; source='dummy-e2e' }
  Assert-Ok $leadSubmit 'submit_lead'

  $leadVerify = Invoke-Api -Method GET -Action 'verify' -Query @{ phone=$state.DummyPhone }
  Assert-Ok $leadVerify 'verify'

  $leadRedeem = Invoke-Api -Method GET -Action 'redeem' -Query @{ token=$dummyToken; phone=$state.DummyPhone }
  Assert-Ok $leadRedeem 'redeem'

  $leadRegen = Invoke-Api -Method GET -Action 'regen_coupon' -Query @{ token=$dummyToken; phone=$state.DummyPhone }
  Assert-Ok $leadRegen 'regen_coupon'

  $qrScan = Invoke-Api -Method POST -Action 'qr_scan_client' -Body @{ userAgent='DUMMY_E2E_QR_AGENT'; device='E2E'; browser='pwsh'; os='Windows' }
  Assert-Ok $qrScan 'qr_scan_client'

  $qrReport = Invoke-Api -Method GET -Action 'qr_report' -Query @{ token=$dummyToken }
  Assert-Ok $qrReport 'qr_report'
  $results.Add([pscustomobject]@{step='lead_and_qr'; ok=$true}) | Out-Null

  Write-Host "[7/8] Cashier issue/handover/approve/dashboard tests..." -ForegroundColor Cyan
  $issueCash = Invoke-Api -Method POST -Action 'admin_issue_cash_paid_pass' -Body @{
    token = $dummyToken
    eventId = $state.DummyEventId
    customerName = 'DUMMY E2E CASH CUSTOMER'
    customerPhone = $state.DummyPhone
    customerEmail = 'dummy.e2e@example.com'
    qty = 2
    notes = 'DUMMY E2E CASH FLOW'
  }
  Assert-Ok $issueCash 'admin_issue_cash_paid_pass'

  $handover = Invoke-Api -Method POST -Action 'admin_request_cash_handover' -Body @{ token=$dummyToken; ledgerDate=(Get-Date).ToString('yyyy-MM-dd') }
  Assert-Ok $handover 'admin_request_cash_handover'
  $state.DummyBatchKey = [string]($handover.Json.batchKey)

  if(-not [string]::IsNullOrWhiteSpace($state.DummyBatchKey)){
    $approve = Invoke-Api -Method POST -Action 'superadmin_approve_cash_handover' -Body @{
      token = $superToken
      adminUsername = $state.DummyUser
      ledgerDate = (Get-Date).ToString('yyyy-MM-dd')
    }
    Assert-Ok $approve 'superadmin_approve_cash_handover'
  }

  $adminCashSummary = Invoke-Api -Method GET -Action 'admin_cash_summary' -Query @{ token=$dummyToken; ledgerDate=(Get-Date).ToString('yyyy-MM-dd') }
  Assert-Ok $adminCashSummary 'admin_cash_summary'

  $superCashDash = Invoke-Api -Method GET -Action 'superadmin_cash_dashboard' -Query @{ token=$superToken }
  Assert-Ok $superCashDash 'superadmin_cash_dashboard'
  $results.Add([pscustomobject]@{step='cashier_flow'; ok=$true}) | Out-Null

  Write-Host "[8/8] Final auth/logout checks..." -ForegroundColor Cyan
  $me = Invoke-Api -Method POST -Action 'auth_me' -Body @{ token=$dummyToken }
  Assert-Ok $me 'auth_me(dummy)'
  $logout = Invoke-Api -Method POST -Action 'auth_logout' -Body @{ token=$dummyToken }
  Assert-Ok $logout 'auth_logout(dummy)'

  $results.Add([pscustomobject]@{step='final_auth'; ok=$true}) | Out-Null

  Write-Host "\nALL DUMMY TESTS PASSED" -ForegroundColor Green
  $results | ConvertTo-Json -Depth 10
}
finally {
  Write-Host "\n[Cleanup] Removing dummy records..." -ForegroundColor Yellow

  if($state.DummyMenuRowId -gt 0){
    try {
      $login = Invoke-Api -Method POST -Action 'auth_login' -Body @{ username = $SuperadminUser; password = $SuperadminPassword }
      if($login.Json.ok){
        $tok = [string]$login.Json.token
        [void](Invoke-Api -Method POST -Action 'admin_menu_editor_delete_rows' -Body @{ token=$tok; sheetType='food'; ids=@($state.DummyMenuRowId) })
      }
    } catch {}
  }

  $cleanupSqlPath = Join-Path $PSScriptRoot 'dummy_cleanup.sql'
  $dummyUser = $state.DummyUser.Replace("'","''")
  $dummyPhone = $state.DummyPhone.Replace("'","''")
  $dummyEvent = $state.DummyEventId.Replace("'","''")
  $dummyBatch = $state.DummyBatchKey.Replace("'","''")

  $sql = @"
DELETE FROM event_transactions WHERE event_id = '$dummyEvent' OR customer_name LIKE 'DUMMY E2E%';
DELETE FROM admin_cash_ledger WHERE event_id = '$dummyEvent' OR customer_name LIKE 'DUMMY E2E%';
DELETE FROM superadmin_cash_ledger WHERE batch_key = '$dummyBatch' OR admin_username = '$dummyUser';
DELETE FROM events WHERE event_id = '$dummyEvent';
DELETE FROM leads WHERE phone = '$dummyPhone' OR source = 'dummy-e2e' OR name LIKE 'DUMMY E2E%';
DELETE FROM users WHERE username = '$dummyUser';
DELETE FROM menu_items WHERE item_name LIKE 'DUMMY_E2E_MENU_ITEM%';
DELETE FROM qr_scans WHERE user_agent LIKE 'DUMMY_E2E_QR_AGENT%';
"@

  Set-Content -Path $cleanupSqlPath -Value $sql -Encoding UTF8

  try {
    docker cp $cleanupSqlPath "$DockerDbContainer`:/tmp/dummy_cleanup.sql" | Out-Null
    docker exec $DockerDbContainer sh -lc 'mysql -unamastes -p"LocalPass@123" namastekalyan_local < /tmp/dummy_cleanup.sql' | Out-Null
    Write-Host "[Cleanup] Dummy data removed." -ForegroundColor Green
  } catch {
    Write-Host "[Cleanup] Warning: automatic cleanup command failed. Review .tmp/dummy_cleanup.sql" -ForegroundColor Red
  }
}
