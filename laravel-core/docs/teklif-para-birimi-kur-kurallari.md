# Teklif Para Birimi ve Kur Dönüşüm Kuralları

Bu not, `Teklif Oluştur` ve `Teklif Düzenle` ekranlarında uygulanacak para birimi davranışını yarıda kalma riskine karşı sabitlemek için hazırlanmıştır.

## Uygulama Durumu

- `2026-04-30`: İlk uygulama paketi işlendi.
- Teklif formunda kur seti alanları, üst kur bilgi notu ve `Kurları yenile` akışı eklendi.
- Stok fiyatından seçili teklif para birimine dönüşüm, `Özel Fiyat` rozeti ve `Sıfırla` aksiyonu eklendi.
- Satır altı kırmızı uyarı metni, tam ekran `yeniden hesaplanıyor...` katmanı ve yeni metadata alanları migration ile açıldı.
- Görsel ve canlı tarayıcı doğrulaması ayrıca yapılmalı.

## Kur Kaynağı

- Sistemde kayıtlı anlık kur altyapısı kullanılacak.
- Sayfa ilk açıldığında tek bir kur seti alınacak.
- Bu kur seti sayfa boyunca sabit kalacak.
- Kullanıcı `Kurları yenile` derse yeni kur seti alınacak.

## Üst Bilgi Alanı

- Teklif para birimi gösterilecek.
- Kur zamanı gösterilecek.
- Sadece seçili teklif para birimine ait özet kur bilgisi gösterilecek.
- Kur tipi / tutarı / kaynak adı gösterilecek.
- `TRY` için kur tutarı boş kalacak.
- `Kurları yenile` butonu bulunacak.
- `Kurları yenile` sonrası başarı mesajı gösterilmeyecek, sadece kur zamanı ve bilgiler güncellenecek.

## Stoktan Gelen Fiyat Mantığı

- Esas alınacak fiyat stok kartındaki satış fiyatı olacak.
- Stok fiyatı hangi para birimindeyse önce o baz alınacak.
- Sonra seçili teklif para birimine sistem kuruyla çevrilecek.
- Ekranda sadece seçili teklif para birimi gösterilecek.
- Satır başlangıçta `otomatik kur bağlı` modunda olacak.
- KDV oranı varsayılan `0` olacak.

## Stokta Fiyat / Para Birimi Yoksa

- Satır `0` fiyatla eklenecek.
- Satır altında kırmızı küçük uyarı metni gösterilecek:
  - `Bu stok için satış fiyatı veya para birimi tanımlı değil. İsterseniz manuel fiyat girerek özel fiyat modunda devam edebilirsiniz.`
- Kullanıcı manuel fiyat girebilecek.
- Manuel fiyat girildiğinde satır `Özel Fiyat` moduna geçecek.

## Özel Fiyat Mantığı

- Rozet metni: `Özel Fiyat`
- Geri dönüş aksiyonu: `Sıfırla`
- Kullanıcıya görünmeden arka planda satırın kaynak para birimi saklanacak.
- Bu kaynak bilgi, manuel fiyat girildiği andaki teklif para birimi olacak.

## Para Birimi Değişince

- Otomatik kur bağlı satırlar yeni para birimine yeniden çevrilecek.
- Özel fiyatlı satırlar saklanan kaynak para biriminden yeni para birimine çevrilecek.
- Problemli `0` fiyatlı satırlar kullanıcı manuel giriş yapana kadar `0` kalabilecek.
- Miktar değişmeyecek.
- İskonto oranı değişmeyecek.
- KDV oranı değişmeyecek.
- Hangi iskonto tipi seçildiyse o mantık korunacak.
- KDV oranı korunacak, tutarlar yeni para birimine göre yeniden hesaplanacak.

## Kurlar Yenilenince

- Kurla hesaplanan işlemler güncellenecek.
- Toplamlar yeniden hesaplanacak.
- Manuel özel fiyatlar kur yenilemede sabit kalacak.

## Sıfırla Davranışı

- `Sıfırla` tıklanınca stok kartındaki güncel satış fiyatı alınacak.
- Gerekirse mevcut kur setiyle seçili teklif para birimine çevrilecek.
- Sadece fiyat sıfırlanacak.
- Diğer tüm kullanıcı girişleri aynen korunacak.

## Hesaplama Hassasiyeti

- Hesaplamalarda 8 ondalık hassasiyet kullanılacak.
- Toplam gösterimlerinde 2 ondalığa yuvarlanacak.

## Yüklenme Davranışı

- Para / kur ile ilgili tüm satır hesapları yapılırken tam ekran katman gösterilecek.
- Sayfa içeriği buğulanacak.
- Ortada şu metin yer alacak:
  - `yeniden hesaplanıyor...`

## Kur Alınamazsa

- Form yine açılacak.
- Para alanları `0` kalacak.
- Üstte kırmızı uyarı gösterilecek:
  - `Kur bilgileri alınamadı. Fiyatlar 0 olarak gösteriliyor. Kurları yenileyerek tekrar deneyebilirsiniz.`

## Kapsam

- Bu davranış `Teklif Oluştur` ve `Teklif Düzenle` ekranlarında birebir aynı çalışacak.
