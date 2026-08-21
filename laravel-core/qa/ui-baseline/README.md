# Admin UI Regresyon Baseline

Bu klasör Faz 4C kapsamında `modern-vertical`, `compact-vertical` ve
`horizontal` admin düzenleri için üretilen tekrar kullanılabilir QA
baseline'ını içerir. Araç production route, model, servis, yetki, tenant veya
navigation kararlarını değiştirmez; iki gerçek kullanıcıyla yalnız oturum,
layout tercihi ve render testi yapar.

## İçerik

- `capture-baseline.mjs`: Chrome DevTools Protocol ile screenshot ve teknik
  metadata üretir. Ek npm paketi gerektirmez.
- `run-baseline.ps1`: Laravel geliştirme sunucusunu gerektiğinde başlatır ve
  capture aracını çalıştırır.
- `report.json`: Son başarılı/başarısız çalışmanın manifesti.
- `screenshots/`: Okunabilir isimli viewport screenshotları.

## Yeniden çalıştırma

PowerShell'de parolaları yalnız mevcut terminal oturumu için tanımlayın:

```powershell
$env:QA_MANAGER_USER = 'gokhan'
$env:QA_MANAGER_PASSWORD = '<yönetici parolası>'
$env:QA_TENANT_CODE = 'F7398E1A'
$env:QA_TENANT_USER = 'satis'
$env:QA_TENANT_PASSWORD = '<firma kullanıcısı parolası>'
./qa/ui-baseline/run-baseline.ps1
```

Baseline mevcutsa bu komut referansı ezmez. Yeni sonucu
`runs/YYYYMMDD-HHMMSS/` altına yazar ve otomatik karşılaştırma üretir.
Onaylanmış yeni görünümü referans yapmak için açıkça:

```powershell
./qa/ui-baseline/run-baseline.ps1 -UpdateBaseline
```

Mevcut bir aday klasörü tekrar karşılaştırmak için:

```powershell
./qa/ui-baseline/compare-baseline.ps1 -CandidateDirectory './qa/ui-baseline/runs/YYYYMMDD-HHMMSS'
```

İsteğe bağlı değişkenler:

- `QA_BASE_URL` (varsayılan `http://127.0.0.1:8000`)
- `QA_CHROME_PATH`
- `QA_PHP_PATH`
- `QA_MYSQL_PATH`
- `QA_DB_HOST`, `QA_DB_PORT`, `QA_DB_DATABASE`, `QA_DB_USERNAME`,
  `QA_DB_PASSWORD`

Araç iki kullanıcının başlangıç `admin_layout` değerini veritabanından okur
ve `finally` aşamasında aynı değere geri yükler. Parolalar `report.json`
içine yazılmaz.

## Karşılaştırma sözleşmesi

Bir sonraki UI değişikliğinden sonra komutu yeniden çalıştırın ve şunları
karşılaştırın:

1. `report.json` içindeki `assertions` ve her snapshot'ın teknik alanları.
2. Aynı isimli PNG dosyalarının görsel farkı.
3. Menü sayıları: yönetici `16`, firma `12`.
4. Secondary navigation sayaçlarının tamamı `0`.
5. Console/runtime, HTTP 500, asset 404 ve yatay overflow sayaçları.

Screenshotlar runtime asset değildir ve `public` klasörüne kopyalanmaz.

`comparison.json` teknik metadata farklarını ve değişen PNG hash'lerini ayrı
listeler. Dinamik saat/veri içeren ekranlarda PNG hash farkı görsel inceleme
gerektirir; teknik assertion farkı ise komutu başarısız (`exit 1`) tamamlar.
