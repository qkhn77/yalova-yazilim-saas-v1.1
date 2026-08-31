# Local Clean Database / Migration Verification Report

## Phase 2.1 status update

Bu bölüm, önceki Phase 2 failure raporunu günceller. Aşağıdaki ilk bölümdeki eski `FAIL` sonucu tarihsel ilk denemeye aittir; güncel clean migration sonucu bu dosyanın sonundaki `CLEAN MIGRATION RESULT` bölümündedir.

Güncel durum: **294 / 294 migration PASS**.

**Tarih:** 2026-08-22  
**Kapsam:** Yalnızca yeni ve izole local MariaDB veritabanı. Production bağlantısı, backup, restore ve mevcut geliştirme DB'si kullanılmadı.  
**Ön koşul:** `database-health-audit.md` tamamen okundu.

## Environment

- Laravel: `11.x` (`laravel/framework ^11.0`)
- PHP runtime: `C:\xampp\php\php.exe` (PATH'te PHP yoktu; XAMPP binary'si kullanıldı)
- Mevcut `.env`: `APP_ENV=local`, `APP_URL=http://127.0.0.1:8000`
- Mevcut DB: `yalovakamera_local` — kullanılmadı.
- Mevcut host/port: `127.0.0.1:3306`
- Uygulama `.env` dosyası değiştirilmedi; migration process'i izole DB değerleriyle override edildi.

## Isolation Verification

İzole DB:

```text
yalovayazilimsaas_clean_test_20260822
```

- Host `127.0.0.1` loopback ve MariaDB hostname `RogStrix` local makine olarak doğrulandı.
- Test DB, `yalovakamera_local` geliştirme DB'sinden farklıdır.
- Migration öncesi tablo sayısı `0`; `migrations` tablosu yoktu.
- DB charset/collation: `utf8mb4` / `utf8mb4_unicode_ci`.
- Production host/domain/IP tespit edilmedi; production credential kullanılmadı.
- Failure sonrası kısmi migration şeması bu izole DB'de bırakıldı; mevcut geliştirme DB'si silinmedi veya değiştirilmedi.

## MariaDB Version

```text
10.4.32-MariaDB
Version comment: mariadb.org binary distribution
Server hostname: RogStrix
Laravel connection driver: mariadb
```

## Migration Execution

Çalıştırılan komut:

```text
C:\xampp\php\php.exe artisan migrate --no-interaction --force
```

Process DB ayarları:

```text
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yalovayazilimsaas_clean_test_20260822
DB_USERNAME=root
```

Sonuç:

- Repository migration dosyası: **294**.
- Başarıyla tamamlanan migration: **171**.
- Failure veren migration: **1**.
- Başarısız olan dahil denenmiş migration: **172** (`171 PASS + 1 FAIL`).
- Failure sonrasında bekleyen migration: **122**.
- Failure anındaki kısmi base table sayısı: **125**.
- Artisan exit code: `1`.
- Seeder, rollback, `migrate:fresh`, DB wipe veya corrective migration çalıştırılmadı.

## First Failure

```text
Migration: 2026_05_30_051000_teknik_servis_muhasebe_tablolarini_onar.php
SQLSTATE: 42000
MariaDB error: 1061 Duplicate key name
Table: teknik_servis_muhasebe_baglantilari
Index: ts_muhasebe_firma_only
SQL: alter table `teknik_servis_muhasebe_baglantilari` add index `ts_muhasebe_firma_only`(`firma_id`)
```

Başarılı son migration:

```text
2026_05_30_050000_teknik_servis_fis_numaralari_tablosunu_onar.php
```

Kök neden kanıtı:

1. `2026_03_27_160300_create_teknik_servis_bagimli_tablolari.php`, `teknik_servis_muhasebe_baglantilari` oluşturulurken `ts_muhasebe_firma_only` index'ini zaten ekliyor.
2. `2026_05_30_051000_teknik_servis_muhasebe_tablolarini_onar.php` aynı index için `indexEkle()` çağırıyor.
3. Bu migrationdaki `indexVarMi()` yalnız `sqlite` ve `mysql` driver adlarını işliyor; Laravel MariaDB bağlantısında gerçek driver adı `mariadb`.
4. `mariadb` için helper doğrudan `false` döndürüyor, mevcut index görülmüyor ve duplicate index deneniyor.

