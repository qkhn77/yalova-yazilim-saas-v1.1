# UTF-8
param(
  [int]$StartId = 1,
  [int]$Count = 10,
  [int]$PerService = 10,
  [int]$Width = 340
)

$ErrorActionPreference = "Stop"
$PSDefaultParameterValues['*:Encoding'] = 'utf8'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$projectRoot = Split-Path -Parent $PSScriptRoot
$exporter = Join-Path $projectRoot "tools/export-services-for-images.php"

function Get-Services() {
  $json = & php $exporter
  return ($json | ConvertFrom-Json)
}

function Is-HumanishTitle([string]$title) {
  $t = $title.ToLowerInvariant()
  $bad = @(
    "person","people","woman","man","boy","girl","human","face","portrait","selfie",
    "hand","hands","technician","installer","worker","engineer","security guard","operator",
    "family","couple","child","children","nurse","doctor",
    "crew","yearbook","catalogue","bulletin","dpla","apollo","soyuz","gemini","press conference",
    "meeting","conference","ceremony","award","group photo","team",
    "logo","icon","label","form","template","poster","flyer","brochure","advertisement"
    ,"railway","bike","bicycle","train",
    "nordstrom","audio","studio","synth","equalizer","castle","rail",
    "googly","opera",
    "war","military","battle","attack","air raid","raid on","fireplace","eclipse","ocean",
    "dieppe","drvar","warden","raid by night","film"
    ,"ticker","broker"
    ,"ship","vessel","boat","yacht","arriving","esbjerg","forskip"
    ,"screenshot","desktop","wallpaper","ui"
    ,"diagram","schema","flow","typický","cheat sheet","networkview"
    ,"geograph.org.uk","monument","estate","bus terminus","cycle","pipeline","river","lockers","seine","park","gate","house"
  )
  foreach ($b in $bad) { if ($t.Contains($b)) { return $true } }
  return $false
}

function Normalize-ServiceTitle([string]$title) {
  $t = ""
  if ($null -ne $title) { $t = $title }
  $t = $t.Trim()
  $t = $t -replace "^Yalova\\s+", ""
  $t = $t -replace "\\s+Hizmeti$", ""
  $t = $t -replace "\\s+Hizmetleri$", ""
  $t = $t -replace "\\s+\\(.*?\\)\\s*", " "
  $t = ($t -replace "\\s+", " ").Trim()
  return $t
}

