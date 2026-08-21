# Muhasebe Geliştirme ve Modül Entegrasyon Raporu

**Proje:** `yalova-kamera`  
**Rapor tarihi:** 2026-07-13  
**Kapsam:** Muhasebe çekirdeği, bu çekirdeğe veri yazan/okuyan modüller, yetki-tenant sınırı, veri yaşam döngüsü, güvenli geliştirme kuralları ve yeni sohbetler için çalışma sözleşmesi.

## 1. Kısa cevap: Bu dosya nedir?

Evet, bu bir **Markdown (`.md`) dosyasıdır**. Düz metindir; başlık, tablo, kod ve dosya yollarını düzenli göstermek için Markdown biçimi kullanır. Bu rapor yeni bir sohbette referans olarak okutulabilir. Aynı klasördeki `muhasebe-gelistirme-on-promptu.md` ise yeni sohbete yapıştırılacak kısa başlangıç talimatıdır.

## 2. Yönetici özeti

Proje Laravel 11, PHP 8.2 ve Filament 3.3 kullanır. Tek veritabanında firma bazlı çok kiracılı SaaS yapısı vardır. Muhasebe modülü yalnızca fatura ekranlarından oluşmaz; aşağıdaki modüllerle ortak veri ve servis kullanır:

- Muhasebe: cari, fatura, stok, kasa, banka, POS, finans, vade/alacak, döviz ve e-belge.
- E-ticaret: müşteri carisi, proforma fatura, ödeme finansı, stok rezervi/çıkışı ve sipariş iptali.
- Teknik Servis: servis tahsilatı, satış/gider faturası, finans bağlantısı, ödeme planı ve servis bazlı cari/fatura özeti.
- Restoran: adisyon, kısmi/çoklu tahsilat, kasa-banka-POS, reçeteli stok hareketi, gün sonu mutabakatı ve bekleyen fatura.
- Personel Takip: personel avansı ve maaş ödemesinin kasa/banka finansına yazılması.
- Teklif Yönetimi: onaylı tekliften bekleyen fatura ve fatura kalemi üretimi.
- Barkodlu Satış: satış/iade, POS tahsilatı, stok etkisi ve muhasebe mutabakatı.

Muhasebe çekirdeğinin güvenlik ve tutarlılık ilkeleri şunlardır:

1. Firma izolasyonu `firma_id` ve aktif firma oturum bağlamıyla korunur.
2. Finansal/stok/cari hareketlerde silme yerine iptal ve ters kayıt tercih edilir.
3. Birden fazla tabloyu etkileyen işlemler transaction ve gerektiğinde `lockForUpdate()` içindedir.
4. Tekrar çağrılabilecek akışlarda idempotency ve ters kayıt kontrolü vardır.
5. Fatura onayı cari ve stok hareketlerini üretir; ödeme ise finans ve hesap hareketlerini, ardından fatura kapamasını günceller.
6. Tutarlar için `float` yerine mevcut servislerin decimal/BCMath kuralları kullanılmalıdır.

## 3. Mimari ve giriş noktaları

### Teknoloji

- Laravel 11
- PHP `^8.2`
- Filament `3.3`
- MySQL bağlantısı (`.env` mevcut ortamında `127.0.0.1:3306`)
- Frontend ödeme callback ve kargo callback rotaları
- Filament yönetim paneli `/admin` altında
- Panel modülleri `app/Filament/Clusters` altında keşfedilir.

### Panel ve tenant akışı

Ana panel sağlayıcısı `app/Providers/Filament/AdminPanelProvider.php` dosyasıdır. Panelde `FilamentTenantContextMiddleware` çalışır. Normal kullanıcıda aktif firma yoksa panel erişimi kesilir; süper yönetici ve sistem yöneticisi istisnadır.

Aktif firma `app/Services/TenantContextService.php` üzerinden session alanlarında tutulur:

- `aktif_firma_id`
- `aktif_firma_kodu`
- `aktif_rol_id`
- `aktif_kullanici_firma_id`

Muhasebe çekirdeği modellerinin önemli kısmı `HasFirmaTenantScope` kullanır. Scope, normal web isteğinde aktif `firma_id` filtresi ekler. Konsol ve test davranışı özel olarak ayrılmıştır. Scope kaldırılan sorgularda güvenlik için açıkça `where('firma_id', $firmaId)` yazılması zorunludur.

Ana güvenlik sınıfları:

- `app/Models/Concerns/HasFirmaTenantScope.php`
- `app/Models/Scopes/FirmaIdTenantScope.php`
- `app/Muhasebe/Guvenlik/MuhasebeFirmaErisimDenetleyicisi.php`
- `app/Muhasebe/Guvenlik/MuhasebeFilamentErisimYardimcisi.php`
- `app/Support/MuhasebeYetkiSablonlari.php`
- `app/Providers/AppServiceProvider.php`

## 4. Muhasebe çekirdeği

### Ana modeller ve tablolar

| Alan | Model / tablo | Rolü |
|---|---|---|
| Cari | `Cari` / `cariler` | Müşteri, tedarikçi veya diğer cari kartı; para birimi, risk ve vade bilgisi. |
| Cari hareket | `CariHareketi` / `cari_hareketleri` | Borç/alacak hareketi; fatura, ödeme, tahsilat ve plan taksitleriyle ilişkilidir. |
| Fatura | `Fatura` / `faturalar` | Taslak, bekleyen, proforma, giden/gelen, gider, iade ve iptal durumları. |
| Fatura kalemi | `FaturaKalemi` / `fatura_kalemleri` | Hizmet veya stok kalemi; vergi/indirim ve tutar snapshot'ları. |
| Stok kartı | `StokKarti` / `stok_kartlari` | Ürün/hizmet stok tanımı, miktar, rezerv, maliyet, fiyat ve kategori. |
| Stok hareketi | `StokHareketi` / `stok_hareketleri` | Alış, satış, iade, transfer ve ters hareketler. |
| Finans hareketi | `FinansHareketi` / `finans_hareketleri` | Tahsilat, ödeme, mahsup ve ters finans hareketleri. |
| Kasa/banka/POS hesabı | `KasaHesabi`, `BankaHesabi`, `PosHesabi` | Para hareketinin hedef hesabı. |
| Hesap hareketi | `KasaHareketi`, `BankaHareketi`, `PosHareketi` | Finans hareketinin hesap bazındaki ayrıntısı. |
| Fatura kapama | `FaturaFinansKapama` / `fatura_finans_kapatmalari` | Finans hareketinin hangi faturaya ne kadar uygulandığını tutar. |
| Cari FIFO | `CariHareketEslesmesi` / `cari_hareket_eslesmeleri` | Borç/alacak açık kalemlerini eşleştirir. |
| Alacak planı | `AlacakPlani`, `AlacakPlanTaksiti` ve eşleşme tabloları | Vadeli satış/tahsilat planı, taksit, tahsilat ve revizyon. |
| Döviz | `ParaBirimi`, `DovizKuru` | Para birimi tanımları ve tarihli kur altyapısı. |
| E-belge | `NetteFaturaGonderimi`, `NetteFaturaGelenBelge` | NetteFatura gönderim/gelen belge altyapısı ve logları. |

Muhasebe model dosyalarının tamamı `app/Models/Muhasebe` altındadır. İş kuralları çoğunlukla `app/Muhasebe/Servisler` altındadır; Filament ekranındaki form kodu iş kuralının tek kaynağı kabul edilmemelidir.

### Enumlar

Yeni kodda string değerleri elle çoğaltmak yerine mevcut enumlar kullanılmalıdır:

- `FaturaTuru`, `FaturaDurumu`
- `FinansHareketTuru`, `FinansHareketDurumu`
- `CariHareketBelgeTuru`, `CariHareketDurumu`
- `StokHareketIslemTuru`, `StokHareketDurumu`, `StokBelgeTuru`
- `HesapDurumu`, `PosTipi`, `SaglayiciTipi`

## 5. Temel veri yaşam döngüleri

### 5.1 Fatura onayı

Ana servis: `app/Muhasebe/Servisler/FaturaIslemServisi.php`

Akış:

```text
Taslak/Beklemede fatura
        |
        v
FaturaToplamDogrulamaServisi
        |
        +--> CariHareketServisi::kayitOlustur()
        |
        +--> Stok kalemi varsa StokHareketServisi::kayitOlustur()
        |
        +--> fatura durumu = Onayli
        |
        +--> afterCommit ile uygun avans mahsubu
```

Kurallar:

- Proforma/bekleyen/iptal gibi kayıt üretmeyen türler cari ve stok hareketi üretmez.
- Kayıt üreten faturada cari zorunludur.
- Fatura carisi ve stok kartı aynı firmaya ait olmalıdır.
- Aynı fatura tekrar onaylanırsa duplicate hareket üretilmemelidir.
- Fatura iptal/iade edilirken bağlı aktif finans hareketleri önce terslenmelidir.
- Cari ve stok hareketi kaynak kaydın `belge_turu` ve `belge_id` değerleriyle izlenir.

### 5.2 Tahsilat/ödeme

Ana servis: `app/Muhasebe/Servisler/FinansHareketServisi.php`

Nakit, banka, POS, kur dönüşümlü tahsilat/ödeme, POS komisyonu ve hesaplar arası virman akışları aynı servis ailesindedir.

Tipik tahsilat:

```text
FinansHareketi (Tahsilat)
  +--> KasaHareketi veya BankaHareketi veya PosHareketi
  +--> CariHareketi (tahsilat)
  +--> FaturaFinansKapama
  +--> açık faturanın ödeme durumu
  +--> gerekiyorsa AlacakTahsilatServisi ile taksit eşleşmesi
```

Tipik ödeme aynı yapıyı `Odeme` türüyle ters yönde kullanır. Fatura kapama servisi `FaturaFinansKapamaServisi` üzerinden FIFO/otomatik dağıtım ve avans mahsubu yapabilir. Bu davranışlar `config/muhasebe.php` içindeki `otomasyon` ayarlarıyla kontrol edilir.

### 5.3 Stok hareketi ve ters kayıt

Ana servis: `app/Muhasebe/Servisler/StokHareketServisi.php`

- Stok kartı `lockForUpdate()` ile kilitlenir.
- Önceki miktar, sonraki miktar, maliyet ve stok değeri hesaplanır.
- Negatif stok varsayılan olarak kapalıdır: `config/muhasebe.php`.
- Kritik stok olayı `SistemOlayServisi` ile kaydedilir.
- İptalde hareket silinmez; kaynak iptal edilir, ters yönde yeni hareket üretilir.
- Stok maliyeti `StokMaliyetHesaplamaServisi` ile hesaplanır.

### 5.4 Cari ve FIFO

Ana servisler:

- `CariHareketServisi`
- `CariHareketFifoEslestirmeServisi`
- `CariBakiyeServisi`
- `CariEkstreServisi`
- `CariYaslandirmaServisi`

Yeni cari hareketi oluşturulduğunda otomatik FIFO eşleşmesi tetiklenir. Hareket terslenirken ilgili FIFO eşleşmeleri kaldırılır ve borç/alacak yönü terslenmiş yeni hareket oluşturulur.

### 5.5 Alacak planı ve vade takibi

Ana servisler:

- `AlacakPlanServisi`
- `AlacakTahsilatServisi`
- `AlacakPlanDogrulamaServisi`
- `AlacakPlanOnayServisi`
- `AlacakOperasyonServisi`
- `AlacakRaporServisi`
- `AlacakHatirlatmaServisi`

Alacak planı; cari, kaynak türü/kaynak ID, para birimi, toplam, peşinat, taksit, vade farkı/faiz ve ödeme durumunu taşır. Taksit tahsilatı finans hareketiyle eşleştirilir. Plan revizyonunda eski açık cari hareketleri iptal edilip yeni taksit/cari hareketleri üretilebilir.

## 6. Diğer modüllerle entegrasyon haritası

### E-ticaret

Ana dosyalar:

- `app/Services/EcommerceMuhasebeEntegrasyonServisi.php`
- `app/Services/EcommerceCariServisi.php`
- `app/Services/EcommerceMuhasebeOdemeHedefServisi.php`
- `app/Modules/Urun/Servisler/SiparisOdemeServisi.php`
- `app/Modules/Urun/Servisler/CheckoutServisi.php`
- `app/Models/Ecommerce/Siparis.php`

Ödeme başarılı olduğunda sipariş için cari oluşturulur/güncellenir, proforma fatura oluşturulur veya mevcut bağlantı kullanılır ve seçilen kasa/banka/POS hesabına finans tahsilatı yazılır. Sipariş iptalinde ödeme ve stok rezervi/çıkışı tersine alınır.

Önemli ayrım: E-ticaret sipariş stoğu `SiparisOdemeServisi` içinde doğrudan stok miktarı/rezerv alanlarını güncelleyen ayrı bir akışa sahiptir. Bu, fatura onayındaki `StokHareketServisi` akışından farklıdır. E-ticaret stok davranışı değiştirilirken aynı stoğun faturayla ikinci kez düşürülmemesi özellikle kontrol edilmelidir.