Migration dosyası değiştirilmedi; otomatik düzeltme yapılmadı.

## Migration Ordering

Failure'a kadar gerçek sıra dosya adı sıralamasıyla şöyledir:

```text
2026_03_27_160300_create_teknik_servis_bagimli_tablolari.php
2026_05_30_020000_create_teknik_servis_alacak_takip_notlari_table.php
2026_05_30_020000_teknik_servis_liste_indeksleri.php
2026_05_30_030000_extend_alacak_takip_and_create_plan_revizyonlari.php
2026_05_30_030000_teknik_servis_muhasebe_ozet_indeksleri.php
2026_05_30_040000_create_muhasebe_alacak_plan_onay_talepleri_table.php
2026_05_30_041000_add_faiz_fields_to_muhasebe_alacak_planlari.php
2026_05_30_043000_add_vade_farki_fields_to_muhasebe_alacak_planlari.php
2026_05_30_044500_create_hizli_satis_favorileri_table.php
2026_05_30_050000_teknik_servis_fis_numaralari_tablosunu_onar.php
2026_05_30_051000_teknik_servis_muhasebe_tablolarini_onar.php  ← FAIL
```

Aynı timestamp prefix'li dosyalar ayrıca `2026_03_26_120000`, `2026_05_31_020000`, `2026_08_22_120000` ve `2026_08_22_170000` gruplarında bulunuyor. Bu test, 2026-05-30 zincirinde gerçek idempotency/driver uyumsuzluğu olduğunu kanıtladı.

## Fresh Schema Summary

**Final fresh schema oluşmadı.** Bu DB ileride kullanılacak `FRESH SCHEMA` değildir.

Failure anında:

- `migrations` tablosunda 171 kayıt `Ran`.
- `teknik_servis_muhasebe_baglantilari` mevcut ve `ts_muhasebe_firma_only` index'i zaten var.
- Kısmi base table sayısı 125.
- Final tablo/kolon/FK/index/enum/generated-column/view/trigger envanteri çıkarılmadı; baseline veya schema dump oluşturulmadı.

## Model vs Schema Findings

Final şema oluşmadığı için `Firma`, `User`, `Cari`, `Fatura`, `FaturaKalemi`, `StokKarti`, `StokHareketi`, `StokOlcusu`, `StokDepoBakiyesi`, `StokSeriNo`, `TeknikServisKaydi` ve `TeknikServisKalem` için final MariaDB karşılaştırması **NOT TESTED**.

Failure model kolon uyumsuzluğu değildir; migrationın driver-aware index existence helper'ındaki `mariadb` eksikliğidir. Tüm fillable/casts/relations/soft-delete/FK uyumu migration zinciri tamamlanmadan PASS sayılamaz.

## Parti/Parça/Seri/Ölçü Findings

Failure 2026-05-30'da olduğu için son 2026-08-22 migrationları beklemede kaldı:

- Parti/parça kaldırma: `2026_08_22_120000_remove_parti_parca_sistemi.php`, `2026_08_22_150000_remove_remaining_parti_parca_columns.php` — çalışmadı.
- Legacy metadata temizliği: `2026_08_22_180000_cleanup_parti_parca_legacy_metadata.php` — çalışmadı.
- Serial restore: `2026_08_22_130000_restore_serial_tracking_type.php` — çalışmadı.
- Ölçülü stok, depo ve fatura ölçü dağılımı zincirleri — final state'e ulaşmadı.

Final DB'de legacy parti/parça tabloları/kolonları/indexleri ve seri/ölçü/depo alanları için sonuç: **UNKNOWN**.

## Seeder Requirements

Seeder çalıştırılmadı. Statik olarak aday minimum zincir:

1. `SaasDatabaseSeeder` — SaaS roller, modüller, yetkiler, planlar ve matrisler.
2. `VarsayilanSuperAdminSeeder` — yönetici erişimi.
3. `MuhasebeOlcuBirimleriSeeder` — ölçü birimleri/default tanımlar.
4. CMS istenirse `ServiceSeeder`, `ProjectSeeder`, `PageSeeder`, `MenuSeeder`.
5. Yalnız local/testing için `SaasDevSampleSeeder` veya `QaFullSaaSTestSeeder`.

