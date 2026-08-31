# CLEAN APPLICATION / SEED / TEST VERIFICATION

Tarih: 2026-08-22  
Kapsam: yalnızca local MariaDB QA veritabanları; production bağlantısı/restore yapılmadı.

## Seeder Analysis

| Seeder | Sınıf | Bulgı |
|---|---|---|
| `SaasRolesSeeder` | SYSTEM REQUIRED | Roller için `updateOrCreate`; idempotent. |
| `SaasModulesSeeder` | SYSTEM REQUIRED | SaaS modülleri için `updateOrCreate`; idempotent. |
| `SaasPermissionsSeeder` | SYSTEM REQUIRED | Yetkileri `updateOrCreate` ile hazırlar; idempotent. |
| `SaasRolePermissionMatrixSeeder` | SYSTEM REQUIRED | Rol-yetki matrisi; idempotent. |
| `SaasPlansSeeder` | SYSTEM REQUIRED | Planları hazırlar; idempotent. |
| `SaasPlanModuleMatrixSeeder` | SYSTEM REQUIRED | Plan-modül ilişkilerini hazırlar; idempotent. |
| `MuhasebeOlcuBirimleriSeeder` | SYSTEM REQUIRED | AD/MTR/MTK/MTQ/KGM sistem birimlerini hazırlar. Legacy alias senaryosunda ayrıca hata vardır; bkz. Remaining Risks. |
| `SaasDevSampleSeeder` | DEMO / QA ONLY | Demo firma ve üç demo kullanıcı üretir; minimum zincirden çıkarıldı. |
| `VarsayilanSuperAdminSeeder` | PRODUCTION-SPECIFIC / UNSAFE UNKNOWN | Sabit `admin@yalovakamera.local` ve `123456` parola kullanır; minimum zincire alınmadı. Üretimde otomatik çalıştırılmamalı. |
| `DatabaseSeeder` | DEMO / LOCAL ONLY | CMS servis/proje/sayfa/menü verileri ve local test kullanıcısı üretir; production minimum zinciri değildir. |

`SaasDatabaseSeeder` içindeki demo çağrısı kaldırıldı. Demo seed artık yalnızca açıkça `SaasDevSampleSeeder` çağrılırsa çalışır.

## Minimum Required Seed Chain

Kullanılan zincir:

```text
SaasRolesSeeder
SaasModulesSeeder
SaasPermissionsSeeder
SaasRolePermissionMatrixSeeder
SaasPlansSeeder
SaasPlanModuleMatrixSeeder
MuhasebeOlcuBirimleriSeeder
```

## Phase 3.2 — Final Test Stabilization

### FIX-APP-06 — Isolated MariaDB test infrastructure

Root cause: the original XAMPP MariaDB instance was not serving TCP connections. A controlled foreground start showed the previous orphan transaction `843081` was recovered and rolled back, then MariaDB aborted with `Can't open and lock privilege tables: Incorrect file format 'db'`. The affected local development datadir contains a damaged `mysql.db` system table; this is separate from the PHPUnit metadata lock itself.

Files: `scripts/run-mariadb-tests.ps1`, `laravel-core/phpunit.mariadb.xml`.

Change: created a separate local MariaDB 10.4.32 instance on `127.0.0.1:3307` with datadir `C:\xampp\mysql\data-phase32-test-20260822`. The harness rejects non-loopback hosts and database names outside `yalovayazilimsaas_test_phase32_*`; it drops/recreates only that exact test database, runs migrations, required seed twice, boot, PHPUnit and a post-test transaction check.

Why correct: the original development datadir was not modified, no production host/credential was used, and the isolated instance had no pending metadata lock or open transaction after the interrupted run (`innodb_trx=0`).

SQLite verification: not applicable.

MariaDB verification: isolated empty DB reached 294 migrations, double seed and application boot successfully. A full 800-test run was started but stopped after 63/800 because it was an unbounded all-suite run; the isolated server remained lock-free after stop.

Production implication: none; production DB/backup was not accessed.

