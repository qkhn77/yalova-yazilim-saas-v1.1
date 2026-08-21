# Masraf Takibi Modülü — Devam Bağlamı

> Yeni bir sohbette masraf modülüne devam ederken önce bu dosyayı oku. Gereksiz proje taraması yapma; yalnızca görevle ilgili dosyaları incele.

## Kısa başlangıç talimatı

```text
Önce docs/masraf-takibi-devam-konteksti.md dosyasını oku.
Sadece masraf modülüyle ilgili dosyaları incele.
Önce kısa analiz ve etkilenecek dosya listesini ver; sonra değişiklik yap ve ilgili testleri çalıştır.
UTF-8, tenant izolasyonu, transaction, idempotency ve ters kayıt/pasifleştirme kurallarını koru.
Gereksiz refactor yapma.
```

## Proje ve amaç

- Laravel 11 + Filament 3.3 SaaS uygulaması.
- Çalışma dizini: `C:/xampp/htdocs/yalova-kamera`
- PHP: `C:/xampp/php/php.exe`
- Amaç: İşletmelerin personel, elektrik, araç vb. giderlerini çok hızlı kaydetmesi; masraf türleriyle sınırlandırması ve raporlayabilmesi.
- Modül, Muhasebe altında bir sayfa değil; Teklif Yönetimi ve Teknik Servis gibi bağımsız birinci seviye modüldür.

## Mevcut route'lar

- `/admin/masraf-takip/masraflar` — Masraf kayıtları, filtreler, özetler ve dışa aktarma.
- `/admin/masraf-takip/tanimlar/masraf-turleri` — Masraf türü ekleme, düzenleme ve pasifleştirme.
- `/admin/muhasebe/masraflar` — Eski adres; yeni bağımsız modüle yönlendirir.

## Tamamlanan özellikler

- Hızlı masraf kaydı: tarih, masraf türü, tutar, para birimi, açıklama ve not.
- Tarih, tür ve durum filtreleri.
- Genel toplam, para birimine göre özet ve tür bazlı rapor.
- `aktif` ve `iptal` durumları; iptal edilen kayıtlar raporlara dahil edilmez.
- Fiziksel silme yok; kayıt iptal edilir veya kategori pasifleştirilir.
- CSV ve Excel uyumlu CSV dışa aktarma.
- Excel çıktısında UTF-8 BOM ve `;` ayraç kullanılır.
- Dışa aktarma seçili tarih/tür/durum filtrelerine uyar ve kayıtları akış halinde okur.
- Muhasebe dışa aktarım raporunda aktif masraf toplamı ve masraf adedi kullanılır.
- Sidebar, SaaS modül tanımları, plan matrisi ve rol/yetki matrisi bağımsız modüle göre düzenlendi.

Varsayılan masraf türleri:

`Personel`, `Elektrik`, `Su`, `Doğalgaz`, `Telefon/İnternet`, `Araç`, `Kira`, `Vergi/Harç`, `Bakım/Onarım`, `Ofis`, `Pazarlama`, `Diğer`.

## Yetkiler

Yetki kodları `app/Support/MasrafTakipYetkiSablonlari.php` içindedir:

- `masraf_takip.goruntule`
- `masraf_takip.olustur`
- `masraf_takip.guncelle`
- `masraf_takip.sil`

Rol yaklaşımı:

- İşletme sahibi: tüm yetkiler.
- Yönetici: görüntüleme, oluşturma, güncelleme.
- Muhasebe personeli: görüntüleme, oluşturma, güncelleme.
- Görüntüleyici: yalnızca görüntüleme.

Plan matrisi ve yetki cache'i değişirse ilgili SaaS seeder'larını çalıştırıp `artisan cache:clear` yap.

## Veri ve iş kuralları

- Tablolar: `masraf_kategorileri`, `masraflar`.
- Modeller bilinçli olarak `App\\Models\\Muhasebe` altında tutulur; rapor çekirdeğini bozacak taşıma/refactor yapma.
- Her sorgu ve yazma işleminde `firma_id` tenant sınırı zorunludur.
- Başka firmaya ait kategori ID'si kullanılamaz.
- Masraf oluşturma/güncelleme/iptal işlemleri servis üzerinden yapılmalıdır.
- Yazma işlemlerinde transaction ve gerekli yerlerde `lockForUpdate` korunmalıdır.
- Masraf kaydında idempotency anahtarı ve firma bazlı benzersizlik korunmalıdır.
- Tutarlar decimal/BCMath kurallarıyla işlenmelidir; float kullanma.
- Geçmiş kayıtların anlamı bozulmamalı; fiziksel silme ekleme.
- Kategori silinmez; düzenlenir veya pasifleştirilir.
- Türkçe karakterler ve tüm dosya/çıktılar UTF-8 olmalıdır.