`DatabaseSeeder` SaaS çekirdek seed'ini otomatik çağırmıyor. Seeder readiness: **UNKNOWN**.

## Test Environment Safety

- `phpunit.xml` `DB_CONNECTION=sqlite` ve `DB_DATABASE=:memory:` değerlerini `force="true"` ile kullanıyor.
- `.env.testing.example` SQLite memory DB'yi varsayılan gösteriyor; MySQL örneği yorum satırında.
- PHPUnit suite'i çalıştırılmadı.
- Production veya mevcut geliştirme DB'sine bağlanılmadı.
- Mail/SMS/webhook/ödeme API/queue dış sistem çağrısı yapılmadı.

**Test environment isolation: PASS** — bu sonuç çalıştırılan migration process'inin DB izolasyonudur; PHPUnit davranışı ayrıca test edilmedi.

## Smoke Test

Migration zinciri tamamlanmadığı için application boot, route loading ve HTTP smoke testleri çalıştırılmadı. Eksik default veri kaynaklı hataları migration failure'ı ile karıştırmamak için duruldu.

```text
Application boot: NOT TESTED
```

## Risks

### CLEAN-01 — CRITICAL

`2026_05_30_051000_teknik_servis_muhasebe_tablolarini_onar.php` MariaDB driver adını tanımıyor. Empty MariaDB migration zinciri doğrudan bu nedenle kırılıyor.

### CLEAN-02 — HIGH

`ts_muhasebe_firma_only` index adı hem `2026_03_27_160300_create_teknik_servis_bagimli_tablolari.php` hem repair migrationda yönetiliyor. Driver helper düzelse bile iki migrationın aynı index sorumluluğu bakım/rollback riski taşıyor.

### CLEAN-03 — HIGH

SQLite testleri MariaDB driver/index metadata farkını yakalamamış. `phpunit.xml` MariaDB'yi zorunlu test etmiyor.

### CLEAN-04 — HIGH

İlk failure çözülmeden parti/parça cleanup, serial restore ve ölçülü stok final şeması doğrulanamıyor.

### CLEAN-05 — MEDIUM

`yalovayazilimsaas_clean_test_20260822` artık kısmi migration DB'sidir; sonraki deneme aynı DB üzerinde yapılmamalı, yeni izole DB adı kullanılmalıdır.

## Result

Soru: “Mevcut repository boş MariaDB üzerinde migrationlardan yeniden oluşturulabiliyor mu?”

**Mevcut haliyle hayır.** İlk gerçek clean-install migration testinde failure alındı. Bu yalnız local izole DB sonucudur; production DB/backup hakkında çıkarım değildir.

```text
CLEAN DATABASE RESULT

Local DB isolation: PASS
Migration from empty DB: FAIL
Schema creation: FAIL
Critical model compatibility: NOT TESTED
Legacy parti/parça cleanup: UNKNOWN
Serial tracking: UNKNOWN
Measured stock schema: UNKNOWN
Seeder readiness: UNKNOWN
Test environment isolation: PASS
Application boot: NOT TESTED
OVERALL: FAIL
```

Migration dosyaları değiştirilmedi, corrective migration yazılmadı, seed çalıştırılmadı, baseline/schema dump oluşturulmadı ve production'a bağlanılmadı.

---

## Repair History

### FIX-01

**File:** `laravel-core/database/migrations/2026_05_30_051000_teknik_servis_muhasebe_tablolarini_onar.php`  
**Original problem:** MariaDB'de mevcut `ts_muhasebe_firma_only` index'i yok sanılıp duplicate index oluşturuluyordu.  
**Evidence:** `Schema::getConnection()->getDriverName()` gerçek değeri `mariadb`; helper yalnız `mysql` ve `sqlite` işliyordu. İlk fresh denemede SQLSTATE `42000`, error `1061` alındı.  
**Change:** `mysql` yanında `mariadb` driver'ı da metadata sorgusuna dahil edildi.  
**Why minimal:** Index adı, migration amacı, timestamp ve drop davranışı değiştirilmedi; yalnız driver uyumluluğu düzeltildi.  
**Fresh migration verification:** Yeni boş DB'de 294 zincirin tamamı bu düzeltmeyle ilgili ilk failure'ı geçti.  
**Potential production impact:** Daha önce `Ran` olan production migrationı tekrar çalışmaz; bu nedenle mevcut production şemasını değiştirmez. Fresh kurulum ve migrationın henüz çalışmadığı ortamlarda duplicate index failure'ını önler. Production upgrade öncesi migration history/schema diff yine zorunludur.

