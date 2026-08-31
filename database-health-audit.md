# Database Health Audit — Yalova Yazılım SaaS

**İnceleme tarihi:** 2026-08-22  
**Kapsam:** `laravel-core` uygulaması ve repository içindeki statik dosyalar  
**Yöntem:** Yalnızca dosya/dizin, manifest, model, migration, seed, route, config ve test incelemesi. Migration, seeder, artisan komutu, test suite'i veya veritabanı bağlantısı çalıştırılmadı.

## Yönetici özeti

Repository; Laravel 11, PHP 8.2+, Filament 3.3, Livewire ve Vite kullanan, tek uygulama içinde çok sayıda iş modülünü birleştiren bir SaaS monolitidir. `database/migrations` altında **294 migration**, `app/Models` altında yaklaşık **189 model**, **94 Filament Resource**, **102 service**, **136 test dosyası** (14 Unit, 122 Feature) bulunuyor.

İzolasyon için hem eski `App\Traits\HasFirma` hem de yeni `TenantContextService` tabanlı global scope ailesi birlikte yaşıyor. Bu yaklaşımın olumlu yanı muhasebe, personel, teknik servis ve diğer modüllerde tenant testlerinin bulunmasıdır; olumsuz yanı `withoutGlobalScopes()`, doğrudan `DB::table()` ve console/test bypasslarının çok yaygın olmasıdır. Bu nedenle izolasyonun statik koddan tamamen güvenli olduğu doğrulanamaz.

Migration geçmişi yoğun biçimde evrilmiştir. Aynı gün/saat prefix'li migrationlar, son günlerde silinen ve yeniden eklenen parti/parça migrationları, rename/drop/backfill işlemleri ve migration içinde veri güncellemeleri vardır. Temiz MariaDB kurulumu **READY değil**; güvenli sınıflandırma **READY WITH RISKS** yerine mevcut kanıt seviyesiyle **NOT READY**'dir. Bunun nedeni migrationların kesin olarak bozuk olduğunun kanıtlanması değil, temiz MariaDB üzerinde henüz doğrulanmamış olmaları ve çalışma ağacının temiz/baseline durumda olmamasıdır.

## 1. Genel mimari

### Teknoloji yığını

| Alan | Bulgular |
|---|---|
| Laravel | `composer.json`: `laravel/framework ^11.0` |
| PHP | `^8.2` |
| Admin UI | `filament/filament 3.3` |
| Livewire | `config/livewire.php`, `app/Livewire`, Filament bileşenleri |
| Frontend | Vite 5, `laravel-vite-plugin`, Axios; `resources/js`, `resources/css` |
| Önemli Composer paketleri | Doctrine DBAL 4, Endroid QR Code 6, Laravel Tinker; PHPUnit 10.5, Faker, Pint, Sail, Mockery |
| NPM | Vite 5, Laravel Vite Plugin, Axios |
| DB sürücüleri | SQLite varsayılanı; MySQL ve MariaDB bağlantıları `config/database.php` içinde |
| Queue | `.env.example`: database queue; PHPUnit: `sync` |
| Cache | `.env.example`: database; PHPUnit: `array` |
| Session | `.env.example`: database; PHPUnit: `array` |
| Scheduler | `routes/console.php` içinde 9 periyodik komut |
| Event/listener | Klasik `app/Events` ve `app/Listeners` dizinleri yok; Livewire dispatch olayları ve model observer'ları var |
| Observer | 8 observer dosyası; özellikle fatura kalemi/stok/muhasebe yan etkileri önemli |
| Service katmanı | `app/Services`, `app/Muhasebe/Servisler`, `app/TeknikServis/Servisler`, `app/TeklifYonetimi/Servisler`, `app/Modules/*/Servisler` |
| Repository | Ayrı bir repository katmanı tespit edilmedi; Eloquent model + service + Filament kaynakları kullanılıyor |

Mimari, Filament panelinin modül cluster'ları üzerinden yönetildiği, domain servislerinin muhasebe/teknik servis/e-ticaret gibi alanlarda yoğunlaştığı bir modular-monolith görünümündedir. `public_html` içinde WordPress/theme varlıkları da bulunduğundan repository uygulama kodu ile web varlıklarını birlikte barındırıyor.

### Authentication ve kullanıcı ayrımı

- `app/Models/User.php`, `app/Models/Kullanici.php`, `app/Models/FirmaKullanici.php` ve `FirmaKullanici` ilişkileri birlikte kullanılıyor.
- Yönetici/süper yönetici ayrımı `super_admin_mi` ve `is_admin` alanları ile policy/helper katmanlarında yapılıyor.
- Firma paneli `TenantAuthController`, `FilamentTenantContextMiddleware`, `TenantContextService` ve session içindeki aktif firma bağlamı ile çalışıyor.
- Filament panel kaydı `app/Providers/Filament/AdminPanelProvider.php` içindedir.

## 2. Modül haritası

