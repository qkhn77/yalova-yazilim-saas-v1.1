# Restoran Yetki ve Tenant Denetim Raporu

Tarih: 2026-06-02

Bu rapor Laravel 11 + Filament 3 + tek veritabanlı multi tenant SaaS yapısındaki restoran modülünün mevcut güvenlik, yetki ve firma izolasyonu durumunu özetler. Amaç restoran modülünü büyütürken muhasebe, personel takip ve teknik servis tarafındaki mevcut çalışmayı bozmadan ilerlemek.

## 1. Mimari Durum

Sistem firmaları tek veritabanında tutar ve tenant ayrımı `firma_id` üzerinden yapılır. Aktif firma bağlamı `TenantContextService` ile yönetilir. Restoran tarafındaki temel modeller `HasFirmaTenantScope` kullanır ve normal sorgularda aktif firma dışındaki kayıtları gizler.

Restoran ana kayıtları:

- `RestoranSalonu`
- `RestoranMasasi`
- `RestoranAdisyonu`
- `RestoranAdisyonKalemi`
- `RestoranAdisyonTahsilati`
- `RestoranMenuKategorisi`
- `RestoranMenuUrunu`
- `RestoranRecetesi`
- `RestoranReceteKalemi`

Operasyon servisleri ilişkili kayıtları çoğunlukla `withoutGlobalScope(FirmaIdTenantScope::class)` ile açıp hemen ardından açık `where('firma_id', ...)` filtresi uygular. Bu desen QR menü gibi public akışlarda da önemlidir; scope'a güvenmek yerine URL/slug üzerinden gelen veriler firma ile tekrar doğrulanır.

Panel veya iç servis çağrılarında aktif firma bağlamı ayrıca korunur. Masa operasyonu, sipariş kalemi ekleme, mutfak durumu, paket servis, tahsilat ve restoran fatura servisleri aktif firma varsa başka firmaya ait model üzerinde işlem yapmaz. CLI/background veya public QR gibi aktif firma bağlamı olmayan akışlarda kayıt kendi `firma_id` değeriyle doğrulanmaya devam eder.

## 2. Yetki Matrisi

Restoran izinleri `SaasPermissionsSeeder`, rol dağılımı ise `SaasRolePermissionMatrixSeeder` üzerinden yönetilir.

Mevcut restoran izinleri:

- `restoran_masa.goruntule`
- `restoran_masa.duzenle`
- `restoran_adisyon.goruntule`
- `restoran_adisyon.olustur`
- `restoran_adisyon.guncelle`
- `restoran_adisyon.iptal`
- `restoran_adisyon.tahsilat`
- `restoran_adisyon.fatura`
- `restoran_mutfak.goruntule`
- `restoran_mutfak.guncelle`
- `restoran_qr_menu.goruntule`
- `restoran_qr_menu.guncelle`
- `restoran_paket_servis.goruntule`
- `restoran_paket_servis.guncelle`
- `restoran_rapor.goruntule`
- `restoran_gun_sonu.goruntule`
- `restoran_ayar.guncelle`

Rol dağılımı:

- Firma sahibi ve firma yöneticisi: tüm restoran yönetim izinleri.
- Muhasebe personeli: adisyon görüntüleme, tahsilat, restoran faturası, restoran raporları ve gün sonu mutabakatı.
- Satış/operasyon personeli: masa, adisyon, mutfak ve paket servis operasyonları; gün sonu finansal mutabakat yok.
- Görüntüleyici: genel restoran görüntüleme ve rapor görüntüleme; tahsilat ve gün sonu yok.

Finansal hassasiyet nedeniyle `restoran_gun_sonu.goruntule` ve `restoran_adisyon.fatura` ayrı izin olarak ayrıldı. Bu ekran ve resmi belge işlemi sadece yönetim ve muhasebe rollerinde olmalıdır.

## 3. Kritik Tenant Kuralları

Restoran kayıt oluşturma ve ilişki seçimlerinde aşağıdaki kurallar korunmalıdır:

- Her restoran tablosunda `firma_id` zorunlu olmalı.
- Filament formlarındaki gizli `firma_id` aktif firmadan gelmeli.
- Şube, salon, masa, personel, stok, reçete, kasa, banka ve POS seçimleri aynı `firma_id` ile sınırlandırılmalı.
- Public QR menü ve QR sipariş endpointleri mutlaka firma slug/kod + masa QR kodu + aktif firma ilişkisini birlikte doğrulamalı.
- Başka firmaya ait masa, salon, personel, stok kartı, reçete veya finans hesabı adisyona bağlanamamalı.
- Servislerde `withoutGlobalScope` kullanılıyorsa aynı sorguda açık `where('firma_id', $firmaId)` bulunmalı.
- Aktif firma bağlamı varsa masa, adisyon, adisyon kalemi, tahsilat, fatura, mutfak ve paket servis işlemleri aktif firma dışı modeli reddetmeli.

## 4. Muhasebe ve Stok Entegrasyonu

Adisyon tahsilatı `RestoranTahsilatServisi` üzerinden yürür. Servis:

- Adisyonu kilitleyerek tahsilat çakışmalarını azaltır.
- Nakit, banka ve POS hesaplarını firma ve para birimi bazında doğrular.
- Finans hareketi oluşturur.
- Adisyon tam kapandıysa stok/reçete çıkışını tetikler.
- Kısmi ve çoklu ödeme kayıtlarını `restoran_adisyon_tahsilatlari` tablosunda saklar.
- Tahsilat iptalinde finans ters kaydı oluşturur.
- Kapanmış adisyonun tahsilatı iptal edilirse stok hareketlerini tersler ve adisyonu tekrar ödeme durumuna döndürür.

Gün sonu mutabakatı `RestoranGunSonuMutabakatServisi` ile restoran tahsilatlarını kasa/banka/POS muhasebe hareketleriyle karşılaştırır. İptal edilmiş tahsilatlar aktif toplamdan düşülür.

## 5. Personel Entegrasyonu

Restoran akışlarında personel bağlantıları şu alanlarda kullanılır:

- Garson: masa/adisyon sorumlusu.
- Kasiyer: tahsilatı alan kullanıcı veya personel.
- Mutfak personeli: hazırlama durumu ve performans.
- Kurye: paket servis teslimatı.

Personel seçimlerinde temel kural aynı firma ve aktif personel olmalıdır. Maaş, avans ve personel finans kayıtları restoran tahsilatı ile doğrudan karıştırılmamalı; raporlama seviyesinde ilişkilendirilmelidir.

Paket servis kurye atamasında personel aynı firmaya ait ve aktif olmalıdır. Pasif personel veya başka firmaya ait personel kurye olarak atanamaz. Mutfak ekranında hazırlayan personel bağlantısı korunurken aktif firma dışı kalem üzerinde durum değiştirilemez.

## 6. Tamamlanan Kontroller

Testlerle doğrulanan ana başlıklar:

- Restoran detay yetkileri seed ediliyor.
- Rol matrisi operasyon, muhasebe ve görüntüleyici ayrımını koruyor.
- Filament URL yapısı sabit kalıyor.
- Filament resource erişimi başka firma kaydına uygulanmıyor.
- Kısmi ve çoklu tahsilat akışları çalışıyor.
- Tahsilat iptali finans ters kaydı oluşturuyor.
- Kapanmış adisyon tahsilatı iptal edilince stok ters kaydı oluşuyor.
- Gün sonu mutabakatı restoran tahsilatı ile muhasebe hareketlerini karşılaştırıyor.
- İptal edilmiş tahsilat gün sonu aktif toplamına alınmıyor.
- QR menü ve paket servis akışları firma izolasyonuna göre test ediliyor.
- Masa operasyonu, sipariş kalemi, mutfak ve paket servis işlemleri aktif firma dışı model geldiğinde reddediliyor.
- Mutfak kuyruğu firma ve sipariş tipi filtresine göre üretiliyor; bekleme dakikası ve geciken kalem sayısı hesaplanıyor.
- Mutfak ekranı aktif kuyrukta `Yeni`, `Hazırlanıyor` ve `Hazır` kolonlarına ayrılıyor; durum grupları aktif firma sınırıyla test ediliyor.
- Masa ekranı şube, salon ve durum filtreleriyle aktif firma içinde daraltılıyor; açık adisyon toplamı ve doluluk oranı hesaplanıyor.
- Paket servis ekranı sipariş kanalı ve paket durumu filtreleriyle çalışıyor; aktif sipariş sayısı, tutar ve geciken teslimat özeti veriyor.

## 7. Yüksek Riskli Alanlar

Öncelikli izlenmesi gereken alanlar:

1. QR sipariş endpointleri: public erişim olduğu için firma/masa doğrulaması en yüksek riskli alandır.
2. Tahsilat iptali: finans ters kaydı ve stok ters kaydı aynı işlem bütünlüğünde kalmalıdır.
3. Reçete/stok düşümü: ürün satışında birden fazla reçete veya eksik stok senaryosu açık mesajla engellenmelidir.
4. Personel seçimi: başka firmaya ait personel adisyona, paket servise veya mutfak işlemine bağlanmamalıdır.
5. Rapor sorguları: scope açılan ağır rapor sorgularında mutlaka `firma_id` filtresi ve tarih aralığı olmalıdır.
6. Canlı ortam: OPcache, config/route/view cache ve warmup akışı restoran ekranları için ayrıca ölçülmelidir.

## 8. Sonraki Sertleştirme Listesi

Bir sonraki geliştirme sırası:

1. Reçeteli ürünlerde tahsilat öncesi stok yeterlilik ön kontrolü.
2. Kasiyer, garson, kurye ve mutfak personeli bazlı raporların tek ekranda netleştirilmesi.
3. Paket servis kurye ekranı, teslimat zamanı gecikme detayı ve kurye performans panosu.
4. QR siparişte oran sınırlama, tekrar gönderim ve müşteri notu uzunluk kontrolleri.
5. Gün sonu ekranında kasa/POS farkı için açıklama ve kapanış notu.
6. Canlı restoran ekranları için hedefli performans testi ve warmup rotaları.
