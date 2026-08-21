# Restoran E-Fatura / E-Arşiv Entegrasyon Planı

Tarih: 2026-06-02

Bu plan restoran modülünün mevcut muhasebe çekirdeğiyle resmi belge tarafında nasıl birleşeceğini tanımlar. Amaç adisyon, tahsilat, stok/reçete ve cari hareketlerini bozmadan e-fatura/e-arşiv akışını kontrollü eklemektir.

## Mevcut Durum

Muhasebe tarafında `Fatura` modeli şu alanları destekliyor:

- `kaynak_tipi`
- `e_belge_tipi`
- `islem_tipi`
- `islem_no`
- `cari_id`
- `para_birimi`
- `ara_toplam`, `kdv_toplam`, `genel_toplam`, `odenecek_tutar`

Restoran tarafında adisyon tahsilatı ayrı `RestoranAdisyonTahsilati` kayıtlarıyla izleniyor. Kısmi ödeme, çoklu ödeme, tahsilat iptali, finans ters kaydı ve stok ters kaydı çalışıyor.

## Önerilen Resmi Belge Kuralı

Restoran adisyonu doğrudan resmi belge değildir. Resmi belge üretimi ayrı ve kontrollü bir aksiyon olmalıdır:

1. Adisyon kapanır.
2. Tahsilatlar aktif ve mutabık hale gelir.
3. Kullanıcı belge tipini seçer: `e_arsiv`, `e_fatura`, `fatura`.
4. Cari seçilir veya perakende cari kullanılır.
5. Sistem adisyon kalemlerinden fatura kalemlerini üretir.
6. Fatura `kaynak_tipi = restoran_adisyon`, `islem_tipi = restoran_satis`, `islem_no = adisyon_id` ile kaydedilir.
7. Fatura onaylandığında muhasebe çekirdeği cari/stok hareketlerini üretir.

## Kritik Karar

Restoran kapanışında stok hareketi zaten reçete üzerinden düşüyor. Fatura onayında stok hareketi de oluşursa çift stok düşümü riski doğar.

Bu yüzden restoran kaynaklı faturalarda iki seçenekten biri seçilmelidir:

- Seçenek A: Fatura kalemleri hizmet kalemi olarak oluşturulur, stok/reçete hareketi restoran kapanışından gelir.
- Seçenek B: Restoran kapanışında stok hareketi oluşturulmaz, stok hareketi fatura onayından gelir.

Bu proje için önerilen güvenli yol: Seçenek A. Çünkü restoran satışında reçete/hammadde düşümü adisyon kapanışına bağlıdır; fatura ise resmi belge ve cari borç/alacak tarafını temsil etmelidir.

## MVP Akış

İlk sürümde:

- Kapalı adisyondan taslak/bekleyen fatura oluştur.
- Cari zorunlu değilse firma ayarındaki perakende cari kullan.
- Fatura kalemlerini hizmet kalemi olarak üret.
- KDV, indirim, ikram ve servis ücretini adisyon toplamlarıyla birebir taşı.
- Adisyon üzerinde `fatura_id` bağlantısı eklenene kadar `Fatura.kaynak_tipi/islem_tipi/islem_no` alanlarıyla ilişki kur.
- Aynı adisyon için ikinci fatura oluşturmayı engelle.
- Tahsilatı iptal edilmiş veya ödeme durumuna dönmüş adisyonda fatura oluşturmayı engelle.
- Tahsilat iptal edilip adisyon tekrar açık/ödemede duruma dönerse restoran kaynaklı bekleyen faturayı silmeden `iptal` durumuna al.
- Fatura oluşturma işlemini `restoran_adisyon.fatura` yetkisine bağla; sadece tahsilat yetkisi yeterli olmamalı.

## İleri Sürüm

Sonraki sürümde:

- E-fatura/e-arşiv sağlayıcı adaptörü.
- Belge gönderim durumu.
- Belge UUID/ETTN takibi.
- İptal/itiraz/iade senaryoları.
- Masa/adisyon fişi ile resmi belge ayrımı.
- Gün sonu kapanışından toplu belge üretme.
- Yemeksepeti/Trendyol/Getir gibi kaynaklara göre belge tipi ve müşteri bilgisi eşleme.

## Kabul Kriterleri

- Firma A, Firma B adisyonundan fatura oluşturamaz.
- Açık, ödemede veya iptal adisyondan resmi belge oluşturulamaz.
- Aynı adisyon için ikinci aktif fatura oluşturulamaz.
- Fatura toplamı adisyon genel toplamıyla kuruş farkı olmadan eşleşir.
- Restoran kaynaklı faturada stok ikinci kez düşmez.
- Tahsilat iptal edilirse bağlı bekleyen fatura silinmez, `iptal` durumuna alınır ve açık tutarı sıfırlanır.
- E-belge gönderim hataları adisyon/tahsilat kayıtlarını geri almaz, sadece belge durumunda izlenir.