### FIX-APP-07 — Test lifecycle and public-route policy isolation

Root cause: `RefreshDatabase` reset IDs while scoped/cache-backed module decisions persisted between tests; additionally, `OnePagePublicSiteMiddleware` intentionally redirects unpublished GET pages to home while several tests expected the old public product/payment pages.

Files: `laravel-core/tests/TestCase.php` and unpublished-web tests under `laravel-core/tests/Feature/Urun`.

Change: test teardown forgets scoped instances and flushes the array test cache. Unpublished controller/provider tests explicitly bypass only the temporary public-site gate; production routes, auth and tenant middleware remain unchanged.

SQLite verification: public-route cluster passed; selected public/Livewire run reached 25 tests / 60 assertions with one remaining measured-invoice expectation failure. Payment callbacks still expose a real missing-finance side effect and are not marked PASS.

MariaDB verification: isolated server remained free of orphan transactions during the bounded test attempt.

Production implication: none; changes are test-only.

### FIX-APP-08 — Measured invoice fixture contract

Root cause: measured-stock invoice fixtures omitted the now-required line `depo_id` context. The fixture also did not create a measurement card for the “no distribution” case.

Files: `laravel-core/tests/Feature/Muhasebe/FaturaOnayliOlusturmaAkisiTest.php`.

Change: added a test depot, line-level depot IDs and an empty-balance measurement card. No production validation rule was relaxed.

Why correct: the fixture now reflects the current measured-stock domain contract; the remaining no-distribution Livewire mismatch is kept visible rather than weakening validation.

SQLite verification: 24/25 tests in the selected public/Livewire cluster pass; one measured-invoice expectation remains for separate form lifecycle investigation.

MariaDB verification: not completed because the bounded critical suite was not rerun to completion.

Production implication: none; test fixture only.

### Cari legacy placeholder

`laravel-core/app/Models/Cari.php` has no active `App\\Models\\Cari` imports or type-hint references in `app`, `tests` or `database`. Active cari behavior uses `App\\Models\\Muhasebe\\Cari` (144 repository references found). Status: **LEGACY UNUSED / SAFE TO KEEP**; deletion was not performed.

## Phase 3.2 Final Result

```text
PHASE 3.2 RESULT

MariaDB test infrastructure: PARTIAL — isolated instance PASS; original XAMPP datadir has mysql.db format failure
Orphan metadata lock resolved: PASS in isolated test instance; original instance recovery rollback evidenced
294 migrations: PASS — isolated Phase 3.2 DB
Required seed: PASS — isolated DB
Seed idempotency: PASS — isolated DB
Application boot: PASS — isolated DB
AD/ADET regression: PASS
SQLite full suite: NOT RERUN FINAL — prior baseline 800 tests / 4056 assertions / 0 errors / 27 failures; targeted cluster 24/25 PASS
MariaDB critical suite: NOT COMPLETE — bounded run pending
MariaDB extended tenant suite: NOT RUN
Core business smoke: NOT RUN on final isolated MariaDB matrix
Measured stock regression: PASS
Cari legacy placeholder status: SAFE
Fresh-install reproducibility: PASS — isolated migration/seed chain
Post-test DB cleanup: PASS — no open transaction or pending metadata lock in isolated server
OVERALL: FAIL
```

## Phase 3.4 — Production Gate Blocker Fixes

### SQLite Phase 3.3 failure inventory

