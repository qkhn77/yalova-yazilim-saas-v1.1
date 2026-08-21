# Servisler Modülü – Test Senaryosu (İçerik Doğrulama)

Bu senaryo, `Servisler` sayfasına eklenen kategori ve servis içeriklerinin doğru listelendiğini ve detay sayfalarının beklendiği gibi açıldığını doğrulamak içindir.

## Ön Koşullar

- Veritabanı migrate edilmiş olmalı.
- Servis verileri seed edilmiş olmalı (örn. `php artisan db:seed --class=ServiceSeeder`).
- Servis görselleri indirilmiş olmalı (örn. `powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools\download-service-images.ps1`).

## 1) Servisler Ana Liste

1. `/Servisler` sayfasını açın.
2. En az 1 servis kartı göründüğünü doğrulayın.
3. Sayfa başlığının “Servisler” olduğunu doğrulayın.
4. Kategori filtre butonlarının göründüğünü doğrulayın.

Beklenen:
- Liste boş değil.
- Kategori butonları aktif kategoriyi görsel olarak işaretliyor.

## 2) Kategori Sayfaları

Her kategori için aşağıdaki adımları tekrarlayın:

1. `/Servisler/kategori/{kategori-slug}` sayfasını açın.
2. Sayfa başlığında kategori adının göründüğünü doğrulayın.
3. Liste içinde sadece o kategoriye ait servislerin göründüğünü doğrulayın.

Beklenen:
- Sayfa 404 vermiyor.
- Kategoriye ait servisler listeleniyor.

## 3) Servis Detay Sayfaları

Rastgele 5 servis seçip aşağıdaki adımları uygulayın:

1. `/Servisler/{servis-slug}` sayfasını açın.
2. H1 başlığının servis başlığı ile aynı olduğunu doğrulayın.
3. Üst kısımda kısa açıklamanın (meta açıklama) göründüğünü doğrulayın.
4. İçerikte aşağıdaki başlıkların bulunduğunu doğrulayın:
   - “Hizmet Kapsamı”
   - “Kimler İçin Uygun?”
   - “Neden Bizi Tercih Etmelisiniz?”
   - “Yalova Odaklı Hizmet”
   - “İlgili Servisler”
5. İçerikte “Hemen Ara” butonunun `tel:` ile çalıştığını doğrulayın.
6. İçerikte “Yol Tarifi Al” butonunun Google Maps linkine gittiğini doğrulayın.

Beklenen:
- Sayfa 404 vermiyor.
- İçerik ve CTA linkleri çalışıyor.

## 4) SEO Alan Kontrolü (Basit)

1. Bir kategori sayfasını açın, sayfa kaynağında `meta description` ve `meta keywords` etiketlerini kontrol edin.
2. Bir servis detay sayfasını açın, sayfa kaynağında `meta description` ve `meta keywords` etiketlerini kontrol edin.

Beklenen:
- Kategori meta alanları dolu.
- Servislerde `meta_description` kısa açıklamadan geliyor ve dolu.