function Is-RelevantTitle([string]$group, [string]$title) {
  $t = $title.ToLowerInvariant()

  switch ($group) {
    "cyber" {
      return (
        $t.Contains("firewall") -or
        $t.Contains("security appliance") -or
        $t.Contains("gateway") -or
        $t.Contains("server") -or
        $t.Contains("rack") -or
        $t.Contains("datacenter") -or
        $t.Contains("data center")
      )
    }
    "data_recovery" {
      return (
        $t.Contains("hard disk") -or
        $t.Contains("hdd") -or
        $t.Contains("ssd") -or
        $t.Contains("disk") -or
        $t.Contains("raid") -or
        $t.Contains("controller") -or
        $t.Contains("storage")
      )
    }
    "wifi" {
      return (
        $t.Contains("access point") -or
        $t.Contains("wireless access") -or
        $t.Contains("wireless ap") -or
        $t.Contains("wifi router") -or
        $t.Contains("wi-fi router") -or
        $t.Contains("wireless router") -or
        $t.Contains("unifi") -or
        $t.Contains("ubiquiti") -or
        $t.Contains("ruckus") -or
        $t.Contains("aruba") -or
        $t.Contains("aironet") -or
        $t.Contains("mikrotik") -or
        $t.Contains("tp-link") -or
        $t.Contains("dlink") -or
        $t.Contains("d-link")
      )
    }
    "firewall" {
      return (
        $t.Contains("firewall") -or
        $t.Contains("security appliance") -or
        $t.Contains("fortigate") -or
        $t.Contains("fortinet") -or
        $t.Contains("palo alto") -or
        $t.Contains("checkpoint") -or
        $t.Contains("watchguard") -or
        $t.Contains("sonicwall") -or
        $t.Contains("juniper srx") -or
        $t.Contains("asa") -or
        $t.Contains("vpn firewall")
      )
    }
    "vpn" {
      return (
        $t.Contains("vpn") -or
        $t.Contains("vpn router") -or
        $t.Contains("vpn gateway") -or
        $t.Contains("security appliance") -or
        $t.Contains("firewall") -or
        $t.Contains("gateway") -or
        $t.Contains("router") -or
        $t.Contains("juniper srx") -or
        $t.Contains("cisco asa") -or
        $t.Contains("fortigate") -or
        $t.Contains("mikrotik") -or
        $t.Contains("routerboard") -or
        $t.Contains("netgate") -or
        $t.Contains("pfsense")
      )
    }
    "router" {
      return ($t.Contains("router") -or $t.Contains("gateway") -or $t.Contains("cisco") -or $t.Contains("mikrotik") -or $t.Contains("juniper"))
    }
    "switch" {
      return ($t.Contains("switch") -or $t.Contains("cisco") -or $t.Contains("ethernet") -or $t.Contains("network switch"))
    }
    "patch_panel" {
      return ($t.Contains("patch panel") -or $t.Contains("patchpanel") -or $t.Contains("keystone") -or $t.Contains("distribution frame") -or $t.Contains("idf") -or $t.Contains("mdf"))
    }
    "fiber" {
      return ($t.Contains("fiber") -or $t.Contains("fibre") -or $t.Contains("optic") -or $t.Contains("splice") -or $t.Contains("odf") -or $t.Contains("distribution frame"))
    }
    "rack" {
      return ($t.Contains("rack") -or $t.Contains("cabinet") -or $t.Contains("enclosure") -or $t.Contains("server"))
    }
    "cabling" {
      return ($t.Contains("cat5") -or $t.Contains("cat6") -or $t.Contains("cat7") -or $t.Contains("ethernet") -or $t.Contains("cable") -or $t.Contains("cabling") -or $t.Contains("patch cord") -or $t.Contains("cable management"))
    }
    "raid" {
      return (
        $t.Contains("controller") -or
        $t.Contains("megaraid") -or
        $t.Contains("serveraid") -or
        $t.Contains("serve raid") -or
        $t.Contains("adaptec") -or
        $t.Contains("lsi") -or
        $t.Contains("scsi")
      )
    }
    "nas" {
      return ($t.Contains("nas") -or $t.Contains("network attached") -or $t.Contains("network-attached") -or $t.Contains("storage") -or $t.Contains("disk") -or $t.Contains("synology") -or $t.Contains("qnap"))
    }
    "backup" {
      return ($t.Contains("backup") -or $t.Contains("tape") -or $t.Contains("library") -or $t.Contains("autoloader"))
    }
    "cloud_backup" {
      if ($t.Contains("tape") -or $t.Contains("library")) { return $false }
      return ($t.Contains("cloud") -or $t.Contains("data center") -or $t.Contains("datacenter") -or $t.Contains("server") -or $t.Contains("rack") -or $t.Contains("storage") -or $t.Contains("comput") -or $t.Contains("backup"))
    }
    "disaster" {
      return ($t.Contains("redund") -or $t.Contains("failover") -or $t.Contains("backup") -or $t.Contains("data center") -or $t.Contains("datacenter") -or $t.Contains("server") -or $t.Contains("rack"))
    }
    "attendance" {
      return (
        $t.Contains("time attendance") -or
        $t.Contains("attendance terminal") -or
        $t.Contains("biometric terminal") -or
        $t.Contains("fingerprint terminal") -or
        $t.Contains("fingerprint") -or
        $t.Contains("rfid reader") -or
        $t.Contains("card reader") -or
        $t.Contains("access control terminal") -or
        $t.Contains("access control") -or
        $t.Contains("door access") -or
        $t.Contains("turnstile") -or
        $t.Contains("tripod gate") -or
        $t.Contains("speed gate")
      )
    }
    "access_control" {
      return (
        $t.Contains("access control") -or
        $t.Contains("rfid reader") -or
        $t.Contains("card reader") -or
        $t.Contains("door controller") -or
        $t.Contains("fingerprint") -or
        $t.Contains("face recognition") -or
        $t.Contains("turnstile") -or
        $t.Contains("tripod gate") -or
        $t.Contains("speed gate") -or
        $t.Contains("electromagnetic lock")
      )
    }
    "telephony" {
      return (
        $t.Contains("ip phone") -or
        $t.Contains("voip") -or
        $t.Contains("sip phone") -or
        $t.Contains("desk phone") -or
        $t.Contains("handset") -or
        $t.Contains("telephone") -or
        $t.Contains("phone system") -or
        $t.Contains("pbx") -or
        $t.Contains("pabx") -or
        $t.Contains("switchboard") -or
        $t.Contains("telephony") -or
        $t.Contains("call manager") -or
        $t.Contains("asterisk") -or
        $t.Contains("yealink") -or
        $t.Contains("grandstream") -or
        $t.Contains("avaya") -or
        $t.Contains("mitel") -or
        $t.Contains("panasonic") -or
        $t.Contains("alcatel") -or
        $t.Contains("cisco")
      )
    }
    default {
      return ($t.Contains("server") -or $t.Contains("rack") -or $t.Contains("data center") -or $t.Contains("datacenter") -or $t.Contains("switch") -or $t.Contains("router") -or $t.Contains("storage"))
    }
  }
}