| Modül | Ana kod alanları / örnek modeller | Bağımlılık ve yan etkiler |
|---|---|---|
| SaaS / Firma yönetimi | `Firma`, `FirmaKullanici`, `FirmaModulu`, `FirmaAboneligi`, `FirmaAyari`, `Plan`, `Modul`, `Rol`, `Yetki`; `FirmaYonetimKaynagi`, `FirmaIciKullaniciKaynagi` | Tenant bağlamının kaynağıdır. Firma silme/pasifleştirme diğer tüm `firma_id` kayıtlarını etkileyebilir. |
| Muhasebe / Cari | `Cari`, `CariHareketi`, `CariGrubu`, banka/kasa/pos modelleri; `app/Filament/Clusters/Muhasebe` | Fatura, stok, finans, tahsilat, çek/senet ve e-ticaret ile bağlı. |
| Fatura / Finans | `Fatura`, `FaturaKalemi`, `FinansHareketi`, `FaturaFinansKapama`, kur farkı ve alacak planı modelleri | Cari, stok hareketi, ödeme, teknik servis ve e-ticaret entegrasyonlarının merkezidir. |
| Stok / Depo / Barkod | `StokKarti`, `StokHareketi`, `StokOlcusu`, `StokDepoBakiyesi`, `StokSeriNo`, transfer ve barkod modelleri | Fatura, sipariş, barkodlu satış, depo ve ölçülü stok zinciriyle bağlı. |
| Teknik Servis | `TeknikServisKaydi`, `TeknikServisKalem`, cihaz, durum, aksesuar, arıza, tahsilat, şablon ve muhasebe bağlantı modelleri | Cari, fatura, stok, alacak planı ve bildirimlerle bağlı. |
| Teklif Yönetimi | `Teklif`, `TeklifKalemi`, baskı/numara şablonları; `app/TeklifYonetimi` | Cari, stok, para birimi ve PDF/baskı akışıyla bağlı. |
| E-ticaret / Ürün / Ödeme | `Product`, `ProductCategory`, `Sepet`, `Siparis`, sipariş kalemleri, ödeme, kargo, kampanya, pazaryeri, mesaj modelleri | Web ürünleri, cari, fatura, stok, finans, ödeme sağlayıcıları ve cron komutlarıyla bağlı. |
| Personel Takip | personel, departman, görev, izin, vardiya, maaş, avans, belge modelleri | Firma/şube tenant sınırı ve finans/maliyet raporlarıyla bağlı. |
| Masraf / Proje | masraf, araç, bütçe, düzenli fatura, işletme projesi modelleri | Fatura, finans, cari, personel ve proje raporlarıyla bağlı. |
| Restoran | adisyon, masa/salon, menü, reçete, tahsilat, gün sonu modelleri | Stok, cari, finans, fatura ve yetki sistemiyle bağlı. |
| Sekreter | görev, not, randevu, hatırlatma modelleri | Firma tenant'ı ve scheduler ile bağlı. |
| Web / CMS | servis, proje, yazı, kategori, sayfa, menü, newsletter modelleri | Global/web içerik; bazıları firma tenant'ından bağımsız. |
| Sistem / Denetim | `DenetimKayidi`, `SistemOlayi`, ayarlar, yetki kaynakları | Tüm modüllerin güvenlik ve izlenebilirlik katmanı. |

Bir modül kapatıldığında genel mekanizma `FirmaModulu` durumu, Filament navigation/sidebar görünürlüğü ve sayfa erişim kurallarıdır. Veri katmanında modül kapanmasının tüm ilişkili kayıtları otomatik kapattığına dair tek, merkezi bir cascade mekanizması tespit edilmedi; iş kuralları çoğunlukla servis/policy seviyesinde.

## 3. Veritabanı ve migration envanteri

- Toplam migration: **294**.
- Tarih aralığı: `0001_01_01` Laravel çekirdeğinden `2026_08_22_180000` son migrationa kadar.
- İlk temel tablolar: `users`, `cache`, `jobs`, ardından CMS tabloları ve SaaS çekirdeği.
- SaaS çekirdeği: `firmalar`, roller/yetkiler, firma kullanıcıları, modüller, planlar, abonelikler, ayarlar ve denetim kayıtları.
- Muhasebe çekirdeği: cari, kasa/banka/pos, stok, fatura, fatura kalemi, cari/stok/finans hareketleri.
- Sonraki katmanlar: e-ticaret, teknik servis, alacak takibi, personel, restoran, çek/senet, masraf/proje, depo/seri/ölçü ve sekreter.

### Riskli migration sınıfları

Statik taramada çok sayıda migrationda `dropColumn`, `dropForeign`, `renameColumn`, `change`, `DB::table`, veri backfill'i veya seed benzeri işlem bulundu. `dropIfExists` sayısının yüksek olması tek başına tehlike değildir; çoğu migrationın `down()` tarafında kullanılıyor olabilir. Ancak migration dosyalarının ileri ve geri yönleri ayrıca çalıştırılmadı.