| Test | File | Expected / actual | HTTP / route | Middleware | Category |
|---|---|---|---|---|---|
| `test_odeme_finans_kaydi_firma_ayarlarindan_cari_kasa_kullanir` | `EcommerceTahsilatAyarlarFallbackTest.php:325` | Redirect expected / 404 | POST `/odeme/{siparis}/basarili` | Public ecommerce gate | AUTH/MIDDLEWARE |
| `test_firma_ayar_varsa_legacy_config_yok_sayilir` | `EcommerceTahsilatAyarlarFallbackTest.php:358` | Redirect expected / 404 | POST `/odeme/{siparis}/basarili` | Public ecommerce gate | AUTH/MIDDLEWARE |
| `test_paytr_callback_basarili_ve_idempotent` | `OdemeProviderIntegrationTest.php:317` | 200 / 500 | POST `/api/odeme/callback/paytr` | Webhook CSRF-exempt | PAYMENT DOMAIN BUG |
| `test_iyzico_callback_basarili_ve_idempotent` | `OdemeProviderIntegrationTest.php:517` | 200 / 500 | POST `/api/odeme/callback/iyzico` | Webhook CSRF-exempt | PAYMENT DOMAIN BUG |
| `test_odeme_basarili_siparis_odendi_ve_stok_duser` | `OdemeSiparisYasamDongusuTest.php:184` | Redirect / 404 | POST `/odeme/{siparis}/basarili` | Public ecommerce gate | AUTH/MIDDLEWARE |
| `test_depo_modulu_acikken_e_ticaret_rezervi_ve_stok_hareketi_depo_bakiyesine_yansir` | `OdemeSiparisYasamDongusuTest.php:228` | Redirect / 404 | POST `/odeme/{siparis}/basarili` | Public ecommerce gate | AUTH/MIDDLEWARE |
| `test_odeme_basarisiz_siparis_acik_kalir_ve_tekrar_denenebilir` | `OdemeSiparisYasamDongusuTest.php:283` | Redirect / 404 | POST `/odeme/{siparis}/basarisiz` | Public ecommerce gate | AUTH/MIDDLEWARE |
| `test_iptalde_finans_iade_otomatik_ters_kayit_olusturulur` | `OdemeSiparisYasamDongusuTest.php:310` | Redirect / 404 | POST `/odeme/{siparis}/basarili` | Public ecommerce gate | AUTH/MIDDLEWARE |
| `test_odeme_sonrasi_iptalde_stok_geri_gelir` | `OdemeSiparisYasamDongusuTest.php:369` | 10 / 8 stock | Service cancellation flow | N/A | STOCK ROLLBACK BUG |
| `test_odeme_basarili_finans_kaydi_olusur` | `OdemeSiparisYasamDongusuTest.php:418` | Redirect / 404 | POST `/odeme/{siparis}/basarili` | Public ecommerce gate | AUTH/MIDDLEWARE |
| `test_cift_odeme_idempotent` | `OdemeSiparisYasamDongusuTest.php:448` | `onaylandi` / `onay_bekliyor` | POST `/odeme/{siparis}/basarili` twice | Public ecommerce gate | IDEMPOTENCY BUG |
| `test_odeme_basarili_sonrasi_sepet_bosalir` | `SepetSiparisCoreTest.php:230` | Empty-cart page / redirect home | GET `/sepet` | One-page public gate | AUTH/MIDDLEWARE |

The eight checkout 404s were route-contract tests executed without the temporary public-site middleware contract. The routes exist and current targeted tests pass after test-only middleware isolation. The payment, rollback and idempotency failures were application/domain behavior and were fixed below.

### FIX-APP-09 — Oturumsuz ödeme callback finans scope’u

Root cause: successful PayTR/Iyzico callbacks enter the same `SiparisOdemeServisi::providerOdemeCallbackBasarili()` path and create the ecommerce finance movement, but `AlacakTahsilatServisi` reloaded that just-created movement through the active-user tenant global scope. In a webhook request there is no active panel tenant, so the reload raised `No query results for model [App\\Models\\Muhasebe\\FinansHareketi]` and rolled back the atomic callback transaction.

Affected tests: PayTR and Iyzico success/idempotency callbacks.

Production code affected: `laravel-core/app/Muhasebe/Servisler/AlacakTahsilatServisi.php`.

Change: reload the already firma-validated finance record with `withoutGlobalScopes()` and `lockForUpdate()`. The object identity and previously validated firm context remain authoritative; this does not bypass firm ownership checks or create empty finance rows.

Why correct: webhook processing is oturumsuz, while the callback service validates the order/provider/firma before entering the finance path. The entire payment, finance and stock flow remains inside the existing transaction.