function Guess-Group([string]$categorySlug, [string]$title) {
  $t = $title.ToLowerInvariant()
  $c = ""
  if ($null -ne $categorySlug) { $c = $categorySlug.ToLowerInvariant() }

  if ($t.Contains("veri kurtarma")) { return "data_recovery" }
  if ($t.Contains("pdks") -or $t.Contains("devam kontrol") -or $t.Contains("vardiya") -or $t.Contains("mesai takip")) { return "attendance" }
  if ($t.Contains("parmak izi") -or $t.Contains("yüz tanıma") -or $t.Contains("yuz tanima") -or $t.Contains("kartlı geçiş") -or $t.Contains("kartli gecis") -or $t.Contains("turnike")) { return "access_control" }
  if ($t.Contains("ip santral") -or $t.Contains("voip") -or $t.Contains("çağrı merkezi") -or $t.Contains("cagri merkezi") -or $t.Contains("telefon altyapısı") -or $t.Contains("telefon altyapisi")) { return "telephony" }
  if ($t.Contains("site-to-site vpn")) { return "vpn" }
  if ($t.Contains("endpoint") -or $t.Contains("antivirus") -or $t.Contains("ağ güvenlik analizi") -or $t.Contains("ag guvenlik analizi") -or $t.Contains("veri güvenliği") -or $t.Contains("veri guvenligi")) { return "cyber" }
  if ($t.Contains("cloud")) { return "cloud_backup" }
  if ($t.Contains("firewall")) { return "firewall" }
  if ($t.Contains("vpn")) { return "vpn" }
  if ($t.Contains("wifi") -or $t.Contains("wi-fi") -or $t.Contains("access point") -or $t.Contains("mesh") -or $t.Contains("hotspot")) { return "wifi" }

  if ($t.Contains("router")) { return "router" }
  if ($t.Contains("switch")) { return "switch" }
  if ($t.Contains("patch panel") -or $t.Contains("patch-panel")) { return "patch_panel" }
  if ($t.Contains("fiber") -or $t.Contains("fib")) { return "fiber" }
  if ($t.Contains("cat5") -or $t.Contains("cat6") -or $t.Contains("cat7") -or $t.Contains("kablolama")) { return "cabling" }
  if ($t.Contains("rack kabin") -or $t.Contains("rack")) { return "rack" }

  if ($t.Contains("bulut")) { return "cloud_backup" }
  if ($t.Contains("windows server")) { return "windows_server" }
  if ($t.Contains("linux")) { return "linux_server" }
  if ($t.Contains("active directory") -or $t.Contains("domain")) { return "active_directory" }
  if ($t.Contains("sanallaştırma") -or $t.Contains("virtual") -or $t.Contains("vmware") -or $t.Contains("hyper-v") -or $t.Contains("proxmox")) { return "virtualization" }
  if ($t.Contains("dosya") -or $t.Contains("file server")) { return "file_server" }
  if ($t.Contains("mail")) { return "mail" }
  if ($t.Contains("web server")) { return "web_server" }
  if ($t.Contains("nas")) { return "nas" }
  if ($t.Contains("raid")) { return "raid" }
  if ($t.Contains("yedek") -or $t.Contains("backup")) { return "backup" }
  if ($t.Contains("felaket") -or $t.Contains("disaster")) { return "disaster" }

  if ($c -eq "sunucu-sistemleri") { return "servers" }
  if ($c -eq "network-altyapi") { return "network" }
  if ($c -eq "ag-siber-guvenlik") { return "cyber" }
  if ($c -eq "yedekleme-veri-yonetimi") { return "backup" }
  if ($c -eq "personel-takip-gecis-sistemleri") { return "access_control" }
  if ($c -eq "iletisim-sistemleri") { return "telephony" }
  if ($c -eq "bulut-hosting") { return "cloud" }
  if ($c -eq "guvenlik-sistemleri") { return "cctv" }
  if ($c -eq "isletme-otomasyonlari") { return "business_automation" }
  if ($c -eq "enerji-altyapi") { return "ups" }
  if ($c -eq "akilli-ev-sistemleri") { return "smart_home" }

  return "generic"
}

