param(
    [string] $BaseUrl = "http://localhost/yalova-kamera",
    [string] $PhpPath = "C:\xampp\php\php.exe",
    [int] $Runs = 1,
    [string[]] $OnlyUri = @(),
    [switch] $UseFixtureRecords,
    [switch] $KeepFixtureRecords,
    [switch] $Probe,
    [switch] $CleanupTempUser
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$samplesPath = Join-Path $root "tools\admin-parameter-route-samples.json"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$resultCsv = Join-Path $root "tools\admin-parameter-performance-results-$timestamp.csv"
$resultJson = Join-Path $root "tools\admin-parameter-performance-results-$timestamp.json"
$latestCsv = Join-Path $root "tools\admin-parameter-performance-results-latest.csv"
$summaryCsv = Join-Path $root "tools\admin-parameter-performance-module-summary-latest.csv"

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
        $response = $request.GetResponse()
    } catch [System.Net.WebException] {
        if ($_.Exception.Response) {
            return $_.Exception.Response
        }

        throw
    }

    return $response
}

if (-not (Test-Path $PhpPath)) {
    throw "PHP bulunamadı: $PhpPath"
}

Push-Location $root
try {
    if ($UseFixtureRecords) {
        Write-Host "Geçici performans fixture kayıtları hazırlanıyor..."
        & $PhpPath tools\admin_perf_fixture_records.php ensure | Out-Null
    }

    Write-Host "Parametreli route örnekleri hazırlanıyor..."
    & $PhpPath tools\admin_parameter_route_samples.php | Set-Content -Path $samplesPath -Encoding UTF8
    $samplesJson = Get-Content $samplesPath -Raw -Encoding UTF8 | ConvertFrom-Json
    $samples = @(foreach ($sample in $samplesJson) { $sample })
    $measurable = @(foreach ($sample in $samples) {
        if ($sample.status -eq "ok" -and -not [string]::IsNullOrWhiteSpace($sample.uri)) {
            $sample
        }
    })

    $onlyUris = @($OnlyUri | ForEach-Object { ([string] $_) -split "," } | Where-Object { $_ -ne "" })
    if ($onlyUris.Count -gt 0) {
        $wanted = @{}
        foreach ($uri in $onlyUris) {
            $wanted[$uri.Trim("/")] = $true
        }

        $measurable = @($measurable | Where-Object {
            $wanted.ContainsKey($_.uri.Trim("/")) -or $wanted.ContainsKey($_.uri_template.Trim("/"))
        })
    }

    Write-Host ("Parametreli admin route: {0}" -f $samples.Count)
    Write-Host ("Gerçek kayıtla ölçülecek sayfa: {0}" -f $measurable.Count)
    Write-Host ("Kayıt bulunamadığı için atlanan: {0}" -f (@($samples | Where-Object { $_.status -ne "ok" }).Count))
    Write-Host ""
    Write-Host "Modül sayıları:"
    $measurable |
        Group-Object { Get-ModuleName $_.uri } |
        Sort-Object Count -Descending |
        ForEach-Object { Write-Host ("- {0}: {1}" -f $_.Name, $_.Count) }

    Write-Host ""
    Write-Host "Geçici performans admin kullanıcısı hazırlanıyor..."
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

    $loginResponse = Invoke-PostNoRedirect $loginUrl $loginBody $session.Cookies
    try {
        $loginStatus = [int] $loginResponse.StatusCode
        if ($loginStatus -lt 300 -or $loginStatus -ge 400) {
            throw "Login POST beklenen redirect yerine HTTP $loginStatus döndürdü."
        }
    } finally {
        $loginResponse.Close()
    }

    $authCheck = Invoke-WebRequest -Uri (Join-Url $BaseUrl "admin") -WebSession $session -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 90
    if ($authCheck.StatusCode -ne 200 -or $authCheck.BaseResponse.ResponseUri.AbsoluteUri -like "*/giris*") {
        throw "Admin oturumu doğrulanamadı. Son URL: $($authCheck.BaseResponse.ResponseUri.AbsoluteUri)"
    }

    Write-Host ""
    Write-Host "Parametreli sayfa açılış ölçümü başlıyor..."
    $results = New-Object System.Collections.Generic.List[object]
    $index = 0

    foreach ($sample in ($measurable | Sort-Object uri)) {
        $index++
        $url = Join-Url $BaseUrl $sample.uri
        $durations = @()
        $status = $null
        $bytes = 0
        $finalUrl = $null
        $errorText = $null
        $probeAppMs = $null
        $probeQueryCount = $null
        $probeQueryMs = $null
        $probeSlowest = $null
        $headers = @{}
        if ($Probe) {
            $headers["X-Admin-Performance-Probe"] = "1"
        }

        for ($run = 1; $run -le $Runs; $run++) {
            $sw = [Diagnostics.Stopwatch]::StartNew()
            try {
                $response = Invoke-WebRequest -Uri $url -WebSession $session -Headers $headers -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 120
                $sw.Stop()
                $durations += $sw.Elapsed.TotalMilliseconds
                $status = [int] $response.StatusCode
                $bytes = $response.Content.Length
                $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
                if ($Probe) {
                    $probeAppMs = $response.Headers["X-Admin-Perf-App-Ms"]
                    $probeQueryCount = $response.Headers["X-Admin-Perf-Queries"]
                    $probeQueryMs = $response.Headers["X-Admin-Perf-Query-Ms"]
                    $probeSlowest = $response.Headers["X-Admin-Perf-Slowest"]
                }
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
        $module = Get-ModuleName $sample.uri

        $row = [pscustomobject]@{
            rank = $null
            module = $module
            uri = $sample.uri
            uri_template = $sample.uri_template
            name = $sample.name
            action = $sample.action
            model = $sample.model
            record_key = $sample.record_key
            status = $status
            avg_ms = $avg
            min_ms = $min
            max_ms = $max
            runs_ms = ($durations | ForEach-Object { [math]::Round($_, 2) }) -join "|"
            bytes = $bytes
            final_url = $finalUrl
            error = $errorText
            probe_app_ms = $probeAppMs
            probe_query_count = $probeQueryCount
            probe_query_ms = $probeQueryMs
            probe_slowest_b64 = $probeSlowest
        }
        $results.Add($row) | Out-Null

        Write-Host ("[{0}/{1}] {2} | {3} | {4} ms | HTTP {5}" -f $index, $measurable.Count, $module, $sample.uri, $avg, $status)
    }

    $ranked = @($results | Sort-Object avg_ms -Descending)
    for ($i = 0; $i -lt $ranked.Count; $i++) {
        $ranked[$i].rank = $i + 1
    }

    $ranked | Export-Csv -Path $resultCsv -Encoding UTF8 -NoTypeInformation
    $ranked | Export-Csv -Path $latestCsv -Encoding UTF8 -NoTypeInformation
    $ranked | ConvertTo-Json -Depth 5 | Set-Content -Path $resultJson -Encoding UTF8

    $ranked |
        Group-Object module |
        ForEach-Object {
            $times = $_.Group | Select-Object -ExpandProperty avg_ms
            [pscustomobject]@{
                module = $_.Name
                page_count = $_.Count
                avg_ms = [math]::Round(($times | Measure-Object -Average).Average, 2)
                slowest_ms = [math]::Round(($times | Measure-Object -Maximum).Maximum, 2)
                fastest_ms = [math]::Round(($times | Measure-Object -Minimum).Minimum, 2)
            }
        } |
        Sort-Object slowest_ms -Descending |
        Export-Csv -Path $summaryCsv -Encoding UTF8 -NoTypeInformation

    Write-Host ""
    Write-Host "Parametreli en yavaş ilk 20 sayfa:"
    $ranked | Select-Object -First 20 rank,module,uri,uri_template,avg_ms,status | Format-Table -AutoSize
    Write-Host "CSV: $resultCsv"
    Write-Host "JSON: $resultJson"
    Write-Host "Modül özeti: $summaryCsv"
} finally {
    if ($UseFixtureRecords -and -not $KeepFixtureRecords) {
        Write-Host "Geçici performans fixture kayıtları temizleniyor..."
        & $PhpPath tools\admin_perf_fixture_records.php delete | Out-Null
    }

    if ($CleanupTempUser) {
        Write-Host "Geçici performans admin kullanıcısı temizleniyor..."
        & $PhpPath tools\admin_perf_temp_user.php delete | Out-Null
    }
    Pop-Location
}