SQLite verification: PASS — provider and order payment regression 32 tests / 168 assertions.  
MariaDB verification: PASS — same bounded payment regression 32 tests / 168 assertions.  
Idempotency verification: PASS — PayTR/Iyzico replay and duplicate mock payment produce no duplicate finance/stock/order side-effects.  
Tenant implication: finance is still selected and written using the order’s `firma_id`; no cross-tenant lookup was introduced.

### FIX-APP-10 — Fatura form tenant cache lifecycle

Root cause: `FaturaKaynagi::$aktifFirmaIdCache` retained a prior test/request’s firm ID across Livewire instances. MariaDB enforced `fatura_kalemleri.firma_id` foreign keys and exposed the stale lifecycle when the measured invoice success test inserted a line for a firm ID no longer present in the refreshed database state.

Affected tests: measured invoice Livewire success test in `FaturaOnayliOlusturmaAkisiTest.php`.

Production code affected: `laravel-core/app/Filament/Clusters/Muhasebe/Resources/FaturaKaynagi.php`.

Change: `aktifFirmaId()` now reads the scoped `TenantContextService` on each call instead of retaining a process-wide firm ID.

Why correct: tenant context is request/session state; a process-wide firm cache is unsafe across tenant switches and long-lived Livewire/PHPUnit processes. Foreign keys remain enabled.

SQLite verification: PASS — full suite 800 tests / 4138 assertions.  
MariaDB verification: PASS — critical suite 49 tests / 115 assertions, 0 errors/failures.

### FIX-APP-11 — Public cart test middleware contract

Root cause: the remaining cart assertion followed `/sepet` through the temporary `OnePagePublicSiteMiddleware`, which redirected to the one-page home instead of rendering the empty-cart page.

Affected test: `SepetSiparisCoreTest::test_odeme_basarili_sonrasi_sepet_bosalir`.

Production code affected: none.  
Change: `laravel-core/tests/Feature/Urun/SepetSiparisCoreTest.php` bypasses only the temporary public-site gate for this controller-level cart test. Authentication, ecommerce module and tenant middleware behavior remain covered separately.

SQLite verification: PASS.  
MariaDB verification: PASS.

### Phase 3.4 final result

Checkout POST routes: PASS  
PayTR callback: PASS  
Iyzico callback: PASS  
Finance side-effect: PASS  
Duplicate payment idempotency: PASS  
Cancellation stock rollback: PASS  
Measured invoice MariaDB FK: PASS

SQLite full: 800 tests, 4138 assertions, 0 errors, 0 failures — PASS  
MariaDB critical: 49 tests, 115 assertions, 0 errors, 0 failures — PASS  
Payment regression: 32 tests, 168 assertions, 0 errors, 0 failures — PASS  
294 migrations: PASS  
Seed: PASS  
Seed idempotency: PASS  
Application boot: PASS  
Open transactions: 0  
Pending user metadata locks: 0 observed; MariaDB 10.4 isolated instance has no `performance_schema.metadata_locks` table, and `SHOW PROCESSLIST` showed no user lock holder.

OVERALL: PASS

PRODUCTION BACKUP TEST GATE: OPEN

## Phase 3.3 — Final Test Gate

### Targeted Livewire failure

Test: `FaturaOnayliOlusturmaAkisiTest::test_olculu_stokta_olcu_dagilimi_olmadan_onayli_fatura_olusturulamaz`

Expected: Ölçülü giden stok satırında dağılım yoksa `kalemler.0.olcu_dagilimlari` validation hatası ve 0 fatura.

Actual: İlk fixture tek geçerli ölçü bakiyesi oluşturarak otomatik dağılımı tetikliyordu. Sonraki Livewire payload’ı repeater form state’ine taşınmadığı için `CreateFatura` dönüşümünde `kalemler` boş görünüyordu.

Validation state: Domain servisi aynı payload’ı doğru biçimde reddediyor; beklenen anahtar `kalemler.0.olcu_dagilimlari`.