### FIX-02

**File:** `laravel-core/database/migrations/2026_06_06_061000_nette_fatura_entegrasyon_altyapisi.php`  
**Original problem:** Laravel MariaDB grammar `uuid()` için `uuid` SQL tipi üretti; MariaDB 10.4 `uuid` tipini kabul etmedi.  
**Evidence:** SQLSTATE `42000`, error `1064`; SQL: `alter table faturalar add e_belge_uuid uuid null ...`.  
**Change:** `uuid('e_belge_uuid')` yerine `char('e_belge_uuid', 36)` kullanıldı.  
**Why minimal:** UUID değeri aynı 36 karakterlik metin anlamını koruyor; alan adı, nullable davranışı ve kolon sırası korunuyor.  
**Fresh migration verification:** İkinci fresh döngüde Nette Fatura migrationı PASS oldu.  
**Potential production impact:** Daha önce migrationı çalışmış production DB'de dosya yeniden çalışmaz. Yeni/fresh MariaDB kurulumlarında geçerli ve MariaDB 10.4 ile uyumludur; production upgrade'de mevcut kolon tipi ayrıca schema diff ile doğrulanmalıdır.

### FIX-03

**File:** `laravel-core/database/migrations/2026_06_06_170000_create_nette_fatura_gelen_belgeler_table.php`  
**Original problem:** Aynı `uuid()` → MariaDB 10.4 uyumsuzluğu, sonraki `ettn` kolonunda da kesin olarak bulunuyordu.  
**Evidence:** Repository'de `uuid()` kullanan ikinci migration; aynı MariaDB driver ve aynı grammar davranışı.  
**Change:** `uuid('ettn')` yerine `char('ettn', 36)` kullanıldı.  
**Why minimal:** ETTN UUID metin uzunluğu ve index davranışı korunuyor; yalnız SQL tipinin MariaDB uyumlu karşılığı seçildi.  
**Fresh migration verification:** Üçüncü fresh döngüde migration PASS oldu.  
**Potential production impact:** Daha önce `Ran` production migrationı tekrar çalışmaz. Fresh kurulumlarda MariaDB 10.4 syntax failure'ını önler; mevcut production kolonları schema diff ile karşılaştırılmalıdır.

### FIX-04

**File:** `laravel-core/database/migrations/2026_08_16_120000_create_olculu_stok_altyapisi.php`  
**Original problem:** `stok_kartlari` tablosunda bulunmayan `stok_takip_tipi` kolonunun arkasına `olculu_takip_turu` eklenmeye çalışıldı.  
**Evidence:** Fresh failure SQLSTATE `42S22`, error `1054`; `stok_takip_tipi` ancak daha sonraki `2026_08_22_130000_restore_serial_tracking_type.php` migrationında oluşturuluyor. Bu noktada gerçek mevcut kolon `stok_takip`.  
**Change:** Yalnız `after('stok_takip_tipi')` → `after('stok_takip')` değiştirildi.  
**Why minimal:** Kolon yerleşim anchor'ı mevcut schema'ya alındı; alan adı, türü, default'u, index'i ve ölçülü stok domain davranışı değiştirilmedi.  
**Fresh migration verification:** Üçüncü fresh döngüde ölçülü stok migrationı ve sonraki serial restore zinciri PASS oldu.  
**Potential production impact:** Daha önce `Ran` production migrationı tekrar çalışmaz. Değişiklik yalnız yeni kurulumlarda kolon fiziksel sırasını etkiler; mantıksal kolon adı/tipi değişmez. Production schema diff ile gerçek kolon sırası ayrıca doğrulanmalıdır.

### Değiştirilmeyen benzer desenler