E-ticaret ödeme callback'leri oturumsuz çalışabildiği için muhasebe servislerinde özel e-ticaret yazma metotları vardır. Bu metotlar normal kullanıcı oturumu yerine firma varlığını ve kayıtların aynı firmaya ait olmasını doğrular.

### Teknik Servis

Ana dosyalar:

- `app/TeknikServis/Servisler/TeknikServisTahsilatServisi.php`
- `app/TeknikServis/Filament/ServisGiderFaturasiDestegi.php`
- `app/TeknikServis/Filament/TeknikServisTahsilatFormu.php`
- `app/Models/TeknikServis/TeknikServisMuhasebeBaglantisi.php`
- `app/Models/TeknikServis/TeknikServisTahsilati.php`
- `app/TeknikServis/Servisler/TeknikServisBekleyenFaturaSenkronKontrolu.php`

Teknik servis tahsilatı:

```text
Servis kaydı + cari + tahsilat kanalı
  +--> FinansHareketi (Tahsilat)
  +--> Kasa/Banka/POS hareketi
  +--> TeknikServisTahsilati
  +--> TeknikServisMuhasebeBaglantisi
  +--> gerekiyorsa satış faturası ve alacak planı
```

Teknik servis tahsilatı güncelleme/iptal işleminde mevcut finans hareketi silinmez; ters kayıt oluşturulur. Vadeli/taksitli senaryoda `AlacakPlanServisi` kullanılır. Gider faturası desteği, muhasebe fatura kaynaklarını kullanır ve kalem/servis bağlantısını ayrıca izler.

### Restoran

Ana dosyalar:

- `app/Services/Restoran/RestoranTahsilatServisi.php`
- `app/Services/Restoran/RestoranStokServisi.php`
- `app/Services/Restoran/RestoranFaturaServisi.php`
- `app/Services/Restoran/RestoranGunSonuMutabakatServisi.php`
- `app/Services/Restoran/RestoranRaporServisi.php`
- `app/Models/Restoran/RestoranAdisyonu.php`
- `app/Models/Restoran/RestoranAdisyonTahsilati.php`

Adisyon kapandığında tahsilat finansına ve uygun hesap hareketine yazılır; tam kapanışta reçete/stok çıkışı tetiklenir. Tahsilat iptalinde finans ters kaydı, kapanmış adisyon tekrar açılıyorsa stok ters kaydı ve bekleyen fatura iptali birlikte ele alınır.

Restoran kaynaklı resmi/bekleyen fatura için mevcut tasarımda kalemler bilinçli olarak hizmet kalemi üretir. Bunun nedeni reçete stok düşümünün zaten adisyon kapanışında yapılmasıdır. Bu karar değiştirilirse çift stok düşümü oluşabilir.

Gün sonu mutabakatı restoran tahsilatlarını aktif kasa/banka/POS muhasebe hareketleriyle karşılaştırır. Rapor ve kapanış sorgularında firma filtresi ve tarih aralığı korunmalıdır.

### Personel Takip

Ana dosyalar:

- `app/Services/PersonelTakip/PersonelFinansHareketServisi.php`
- `app/Services/PersonelTakip/PersonelAvansKuralServisi.php`
- `app/Services/PersonelTakip/PersonelMaasOdemeKuralServisi.php`
- `app/Models/Personel/PersonelAvansi.php`
- `app/Models/Personel/PersonelMaasOdemeKaydi.php`

Personel avansı veya maaş ödemesi, `FinansHareketi` türü `Odeme` olarak oluşturulur ve kasa/banka hareketine bağlanır. Bu akış doğrudan cari hareketi üretmeyebilir; personel modülünün kendi ödeme/mahsup durumları ayrıca güncellenir. Yeni personel finans özelliğinde muhasebe hesabı ve personel kayıtlarının aynı firmaya aitliği ayrıca doğrulanmalıdır.

### Teklif Yönetimi

Ana dosyalar:

- `app/TeklifYonetimi/Servisler/TeklifIsAkisiServisi.php`
- `app/TeklifYonetimi/Servisler/TeklifNumaraServisi.php`
- `app/Models/Muhasebe/Teklif.php`
- `app/Models/Muhasebe/TeklifKalemi.php`

