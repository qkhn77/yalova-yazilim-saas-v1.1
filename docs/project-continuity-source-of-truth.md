# Yalova Yazılım SaaS — Proje Devamlılık ve Teknik Kaynak Dosyası

**Belge türü:** Yeni sohbetler için teknik devir / başlangıç bağlamı  
**Son derleme:** 26.08.2026  
**Repository kökü:** `C:\Users\Codex\Desktop\yalova-yazilim-saas`  
**Uygulama kökü:** `C:\Users\Codex\Desktop\yalova-yazilim-saas\laravel-core`

Bu belge; geçmişte yapılan sistem toparlama, şema yakınsaması, muhasebe entegrasyonu, iş akışı doğrulaması, release paketleme ve son admin sidebar çalışmasını yeni sohbetlerde hızlıca anlamak için hazırlanmıştır.

Bu belge birincil kaynakların yerine geçmez. Yeni değişiklikte önce bu dosya okunmalı, sonra ilgili gerçek kod/migration/test ve kaynak raporlar kontrol edilmelidir. Rapor ile mevcut kod çelişirse çelişki açıkça belirtilmeli ve varsayım yapılmamalıdır.

> Güvenlik: Bu belgede üretim parolası, APP_KEY, reCAPTCHA secret/site key, token veya başka bir gizli değer bulunmaz. Sohbette daha önce paylaşılmış olabilecek gizliler güvenli kabul edilmemeli ve üretimde döndürülmelidir.

## 1. Yeni sohbette okunacak başlangıç sırası

1. `AGENTS.md`
2. Bu dosya: `docs/project-continuity-source-of-truth.md`
3. İlgili modülün birincil kodu ve migrationları
4. İlgili testler
5. İlgili final rapor

Şema veya deployment işi varsa ayrıca şunları okuyun:

- `database-health-audit.md`
- `database-clean-install-report.md`
- `database-clean-application-report.md`
- `production-backup-upgrade-report.md`
- `output/phase44/canonical-schema-target-final.md`
- `output/phase44/final-canonical-closure-audit.md`
- `output/phase45/full-corrective-schema-gate-report.md`
- `output/phase45/all-fk-batches-implementation-report.md`
- `output/phase46/final-phase4-closure-report.md`
- `output/phase5/phase53c-final-packaging-report.md`
- `output/phase5/phase54-production-cutover-report.md`

Muhasebe geliştirmesi için:

- `laravel-core/docs/muhasebe-gelistirme-on-promptu.md`
- `laravel-core/docs/muhasebe-gelistirme-entegrasyon-raporu-2026-07-13.md`
- `laravel-core/docs/e-ticaret-modul-kurallari.md`
- `laravel-core/docs/barkodlu-satis-gelistirme-guvenlik-checklist.md`
- `laravel-core/docs/masraf-takibi-devam-konteksti.md`

UI/tablo/kart geliştirmesi için:

- `laravel-core/docs/architecture/admin-table-standard.md`
- `laravel-core/docs/architecture/admin-card-standard.md`
- `docs/sidebar-development-handoff.md`

## 2. Projenin genel kimliği

Yalova Yazılım SaaS, tek Laravel uygulaması içinde çok sayıda iş modülünü birleştiren, Filament tabanlı, çok firmalı bir SaaS monolith/modular-monolith yapısıdır.

| Katman | Gerçek durum |
|---|---|
| Backend | Laravel 11, PHP `^8.2` |
| Admin panel | Filament 3.3 + Livewire |
| Frontend asset | Vite 5 + Axios + Laravel Vite Plugin |
| Veritabanı | MySQL driver; MySQL 8 ve MariaDB uyumluluğu ayrıca gözetilir |
| Local DB doğrulaması | İzole MariaDB 10.4.32, çoğunlukla `127.0.0.1:3307` |
| Test DB | `phpunit.xml` ile zorlanan SQLite `:memory:` |
| Admin URL | `/admin` |
| Yönetici login | `/yonetici-giris` |
| Web kökü | Repository içindeki `public_html` |
| Uygulama kökü | `laravel-core` |
| Hosting yerleşimi | `laravel-core` webroot dışında, `public_html` document root |

Composer kaynağı `laravel-core/composer.json`, frontend script kaynağı `laravel-core/package.json` dosyasıdır. Önemli bağımlılıklar Laravel, Filament, Doctrine DBAL, Endroid QR Code, PHPUnit ve Vite'tır.

## 3. Repository yerleşimi