Livewire state: `set('data', ...)` / `fillForm()` çağrısı bu testte repeater satırını dehydration aşamasına taşımadı.

Domain rule: Ölçülü giden stokta en az bir geçerli ölçü bakiyesi dağılımı zorunludur.

Root cause: `LIVEWIRE LIFECYCLE EXPECTATION`; test fixture/lifecycle kullanımı. Validation kuralı gevşetilmedi.

Targeted SQLite cluster: PASS — 25 tests, 60 assertions, 0 error, 0 failure.

### SQLite full final

Tests: 800  
Assertions: 4082  
Errors: 0  
Failures: 12  
Skipped: 0  
Duration: 12:21.933  
PASS / FAIL: FAIL

Failure groups: ecommerce checkout POST routes (8 × 404), PayTR/Iyzico callback finance side-effect (2 × 500), cancellation stock rollback (1), duplicate-payment idempotency status (1). Payment failures were not hidden by changing assertions.

### MariaDB bounded verification

MariaDB test infrastructure: PASS for isolated `127.0.0.1:3307`; each harness run recreated only `yalovayazilimsaas_test_phase32_20260822`, ran migrations, seed twice and `artisan about`.

MariaDB critical: FAIL — 49 tests, 111 assertions, 0 failures, 1 error. The error is a MariaDB-only foreign-key fixture/lifecycle failure in the measured invoice Livewire success test (`fatura_kalemleri.firma_id` references a non-existent firm row). No migration was changed.

MariaDB tenant matrix: 42 tests, 91 assertions, 0 errors, 0 failures.

Cari: PASS  
Fatura: PASS  
Stok: PASS  
Teknik Servis: PASS  
Personel: PASS  
Restoran: PASS  
Sekreter: PASS

Core business smoke: PASS — 85 tests, 165 assertions, 0 errors, 0 failures for explicit Cari/Stok/Fatura/ölçülü stok/teknik servis critical smoke classes. Payment callback is reported separately below.

Payment callback / finance side effect: FAIL — PayTR and Iyzico callback tests return HTTP 500; logs show `No query results for model [App\Models\Muhasebe\FinansHareketi]` after callback processing. The expected finance movement is not reliably present; this is an application/domain integration failure requiring a separate minimum fix or decision. Assertion was not weakened.

Measured stock: PASS — targeted SQLite and bounded MariaDB core coverage; the MariaDB critical group remains overall FAIL because of the separate invoice fixture FK error.

AD/ADET: PASS  
294 migrations: PASS  
Seed: PASS  
Seed idempotency: PASS  
Application boot: PASS

Open transactions after tests: 0  
Pending metadata locks: 0 observed user locks; MariaDB 10.4 isolated instance does not expose `performance_schema.metadata_locks`. `SHOW PROCESSLIST` contained only system purge workers and the inspection query.

App\\Models\\Cari: No active references found. Real model: `App\\Models\\Muhasebe\\Cari`. Cleanup deferred.

OVERALL: FAIL

PRODUCTION BACKUP TEST GATE: CLOSED

Production backup aşamasına geçilmedi; production DB, credential veya backup kullanılmadı.

QA MariaDB: `yalovayazilimsaas_qa_20260822`. Boş veritabanı → 294 migration → zincir başarılı.

İlk seed sonrası: 7 rol, 164 yetki, 487 rol-yetki, 14 modül, 3 plan, 26 plan-modül, 5 sistem ölçü birimi. `users=0`, `firmalar=0`; demo veri üretilmedi.

## Seeder Idempotency

Aynı minimum zincir ikinci kez çalıştırıldı. Tüm seeder’lar başarılı; yukarıdaki sayımlar değişmedi. Sonuç: PASS.

## Application Boot

QA DB üzerinde `artisan about` başarılı. Laravel 11.48.0, PHP 8.2.12, MariaDB driver, Filament v3.3.0 ve Livewire v3.7.11 yüklendi. `route:list` başarılı; Filament admin rotaları, `/giris`, `/yonetici-giris` ve ilgili POST rotaları keşfedildi. Panel route boot’u başarısız olmadı.

