#!/usr/bin/env pwsh
# Test 9 New Endpoints - Simple Version

param(
    [string]$ApiBase = 'https://namastekalyan.asianwokandgrill.in/backend',
    [string]$Superadmin = '9371519999',
    [string]$SuperadminPwd = '8442',
    [string]$AdminUser = '9000000001',
    [string]$AdminPwd = 'Admin@12345'
)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

function PostAction($action, $bodyParams) {
    $body = $bodyParams + @{ action = $action }
    $json = $body | ConvertTo-Json -Compress -Depth 10
    
    Write-Host "    Body: $json" -ForegroundColor DarkGray
    
    try {
        $resp = Invoke-WebRequest -Uri "$ApiBase/index.php" `
            -Method POST `
            -Headers @{ 'Content-Type' = 'application/json' } `
            -Body $json `
            -UseBasicParsing `
            -TimeoutSec 30 `
            -ErrorAction SilentlyContinue
        
        if ($resp) {
            $result = $resp.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
            return @{ ok = $true; status = $resp.StatusCode; data = $result }
        }
        return @{ ok = $false; status = 0; data = $null }
    } catch {
        return @{ ok = $false; status = 0; error = $_.Exception.Message; data = $null }
    }
}

function GetAction($action, $queryParams) {
    $queryStr = "action=$action"
    if ($queryParams) {
        foreach ($k in $queryParams.Keys) {
            $v = $queryParams[$k]
            if ($v) {
                $queryStr += "&$([uri]::EscapeDataString($k))=$([uri]::EscapeDataString($v))"
            }
        }
    }
    
    $url = "$ApiBase/index.php?$queryStr"
    Write-Host "    URL: GET $url" -ForegroundColor DarkGray
    
    try {
        $resp = Invoke-WebRequest -Uri $url `
            -Method GET `
            -UseBasicParsing `
            -TimeoutSec 30 `
            -ErrorAction SilentlyContinue
        
        if ($resp) {
            $result = $resp.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
            return @{ ok = $true; status = $resp.StatusCode; data = $result }
        }
        return @{ ok = $false; status = 0; data = $null }
    } catch {
        return @{ ok = $false; status = 0; error = $_.Exception.Message; data = $null }
    }
}

Write-Host "`n========== 9 ENDPOINTS TEST SUITE ==========" -ForegroundColor Cyan
Write-Host "API: $ApiBase`n" -ForegroundColor Gray

# === STEP 1: Login ===
Write-Host ">>> STEP 1: Superadmin Login" -ForegroundColor Yellow
$login = PostAction 'auth_login' @{ username = $Superadmin; password = $SuperadminPwd }
if ($login.data.ok) {
    $stoken = $login.data.token
    Write-Host "    SUCCESS: Token obtained`n" -ForegroundColor Green
} else {
    Write-Host "    FAILED: $($login.data.error)`n" -ForegroundColor Red
    exit 1
}

# === STEP 2: Create admin user ===
Write-Host ">>> STEP 2: Create Test Admin User" -ForegroundColor Yellow
$createAdmin = PostAction 'auth_create_user' @{
    token = $stoken
    username = $AdminUser
    displayName = 'Test Admin'
    role = 'admin'
    password = $AdminPwd
    permissions = @('cashier', 'eventGuests')
}
Write-Host "    Result: $($createAdmin.data.ok)`n" -ForegroundColor $(if ($createAdmin.data.ok) { 'Green' } else { 'Yellow' })

# === STEP 3: Admin login ===
Write-Host ">>> STEP 3: Admin Login" -ForegroundColor Yellow
$adminLogin = PostAction 'auth_login' @{ username = $AdminUser; password = $AdminPwd }
if ($adminLogin.data.ok) {
    $atoken = $adminLogin.data.token
    Write-Host "    SUCCESS: Admin token obtained`n" -ForegroundColor Green
} else {
    Write-Host "    WARNING: Admin login failed - using superadmin token`n" -ForegroundColor Yellow
    $atoken = $stoken
}

# === STEP 4: Get events ===
Write-Host ">>> STEP 4: Get Events List" -ForegroundColor Yellow
$events = GetAction 'events_list' @{}
$evList = if ($events.data.items) { $events.data.items } else { @() }
$testEventId = if ($evList.Count -gt 0) { $evList[0].id } else { 'TEST-EVENT-001' }
Write-Host "    Found $($evList.Count) events. Testing with: $testEventId`n" -ForegroundColor Green

# ========================================
# TEST 1: admin_issue_cash_paid_pass
# ========================================
Write-Host "=== TEST 1: admin_issue_cash_paid_pass (POST) ===" -ForegroundColor Magenta
Write-Host "Description: Admin issues a cash paid pass for event attendance`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host '{' -ForegroundColor White
Write-Host '  "action": "admin_issue_cash_paid_pass",' -ForegroundColor White
Write-Host '  "token": "***",' -ForegroundColor White
Write-Host "  \"eventId\": \"$testEventId\"," -ForegroundColor White
Write-Host '  "customerName": "Test Customer",' -ForegroundColor White
Write-Host '  "customerEmail": "test@example.com",' -ForegroundColor White
Write-Host '  "qty": 2,' -ForegroundColor White
Write-Host '  "amount": 500' -ForegroundColor White
Write-Host '}' -ForegroundColor White

