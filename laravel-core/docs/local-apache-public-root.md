# Local Apache Public Root Ayari

Bu proje iki ana klasörle çalışır: Laravel çekirdeği `laravel-core`, web sunucusu document root'u `public_html` klasörüdür.
Production ve local Apache yapılandırması aynı public_html modelini kullanır.

Artisan, Composer ve npm komutları `laravel-core` klasörü içinde çalıştırılmalıdır:

```powershell
cd C:\xampp\htdocs\yalova-kamera\laravel-core
php artisan optimize:clear
composer install --no-dev --optimize-autoloader
```

Canlı sunucuda yalnızca `public_html` klasörü web erişimine açık bırakılmalı; `laravel-core` document root yapılmamalıdır.

## Onerilen URL

`http://yalova-kamera.local`

HTTPS icin:

`https://yalova-kamera.local`

Mevcut `http://localhost/yalova-kamera/` adresi de kök uyumluluk katmanı üzerinden çalışmaya devam eder.

## Apache VirtualHost Ornegi

XAMPP Apache `httpd-vhosts.conf` icine benzer bir blok ekleyin.
Hazir dosya ornegi:

[yalova-kamera.local.conf](/C:/xampp/htdocs/yalova-kamera/laravel-core/tools/apache/yalova-kamera.local.conf)

Temel HTTP blok:

```apache
<VirtualHost *:80>
    ServerName yalova-kamera.local
DocumentRoot "C:/xampp/htdocs/yalova-kamera/public_html"

    <Directory "C:/xampp/htdocs/yalova-kamera/public_html">
        AllowOverride All
        Require all granted
        Options Indexes FollowSymLinks
    </Directory>

    ErrorLog "logs/yalova-kamera-error.log"
    CustomLog "logs/yalova-kamera-access.log" common
</VirtualHost>
```

HTTPS icin ayni hosta SSL blok ekleyin:

```apache
<VirtualHost *:443>
    ServerName yalova-kamera.local
DocumentRoot "C:/xampp/htdocs/yalova-kamera/public_html"

    SSLEngine on
    SSLCertificateFile "C:/xampp/apache/conf/ssl/yalova-kamera.local.crt"
    SSLCertificateKeyFile "C:/xampp/apache/conf/ssl/yalova-kamera.local.key"

    <Directory "C:/xampp/htdocs/yalova-kamera/public_html">
        AllowOverride All
        Require all granted
        Options Indexes FollowSymLinks
    </Directory>

    SetEnvIf X-Forwarded-Proto https HTTPS=on

    ErrorLog "logs/yalova-kamera-ssl-error.log"
    CustomLog "logs/yalova-kamera-ssl-access.log" common
</VirtualHost>
```

## hosts Dosyasi

Windows `hosts` dosyasina su satiri ekleyin:

```txt
127.0.0.1 yalova-kamera.local
```

Dosya yolu:

`C:\Windows\System32\drivers\etc\hosts`

## Sonrasi

1. Apache'yi yeniden baslatin.
2. Tarayicidan `http://yalova-kamera.local` adresini acin.
3. SSL tanimliysa `https://yalova-kamera.local` adresini de test edin.

## APP_URL

Local `VirtualHost` kullaniyorsaniz `.env` icinde:

```env
APP_URL=https://yalova-kamera.local
```

veya SSL kurmadan baslayacaksaniz:

```env
APP_URL=http://yalova-kamera.local
```

Ardindan:

```bash
php artisan optimize:clear
```

## Neden Daha Dogru

- Production ile ayni `public_html` giris noktasi kullanilir.
- `.htaccess` ve asset davranisi daha tutarli olur.
- Klasor altinda calisma kaynakli path sorunlari azalir.
- Laravel'in asset, route ve upload URL mantigi daha temiz calisir.
- HTTP ve HTTPS davranisi `localhost/alt-klasor` kurulumuna gore daha kararlidir.

## HTTPS Notu

Localde HTTPS icin bir sertifika gerekir. En rahat yontem `mkcert` benzeri bir yerel sertifika aracidir.

Olusacak dosyalar tipik olarak:

- `C:/xampp/apache/conf/ssl/yalova-kamera.local.crt`
- `C:/xampp/apache/conf/ssl/yalova-kamera.local.key`

Bu dosya yollarini kendi ortaminiza gore guncelleyebilirsiniz.

## Upload Dosyalari Nerede

Bu projede webde gorunen upload dosyalari esas olarak Laravel `public` diskine yaziliyor.

Kod karsiligi:

- disk tanimi: `config/filesystems.php`
- `public` disk root: `storage/app/public`
- public disk URL: `/uploads`

Yani hem localde hem canlida temel mantik su:

- fiziksel dosya: `storage/app/public/...`
- web URL: `/uploads/...`

## Ornekler

- site logolari: `storage/app/public/settings/logos/...`
- urun ana gorselleri: `storage/app/public/products/main/...`
- urun galeri gorselleri: `storage/app/public/products/gallery/...`
- proje gorselleri: `storage/app/public/projects/...`
- servis gorselleri: `storage/app/public/services/...`
- bilgi sayfasi gorselleri: `storage/app/public/info-pages/...`

## Canli Ortam

Canlida uygulama doğrudan `public_html` altından açılır; upload edilen dosyalar yine uygulamanın:

`storage/app/public/...`

altinda tutulur ve webde:

`/uploads/...`

URL'i ile servis edilir.

Bu projede `/uploads/{path}` route'u dogrudan `Storage::disk('public')` icinden dosya dondurur.