function Query-Templates([string]$group, [string]$coreTitle) {
  switch ($group) {
    "cyber"            { return @('security appliance rack', 'network firewall appliance', 'server security appliance', 'data center security') }
    "data_recovery"    { return @('hard disk drive closeup', 'ssd drive storage', 'raid controller card', 'storage disk array') }
    "wifi"             { return @('wireless access point', 'wifi access point device', 'ubiquiti access point', 'aruba access point') }
    "firewall"         { return @('network firewall appliance', 'juniper srx appliance', 'fortigate firewall appliance', 'rackmount firewall') }
    "vpn"              { return @('vpn gateway appliance', 'enterprise vpn router', 'security gateway appliance', 'cisco asa firewall') }
    "router"           { return @('enterprise router', 'cisco router', 'mikrotik router', 'router rack') }
    "switch"           { return @('ethernet switch', 'cisco switch', 'network switch rack', 'switch stack') }
    "patch_panel"      { return @('ethernet patch panel', 'keystone patch panel', 'rack patch panel', 'patch panel 24 port') }
    "fiber"            { return @('fiber optic patch panel', 'optical distribution frame', 'fiber splice tray', 'ODF fiber') }
    "rack"             { return @('server rack cabinet', 'rack cabinet servers', 'rack enclosure server') }
    "cabling"          { return @('ethernet cable management', 'structured cabling rack', 'cat6 patch cord', 'network cabling rack') }
    "active_directory" { return @('network switch rack', 'data center server racks', 'server rack') }
    "virtualization"   { return @('server rack', 'rack mount server', 'data center server racks') }
    "file_server"      { return @('storage server rack', 'server rack storage', 'storage array rack', 'server room racks') }
    "mail"             { return @('data center server racks', 'server rack servers', 'server room servers') }
    "web_server"       { return @('data center server racks', 'server rack servers', 'server room servers') }
    "nas"              { return @('network attached storage', 'network-attached storage', 'nas storage server', 'storage appliance') }
    "raid"             { return @('ServeRAID', 'RAID controller', 'Adaptec RAID controller', 'SCSI RAID controller') }
    "backup"           { return @('tape library', 'backup server rack', 'backup storage server') }
    "cloud_backup"     { return @('cloud computing data center', 'cloud server infrastructure', 'data center cloud') }
    "disaster"         { return @('backup data center', 'data center redundancy', 'server rack redundancy', 'data center servers') }
    "attendance"       { return @('time attendance terminal', 'biometric attendance device', 'fingerprint attendance terminal', 'access control card reader') }
    "access_control"   { return @('access control card reader', 'biometric access control terminal', 'face recognition access terminal', 'turnstile gate access control') }
    "telephony"        { return @('ip phone system', 'voip telephone handset', 'pbx telephone exchange equipment', 'cisco ip phone', 'yealink ip phone', 'avaya desk phone') }
    "servers"          { return @('server rack', 'data center servers', 'server room racks') }
    default            { return @($coreTitle, $coreTitle + ' server', 'server rack') }
  }
}

