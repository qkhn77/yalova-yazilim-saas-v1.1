# Restoran Canlı Ortam Cache ve Warmup Planı

Tarih: 2026-06-02

Bu plan restoran modülünün canlı ortamda hızlı ve kontrollü çalışması için uygulanacak sırayı tanımlar.

## Yayın Öncesi

1. Kod deploy edilir.
2. `php artisan migrate --force` çalıştırılır.
3. `php artisan config:cache` çalıştırılır.
4. `php artisan route:cache` çalıştırılır.
5. `php artisan view:cache` çalıştırılır.
6. PHP-FPM/Apache OPcache resetlenir.

XAMPP/Apache ortamında OPcache reset için servis yeniden başlatma en net yoldur.

## Restoran Warmup

Deploy sonrası:

```bash
php artisan performans:warmup --firma-id=FIRMA_ID --timeout=25 --only=restoran
```

Bu komut artık şu restoran ekranlarını da ısıtır:

- Restoran cluster
- Masa ekranı
- Mutfak ekranı
- Paket servis ekranı
- Genel restoran raporları
- Gün sonu mutabakatı
- Adisyonlar
- Masalar
- Menü ürünleri
- Reçeteler
- QR menü public endpointi
- QR masa menüsü
- QR aktif adisyon endpointi

## Günlük Operasyon

Restoran canlı kullanımında günlük takip:

- Sabah ilk açılışta `performans:warmup --only=restoran`
- Yoğun saat öncesi masa, mutfak ve paket ekranlarının manuel kontrolü
- Masa ekranında şube/salon/durum filtreleri, açık adisyon toplamı ve doluluk oranı kontrolü
- Mutfak ekranında aktif kolon görünümü ve geciken kalem sayısı kontrolü
- Paket servis ekranında sipariş kanalı, paket durumu, geciken teslimat ve aktif tutar kontrolü
- Gün sonunda `Restoran > Gün Sonu` ekranında mutabakat ve kapanış kaydı
- Fark varsa açıklama zorunlu şekilde kayıt

## İzlenecek Metrikler

- Masa ekranı ilk yüklenme süresi
- Mutfak ekranı ilk yüklenme süresi
- Paket servis ekranı ilk yüklenme süresi
- QR menü JSON cevap süresi
- Tahsilat kapanış süresi
- Gün sonu mutabakat süresi
- Negatif stok engelleme olayları
- Tahsilat iptal ve ters kayıt sayısı
- Geciken mutfak kalemi ve geciken paket teslimat sayısı

## Risk Notları

- OPcache resetlenmeden yeni servis kodu eski haliyle çalışabilir.
- Route cache sonrası yeni route eklenmişse cache mutlaka yeniden üretilmelidir.
- Gün sonu kapanışı muhasebe hareketleriyle aynı gün içinde kontrol edilmelidir.
- QR endpointleri public olduğu için throttle değerleri düşürülmemelidir.
