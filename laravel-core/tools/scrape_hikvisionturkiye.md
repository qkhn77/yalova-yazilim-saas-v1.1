# Hikvisionturkiye scraper

Komut:

```powershell
php tools\scrape_hikvisionturkiye.php
```

Detay sayfalarini atlamak icin:

```powershell
php tools\scrape_hikvisionturkiye.php --no-fetch-details
```

Tum gorselleri indirmek icin:

```powershell
php tools\scrape_hikvisionturkiye.php --download-images
```

Cikti klasoru varsayilan olarak:

`hikvisionturkiye.net\urunler`

Uretilen dosyalar:

- `products.json`
- `products.xlsx`
- `images\...` (`--download-images` kullanilirsa)