```text
C:\Users\Codex\Desktop\yalova-yazilim-saas
├─ laravel-core/                 Laravel uygulaması
│  ├─ app/                       Controller, model, service, Filament ve policy
│  ├─ database/migrations/       304 mevcut migration
│  ├─ database/seeders/          SaaS, yetki, CMS ve test seed'leri
│  ├─ resources/css/             Filament/CORK CSS
│  ├─ resources/views/           Blade ve Filament görünümleri
│  ├─ resources/js/              Frontend JS
│  ├─ routes/                    web.php ve console.php
│  ├─ tests/                     Unit/Feature testleri
│  └─ qa/ui-baseline/            Manual/local visual baseline
├─ public_html/                  Document-root statik dosyalar/build
├─ output/phase44/                Canonical şema ve FK/index kararları
├─ output/phase45/                Corrective migration kanıtı
├─ output/phase46/                Production rehearsal ve business E2E
├─ output/phase5/                 Release, backup, package, deployment
└─ database-*.md / production-*  İlk DB audit raporları
```

## 4. Uygulama mimarisi ve kurallar

### 4.1 Panel ve modüller

Filament panel kaydı `laravel-core/app/Providers/Filament/AdminPanelProvider.php` içindedir. Cluster'lar `laravel-core/app/Filament/Clusters` altındadır:

`Ayarlar`, `ETicaret`, `MasrafTakip`, `Muhasebe`, `PersonelTakip`, `ProjeYonetimi`, `Restoran`, `Sekreter`, `TeklifYonetimi`, `TeknikServis`, `Web`.

Navigation/sidebar görünümü, modül erişimi ve layout seçimleri provider, cluster, `SidebarService` ve CSS katmanları birlikte üzerinden çalışır.

### 4.2 Model–service–UI ayrımı

- Eloquent model veri ve ilişki sözleşmesini taşır.
- İş kuralı mümkün olduğunca `app/Muhasebe/Servisler`, `app/TeknikServis/Servisler`, `app/Services` veya ilgili module service sınıfında olmalıdır.
- Filament formu iş kuralının tek kaynağı değildir.
- Yeni yazma işleminde transaction, tenant sınırı, idempotency, audit ve ters kayıt davranışı birlikte incelenir.
- Finansal değerlerde float kullanılmaz; decimal/BCMath ve mevcut para snapshot kuralları korunur.

### 4.3 Çok firmalı tenant modeli

Ana mekanizmalar:

- `app/Services/TenantContextService.php`
- `app/Http/Middleware/FilamentTenantContextMiddleware.php`
- `app/Models/Concerns/HasFirmaTenantScope.php`
- `app/Traits/HasFirma.php`
- `app/Models/Scopes/FirmaIdTenantScope.php`
- `app/Models/Scopes/TanimFirmaTenantScope.php`
- `app/Models/Scopes/TeknikServisTanimFirmaScope.php`
- `app/Muhasebe/Guvenlik/MuhasebeFirmaErisimDenetleyicisi.php`
- `app/Muhasebe/Guvenlik/MuhasebeFilamentErisimYardimcisi.php`

Aktif firma session/context üzerinden taşınır. Super admin tüm firmaları yönetebilir; firma kullanıcıları aktif firma ile sınırlıdır.

Kritik risk: `withoutGlobalScopes()`, doğrudan `DB::table()` sorguları ve console/job/scheduler yolları global tenant scope'u otomatik uygulamaz. Bu sorgularda `firma_id` açıkça doğrulanmalıdır. Tenant isolation statik incelemeyle kusursuz kanıtlanmamıştır; risk seviyesi **HIGH**'dır.

### 4.4 Yetki

Yetki şablonları `app/Support/MuhasebeYetkiSablonlari.php`, `app/Support/MasrafTakipYetkiSablonlari.php` ve modül erişim yardımcılarındadır. Örnek yetkiler: `muhasebe.goruntule`, `cari.goruntule`, `fatura.onay`, `finans.olustur`, `stok.guncelle`.

Yeni ekran yalnız UI'da gizlenmemeli; backend policy/access helper de kontrol edilmelidir.

## 5. Modül haritası ve muhasebe entegrasyonu

### 5.1 SaaS / Firma

Ana modeller/tables: `Firma`/`firmalar`, `User`/`users`, `FirmaKullanici`/`firma_kullanicilari`, `FirmaModulu`/`firma_modulleri`, `FirmaAboneligi`/`firma_abonelikleri`, `FirmaAyari`/`firma_ayarlari`, `Plan`/`planlar`, `Modul`/`moduller`, `Rol`/`roller`, `Yetki`/`yetkiler`, `RolYetkisi`/`rol_yetkileri`.

Firma tenant verisinin köküdür. Firma hard-delete kararları ticari geçmişi etkiler; mevcut `RESTRICT` ve uygulama politikaları korunmalıdır.

### 5.2 Muhasebe / Cari çekirdeği

