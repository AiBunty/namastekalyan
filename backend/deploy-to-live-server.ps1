# Deploy Controller Files to Live Server via FTP
# Credentials from .env file

param(
    [string]$FtpHost = 'ftp.theboxerp.com',
    [string]$FtpUser = 'nk@namastekalyan.asianwokandgrill.in',
    [string]$FtpPass = 'Zebra@789',
    [string]$FtpPath = 'namastekalyan/backend'
)

$ErrorActionPreference = 'Stop'

Write-Host "=== FTP DEPLOYMENT ===" -ForegroundColor Cyan

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
