$ErrorActionPreference = 'Stop'

$appRoot = Split-Path -Parent $PSScriptRoot
$sourceRoot = Join-Path $appRoot 'theme\yalovakamera'
$publicRoot = Join-Path (Split-Path -Parent $appRoot) 'public_html\theme\yalovakamera'

$bundles = @(
    @{ Name = 'admin-panel-bundle.css'; Files = @((Join-Path $sourceRoot 'css\admin-panel-overrides.css'), (Join-Path $sourceRoot 'css\admin-custom-sidebar.css')); Outputs = @((Join-Path $sourceRoot 'css\admin-panel-bundle.css'), (Join-Path $publicRoot 'css\admin-panel-bundle.css')) },
    @{ Name = 'admin-panel-bundle.js'; Files = @((Join-Path $sourceRoot 'js\admin-panel-overrides.js'), (Join-Path $sourceRoot 'js\admin-custom-sidebar.js')); Outputs = @((Join-Path $sourceRoot 'js\admin-panel-bundle.js'), (Join-Path $publicRoot 'js\admin-panel-bundle.js')) }
)

foreach ($bundle in $bundles) {
    $parts = foreach ($file in $bundle.Files) {
        if (-not (Test-Path -LiteralPath $file)) { throw "Admin asset bulunamadı: $file" }
        "/* source: $([IO.Path]::GetFileName($file)) */`r`n$([IO.File]::ReadAllText($file, [Text.Encoding]::UTF8).Trim())"
    }
    $content = ($parts -join "`r`n`r`n") + "`r`n"
    foreach ($output in $bundle.Outputs) {
        New-Item -ItemType Directory -Path (Split-Path -Parent $output) -Force | Out-Null
        [IO.File]::WriteAllText($output, $content, [Text.UTF8Encoding]::new($false))
    }
    Write-Output "$($bundle.Name) oluşturuldu: $([Text.Encoding]::UTF8.GetByteCount($content)) byte"
}
