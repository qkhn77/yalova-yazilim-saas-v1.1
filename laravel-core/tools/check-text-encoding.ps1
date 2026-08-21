param(
    [string]$RootPath = ".",
    [ValidateSet("changed", "all")]
    [string]$Scope = "changed",
    [string]$IgnoreListFile = "tools/encoding-ignore.txt"
)

$ErrorActionPreference = "Stop"

$extensions = @(
    "*.php",
    "*.blade.php",
    "*.md",
    "*.js",
    "*.ts",
    "*.css",
    "*.scss",
    "*.json",
    "*.yml",
    "*.yaml",
    "*.xml",
    "*.txt"
)

$excludeDirs = @(
    "\vendor\",
    "\node_modules\",
    "\storage\",
    "\bootstrap\cache\",
    "\public\build\",
    "\.git\"
)

$sorunlar = New-Object System.Collections.Generic.List[string]
$ignoreSet = New-Object System.Collections.Generic.HashSet[string]([System.StringComparer]::OrdinalIgnoreCase)

if (Test-Path $IgnoreListFile -PathType Leaf) {
    $ignoreLines = Get-Content -Path $IgnoreListFile
    foreach ($line in $ignoreLines) {
        $trim = $line.Trim()
        if ($trim -eq "" -or $trim.StartsWith("#")) {
            continue
        }
        $full = Join-Path (Resolve-Path $RootPath).Path $trim
        $ignoreSet.Add($full) | Out-Null
    }
}

function Is-Excluded([string]$path) {
    foreach ($ex in $excludeDirs) {
        if ($path -like "*$ex*") {
            return $true
        }
    }
    return $false
}

function Add-Issue([string]$file, [string]$message) {
    $sorunlar.Add("$file :: $message")
}

function Ext-Match([string]$fullPath) {
    foreach ($pattern in $extensions) {
        $suffix = $pattern.Replace("*", "")
        if ($fullPath -like "*$suffix") {
            return $true
        }
    }
    return $false
}

$files = @()
$root = (Resolve-Path $RootPath).Path

if ($Scope -eq "changed") {
    $gitFiles = git status --porcelain 2>$null
    foreach ($line in $gitFiles) {
        if ([string]::IsNullOrWhiteSpace($line)) {
            continue
        }

        $relative = $line.Substring(3).Trim()
        if ([string]::IsNullOrWhiteSpace($relative)) {
            continue
        }

        $full = Join-Path $root $relative
        if (-not (Test-Path $full -PathType Leaf)) {
            continue
        }

        if (Ext-Match $full) {
            $files += Get-Item $full
        }
    }
} else {
    $files = foreach ($pattern in $extensions) {
        Get-ChildItem -Path $root -Recurse -File -Filter $pattern
    }
}

$files = $files | Sort-Object -Property FullName -Unique

foreach ($file in $files) {
    if (Is-Excluded $file.FullName) {
        continue
    }
    if ($ignoreSet.Contains($file.FullName)) {
        continue
    }

    $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 239 -and $bytes[1] -eq 187 -and $bytes[2] -eq 191) {
        Add-Issue $file.FullName "UTF-8 BOM tespit edildi (BOM kullanma)."
    }

    $text = [System.IO.File]::ReadAllText($file.FullName)

    # Yaygin mojibake desenleri:
    # - "Ã" / "Â" ile baslayan bozuk UTF-8 fragmanlari
    # - replacement char (U+FFFD)
    # - "â€" ile baslayan cp1252/latin1 fragmanlari (U+00E2 U+20AC)
    $hasMojibake = $false
    if ($text.Contains([string][char]0x00C3) -or $text.Contains([string][char]0x00C2)) {
        $hasMojibake = $true
    }
    if ($text.Contains([string][char]0xFFFD)) {
        $hasMojibake = $true
    }
    $mojibakeLead = ([string][char]0x00E2) + ([string][char]0x20AC)
    if ($text.Contains($mojibakeLead)) {
        $hasMojibake = $true
    }

    if ($hasMojibake) {
        Add-Issue $file.FullName "Karakter bozulmasi (mojibake) supheli karakter(ler) bulundu."
    }
}

if ($sorunlar.Count -gt 0) {
    Write-Host "Encoding kontrolu basarisiz. Tespit edilen sorunlar:" -ForegroundColor Red
    foreach ($s in $sorunlar) {
        Write-Host " - $s" -ForegroundColor Yellow
    }
    exit 1
}

Write-Host "Encoding kontrolu basarili. BOM/mojibake sorunu bulunmadi." -ForegroundColor Green
exit 0
