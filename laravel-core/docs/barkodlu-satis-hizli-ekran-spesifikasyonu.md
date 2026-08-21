# Barkodlu Satis Hizli Ekran Spesifikasyonu

Bu dokuman, barkodlu satis (POS) ekraninin hiz odakli ve muhasebe yapisini bozmadan gelistirilmesi icin teknik referanstir.

## 1. Ekran Amaci

- Barkod okutma ile urunleri minimum tik ile sepete dusurmek.
- Kasada bekleme suresini azaltmak.
- Islem hatalarini (yanlis stok, yanlis fiyat, eksik odeme) en aza indirmek.
- Satis -> stok -> finans baglantisini dogru ve izlenebilir sekilde tamamlamak.

## 2. Masaustu Wireframe

```text
+----------------------------------------------------------------------------------+
| HIZLI SATIS (POS)                 [F2 Barkod] [F8 Odeme] [F9 Tamamla] [Esc Temizle] |
+-----------------------------+-------------------------------+--------------------+
| SOL (Arama/Oku)             | ORTA (Sepet Kalemleri)       | SAG (Odeme/Toplam) |
|-----------------------------|-------------------------------|--------------------|
| Barkod: [______________]    | Urun            Miktar  Tutar | Ara Toplam: 0,00   |
| [Enter ile ekle]            | ----------------------------- | Iskonto : 0,00     |
| Hizli urun ara: [______]    | Kamera A          1     500   | KDV     : 0,00     |
| Son okutulan: XYZ123        | Termal macun      2     300   | GENEL   : 0,00     |
|-----------------------------| ...                           |--------------------|
| Musteri: [Cari Secimi]      | [Satir +] [-] [Sil] [Iskonto]| Odeme Tipi         |
| Musteri tel: 05xx...        |                               | ( ) Nakit          |
| Fis notu: [_____________]   |                               | ( ) Kart           |
|-----------------------------|                               | ( ) Havale         |
| [F4 Beklet] [F6 Iptal]      |                               |--------------------|
| [Satis Gecmisi]             |                               | [F9 SATISI TAMAMLA]|
+-----------------------------+-------------------------------+--------------------+
```

## 3. Mobil/Tablet Wireframe

```text
+--------------------------------------+
| Barkod [______________] [Ekle]       |
| Hizli ara [__________]               |
|--------------------------------------|
| Sepet                                |
| Kamera A        x1         500,00    |
| Termal Macun    x2         300,00    |
| [Satir duzenle]                     |
|--------------------------------------|
| Ara Toplam: 800,00                   |
| Iskonto  :  50,00                    |
| KDV      : 135,00                    |
| Genel    : 885,00                    |
|--------------------------------------|
| Odeme: [Nakit v]                     |
| [SATISI TAMAMLA]                     |
+--------------------------------------+
```

## 4. Zorunlu Alanlar

1. Barkod (`text`, zorunlu, odakta kalir).
2. Sepet kalemleri (`stok_id`, `stok_adi`, `miktar`, `birim_fiyat`, `iskonto_tutari`, `kdv_orani`, `satir_toplami`).
3. Satis tarihi (`datetime`, varsayilan: simdi).
4. Odeme tipi (`nakit|kart|havale|diger`).
5. Para birimi (`TRY` varsayilan).
6. Cari (opsiyonel).
7. Not (opsiyonel).
8. Ozet alanlari (ara_toplam, iskonto_toplami, kdv_toplami, genel_toplam).

## 5. Is Kurallari

1. Barkod bulunamazsa satira urun eklenmez, net hata verilir.
2. Ayni barkod tekrar okutulursa yeni satir acmak yerine miktar artar.
3. Yetersiz stokta kaydetme engellenir.
4. Ayni stok birden fazla satirdaysa toplu miktar kontrolu yapilir.
5. Yetkisiz kullanici fiyat degistiremez.
6. Yetkisiz kullanici iskonto giremez.
7. Satis tamamlandiginda:
   - stok hareketi olusur,
   - finans/tahsilat kaydi olusur,
   - fis akisi acilir.

## 6. Klavye Kisayol Matrisi

| Tus | Islem | Not |
|---|---|---|
| `F2` | Barkod alanina odak | Her adimdan geri donebilmeli |
| `Enter` | Barkodu sepete ekle | Ekleme sonrasi tekrar barkoda odak |
| `Ctrl + F` | Hizli urun arama odagi | Barkod yoksa manuel secim icin |
| `F7` | Secili satir miktar +1 | Seri satis hizlandirma |
| `Shift + F7` | Secili satir miktar -1 | Hata duzeltme |
| `Del` | Secili satiri sil | Onay isteyebilir |
| `F8` | Odeme paneline gec | Kasa bitis adimi |
| `F9` | Satisi tamamla | Yetki + stok + finans kontrolleri sonrasi |
| `Ctrl + P` | Fis yazdir | Satis tamamlandiktan sonra aktif |
| `Esc` | Acik popup kapat / odaga don | Akisi kesmemek icin |

## 7. Performans Hedefleri

1. Barkod okut -> satira dusme suresi: hedef `<150ms`.
2. Satisi tamamla -> sonuc bildirimi: hedef `<1200ms` (lokal ag).
3. Ekran boyunca tam klavye ile kullanilabilirlik: `%100`.

## 8. Hata Mesaji Standartlari

1. Kisa ve net: `Yetersiz stok: {urun_adi}`.
2. Aksiyon odakli: `Barkod bulunamadi. Kodu kontrol edin veya hizli arama kullanin.`
3. Teknik hata durumunda referans kodu/log: `Islem basarisiz (REF: POS-ERR-XXXX)`.

## 9. Kabul Kriterleri

1. Barkod odagi surekli korunur.
2. Enter ile seri urun ekleme kesintisiz calisir.
3. Yetki disi fiyat/iskonto degisikligi backend tarafinda da bloklanir.
4. Kaydedilen satislarda stok-finans mutabakati testleri yesildir.
5. Fis akisi satis sonu otomatik acilir.

## 10. Asamali Uygulama Plani

1. Asama 1: UI hiz optimizasyonu (odak, kisayollar, sepet akisi).
2. Asama 2: Is kurali sertlestirme (stok toplu kontrolu, yetki kilitleri).
3. Asama 3: Operasyon katmani (kritik alarm, rapor/export, mutabakat gorunurlugu).

