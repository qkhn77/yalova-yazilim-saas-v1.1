# Sekreter Modülü — Ön Çalışma ve Sisteme Uyarlama Planı

## 1. Amaç

Bu doküman, mevcut Laravel + Filament SaaS yapısına Sekreter / Ajanda modülünün en az müdahale ile eklenmesi için hazırlanmıştır.

Bu aşamada uygulama kodu, migration veya seed dosyası değiştirilmemiştir. Dokümandaki kararlar geliştirme başlamadan önce referans olarak kullanılacaktır.

## 2. Mevcut mimari özeti

- Laravel 11.48.0
- Filament 3.3
- PHP 8.2+
- Uygulama kökü: `laravel-core`
- Firma/tenant bağlamı: `TenantContextService`
- Firma bazlı Eloquent kapsamı: `HasFirmaTenantScope` / `FirmaIdTenantScope`
- Modül erişimi: `ModulErisimService`
- Menü görünürlüğü: `SidebarService`
- Filament kaynak erişimi: `HasTenantVisibility`
- Yetki kontrolü: `YetkiService`, Policy ve mevcut Gate kayıtları
- Uygulama içi bildirim modeli: `App\Models\Iletisim\KullaniciBildirimi`

Yeni modül bu yapıların yanına eklenecek; yeni tenant, yeni modül erişim servisi veya paralel yetkilendirme sistemi oluşturulmayacaktır.

## 3. Modül kodu ve yetki standardı

Mevcut modül seeder standardına göre yeni kayıt:

```text
modül adı: Sekreter
modül kodu: sekreter
```

Önerilen yetkiler:

```text
sekreter.goruntule
sekreter.olustur
sekreter.guncelle
sekreter.sil
```

Yetkilerin `modul_kodu` değeri `sekreter` olmalıdır. Firma sahibi/yöneticisi ve rol matrisi mevcut `SaasRolePermissionMatrixSeeder` yaklaşımına göre genişletilmelidir.

Sekreter modülü kapatıldığında veri silinmeyecek; yalnızca erişim, navigation ve işlemler engellenecektir.

## 4. Entegrasyon modülü eşleştirmesi

| İşlev | Mevcut sistemdeki karşılığı | Kontrol |
|---|---|---|
| Cari | `App\Models\Muhasebe\Cari` / `cariler` | `muhasebe` aktifliği ve cari yetkisi |
| Teknik servis | `TeknikServisKaydi` | `teknik_servis` aktifliği |
| Garanti | `garanti_bitis_tarihi` | Teknik Servis aktifse sorgulanır |
| Bakım | `bakim_tarihi`, `TeknikServisHatirlatma` | Teknik Servis aktifse sorgulanır |
| Çek | `App\Models\Muhasebe\Cek` | `muhasebe` aktifliği |
| Senet | `App\Models\Muhasebe\Senet` | `muhasebe` aktifliği |
| Veresiye/vade | `AlacakPlanTaksiti` | `muhasebe` aktifliği |
| Personel | `App\Models\Personel\Personel` | `personel_takip` aktifliği |
| Bildirim | `KullaniciBildirimi` | Kullanıcı ve firma bazlı |

Önemli: `App\Models\Cari` isimli boş model kullanılmayacaktır. Doğru Cari modeli `App\Models\Muhasebe\Cari` modelidir.

## 5. Yeni veri yapısı

Mevcut tablolar içinde görev, randevu, sekreter notu veya genel hatırlatma karşılığı tespit edilmedi. V1 için önerilen minimum yapı:

### `sekreter_gorevleri`

- `id`
- `firma_id`
- `olusturan_kullanici_id`
- `atanan_kullanici_id` nullable
- `atanan_personel_id` nullable
- `cari_id` nullable
- `baslik`
- `aciklama` nullable
- `tarih`
- `saat` nullable
- `durum`
- `oncelik`
- `hatirlatma_tipi`
- `tekrar_tipi`
- timestamps
- tercihen soft delete

### `sekreter_randevulari`

- `id`
- `firma_id`
- `olusturan_kullanici_id`
- `cari_id` nullable
- `baslik`
- `baslangic_tarihi`, `baslangic_saati`
- `bitis_tarihi`, `bitis_saati`
- `aciklama` nullable
- `hatirlatma_tipi`
- `tekrar_tipi`
- timestamps
- tercihen soft delete

### `sekreter_notlari`

- `id`
- `firma_id`
- `kullanici_id`
- `cari_id` nullable
- `baslik`
- `icerik`
- `etiket` nullable
- `sabit_mi`
- timestamps
- tercihen soft delete