| Seviye | Bulgular |
|---|---|
| CRITICAL | `2026_08_22_120000_remove_parti_parca_sistemi.php` ve `2026_08_22_150000_remove_remaining_parti_parca_columns.php`: birden fazla satış/sipariş/fatura tablosunda kolon silme; `2026_08_22_180000_cleanup_parti_parca_legacy_metadata.php`: yetki/ayar metadata temizliği. |
| CRITICAL | Çalışma ağacında parti/parça ile ilgili eski migration dosyaları silinmiş, yeni remove/restore migrationları eklenmiş görünüyor. Temiz kurulum ile production `migrations` geçmişi arasında ayrışma riski var. |
| HIGH | `2026_04_01_220000_add_marka_adi...` + `2026_04_01_221000_rename_marka_adi_to_marka_uretici...`: rename zinciri ve uygulamada legacy `marka_adi` rapor alias'larının kalması. |
| HIGH | `2026_08_16`–`2026_08_22` ölçü/seri/depo/parti geçişleri: tablo yapısı, foreign key ve uygulama modelleri birlikte evriliyor; `restore_*`, `remove_*`, `disable_*` adları yüksek drift sinyali. |
| HIGH | `2026_03_24_140300_firma_yabanci_anahtarlarini_restrict_yap.php`: mevcut orphan veriler varsa upgrade sırasında foreign key ekleme başarısız olabilir. |
| HIGH | `2026_04_02_250000_backfill_stok_barkodlari_from_stok_kartlari.php`, `2026_08_15_131000_sync_kayitli_cihaz_garanti_bilgileri.php` ve benzerleri: mevcut veriye dokunan backfill/sync işlemleri. |
| MEDIUM | Çok sayıda `change()`/nullable/default değişikliği: MariaDB sürümü, mevcut veri ve index/foreign key durumu ile uyumluluk doğrulanmamış. |
| MEDIUM | Migration içinde seed/metadata/yetki ekleyen işlemler var; migration idempotency ve production `migrations` tablosu ile eşleşme kontrol edilmedi. |

Migration filename prefix'lerinde aynı timestamp'ler vardır; örneğin `2026_03_26_120000`, `2026_05_30_020000`, `2026_05_31_020000`, `2026_08_22_120000`, `2026_08_22_170000` birden fazla dosyada kullanılıyor. Laravel dosya sıralamasını filename ile belirlediği için saniye/isim sırasının foreign key bağımlılıklarıyla uyumu ayrıca doğrulanmalıdır.

## 4. Kavram/migration tarihi karmaşası

- Parti/parça sistemi 2026-08-14/18/20 civarında eklenmiş migration/model katmanından sonra 2026-08-22'de kaldırılmış görünüyor. Çalışma ağacında `StokParcasi`, `StokHareketiParcasi`, `StokParcaIslemLogu` modelleri silinmiş; buna karşılık yeni remove/cleanup migrationları eklenmiş. Bu durum temiz kurulum, mevcut production ve rollback senaryolarının aynı olmayabileceğini gösterir.
- Seri takip türü, parti/parça kaldırma zincirinden sonra `2026_08_22_130000_restore_serial_tracking_type.php` ile tekrar ekleniyor. Bu, ürün kavramlarının ardışık sadeleştirme/geri alma yaşadığını gösteren güçlü bir drift sinyalidir.
- `marka_adi` → `marka_uretici` rename zinciri migrationda açıkça var. Uygulamada `marka_uretici` kolonuna göre denetim yapılırken bazı teknik servis raporlarında `marka_adi` SQL alias'ı kullanılıyor; bunun alias mı gerçek kolon kullanımı mı olduğu bağlama göre ayrıştırılmalı.
- `takip_turu` hem ölçü dağılımı hem fatura/ölçü akışında geçiyor; `olculu_takip_turu` ise model/enum seviyesinde geçiyor. Bunların aynı kavramın farklı katman alanları mı yoksa ayrı kavramlar mı olduğu statik koddan kesinleştirilemedi: **UNKNOWN — veri sözlüğü gerekir**.
- `deleted_at` kullanan 72 model/alan referansı tespit edildi. Soft delete kullanan her modelin karşılık gelen migration kolonunun mevcut olduğu bu auditte otomatik doğrulanmadı.

## 5. Model ↔ database tutarlılığı

189 modelin tüm `$fillable/$casts/relations` alanlarının migrationla birebir şema karşılaştırması henüz yapılmadı; bu nedenle burada kesin hata listesi değil, doğrulama bulguları verilmektedir.

Olumlu bulgular:

- Modellerde tenant trait/scope kullanımı yaygın.
- Finansal alanlarda decimal hassasiyeti, para birimi snapshot'ları ve ölçü modelleri için özel servisler mevcut.
- `Firma` ve `User` gibi çekirdek modellerde `SoftDeletes`, cast ve ilişki tanımları bulunuyor.

Riskli bulgular:

