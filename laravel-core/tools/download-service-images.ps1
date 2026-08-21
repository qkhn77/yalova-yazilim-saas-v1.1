# UTF-8
$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$manifestPath = Join-Path $projectRoot "database/seeders/data/service-image-manifest.json"
$storagePublic = Join-Path $projectRoot "storage/app/public"

if (!(Test-Path -LiteralPath $manifestPath)) {
  throw "Manifest bulunamadı: $manifestPath"
}

$json = Get-Content -LiteralPath $manifestPath -Encoding UTF8 -Raw
$items = $json | ConvertFrom-Json

function Ensure-Dir([string]$path) {
  $dir = Split-Path -Parent $path
  if ($dir -and !(Test-Path -LiteralPath $dir)) {
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
  }
}

$downloaded = 0
$skipped = 0
$failed = @()

foreach ($item in $items) {
  $commonsFile = [string]$item.commons_file
  $targetRel = [string]$item.target_path

  if ([string]::IsNullOrWhiteSpace($commonsFile) -or [string]::IsNullOrWhiteSpace($targetRel)) {
    continue
  }

  $targetFull = Join-Path $storagePublic ($targetRel -replace "/", "\\")
  Ensure-Dir $targetFull

  if (Test-Path -LiteralPath $targetFull) {
    $skipped++
    continue
  }

  $escaped = [System.Uri]::EscapeDataString($commonsFile)
  $url = "https://commons.wikimedia.org/wiki/Special:FilePath/$escaped"

  # curl ile indir (redirect takip) - 429 vb. için yavaş ve tekrar denemeli
  Start-Sleep -Seconds 2
  & curl.exe -L --fail --silent --show-error `
    --retry 8 --retry-delay 5 --retry-all-errors --retry-max-time 300 `
    -o $targetFull $url

  if ($LASTEXITCODE -ne 0) {
    $failed += $commonsFile
    if (Test-Path -LiteralPath $targetFull) {
      Remove-Item -LiteralPath $targetFull -Force -ErrorAction SilentlyContinue
    }
    continue
  }

  $downloaded++
}

Write-Output ("İndirilen: {0}, Atlanan: {1}, Hatalı: {2}" -f $downloaded, $skipped, $failed.Count)
if ($failed.Count -gt 0) {
  Write-Output "Hata alan dosyalar:"
  $failed | ForEach-Object { Write-Output ("- " + $_) }
  exit 1
}