Onaylı tekliften bekleyen fatura oluşturulur. Fatura kalemleri teklif kalemlerinden taşınır. Fatura daha sonra normal muhasebe fatura onay akışına girer. Aynı teklif için ikinci fatura oluşturulması mevcut kaynak bağlantısı ve idempotency kontrolleriyle engellenmelidir.

### Barkodlu Satış

Ana dosyalar:

- `app/Muhasebe/Servisler/BarkodluSatisServisi.php`
- `app/BarkodluSatis/Mutabakat/BarkodluSatisMuhasebeMutabakatServisi.php`
- `app/Filament/Clusters/Muhasebe/Pages/BarkodluSatisSayfasi.php`
- `app/Filament/Clusters/Muhasebe/Pages/BarkodluSatisIadeFisiSayfasi.php`

Barkodlu satışta satış fişi, tahsilat finansı, stok etkisi, iade ve mutabakat birlikte düşünülür. Mutabakat servisi tamamlanan satış/iade ile aktif finans hareketlerinin sayısını, toplamını, para birimini ve iptal durumunu kontrol eder. Yeni alan eklenecekse mutabakat komutu ve iade akışı da güncellenmelidir.

## 7. Yetki ve erişim sözleşmesi

Muhasebe yetki sabitleri `app/Support/MuhasebeYetkiSablonlari.php` içindedir. Başlıca kodlar:

- `muhasebe.goruntule`
- `cari.goruntule`, `cari.olustur`, `cari.guncelle`, `cari.sil`
- `stok.goruntule`, `stok.olustur`, `stok.guncelle`, `stok.sil`
- `fatura.goruntule`, `fatura.olustur`, `fatura.guncelle`, `fatura.sil`, `fatura.onay`
- `finans.goruntule`, `finans.olustur`, `finans.guncelle`, `finans.sil`, `finans.onay`
- `muhasebe_rapor.goruntule`, `muhasebe_tanim.goruntule`, `muhasebe_tanim.guncelle`
- POS ve barkodlu satış yetkileri

Yeni bir muhasebe özelliğinde yalnızca Filament menüsünü gizlemek yeterli değildir. Şu üç katman birlikte kontrol edilmelidir:

1. Navigation/sidebar görünürlüğü.
2. Filament resource/page erişimi ve action yetkisi.
3. Servis katmanı ve firma sınırı.

Modül erişimi ile işlem yetkisi ayrıdır. Firma modülü aktif değilse ekran görünmemeli; yetkisi olmayan kullanıcı doğrudan URL veya Livewire isteğiyle de işlem yapamamalıdır.

## 8. Geliştirme sırasında kesin korunacak kurallar

### Firma izolasyonu

- Her yeni muhasebe tablosunda `firma_id` bulunmalıdır.
- Foreign key ilişkileri aynı firma kontrolüyle doğrulanmalıdır; yalnızca ID'nin var olması yeterli değildir.
- `withoutGlobalScopes()` kullanılırsa aynı sorguda açık firma filtresi olmalıdır.
- Public callback/QR/cron akışlarında session tenant'a güvenilmemeli; kaynak kaydın `firma_id` değeriyle doğrulama yapılmalıdır.

### Hareket üretimi

- `CariHareketi`, `StokHareketi`, `FinansHareketi`, `KasaHareketi`, `BankaHareketi`, `PosHareketi` için mümkün olduğunca mevcut servisler kullanılmalıdır.
- Doğrudan `Model::query()->create()` yalnızca ilgili servis sözleşmesi buna izin veriyorsa kullanılmalıdır.
- Finansal kayıtlar fiziksel olarak silinmemeli; ters kayıt/idempotent iptal akışı kullanılmalıdır.
- Ters kaydın `iptal_edilen_hareket_id`, kaynak türü ve kaynak ID bilgileri korunmalıdır.

### Transaction ve eşzamanlılık

- Kaynak kayıt ve bağlı muhasebe kayıtlarını etkileyen işlem tek transaction olmalıdır.
- Fatura, finans ve stokta mevcut kilitleme desenleri korunmalıdır.
- Sayaç/numara üretiminde ilgili servis kullanılmalı; son ID'yi okuyup artırma yöntemi yazılmamalıdır.
- Aynı isteğin tekrar gelmesiyle duplicate fatura, finans, stok veya tahsilat oluşmamalıdır.