- Model ve migrationlar aynı çalışma ağacında eşzamanlı değişmiş; özellikle fatura kalemi, stok kartı/hareketi, ölçü dağılımı, seri ve teknik servis kalemi alanları baseline dışı görünüyor.
- `User` içinde kolon yokken davranışı bozmamaya çalışan schema-check/soft-delete uyumluluk kodları bulunuyor. Bu, farklı DB şemalarının geçmişte aynı kodla desteklendiğine işaret eder.
- `Firma::created` içinde para birimi ve depo default verisi oluşturan model yan etkisi var. Temiz kurulumda migration → seed sırası ve uygulama boot davranışı birbirinden ayrıştırılmalıdır.
- Bazı raw `DB::table()` sorguları model global scope'larını doğal olarak kullanmaz; tenant filtresi her sorguda ayrıca kanıtlanmalıdır.

## 6. Foreign key ve veri bütünlüğü haritası

Temel ilişkiler:

```text
firmalar
├─ firma_kullanicilari → users
├─ firma_modulleri → moduller
├─ firma_abonelikleri → planlar
├─ firma_ayarlari / denetim_kayitlari
└─ tenant modülleri (cari, stok, fatura, finans, servis, personel, restoran...)

cari → cari_hareketleri / fatura / teknik_servis / alacak / ödeme
stok_kartlari → stok_hareketleri / barkod / seri / depo bakiyesi / fatura kalemleri
faturalar → fatura_kalemleri → stok/cari/ölçü/seri/depo bağlantıları
faturalar → fatura_finans_kapatmalari → finans hareketleri
teknik_servis_kayitlari → kalemler / cihaz / durum geçmişi / tahsilat / muhasebe bağlantısı
siparisler → sipariş kalemleri / ödeme / mesaj / kargo / kampanya / stok-finans entegrasyonu
```

Orphan riski özellikle şu alanlarda yüksektir:

- `firma_id` kaldırılmadan veya aynı transaction içinde doğrulanmadan child kayıt oluşturulan servis/raw sorgular.
- `withoutGlobalScopes()` sonrası yalnız primary key ile seçim yapılan fatura, stok ve tanım ilişkileri.
- Firma foreign key'lerinin `RESTRICT`e çevrildiği migration öncesi mevcut production orphan kayıtları.
- Parti/parça ve ölçü/seri geçişlerinde eski child tablo/kolon kayıtlarının yeni modele taşınamaması.

## 7. Firma / tenant izolasyonu

İzolasyonun ana mekanizmaları:

- `app/Services/TenantContextService.php` aktif firma bağlamı.
- `app/Http/Middleware/FilamentTenantContextMiddleware.php` panel request bağlamı.
- `app/Models/Scopes/FirmaIdTenantScope.php`, `TanimFirmaTenantScope.php`, `TeknikServisTanimFirmaScope.php`.
- `app/Models/Concerns/HasFirmaTenantScope.php` ve `app/Traits/HasFirma.php`.
- Policy tabanında `BasePolicy`, firma kullanıcı policy'leri ve modül yetki yardımcıları.

Değerlendirme:

- Muhasebe/personel/teknik servis için tenant testleri bulunuyor; örnekler `tests/Feature/Muhasebe/FinansHareketiTenantScopeTest.php`, `tests/Feature/PersonelTakip/PersonelTakipTenantScopeTest.php`, `tests/Feature/TeklifYonetimi/TeklifYonetimiHardeningTest.php`.
- Global scope bypassları bilinçli yardımcılarla yapılmış olsa da uygulama ve Filament katmanında çok yaygın. Bu noktalar için firma A/firma B negatif erişim matrisi olmadan kesin güvence verilemez.
- `HasFirma` console ortamında scope/creating davranışını bypass ediyor; `FirmaIdTenantScope` ise unit test dışında console'da bypass ediyor. Job/command/scheduler'ların firma filtresini kendisinin taşıması gerekiyor.
- Doğrudan `DB::table()` sorguları Eloquent global scope kullanmaz. `BankaHesabiKaynagi`, `KasaHesabiKaynagi`, `PosHesabiKaynagi`, stok/fatura/masraf/proje rapor sayfalarında kayıt existence ve rapor sorguları ayrıca `firma_id` ile kontrol edilmelidir.
- `Firma` ve sistem yöneticisi davranışı bilinçli olarak tüm firmaları görebiliyor. Bu gerçek açık değildir; ancak admin rolünün yanlış atanması halinde etkisi kritik olur.

Kesin tenant açığı statik olarak kanıtlanmadı. Ancak **şüpheli/riskli alan sayısı yüksek** olduğundan Tenant Isolation Risk: HIGH değerlendirilmiştir.

## 8. Seeder ve default data

`DatabaseSeeder` yalnızca temel CMS seed'lerini (`ServiceSeeder`, `ProjectSeeder`, `PageSeeder`, `MenuSeeder`) çağırıyor; local/testing ortamında örnek `test@example.com` kullanıcısı oluşturuyor. SaaS çekirdeği bilinçli olarak otomatik çağrılmıyor.

Boş DB sonrası tipik gereksinimler:

