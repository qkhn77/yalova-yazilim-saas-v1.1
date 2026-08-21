# Admin Datatable Standardı

Bu belge bütün yönetim listelerinin görsel, davranışsal ve performans sözleşmesidir.

## Tek mimari

- Standart listelerde Filament Table ve Livewire kullanılmaya devam edilir.
- Ortak PHP yapılandırması `AppServiceProvider` içindeki `Table::configureUsing(...)` katmanıdır.
- Ortak değerler `App\Support\TablePaginationDefaults` sınıfında tutulur.
- Görsel adapter `resources/css/filament/cork-admin-tables.css` dosyasıdır.
- Özel Blade/Livewire listeleri sunucu taraflı sorgu/pagination kullanmalı ve mevcut CORK tablo sınıflarıyla aynı görsel sözleşmeye bağlanmalıdır.
- Tablo sınıflarına aynı ayarları kopyalamak yerine merkezi katman genişletilir.

## Performans kapısı

Ortak bir özellik aşağıdaki koşulların tamamını sağlamadan genel kullanıma alınamaz:

1. Normal sayfa açılışında ek veritabanı sorgusu üretmez.
2. Satır başına sorgu veya N+1 sorgu üretmez.
3. Bütün kayıtları yalnız CSS/JavaScript ile gizlemek için tarayıcıya yüklemez.
4. Arama, filtreleme, sıralama ve pagination tenant kapsamlı sunucu sorgusunda kalır.
5. Tablo başına ayrı JavaScript dosyası ya da yinelenen Alpine bileşeni oluşturmaz.
6. Kullanıcı tercihleri küçük session/localStorage verisi olarak tutulur; her renderda veritabanından okunmaz.
7. `1000` ve `Hepsi` yalnız kullanıcı seçtiğinde sorgulanır.
8. CSV/Excel, toplam ve rapor sorguları normal renderda değil yalnız ilgili aksiyonda çalışır.
9. SPA, dark mode, üç admin layout, mobil taşma ve permission/tenant scope korunur.

## Genel çekirdek özellikler

- Teknik Servis ölçülerinde sabit kompakt satır yapısı
- 10 / 20 / 50 / 100 / 1000 / Hepsi sayfa boyutu
- Kullanıcı bazlı sütun görünürlüğü
- Tercihleri sıfırlama
- CSS tabanlı sabit başlık ve işlem sütunu
- Uzun metin kısaltma ve tooltip
- Kontrollü yatay taşma
- Filament filtre göstergeleri ve tek tuşla temizleme
- Hover/seçim vurgusu
- Mobil uyum, loading ve empty-state standardı
- Büyük veri seçimi uyarısı

## Prototip ve yaygınlaştırma

Yeni ortak özellik önce tek bir temsilci tabloda ölçülür. Şu anki temsilci:

`/admin/muhasebe/cari-yonetimi/cariler`

Cariler prototipi 16 Ağustos 2026 tarihinde kullanıcı tarafından onaylanarak ortak provider/CSS katmanına alınmıştır. Yeni ortak özellikler yine önce tek bir temsilci tabloda ölçülür; en az Cariler, Teknik Servis, bir Personel/Restoran tablosu, relation manager, modal, mobil ve dark-mode kontrol edilmeden yaygınlaştırılmaz.

## Kapsam dışı yüzeyler

- Print/PDF/fiş ve belge önizlemeleri
- Küçük ilişki/seçim tabloları
- Dashboard KPI veya özet widget'ları
- POS, barkod okuyucu ve klavye akışı özel operasyon ekranları

Bu yüzeyler ancak ayrıca değerlendirilip gerçek kayıt listesi oldukları doğrulanırsa standarda bağlanır.