| Alan | Model / tablo | Görev |
|---|---|---|
| Cari | `Cari` / `cariler` | Müşteri, tedarikçi veya diğer cari kartı |
| Cari hareket | `CariHareketi` / `cari_hareketleri` | Borç/alacak hareketleri |
| FIFO eşleşme | `CariHareketEslesmesi` / `cari_hareket_eslesmeleri` | Açık borç/alacak kalemlerini eşleştirme |
| Fatura | `Fatura` / `faturalar` | Giden/gelen, gider, proforma, iade, iptal ve durumlar |
| Fatura kalemi | `FaturaKalemi` / `fatura_kalemleri` | Hizmet/stok, vergi/indirim/tutar snapshot'ları |
| Finans hareketi | `FinansHareketi` / `finans_hareketleri` | Tahsilat, ödeme, mahsup, ters hareket |
| Fatura kapama | `FaturaFinansKapama` / `fatura_finans_kapatmalari` | Finans tutarının faturaya uygulanması |
| Kasa/banka/POS | `KasaHesabi`, `BankaHesabi`, `PosHesabi` | Para hareketinin hesabı |
| Hesap hareketi | `KasaHareketi`, `BankaHareketi`, `PosHareketi` | Hesap bazlı finans ayrıntısı |
| Alacak planı | `AlacakPlani`, `AlacakPlanTaksiti` | Vadeli/taksitli alacak ve tahsilat |
| Döviz | `ParaBirimi`, `DovizKuru` | Para birimi ve kur altyapısı |
| E-belge | `NetteFaturaGonderimi`, `NetteFaturaGelenBelge` | E-belge ve entegrasyon kayıtları |

Temel servisler:

- `FaturaIslemServisi`
- `FaturaToplamDogrulamaServisi`
- `FaturaFinansKapamaServisi`
- `FinansHareketServisi`
- `CariHareketServisi`
- `CariHareketFifoEslestirmeServisi`
- `CariBakiyeServisi`
- `AlacakPlanServisi` / `AlacakTahsilatServisi`
- `StokHareketServisi`
- `FaturaOlcuKalemiServisi`

Kaynak dizin: `laravel-core/app/Muhasebe/Servisler`.

### 5.3 Fatura onayı

```text
Taslak / Beklemede fatura
        │
        ├─ toplam ve kalem doğrulaması
        ├─ cari hareketi
        ├─ stok kalemi varsa stok hareketi
        ├─ fatura durumu = onayli
        └─ afterCommit gerekiyorsa avans/plan mahsubu
```

Kurallar:

- Proforma/bekleyen/iptal gibi kayıt üretmeyen türler cari/stok hareketi üretmez.
- Kayıt üreten faturada cari gerekir.
- Fatura carisi, stok kartı ve ilişkili kayıtlar aynı firmaya ait olmalıdır.
- Aynı fatura ikinci kez onaylandığında duplicate hareket oluşmamalıdır.
- İptal/iade sırasında finans hareketi fiziksel olarak silinmez; ters kayıt/iptal politikası kullanılır.
- Kaynak bağlantısı `belge_turu` ve `belge_id` ile izlenir.

### 5.4 Tahsilat / ödeme

```text
FinansHareketi (Tahsilat veya Ödeme)
  ├─ KasaHareketi / BankaHareketi / PosHareketi
  ├─ CariHareketi
  ├─ FaturaFinansKapama
  ├─ fatura ödeme durumu
  └─ gerekiyorsa AlacakTahsilatServisi ve taksit eşleşmesi
```

Nakit, banka, POS, kur dönüşümü, POS komisyonu ve virman aynı servis ailesindedir. Fatura kapama FIFO/otomatik dağıtım ve avans mahsubu yapabilir. Eski finans kaydını silmek yerine ters hareket politikası korunur.

### 5.5 Stok / depo / ölçü / seri

Ana modeller/tables: `StokKarti`/`stok_kartlari`, `StokHareketi`/`stok_hareketleri`, `muhasebe_depolar`, stok depo bakiyeleri, barkod, seri ve `fatura_olcu_dagilimlari` tabloları.

Ana servisler: `StokHareketServisi`, `StokMaliyetHesaplamaServisi`, `StokDegerlemeServisi`, `StokOlcuHesaplamaServisi`, `StokOlcuBakiyeServisi`, `StokIzlemeServisi`, `StokBarkodServisi`, `CanonicalBirimGecisServisi`, `BirimKodResolver`.

Stok yazma akışında transaction ve `lockForUpdate()` önemlidir. Negatif stok varsayılan olarak kapalıdır. İptalde geçmiş hareket silinmez; kaynak iptal edilir ve ters hareket oluşturulur. Maliyet mevcut servis üzerinden hesaplanır.

Canonical ölçü durumu:

- Birim ID `1` korunur; canonical kod `AD`'dir.
- Sistem birimleri: `AD`, `MTR`, `MTK`, `MTQ`, `KGM`.
- `KILO` ve `SAAT` gibi mevcut değerler silinmez.
- Rehearsal'da 214 stok kartının tamamı `basit` takip tipindeydi.
- `stok_takip_tipi` yanlış dönüştürülmemeli; seri/parti olmayan kartlara takip davranışı eklenmemelidir.

### 5.6 İade ve ters kayıt

İade, kaynak fatura/satış bağlantısını korur. Miktar kaynakta kalan miktarı aşamaz. Stok ve cari/finans etkisi ters yönde üretilir; geçmiş kayıtlar fiziksel olarak silinmez.

