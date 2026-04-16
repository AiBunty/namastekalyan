#!/usr/bin/env pwsh

param(
    [string]$OutputZip = "backend-full-deploy.zip",
    [switch]$ValidateLocal,
    [int]$Port = 8012,
    [string]$ContainerName = "nk-php82-verify",
    [string]$ImageName = "nk-php82-verify:local"
)

$ErrorActionPreference = "Stop"

$repoRoot = $PSScriptRoot
$backendDir = Join-Path $repoRoot 'backend'
$outputPath = Join-Path $repoRoot $OutputZip
$stageRoot = Join-Path $env:TEMP ("nk-backend-deploy-stage-{0}" -f $PID)
$packageDir = Join-Path $stageRoot 'package'
$extractDir = Join-Path $stageRoot 'extract'
$dockerfilePath = Join-Path $stageRoot 'Dockerfile'

function Remove-IfExists {
    param([string]$Path)

    if (Test-Path $Path) {
        Remove-Item $Path -Recurse -Force
    }
}

function Assert-Path {
    param(
        [string]$Path,
        [string]$Message
    )

    if (-not (Test-Path $Path)) {
        throw $Message
    }
}

function Assert-Command {
    param(
        [string]$Name,
        [string]$Message
    )

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw $Message
    }
}

try {
    Assert-Path $backendDir "Backend directory not found: $backendDir"
    Assert-Path (Join-Path $backendDir 'bootstrap.php') 'backend/bootstrap.php is missing.'
    Assert-Path (Join-Path $backendDir 'index.php') 'backend/index.php is missing.'
    Assert-Path (Join-Path $backendDir 'src') 'backend/src is missing.'
    Assert-Path (Join-Path $backendDir 'vendor/autoload.php') 'backend/vendor/autoload.php is missing. Run composer install first.'
    Assert-Path (Join-Path $backendDir 'composer.lock') 'backend/composer.lock is missing. Build should use a locked dependency set.'

    Remove-IfExists $stageRoot
    New-Item -ItemType Directory -Path $packageDir | Out-Null

    Copy-Item $backendDir -Destination $packageDir -Recurse

    $stagedBackendDir = Join-Path $packageDir 'backend'
    Remove-IfExists (Join-Path $stagedBackendDir '.env')
    Remove-IfExists (Join-Path $stagedBackendDir 'logs')
    Remove-IfExists (Join-Path $stagedBackendDir 'composer-local.json')
    Remove-IfExists (Join-Path $stagedBackendDir 'composer-local.lock')
    Remove-IfExists (Join-Path $stagedBackendDir 'composer.phar')

    if (Test-Path $outputPath) {
        try {
            Remove-Item $outputPath -Force
        }
        catch {
            $baseName = [System.IO.Path]::GetFileNameWithoutExtension($OutputZip)
            $extension = [System.IO.Path]::GetExtension($OutputZip)
            $fallbackName = '{0}-{1}{2}' -f $baseName, (Get-Date -Format 'yyyyMMdd-HHmmss'), $extension
            $outputPath = Join-Path $repoRoot $fallbackName
        }
    }

    Compress-Archive -Path (Join-Path $stagedBackendDir '*') -DestinationPath $outputPath -CompressionLevel Optimal

    Write-Host ("Built deployment package: {0}" -f $outputPath) -ForegroundColor Green

    if (-not $ValidateLocal) {
        exit 0
    }

    $envFile = Join-Path $backendDir '.env'
    Assert-Path $envFile 'backend/.env is missing. Local validation requires local env values.'
    Assert-Command 'docker' 'Docker is not available in PATH.'

    docker image inspect $ImageName *> $null
    if ($LASTEXITCODE -ne 0) {
        @"
FROM php:8.2-cli
RUN docker-php-ext-install pdo_mysql
"@ | Set-Content -Path $dockerfilePath -Encoding UTF8

        docker build -t $ImageName $stageRoot | Out-Null
    }

    New-Item -ItemType Directory -Path $extractDir | Out-Null
    Expand-Archive -Path $outputPath -DestinationPath $extractDir -Force

    $containerEnv = Get-Content $envFile
    $containerEnv = $containerEnv -replace '^DB_HOST=.*$', 'DB_HOST=host.docker.internal'
    $containerEnv = $containerEnv -replace '^APP_URL=.*$', ("APP_URL=http://localhost:{0}" -f $Port)
    Set-Content -Path (Join-Path $extractDir '.env') -Value $containerEnv -Encoding UTF8

    docker rm -f $ContainerName 2>$null | Out-Null
    docker create --name $ContainerName -p "${Port}:8000" $ImageName sh -lc "php -S 0.0.0.0:8000 -t /app /app/index.php" | Out-Null
    docker cp (Join-Path $extractDir '.') "${ContainerName}:/app"
    docker start $ContainerName | Out-Null

    curl.exe -sS --retry 8 --retry-connrefused --retry-delay 1 "http://localhost:${Port}/?action=auth_bootstrap_status" | Out-Null

    & (Join-Path $repoRoot 'test-local-api.ps1') -ApiBase "http://localhost:${Port}" -ServerHint "docker run php:8.2-cli"
    if ($LASTEXITCODE -ne 0) {
        throw ("Smoke tests failed for deployment package: {0}" -f $outputPath)
    }

    Write-Host ("Validation passed on PHP 8.2 using container {0} at port {1}." -f $ContainerName, $Port) -ForegroundColor Green
}
finally {
    if ($ValidateLocal) {
        docker rm -f $ContainerName 2>$null | Out-Null
    }

    Remove-IfExists $stageRoot
}