### Para ve hesaplama

- Fatura toplamlarında mevcut 8 basamaklı hassasiyet altyapısı korunmalıdır.
- Stok miktarı 4, maliyet/tutar alanları servislerin mevcut decimal hassasiyetiyle hesaplanmalıdır.
- `float` ile toplam/kur/komisyon hesabı eklenmemelidir; `bcadd`, `bcsub`, `bcmul`, `bccomp` veya mevcut hesaplama servisleri kullanılmalıdır.
- Fatura, cari, finans ve hesap para birimi uyumsuzlukları reddedilmelidir.
- Baz para birimi ve kur snapshot'ları kaybolmamalıdır.

### UI ve metin

- Formdaki hesaplama yalnızca kullanıcı deneyimidir; son doğrulama backend servisinde olmalıdır.
- Türkçe metinlerde mojibake kabul edilmez.
- Yeni/editi yapılan kaynak dosyaları UTF-8, tercihen BOM'suz kaydedilmelidir.
- Kullanıcıya gösterilen hata/uyarı metni mevcut Türkçe terminolojiyle uyumlu olmalıdır.

## 9. Geliştirme öncesi ve sonrası kontrol listesi

### Önce

- Değişecek tablo/model/servis ve onu çağıran tüm modüller çıkarılır.
- Yeni alanın kaynağı ve yaşam döngüsü yazılır: kim oluşturur, kim günceller, iptal nasıl olur?
- Firma, para birimi, yetki ve idempotency kuralları belirlenir.
- Mevcut testlerden en yakın senaryolar seçilir.

### Değişiklik sırasında

- Migration `up/down` veya güvenli geri dönüş davranışı düşünülür.
- İş kuralı servis katmanında tutulur; ekran koduna kopyalanmaz.
- Eski akışların beklediği alan adları ve enum değerleri korunur.
- Yeni entegrasyon için kaynak türü/referans alanları ve audit/olay logları eklenir.

### Sonra

- İlgili Unit/Feature testleri çalıştırılır.
- Firma A verisiyle Firma B verisine erişim/yazma reddi test edilir.
- Aynı isteğin iki kez gönderilmesi denenir.
- İptal/ters kayıt ve kısmi ödeme denenir.
- Fatura–cari–stok–finans tutarlılık komutları çalıştırılır.
- UTF-8 kontrolü yapılır.

## 10. Mevcut doğrulama komutları ve test odakları

Muhasebe için mevcut komut ailesi:

```text
muhasebe:sistem-dogrula
muhasebe:reconcile
muhasebe:fatura-finans-yetim-onar
fatura:kapama-dogrula
muhasebe:alacak-plan-dogrula
muhasebe:doviz-kurlari-guncelle
muhasebe:vade-hatirlatma
muhasebe:export-minimum
stok:yeniden-hesapla
stok:maliyet-yeniden-hesapla
stok:rezerv-dogrula
muhasebe:stok-hareket-fatura-yetim-onar
barkodlu-satis:mutabakat-dogrula
```

Scheduler'da özellikle şu işler tanımlıdır:

- Döviz kurlarını günlük güncelleme.
- Barkodlu satış mutabakatı.
- Alacak planı doğrulama.
- Vade hatırlatma.
- E-ticaret ödeme zaman aşımı ve sipariş işlemleri.

Muhasebe testleri `tests/Unit/Muhasebe`, `tests/Feature/Muhasebe` altındadır. Entegrasyon modülleri için `tests/Feature/Restoran`, `tests/Feature/TeknikServis`, `tests/Feature/Urun`, `tests/Feature/PersonelTakip` ve `tests/Feature/TeklifYonetimi` klasörleri de kontrol edilmelidir.

## 11. İnceleme sırasında tespit edilen dikkat noktaları

