#!/usr/bin/env pwsh

param(
    [string]$ApiBase = "http://localhost:8010",
    [string]$ServerHint = "php -S localhost:8010 -t . backend/index.php"
)

$ErrorActionPreference = "Stop"

$apiBase = $ApiBase.TrimEnd('/')
$passCount = 0
$failCount = 0

function Invoke-JsonApi {
    param(
        [Parameter(Mandatory=$true)][string]$Name,
        [Parameter(Mandatory=$true)][string]$Method,
        [Parameter(Mandatory=$true)][string]$Endpoint,
        [hashtable]$Body = $null,
        [bool]$ExpectOk = $true
    )

    Write-Host "`n[TEST] $Name" -ForegroundColor Cyan
    Write-Host "Request: $Method $Endpoint" -ForegroundColor Yellow

    $params = @{
        Uri        = "$apiBase$Endpoint"
        Method     = $Method
        TimeoutSec = 10
        Headers    = @{ "Content-Type" = "application/json" }
    }

    if ($null -ne $Body) {
        $params.Body = ($Body | ConvertTo-Json -Depth 6)
    }

    try {
        $res = Invoke-WebRequest @params -UseBasicParsing
        $json = $res.Content | ConvertFrom-Json
        Write-Host ("Status: {0}" -f $res.StatusCode) -ForegroundColor Green
        Write-Host ("Response: {0}" -f ($res.Content)) -ForegroundColor Gray

        $isOk = ($json.ok -eq $true)
        if ($ExpectOk -and -not $isOk) {
            Write-Host "✗ FAILED (ok=false)" -ForegroundColor Red
            return @{ passed = $false; json = $json }
        }

        Write-Host "✓ PASSED" -ForegroundColor Green
        return @{ passed = $true; json = $json }
    }
    catch {
        Write-Host "✗ FAILED" -ForegroundColor Red
        Write-Host $_.Exception.Message -ForegroundColor Red
        return @{ passed = $false; json = $null }
    }
}

Write-Host "`n=== Local API Smoke Tests ===" -ForegroundColor Cyan
Write-Host "Base URL: $apiBase" -ForegroundColor Cyan

# Precheck
$precheck = Invoke-JsonApi -Name "Precheck: auth_bootstrap_status" -Method "GET" -Endpoint "/?action=auth_bootstrap_status"
if (-not $precheck.passed) {
    Write-Host "Start server with: $ServerHint" -ForegroundColor Yellow
    exit 1
}

# 1. auth bootstrap status
$t1 = Invoke-JsonApi -Name "Auth Bootstrap Status" -Method "GET" -Endpoint "/?action=auth_bootstrap_status"
if ($t1.passed) { $passCount++ } else { $failCount++ }

# 2. events list
$t2 = Invoke-JsonApi -Name "Events List" -Method "GET" -Endpoint "/?action=events_list&limit=1"
if ($t2.passed) { $passCount++ } else { $failCount++ }

# 3. event popup
$t3 = Invoke-JsonApi -Name "Event Popup" -Method "GET" -Endpoint "/?action=event_popup"
if ($t3.passed) { $passCount++ } else { $failCount++ }

# 4. lead counter
$t4 = Invoke-JsonApi -Name "Lead Counter" -Method "GET" -Endpoint "/?action=counter"
if ($t4.passed) { $passCount++ } else { $failCount++ }

# 5. submit lead
$t5 = Invoke-JsonApi -Name "Submit Lead" -Method "POST" -Endpoint "/?action=submit_lead" -Body @{
    name = "Local Test User"
    phone = "9999999999"
    countryCode = "91"
    source = "local-test"
}
if ($t5.passed) { $passCount++ } else { $failCount++ }

# 6. auth login
$t6 = Invoke-JsonApi -Name "Auth Login" -Method "POST" -Endpoint "/?action=auth_login" -Body @{
    mobile = "9371519999"
    password = "8442"
}
if ($t6.passed) { $passCount++ } else { $failCount++ }

$total = $passCount + $failCount
$rate = if ($total -gt 0) { [math]::Round(($passCount / $total) * 100, 1) } else { 0 }

Write-Host "`n=== Summary ===" -ForegroundColor Cyan
Write-Host "Total: $total" -ForegroundColor White
Write-Host "Passed: $passCount" -ForegroundColor Green
Write-Host "Failed: $failCount" -ForegroundColor Red
Write-Host "Success Rate: $rate%" -ForegroundColor White

if ($failCount -gt 0) {
    exit 1
}

Write-Host "All tests passed. Local stack is ready." -ForegroundColor Green
exit 0
