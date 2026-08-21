# Muhasebe Geliştirme Ön Promptu

Aşağıdaki metni yeni muhasebe geliştirme sohbetlerinin başında kullanabilirsiniz.

```text
Bu proje C:\xampp\htdocs\yalova-kamera altında çalışan Laravel 11 + PHP 8.2 + Filament 3.3 uygulamasıdır. Tüm dosya işlemlerinde UTF-8 kullan; Türkçe karakterleri bozma.

Önce şu raporu oku:
docs/muhasebe-gelistirme-entegrasyon-raporu-2026-07-13.md

Çalışma kuralları:
1) Mevcut muhasebe çekirdeğini ve bağlı modülleri bozmayacak şekilde ilerle.
2) Firma/tenant izolasyonunu koru. `withoutGlobalScopes()` kullanırsan mutlaka açık `firma_id` filtresi ve erişim kontrolü ekle.
3) Cari, stok, finans, kasa, banka, POS ve fatura hareketlerini doğrudan kopyalama; mevcut servisleri ve enumları kullan.
4) Finansal/stok/cari kayıtları silme; mevcut ters kayıt ve iptal desenini kullan.
5) Çoklu tablo değişikliklerini transaction, eşzamanlı işlemleri kilit ve idempotency ile koru.
6) Tutar/kur/komisyon hesaplarında float kullanma; mevcut decimal/BCMath hassasiyetini koru.
7) UI doğrulamasını backend servis iş kuralı doğrulamasının yerine koyma.
8) Yeni yetki varsa sabit, seeder/matris, panel erişimi ve servis güvenliğini birlikte güncelle.
9) E-ticaret, teknik servis, restoran, personel, teklif ve barkodlu satış entegrasyonlarını rapordaki kurallara göre kontrol et.
10) Değişiklikten önce mevcut akışı ve etkilenecek dosyaları çıkar; gereksiz refactor yapma.

İstenen iş:
[Buraya tek ve net geliştirme isteğini yaz.]

Çalışma biçimi:
- Önce kısa analiz: mevcut akış, veri kaynağı, çağıranlar, riskler.
- Sonra uygulanacak dosya listesi ve değişiklik planı.
- Belirsizlik varsa varsayımı açıkça yaz; kapsamı kendi kendine büyütme.
- Kod değişikliği isteniyorsa apply_patch ile küçük ve geri alınabilir değişiklik yap.
- Migration gerekiyorsa up/down ve eski veriye etkisini açıkla.
- İlgili Unit/Feature testini ekle veya güncelle.
- Sonunda değişen dosyaları, test komutlarını, test sonucunu ve kalan riskleri özetle.
- Bir entegrasyon etkileniyorsa diğer modüller için hangi kontrolün yapıldığını mutlaka belirt.
```

## Daha kısa sürüm

```text
Bu Laravel 11 + Filament 3.3 projesinde muhasebe geliştirmesi yapacağız. Önce `docs/muhasebe-gelistirme-entegrasyon-raporu-2026-07-13.md` dosyasını oku. UTF-8 kullan, tenant/firma izolasyonunu ve mevcut servis/enum sözleşmelerini koru. Doğrudan hareket tablosuna yazma; transaction, lock, idempotency ve ters kayıt desenlerini kullan. İsteği önce analiz et, etkilenecek dosyaları ve riskleri çıkar, sonra küçük bir patch uygula. İlgili testleri çalıştır ve sonucu raporla. Geliştirme isteği: [BURAYA YAZ].
```

## İstek yazma şablonu

```text
Amaç:
Kullanıcı akışı:
Etkilenecek modül: Muhasebe / E-ticaret / Teknik Servis / Restoran / Personel / Teklif / Barkodlu Satış
Beklenen veri:
Yetki gereksinimi:
İptal/ters kayıt davranışı:
Kabul kriterleri:
```
