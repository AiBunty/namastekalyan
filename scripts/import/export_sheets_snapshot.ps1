param(
  [string]$AppsScriptUrl = ""
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$importDir = Join-Path $root 'import-data'
if (!(Test-Path $importDir)) {
  New-Item -Path $importDir -ItemType Directory | Out-Null
}

if ([string]::IsNullOrWhiteSpace($AppsScriptUrl)) {
  $dataConfig = Join-Path (Split-Path -Parent $root) 'data-config.js'
  if (Test-Path $dataConfig) {
    $text = Get-Content $dataConfig -Raw
    $m = [regex]::Match($text, "appsScriptUrl\s*:\s*'([^']+)'")
    if ($m.Success) {
      $AppsScriptUrl = $m.Groups[1].Value.Trim()
    }
  }
}

if ([string]::IsNullOrWhiteSpace($AppsScriptUrl)) {
  throw "Could not resolve appsScriptUrl. Pass -AppsScriptUrl explicitly."
}

$base = $AppsScriptUrl -replace '\?.*$', ''
Write-Host "Using source endpoint: $base"

$tabs = @(
  'AWGNK MENU',
  'BAR MENU NK',
  'EVENTS',
  'Leads',
  'Users'
)

foreach ($tab in $tabs) {
  $url = '{0}?tab={1}&shape=records' -f $base, [uri]::EscapeDataString($tab)
  try {
    $response = Invoke-WebRequest -Uri $url -TimeoutSec 60 -UseBasicParsing
    $slug = ($tab.ToLower() -replace '[^a-z0-9]+', '_').Trim('_')
    if ([string]::IsNullOrWhiteSpace($slug)) { $slug = 'tab' }
    $outFile = Join-Path $importDir ($slug + '.json')
    [System.IO.File]::WriteAllText($outFile, $response.Content, [System.Text.UTF8Encoding]::new($false))
    Write-Host ('Saved {0} -> {1}' -f $tab, $outFile)
  } catch {
    Write-Warning ('Failed to fetch snapshot for tab ' + $tab)
  }
}

Write-Host "Snapshot export complete."
