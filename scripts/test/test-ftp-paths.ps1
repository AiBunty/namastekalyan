param(
    [string]$EnvFile = '.env',
    [string]$FtpHost,
    [string]$FtpUser,
    [string]$FtpPass
)

$ErrorActionPreference = 'Stop'

function Get-EnvMap {
    param([string]$Path)

    if (!(Test-Path $Path)) {
        throw "Env file not found: $Path"
    }

    $map = @{}
    $lines = Get-Content -Path $Path -Raw -Encoding UTF8 -ErrorAction Stop
    foreach ($rawLine in ($lines -split "`r?`n")) {
        $line = $rawLine.Trim()
        if ([string]::IsNullOrWhiteSpace($line) -or $line.StartsWith('#')) {
            continue
        }

        $idx = $line.IndexOf('=')
        if ($idx -lt 1) {
            continue
        }

        $key = $line.Substring(0, $idx).Trim()
        $val = $line.Substring($idx + 1).Trim().Trim('"', "'")
        $map[$key] = $val
    }

    return $map
}

function Get-ProfileValue {
    param(
        [hashtable]$Map,
        [string]$Key,
        [string]$Profile
    )

    $profileKey = if ([string]::IsNullOrWhiteSpace($Profile)) { $null } else { "${Key}_$($Profile.ToUpperInvariant())" }
    if ($profileKey -and $Map.ContainsKey($profileKey) -and -not [string]::IsNullOrWhiteSpace([string]$Map[$profileKey])) {
        return [string]$Map[$profileKey]
    }

    if ($Map.ContainsKey($Key) -and -not [string]::IsNullOrWhiteSpace([string]$Map[$Key])) {
        return [string]$Map[$Key]
    }

    return ''
}

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$envPath = if ([System.IO.Path]::IsPathRooted($EnvFile)) { $EnvFile } else { Join-Path (Split-Path -Parent (Split-Path -Parent $scriptDir)) $EnvFile }
$envMap = Get-EnvMap -Path $envPath
$profile = [string]($envMap['NK_ENV_PROFILE'] ?? 'live')

if ([string]::IsNullOrWhiteSpace($FtpHost)) {
    $FtpHost = Get-ProfileValue -Map $envMap -Key 'FTP_HOST' -Profile $profile
}
if ([string]::IsNullOrWhiteSpace($FtpUser)) {
    $FtpUser = Get-ProfileValue -Map $envMap -Key 'FTP_USER' -Profile $profile
}
if ([string]::IsNullOrWhiteSpace($FtpPass)) {
    $FtpPass = Get-ProfileValue -Map $envMap -Key 'FTP_PASS' -Profile $profile
}

if ([string]::IsNullOrWhiteSpace($FtpHost) -or [string]::IsNullOrWhiteSpace($FtpUser) -or [string]::IsNullOrWhiteSpace($FtpPass)) {
    throw 'Missing FTP configuration. Set the FTP_* values in .env or pass them as parameters.'
}

Write-Host "=== Alternative FTP Deployment ===" -ForegroundColor Cyan
Write-Host "Using env file: $envPath"
Write-Host "Active profile: $profile"

function Test-FTPPath {
    param([string]$FtpHost, [string]$FtpUser, [string]$FtpPass, [string]$Path)
    
    try {
        $FtpUrl = "ftp://$FtpHost/$Path"
        $FtpRequest = [System.Net.FtpWebRequest]::Create($FtpUrl)
        $FtpRequest.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectory
        $FtpRequest.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
        $FtpRequest.KeepAlive = $true
        
        $FtpResponse = $FtpRequest.GetResponse()
        $FtpResponse.Close()
        
        Write-Host "Path exists: $Path" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "Path NOT found: $Path" -ForegroundColor Yellow
        return $false
    }
}

Write-Host "`nTesting FTP paths..." -ForegroundColor Cyan
Test-FTPPath $FtpHost $FtpUser $FtpPass "" | Out-Null
Test-FTPPath $FtpHost $FtpUser $FtpPass "namastekalyan" | Out-Null
Test-FTPPath $FtpHost $FtpUser $FtpPass "namastekalyan/backend" | Out-Null

Write-Host "`nNote: The webhook from GitHub should auto-deploy the files soon." -ForegroundColor Yellow
Write-Host "If deployment is needed urgently, please check:" -ForegroundColor Yellow
Write-Host "1. GitHub webhook configuration" -ForegroundColor White
Write-Host "2. Server-side git pull or deployment script" -ForegroundColor White
Write-Host "3. SSH access to manually run: git pull origin main" -ForegroundColor White
