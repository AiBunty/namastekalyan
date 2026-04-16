param(
    [string]$FtpHost = 'ftp.theboxerp.com',
    [string]$FtpUser = 'nk@namastekalyan.asianwokandgrill.in',
    [string]$FtpPass = 'Zebra@789'
)

$ErrorActionPreference = 'Stop'

Write-Host "=== Alternative FTP Deployment ===" -ForegroundColor Cyan

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