Faz 4 local kapanışında doğrulananlar:

- Stok/depo: `10 − 4 + 1 − 1 = 6`.
- Ödeme/tahsilat: `480 + 720 = 1200`, `240 + 240 = 480`.
- Dört iade faturası DB'de `onayli`.
- Over-return denemesinde oluşturma kapalı kaldı; ek kayıt oluşmadı.

### 5.7 E-ticaret / ürün / ödeme

Ana kod: `EcommerceMuhasebeEntegrasyonServisi`, `EcommerceCariServisi`, `EcommerceMuhasebeOdemeHedefServisi`, `CheckoutServisi`, `SiparisOdemeServisi`, `app/Models/Ecommerce/Siparis.php`.

Başarılı ödeme sonrasında müşteri carisi, proforma/fatura bağlantısı, finans tahsilatı ve stok rezervi/çıkışı birlikte ele alınır. Sipariş iptalinde ödeme ve stok etkisi tersine alınır.

Önemli ayrım: Sipariş stoğu kendi rezerv/çıkış akışına sahip olabilir; fatura onayındaki `StokHareketServisi` ile karıştırılırsa stok iki kez düşebilir. Callback oturumsuz çalışabileceği için firma ve kaynak doğrulaması ayrıca yapılır.

E-ticaret kapatma kuralı route/UI giriş noktalarını kapatır; muhasebe, stok ve fatura çekirdeğinin işleyişini değiştirmemelidir.

### 5.8 Teknik Servis

Ana tablolar: `teknik_servis_kayitlari`, teknik servis kalemleri, `teknik_servis_kayitli_cihazlar`, cihaz marka/model, durum geçmişi, tahsilat ve muhasebe bağlantıları.

```text
Servis kaydı + cari + tahsilat kanalı
  ├─ FinansHareketi
  ├─ Kasa/Banka/POS hareketi
  ├─ TeknikServisTahsilati
  ├─ TeknikServisMuhasebeBaglantisi
  └─ gerekiyorsa satış faturası / alacak planı
```

Teknik servis device identity index:

```text
teknik_servis_kayitli_cihazlar
(firma_id, cihaz_id, marka_id, model_no)
NON-UNIQUE: ts_kayitli_cihazlar_kimlik_idx
```

Ana kod `app/TeknikServis/Servisler` ve `app/TeknikServis/Filament` altındadır.

### 5.9 Restoran

`RestoranTahsilatServisi`, `RestoranStokServisi`, `RestoranFaturaServisi`, `RestoranGunSonuMutabakatServisi` ve `RestoranRaporServisi` adisyon, tahsilat, stok ve gün sonu zincirini yönetir. Adisyon kapanışındaki reçete stok düşümü ile restoran faturasındaki hizmet kalemi davranışı çift stok düşümüne karşı birlikte korunmalıdır.

### 5.10 Personel, Teklif, Masraf, Proje, Sekreter, Web

- Personel avansı/maaş ödemesi `FinansHareketi` ödeme türüyle kasa/banka hareketine bağlanır.
- Teklif onayından bekleyen fatura ve kalem üretilebilir; aynı teklif için duplicate fatura engellenir.
- Masraf tabloları `masraf_kategorileri` ve `masraflar`dır; fiziksel silme yerine iptal/pasifleştirme vardır.
- Masraf yazma/sorgulama işlemleri firma sınırı, transaction, idempotency ve decimal kurallarını korur.
- Proje/işletme projesi bağlantıları fatura, cari, finans ve masraf raporlarına yansıyabilir.
- Sekreter görev/not/randevu/hatırlatma alanları tenant ve scheduler ile ilişkilidir.
- Web/CMS bazı tabloları global olabilir; tenant varsayımı otomatik yapılmaz.

## 6. Veritabanı şeması — canonical hedef

### 6.1 Migration envanteri

- İlk historical/current migration zinciri: **294**.
- Corrective FK migrationı: **9** batch, B01–B09.
- Required index migrationı: **1**.
- Güncel repository migration dosyası: **304**.
- Final restore history: **304 current + 7 historical extra = 311**.
- Historical 7 satır metadata'dır; silinmez, yeniden yazılmaz.

Son corrective migrationlar `laravel-core/database/migrations/2026_08_25_090000...099000_*.php` aralığındadır. İsim, timestamp veya eski migration history değiştirilmemelidir.

### 6.2 170 canonical FK

Birincil kaynaklar:

- `output/phase44/fk170-canonical-target.csv`
- `output/phase44/fk-semantic-review.csv`
- `output/phase44/canonical-schema-target-final.md`

| Hedef | Değer |
|---|---:|
| Required canonical FK | 170 |
| CASCADE | 25 |
| RESTRICT / NO ACTION | 81 |
| SET NULL | 64 |
| SKIP | 0 |
| Orphan blocked | 0 |
| Source mapped | 170/170 |
| MySQL 8 | PASS |
| MariaDB 10.4 | PASS |