1. `SaasDatabaseSeeder`: roller, modüller, yetkiler, rol/yetki matrisleri, planlar ve plan/modül matrisi.
2. `VarsayilanSuperAdminSeeder`: yönetici erişimi.
3. `MuhasebeOlcuBirimleriSeeder`: ölçü birimleri ve yeni stok/fatura akışları.
4. CMS için `DatabaseSeeder` veya ayrı `ServiceSeeder`, `ProjectSeeder`, `PageSeeder`, `MenuSeeder`.
5. Demo/QA istenirse yalnız local/testing için `SaasDevSampleSeeder` veya `QaFullSaaSTestSeeder`; production'da çalıştırılmamalı.
6. Restoran/personel/teknik servis tanım ve yetki seed'leri ilgili sınıflardan doğrulanmalı.

`SaasDatabaseSeeder` ve diğer seed'ler çalıştırılmadı; idempotency, boş MariaDB davranışı ve migration sonrası zorunlu sıra **UNKNOWN**. `Firma::created` model yan etkileri default TRY/depo oluşturabildiği için ayrıca gözlemlenmelidir.

## 9. Test altyapısı

- Unit: **14 dosya**.
- Feature: **122 dosya**.
- Toplam test dosyası: **136**.
- `phpunit.xml` zorunlu olarak SQLite `:memory:` kullanıyor; `.env.testing.example` de SQLite'ı varsayılan gösteriyor.
- MySQL/MariaDB entegrasyon test suite'i için aktif, güvenli ve doğrulanmış bir profil tespit edilmedi. Dosyalarda alternatif MySQL örneği ve bazı test içi SQLite override'ları var.
- DB kullanan testlerin önemli kısmı migration/trait/model oluşturuyor; ancak bu audit sırasında hiçbiri çalıştırılmadı.
- Tenant isolation, muhasebe, stok, teknik servis, restoran, personel, auth ve e-ticaret için test dosyaları mevcut.
- `tests/Feature/Restoran/RestoranFilamentUiTest.php`, `tests/Feature/Muhasebe/*`, `tests/Feature/PersonelTakip/*`, `tests/Feature/TeknikServis/*` kapsamı geniştir.

Ana güvenilirlik açığı SQLite–MariaDB farkıdır: foreign key/DDL, enum, decimal, index uzunlukları, collation, JSON, nullable/default davranışı ve ALTER lock etkileri SQLite testlerinde gizlenebilir. `phpunit.xml` içindeki `force="true"` DB ayarları, ortamdan MariaDB testine geçişi ayrıca tasarlamayı gerektirir.

## 10. Clean install değerlendirmesi

**Sonuç: NOT READY**

Gerekçeler:

- 294 migrationlık uzun ve veri dönüşümlü bir zincir temiz MariaDB üzerinde doğrulanmadı.
- Çalışma ağacında çok sayıda uygulama, model ve migration değişikliği mevcut; baseline/release commit'i belirsiz.
- Parti/parça migrationlarının silinmesi ve aynı gün yeni remove/restore migrationlarının eklenmesi fresh/upgrade ayrışması yaratabilir.
- Aynı timestamp prefix'li migrationlar foreign key sırası için kontrol edilmeli.
- Seeder zorunlulukları otomatik ve tek bir `DatabaseSeeder` akışında toplanmamış.
- PHPUnit SQLite'a zorunlu bağlı; MariaDB uyumluluğu kanıtlanmamış.

Bu sonuç “kesinlikle migrate başarısız olur” anlamına gelmez; güvenli biçimde READY denmesi için gerekli kontrollü kanıt henüz yoktur.

## 11. Production backup upgrade hazırlığı

Önerilen senaryo production backup → izole local MariaDB → mevcut kod → kontrollü `artisan migrate` → MariaDB testleri şeklinde olmalıdır. En önemli ön koşul production DB içindeki `migrations` tablosunun repository migration dosyalarıyla karşılaştırılmasıdır.

Riskler:

- Production'da olup repository'de bulunmayan migration: şema koddan ileri olabilir.
- Repository'de olup production `migrations` tablosunda olmayan migration: `migrate` mevcut veriye ALTER/drop/backfill uygulayabilir.
- Aynı migration adı farklı içerikle yeniden kullanılmışsa Laravel yalnız adı görür; checksum tutulmadığı için sessiz drift oluşabilir.
- `RESTRICT` foreign key değişiklikleri orphan veride durabilir.
- Parti/parça kolonlarının silinmesi geri döndürülemez veri kaybı yaratabilir.
- Backfill/seed migrationları yanlış production snapshot üzerinde finansal veya metadata yan etkisi doğurabilir.
- Kod rollback'i destructive migration sonrası eski kodun beklediği kolonları geri getirmez.

Production credential okunmadı/kullanılmadı; `.env` içinden DB bağlantısı kontrol edilmedi ve hiçbir DB bağlantısı açılmadı.

## 12. Schema diff sistemi önerisi

En güvenilir yaklaşım: iki izole MariaDB instance'ında aynı MariaDB major/minor, charset/collation ve SQL mode kullanarak şemayı SQL metadata'dan çıkarmak; veri içermeyen canonical dump üretmek; ardından yapılandırılmış diff çalıştırmaktır.

Karşılaştırma kaynakları:

- `information_schema.tables`, `columns`, `statistics`, `table_constraints`, `key_column_usage`, `referential_constraints`.
- `SHOW CREATE TABLE` ile enum tanımları, generated columns, engine, charset/collation ve constraint ayrıntıları.
- `information_schema.views`, `SHOW CREATE TRIGGER` ile view/trigger varsa.
- Primary/unique/normal index sırası, prefix length, nullable/default, decimal precision/scale, auto increment ve foreign key action'ları.

Önce `DB-A = migrate:fresh + gerekli seed`, `DB-B = production backup + current migrations` izole ortamlarda hazırlanmalı; sonra canonical JSON şema çıktısı ile diff raporu üretilmelidir. Laravel migration metinlerini karşılaştırmak tek başına yeterli değildir. Implementasyon bu fazda yapılmadı.

## 13. Deployment yapısı

Repository'de `laravel-core/deploy.php`, `package.json`, `vite.config.js` ve `composer` script'leri bulunuyor. `deploy.php` incelenmiş olsa da bu raporda script içeriği operasyonel bir deployment prosedürü olarak doğrulanmadı. GitHub Actions, Docker/compose, supervisor, nginx veya kanıtlanmış CI/CD pipeline'ı tespit edilmedi. Production'ın cPanel/Apache benzeri bir ortamda çalışabileceğine dair docs vardır; kesin mevcut production mekanizması: **UNKNOWN**.

Scheduler tanımları `routes/console.php` içindedir ve cPanel cron fallback endpoint/ayarları e-ticaret tarafında bulunur. Scheduler'ın production'da gerçekten çalıştığı repository'den kanıtlanamaz.

## 14. Backup / rollback riski

Deployment öncesinde en azından şu varlıklar birlikte yedeklenmelidir:

- Tam MariaDB dump + `migrations` tablosu.
- `.env` ve deployment secret'larının güvenli, ayrı erişimli kopyası.
- `storage/app`, `storage/app/public`, `public` altındaki kullanıcı yüklemeleri ve symlink hedefleri.
- Release application files ve `composer.lock`/`package-lock.json`.

Repository `Storage::disk('public')`, avatar, logo, belge/görsel ve PDF akışlarını kullandığı için yalnız DB backup'ı dosya kaybına karşı yeterli değildir. `storage:link` ve public upload hedefinin production'da tam yolu **UNKNOWN**.

Rollback için yalnız kodu geri almak yeterli olmayan migrationlar: kolon/tablo drop, rename, tip/nullable/default değişimi, backfill/update, foreign key action değişimi ve parti/parça kaldırma migrationlarıdır. Destructive migration sonrası ters migrationın tüm veriyi geri getireceği varsayılamaz.

## 15. En riskli noktalar

### RISK-01

**Severity:** CRITICAL  
**Area:** Repository baseline / migration geçmişi  
**Files:** `git status` çıktısındaki çok sayıdaki mevcut değişiklik; `database/migrations/2026_08_*.php`  
**Tables:** Ölçü, seri, depo, fatura kalemi, stok hareketi, finans  
**Description:** Çalışma ağacı temiz değil; uygulama, model, test ve migration değişiklikleri aynı anda mevcut.  
**Why dangerous:** Audit sonucu hangi commit'in deploy edilebilir baseline olduğu belirsiz.  
**Recommended next step:** Değişiklik yapmadan release/baseline commit'i ve migration dosya listesi dondurulmalı.

### RISK-02

**Severity:** CRITICAL  
**Area:** Destructive schema changes  
**Files:** `2026_08_22_120000_remove_parti_parca_sistemi.php`, `2026_08_22_150000_remove_remaining_parti_parca_columns.php`, `2026_08_22_180000_cleanup_parti_parca_legacy_metadata.php`  
**Tables:** Sipariş/fatura/barkodlu satış kalemleri ve yetki/ayar metadata  
**Description:** Parti/parça alanları ve metadata temizleniyor.  
**Why dangerous:** Production verisi ve eski kod geri döndürülemez biçimde uyumsuz olabilir.  
**Recommended next step:** Production backup kopyasında kolon/veri envanteri ve migration-history diff'i yapılmalı.

### RISK-03

**Severity:** HIGH  
**Area:** Migration ordering  
**Files:** Aynı timestamp'li `2026_03_26_120000`, `2026_05_30_020000`, `2026_05_31_020000`, `2026_08_22_120000`, `2026_08_22_170000` migrationları  
**Description:** Aynı prefix'li dosyaların lexicographic sırası foreign key/column bağımlılıklarını belirliyor.  
**Why dangerous:** Fresh install veya rollback sıra kaynaklı başarısız olabilir.  
**Recommended next step:** Dosya sırası ve her foreign key hedefi statik dependency graph ile doğrulanmalı.

### RISK-04

