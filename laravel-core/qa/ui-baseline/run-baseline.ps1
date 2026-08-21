[CmdletBinding()]
param(
    [switch]$UpdateBaseline,
    [string]$OutputDirectory
)

$ErrorActionPreference = 'Stop'
$OutputEncoding = [System.Text.UTF8Encoding]::new($false)
[Console]::InputEncoding = [System.Text.UTF8Encoding]::new($false)
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)

$qaDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$laravelDirectory = (Resolve-Path (Join-Path $qaDirectory '..\..')).Path
$baseUrl = if ($env:QA_BASE_URL) { $env:QA_BASE_URL.TrimEnd('/') } else { 'http://127.0.0.1:8000' }
$phpPath = if ($env:QA_PHP_PATH) { $env:QA_PHP_PATH } else { 'C:\xampp\php\php.exe' }
$baselineReport = Join-Path $qaDirectory 'report.json'

if (-not [string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $artifactDirectory = [System.IO.Path]::GetFullPath((Join-Path (Get-Location) $OutputDirectory))
    $runRole = 'candidate'
} elseif ($UpdateBaseline -or -not (Test-Path -LiteralPath $baselineReport)) {
    $artifactDirectory = $qaDirectory
    $runRole = 'baseline'
} else {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $artifactDirectory = Join-Path $qaDirectory "runs\$stamp"
    $runRole = 'candidate'
}

$qaPrefix = $qaDirectory.TrimEnd('\') + '\'
if ($artifactDirectory -ne $qaDirectory -and -not $artifactDirectory.StartsWith($qaPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Artefact klasörü QA alanı dışında olamaz: $artifactDirectory"
}

$env:QA_OUTPUT_DIR = $artifactDirectory
$env:QA_RUN_ROLE = $runRole

$required = @(
    'QA_MANAGER_USER',
    'QA_MANAGER_PASSWORD',
    'QA_TENANT_CODE',
    'QA_TENANT_USER',
    'QA_TENANT_PASSWORD'
)

$missing = @($required | Where-Object { [string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($_)) })
if ($missing.Count -gt 0) {
    throw "Eksik QA ortam değişkenleri: $($missing -join ', ')"
}

if (-not (Test-Path -LiteralPath $phpPath)) {
    throw "PHP bulunamadı: $phpPath"
}

$serverProcess = $null

try {
    try {
        $null = Invoke-WebRequest -Uri "$baseUrl/yonetici-giris" -Method Get -TimeoutSec 4 -UseBasicParsing
    } catch {
        $uri = [Uri]$baseUrl
        if ($uri.Host -notin @('127.0.0.1', 'localhost')) {
            throw "Otomatik sunucu başlatma yalnız localhost için destekleniyor: $baseUrl"
        }

        $serverArguments = @{
            FilePath = $phpPath
            ArgumentList = @('artisan', 'serve', '--host=127.0.0.1', "--port=$($uri.Port)")
            WorkingDirectory = $laravelDirectory
            WindowStyle = 'Hidden'
            PassThru = $true
        }
        $serverProcess = Start-Process @serverArguments

        $ready = $false
        for ($attempt = 0; $attempt -lt 20; $attempt++) {
            Start-Sleep -Milliseconds 500
            try {
                $response = Invoke-WebRequest -Uri "$baseUrl/yonetici-giris" -Method Get -TimeoutSec 2 -UseBasicParsing
                if ($response.StatusCode -eq 200) {
                    $ready = $true
                    break
                }
            } catch {
                # Sunucunun hazır olmasını bekle.
            }
        }

        if (-not $ready) {
            throw 'Laravel geliştirme sunucusu zamanında hazır olmadı.'
        }
    }

    Push-Location $laravelDirectory
    try {
        & node (Join-Path $qaDirectory 'capture-baseline.mjs')
        if ($LASTEXITCODE -ne 0) {
            throw "Baseline aracı $LASTEXITCODE çıkış koduyla tamamlandı."
        }

        if ($runRole -eq 'candidate' -and (Test-Path -LiteralPath $baselineReport)) {
            & (Join-Path $qaDirectory 'compare-baseline.ps1') -CandidateDirectory $artifactDirectory
            if ($LASTEXITCODE -ne 0) {
                throw "Baseline karşılaştırması $LASTEXITCODE çıkış koduyla tamamlandı."
            }
        }
    } finally {
        Pop-Location
    }
} finally {
    if ($null -ne $serverProcess -and -not $serverProcess.HasExited) {
        Stop-Process -Id $serverProcess.Id -Force
    }
}

Write-Output "QA artefact klasörü: $artifactDirectory"
