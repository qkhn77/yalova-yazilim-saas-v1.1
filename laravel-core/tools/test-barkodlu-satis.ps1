$ErrorActionPreference = "Stop"

Write-Host "Barkodlu satis zorunlu test paketi baslatiliyor..." -ForegroundColor Cyan

Write-Host "Encoding kontrolu calisiyor..." -ForegroundColor Cyan
powershell -ExecutionPolicy Bypass -File tools/check-text-encoding.ps1
if ($LASTEXITCODE -ne 0) {
    Write-Host "Encoding kontrolu basarisiz." -ForegroundColor Red
    exit $LASTEXITCODE
}

php artisan test --filter="BarkodluSatis(IadeGuvenlik|IzlemeHardening|TahsilatVeFis)Test"

if ($LASTEXITCODE -ne 0) {
    Write-Host "Barkodlu satis testleri basarisiz." -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host "Barkodlu satis testleri basarili." -ForegroundColor Green
