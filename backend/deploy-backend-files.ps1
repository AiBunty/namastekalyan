[CmdletBinding(SupportsShouldProcess = $true)]
param(
  [string]$EnvFile = ".env",
  [string]$RemotePath,
  [string[]]$Files = @("diag.php", "setup.php", ".htaccess", ".env"),
  [switch]$SkipChmod
)

$ErrorActionPreference = "Stop"

function Get-EnvMap {
  param([string]$Path)

  if (!(Test-Path $Path)) {
    throw "Env file not found: $Path"
  }

  $map = @{}
  $lines = Get-Content -Path $Path -Raw -Encoding UTF8 -ErrorAction Stop
  foreach ($rawLine in ($lines -split "`r?`n")) {
    $line = $rawLine.Trim()
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    if ($line.StartsWith("#")) { continue }

    $idx = $line.IndexOf("=")
    if ($idx -lt 1) { continue }

    $key = $line.Substring(0, $idx).Trim()
    $val = $line.Substring($idx + 1).Trim()

    if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
      if ($val.Length -ge 2) {
        $val = $val.Substring(1, $val.Length - 2)
      }
    }

    $map[$key] = $val
  }

  return $map
}

function Require-Key {
  param(
    [hashtable]$Map,
    [string]$Key
  )

  if (-not $Map.ContainsKey($Key) -or [string]::IsNullOrWhiteSpace([string]$Map[$Key])) {
    throw "Missing required key in .env: $Key"
  }

  return [string]$Map[$Key]
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$envPath = if ([System.IO.Path]::IsPathRooted($EnvFile)) { $EnvFile } else { Join-Path $scriptDir $EnvFile }

$envMap = Get-EnvMap -Path $envPath

$ftpHost = Require-Key -Map $envMap -Key "FTP_HOST"
$ftpUser = Require-Key -Map $envMap -Key "FTP_USER"
$ftpPass = Require-Key -Map $envMap -Key "FTP_PASS"
$remoteBase = if ($PSBoundParameters.ContainsKey("RemotePath") -and -not [string]::IsNullOrWhiteSpace($RemotePath)) {
  $RemotePath
} else {
  Require-Key -Map $envMap -Key "FTP_REMOTE_PATH"
}

$ftpHost = $ftpHost.Trim()
if (-not $ftpHost.StartsWith("ftp://") -and -not $ftpHost.StartsWith("ftps://")) {
  $ftpHost = "ftp://$ftpHost"
}

$remoteBase = $remoteBase.Trim([char]'/', [char]92)
$auth = "$ftpUser`:$ftpPass"

Write-Host "Using env file: $envPath"
Write-Host "FTP host: $ftpHost"
Write-Host "Remote path: $remoteBase"

foreach ($file in $Files) {
  $localPath = if ([System.IO.Path]::IsPathRooted($file)) { $file } else { Join-Path $scriptDir $file }
  if (!(Test-Path $localPath)) {
    throw "Local file not found: $localPath"
  }

  $remoteName = Split-Path -Leaf $localPath
  $targetUrl = "$ftpHost/$remoteBase/$remoteName"

  if ($PSCmdlet.ShouldProcess($targetUrl, "Upload $localPath")) {
    Write-Host "Uploading $localPath -> $targetUrl"
    & curl.exe --silent --show-error --fail --ftp-create-dirs -u $auth -T $localPath $targetUrl | Out-Null
  }
}

if (-not $SkipChmod) {
  $chmodMap = @{
    "diag.php" = "644"
    "setup.php" = "644"
    ".env" = "600"
  }

  foreach ($entry in $chmodMap.GetEnumerator()) {
    $name = $entry.Key
    $mode = $entry.Value
    if ($Files -notcontains $name) { continue }

    $quote = "SITE CHMOD $mode $remoteBase/$name"
    if ($PSCmdlet.ShouldProcess($quote, "Apply remote permission")) {
      Write-Host "Applying permission: $quote"
      & curl.exe --silent --show-error -u $auth --quote $quote "$ftpHost/" | Out-Null
    }
  }
}

Write-Host "Deployment complete."