1. **E-ticaret stok yolu ayrıdır.** Sipariş ödeme akışı stok miktarı/rezervini doğrudan güncelleyebildiği için yeni fatura veya stok entegrasyonu eklenirken çift düşüm kontrolü yapılmalıdır.
2. **Restoran faturası hizmet kalemi tasarımına bağlıdır.** Reçete stok çıkışı adisyon kapanışından geldiği için restoran faturasını fiziksel stok kalemi yapmak çift stok düşümüne yol açabilir.
3. **`referans_turu` ve `referans_id` birlikte okunmalıdır.** `referans_id` tek başına fatura, sipariş, teknik servis veya personel kaydı anlamına gelmez.
4. **`withoutGlobalScopes()` bilinçli istisnadır.** Yeni kodda kolaylık amacıyla kullanılmamalı; kullanıldığında firma filtresi, erişim kontrolü ve gerekirse kilit eklenmelidir.
5. **Finans ters kaydı fatura kapamasını da yeniler.** Sadece finans satırını terslemek fatura ödeme durumunu güncellemez; `FaturaFinansKapamaServisi` akışı korunmalıdır.
6. **NetteFatura altyapısı vardır.** E-belge alanları, gönderim/gelen belge modelleri, ayar ekranı ve istemciler mevcut; yeni belge davranışı eklenirken muhasebe hareketlerinin belge gönderim hatasından dolayı geri alınmaması korunmalıdır.
7. **Canlı veritabanı durumu bu incelemede doğrulanamadı.** `php artisan migrate:status` ve bazı Artisan komutları mevcut MySQL bağlantısında zaman aşımına uğradı. Bu nedenle bu rapor kaynak kodu, migration dosyaları ve test tanımları üzerinden hazırlanmıştır; canlı migration durumu ayrıca kontrol edilmelidir.
8. **UTF-8 taraması başarılıdır.** Proje kaynaklarının seçilen 1.371 dosyası strict UTF-8 olarak okunabildi. Projenin kendi `tools/check-text-encoding.ps1` betiği ise ortamda `git` komutu bulunamadığı için tamamlanamadı.

## 12. Yeni muhasebe geliştirmesi için önerilen çalışma sırası

1. İsteği tek bir iş akışına indir: örneğin “fatura onayında X”, “tahsilatta Y”, “stok raporunda Z”.
2. Bu rapordan ilgili entegrasyon bölümünü seç.
3. Yeni sohbette ön promptu kullan ve yalnızca ilgili dosyaları okut.
4. Önce mevcut akış ve veri sözleşmesini doğrulat; hemen kod yazdırma.
5. Sonra migration → model → servis → Filament ekranı → yetki → test sırasıyla ilerle.
6. Her adım sonunda ilgili mutabakat/test komutunu çalıştır.
7. Bir sonraki adıma geçmeden önce değişen dosyalar ve riskler özetlensin.

## 13. Gelecek sohbetlerde okutulacak temel dosya seti

Her işte tüm projeyi göndermek yerine aşağıdaki çekirdek set yeterli başlangıçtır:

```text
docs/muhasebe-gelistirme-entegrasyon-raporu-2026-07-13.md
config/muhasebe.php
app/Models/Concerns/HasFirmaTenantScope.php
app/Models/Scopes/FirmaIdTenantScope.php
app/Services/TenantContextService.php
app/Muhasebe/Guvenlik/MuhasebeFirmaErisimDenetleyicisi.php
app/Muhasebe/Servisler/FaturaIslemServisi.php
app/Muhasebe/Servisler/FinansHareketServisi.php
app/Muhasebe/Servisler/CariHareketServisi.php
app/Muhasebe/Servisler/StokHareketServisi.php
```

İşin modülüne göre ek dosyalar:

- E-ticaret: `app/Services/EcommerceMuhasebeEntegrasyonServisi.php`, `app/Modules/Urun/Servisler/SiparisOdemeServisi.php`
- Teknik Servis: `app/TeknikServis/Servisler/TeknikServisTahsilatServisi.php`, `app/TeknikServis/Filament/ServisGiderFaturasiDestegi.php`
- Restoran: `app/Services/Restoran/RestoranTahsilatServisi.php`, `app/Services/Restoran/RestoranStokServisi.php`, `app/Services/Restoran/RestoranFaturaServisi.php`
- Personel: `app/Services/PersonelTakip/PersonelFinansHareketServisi.php`
- Teklif: `app/TeklifYonetimi/Servisler/TeklifIsAkisiServisi.php`
- Barkodlu satış: `app/Muhasebe/Servisler/BarkodluSatisServisi.php`, `app/BarkodluSatis/Mutabakat/BarkodluSatisMuhasebeMutabakatServisi.php`

Bu raporun amacı yeni geliştirmeyi sınırlamak değil; değişikliğin doğru katmanda yapılmasını, bağlı modüllerin aynı muhasebe sözleşmesini kullanmasını ve gereksiz dosya/bağlam taşınmamasını sağlamaktır.