**Severity:** HIGH  
**Area:** Tenant isolation  
**Files:** `app/Models/Scopes/*`, `app/Models/Concerns/HasFirmaTenantScope.php`, `app/Traits/HasFirma.php`, raw `DB::table()` kullanan Filament sayfaları  
**Description:** İki tenant yaklaşımı ve yaygın `withoutGlobalScopes()` birlikte kullanılıyor.  
**Why dangerous:** Bir sorguda firma filtresi unutulursa cross-tenant okuma/yazma oluşabilir.  
**Recommended next step:** Firma A/B negatif erişim test matrisi raw sorgu ve console komutlarını kapsayacak şekilde çalıştırılmalı.

### RISK-05

**Severity:** HIGH  
**Area:** SQLite test gap  
**Files:** `phpunit.xml`, `.env.testing.example`  
**Description:** Suite zorunlu SQLite memory DB ile çalışıyor; MariaDB doğrulaması yok.  
**Why dangerous:** DDL, FK, decimal, enum, index ve collation farkları gizlenebilir.  
**Recommended next step:** Production olmayan izole MariaDB test profili kurulup schema/migration suite'i orada koşturulmalı.

### RISK-06

**Severity:** HIGH  
**Area:** Foreign key hardening  
**Files:** `2026_03_21_140300_firma_yabanci_anahtarlarini_restrict_yap.php` ve `dropForeign` kullanan migrationlar  
**Description:** Foreign key action'ları sıkılaştırılıyor.  
**Why dangerous:** Mevcut orphan kayıtlar upgrade'i durdurabilir; yanlış cascade/restrict iş kuralı kaybı yaratabilir.  
**Recommended next step:** Backup kopyasında orphan ve constraint preflight sorguları çalıştırılmalı.

### RISK-07

**Severity:** HIGH  
**Area:** Model/migration drift  
**Files:** `StokKarti`, `StokHareketi`, `FaturaKalemi`, `TeknikServisKalem`, ölçü/seri modelleri ve 2026-08 migrationları  
**Description:** Model, migration ve uygulama değişiklikleri aynı feature zincirinde ilerliyor.  
**Why dangerous:** Fillable/cast/relation ile gerçek kolonlar ayrışabilir.  
**Recommended next step:** Model kolon kullanım matrisi ve MariaDB `SHOW CREATE TABLE` diff'i çıkarılmalı.

### RISK-08

**Severity:** HIGH  
**Area:** Data migration/backfill  
**Files:** `2026_04_02_250000_backfill_stok_barkodlari_from_stok_kartlari.php`, `2026_08_15_131000_sync_kayitli_cihaz_garanti_bilgileri.php` ve benzeri `DB::table` migrationları  
**Description:** Migrationlar mevcut veriyi dönüştürüyor.  
**Why dangerous:** Büyük tabloda lock, duplicate ve yanlış eşleşme olabilir.  
**Recommended next step:** Her backfill için satır sayısı, idempotency, transaction/batch ve rollback kanıtı hazırlanmalı.

### RISK-09

**Severity:** HIGH  
**Area:** Seeder/default data dependency  
**Files:** `DatabaseSeeder.php`, `SaasDatabaseSeeder.php`, `VarsayilanSuperAdminSeeder.php`, `MuhasebeOlcuBirimleriSeeder.php`  
**Description:** SaaS çekirdek seed'i otomatik `DatabaseSeeder` akışında değil.  
**Why dangerous:** Fresh DB migration sonrası uygulama erişim/yetki/tanım olmadan açılabilir.  
**Recommended next step:** Temiz DB kurulum sözleşmesi ve seed sırası yazılı testle doğrulanmalı.

### RISK-10

**Severity:** MEDIUM  
**Area:** Concept rename drift  
**Files:** `2026_04_01_220000_add_marka_adi...`, `2026_04_01_221000_rename_marka_adi_to_marka_uretici...`, teknik servis raporları  
**Description:** `marka_adi`/`marka_uretici` eski-yeni adları birlikte görülüyor.  
**Why dangerous:** Bazı ekranlar eski kolon varsayımıyla çalışmayabilir.  
**Recommended next step:** Kolon sözlüğü ve uygulama referanslarının migration sonrası gerçek şemaya göre taranması.

### RISK-11

**Severity:** MEDIUM  
**Area:** Scheduler/command tenant context  
**Files:** `routes/console.php`, `app/Console/Commands/*`  
**Description:** Periyodik komutlar firma filtresini seçenek veya servis mantığıyla taşıyor; console global scope bypass ediyor.  
**Why dangerous:** Tüm firmalara işlem uygulanması veya hiçbir firmaya uygulanmaması mümkün.  
**Recommended next step:** Her scheduled command için firma kapsamı, dry-run ve tenant isolation testi eklenmeli.

### RISK-12

**Severity:** MEDIUM  
**Area:** Backup/release evidence  
**Files:** `deploy.php`, `composer.json`, `package.json`, storage/public upload akışları  
**Description:** Repository'de kesin production deployment ve restore prosedürü kanıtı yok.  
**Why dangerous:** Kod rollback'i, storage ve schema rollback'iyle koordineli olmayabilir.  
**Recommended next step:** Önce izole local runbook ve backup doğrulama prosedürü hazırlanmalı.

## 16. Sonraki faz planı