Not: gerçek tarayıcı login akışı ve seeded kullanıcıyla login doğrulaması yapılmadı; minimum zincir bilinçli olarak kullanıcı oluşturmuyor.

## SQLite Test Suite

Mevcut `phpunit.xml` değiştirilmeden çalıştırıldı:

```text
Tests: 794
Assertions: 4032
Errors: 3
Failures: 28
Duration: 13:25.862
```

Başlıca kümeler: ödeme/e-ticaret route veya auth beklentileri (404/302), testte kur verisi bulunmaması, ölçü birimi alias uyumsuzluğu ve bazı ölçülü fatura Livewire validation beklentileri. Sonuç: FAIL.

## MariaDB Integration Tests

Ayrı profil oluşturuldu: `laravel-core/phpunit.mariadb.xml`. Ayrı DB: `yalovayazilimsaas_phpunit_mariadb_20260822`.

Seçili kritik dosyalarla 91 test çalıştı:

```text
Tests: 91
Assertions: 219
Errors: 1
Failures: 1
Duration: 03:11.789
```

Hatalar `StokOlcuVeriModeliTest` içindeki sistem birimi sayısı ve legacy `AD/ADET` alias senaryosundadır. Sonuç: FAIL.

## Tenant Isolation

Muhasebe tanım kapsamı ve seçili cari/ölçü/fatura tenant testleri MariaDB profilinde koştu; bu seçili kapsamda tenant scope ihlali gözlenmedi. Ancak istenen Cari, Fatura, Stok, Teknik Servis, Personel, Restoran ve Sekreter kapsamının tamamı tek bir MariaDB koşusunda tamamlanmadı. Sonuç: FAIL — kapsam tamamlanmadı.

## Business Smoke Tests

Ölçülü stok/fatura kritik dosyaları MariaDB’de çalıştırıldı. Migration şeması ve ölçü tabloları mevcut; fakat ölçü birimi alias hatası nedeniyle kritik smoke grubunun tamamı yeşil değil. Teknik servis ve finansın geniş uçtan uca smoke matrisi bu turda tamamlanmadı. Sonuç: FAIL.

## Data Integrity

QA minimum seed sonrası kritik orphan kontrolleri:

```text
fatura_kalemleri → faturalar: 0
firma_kullanicilari → firmalar: 0
firma_kullanicilari → users: 0
stok_hareketleri → stok_kartlari: 0
stok_hareketleri → firmalar: 0
```

Migration sayısı 294, tablo sayısı 191. Sonuç: PASS (kritik kontrol kapsamı).

## Regression Tests

Phase 2.1’de doğrulanan dört migration düzeltmesi bu turda iki ayrı MariaDB boş kurulumunda tekrar migration akışı içinde geçti:

- MariaDB index driver uyumluluğu;
- MariaDB’de UUID yerine `CHAR(36)`;
- ikinci UUID alanında `CHAR(36)`;
- ölçülü stok migration’ındaki doğru `after('stok_takip')` anchor’ı.

İki boş DB’de de 294/294 migration başarılı oldu. Sonuç: PASS.

## Reproducibility

İkinci boş DB: `yalovayazilimsaas_repro_20260822`.

Sonuç: boş DB → 294 migration → minimum seed başarılı. Doğrulanan sayımlar: `294 migration`, `7 rol`, `164 yetki`, `14 modül`, `3 plan`, `5 ölçü birimi`, `0 user`, `0 firma`. Sonuç: PASS.

## Remaining Risks

1. `MuhasebeOlcuBirimleriSeeder`, migration’ın canonical `AD` kaydı ile legacy `ADET` kaydı aynı anda bulunduğunda “AD, ADET” diyerek duruyor. Mevcut test bunu beklenmeyen duplicate olarak yakalıyor. Üretim verisine dokunmadan alias migration/normalizasyon kararı verilmelidir.
2. `VarsayilanSuperAdminSeeder` sabit parola kullanıyor; üretim seed zincirine alınmamalı ve güvenli kurulum akışı ayrıca tasarlanmalı.
3. SQLite suite’te 31 başarısız/hataya düşen test mevcut; bunlar Phase 3 temiz kurulumunun tek başına kanıtı değildir ve ayrı regresyon işi gerektirir.
4. MariaDB’de geniş tenant matrisi ve gerçek HTTP login/tarayıcı doğrulaması tamamlanmadı.
5. `App\Models\Cari` adlı boş placeholder model ile gerçek `App\Models\Muhasebe\Cari` ayrımı önceki raporda görülen legacy riskidir.

