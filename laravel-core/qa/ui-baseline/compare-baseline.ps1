[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$CandidateDirectory,

    [string]$BaselineDirectory = (Split-Path -Parent $MyInvocation.MyCommand.Path),

    [switch]$StrictImages
)

$ErrorActionPreference = 'Stop'
$OutputEncoding = [System.Text.UTF8Encoding]::new($false)
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false)

$baselineDirectory = (Resolve-Path -LiteralPath $BaselineDirectory).Path
$candidateDirectory = (Resolve-Path -LiteralPath $CandidateDirectory).Path
$baselineReportPath = Join-Path $baselineDirectory 'report.json'
$candidateReportPath = Join-Path $candidateDirectory 'report.json'

if (-not (Test-Path -LiteralPath $baselineReportPath)) {
    throw "Baseline raporu bulunamadı: $baselineReportPath"
}
if (-not (Test-Path -LiteralPath $candidateReportPath)) {
    throw "Aday raporu bulunamadı: $candidateReportPath"
}

$baseline = Get-Content -LiteralPath $baselineReportPath -Raw -Encoding UTF8 | ConvertFrom-Json
$candidate = Get-Content -LiteralPath $candidateReportPath -Raw -Encoding UTF8 | ConvertFrom-Json
$technicalDifferences = [System.Collections.Generic.List[object]]::new()
$visualDifferences = [System.Collections.Generic.List[object]]::new()

function Get-SnapshotSignature {
    param([Parameter(Mandatory = $true)]$Snapshot)

    return [ordered]@{
        route = $Snapshot.route
        userContext = $Snapshot.userContext
        activeLayout = $Snapshot.activeLayout
        colorTheme = $Snapshot.colorTheme
        viewport = "$($Snapshot.viewport.width)x$($Snapshot.viewport.height)"
        rootLayoutClass = $Snapshot.rootLayoutClass
        rootLayoutClassCount = $Snapshot.rootLayoutClassCount
        renderer = $Snapshot.renderer
        navigationCount = $Snapshot.navigationCount
        navigationLabels = @($Snapshot.navigationLabels)
        secondarySidebar = $Snapshot.secondaryNavigationCount.sidebar
        secondarySelect = $Snapshot.secondaryNavigationCount.select
        secondaryTabs = $Snapshot.secondaryNavigationCount.tabs
        horizontalOverflow = $Snapshot.horizontalOverflow
        consoleErrorCount = $Snapshot.consoleErrorCount
        consoleWarningCount = $Snapshot.consoleWarningCount
        serverError = $Snapshot.serverError
        httpStatus = $Snapshot.httpStatus
        asset404Count = $Snapshot.asset404Count
        cssAssetCount = $Snapshot.performance.cssAssetCount
        jsAssetCount = $Snapshot.performance.jsAssetCount
        sidebarWidth = [Math]::Round([double]($Snapshot.layoutGeometry.sidebar.width), 0)
        mainX = [Math]::Round([double]($Snapshot.layoutGeometry.main.x), 0)
        visibleModalCount = $Snapshot.visibleModalCount
    }
}

$baselineById = @{}
foreach ($snapshot in $baseline.snapshots) { $baselineById[$snapshot.id] = $snapshot }
$candidateById = @{}
foreach ($snapshot in $candidate.snapshots) { $candidateById[$snapshot.id] = $snapshot }

$allIds = @($baselineById.Keys + $candidateById.Keys | Sort-Object -Unique)
foreach ($id in $allIds) {
    if (-not $baselineById.ContainsKey($id)) {
        $technicalDifferences.Add([ordered]@{ id = $id; type = 'snapshot-added' })
        continue
    }
    if (-not $candidateById.ContainsKey($id)) {
        $technicalDifferences.Add([ordered]@{ id = $id; type = 'snapshot-missing' })
        continue
    }

    $baselineSignature = Get-SnapshotSignature $baselineById[$id] | ConvertTo-Json -Depth 8 -Compress
    $candidateSignature = Get-SnapshotSignature $candidateById[$id] | ConvertTo-Json -Depth 8 -Compress
    if ($baselineSignature -ne $candidateSignature) {
        $technicalDifferences.Add([ordered]@{
            id = $id
            type = 'metadata-changed'
            baseline = Get-SnapshotSignature $baselineById[$id]
            candidate = Get-SnapshotSignature $candidateById[$id]
        })
    }

    $baselineImage = Join-Path $baselineDirectory $baselineById[$id].screenshot
    $candidateImage = Join-Path $candidateDirectory $candidateById[$id].screenshot
    if ((Test-Path -LiteralPath $baselineImage) -and (Test-Path -LiteralPath $candidateImage)) {
        $baselineHash = (Get-FileHash -LiteralPath $baselineImage -Algorithm SHA256).Hash
        $candidateHash = (Get-FileHash -LiteralPath $candidateImage -Algorithm SHA256).Hash
        if ($baselineHash -ne $candidateHash) {
            $visualDifferences.Add([ordered]@{
                id = $id
                baseline = $baselineById[$id].screenshot
                candidate = $candidateById[$id].screenshot
                baselineSha256 = $baselineHash
                candidateSha256 = $candidateHash
            })
        }
    }
}

$comparison = [ordered]@{
    generatedAt = (Get-Date).ToUniversalTime().ToString('o')
    baseline = $baselineReportPath
    candidate = $candidateReportPath
    snapshotCountBaseline = @($baseline.snapshots).Count
    snapshotCountCandidate = @($candidate.snapshots).Count
    technicalDifferenceCount = $technicalDifferences.Count
    visualDifferenceCount = $visualDifferences.Count
    technicalPassed = $technicalDifferences.Count -eq 0
    strictImagePassed = (-not $StrictImages) -or $visualDifferences.Count -eq 0
    technicalDifferences = $technicalDifferences
    visualDifferences = $visualDifferences
}

$comparisonPath = Join-Path $candidateDirectory 'comparison.json'
[System.IO.File]::WriteAllText(
    $comparisonPath,
    (($comparison | ConvertTo-Json -Depth 12) + [Environment]::NewLine),
    [System.Text.UTF8Encoding]::new($false)
)

Write-Output "Teknik fark: $($technicalDifferences.Count)"
Write-Output "Görsel hash farkı: $($visualDifferences.Count)"
Write-Output "Karşılaştırma: $comparisonPath"

if ($technicalDifferences.Count -gt 0 -or ($StrictImages -and $visualDifferences.Count -gt 0)) {
    exit 1
}

