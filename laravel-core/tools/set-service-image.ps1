# UTF-8
param(
  [Parameter(Mandatory = $true)][string]$Slug,
  [Parameter(Mandatory = $true)][string]$CommonsFileName
)

$ErrorActionPreference = "Stop"
$PSDefaultParameterValues['*:Encoding'] = 'utf8'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$projectRoot = Split-Path -Parent $PSScriptRoot
$outDir = Join-Path $projectRoot "storage/app/public/services/pages"
$mapPath = Join-Path $projectRoot "database/seeders/data/service-page-images.map.json"

if (!(Test-Path -LiteralPath $outDir)) {
  New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

function Read-Map([string]$path) {
  $map = [ordered]@{}
  if (Test-Path -LiteralPath $path) {
    try {
      $raw = Get-Content -LiteralPath $path -Encoding UTF8 -Raw
      if (-not [string]::IsNullOrWhiteSpace($raw)) {
        $loaded = $raw | ConvertFrom-Json
        foreach ($p in $loaded.PSObject.Properties) {
          $map[$p.Name] = [string]$p.Value
        }
      }
    } catch { }
  }
  return $map
}

function Write-Map([hashtable]$map, [string]$path) {
  $out = [ordered]@{}
  foreach ($k in ($map.Keys | Sort-Object)) { $out[$k] = $map[$k] }
  ($out | ConvertTo-Json -Depth 4) | Set-Content -LiteralPath $path -Encoding UTF8
}

function Download-CommonsFile([string]$fileName, [string]$destPath) {
  $escaped = [System.Uri]::EscapeDataString($fileName)
  $url = "https://commons.wikimedia.org/wiki/Special:FilePath/$escaped"

  # curl.exe (schannel) bazı Windows ortamlarda TLS hatası verebiliyor.
  # Önce Python urllib ile indiriyoruz; başarısız olursa curl fallback.
  Start-Sleep -Seconds 1

  $py = @'
import sys, urllib.request
url = sys.argv[1]
dest = sys.argv[2]
req = urllib.request.Request(url, headers={'User-Agent': 'YalovaBilgisayar/1.0 (info@yalovabilgisayar.com)'})
with urllib.request.urlopen(req, timeout=120) as r:
    data = r.read()
with open(dest, 'wb') as f:
    f.write(data)
'@

  try {
    & python -c $py $url $destPath | Out-Null
    if (Test-Path -LiteralPath $destPath) { return $true }
  } catch { }

  & curl.exe -A "YalovaBilgisayar/1.0 (info@yalovabilgisayar.com)" -L --fail --silent --show-error `
    --connect-timeout 20 --max-time 120 `
    --retry 10 --retry-delay 8 --retry-all-errors --retry-max-time 1200 `
    -o $destPath $url
  return ($LASTEXITCODE -eq 0 -and (Test-Path -LiteralPath $destPath))
}

$slug = $Slug.Trim()
if ($slug -eq "") { throw "Slug boş olamaz." }

$file = $CommonsFileName.Trim()
$file = $file.Replace("File:", "")
if ($file -eq "") { throw "CommonsFileName boş olamaz." }

$targetName = "yalova-bilgisayar-$slug.jpg"
$dest = Join-Path $outDir $targetName

Write-Output ("İndiriliyor: {0} -> {1}" -f $file, $targetName)
$ok = Download-CommonsFile $file $dest
if (-not $ok) {
  throw "İndirme başarısız: $file"
}

$map = Read-Map $mapPath
$map[$slug] = $file
Write-Map $map $mapPath

Write-Output "Tamam: $targetName"