İlk production clone auditinde 170 relation eksik görünüyordu. Corrective implementation bunları batch'lerle ekledi. Fresh path'te 140 relation aynı aksiyondaydı; 30 mevcut relation için canonical action replacement yapıldı. Bu 30'un 29'u `CASCADE → RESTRICT`, 1'i `CASCADE → SET NULL` oldu.

Batch dağılımı:

| Batch | Modül | FK |
|---|---|---:|
| B01 | Core/Auth/Tenant | 13 |
| B02 | Auth/Permission | 8 |
| B03 | Configuration/Templates | 6 |
| B04 | Accounting/Finance | 32 |
| B05 | Ecommerce/Order | 8 |
| B06 | Stock/Depot/Reference | 25 |
| B07 | Technical Service | 61 |
| B08 | Other Finance | 9 |
| B09 | Other Ecommerce/Content | 8 |
| **Toplam** |  | **170** |

Her batch guarded/idempotent helper ve deterministic constraint name planıyla uygulanmıştır. Self-referential set 7/7 ele alınmıştır. Orphan temizleme veya business data düzeltmesi yapılmaz; beklenmeyen state'te fail-fast tercih edilir.

### 6.3 Index politikası

Tek zorunlu production index:

```text
teknik_servis_kayitli_cihazlar
(firma_id, cihaz_id, marka_id, model_no)
NON-UNIQUE ts_kayitli_cihazlar_kimlik_idx
```

İki fresh-only index production hedefi değildir ve kabul edilmiş non-blocking farktır:

1. `ecommerce_pazaryeri_entegrasyonlari(firma_id, aktif_mi)` — INDEX-1, REJECT
2. `teknik_servis_kayitli_cihaz_degisiklikleri(firma_id, kayitli_cihaz_id)` — INDEX-3, REJECT

Fresh/prod fiziksel index listesi byte-for-byte eşit olmak zorunda değildir.

### 6.4 Üç production legacy kolonu

Final karar ile şu kolonlar production'da korunur, fresh canonical hedefe eklenmez ve otomatik drop edilmez:

- `fatura_kalemleri.uretim_tarihi` — nullable `date`
- `fatura_kalemleri.son_kullanma_tarihi` — nullable `date`
- `muhasebe_barkodlu_satis_iade_kalemleri.seri_nolari` — nullable `longtext`

İlk raporlar bu isimleri başka akışlardaki aktif alanlarla karıştırmıştı. Final direct-target audit current contract kullanımını doğrulamadı. Production'daki kolonlar veri kaybı riski nedeniyle legacy extra olarak tutulur.

### 6.5 Final schema snapshot

Production-copy rehearsal sonunda: **452 physical FK**, **170/170 canonical FK**, **0 orphan**, action dağılımı **25/81/64**, required device index mevcut, commercial row loss **0**. Final package restore ve local self-test bu değerleri yeniden doğruladı.

Canonical schema fresh DB'nin birebir kopyası değildir. Hedef:

```text
required canonical objects
+ accepted fresh extras
+ accepted production legacy extras
+ preserved historical migration metadata
```

## 7. Şema toparlama tarihçesi ve riskli noktalar

- Parti/parça sistemi eklenmiş ve daha sonra cleanup/remove migrationlarıyla kaldırılmıştır. Eski model/migration dosyaları geri yazılmamalıdır.
- `restore_serial_tracking_type` seri takip türünü canonical akışa taşır; her stok kartını serial/parti takipli yapmaz.
- `ADET → AD` dönüşümünde ID 1 korunur.
- `marka_adi → marka_uretici` rename zinciri vardır; alias ile gerçek kolon ayrılmadan değişiklik yapılmaz.
- Migrationlar içinde `dropColumn`, `renameColumn`, `change`, backfill ve metadata/seed işlemleri bulunur.
- Aynı timestamp prefix'li migrationlar vardır; Laravel filename sırası ve foreign key bağımlılıkları korunmalıdır.
- Eski migration rewrite etmek yerine additive corrective migration yaklaşımı kullanılmıştır.

## 8. Test, business invariant ve doğrulama

### 8.1 Komutlar

```powershell
Set-Location C:\Users\Codex\Desktop\yalova-yazilim-saas\laravel-core
php artisan test
npm ci
npm run build
.\qa\ui-baseline\run-baseline.ps1
```

`-UpdateBaseline` yalnız bilinçli görsel değişiklik onaylandığında kullanılmalıdır. SQLite testleri MariaDB/MySQL FK, DDL, collation, decimal, index ve lock farklarını tam göstermez.

### 8.2 Kanıt özeti

Faz 4.5.4: 9/9 FK migration, 170/170 manifest, 170/170 name plan, fresh 140/140 NO-OP, 30/30 REPLACE, production ADD 170/170, orphan 0, idempotent second pass PASS, partial recovery PASS, failure-window recovery PASS, bounded MariaDB 67 test/169 assertion PASS.

