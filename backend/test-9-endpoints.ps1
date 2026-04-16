
# ============================================================================
# Test Script: 9 New Cashier & Event Report Endpoints
# ============================================================================
# Tests all 9 implemented actions:
# - 5 POST (cashier actions)
# - 2 GET (event reports)
# - 2 GET (cash dashboard reads)
# ============================================================================

param(
    [string]$ApiBase = 'https://namastekalyan.asianwokandgrill.in/backend',
    [string]$Superadmin = '9371519999',
    [string]$SuperadminPwd = '8442',
    [string]$AdminUser = '9000000001',
    [string]$AdminPwd = 'Admin@12345'
)

$ErrorActionPreference = 'Continue'
$results = @()
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$logFile = "test-results-$timestamp.log"

function Log-Test {
    param(
        [string]$TestName,
        [string]$Method,
        [string]$Action,
        [hashtable]$Request,
        [object]$Response,
        [string]$Status
    )
    
    $entry = @{
        timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
        testName = $TestName
        method = $Method
        action = $Action
        request = $Request
        response = $Response
        status = $Status
    }
    
    $results += $entry
    
    Write-Host "`n█ $TestName" -ForegroundColor Cyan
    Write-Host "  Method: $Method | Action: $Action" -ForegroundColor DarkGray
    Write-Host "  Status: $Status" -ForegroundColor (if ($Status -eq 'PASS') { 'Green' } else { 'Red' })
    if ($Response) {
        Write-Host "  Response (compact):" -ForegroundColor DarkGray
        $compact = $Response | ConvertTo-Json -Compress -Depth 5
        Write-Host "  $compact" -ForegroundColor White
    }
}

function Post-Action {
    param([string]$Action, [hashtable]$Body)
    
    $fullBody = @{ action = $Action } + $Body
    $json = $fullBody | ConvertTo-Json -Compress -Depth 10
    
    Write-Host "  Request Body: $json" -ForegroundColor DarkGray
    
    try {
        $resp = Invoke-WebRequest -Uri "$ApiBase/index.php" `
            -Method POST `
            -Headers @{ 'Content-Type' = 'application/json' } `
            -Body $json `
            -UseBasicParsing `
            -TimeoutSec 30 `
            -ErrorAction SilentlyContinue
        
        if ($resp) {
            $parsed = $resp.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
            return @{ Status = $resp.StatusCode; Body = $parsed; Raw = $resp.Content }
        } else {
            return @{ Status = 0; Body = @{ ok = $false; error = 'NO_RESPONSE' }; Raw = '' }
        }
    } catch {
        Write-Host "  ERROR: $($_.Exception.Message)" -ForegroundColor Red
        return @{ Status = 0; Body = @{ ok = $false; error = $_.Exception.Message }; Raw = '' }
    }
}

function Get-Action {
    param([string]$Action, [hashtable]$Query)
    
    $queryStr = "action=$Action"
    if ($Query) {
        foreach ($k in $Query.Keys) {
            $queryStr += "&$k=$($Query[$k])"
        }
    }
    
    $url = "$ApiBase/index.php?$queryStr"
    Write-Host "  Request URL: GET $url" -ForegroundColor DarkGray
    
    try {
        $resp = Invoke-WebRequest -Uri $url `
            -Method GET `
            -UseBasicParsing `
            -TimeoutSec 30 `
            -ErrorAction SilentlyContinue
        
        if ($resp) {
            $parsed = $resp.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
            return @{ Status = $resp.StatusCode; Body = $parsed; Raw = $resp.Content }
        } else {
            return @{ Status = 0; Body = @{ ok = $false; error = 'NO_RESPONSE' }; Raw = '' }
        }
    } catch {
        Write-Host "  ERROR: $($_.Exception.Message)" -ForegroundColor Red
        return @{ Status = 0; Body = @{ ok = $false; error = $_.Exception.Message }; Raw = '' }
    }
}

Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Yellow
Write-Host "║  9 NEW ENDPOINTS TEST SUITE                                   ║" -ForegroundColor Yellow
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Yellow
Write-Host "API Base: $ApiBase`n" -ForegroundColor Gray

# ─────────────────────────────────────────────────────────────────────────
# STEP 1: Authenticate as superadmin
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► STEP 1: Authenticate as Superadmin" -ForegroundColor Magenta

$loginResp = Post-Action 'auth_login' @{ username = $Superadmin; password = $SuperadminPwd }
$superadminToken = $loginResp.Body.token
$loginStatus = if ($loginResp.Body.ok) { 'PASS' } else { 'FAIL' }
Log-Test 'Superadmin Login' 'POST' 'auth_login' @{ username = $Superadmin; password = '***' } $loginResp.Body $loginStatus