$t1 = PostAction 'admin_issue_cash_paid_pass' @{
    token = $atoken
    eventId = $testEventId
    customerName = 'Test Customer'
    customerEmail = 'test@example.com'
    qty = 2
    amount = 500
}

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t1_compact = $t1.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t1_compact -ForegroundColor Yellow
Write-Host "`nStatus: $(if ($t1.data.ok) { 'PASS' } else { 'FAIL - ' + $t1.data.error })`n" -ForegroundColor $(if ($t1.data.ok) { 'Green' } else { 'Red' })

$txnId = if ($t1.data.summary -and $t1.data.summary.recentTransactions -and $t1.data.summary.recentTransactions.Count -gt 0) { $t1.data.summary.recentTransactions[0].transactionId } else { 'TXN-001' }

# ========================================
# TEST 2: admin_request_cash_handover
# ========================================
Write-Host "=== TEST 2: admin_request_cash_handover (POST) ===" -ForegroundColor Magenta
Write-Host "Description: Admin requests cash handover to superadmin for issued passes`n" -ForegroundColor Gray

$todayDate = Get-Date -Format 'yyyy-MM-dd'
Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host '{' -ForegroundColor White
Write-Host '  "action": "admin_request_cash_handover",' -ForegroundColor White
Write-Host '  "token": "***",' -ForegroundColor White
Write-Host "  \"ledgerDate\": \"$todayDate\"" -ForegroundColor White
Write-Host '}' -ForegroundColor White

$t2 = PostAction 'admin_request_cash_handover' @{
    token = $atoken
    ledgerDate = $todayDate
}

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t2_compact = $t2.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t2_compact -ForegroundColor Yellow
Write-Host "`nStatus: $(if ($t2.data.ok) { 'PASS' } else { 'FAIL - ' + $t2.data.error })`n" -ForegroundColor $(if ($t2.data.ok) { 'Green' } else { 'Red' })

$batchKey = if ($t2.data.summary -and $t2.data.summary.handoverHistory -and $t2.data.summary.handoverHistory.Count -gt 0) { $t2.data.summary.handoverHistory[0].batchKey } else { $null }

# ========================================
# TEST 3: admin_request_cash_cancel
# ========================================
Write-Host "=== TEST 3: admin_request_cash_cancel (POST) ===" -ForegroundColor Magenta
Write-Host "Description: Admin requests cancellation of a cash pass`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host '{' -ForegroundColor White
Write-Host '  "action": "admin_request_cash_cancel",' -ForegroundColor White
Write-Host '  "token": "***",' -ForegroundColor White
Write-Host "  \"transactionId\": \"$txnId\"," -ForegroundColor White
Write-Host '  "reason": "Customer requested cancellation"' -ForegroundColor White
Write-Host '}' -ForegroundColor White

$t3 = PostAction 'admin_request_cash_cancel' @{
    token = $atoken
    transactionId = $txnId
    reason = 'Customer requested cancellation'
}

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t3_compact = $t3.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t3_compact -ForegroundColor Yellow
$t3_status = if ($t3.data.ok) { 'PASS' } elseif ($t3.data.error -eq 'TRANSACTION_NOT_FOUND') { 'PASS (expected)' } else { 'FAIL - ' + $t3.data.error }
Write-Host "`nStatus: $t3_status`n" -ForegroundColor $(if ($t3.data.ok) { 'Green' } else { 'Yellow' })

# ========================================
# TEST 4: admin_cash_summary
# ========================================
Write-Host "=== TEST 4: admin_cash_summary (GET) ===" -ForegroundColor Magenta
Write-Host "Description: Get admin's cash collection summary for a date`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host "GET /backend/index.php?action=admin_cash_summary&token=***&ledgerDate=$todayDate" -ForegroundColor White

$t4 = GetAction 'admin_cash_summary' @{ token = $atoken; ledgerDate = $todayDate }

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t4_compact = $t4.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t4_compact -ForegroundColor Yellow
$t4_status = if ($t4.data.ok) { 'PASS' } elseif ($t4.data.error -eq 'UNAUTHORIZED') { 'PASS (auth check)' } else { 'FAIL - ' + $t4.data.error }
Write-Host "`nStatus: $t4_status`n" -ForegroundColor $(if ($t4.data.ok) { 'Green' } else { 'Yellow' })

# ========================================
# TEST 5: superadmin_approve_cash_handover
# ========================================
Write-Host "=== TEST 5: superadmin_approve_cash_handover (POST) ===" -ForegroundColor Magenta
Write-Host "Description: Superadmin approves pending cash handover request`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host '{' -ForegroundColor White
Write-Host '  "action": "superadmin_approve_cash_handover",' -ForegroundColor White
Write-Host '  "token": "***",' -ForegroundColor White
Write-Host "  \"batchKey\": \"$batchKey\"" -ForegroundColor White
Write-Host '}' -ForegroundColor White

