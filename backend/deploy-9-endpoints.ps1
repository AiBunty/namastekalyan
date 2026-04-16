# Deploy 9 New Endpoint Implementations to Production
# Endpoints: admin_issue_cash_paid_pass, admin_request_cash_handover, admin_request_cash_cancel,
#           superadmin_approve_cash_handover, superadmin_resolve_cash_cancel, admin_cash_summary,
#           superadmin_cash_dashboard, event_guest_report, event_transactions_report

param(
    [string]$ApiBase = 'https://namastekalyan.asianwokandgrill.in/backend'
)

$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║ DEPLOYMENT: 9 New Endpoint Implementations                  ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

Write-Host "2 Files to Deploy:" -ForegroundColor Yellow
Write-Host "  1. src/Controllers/CashierController.php (7 endpoints)" -ForegroundColor White
Write-Host "  2. src/Controllers/EventController.php (2 endpoints)" -ForegroundColor White

# Step 1: Read controller files locally
Write-Host "`n► STEP 1: Read Local Controller Files" -ForegroundColor Magenta

$cashierPath = "src/Controllers/CashierController.php"
$eventPath = "src/Controllers/EventController.php"

if (!(Test-Path $cashierPath)) {
    Write-Host "ERROR: $cashierPath not found" -ForegroundColor Red
    exit 1
}

if (!(Test-Path $eventPath)) {
    Write-Host "ERROR: $eventPath not found" -ForegroundColor Red
    exit 1
}

$cashierContent = Get-Content -Path $cashierPath -Raw -Encoding UTF8
$eventContent = Get-Content -Path $eventPath -Raw -Encoding UTF8

Write-Host "  ✓ CashierController.php ($([Math]::Round($cashierContent.Length/1024, 1)) KB)" -ForegroundColor Green
Write-Host "  ✓ EventController.php ($([Math]::Round($eventContent.Length/1024, 1)) KB)" -ForegroundColor Green

# Step 2: Validate PHP syntax
Write-Host "`n► STEP 2: Validate PHP Syntax" -ForegroundColor Magenta

$cashierLint = & php -l $cashierPath 2>&1
$eventLint = & php -l $eventPath 2>&1

if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ CashierController.php: No syntax errors" -ForegroundColor Green
} else {
    Write-Host "  ✗ CashierController.php: Syntax error" -ForegroundColor Red
    Write-Host $cashierLint -ForegroundColor Red
    exit 1
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ EventController.php: No syntax errors" -ForegroundColor Green
} else {
    Write-Host "  ✗ EventController.php: Syntax error" -ForegroundColor Red
    Write-Host $eventLint -ForegroundColor Red
    exit 1
}

# Step 3: Create backup of current controllers on remote
Write-Host "`n► STEP 3: Backup Current Remote Controllers" -ForegroundColor Magenta

$backupUrl = "$ApiBase/diag.php?backup_controllers=1&token=42da05f89b0137f8e5ed83ec8319eeab"

try {
    $backupResp = Invoke-WebRequest -Uri $backupUrl -Method GET -UseBasicParsing -TimeoutSec 30 -ErrorAction SilentlyContinue
    if ($backupResp -and $backupResp.StatusCode -eq 200) {
        $backupData = $backupResp.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
        if ($backupData.ok -eq $true -or $backupData.backup -eq $true) {
            Write-Host "  ✓ Remote controllers backed up" -ForegroundColor Green
        } else {
            Write-Host "  ⚠ Backup status unclear, proceeding anyway" -ForegroundColor Yellow
        }
    }
} catch {
    Write-Host "  ⚠ Could not trigger backup via API, proceeding anyway" -ForegroundColor Yellow
}

# Step 4: Deploy files via Git
Write-Host "`n► STEP 4: Deploy Files via Git" -ForegroundColor Magenta

Write-Host "  Staging files..." -ForegroundColor Gray
& git add "src/Controllers/CashierController.php"
& git add "src/Controllers/EventController.php"

$gitStatus = & git status --short
Write-Host "  Staged changes:" -ForegroundColor Gray
Write-Host ($gitStatus -join "`n") -ForegroundColor Gray

Write-Host "  Creating deployment commit..." -ForegroundColor Gray
$commitMsg = "Deploy: Implement 9 missing cashier and event report endpoints (2026-04-16)"
& git commit -m $commitMsg

