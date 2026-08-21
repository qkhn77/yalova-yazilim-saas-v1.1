# Barkodlu Satis Gelistirme Guvenlik Checklisti

Bu checklist, muhasebe cekirdeginin bozulmamasini garanti etmek icin her barkodlu satis gelistirmesinde zorunlu kontrol listesidir.

## 1. Tasarim Kurallari

- Muhasebe cekirdegine (fatura/finans/stok servisleri) dogrudan davranis degisikligi yapma.
- Yeni ozellikleri once barkodlu satis katmaninda izole et.
- Veri modeli degisikliklerinde migration `up/down` guvenli olmali.
- Degisiklikler idempotent olmali (ayni islem tekrarlandiginda cift kayit olusmamali).
- Yeni yetki varsa:
  - yetki sabiti
  - migration/seeder
  - rol matrisi
  birlikte guncellenmeli.

## 2. Guvenlik Kurallari

- Tenant/Firma izolasyonu korunmali.
- Yetkisiz fiyat/iskonto degisikligi UI ve backend tarafinda engellenmeli.
- Stok eksiye dusurme kurali stok servisindeki mevcut politika ile uyumlu olmali.
- Iptal ve iade akislari finans/stok ters kayit mantigini bozmamali.
- Tum metin dosyalari `UTF-8 (BOM'suz)` olmalidir.
- Turkce karakterlerde bozulma (mojibake) kabul edilmez; `tools/check-text-encoding.ps1` kontrolu zorunludur.
- Hata durumlari is akisina gore:
  - kritikse fail
  - kritik degilse olay/metrik log + guvenli fallback
  ile ele alinmali.

## 3. Zorunlu Test Komutlari

Asagidaki komutlar merge/onay oncesi zorunludur:

```powershell
powershell -ExecutionPolicy Bypass -File tools/check-text-encoding.ps1
php artisan test --filter="BarkodluSatis(IadeGuvenlik|IzlemeHardening|TahsilatVeFis)Test"
php artisan test --filter="BarkodluSatisMutabakatKomutuTest"
```

Tum barkodlu satis test paketini tek komutla kosmak icin:

```powershell
powershell -ExecutionPolicy Bypass -File tools/test-barkodlu-satis.ps1
php artisan barkodlu-satis:mutabakat-dogrula --days=30 --limit=1500
php artisan barkodlu-satis:mutabakat-dogrula --days=30 --limit=1500 --critical-only
```

## 4. Kabul Kriteri

- Testler tamamen yesil olmali.
- Yeni migration calistiginda veri kaybi olmamali.
- POS temel akis bozulmamali:
  - barkod okut
  - satira dusme
  - Enter ile ekleme
  - satis kaydet
  - fis/tahsilat kaydi.
- Olay/izleme kayitlari beklenen tiplerde uretilmeli.

## 5. Son Yapilanlar (Durum)

- [x] Coklu barkod destegi (stok_barkodlari + POS arama entegrasyonu)
- [x] Fiyat/iskonto yetki kirilimi (UI + backend sertlestirme)
- [x] POS Enter/odak akisi
- [x] Satis fisi + tahsilat entegrasyonu (nakit/kart)
- [x] Guvenlik/izleme testleri (kritik senaryolar)
- [x] Barkodlu satis-finans mutabakat kontrolu (sayfa + komut + gunluk scheduler)