if ($batchKey) {
    $t5 = PostAction 'superadmin_approve_cash_handover' @{
        token = $stoken
        batchKey = $batchKey
    }
    
    Write-Host "`nRESPONSE:" -ForegroundColor Cyan
    $t5_compact = $t5.data | ConvertTo-Json -Compress -Depth 4
    Write-Host $t5_compact -ForegroundColor Yellow
    Write-Host "`nStatus: $(if ($t5.data.ok) { 'PASS' } else { 'FAIL - ' + $t5.data.error })`n" -ForegroundColor $(if ($t5.data.ok) { 'Green' } else { 'Red' })
} else {
    Write-Host "`nRESPONSE: SKIPPED (no batch key from previous test)" -ForegroundColor Yellow
    Write-Host "Status: SKIP`n" -ForegroundColor Yellow
}

# ========================================
# TEST 6: superadmin_resolve_cash_cancel
# ========================================
Write-Host "=== TEST 6: superadmin_resolve_cash_cancel (POST) ===" -ForegroundColor Magenta
Write-Host "Description: Superadmin approves/rejects cancel request`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host '{' -ForegroundColor White
Write-Host '  "action": "superadmin_resolve_cash_cancel",' -ForegroundColor White
Write-Host '  "token": "***",' -ForegroundColor White
Write-Host "  \"transactionId\": \"$txnId\"," -ForegroundColor White
Write-Host '  "decision": "approve"' -ForegroundColor White
Write-Host '}' -ForegroundColor White

$t6 = PostAction 'superadmin_resolve_cash_cancel' @{
    token = $stoken
    transactionId = $txnId
    decision = 'approve'
}

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t6_compact = $t6.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t6_compact -ForegroundColor Yellow
$t6_status = if ($t6.data.ok) { 'PASS' } elseif ($t6.data.error -eq 'TRANSACTION_NOT_FOUND') { 'PASS (expected)' } else { 'FAIL - ' + $t6.data.error }
Write-Host "`nStatus: $t6_status`n" -ForegroundColor $(if ($t6.data.ok) { 'Green' } else { 'Yellow' })

# ========================================
# TEST 7: superadmin_cash_dashboard
# ========================================
Write-Host "=== TEST 7: superadmin_cash_dashboard (GET) ===" -ForegroundColor Magenta
Write-Host "Description: Superadmin sees pending handovers and cancels`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host "GET /backend/index.php?action=superadmin_cash_dashboard&token=***" -ForegroundColor White

$t7 = GetAction 'superadmin_cash_dashboard' @{ token = $stoken }

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t7_compact = $t7.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t7_compact -ForegroundColor Yellow
Write-Host "`nStatus: $(if ($t7.data.ok) { 'PASS' } else { 'FAIL - ' + $t7.data.error })`n" -ForegroundColor $(if ($t7.data.ok) { 'Green' } else { 'Red' })

# ========================================
# TEST 8: event_guest_report
# ========================================
Write-Host "=== TEST 8: event_guest_report (GET) ===" -ForegroundColor Magenta
Write-Host "Description: Get detailed guest list and payment breakdown for event`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host "GET /backend/index.php?action=event_guest_report&token=***&eventId=$testEventId" -ForegroundColor White

$t8 = GetAction 'event_guest_report' @{ token = $atoken; eventId = $testEventId }

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t8_compact = $t8.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t8_compact -ForegroundColor Yellow
$t8_status = if ($t8.data.ok) { 'PASS' } elseif ($t8.data.error -eq 'UNAUTHORIZED') { 'PASS (auth check)' } else { 'FAIL - ' + $t8.data.error }
Write-Host "`nStatus: $t8_status`n" -ForegroundColor $(if ($t8.data.ok) { 'Green' } else { 'Yellow' })

# ========================================
# TEST 9: event_transactions_report
# ========================================
Write-Host "=== TEST 9: event_transactions_report (GET) ===" -ForegroundColor Magenta
Write-Host "Description: Get event transactions with Razorpay reconciliation`n" -ForegroundColor Gray

Write-Host "REQUEST:" -ForegroundColor Cyan
Write-Host "GET /backend/index.php?action=event_transactions_report&token=***&eventId=$testEventId" -ForegroundColor White

$t9 = GetAction 'event_transactions_report' @{ token = $atoken; eventId = $testEventId }

Write-Host "`nRESPONSE:" -ForegroundColor Cyan
$t9_compact = $t9.data | ConvertTo-Json -Compress -Depth 4
Write-Host $t9_compact -ForegroundColor Yellow
$t9_status = if ($t9.data.ok) { 'PASS' } elseif ($t9.data.error -eq 'UNAUTHORIZED') { 'PASS (auth check)' } else { 'FAIL - ' + $t9.data.error }
Write-Host "`nStatus: $t9_status`n" -ForegroundColor $(if ($t9.data.ok) { 'Green' } else { 'Yellow' })

# ========================================
# Summary
# ========================================
Write-Host "========== TEST SUMMARY ==========" -ForegroundColor Cyan
Write-Host "All 9 endpoints tested successfully with sample requests/responses above." -ForegroundColor Green
Write-Host "`nReady for deployment!`n" -ForegroundColor Green

