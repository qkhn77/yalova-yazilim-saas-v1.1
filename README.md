# Yalova Yazılım SaaS

Laravel uygulaması `laravel-core`, web kökü ve Vite çıktıları `public_html` dizinindedir.

## CI ve test

GitHub Actions workflow'u her `push` ve `pull_request` olayında PHP bağımlılıklarını kurar, Vite production build'ini üretir, Blade görünümlerini derler ve tam backend regression suite'ini çalıştırır. Testler `phpunit.xml` tarafından zorlanan SQLite `:memory:` veritabanını kullanır; geliştirme veya production MySQL veritabanına bağlanmaz.

Yerel backend regression:

```powershell
cd laravel-core
php artisan test
```

Asset build:

```powershell
cd laravel-core
npm ci
npm run build
```

UI visual regression, Chrome/CDP ve mevcut yerel QA oturumuna bağlı olduğu için otomatik Linux CI kapısına dahil değildir. Yerel/manual QA gate olarak çalıştırılır:

```powershell
cd laravel-core
.\qa\ui-baseline\run-baseline.ps1
```

Baseline yalnız bilinçli bir görsel değişiklik onaylandığında güncellenir:

```powershell
cd laravel-core
.\qa\ui-baseline\run-baseline.ps1 -UpdateBaseline
```

`-UpdateBaseline` normal CI veya rutin regresyon kontrolünde kullanılmamalıdır.
