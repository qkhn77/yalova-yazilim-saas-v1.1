param(
    [string] $BaseUrl = "http://localhost/yalova-kamera",
    [string] $PhpPath = "C:\xampp\php\php.exe",
    [int] $Runs = 3,
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

    foreach ($currentUri in $Uri) {
        $durations = @()
        $status = $null
        $finalUrl = $null

        for ($run = 1; $run -le $Runs; $run++) {
            $sw = [Diagnostics.Stopwatch]::StartNew()
            try {
                $response = Invoke-WebRequest `
                    -Uri (Join-Url $BaseUrl $currentUri) `
                    -WebSession $session `
                    -UseBasicParsing `
                    -MaximumRedirection 5 `
                    -TimeoutSec 120
                $sw.Stop()

                $status = [int] $response.StatusCode
                $finalUrl = $response.BaseResponse.ResponseUri.AbsoluteUri
            } catch {
                $sw.Stop()

                if (-not $_.Exception.Response) {
                    throw
                }

                $status = [int] $_.Exception.Response.StatusCode
                $finalUrl = $_.Exception.Response.ResponseUri.AbsoluteUri
            }

            $durations += $sw.Elapsed.TotalMilliseconds
        }

        $avg = [math]::Round(($durations | Measure-Object -Average).Average, 2)
        $runsText = ($durations | ForEach-Object { [math]::Round($_, 2) }) -join "|"

        [pscustomobject]@{
            uri = $currentUri
            avg_ms = $avg
            runs_ms = $runsText
            status = $status
            final_url = $finalUrl
        }
    }
} finally {
    Pop-Location
}