### `sekreter_hatirlatmalari`

Hatırlatmayı üç ayrı tabloda tekrar etmek yerine polymorphic ilişki önerilir:

- `id`
- `firma_id`
- `hatirlanabilir_type`
- `hatirlanabilir_id`
- `hatirlatma_tipi`
- `hatirlatma_zamani`
- `gonderildi_at` nullable
- `okundu_at` nullable
- timestamps

İlk versiyonda tekrar kuralları sınırlı enum değerlerle tutulmalıdır:

```text
yok, gunluk, haftalik, aylik, yillik
```

Karmaşık cron kuralı veya gelişmiş recurrence sistemi eklenmemelidir.

## 6. Tenant ve güvenlik tasarımı

Sekreter modelleri `HasFirmaTenantScope` kullanmalıdır. Böylece aktif firma kapsamı otomatik uygulanır.

Buna ek olarak:

- Firma ID istemciden güvenilerek alınmamalıdır.
- Oluşturma sırasında aktif firma bağlamı kullanılmalıdır.
- Cari, personel ve entegrasyon kayıtları aynı firmaya ait olmalıdır.
- Sekreter kapalıysa doğrudan URL erişimi de engellenmelidir.
- Salt okunur firma modülü durumunda oluşturma/güncelleme/silme işlemleri kapatılmalıdır.
- Filament `canAccess`, `canViewAny`, `canCreate`, `canEdit`, `canDelete` kontrolleri mevcut trait/service kalıbına bağlanmalıdır.

## 7. Cari uyarlaması

Cari, sistemde Muhasebe modülü altında çalışmaktadır. Bu nedenle Sekreter formunda Cari alanı şu koşullarda açılmalıdır:

```text
muhasebe aktif + cari.goruntule yetkisi var
```

Muhasebe pasif olduğunda:

- Cari alanı görünmeyecek.
- Cari seçeneği sorgulanmayacak.
- Cari relation eager-load edilmeyecek.
- Cari detayında Sekreter sekmesi gösterilmeyecek.

Cari detayına eklenecek Sekreter görünümü mevcut Cari kaynağını bozmayacak şekilde Relation Manager veya uygun bir sekme olarak düşünülmelidir.

## 8. Dashboard ve entegrasyon sorguları

Dashboard sorguları modül durumuna göre bağımsız çalışmalıdır:

```text
Sekreter aktif değilse hiçbir Sekreter sorgusu çalışmaz.

Teknik Servis pasifse TeknikServisKaydi sorgusu çalışmaz.

Muhasebe pasifse Cek, Senet ve AlacakPlanTaksiti sorguları çalışmaz.

Personel pasifse Personel sorgusu çalışmaz.
```

Bu kontroller yalnızca Blade veya Filament `visible()` ile yapılmamalıdır. Sorgu oluşturan servis/widget metotlarının başında uygulanmalıdır.

Önerilen servis sınırı:

```text
SekreterDashboardServisi
SekreterAjandaServisi
SekreterHatirlatmaServisi
```

Ancak bu servisler ancak gerçek tekrar veya ortak sorgu karmaşıklığı oluştuğunda eklenmelidir. İlk aşamada gereksiz abstraction oluşturulmamalıdır.

## 9. Filament uyarlaması

Önerilen yapı:

```text
App\Filament\Clusters\Sekreter
├── Sekreter.php
├── Pages\GenelBakisSayfasi.php
├── Pages\AjandaSayfasi.php
├── Resources\GorevKaynagi.php
├── Resources\NotKaynagi.php
└── ...
```

Randevu ayrı navigation öğesi olmayacak; Ajanda sayfasındaki modal/action üzerinden yönetilecektir.

Navigation grubu:

```text
Sekreter
```

Mevcut AdminPanelProvider kayıt düzeni ve mevcut sidebar cache kapsamı incelenerek minimum değişiklik yapılmalıdır.

## 10. Takvim kararı

Harici Google/Outlook entegrasyonu kapsam dışıdır. V1 için Ajanda sayfasında:

- Ay görünümü varsayılan
- Gün görünümü
- Hafta görünümü
- Görev ve randevu kayıtları
- Hatırlatma işaretleri
- Kayıta tıklayınca ilgili Filament sayfası/modal

kapsanmalıdır.