function Commons-Search([string]$query, [int]$limit) {
  $q = [System.Uri]::EscapeDataString($query)
  $url = "https://commons.wikimedia.org/w/api.php?action=query&list=search&srnamespace=6&srlimit=$limit&format=json&srsearch=$q"

  # curl.exe (schannel) bazı Windows ortamlarda TLS hatası verebiliyor.
  # Python urllib ile çekip JSON parse ediyoruz.
  $py = @'
import sys, urllib.request
url = sys.argv[1]
req = urllib.request.Request(url, headers={'User-Agent': 'YalovaBilgisayar/1.0 (info@yalovabilgisayar.com)'})
with urllib.request.urlopen(req, timeout=25) as r:
    sys.stdout.write(r.read().decode('utf-8'))
'@

  for ($attempt = 1; $attempt -le 3; $attempt++) {
    try {
      $resp = & python -c $py $url
      return ($resp | ConvertFrom-Json).query.search | ForEach-Object { $_.title }
    } catch {
      Start-Sleep -Milliseconds (300 * $attempt)
    }
  }

  return @()
}

function As-FilePath([string]$fileTitle, [int]$width) {
  $name = $fileTitle.Replace("File:", "")
  $escaped = [System.Uri]::EscapeDataString($name)
  return "https://commons.wikimedia.org/wiki/Special:FilePath/${escaped}?width=$width"
}

function As-FilePage([string]$fileTitle) {
  $escaped = [System.Uri]::EscapeDataString($fileTitle.Replace("File:", ""))
  return "https://commons.wikimedia.org/wiki/File:$escaped"
}

$services = Get-Services | Where-Object { $_.is_active } | Sort-Object id
$batch = $services | Where-Object { $_.id -ge $StartId } | Select-Object -First $Count

$used = @{}

foreach ($svc in $batch) {
  $slug = [string]$svc.slug
  $title = [string]$svc.title
  $categorySlug = [string]$svc.category_slug

  $core = Normalize-ServiceTitle $title
  $group = Guess-Group $categorySlug $core
  $queries = Query-Templates $group $core

  $candidates = New-Object System.Collections.Generic.List[string]
  foreach ($q in $queries) {
    $negFull = "-person -people -portrait -hand -logo -icon -yearbook -catalogue -bulletin -crew -meeting -conference -war -military -battle -attack -fireplace -eclipse -ocean"
    $negShort = "-person -people -portrait -logo -icon -war -military -battle -attack"

    $search = ($q + " " + $negFull).Trim()
    if ($search.Length -gt 290) { $search = ($q + " " + $negShort).Trim() }

    $results = Commons-Search $search 60
    foreach ($r in $results) { $candidates.Add($r) }
    Start-Sleep -Milliseconds 250
    if ($candidates.Count -ge 200) { break }
  }

  $picked = @()
  $extPasses = @(@(".jpg", ".jpeg"), @(".png"), @(".svg"))
  foreach ($pass in $extPasses) {
    foreach ($c in $candidates) {
      if ($picked.Count -ge $PerService) { break }
      if ([string]::IsNullOrWhiteSpace($c)) { continue }
      if (Is-HumanishTitle $c) { continue }
      if (!(Is-RelevantTitle $group $c)) { continue }
      $lc = $c.ToLowerInvariant()
      $ok = $false
      foreach ($ext in $pass) { if ($lc.EndsWith($ext)) { $ok = $true; break } }
      if (!$ok) { continue }
      if ($used.ContainsKey($c)) { continue }
      $used[$c] = $true
      $picked += $c
    }
    if ($picked.Count -ge $PerService) { break }
  }

  Write-Output ""
  Write-Output ("## {0} (`{1}`)" -f $title, $slug)

  $idx = 1
  foreach ($fileTitle in $picked) {
    $img = As-FilePath $fileTitle $Width
    $page = As-FilePage $fileTitle
    Write-Output ("{0}. [{1}]({2})" -f $idx, $fileTitle.Replace("File:", ""), $page)
    Write-Output ("   ![{0} {1}]({2})" -f $slug, $idx, $img)
    $idx++
  }

  if ($picked.Count -lt $PerService) {
    Write-Output ("(Not: Bu servis için {0}/{1} görsel bulundu; istersen bu servis için tekrar arama genişletebilirim.)" -f $picked.Count, $PerService)
  }
}