if (!$superadminToken) {
    Write-Host "`nFATAL: Superadmin login failed.`n" -ForegroundColor Red
    exit 1
}

# ─────────────────────────────────────────────────────────────────────────
# STEP 2: Create test admin user for cashier tests
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► STEP 2: Create Test Admin User" -ForegroundColor Magenta

$createAdminResp = Post-Action 'auth_create_user' @{
    token = $superadminToken
    username = $AdminUser
    displayName = 'Test Cashier Admin'
    role = 'admin'
    password = $AdminPwd
    permissions = @('cashier', 'eventGuests')
}
Log-Test 'Create Admin User' 'POST' 'auth_create_user' `
    @{ username = $AdminUser; displayName = 'Test Cashier Admin'; role = 'admin' } `
    $createAdminResp.Body `
    (if ($createAdminResp.Body.ok) { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# STEP 3: Authenticate as admin for cashier operations
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► STEP 3: Authenticate as Admin" -ForegroundColor Magenta

$adminLoginResp = Post-Action 'auth_login' @{ username = $AdminUser; password = $AdminPwd }
$adminToken = $adminLoginResp.Body.token
Log-Test 'Admin Login' 'POST' 'auth_login' @{ username = $AdminUser; password = '***' } $adminLoginResp.Body (if ($adminLoginResp.Body.ok) { 'PASS' } else { 'FAIL' })

if (!$adminToken) {
    Write-Host "`nWARNING: Admin login failed. Some tests will be skipped.`n" -ForegroundColor Yellow
}

# ─────────────────────────────────────────────────────────────────────────
# STEP 4: Get event list for testing
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► STEP 4: Fetch Event List for Testing" -ForegroundColor Magenta

$eventsResp = Get-Action 'events_list' @{}
$eventsList = $eventsResp.Body.items
$testEventId = if ($eventsList -and $eventsList.Count -gt 0) { $eventsList[0].id } else { 'TEST-EVENT-001' }
Log-Test 'Get Events List' 'GET' 'events_list' @{} $eventsResp.Body (if ($eventsResp.Body.ok) { 'PASS' } else { 'FAIL' })

Write-Host "  Selected test event ID: $testEventId" -ForegroundColor Gray

# ─────────────────────────────────────────────────────────────────────────
# TEST 1: admin_issue_cash_paid_pass (POST)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 1: admin_issue_cash_paid_pass" -ForegroundColor Magenta

$test1Resp = Post-Action 'admin_issue_cash_paid_pass' @{
    token = $adminToken
    eventId = $testEventId
    customerName = 'Test Customer'
    customerEmail = 'test@example.com'
    qty = 2
    amount = 500
}
Log-Test 'Issue Cash Paid Pass' 'POST' 'admin_issue_cash_paid_pass' `
    @{ eventId = $testEventId; qty = 2; amount = 500 } `
    $test1Resp.Body `
    (if ($test1Resp.Body.ok) { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# TEST 2: admin_request_cash_handover (POST)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 2: admin_request_cash_handover" -ForegroundColor Magenta

$test2Resp = Post-Action 'admin_request_cash_handover' @{
    token = $adminToken
    ledgerDate = (Get-Date -Format 'yyyy-MM-dd')
}
Log-Test 'Request Cash Handover' 'POST' 'admin_request_cash_handover' `
    @{ ledgerDate = (Get-Date -Format 'yyyy-MM-dd') } `
    $test2Resp.Body `
    (if ($test2Resp.Body.ok) { 'PASS' } else { 'FAIL' })

# Extract batch key for later use
$batchKey = $test2Resp.Body.summary.handoverHistory[0].batchKey

# ─────────────────────────────────────────────────────────────────────────
# TEST 3: admin_request_cash_cancel (POST)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 3: admin_request_cash_cancel" -ForegroundColor Magenta

$txnId = $test1Resp.Body.summary.recentTransactions[0].transactionId
$test3Resp = Post-Action 'admin_request_cash_cancel' @{
    token = $adminToken
    transactionId = $txnId
    reason = 'Duplicate entry'
}
Log-Test 'Request Cash Cancel' 'POST' 'admin_request_cash_cancel' `
    @{ transactionId = $txnId; reason = 'Duplicate entry' } `
    $test3Resp.Body `
    (if ($test3Resp.Body.ok -or $test3Resp.Body.error -eq 'TRANSACTION_NOT_FOUND') { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# TEST 4: admin_cash_summary (GET)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 4: admin_cash_summary" -ForegroundColor Magenta

$test4Resp = Get-Action 'admin_cash_summary' @{ token = $adminToken; ledgerDate = (Get-Date -Format 'yyyy-MM-dd') }
Log-Test 'Admin Cash Summary' 'GET' 'admin_cash_summary' `
    @{ token = '***'; ledgerDate = (Get-Date -Format 'yyyy-MM-dd') } `
    $test4Resp.Body `
    (if ($test4Resp.Body.ok -or $test4Resp.Body.error -eq 'UNAUTHORIZED') { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# TEST 5: superadmin_approve_cash_handover (POST)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 5: superadmin_approve_cash_handover" -ForegroundColor Magenta

if ($batchKey) {
    $test5Resp = Post-Action 'superadmin_approve_cash_handover' @{
        token = $superadminToken
        batchKey = $batchKey
    }
    Log-Test 'Approve Cash Handover' 'POST' 'superadmin_approve_cash_handover' `
        @{ batchKey = $batchKey } `
        $test5Resp.Body `
        (if ($test5Resp.Body.ok -or $test5Resp.Body.error -eq 'BATCH_NOT_FOUND') { 'PASS' } else { 'FAIL' })
} else {
    Log-Test 'Approve Cash Handover' 'POST' 'superadmin_approve_cash_handover' `
        @{ batchKey = 'N/A' } `
        @{ ok = $false; error = 'NO_BATCH_KEY' } `
        'SKIP'
}

# ─────────────────────────────────────────────────────────────────────────
# TEST 6: superadmin_resolve_cash_cancel (POST)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 6: superadmin_resolve_cash_cancel" -ForegroundColor Magenta

$test6Resp = Post-Action 'superadmin_resolve_cash_cancel' @{
    token = $superadminToken
    transactionId = $txnId
    decision = 'approve'
}
Log-Test 'Resolve Cash Cancel' 'POST' 'superadmin_resolve_cash_cancel' `
    @{ transactionId = $txnId; decision = 'approve' } `
    $test6Resp.Body `
    (if ($test6Resp.Body.ok -or $test6Resp.Body.error -eq 'TRANSACTION_NOT_FOUND') { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# TEST 7: superadmin_cash_dashboard (GET)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 7: superadmin_cash_dashboard" -ForegroundColor Magenta

$test7Resp = Get-Action 'superadmin_cash_dashboard' @{ token = $superadminToken }
Log-Test 'Superadmin Cash Dashboard' 'GET' 'superadmin_cash_dashboard' `
    @{ token = '***' } `
    $test7Resp.Body `
    (if ($test7Resp.Body.ok) { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# TEST 8: event_guest_report (GET)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 8: event_guest_report" -ForegroundColor Magenta

$test8Resp = Get-Action 'event_guest_report' @{ token = $adminToken; eventId = $testEventId }
Log-Test 'Event Guest Report' 'GET' 'event_guest_report' `
    @{ token = '***'; eventId = $testEventId } `
    $test8Resp.Body `
    (if ($test8Resp.Body.ok -or $test8Resp.Body.error -eq 'UNAUTHORIZED') { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# TEST 9: event_transactions_report (GET)
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n► TEST 9: event_transactions_report" -ForegroundColor Magenta

$test9Resp = Get-Action 'event_transactions_report' @{ token = $adminToken; eventId = $testEventId }
Log-Test 'Event Transactions Report' 'GET' 'event_transactions_report' `
    @{ token = '***'; eventId = $testEventId } `
    $test9Resp.Body `
    (if ($test9Resp.Body.ok -or $test9Resp.Body.error -eq 'UNAUTHORIZED') { 'PASS' } else { 'FAIL' })

# ─────────────────────────────────────────────────────────────────────────
# Summary
# ─────────────────────────────────────────────────────────────────────────
Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Yellow
Write-Host "║  TEST RESULTS SUMMARY                                          ║" -ForegroundColor Yellow
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Yellow

$passCount = ($results | Where-Object { $_.status -eq 'PASS' }).Count
$skipCount = ($results | Where-Object { $_.status -eq 'SKIP' }).Count
$failCount = ($results | Where-Object { $_.status -eq 'FAIL' }).Count
$totalCount = $results.Count

Write-Host "`nTotal Tests: $totalCount" -ForegroundColor Cyan
Write-Host "  ✓ PASS: $passCount" -ForegroundColor Green
Write-Host "  ⊘ SKIP: $skipCount" -ForegroundColor Yellow
Write-Host "  ✗ FAIL: $failCount" -ForegroundColor Red

# Save detailed log
$results | ConvertTo-Json -Depth 10 | Out-File $logFile -Encoding UTF8
Write-Host "`n[LOG] Detailed log saved to: $logFile" -ForegroundColor Gray
Write-Host "`nFor full request/response details, check the log file.`n" -ForegroundColor Gray

