param(
  [string]$Url = '',

  [switch]$FromConfig,

  [switch]$OutputJson
)

$ErrorActionPreference = 'Stop'

Set-StrictMode -Version Latest

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent $scriptRoot

function Get-ConfiguredUrl {
  $configPath = Join-Path $repoRoot 'data-config.js'
  if (-not (Test-Path $configPath)) {
    throw ('Config file not found: {0}' -f $configPath)
  }

  $configText = Get-Content -Raw -Path $configPath
  $match = [regex]::Match($configText, "appsScriptUrl:\s*'(?<url>[^']+)'")
  if (-not $match.Success) {
    throw 'Could not find appsScriptUrl in data-config.js.'
  }

  return $match.Groups['url'].Value.Trim()
}

function Invoke-Action {
  param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,

    [Parameter(Mandatory = $true)]
    [string]$Action
  )

  $uriBuilder = [System.UriBuilder]$BaseUrl
  $query = [System.Web.HttpUtility]::ParseQueryString($uriBuilder.Query)
  $query['action'] = $Action
  $uriBuilder.Query = $query.ToString()

  function Get-HeaderValue {
    param([object]$Headers, [string]$Name)

    if ($null -eq $Headers) {
      return ''
    }

    try {
      $values = $Headers.GetValues($Name)
      if ($values) {
        return [string]($values -join ', ')
      }
    }
    catch {
    }

    try {
      return [string]$Headers[$Name]
    }
    catch {
      return ''
    }
  }

  try {
    $response = Invoke-WebRequest -Uri $uriBuilder.Uri.AbsoluteUri -Method Get -Headers @{ Accept = 'application/json, text/plain, text/html' }
    $text = $response.Content
    $statusCode = [int]$response.StatusCode
    $contentType = Get-HeaderValue -Headers $response.Headers -Name 'Content-Type'
  }
  catch {
    $errorResponse = $_.Exception.Response
    $statusCode = if ($errorResponse -and $errorResponse.StatusCode) { [int]$errorResponse.StatusCode } else { 0 }
    $contentType = if ($errorResponse -and $errorResponse.Headers) { Get-HeaderValue -Headers $errorResponse.Headers -Name 'Content-Type' } else { '' }
    $text = [string]$_.Exception.Message

    if ($errorResponse -and $errorResponse.Content) {
      $text = [string]$errorResponse.Content
    }
  }

  $json = $null
  try {
    $json = $text | ConvertFrom-Json
  }
  catch {
    $json = $null
  }

  return [ordered]@{
    action = $Action
    statusCode = $statusCode
    contentType = $contentType
    isJson = $null -ne $json
    json = $json
    bodyPreview = if ($text.Length -gt 500) { $text.Substring(0, 500) } else { $text }
  }
}

function Get-TotalScans {
  param([object]$Payload)

  if ($null -eq $Payload) {
    return $null
  }

  if ($Payload.PSObject.Properties.Name -contains 'totalScans') {
    return [int]$Payload.totalScans
  }

  if (($Payload.PSObject.Properties.Name -contains 'data') -and ($Payload.data -ne $null) -and ($Payload.data.PSObject.Properties.Name -contains 'totalScans')) {
    return [int]$Payload.data.totalScans
  }

  return $null
}

Add-Type -AssemblyName System.Web

if ($FromConfig -or [string]::IsNullOrWhiteSpace($Url)) {
  $Url = Get-ConfiguredUrl
}

if ([string]::IsNullOrWhiteSpace($Url)) {
  throw 'No Apps Script URL provided.'
}

$result = [ordered]@{
  ok = $false
  url = $Url
  checks = @()
}

$before = Invoke-Action -BaseUrl $Url -Action 'qr_report'
$result.checks += $before

if (-not $before.isJson) {
  $result.error = 'qr_report did not return JSON. The deployment URL is not serving the web app publicly.'
  if ($OutputJson) {
    $result | ConvertTo-Json -Depth 8
    exit 1
  }

  $result | ConvertTo-Json -Depth 8
  exit 1
}

$ensure = Invoke-Action -BaseUrl $Url -Action 'ensure_qr_sheet'
$result.checks += $ensure

$insert = Invoke-Action -BaseUrl $Url -Action 'add_test_qr_scan'
$result.checks += $insert

if (-not $insert.isJson) {
  $result.error = 'add_test_qr_scan did not return JSON. The deployment URL is not serving the web app publicly.'
  if ($OutputJson) {
    $result | ConvertTo-Json -Depth 8
    exit 1
  }

  $result | ConvertTo-Json -Depth 8
  exit 1
}

$after = Invoke-Action -BaseUrl $Url -Action 'qr_report'
$result.checks += $after

if (-not $after.isJson) {
  $result.error = 'Second qr_report did not return JSON. The deployment URL is not serving the web app publicly.'
  if ($OutputJson) {
    $result | ConvertTo-Json -Depth 8
    exit 1
  }

  $result | ConvertTo-Json -Depth 8
  exit 1
}

$beforeCount = Get-TotalScans -Payload $before.json
$afterCount = Get-TotalScans -Payload $after.json

$result.beforeCount = $beforeCount
$result.afterCount = $afterCount
$result.ok = ($beforeCount -ne $null -and $afterCount -ne $null -and $afterCount -ge ($beforeCount + 1))

if (-not $result.ok) {
  $result.error = 'QR report responded, but the scan count did not increase after the dummy scan.'
}

$jsonResult = $result | ConvertTo-Json -Depth 8
if ($OutputJson) {
  $jsonResult
}
else {
  Write-Host $jsonResult
}

if (-not $result.ok) {
  exit 1
}