param(
    [string] $BaseUrl = "http://localhost/yalova-kamera",
    [string] $PhpPath = "C:\xampp\php\php.exe",
    [int] $Runs = 3,
    [switch] $AcceptGzip,
    [Parameter(Mandatory = $true)]
    [string[]] $Uri
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

function Join-Url([string] $left, [string] $right) {
    return $left.TrimEnd("/") + "/" + $right.TrimStart("/")
}

function Get-CsrfToken([string] $html) {
    $patterns = @(
        'name="_token"\s+value="([^"]+)"',
        'value="([^"]+)"\s+name="_token"',
        '<meta\s+name="csrf-token"\s+content="([^"]+)"'
    )

    foreach ($pattern in $patterns) {
        $match = [regex]::Match($html, $pattern)
        if ($match.Success) {
            return $match.Groups[1].Value
        }
    }

    throw "CSRF token bulunamadı."
}

function Decode-Slowest([string] $encoded) {
    if ([string]::IsNullOrWhiteSpace($encoded)) {
        return @()
    }

    try {
        $json = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($encoded))
        return @($json | ConvertFrom-Json)
    } catch {
        return @()
    }
}

function Get-HeaderValue($headers, [string] $name, $fallback) {
    $value = $headers[$name]
    if ($null -eq $value -or [string]::IsNullOrWhiteSpace([string] $value)) {
        return $fallback
    }

    return $value
}

Push-Location $root
try {
    if (-not (Test-Path $PhpPath)) {
        throw "PHP bulunamadı: $PhpPath"
    }

    $tempUserJson = & $PhpPath tools\admin_perf_temp_user.php ensure
    $tempUser = $tempUserJson | ConvertFrom-Json

    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginUrl = Join-Url $BaseUrl "yonetici-giris"
    $loginPage = Invoke-WebRequest -Uri $loginUrl -WebSession $session -UseBasicParsing -TimeoutSec 60
    $token = Get-CsrfToken $loginPage.Content

    $loginBody = @{
        "_token" = $token
        "kullanici_adi_veya_eposta" = $tempUser.email
        "sifre" = $tempUser.password
    }

    Invoke-WebRequest `
        -Uri $loginUrl `
        -Method Post `
        -Body $loginBody `
        -WebSession $session `
        -UseBasicParsing `
        -MaximumRedirection 5 `
        -TimeoutSec 60 | Out-Null

    $headers = @{ "X-Admin-Performance-Probe" = "1" }
    if ($AcceptGzip) {
        $headers["Accept-Encoding"] = "gzip"
    }

    foreach ($currentUri in $Uri) {
        for ($run = 1; $run -le $Runs; $run++) {
            $sw = [Diagnostics.Stopwatch]::StartNew()
            try {
                $response = Invoke-WebRequest `
                    -Uri (Join-Url $BaseUrl $currentUri) `
                    -WebSession $session `
                    -Headers $headers `
                    -UseBasicParsing `
                    -MaximumRedirection 5 `
                    -TimeoutSec 120
                $sw.Stop()

                $slowest = Decode-Slowest ($response.Headers["X-Admin-Perf-Slowest"])
                [pscustomobject]@{
                    uri = $currentUri
                    run = $run
                    http_ms = [math]::Round($sw.Elapsed.TotalMilliseconds, 2)
                    app_ms = [double] (Get-HeaderValue $response.Headers "X-Admin-Perf-App-Ms" 0)
                    query_count = [int] (Get-HeaderValue $response.Headers "X-Admin-Perf-Queries" 0)
                    query_ms = [double] (Get-HeaderValue $response.Headers "X-Admin-Perf-Query-Ms" 0)
                    gzip_ms = [double] (Get-HeaderValue $response.Headers "X-Admin-Gzip-Ms" 0)
                    raw_kb = [math]::Round(([double] (Get-HeaderValue $response.Headers "X-Admin-Gzip-Raw-Bytes" 0)) / 1024, 1)
                    gzip_kb = [math]::Round(([double] (Get-HeaderValue $response.Headers "X-Admin-Gzip-Bytes" 0)) / 1024, 1)
                    content_kb = [math]::Round(([System.Text.Encoding]::UTF8.GetByteCount($response.Content)) / 1024, 1)
                    status = [int] $response.StatusCode
                    slowest = (($slowest | ForEach-Object {
                        $bindings = if ($_.bindings) { "[" + (($_.bindings | ForEach-Object { [string] $_ }) -join ",") + "]" } else { "[]" }
                        ("{0} ms: {1} {2}" -f $_.ms, $_.sql, $bindings)
                    }) -join " || ")
                }
            } catch {
                $sw.Stop()

                if (-not $_.Exception.Response) {
                    throw
                }

                [pscustomobject]@{
                    uri = $currentUri
                    run = $run
                    http_ms = [math]::Round($sw.Elapsed.TotalMilliseconds, 2)
                    app_ms = 0
                    query_count = 0
                    query_ms = 0
                    gzip_ms = 0
                    raw_kb = 0
                    gzip_kb = 0
                    content_kb = 0
                    status = [int] $_.Exception.Response.StatusCode
                    slowest = $_.Exception.Message
                }
            }
        }
    }
} finally {
    Pop-Location
}