| Faz | Amaç / ön koşul / işlem | Dosyalar ve testler | PASS / FAIL / production riski |
|---|---|---|---|
| 1 — Audit | Baseline commit, migration listesi, DB motoru ve seed sözleşmesini dondur. | Rapor, migration manifest, model-kolon matrisi. | PASS: hash ve envanter sabit. FAIL: değişiklik sahibi belirlenir. Prod riski: yok. |
| 2 — Local Clean DB | Aynı MariaDB sürümünde izole DB. | Docker/yerel MariaDB kurulumu; production credential yok. | PASS: boş bağlantı doğrulanır. FAIL: sürüm/charset düzeltilir. Risk: local veri silme yalnız yeni DB'de. |
| 3 — `migrate:fresh` doğrulaması | Migration zincirini fresh çalıştır. | Yalnız DB-A; migration log'u. | PASS: 294 migration tamam. FAIL: ilk bozan dosya izole edilir. Prod riski: yok. |
| 4 — Seeder | Minimum ve demo seed ayrımını doğrula. | `SaasDatabaseSeeder`, `DatabaseSeeder`, tanım seed'leri. | PASS: login ve temel modüller çalışır. FAIL: zorunlu seed listesi ayrılır. Risk: seed yalnız local. |
| 5 — Automated clean-install tests | SQLite + MariaDB farkını ölç. | PHPUnit, migration/schema ve tenant testleri. | PASS: iki motor kabul edilir. FAIL: MariaDB uyumluluk düzeltme planı. Risk: test DB izole. |
| 6 — Production backup restore | Backup'ın local kopyasını doğrula. | Restore log, checksum, credentials redaction. | PASS: backup açılır ve production dışıdır. FAIL: backup/encoding sorunu çözülür. Risk: gerçek veri gizliliği. |
| 7 — Production migration upgrade | DB-B üzerinde pending migration preflight ve migrate. | `migrations` diff, orphan/constraint sorguları. | PASS: upgrade + smoke test. FAIL: migration durdurulur, backup korunur. Risk: yüksek; canlıda yapılmaz. |
| 8 — Schema diff | DB-A/DB-B şema farkı. | Information schema + SHOW CREATE canonical diff. | PASS: yalnız beklenen farklar. FAIL: drift sınıflandırılır. Risk: yok, izole DB. |
| 9 — Data integrity tests | Orphan, duplicate, tenant, finans/stok mutabakatı. | Reconciliation komutları dry-run ve SQL kontrolleri. | PASS: ihlal yok. FAIL: veri onarım planı. Risk: yazma işlemi onaysız yapılmaz. |
| 10 — Full application tests | DB-B üzerinde modül testleri. | Muhasebe, stok, servis, restoran, personel, e-ticaret. | PASS: kritik suite temiz. FAIL: release bloklanır. Risk: test verisi izole. |
| 11 — Database Baseline V1 | Onaylı schema snapshot ve migration manifest. | Canonical schema JSON/SQL, checksum. | PASS: tekrar üretilebilir. FAIL: baseline yeniden incelenir. Risk: yok. |
| 12 — Release + rollback | Kod/schema/storage rollback runbook'u. | Release artifact, backup, reverse plan. | PASS: tatbikat başarılı. FAIL: deploy yok. Risk: yüksek ama kontrollü. |
| 13 — Production deployment | Onaylı artifact'i canlıya al. | CI/deploy script ve bakım penceresi. | PASS: migration + health check. FAIL: runbook rollback. Risk: CRITICAL. |
| 14 — Post-deployment verification | Uygulama, scheduler, tenant ve finans smoke testleri. | Health endpoint, log, scheduled command gözlemi. | PASS: kritik akışlar çalışır. FAIL: trafik/işlem durdurma prosedürü. Risk: yüksek. |

## 17. Final değerlendirme

```text
PROJECT DATABASE HEALTH

Clean Install Readiness: NOT READY
Production Upgrade Readiness: NOT READY
Migration Confidence: LOW
Schema Drift Risk: CRITICAL
Data Integrity Risk: HIGH
Tenant Isolation Risk: HIGH
Test Coverage Confidence: MEDIUM (geniş test dosyası kapsamı var; varsayılan SQLite nedeniyle MariaDB güveni düşük)
Rollback Readiness: LOW

OVERALL RISK: CRITICAL
```

## RECOMMENDED NEXT ACTION

Hiçbir migration veya seeder çalıştırmadan, mevcut çalışma ağacını bir release/baseline commit'i olarak dondurun ve yalnızca migration dosyaları ile production `migrations` tablosunu karşılaştıran preflight manifestini hazırlayın.

### Notlar

- Bu rapor oluşturulurken uygulama kodu, migration, seeder veya test dosyası değiştirilmedi.
- Test suite'i çalıştırılmadı; SQLite test DB'sinin production olmadığı ayrıca doğrulanmadığı için çalıştırmama kararı korundu.
- `git status` üzerinde görülen mevcut kullanıcı değişiklikleri bu audit tarafından oluşturulmamıştır ve korunmuştur.
- DB bağlantısı açılmadı; production credential okunmadı veya kullanılmadı.
