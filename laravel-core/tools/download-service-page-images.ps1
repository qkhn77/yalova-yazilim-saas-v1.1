# UTF-8
param(
  [int]$Limit = 0,
  [switch]$DryRun
)

$PSDefaultParameterValues['*:Encoding'] = 'utf8'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$outDir = Join-Path $projectRoot "storage/app/public/services/pages"
$exporter = Join-Path $projectRoot "tools/export-services-for-images.php"
$mapPath = Join-Path $projectRoot "database/seeders/data/service-page-images.map.json"

if (!(Test-Path -LiteralPath $exporter)) {
  throw "Exporter bulunamadı: $exporter"
}

if (!(Test-Path -LiteralPath $outDir)) {
  New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

function Get-Json([string]$url) {
  if ($DryRun) { throw "DryRun modunda API çağrısı yapılmaz." }
  $resp = & curl.exe -A "YalovaBilgisayar/1.0 (info@yalovabilgisayar.com)" -L --silent --show-error --fail `
    --connect-timeout 15 --max-time 25 `
    --retry 6 --retry-delay 4 --retry-all-errors `
    $url
  return $resp | ConvertFrom-Json
}

function Get-CategoryFiles([string]$categoryName) {
  $cat = [System.Uri]::EscapeDataString("Category:$categoryName")
  $url = "https://commons.wikimedia.org/w/api.php?action=query&list=categorymembers&cmtitle=$cat&cmtype=file&cmlimit=500&format=json"
  $json = Get-Json $url
  $members = @()
  if ($json.query -and $json.query.categorymembers) { $members = $json.query.categorymembers }
  return $members | ForEach-Object { $_.title }
}

function Is-HumanishTitle([string]$title) {
  $t = $title.ToLowerInvariant()
  $bad = @(
    "person","people","woman","man","boy","girl","human","face","portrait","selfie",
    "hand","hands","technician","installer","worker","engineer","security guard","operator",
    "family","couple","child","children"
  )
  foreach ($b in $bad) { if ($t.Contains($b)) { return $true } }
  return $false
}

function Pick-NextFile([string[]]$files, [hashtable]$used) {
  foreach ($f in $files) {
    if ([string]::IsNullOrWhiteSpace($f)) { continue }
    $lower = $f.ToLowerInvariant()
    if (!($lower.EndsWith(".jpg") -or $lower.EndsWith(".jpeg") -or $lower.EndsWith(".png"))) { continue }
    if (Is-HumanishTitle $f) { continue }
    if ($used.ContainsKey($f)) { continue }
    $used[$f] = $true
    return $f
  }
  return $null
}

function Download-CommonsFile([string]$fileTitle, [string]$destPath) {
  if ($DryRun) { return $true }
  $apiTitle = [System.Uri]::EscapeDataString($fileTitle)
  $apiUrl = "https://commons.wikimedia.org/w/api.php?action=query&titles=$apiTitle&prop=imageinfo&iiprop=url&format=json"

  $json = Get-Json $apiUrl
  $pages = $json.query.pages.PSObject.Properties.Value
  $page = $pages | Select-Object -First 1
  $direct = $page.imageinfo[0].url
  if ([string]::IsNullOrWhiteSpace($direct)) { return $false }

  # Dosya indir (yavaş + tekrar denemeli + saygılı)
  Start-Sleep -Seconds (Get-Random -Minimum 3 -Maximum 7)
  & curl.exe -A "YalovaBilgisayar/1.0 (info@yalovabilgisayar.com)" -L --fail --silent --show-error `
    --connect-timeout 20 --max-time 120 `
    --retry 10 --retry-delay 8 --retry-all-errors --retry-max-time 1200 `
    -o $destPath $direct

  return ($LASTEXITCODE -eq 0 -and (Test-Path -LiteralPath $destPath))
}

function Guess-Group([string]$categorySlug, [string]$title) {
  $t = $title.ToLowerInvariant()
  $c = ""
  if ($null -ne $categorySlug) { $c = $categorySlug.ToLowerInvariant() }

  if ($t.Contains("windows server")) { return "windows_server" }
  if ($t.Contains("linux")) { return "linux_server" }
  if ($t.Contains("active directory") -or $t.Contains("domain")) { return "active_directory" }
  if ($t.Contains("mail")) { return "mail" }
  if ($t.Contains("web server")) { return "web_server" }

  if ($t.Contains("nas")) { return "nas" }
  if ($t.Contains("raid")) { return "raid" }
  if ($t.Contains("yedek") -or $t.Contains("backup") -or $t.Contains("felaket") -or $t.Contains("disaster")) { return "backup" }

  if ($t.Contains("fiber") -or $t.Contains("optik")) { return "fiber" }
  if ($t.Contains("patch panel")) { return "patch_panel" }
  if ($t.Contains("switch")) { return "switch" }
  if ($t.Contains("router")) { return "router" }
  if ($t.Contains("vlan")) { return "vlan" }
  if ($t.Contains("wifi") -or $t.Contains("access point") -or $t.Contains("hotspot")) { return "wifi" }
  if ($t.Contains("mesh")) { return "mesh" }

  if ($t.Contains("vpn")) { return "vpn" }
  if ($t.Contains("firewall")) { return "firewall" }
  if ($t.Contains("antivirus") -or $t.Contains("endpoint") -or $t.Contains("siber")) { return "cyber" }

  if ($t.Contains("pdks") -or $t.Contains("parmak") -or $t.Contains("kart") -or $t.Contains("turnike") -or $t.Contains("geçiş") -or $t.Contains("gecis") -or $c -eq "personel-takip-gecis-sistemleri") { return "access_control" }
  if ($t.Contains("santral") -or $t.Contains("voip") -or $t.Contains("çağrı") -or $t.Contains("cagri")) { return "voip" }

  if ($t.Contains("bilgisayar") -or $t.Contains("laptop") -or $t.Contains("format") -or $t.Contains("tamir") -or $t.Contains("arıza") -or $t.Contains("ariza")) { return "computer_service" }

  if ($t.Contains("vps") -or $t.Contains("vds") -or $t.Contains("hosting") -or $t.Contains("domain") -or $t.Contains("bulut") -or $c -eq "bulut-hosting") { return "cloud" }

  if ($t.Contains("kamera") -or $t.Contains("alarm") -or $t.Contains("plaka") -or $t.Contains("yangın") -or $t.Contains("yangin") -or $c -eq "guvenlik-sistemleri") { return "security_systems" }

  if ($t.Contains("barkod") -or $t.Contains("stok") -or $t.Contains("erp") -or $t.Contains("crm") -or $t.Contains("muhasebe") -or $c -eq "isletme-otomasyonlari") { return "business_automation" }

  if ($t.Contains("ups") -or $t.Contains("elektrik") -or $c -eq "enerji-altyapi") { return "ups" }

  if ($t.Contains("akıllı") -or $t.Contains("akilli") -or $t.Contains("iot") -or $t.Contains("zigbee") -or $t.Contains("z-wave") -or $t.Contains("termostat") -or $t.Contains("kilit") -or $c -eq "akilli-ev-sistemleri") { return "smart_home" }

  if ($t.Contains("sunucu") -or $t.Contains("server") -or $c -eq "sunucu-sistemleri") { return "servers" }
  if ($c -eq "network-altyapi") { return "network" }
  if ($c -eq "ag-siber-guvenlik") { return "cyber" }
  if ($c -eq "teknik-servis") { return "computer_service" }

  return "generic"
}

# Gruplar -> Commons kategori adları (mümkün olduğunca nesne/altyapı odaklı)
$groupCategories = @{
  "servers"           = @("Server_racks","Computer_servers","Data_centers")
  "windows_server"    = @("Computer_servers","Data_centers")
  "linux_server"      = @("Computer_servers","Data_centers")
  "active_directory"  = @("Computer_networking","Server_racks")
  "mail"              = @("Mail_servers","Email")
  "web_server"        = @("Web_servers","Server_racks")
  "raid"              = @("RAID","Hard_disks")
  "nas"               = @("Network-attached_storage","NAS_devices")
  "backup"            = @("Tape_drives","External_hard_drives","Network-attached_storage")
  "network"           = @("Network_switches","Patch_panels","Computer_networking")
  "switch"            = @("Network_switches")
  "router"            = @("Routers","Computer_networking")
  "patch_panel"       = @("Patch_panels")
  "fiber"             = @("Fiber-optic_communication","Optical_fiber_connectors")
  "vlan"              = @("Computer_networking","Network_switches")
  "wifi"              = @("Wireless_access_points","Wi-Fi")
  "mesh"              = @("Wireless_routers","Wi-Fi")
  "vpn"               = @("Virtual_private_networks","IPsec")
  "firewall"          = @("Firewalls_(computing)","Computer_security")
  "cyber"             = @("Computer_security","Cybersecurity")
  "access_control"    = @("Access_control","Biometric_devices","Turnstiles")
  "voip"              = @("IP_telephony","Telephones")
  "computer_service"  = @("Laptop_computers","Motherboards","Computer_hardware")
  "cloud"             = @("Cloud_computing","Data_centers")
  "security_systems"  = @("Closed-circuit_television_cameras","Video_surveillance","Alarm_systems")
  "business_automation" = @("Barcode_scanners","Point_of_sale","Computer_hardware")
  "ups"               = @("Uninterruptible_power_supplies","Batteries")
  "smart_home"        = @("Smart_home_devices","Home_automation","Smart_locks","Thermostats")
  "generic"           = @("Computer_hardware","Electronics")
}

# Dosya listelerini lazy cache'le (429 riskini ve başlangıç süresini azaltır)
$groupFileCache = @{}
function Get-GroupFiles([string]$group) {
  if ($groupFileCache.ContainsKey($group)) { return @($groupFileCache[$group]) }
  $allFiles = New-Object System.Collections.Generic.List[string]
  $cats = @($groupCategories[$group])
  foreach ($cat in $cats) {
    try {
      $files = Get-CategoryFiles $cat
      foreach ($f in $files) { $allFiles.Add($f) }
      Start-Sleep -Seconds 1
    } catch {
      # ignore
    }
  }
  $groupFileCache[$group] = $allFiles.ToArray()
  return @($groupFileCache[$group])
}

# Servis listesini DB'den al (slug = sistemdeki gerçek slug)
Write-Output "Servis listesi okunuyor..."
$servicesJson = & php $exporter
$services = $servicesJson | ConvertFrom-Json
Write-Output ("Servis sayısı: {0}" -f @($services).Count)

if ($DryRun) {
  $preview = $services
  if ($Limit -gt 0) { $preview = @($services | Select-Object -First $Limit) }
  Write-Output "DryRun: Dosya isimleri ve grup eşleşmesi"
  foreach ($svc in $preview) {
    $slug = [string]$svc.slug
    $title = [string]$svc.title
    $categorySlug = [string]$svc.category_slug
    $group = Guess-Group $categorySlug $title
    $filename = "yalova-bilgisayar-$slug.jpg"
    Write-Output ("- {0} | {1} | {2}" -f $filename, $group, $title)
  }
  exit 0
}

$map = @{}
if (Test-Path -LiteralPath $mapPath) {
  try {
    $raw = Get-Content -LiteralPath $mapPath -Encoding UTF8 -Raw
    if (-not [string]::IsNullOrWhiteSpace($raw)) {
      $loaded = $raw | ConvertFrom-Json
      foreach ($p in $loaded.PSObject.Properties) {
        $map[$p.Name] = [string]$p.Value
      }
    }
  } catch { }
}

$usedFiles = @{}
$map.Values | ForEach-Object {
  if (-not [string]::IsNullOrWhiteSpace($_)) {
    $t = "File:" + $_.Replace("File:", "")
    $usedFiles[$t] = $true
  }
}
$fail = @()
$total = @($services).Count
$processed = 0
$downloadedNew = 0

foreach ($svc in $services) {
  if (-not $svc.is_active) { continue }
  $slug = [string]$svc.slug
  $title = [string]$svc.title
  $categorySlug = [string]$svc.category_slug

  if ([string]::IsNullOrWhiteSpace($slug)) { continue }

  $filename = "yalova-bilgisayar-$slug.jpg"
  $dest = Join-Path $outDir $filename

  if (Test-Path -LiteralPath $dest) {
    $processed++
    continue
  }

  $group = Guess-Group $categorySlug $title
  $pick = $null

  if ($map.ContainsKey($slug) -and -not [string]::IsNullOrWhiteSpace($map[$slug])) {
    $pick = "File:" + $map[$slug].Replace("File:", "")
  } else {
    $candidates = Get-GroupFiles $group
    $pick = Pick-NextFile $candidates $usedFiles
    if ($null -eq $pick) {
      $pick = Pick-NextFile (Get-GroupFiles "generic") $usedFiles
    }
    if ($null -ne $pick) {
      $map[$slug] = $pick.Replace("File:", "")
    }
  }

  if ($null -eq $pick) {
    $fail += $slug
    $processed++
    continue
  }

  $ok = Download-CommonsFile $pick $dest
  if (-not $ok) {
    $fail += $slug
    if (Test-Path -LiteralPath $dest) { Remove-Item -LiteralPath $dest -Force -ErrorAction SilentlyContinue }
  } else {
    $downloadedNew++
  }

  $processed++
  Write-Output ("[{0}/{1}] {2} -> {3}" -f $processed, $total, $title, $filename)

  if ($Limit -gt 0 -and $downloadedNew -ge $Limit) {
    Write-Output ("Limit nedeniyle durduruldu: {0}" -f $Limit)
    break
  }
}

try {
  $mapObj = [ordered]@{}
  foreach ($k in ($map.Keys | Sort-Object)) { $mapObj[$k] = $map[$k] }
  ($mapObj | ConvertTo-Json -Depth 4) | Set-Content -LiteralPath $mapPath -Encoding UTF8
} catch { }

Write-Output ("Tamamlandı. Yeni indirilen: {0}, Başarısız: {1}" -f $downloadedNew, $fail.Count)
if ($fail.Count -gt 0) {
  Write-Output "Başarısız slug'lar:"
  $fail | ForEach-Object { Write-Output ("- " + $_) }
  exit 1
}