## Final Summary

```text
CLEAN APPLICATION RESULT
294 migrations: PASS
Required seed: PASS
Seeder idempotency: PASS
Application boot: PASS
SQLite test suite: FAIL
MariaDB critical suite: FAIL
Tenant isolation: FAIL
Core business smoke: FAIL
Data integrity: PASS
Fresh install reproducibility: PASS
OVERALL: FAIL
```

Production restore, production credential kullanımı, schema dump/baseline, eski migration silme veya deploy yapılmadı.

## Phase 3.1 — Test Stabilization

### Domain decision: AD/ADET

Repository kanıtı canonical kodun `AD` olduğunu gösterir: 2026-08-20 migration’ı `AD` ve `KGM` sistem kayıtlarını seed eder; stok, fatura, restoran, teknik servis ve e-ticaret fixture’larının baskın kısmı `AD` kullanır; görünen ad `Adet`tir.

```text
Canonical code: AD
Display name: Adet
Legacy accepted alias: ADET
```

`ADET`, `Adet` ve `adet` artık merkezi `BirimKodResolver` üzerinden `AD` anlamına çözülür. Fiziksel legacy satır silinmez, yeniden adlandırılmaz veya canonical satırla birleştirilmez.

### FIX-APP-01 — Central AD/ADET resolution

Root cause: ölçü birimi kodları uygulamada `AD` olarak canonical kullanılırken seeder, `AD` ve mevcut legacy `ADET` satırlarını iki ayrı sistem birimi olarak hata kabul ediyordu.

Affected tests: `StokOlcuVeriModeliTest` legacy alias senaryosu; yeni resolver unit testleri.

Files: `app/Muhasebe/Servisler/BirimKodResolver.php`, `app/Muhasebe/Tanimlar/TanimKullanimDenetleyicisi.php`.

Change: `AD/ADET/Adet/adet` normalization ve accepted-code resolution merkezi servise alındı; kullanım denetimi her iki fiziksel kodu da arıyor.

Why correct: canonical karar repository migration ve fixture kanıtına dayanıyor; controller/form/test dosyalarına dağınık eşleme eklenmedi.

SQLite verification: resolver + ölçü modeli hedefi PASS, 10 test / 24 assertion.

MariaDB verification: DB engine’de mevcut orphan metadata lock nedeniyle yeniden çalıştırma engellendi; önceki 91 testlik koşunun tek ölçü alias hatası bu değişiklikle hedeflenmiştir.

Production implication: production’daki `ADET` satırına delete/update uygulanmaz. FK reconciliation ayrı veri migrationı olarak tasarlanmalıdır.

### FIX-APP-02 — Alias-safe system-unit seeder

Root cause: seeder aynı canonical/legacy anlam için birden fazla satır görünce exception atıyordu; ayrıca migration-created canonical kayıtların eksik system metadata’sını tamamlamıyordu.

Affected tests: system-unit idempotency ve legacy alias tests.

Files: `database/seeders/MuhasebeOlcuBirimleriSeeder.php`, `tests/Feature/Muhasebe/StokOlcuVeriModeliTest.php`.

Change: canonical ve alias birlikte varsa alias korunuyor, canonical metadata (`gib_birim_kodu`, `is_sabit`, `aktif_mi`) tamamlanıyor; alias satırı değiştirilmiyor. Canonical yoksa yalnız bir canonical satır oluşturuluyor.

Why correct: fresh DB’de tek canonical AD oluşuyor; existing production alias’ı silme/rename/update yok.

