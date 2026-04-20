$ErrorActionPreference = 'Stop'

$base = 'https://script.google.com/macros/s/AKfycbycDXiXAgZf5l-V4v8cbu8DQEPh8QFYuyYx9XogEpEtiVx6IWXe3_xHmhA-vvQYuZ2E/exec'
$diag = 'https://namastekalyan.asianwokandgrill.in/backend/diag.php?token=42da05f89b0137f8e5ed83ec8319eeab&smoke=2'
$setup = 'https://namastekalyan.asianwokandgrill.in/backend/setup.php?token=42da05f89b0137f8e5ed83ec8319eeab&smoke=2'

function Post-Action([hashtable]$obj) {
  $payload = @{ payload = ($obj | ConvertTo-Json -Compress -Depth 10) }
  $resp = Invoke-WebRequest -Uri $base -Method POST -Body $payload -ContentType 'application/x-www-form-urlencoded' -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 45
  $json = $resp.Content | ConvertFrom-Json
  return [pscustomobject]@{ Status = $resp.StatusCode; Json = $json }
}

function Get-Action([string]$action) {
  $u = "$base?action=$action"
  $resp = Invoke-WebRequest -Uri $u -Method GET -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 45
  $json = $resp.Content | ConvertFrom-Json
  return [pscustomobject]@{ Status = $resp.StatusCode; Json = $json }
}

$results = @()

$d = Invoke-WebRequest -Uri $diag -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 30
$dj = $d.Content | ConvertFrom-Json
$results += [pscustomobject]@{ Test = 'backend_diag'; Pass = [bool]($dj.ok -eq $true); Status = $d.StatusCode; Detail = [string]$dj.db }

$s = Invoke-WebRequest -Uri $setup -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 30
$sj = $s.Content | ConvertFrom-Json
$setupDetail = ((($sj.steps | ConvertTo-Json -Compress) + ' ' + ($sj.errors -join '; '))).Trim()
$results += [pscustomobject]@{ Test = 'backend_setup'; Pass = [bool]($sj.ok -eq $true); Status = $s.StatusCode; Detail = $setupDetail }

$login = Post-Action @{ action = 'auth_login'; username = '9371519999'; password = '8442' }
$token = [string]$login.Json.token
$results += [pscustomobject]@{ Test = 'auth_login'; Pass = [bool]($login.Json.ok -eq $true -and $token.Length -gt 20); Status = $login.Status; Detail = [string]$login.Json.error }

$me = Post-Action @{ action = 'auth_me'; token = $token }
$results += [pscustomobject]@{ Test = 'auth_me'; Pass = [bool]($me.Json.ok -eq $true); Status = $me.Status; Detail = [string]$me.Json.user.username }

$listUsers = Get-Action 'auth_list_users'
$userCount = if ($null -ne $listUsers.Json.items) { $listUsers.Json.items.Count } else { 0 }
$results += [pscustomobject]@{ Test = 'auth_list_users'; Pass = [bool]($listUsers.Json.ok -eq $true -and $userCount -ge 1); Status = $listUsers.Status; Detail = "count=$userCount" }

$newUser = '9' + (Get-Random -Minimum 100000000 -Maximum 999999999)
$createUser = Post-Action @{ action = 'auth_create_user'; token = $token; username = $newUser; displayName = 'Smoke User'; role = 'admin'; password = 'Smoke@12345'; permissions = @('dashboard') }
$results += [pscustomobject]@{ Test = 'auth_create_user'; Pass = [bool]($createUser.Json.ok -eq $true); Status = $createUser.Status; Detail = [string]$createUser.Json.error }

$apiSettings = Post-Action @{ action = 'auth_get_api_settings'; token = $token }
$results += [pscustomobject]@{ Test = 'auth_get_api_settings'; Pass = [bool]($apiSettings.Json.ok -eq $true); Status = $apiSettings.Status; Detail = [string]$apiSettings.Json.error }

$events = Get-Action 'events_list'
$eventCount = if ($null -ne $events.Json.items) { $events.Json.items.Count } else { 0 }
$results += [pscustomobject]@{ Test = 'events_list'; Pass = [bool]($events.Json.ok -eq $true); Status = $events.Status; Detail = "count=$eventCount" }

$menuLoad = Post-Action @{ action = 'admin_menu_editor_load'; token = $token; sheetType = 'food' }
$results += [pscustomobject]@{ Test = 'admin_menu_editor_load'; Pass = [bool]($menuLoad.Json.ok -eq $true); Status = $menuLoad.Status; Detail = [string]$menuLoad.Json.error }

$cashSummary = Get-Action 'admin_cash_summary'
$cashPass = [bool]($cashSummary.Json.ok -eq $true -or $cashSummary.Json.error -eq 'UNAUTHORIZED' -or $cashSummary.Json.error -eq 'INVALID_TOKEN')
$results += [pscustomobject]@{ Test = 'admin_cash_summary'; Pass = $cashPass; Status = $cashSummary.Status; Detail = [string]$cashSummary.Json.error }

$qrReport = Get-Action 'qr_report'
$qrPass = [bool]($qrReport.Json.ok -eq $true -or $qrReport.Json.error -eq 'INVALID_PASSCODE')
$results += [pscustomobject]@{ Test = 'qr_report'; Pass = $qrPass; Status = $qrReport.Status; Detail = [string]$qrReport.Json.error }

$logout = Post-Action @{ action = 'auth_logout'; token = $token }
$results += [pscustomobject]@{ Test = 'auth_logout'; Pass = [bool]($logout.Json.ok -eq $true); Status = $logout.Status; Detail = [string]$logout.Json.error }

'=== SMOKE TEST RESULTS ==='
$results | Format-Table -AutoSize | Out-String
$passCount = ($results | Where-Object { $_.Pass }).Count
$total = $results.Count
"SUMMARY: pass=$passCount total=$total"
"SMOKE_USER_CREATED: $newUser"
