param(
    [string] $BaseUrl = "http://localhost/yalova-kamera",
    [string] $PhpPath = "C:\xampp\php\php.exe",
    [int] $Runs = 3,
    [string[]] $OnlyUri = @(),
    [switch] $CleanupTempUser
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$resultCsv = Join-Path $root "tools\admin-tenant-performance-results-$timestamp.csv"
$resultJson = Join-Path $root "tools\admin-tenant-performance-results-$timestamp.json"
$latestCsv = Join-Path $root "tools\admin-tenant-performance-results-latest.csv"

function Join-Url([string] $left, [string] $right) {
    return $left.TrimEnd("/") + "/" + $right.TrimStart("/")
}

function Get-ModuleName([string] $uri) {
    $parts = $uri -split "/"
    if ($parts.Count -le 1) {
        return "dashboard"
    }

    return $parts[1]
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

function Invoke-PostNoRedirect([string] $url, [hashtable] $body, [System.Net.CookieContainer] $cookies) {
    $pairs = foreach ($key in $body.Keys) {
        [System.Uri]::EscapeDataString([string] $key) + "=" + [System.Uri]::EscapeDataString([string] $body[$key])
    }
    $payload = [string]::Join("&", $pairs)
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)

    $request = [System.Net.HttpWebRequest] [System.Net.WebRequest]::Create($url)
    $request.Method = "POST"
    $request.AllowAutoRedirect = $false
    $request.CookieContainer = $cookies
    $request.ContentType = "application/x-www-form-urlencoded"
    $request.ContentLength = $bytes.Length
    $request.Timeout = 60000

    $stream = $request.GetRequestStream()
    try {
        $stream.Write($bytes, 0, $bytes.Length)
    } finally {
        $stream.Close()
    }

    try {
        return $request.GetResponse()
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) {
            return $_.Exception.Response
        }

        throw
    }
}

if (-not (Test-Path $PhpPath)) {
    throw "PHP bulunamadı: $PhpPath"
}

$defaultUris = @(
    "admin/firma-ayarlari",
    "admin/muhasebe/satis/barkodlu-satis-ayarlar",
    "admin/muhasebe/satis/barkodlu-satis-fis-sablonlari"
)

$uris = @($OnlyUri | ForEach-Object { ([string] $_) -split "," } | Where-Object { $_ -ne "" } | ForEach-Object { $_.Trim("/") })
if ($uris.Count -eq 0) {
    $uris = $defaultUris
}

Push-Location $root
try {
    Write-Host "Geçici performans tenant kullanıcısı hazırlanıyor..."
    $tenantJson = & $PhpPath tools\admin_perf_temp_tenant_user.php ensure
    $tenant = $tenantJson | ConvertFrom-Json

    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginUrl = Join-Url $BaseUrl "giris"
    $loginPage = Invoke-WebRequest -Uri $loginUrl -WebSession $session -UseBasicParsing -TimeoutSec 60
    $token = Get-CsrfToken $loginPage.Content

    $loginBody = @{
        "_token" = $token
        "firma_kodu" = $tenant.firma_kodu
        "kullanici_adi_veya_eposta" = $tenant.email
        "sifre" = $tenant.password
    }

    $loginResponse = Invoke-PostNoRedirect $loginUrl $loginBody $session.Cookies
    try {
        $loginStatus = [int] $loginResponse.StatusCode
        if ($loginStatus -lt 300 -or $loginStatus -ge 400) {
            throw "Tenant login POST beklenen redirect yerine HTTP $loginStatus döndürdü."
        }
    } finally {
        $loginResponse.Close()
    }

    Write-Host ("Tenant firma: {0}, rol: {1}" -f $tenant.firma_kodu, $tenant.rol_kod)
    Write-Host ("Tenant ölçülecek sayfa: {0}" -f $uris.Count)
    Write-Host ""

    $results = New-Object System.Collections.Generic.List[object]
    $index = 0

    foreach ($uri in $uris) {
        $index++
        $url = Join-Url $BaseUrl $uri
        $durations = @()
        $status = $null
        $bytes = 0
        $finalUrl = $null
        $errorText = $null

        for ($run = 1; $run -le $Runs; $run++) {
            $sw = [Diagnostics.Stopwatch]::StartNew()
            try {
                $response = Invoke-WebRequest -Uri $url -WebSession $session -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 120
                $sw.Stop()
                $durations += $sw.Elapsed.TotalMilliseconds
                $status = [int] $response.StatusCode
                $bytes = $response.Content.Length
                $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
            } catch {
                $sw.Stop()
                $durations += $sw.Elapsed.TotalMilliseconds
                $errorText = $_.Exception.Message
                if ($_.Exception.Response) {
                    $status = [int] $_.Exception.Response.StatusCode
                    $finalUrl = $_.Exception.Response.ResponseUri.AbsoluteUri
                }
            }
        }

        $avg = [math]::Round(($durations | Measure-Object -Average).Average, 2)
        $min = [math]::Round(($durations | Measure-Object -Minimum).Minimum, 2)
        $max = [math]::Round(($durations | Measure-Object -Maximum).Maximum, 2)
        $module = Get-ModuleName $uri

        $row = [pscustomobject]@{
            rank = $null
            module = $module
            uri = $uri
            status = $status
            avg_ms = $avg
            min_ms = $min
            max_ms = $max
            runs_ms = ($durations | ForEach-Object { [math]::Round($_, 2) }) -join "|"
            bytes = $bytes
            final_url = $finalUrl
            error = $errorText
        }
        $results.Add($row) | Out-Null

        Write-Host ("[{0}/{1}] {2} | {3} | {4} ms | max {5} ms | HTTP {6}" -f $index, $uris.Count, $module, $uri, $avg, $max, $status)
    }

    $ranked = @($results | Sort-Object avg_ms -Descending)
    for ($i = 0; $i -lt $ranked.Count; $i++) {
        $ranked[$i].rank = $i + 1
    }

    $ranked | Export-Csv -Path $resultCsv -Encoding UTF8 -NoTypeInformation
    $ranked | Export-Csv -Path $latestCsv -Encoding UTF8 -NoTypeInformation
    $ranked | ConvertTo-Json -Depth 5 | Set-Content -Path $resultJson -Encoding UTF8

    Write-Host ""
    Write-Host "Tenant en yavaş sayfalar:"
    $ranked | Select-Object rank,module,uri,avg_ms,max_ms,status | Format-Table -AutoSize
    Write-Host "CSV: $resultCsv"
    Write-Host "JSON: $resultJson"
} finally {
    if ($CleanupTempUser) {
        Write-Host "Geçici performans tenant kullanıcısı temizleniyor..."
        & $PhpPath tools\admin_perf_temp_tenant_user.php delete | Out-Null
    }
    Pop-Location
}
