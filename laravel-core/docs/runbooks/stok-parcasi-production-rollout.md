# Stok parçası üretim geçişi

Bu kontrol listesi parti kimliği, fiziksel stok parçası, bölme/birleştirme ve ölçü bakiyesi değişikliklerinin üretime güvenli alınması içindir.

## Geçiş öncesi

1. Uygulama ve veritabanının zaman damgalı yedeğini alın; geri yükleme işlemini doğrulayın.
2. Kuyruk çalışanlarını durdurun ve kısa süreli bakım moduna geçin.
3. Aynı firmada aynı parti numarasının birden fazla stok kartında kullanılmadığını kontrol edin:

```sql
SELECT firma_id, parti_no, COUNT(DISTINCT stok_id) AS stok_sayisi
FROM stok_partileri
GROUP BY firma_id, parti_no
HAVING COUNT(DISTINCT stok_id) > 1;
```

Sonuç boş değilse migrasyon bilinçli olarak durur. Çakışan kayıtlar stok hareketleri incelenerek düzeltilmeden geçiş yapılmamalıdır.

4. Ana parti–alt parça bağlantılarında firma ve stok uyuşmazlığı olmadığını doğrulayın.
5. Önce üretim verisinin anonimleştirilmiş kopyasında `php artisan migrate --pretend` ve tam test takımını çalıştırın.

## Yayına alma

```bash
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan up
```

Migrasyon, mevcut parti kayıtlarını `stok_parti_kimlikleri` tablosuna taşır. Aynı parti numarası aynı stok kartında farklı depolarda kullanılabilir; başka stok kartına bağlanamaz. Alt parçalı ana parti silme işlemi veritabanı yabancı anahtarıyla da engellenir.

## Yayın sonrası doğrulama

- Parti raporunda gerçekleşen satış, gerçekleşen maliyet ve gerçekleşen kâr toplamlarını örnek faturalarla karşılaştırın.
- Aynı stok ve farklı depoda aynı parti numarası oluşturulabildiğini; farklı stokta reddedildiğini kontrol edin.
- İki hareket görmemiş fiziksel parçayı birleştirin; yeni kod, barkod, maliyet ve ölçü bakiyesini doğrulayın.
- Birleşimi geri alın; kaynak bakiyelerin geri geldiğini doğrulayın.
- Birleşik parçaya satış yaptıktan sonra geri almanın reddedildiğini kontrol edin.
- Parça bölme, transfer, kısmi satış, satış iptali, etiket ve CSV akışlarını kontrol edin.
- Uygulama ve kuyruk loglarında SQL bütünlük veya tenant hatası bulunmadığını doğrulayın.

## Geri dönüş

Hareket alınmış üretim verisinde migrasyonu körlemesine geri çevirmeyin. Uygulamayı bakım moduna alın, dağıtımı önceki sürüme döndürün ve geçiş öncesi veritabanı yedeğini geri yükleyin. Yalnız hiç iş kaydı alınmamış doğrulanmış ortamlarda `php artisan migrate:rollback --step=1` kullanılabilir.

Fiziksel parça işlemlerinde kullanıcı düzeyindeki geri alma yalnız hareket veya bölme görmemiş birleşimler/dönüşümler içindir; muhasebe hareketlerini silmez veya geçmiş maliyet anlık görüntülerini değiştirmez.