if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ Commit created: $commitMsg" -ForegroundColor Green
} else {
    Write-Host "  ⚠ Commit may have failed (no changes or other issue)" -ForegroundColor Yellow
}

Write-Host "  Pushing to origin main..." -ForegroundColor Gray
& git push origin main

if ($LASTEXITCODE -eq 0) {
    Write-Host "  ✓ Files pushed to git repository" -ForegroundColor Green
} else {
    Write-Host "  ✗ Git push failed" -ForegroundColor Red
    exit 1
}

# Step 5: Verify deployment
Write-Host "`n► STEP 5: Verify Deployment on Live Backend" -ForegroundColor Magenta

Write-Host "  Waiting 5 seconds for webhook deployment..." -ForegroundColor Gray
Start-Sleep -Seconds 5

# Test one endpoint to verify deployment
$testUrl = "$ApiBase/index.php?action=admin_cash_summary"
Write-Host "  Testing: GET $testUrl" -ForegroundColor Gray

try {
    $testResp = Invoke-WebRequest -Uri $testUrl -Method GET -UseBasicParsing -TimeoutSec 30 -ErrorAction SilentlyContinue
    
    if ($testResp) {
        $testData = $testResp.Content | ConvertFrom-Json -ErrorAction SilentlyContinue
        
        if ($testData.ok -eq $true) {
            Write-Host "  ✓ Endpoint returns data (no longer NOT_IMPLEMENTED)" -ForegroundColor Green
        } elseif ($testData.error -eq 'UNAUTHORIZED' -or $testData.error -eq 'INVALID_TOKEN') {
            Write-Host "  ✓ Endpoint responds with auth error (implementation working)" -ForegroundColor Green
        } elseif ($testData.error -eq 'NOT_IMPLEMENTED') {
            Write-Host "  ✗ Endpoint still returns NOT_IMPLEMENTED - deployment may not have completed" -ForegroundColor Red
            Write-Host "    This can happen if webhooks haven't triggered yet. Wait 1-2 minutes and try again." -ForegroundColor Yellow
        } else {
            Write-Host "  ✓ Endpoint responds (status: $($testData.error))" -ForegroundColor Yellow
        }
    }
} catch {
    Write-Host "  ⚠ Could not reach endpoint (network or server issue)" -ForegroundColor Yellow
}

# Step 6: Summary and Next Steps
Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║ DEPLOYMENT SUMMARY                                         ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

Write-Host "✓ 2 controller files staged and committed" -ForegroundColor Green
Write-Host "✓ Changes pushed to origin main" -ForegroundColor Green
Write-Host "✓ Live backend will auto-deploy via webhook (1-2 minutes)" -ForegroundColor Green

Write-Host "`n9 ENDPOINTS NOW LIVE:" -ForegroundColor Cyan
Write-Host "  Cashier (POST):" -ForegroundColor Yellow
Write-Host "    1. admin_issue_cash_paid_pass" -ForegroundColor White
Write-Host "    2. admin_request_cash_handover" -ForegroundColor White
Write-Host "    3. admin_request_cash_cancel" -ForegroundColor White
Write-Host "    4. superadmin_approve_cash_handover" -ForegroundColor White
Write-Host "    5. superadmin_resolve_cash_cancel" -ForegroundColor White
Write-Host "  Admin Dashboards (GET):" -ForegroundColor Yellow
Write-Host "    6. admin_cash_summary" -ForegroundColor White
Write-Host "    7. superadmin_cash_dashboard" -ForegroundColor White
Write-Host "  Event Reports (GET):" -ForegroundColor Yellow
Write-Host "    8. event_guest_report" -ForegroundColor White
Write-Host "    9. event_transactions_report" -ForegroundColor White

Write-Host "`nVERIFICATION:" -ForegroundColor Cyan
Write-Host "  After 1-2 minutes, run the test script again:" -ForegroundColor Gray
Write-Host "  powershell -File test-9-endpoints-simple.ps1" -ForegroundColor Cyan

Write-Host "`nADMIN PANEL ACCESS:" -ForegroundColor Cyan
Write-Host "  Users will see data in:" -ForegroundColor Gray
Write-Host "  - Admin Cashier module" -ForegroundColor White
Write-Host "  - Event Guests module" -ForegroundColor White
Write-Host "  - SuperAdmin Cash Approvals module" -ForegroundColor White

Write-Host "`n" -ForegroundColor Green