Repository taramasında `mysql`/`sqlite` driver branching kullanan başka index helper'ları bulundu (`2026_05_30_020000`, `2026_05_30_030000`, 2026-07 index migrationları ve benzerleri). Fresh MariaDB zincirinde bunların aynı duplicate/index failure'ı ürettiği kanıtlanmadı; bu nedenle değiştirilmediler. `uuid()` pattern'i ise repository'deki iki kullanımda da aynı kesin MariaDB 10.4 syntax problemine sahip olduğu için FIX-02/FIX-03 kapsamında düzeltildi.

## Final Schema Inventory — FRESH SCHEMA CANDIDATE

Bu envanter canonical/baseline değildir; yalnızca 294/294 migration sonrası izole DB metadata özetidir.

```text
Database: yalovayazilimsaas_clean_test_20260822
MariaDB: 10.4.32-MariaDB
Tables: 191 base tables
Columns: 2962
Foreign keys: 452
Unique constraints: 125
Non-primary index entries: 1304
Enum columns: 5
Generated columns: 0
Views: 0
Triggers: 0
Engine/collation: InnoDB / utf8mb4_unicode_ci
```

## Final Critical Model ↔ Schema Check

Read-only Eloquent metadata karşılaştırmasında gerçek muhasebe modelleri (`App\Models\Muhasebe\Cari` dahil) için kritik model tabloları bulundu ve fillable alanlarında eksik kolon görülmedi:

```text
Firma → firmalar
User → users
Cari → cariler
Fatura → faturalar
FaturaKalemi → fatura_kalemleri
StokKarti → stok_kartlari
StokHareketi → stok_hareketleri
StokOlcusu → stok_olculeri
StokDepoBakiyesi → stok_depo_bakiyeleri
StokSeriNo → stok_seri_nolari
TeknikServisKaydi → teknik_servis_kayitlari
TeknikServisKalem → teknik_servis_kalemleri
```

Soft-delete kontrolü de uyumlu bulundu: SoftDeletes kullanan kritik modellerde `deleted_at` mevcut; kalem/hareket/bakiye/seri modellerinde SoftDeletes kullanılmıyor ve buna karşılık gelen kolonlar yok. Bu kontrol table/fillable/SoftDeletes kapsamındadır; tüm ilişki davranışı ve her cast'in semantik testi sonraki uygulama test fazındadır.

## Final Parti/Parça/Seri/Ölçü/Depo Check

- Legacy parti/parça kolonları (`parti_no`, `parti_dagilimi`, `parca_kodu`, `parca_no`, `parca_dagilimi`): final şemada yok.
- Legacy parti/parça adı taşıyan tablo: final şemada yok.
- Legacy parti/parça adı taşıyan index: final şemada yok.
- `parcali_kullanima_izin` mevcut; bu, yeni ölçülü stok migrationında tanımlanan alan olup legacy `parti_*`/`parca_*` kolonlarından ayrı değerlendirilmiştir.
- Seri: `stok_seri_nolari`, `stok_hareketi_serileri`, `stok_kartlari.stok_takip_tipi` ve seri barcode alanları mevcut.
- Ölçülü stok: `olculu_takip_turu`, `stok_olculeri`, `stok_olcu_bakiyeleri`, `stok_hareketi_olcu_dagilimlari`, `fatura_kalemi_olcu_dagilimlari` mevcut.
- Depo: `muhasebe_depolar`, `stok_depo_bakiyeleri` ve ilgili hareket/kalem `depo_id` alanları mevcut.

```text
Legacy parti/parça cleanup: PASS
Serial tracking: PASS
Measured stock: PASS
Warehouse/depot: PASS
```

## CLEAN MIGRATION RESULT

```text
Repository migrations: 294
Fresh MariaDB migrations passed: 294 / 294
Repair count: 4 migration files

Local isolation: PASS
Migration chain: PASS
Final schema: PASS — FRESH SCHEMA CANDIDATE, not canonical/baseline
Critical model compatibility: PASS — table/fillable/SoftDeletes scope
Legacy parti/parça cleanup: PASS
Serial tracking: PASS
Measured stock: PASS
Warehouse/depot: PASS
Production data compatibility: NOT TESTED

OVERALL: PASS
```

Production backup restore edilmedi, production DB'ye bağlanılmadı, seeder/full application test suite çalıştırılmadı, schema dump/baseline oluşturulmadı. Sonraki faz için onay beklenmelidir.
