param(
  [ValidateSet('redeploy', 'new')]
  [string]$Mode = 'redeploy',

  [string]$DeploymentId = '',

  [string]$Description = '',

  [switch]$SkipPush,

  [switch]$UpdateConfig,

  [switch]$SmokeTest,

  [string]$ConfigPath = ''
)

$ErrorActionPreference = 'Stop'

Set-StrictMode -Version Latest

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent $scriptRoot
$resolvedConfigPath = ''
$originalConfigText = $null

if ([string]::IsNullOrWhiteSpace($Description)) {
  $Description = 'Apps Script deploy ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
}

if (-not $SkipPush) {
  Write-Host 'Pushing local Apps Script files...'
  & clasp push -f
  if ($LASTEXITCODE -ne 0) {
    throw 'clasp push failed.'
  }
}

Write-Host ('Creating version: {0}' -f $Description)
& clasp version $Description
if ($LASTEXITCODE -ne 0) {
  throw 'clasp version failed.'
}

$versions = & clasp --json versions | ConvertFrom-Json
if (-not $versions) {
  throw 'Unable to load clasp versions after version creation.'
}

$latestVersion = ($versions | Sort-Object versionNumber -Descending | Select-Object -First 1).versionNumber
if (-not $latestVersion) {
  throw 'Unable to determine the latest Apps Script version number.'
}

if ($Mode -eq 'redeploy') {
  if ([string]::IsNullOrWhiteSpace($DeploymentId)) {
    throw 'DeploymentId is required in redeploy mode.'
  }

  Write-Host ('Redeploying deployment {0} to version {1}...' -f $DeploymentId, $latestVersion)
  $deployment = & clasp --json redeploy $DeploymentId -V $latestVersion -d $Description | ConvertFrom-Json
  if ($LASTEXITCODE -ne 0) {
    throw 'clasp redeploy failed.'
  }
}
else {
  Write-Host ('Creating a new deployment for version {0}...' -f $latestVersion)
  $deployment = & clasp --json deploy -V $latestVersion -d $Description | ConvertFrom-Json
  if ($LASTEXITCODE -ne 0) {
    throw 'clasp deploy failed.'
  }
}

$resolvedDeploymentId = [string]$deployment.deploymentId
if ([string]::IsNullOrWhiteSpace($resolvedDeploymentId)) {
  throw 'clasp did not return a deploymentId.'
}

$execUrl = 'https://script.google.com/macros/s/{0}/exec' -f $resolvedDeploymentId

if ($UpdateConfig) {
  $resolvedConfigPath = if ([string]::IsNullOrWhiteSpace($ConfigPath)) {
    Join-Path $repoRoot 'data-config.js'
  }
  else {
    if ([System.IO.Path]::IsPathRooted($ConfigPath)) {
      $ConfigPath
    }
    else {
      Join-Path $repoRoot $ConfigPath
    }
  }

  if (-not (Test-Path $resolvedConfigPath)) {
    throw ('Config file not found: {0}' -f $resolvedConfigPath)
  }

  $configText = Get-Content -Raw -Path $resolvedConfigPath
  $originalConfigText = $configText
  $updatedText = [regex]::Replace(
    $configText,
    "appsScriptUrl:\s*'[^']*'",
    ("appsScriptUrl: '{0}'" -f $execUrl),
    1
  )

  if ($updatedText -eq $configText) {
    throw ('Could not update appsScriptUrl in {0}' -f $resolvedConfigPath)
  }

  Set-Content -Path $resolvedConfigPath -Value $updatedText -NoNewline
  Write-Host ('Updated data config: {0}' -f $resolvedConfigPath)
}

$result = [ordered]@{
  mode = $Mode
  versionNumber = $latestVersion
  deploymentId = $resolvedDeploymentId
  execUrl = $execUrl
  description = $Description
}

if ($SmokeTest) {
  $testScriptPath = Join-Path $scriptRoot 'Test-AppScript.ps1'
  if (-not (Test-Path $testScriptPath)) {
    throw ('Smoke test script not found: {0}' -f $testScriptPath)
  }

  Write-Host 'Running smoke test against deployed web app...'
  $testJson = & pwsh -NoProfile -ExecutionPolicy Bypass -File $testScriptPath -Url $execUrl -OutputJson
  if ($LASTEXITCODE -ne 0) {
    if ($UpdateConfig -and -not [string]::IsNullOrWhiteSpace($resolvedConfigPath) -and $null -ne $originalConfigText) {
      Set-Content -Path $resolvedConfigPath -Value $originalConfigText -NoNewline
      Write-Host ('Smoke test failed. Restored previous Apps Script URL in {0}' -f $resolvedConfigPath)
    }

    if ($testJson) {
      Write-Host $testJson
    }

    throw 'Smoke test failed.'
  }

  $result.smokeTest = ($testJson | ConvertFrom-Json)
}

$result | ConvertTo-Json -Depth 8