Faz 4.6.15: fresh 304/304 migration PASS, seeder run 1/2 idempotent PASS, fresh FK 452, selected regression 95 test/236 assertion PASS, purchase-return browser Run A/B PASS, over-return safety PASS.

Muhasebe/stok geliştirmesinde en az şu invariant'lar kontrol edilmelidir:

- Fatura toplamı = kalem toplamı = ilgili cari/finans etkisi.
- Aynı fatura tekrar onaylanınca duplicate hareket oluşmamalı.
- İade miktarı source invoice kalan miktarını aşmamalı.
- İptal/iade geçmişi silmemeli; ters kayıt izlenebilir olmalı.
- Ödeme fatura kapama ve cari bakiyeye doğru yansımalı.
- Stok miktarı, depo ve ölçü dönüşümü aynı transaction'da tutarlı olmalı.
- Tüm child/source kayıtları aynı `firma_id` tenant'ına ait olmalı.
- Negatif stok, duplicate idempotency key ve cross-tenant relation engellenmeli.

## 9. Production release durumu

### 9.1 Validated artifacts

Final SQL:

`output/phase5/release/database/production-upgraded-final.sql`

SHA-256:

`B76C3BB48772157B94900DC57B0ECCF97109BE5EB840CC8C8673AB933C639A24`

Release ZIP:

`output/phase5/release/yalova-saas-production-20260825-b76c3bb4.zip`

Local rollback reference:

`output/phase5/rollback/database/production-original-predeploy.sql`

Package içine `.env`, node_modules, deploy runner veya production credential konulmaz.

### 9.2 Owner-approved import modeli

```text
EMPTY / RECREATED VERIFIED PRODUCTION DB
→ validated final SQL import
→ production .env
→ first production boot/smoke
```

Production'da 39 migration tekrar çalıştırılmayacak, production migration runner oluşturulmayacak ve strategy 2 kullanılmayacaktır. Import öncesi DB kimliği ve rollback gate PASS olmalıdır.

Bilinen non-secret production identity:

- Engine: MariaDB 10.11.16
- Host: `localhost`
- Port: `3306`
- Database: `yalovaya_db`
- Application user: `yalovaya_user`

Parola bu belgede yoktur ve yeni sohbetlerde istenmemelidir.

Production `.env` webroot dışında, host üzerinde `laravel-core/.env` altında olmalıdır. `APP_ENV=production`, `APP_DEBUG=false`, gerçek DB bilgileri ve host üzerinde yeni üretilmiş APP_KEY kullanılmalıdır. Placeholder değerleri olduğu gibi bırakılmamalıdır.

### 9.3 Son bilinen canlı gözlem

Owner, final SQL importunu phpMyAdmin'de 1.840 query ile başarılı bildirmiştir. Ancak son HTTP gözleminde:

| Endpoint | Son gözlenen |
|---|---:|
| `/up` | 200 |
| `/build/manifest.json` | 200 |
| `/` | 500 |
| `/yonetici-giris` | 500 |
| `/sistem/health` | 500 |
| `/robots.txt` | 500 |

Bu nedenle production uygulaması tam sağlıklı kabul edilmemeli ve deployment gate **CLOSED** kalmalıdır. Public debug açılmamalı; private cPanel/LiteSpeed/PHP error log ve DB içeriği okunarak gerçek exception bulunmalıdır.

### 9.4 Secret remediation

Sohbette production DB parolası, APP_KEY ve reCAPTCHA anahtarları paylaşılmış olabilir. Bunlar bu belgede tekrarlanmaz. Yeni retry öncesi DB parolası ve APP_KEY üretimde döndürülmeli, reCAPTCHA secret/site key de gerekirse yenilenmelidir. Değerler chat, Git, SQL export veya public log'a yazılmamalıdır.

## 10. Google reCAPTCHA çalışma haritası

Runtime ayarları `Setting` abstraction üzerinden tutulur:

- `recaptcha_enabled`
- `recaptcha_site_key`
- `recaptcha_secret_key`

Kaynak: `laravel-core/app/Support/RecaptchaAyarlari.php`.

Login doğrulaması: `app/Http/Controllers/Auth/Concerns/HandlesLoginRecaptcha.php`, `YoneticiGirisDenetleyici.php`, `TenantAuthController.php`.

Form görünümleri: `resources/views/auth/yonetici-giris.blade.php`, `tenant-login.blade.php`, `alici-login.blade.php`, contact/newsletter görünümleri.

Panel ayar ekranı: `app/Filament/Clusters/Web/Pages/WebApiAyarlar.php`.

Çalışma mantığı: enabled true ve site key doluysa widget gösterilir; submit sırasında `g-recaptcha-response` zorunlu olur; backend Google `siteverify` endpoint'ine secret ile istek gönderir. Key domaini `yalovayazilim.com` ile eşleşmeli ve key tipi kodun beklediği v2 checkbox davranışıyla uyumlu olmalıdır.