## Ana dosyalar

### Filament ve görünüm

- `app/Filament/Clusters/MasrafTakip.php`
- `app/Filament/Clusters/MasrafTakip/Pages/MasrafTakibiSayfasi.php`
- `app/Filament/Clusters/MasrafTakip/Pages/MasrafKategorileriSayfasi.php`
- `app/Filament/Clusters/MasrafTakip/Kaynaklar/MasrafTakipFilamentErisimYardimcisi.php`
- `app/Filament/Clusters/MasrafTakip/Kaynaklar/MasrafTakipSayfaErisimleri.php`
- `resources/views/filament/clusters/masraf-takip/pages/masraf-takibi.blade.php`
- `resources/views/filament/clusters/masraf-takip/pages/masraf-kategorileri.blade.php`

### Servisler, modeller ve yetki

- `app/Muhasebe/Servisler/MasrafKayitServisi.php`
- `app/Muhasebe/Servisler/MasrafKategoriServisi.php`
- `app/Models/Muhasebe/Masraf.php`
- `app/Models/Muhasebe/MasrafKategorisi.php`
- `app/Support/MasrafTakipYetkiSablonlari.php`
- `app/Services/MuhasebeDisaAktarimServisi.php`

### SaaS/sidebar/panel

- `app/Services/SidebarService.php`
- `resources/views/filament/components/custom-sidebar.blade.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `database/seeders/SaasModulesSeeder.php`
- `database/seeders/SaasPermissionsSeeder.php`
- `database/seeders/SaasRolePermissionMatrixSeeder.php`
- `database/seeders/SaasPlanModuleMatrixSeeder.php`
- `routes/web.php`

### Migration ve testler

- `database/migrations/2026_07_20_160000_create_masraf_takip_tables.php`
- `tests/Feature/Muhasebe/MasrafTakibiTest.php`
- `tests/Feature/MasrafTakip/MasrafTakipModulErisimTest.php`
- `tests/Feature/Muhasebe/GelirGiderRaporuTest.php`
- `tests/Feature/Muhasebe/MuhasebeDashboardUxTest.php`
- `tests/Feature/Muhasebe/FaturaSayfaErisimTest.php`

## Doğrulama

Hızlı ve ilgili regresyon testi:

```powershell
& C:/xampp/php/php.exe artisan test --filter='MasrafTakibiTest|MasrafTakipModulErisimTest|GelirGiderRaporuTest|MuhasebeDashboardUxTest|FaturaSayfaErisimTest'
```

Son doğrulama: **11 test, 50 assertion başarılı**.

PHP sözdizimi kontrolü için:

```powershell
& C:/xampp/php/php.exe -l app/Filament/Clusters/MasrafTakip/Pages/MasrafTakibiSayfasi.php
```

CSV indirme düğmeleri tarayıcı DOM'unda görünür ve sayfa hatasız açılır. Otomatik browser download-event doğrulaması, Livewire/araç snapshot sınırlaması nedeniyle tamamlanamadı; bu durum uygulama davranışının başarısız olduğu anlamına gelmez.

## İlgili entegrasyon raporu

Gerekirse yalnızca ilgili bölümü oku:

`docs/muhasebe-gelistirme-entegrasyon-raporu-2026-07-13.md`

Özellikle tenant izolasyonu, transaction, idempotency, ters kayıt ve test sıralaması kurallarını koru.

## Önerilen sonraki işler

1. CSV indirmeyi gerçek tarayıcıda manuel olarak son kez doğrulamak.
2. Kullanıcı isterse ödeme yöntemi veya makbuz/fatura eki eklemek; bunun için migration ve tenant güvenliği gerekir.
3. Aylık karşılaştırmalı masraf grafiği veya Masraf Takibi dashboard özeti eklemek.
4. Dışa aktarma büyürse filtre/özet mantığını ayrı bir `MasrafRaporServisi` içine almak.

Her yeni işte önce kısa analiz ve etkilenecek dosyalar ver; yalnızca istenen kapsamda değişiklik yap; ardından hedefli testleri çalıştır.