SQLite verification: PASS, system-unit and alias cases included in 10-test targeted run.

MariaDB verification: rerun blocked by local MariaDB orphan transaction (`trx_mysql_thread_id=0`) and metadata lock; no production DB involved.

Production implication: canonical/legacy FK migrationı bu fazda yapılmadı.

### FIX-APP-03 — Measured-stock conversion regression

`StokOlcuHesaplamaServisiTest` içine deterministic precision regression eklendi:

```text
4 adet × 4 m² = 16 m²
14 m² / 4 m² = 3.5 adet
0.5 adet × 4 m² = 2 m²
```

Bu test yalnız mevcut domain hesaplama servisini doğrular; production davranışı değiştirmez.

### FIX-APP-04 — Deterministic currency fixture

Root cause: two SQLite tests enabled multi-currency conversion but did not create a deterministic `USD → TRY` rate, so production-correct “rate missing” behavior escaped from the fixture as an unexpected error.

Affected tests: `MuhasebeFinalHardeningTest::test_farkli_para_birimi_kapama_reddedilir`, `test_cari_bakiye_para_birimi_bazinda_dogru_hesaplanir`.

Files: `tests/Feature/Muhasebe/MuhasebeFinalHardeningTest.php`.

Change: fixed test-only rate (`40`) and explicit conversion config added.

Why correct: no external service, current-day rate or production configuration is used.

Verification: targeted PASS, 2 tests / 3 assertions.

### FIX-APP-05 — Test scoped-service isolation

Root cause: `RefreshDatabase` resets rows/IDs, while scoped services can retain per-firma/module cache state in a long PHPUnit process.

File: `tests/TestCase.php`.

Change: test teardown forgets scoped instances before the next isolated test.

Why correct: test-only lifecycle hygiene; no runtime authorization or tenant rule is weakened. The remaining payment failures are still caused by public-site middleware/fixture behavior, not by this cache alone.

### Test stabilization status

SQLite full suite Phase 3.1 sonrası çalıştırıldı: 800 test, 4056 assertion, 0 error, 27 failure, 13:08.507. AD/ADET ve currency error kümeleri temizlendi. Kalan 27 failure’ın 25’i public-site/payment route beklentileri, 2’si ölçülü fatura Livewire validation expectation farkıdır. Bu fazda auth zayıflatılmadı ve route silinmedi.

MariaDB extended tenant suite ve core smoke suite, local MariaDB orphan metadata lock temizlenmeden güvenilir biçimde çalıştırılamadı. Bu bir uygulama sonucu değil, test altyapısı engelidir.

### Production normalization plan (analysis only)

İleride production backup üzerinde uygulanacak plan: önce `muhasebe_birimler` içinde canonical/alias satırlarını ve tüm FK kullanım noktalarını envanterlemek; `ADET` referanslarını transaction içinde canonical `AD` id’sine taşımak; `stok_kartlari.birim` ve `fatura_kalemleri.birim` historical snapshot alanlarını değiştirmeden bırakmak; ardından zero-reference alias satırını ayrı onaylı migration’da pasifleştirmek/silmek. Bu fazda hiçbir production veri migrationı yazılmadı veya çalıştırılmadı.

## Phase 3.1 Final Result

```text
PHASE 3.1 RESULT

294 migrations: PASS (previously verified; current DB lock prevented rerun)
Required seed: PASS (previously verified)
Seed idempotency: PASS (previously verified)
Application boot: PASS (previously verified)
AD/ADET canonical resolution: PASS
Alias regression: PASS (SQLite targeted)
SQLite full suite: FAIL — 800 tests, 4056 assertions, 0 errors, 27 failures
MariaDB critical suite: BLOCKED — local orphan metadata lock
MariaDB extended tenant suite: BLOCKED — local orphan metadata lock
Core business smoke: FAIL / blocked for MariaDB
Measured stock regression: PASS (targeted SQLite)
Data integrity: PASS (previously verified)
Fresh-install reproducibility: PASS (previously verified)
OVERALL: FAIL
```