“Lütfen Google doğrulamasını tamamlayın” genellikle token'ın request'e gelmediğini; “Google doğrulaması başarısız” ise secret/domain/server verification sorunu olabileceğini gösterir. Önce private log, sonra Google domain/key type, admin API ayarları, browser Network request ve cache kontrol edilir. Secret SQL/chat içine yazılmaz.

## 11. Sidebar ve admin UI son durumu

Detaylı devir: `docs/sidebar-development-handoff.md`.

- Modern ve Compact dikey menüde sidebar scroll çalışır.
- Açık alt menüler flex tarafından küçültülmez.
- Footer iki dikey menüde sidebar'ın altında görünür.
- Modern logo ve mevcut toggle görünürdür.
- Compact collapsed durumda logo görünür kalır; kullanıcı gizleme denemesini geri aldırmıştır.
- Compact masaüstünde `.fi-sidebar-logo-toggle` gizlidir; hover/focus genişleme kullanılır.
- Yatay menü footer'ı gizlidir.

İlgili dosyalar:

- `laravel-core/resources/css/filament/cork-admin-shell.css`
- `laravel-core/resources/css/filament/cork-admin-layouts.css`
- `laravel-core/resources/views/filament/components/admin-sidebar-footer.blade.php`
- `laravel-core/app/Providers/Filament/AdminPanelProvider.php`
- `public_html/theme/yalovakamera/css/admin-panel-overrides.css`
- `public_html/theme/yalovakamera/css/admin-panel-bundle.css`

CSS değişikliğinde Modern/Compact ve `941×831`, `504×667`, `372×667` gibi dar viewport'lar kontrol edilmelidir. Footer nav içine taşınmamalıdır.

## 12. Admin tablo ve kart sözleşmesi

`AGENTS.md` bağlayıcıdır.

Tablolar:

- Standart listelerde Filament Table/Livewire kullanılmaya devam edilir.
- Ortak ayarlar `AppServiceProvider` içindeki `Table::configureUsing(...)` katmanındadır.
- Pagination `App\Support\TablePaginationDefaults` içindedir.
- Görsel adapter `resources/css/filament/cork-admin-tables.css` dosyasıdır.
- Arama, filtre, sort ve pagination server-side kalır.
- Normal açılışta tüm kayıtlar browser'a yüklenmez.
- `1000` ve `Hepsi` yalnız kullanıcı seçtiğinde çalışır.
- Export ve total sorguları yalnız aksiyon çağrısında çalışır.

Kartlar:

- Yeni kart kökü `yk-info-card`.
- Kart grubu `yk-info-card-grid`.
- Merkezi `cork-admin-widgets.css` kullanılır; sayfa içine kart CSS'i kopyalanmaz.
- Dashboard KPI, print/PDF/fiş ve küçük selector tabloları otomatik datatable'a dönüştürülmez.

## 13. Seeder ve temiz kurulum

Temiz kurulumda tipik sıra:

1. 304 migration.
2. SaaS modül/rol/yetki/plan seed'leri.
3. Gerekirse local/test super admin seed'i.
4. Muhasebe ölçü birimleri.
5. CMS seed'leri.
6. Demo/QA seed'leri yalnız local/testing.

`DatabaseSeeder` temel CMS seed'lerini çağırır; SaaS çekirdeği seed'leri ayrıca doğrulanır. `Firma::created` gibi model event'leri default para birimi/depo oluşturabilir. Production'da rastgele `db:seed` çalıştırılmaz.

## 14. Geliştirme protokolü

Her yeni sohbette:

1. Kapsamı tek cümleyle belirle.
2. İlgili modül, model, service, migration, route ve testleri bul.
3. Cross-module etkileri yaz: fatura/cari/finans/stok/tenant/rapor.
4. `git status --short` ile dirty worktree'yi kontrol et; ilişkisiz değişiklikleri resetleme.
5. Küçük, geri alınabilir patch uygula.
6. Migration gerekiyorsa canonical manifest ve production history etkisini analiz et.
7. Transaction/idempotency/tenant/orphan/rollback etkisini test et.
8. İlgili testleri çalıştır; SQLite ile kanıtlanmayan MariaDB davranışlarını ayrıca belirt.
9. Build/lint/diff check çalıştır.
10. Değişen dosyaları, test komutlarını, kesin sonucu ve kalan riskleri bildir.

Repository kuralı: yerel dosya değişikliklerinde `apply_patch` kullan. UTF-8 ve Türkçe karakter bütünlüğünü koru. Destructive komutları hedefi doğrulamadan kullanma.

## 15. Hızlı teşhis komutları

```powershell
Set-Location C:\Users\Codex\Desktop\yalova-yazilim-saas
git status --short
git diff --check

Set-Location .\laravel-core
php artisan migrate:status --no-ansi
php artisan about --no-ansi
php artisan route:list --except-vendor --no-ansi
php artisan test
npm run build
```

Schema değişikliği öncesi yalnız inceleme için:

```powershell
Get-ChildItem database/migrations -File -Filter '*.php' | Measure-Object
rg -n "Schema::|foreign|index|drop|rename|change|DB::table" database/migrations app
```

MariaDB doğrulaması yalnız izole portta yapılmalıdır. Ana XAMPP/production 3306'a yanlışlıkla bağlanılmamalıdır.

## 16. Açık konular ve çalışma ağacı güvenliği

1. Production `/` ve `/yonetici-giris` için son bilinen HTTP 500 kök nedeni private log seviyesinde kapanmamıştır.
2. Owner import başarısı bildirmiş olsa da production smoke/health başarılı kabul edilmemelidir.
3. Açığa çıkmış olabilecek production DB password, APP_KEY ve reCAPTCHA değerleri döndürülmelidir.
4. SQLite testleri MariaDB/MySQL DDL ve lock farklarını tam kanıtlamaz.
5. Tenant bypass noktaları yüksek dikkat ister.
6. Parti/parça migration tarihçesi ve duplicate timestamp prefix'leri yeniden düzenlenmemelidir.
7. Canonical schema fresh DB'nin birebir fiziksel kopyası değildir; accepted-difference manifest uygulanır.
8. Finansal/tarihsel tablolarda yeni `CASCADE` veya action değişikliği domain incelemesi olmadan yapılmaz.

Çalışma ağacında önceki fazlardan kalan çok sayıda modified/deleted/untracked dosya vardır. `git reset --hard`, `git checkout --` veya ilişkisiz dosya silme kullanılmamalıdır. `.gitignore` içinde `production-backups/`, `*.sql`, `.env` ve geçici production runner kuralları bulunur; backup GitHub'a gönderilmemelidir.

## 17. Kaynak dosya indeksi

### Mimari ve geliştirme

- `AGENTS.md`
- `README.md`
- `laravel-core/docs/architecture/admin-table-standard.md`
- `laravel-core/docs/architecture/admin-card-standard.md`
- `laravel-core/docs/muhasebe-gelistirme-on-promptu.md`
- `laravel-core/docs/muhasebe-gelistirme-entegrasyon-raporu-2026-07-13.md`

### Şema ve migration

- `database-health-audit.md`
- `database-clean-install-report.md`
- `database-clean-application-report.md`
- `production-backup-upgrade-report.md`
- `output/phase44/fk170-canonical-target.csv`
- `output/phase44/canonical-schema-target-final.md`
- `output/phase44/final-canonical-closure-audit.md`
- `output/phase44/fresh-action-mismatch-final-reconciliation.md`
- `output/phase45/fk-repair-plan.csv`
- `output/phase45/all-fk-batches-results.csv`
- `output/phase45/all-fk-batches-implementation-report.md`
- `output/phase45/full-corrective-schema-gate-report.md`

### Business E2E

- `output/phase46/final-phase4-closure-report.md`
- `output/phase46/final-phase4-accounting-reconciliation-report.md`
- `output/phase46/full-production-backup-rehearsal-report.md`
- `output/phase46/final-business-e2e-gate-report.md`

### Release / production

- `output/phase5/phase53c-final-packaging-report.md`
- `output/phase5/phase54-env-database-handoff.md`
- `output/phase5/phase54-production-cutover-report.md`
- `output/phase5/phase54-production-env-template-verified.txt`
- `output/phase5/final-production-deployment-procedure.md`
- `output/phase5/final-production-rollback-procedure.md`
- `output/phase5/PRODUCTION-ENV-CHECKLIST.md`

## 18. Yeni sohbet için hazır başlangıç metni

```text
Önce docs/project-continuity-source-of-truth.md ve AGENTS.md dosyalarını oku.
Bu repository Laravel 11 + Filament 3.3 çok firmalı Yalova SaaS uygulamasıdır.
Canonical schema hedefi 170 FK (CASCADE 25, RESTRICT 81, SET NULL 64) + teknik
servis cihaz identity index'idir. Accepted fresh index extras ve 3 legacy
production kolonu vardır; bunları otomatik blocker veya drop olarak yorumlama.
Muhasebe değişikliklerinde fatura-cari-finans-stok-iade tenant zincirini,
transaction/idempotency ve ters kayıt kurallarını koru. Önce mevcut kod,
migration, test ve ilgili raporu incele; kapsam dışı migration/schema/production
değişikliği yapma. Sonuçta değişen dosyaları ve doğrulama kanıtını bildir.
```

## 19. Belgenin güncellenmesi

Şu değişikliklerden sonra bu belge ve ilgili kaynak indeksi güncellenmelidir:

- canonical FK/index/column kararı,
- yeni migration veya migration history değişikliği,
- muhasebe fatura/ödeme/iade/stok davranışı,
- tenant/auth/yetki modeli,
- production release/import/rollback durumu,
- sidebar/layout/table/card standardı.

Güncelleme sırasında secret veya canlı verinin ham çıktısı eklenmemelidir.
