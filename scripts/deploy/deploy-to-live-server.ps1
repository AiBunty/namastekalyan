# Deploy Controller Files to Live Server via FTP
# Credentials from .env file

param(
    [string]$EnvFile = '.env',
    [string]$FtpHost,
    [string]$FtpUser,
    [string]$FtpPass,
    [string]$FtpPath
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
$envPath = if ([System.IO.Path]::IsPathRooted($EnvFile)) { $EnvFile } else { Join-Path (Split-Path -Parent $scriptDir) $EnvFile }
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
if ([string]::IsNullOrWhiteSpace($FtpPath)) {
    $FtpPath = Get-ProfileValue -Map $envMap -Key 'FTP_REMOTE_PATH' -Profile $profile
}

if ([string]::IsNullOrWhiteSpace($FtpHost) -or [string]::IsNullOrWhiteSpace($FtpUser) -or [string]::IsNullOrWhiteSpace($FtpPass) -or [string]::IsNullOrWhiteSpace($FtpPath)) {
    throw 'Missing FTP configuration. Set the FTP_* values in .env or pass them as parameters.'
}

Write-Host "=== FTP DEPLOYMENT ===" -ForegroundColor Cyan
Write-Host "Using env file: $envPath"
Write-Host "Active profile: $profile"

# Define local files and remote paths
$files = @(
    @{
        local  = "src/Controllers/CashierController.php"
        remote = "src/Controllers/CashierController.php"
    },
    @{
        local  = "src/Controllers/EventController.php"
        remote = "src/Controllers/EventController.php"
    }
)


# Create FTP WebRequest
function Upload-FileViaFTP {
    param(
        [string]$LocalPath,
        [string]$RemotePath,
        [string]$FtpHost,
        [string]$FtpUser,
        [string]$FtpPass
    )
    
    $FtpUrl = "ftp://$FtpHost/$RemotePath"
    
    Write-Host "  Uploading: $LocalPath" -ForegroundColor Cyan
    
    try {
        # Create FTP request
        $FtpRequest = [System.Net.FtpWebRequest]::Create($FtpUrl)
        $FtpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $FtpRequest.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
        $FtpRequest.UseBinary = $true
        $FtpRequest.KeepAlive = $true
        
        # Read file and upload
        $FileBytes = [System.IO.File]::ReadAllBytes($LocalPath)
        $FtpRequest.ContentLength = $FileBytes.Length
        
        $RequestStream = $FtpRequest.GetRequestStream()
        $RequestStream.Write($FileBytes, 0, $FileBytes.Length)
        $RequestStream.Close()
        
        # Get response
        $FtpResponse = $FtpRequest.GetResponse()
        $StatusCode = $FtpResponse.StatusCode
        $FtpResponse.Close()
        
        Write-Host "  OK: Upload completed" -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "  ERROR: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

# Upload files
Write-Host "Uploading files:" -ForegroundColor Yellow

$success = $true
foreach ($file in $files) {
    $localPath = $file.local
    $remotePath = "$FtpPath/$($file.remote)"
    
    if (Test-Path $localPath) {
        $fileSize = "{0:F1}" -f ((Get-Item $localPath).Length / 1024)
        Write-Host "  [$fileSize KB] $localPath" -ForegroundColor White
        
        if (-not (Upload-FileViaFTP -LocalPath $localPath -RemotePath $remotePath -FtpHost $FtpHost -FtpUser $FtpUser -FtpPass $FtpPass)) {
            $success = $false
        }
    } else {
        Write-Host "  ERROR: File not found: $localPath" -ForegroundColor Red
        $success = $false
    }
}

if ($success) {
    Write-Host "Success: All files uploaded" -ForegroundColor Green
    exit 0
} else {
    Write-Host "Error: Some files failed" -ForegroundColor Red
    exit 1
}