Takvim için yeni ağır bir paket eklenmesi gerekip gerekmediği, mevcut frontend asset yapısı incelendikten sonra karar verilmelidir. Paket eklemeden Filament/Livewire tabanlı sade görünüm tercih edilmelidir.

## 11. Hızlı Ekle

Global bir action veya Sekreter başlık action’ı olarak uygulanabilir:

```text
+ Hızlı Ekle
├── Görev
├── Randevu
└── Not
```

Form, aktif olmayan modüllere ait alanları hiç oluşturmamalıdır. Sadece `visible()` ile gizlemek yeterli değildir; alan seçeneklerini hazırlayan sorgular da koşullu olmalıdır.

## 12. Bildirim yaklaşımı

Yeni bir notification tablosu oluşturulmayacaktır. Mevcut `KullaniciBildirimi` kullanılacaktır.

Bildirim üretimi için:

- Kullanıcı bazlı hedef
- Firma bazlı tenant kontrolü
- Kaynak türü ve kaynak ID
- Aksiyon URL’si
- Okundu tarihi

kullanılabilir.

İlk versiyonda yalnızca uygulama içi bildirim yeterlidir. Queue, e-posta, SMS veya WhatsApp eklenmeyecektir.

## 13. Timeline yaklaşımı

Mevcut `SistemOlayi` genel kullanıcı timeline’ı için yeterince zengin değildir. İlk versiyonda ayrı activity paketi eklenmemelidir.

Öneri:

- Görev, randevu ve notların `created_at` kayıtlarından basit timeline üretmek.
- Cari ile ilişkili kayıtları `cari_id` üzerinden birleştirmek.
- Mevcut SistemOlayi kayıtlarını yalnızca uyumlu olaylar varsa ek kaynak olarak kullanmak.
- İleride ihtiyaç kesinleşirse ayrı timeline tablosunu ikinci aşamaya bırakmak.

## 14. Geliştirme öncesi kabul kriterleri

Kodlamaya başlamadan önce aşağıdaki kararlar kesin olmalıdır:

1. Randevu, Ajanda içinde modal mı yoksa ayrı hidden resource mu olacak?
2. Hatırlatma zamanı yalnızca kayıt alanı mı olacak, yoksa ayrı polymorphic kayıt mı tutulacak?
3. Atanan personel ile atanan kullanıcı aynı anda tutulabilecek mi?
4. Sekreter kayıtlarını tüm firma kullanıcıları mı, yoksa yalnızca oluşturan kullanıcı mı görecek?
5. Tekrarlayan kayıtlar fiziksel kopya mı oluşturacak, yoksa sanal tekrar mı üretilecek?
6. Mevcut AdminPanelProvider navigation kayıt düzeni yeni Cluster için nasıl genişletilecek?

Önerilen varsayılanlar:

- Atanan kullanıcı zorunlu değildir.
- Atanan personel yalnızca Personel Takip aktifse görünür.
- Firma içindeki yetkili kullanıcılar kayıtları görebilir.
- Tekrarlar V1'de kayıt oluşturma zamanında basit şekilde ele alınır.
- Randevu Ajanda içinde modal/action olarak yönetilir.
- Hatırlatma ayrı polymorphic tablo ile tutulur.

## 15. Uygulama sırası

1. Modül ve permission seed tanımları
2. Sekreter modelleri ve güvenli migration’lar
3. Policy/Filament erişim kalıbı
4. Görev ve not kaynakları
5. Ajanda ve randevu işlemleri
6. Dashboard
7. Hatırlatma ve mevcut bildirim entegrasyonu
8. Cari entegrasyonu
9. Teknik Servis ve Muhasebe sorguları
10. Personel alanı
11. Hızlı Ekle
12. Testler ve performans doğrulaması

Her aşamada değişen dosyalar ayrıca listelenmeli ve mevcut modüllerde regresyon kontrol edilmelidir.

## 16. Sonuç

Mevcut sistem Sekreter modülünü eklemek için gerekli tenant, modül erişimi, yetki, Filament ve bildirim altyapısına sahiptir.

En düşük riskli yaklaşım:

- Yeni modülü mevcut `ModulErisimService` ve `YetkiService` üzerine oturtmak.
- Yeni Sekreter verilerini firma kapsamlı tutmak.
- Cari, Teknik Servis, Muhasebe ve Personel verilerini kopyalamamak.
- Entegrasyon sorgularını modül aktiflik kontrolünden sonra çalıştırmak.
- Mevcut dosyalarda yalnızca navigation, seeder ve ilişki entegrasyonu için gerekli minimum değişiklikleri yapmak.

