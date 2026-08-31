# Muhasebe Canlı Tarayıcı QA Raporu

## Durum

- Tarih: 2026-08-26
- Hedef: `http://127.0.0.1:8000/`
- Kullanıcı: `qa_full_admin`
- Firma: `qa-full-20260821` / QA Full SaaS Test Firması
- Aşama: Oturum doğrulandı; muhasebe ana paneli testi başlatıldı.

## Oturum ve ana panel

- Eski site admin URL'si: `/admin`
- Oturumdaki firma: `QA Full SaaS Test Firması` (`QA Full Test`)
- Oturumdaki kullanıcı: `QA Full Test Yöneticisi`
- Firma durumu: Aktif
- Aktif modül sayısı: 14
- Muhasebe paneli: `/admin/muhasebe/muhasebe-panel`
- Panel yükleme sonucu: **PASS**
- Panelde gözlenen KPI'lar: tahsilat bugün `0,00 TRY`, ödeme bugün `0,00 TRY`, açık onaylı fatura `26.540,00 TRY`, kritik stok `2`, negatif stok bayrağı `0`.
- Mevcut QA verileri listeleniyor: **PASS**
- Not: İlk ekran mevcut test verileri içeriyor; yeni senaryolar mevcut kayıtlardan ayrıştırılacak.

## Cari oluşturma — Senaryo MUH-CARI-001

- Ekran: `/admin/muhasebe/cari-yonetimi/cariler/create`
- Veri: `QA-20260826-MUH-CARI`
- Tür: Müşteri
- Para birimi: TRY
- Adres, vergi ve iletişim alanları dolduruldu.
- Kaydetme sonucu: **PASS**
- Oluşan cari kodu: `CR-1015`
- Detay ekranı ve `Oluşturuldu` bildirimi doğrulandı.
- Gözlem: Formdaki `Telefon` ve `İl` label'ları strict locator açısından birden fazla eşleşiyor; testte exact role locator kullanıldı. Bu, otomasyon/test edilebilirlik iyileştirmesi adayıdır.

## Stok kartı — Senaryo MUH-STOK-001 — çoklu birim ve parçalı kullanım

- Ekran: `/admin/muhasebe/stok/stok-listesi/create`
- Hedef veri: `QA-20260826-MUH-COKLU-BIRIM`
- İlk gözlem: Stok takip yöntemi `Uzunluk + Adet`, birim `Metre`, mevcut stok `100` ve kritik seviye `10` girilebildi.
- İlk ara kontrolde `Uzunluk + Adet` seçimi sonrasında yöntem ekranda `Standart` değerine geri döndü; yöntemi son adımda seçerek akışa devam edildi.
- Kontrollü tekrar: Yöntem son seçildiğinde `Uzunluk + Adet` ve `Metre` kayda doğru işlendi.
- Ölçü yapısı `Sabit ölçü`, giriş birimi `metre`, boy `2.5`, açılış adedi `10`, parçalı kullanım açık olarak girildi.
- İlk gönderim sonrası geçici hata bildirimi görünmesine rağmen işlem arka planda tamamlandı; liste ve detay ekranında kayıt bulundu.
- Oluşan stok kodu: `STK000019`.
- Liste sonucu: `Uzunluk + Adet`, `2,5 m`, mevcut `10`, toplam `25 m`.
- Parçalı kullanım senaryosu: Formda açıklandı ve işaretlendi; detay ekranında açık/kapalı durumu görünür bir alan olarak sunulmadı, ayrıca satış ekranında ayrıca doğrulanacak.
- Yeni bulgu: Formda satış `125` ve alış `80` girilmesine rağmen liste `₺0,00`, detaydaki fiyat alanları boş görünüyor. Fiyatların sekme değişimi veya kaydetme sırasında kaybolduğu doğrulanmalı.
- Sonuç: **PARTIAL PASS / P1 açık bulgu**
- Öncelik: **P1 — çoklu birim kaydı oluşuyor; fiyat snapshot'ı ve parçalı satış zinciri henüz doğrulanmadı**

### Fiyat kaybı doğrulaması

- Düzenleme ekranında satış fiyatı `0.00000000`, alış fiyatı `0.00000000` olarak okundu.
- Detay ekranında fiyat alanları da 0/boş göründü.
- Sonuç: **FAIL — girilen fiyatlar kayda taşınmıyor**.

## Test ortamı kesintisi — 2026-08-26 16:xx

- Fiyat düzenleme formunda satış `125`, alış `80` girildi ve `Kaydet` gönderildi.
- Sonraki detay ekranı navigasyonunda Chrome `net::ERR_CONNECTION_REFUSED` verdi.
- Yerel uygulama portu `127.0.0.1:8000`: kapalı.
- PHP süreci gözlenmedi.
- Sonuç: **BLOCKED**
- Öncelik: **P0 — uygulama sunucusu kapandı; devam testleri geçerli şekilde yürütülemez**.
- Bu olayın fiyat güncellemesinin kalıcılığıyla nedensel ilişkisi henüz kurulmadı; sunucu yeniden açıldıktan sonra tekrar doğrulanmalıdır.

## Stok hareketleri ve kritik stok

- `/admin/muhasebe/stok/stok-hareketleri`: **PASS**; yeni stok için `acilis` hareketi, miktar `25,0000`, önceki `0`, sonraki `25` olarak listelendi.
- `/admin/muhasebe/stok/kritik-stoklar`: **PASS**; liste ve server-side pagination görünür. Yeni ürün mevcut `25` ve kritik `10` olduğu için kritik listede yer almadı.

## Fatura listesi ve oluşturma — Senaryo MUH-FAT-001

- `/admin/muhasebe/fatura-kaynagis`: **PASS**; mevcut onaylı gelen/iade faturaları listeleniyor.
- `/admin/muhasebe/fatura-kaynagis/create/giden-fatura`: ekran **PASS** olarak açıldı.
- Fatura cari seçicisi aranarak `QA-20260826-MUH-CARI`, `QA` ve mevcut `QA-CLOSE-A` kayıtları denendi.
- Sonuç: Arama kutusu doluyor ancak listbox sonuç üretmiyor; cari seçilemiyor.
- Sonuç: **BLOCKED / FAIL**
- Öncelik: **P1 — cari seçilemediği için giden fatura oluşturma akışı başlatılamıyor**.

## Mevcut onaylı fatura detay ekranı — Senaryo MUH-FAT-002

- Kayıt: fatura ID `162`, tür `Gelen Fatura`, sınıf `Stok alışı`, durum `Onaylı`.
- Detay/düzenleme ekranı açıldı: **PASS**.
- Onaylı kayıt için `Düzenlenemez`, `İptal Et` ve `İade Et` akışları görünür.
- Stok kalemi `QA-PRET-FINAL-B-STOCK` ve fatura operasyon bilgileri görüntüleniyor.
- İptal/iade işlemleri finansal/stok etkili olduğu için bu ilk taramada tetiklenmedi; oluşturma/cari seçici blokajı giderildikten sonra kontrollü QA kaydıyla çalıştırılacak.

## Finans paneli — Senaryo MUH-FIN-001

- `/admin/muhasebe/finans/finans-panel`: **PASS**; panel açıldı ve firma/aktif kayıt kapsamı mesajı görüldü.
- Gözlenen özetler: toplam kasa `1.560,00 TRY`, toplam banka `-8.640,00 TRY`, açık tahsilat `5.080,00 TRY`.
- Son finans hareketlerinde tahsilat/ödeme kayıtları, kaynak-hedef ve fatura bağlantıları listeleniyor.
- Tahsilat/ödeme/virman oluşturma adımları sonraki kontrollü QA kayıtlarında tetiklenecek; finansal kayıt oluşturma işleminden hemen önce işlem onayı gereklidir.

## Yapılan kontroller

1. Chrome mevcut uygulama sekmesi açıldı.
2. Yönetici Girişi ekranı görüntülendi.
3. `qa_full_admin` ile yönetici girişi denendi; uygulama `Kullanici bilgisi veya sifre hatali.` uyarısı verdi.
4. Repository QA seed kaynağı kontrol edildi. Firma girişi için doğru değerler doğrulandı:
   - Firma kodu: `qa-full-20260821`
   - Kullanıcı adı: `qa_full_admin`
   - Kullanıcı e-postası: `qa-full-admin@yalovayazilim.test`
5. E-posta ile ikinci giriş denemesinde sayfa yanıtı kararsızlaştı ve Chrome DOM yüklemesi tamamlanmadı.

## Bloklayıcı hata

Uygulama logunda giriş isteği sırasında `settings` tablosu okunurken veritabanı bağlantısı reddediliyor:

```text
SQLSTATE[HY000] [2002] Hedef makine etkin olarak reddettiğinden bağlantı kurulamadı
Connection: mysql
SQL: select `value`, `key` from `settings`
```

Yerel port kontrolü:

- `127.0.0.1:3307`: kapalı (`False`)
- `127.0.0.1:3306`: açık (`True`)

Proje devir belgesine göre izole QA MariaDB portu `3307` olmalıdır. Uygulama bu porta erişemediği için firma kodlu giriş ve muhasebe canlı tarayıcı testleri başlatılamadı.

## Test sonucu

- Sonuç: **BLOCKED**
- Öncelik: **P0 — test ortamı erişilemiyor**
- Uygulama kodunda değişiklik yapılmadı.
- Şifre rapora veya dosyaya yazılmadı.

## Devam koşulu

İzole QA MariaDB servisi `127.0.0.1:3307` üzerinde çalışır hâle getirildikten sonra Chrome’da Firma Girişi ekranı firma koduyla yeniden açılacak ve muhasebe senaryoları ekran ekran yürütülecektir.

## Ek tarama notu — 26.08.2026 18:54 sonrası

Yukarıdaki ilk blokaj daha sonra canlı QA oturumu üzerinden aşıldı; mevcut Chrome oturumunda firma `QA Full Test`, kullanıcı `QA Full Test Yöneticisi` olarak doğrulandı. İlk bölümdeki **BLOCKED** sonucu başlangıç anına aittir; tüm muhasebe testinin nihai sonucu değildir.

- Finans paneli yeniden doğrulandı: **PASS**. Toplam kasa `1.560,00 TRY`, toplam banka `-8.640,00 TRY`, açık tahsilat `5.080,00 TRY`.
- Kur farkı hareketleri `/admin/muhasebe/finans/kur-farki-hareketleri`: **PASS**; filtre/search alanları ve boş sonuç durumu düzgün açıldı.
- Finans panelindeki son hareketler ve kaynak-hedef gösterimleri mevcut QA kayıtlarıyla okunabiliyor.
- Tarama sırasında uygulama sunucusunun kısa süreli erişilemez olması ayrıca **P0 ortam kararlılığı** olarak kaydedilmiştir; sonrasında uygulama tekrar erişilebilir oldu.

### Rapor ekranları

- Gelir / gider raporu: **PASS**; 01.08.2026–26.08.2026 döneminde 42 onaylı fatura, 19 gelir, 17 gider, 2 hızlı masraf ve TRY kırılımı hesaplanıyor. Net `-32.240,00 TRY`.
- Kar / Zarar Raporu: **FAIL/P1**; ekran açılıyor fakat açıkça “yapısal iskelettir, iş mantığı ve veritabanı bağlantısı sonraki aşamalarda eklenecektir” mesajı gösteriyor; rapor üretmiyor.
- Bilanço Raporu: **FAIL/P1**; aynı iskelettir mesajı var, bilanço hesaplaması yok.
- İş Analitiği: **PASS**; KPI, 7 günlük trend, ciro ve operasyon özeti alanları açılıyor; mevcut test firmasında sipariş KPI'ları 0 ve “Kayıt yok” durumları anlamlı biçimde gösteriliyor.

### Kasa / banka ekranları

- `/admin/muhasebe/finans/kasalar` isteği `/admin/muhasebe/finans/kasa-hesaplari` listesine yönleniyor: **PASS**; kasa listesi, bakiye, para birimi, durum, görüntüle/düzenle/pasifleştir aksiyonları görünüyor.
- `/admin/muhasebe/finans/bankalar` isteği `/admin/muhasebe/finans/banka-hesaplari` listesine yönleniyor: **PASS**; banka, hesap sahibi, para birimi, bakiye ve durum kolonları görünüyor.
- `/admin/muhasebe/finans/finans-fisleri` isteği finans hareketleri listesine yönleniyor; bu nedenle ayrı finans fişi ekranı için **P1 route/menü doğrulaması** gereklidir.

### Finans işlem formları

- Tahsilat formu: **PASS**; cari, opsiyonel proje, kaynak tutar/para birimi, kasa-banka-POS kanalı, hesap, tarih, açıklama ve opsiyonel referans fatura alanları mevcut. Kapama önerisi alanı fatura seçimine bağlı.
- Ödeme formu: **PASS**; kasa/banka, cari, proje, tutar, tarih, açıklama ve borç yönlü referans fatura alanları mevcut.
- Transfer (virman) formu: **PASS**; kaynak/hedef türü ve hesap, kasa-banka-POS seçenekleri, para birimi, tutar, tarih ve açıklama alanları mevcut.
- Bu üç formun kayıt butonları finansal yan etki oluşturduğu için doldurma ve kaydetme adımı kontrollü onay bekliyor; boş form validasyonu ayrıca yürütülecek.
- Tahsilat boş formunda kaydet butonu disabled duruma geçti; eksik zorunlu alanlarla kayıt oluşturulmasına izin verilmedi: **PASS**.
- Son onay sonrası tahsilat kaydı için cari seçimi denenmiştir. Hem `QA-20260826-MUH-CARI` hem de mevcut `QA-CLOSE-B-FINAL-CUSTOMER` aranmış, arama alanı dolmasına rağmen listbox sonuç üretmemiştir. Bu nedenle finans kaydı oluşturmaya geçilemedi; mevcut fatura cari seçici problemine ek olarak finans formlarında da **P1 cari seçenekleri yüklenmiyor** bulgusu açılmıştır. Tutar yazılıp yanlış/eksik cari ile kayıt oluşturulmamıştır.
- Virman kaydı başarıyla oluşturuldu: kaynak `QA-CLOSE-B-FINAL-CASH`, hedef `QA-CLOSE-B-FINAL-BANK`, tutar `240 TRY`, açıklama `QA muhasebe virman testi 2026-08-26`. Form sonrası finans paneline yönlendi ve yeni açıklama panel hareketlerinde göründü: **PASS**.
- Virman formunda ilk kaydet denemesi tutar dolu görünmesine rağmen `tutar alanı zorunludur` validasyonu verdi; alan yeniden doldurulup tekrar kaydedildiğinde kayıt oluştu. Bu durum **P2 Livewire/numeric binding kararsızlığı** olarak izlenmelidir.

### Fatura alt listeleri ek taraması

- Giden Faturalar: **PASS**; ekleme bağlantısı, özet yükleme, arama/filtre/sütun kontrolü ve server-side tablo başlıkları mevcut.
- Gelen Faturalar: **PASS**; ekleme bağlantısı ve aynı tablo kontrolleri mevcut.
- Bekleyen Faturalar: **PASS/EMPTY**; ekran ve ekleme bağlantısı açılıyor, aktif bekleyen kayıt yok mesajı gösteriliyor.
- Gelen İade Faturaları: **PASS**; ekleme bağlantısı, özet, arama/filtre/sütun kontrolleri ve tablo kolonları açılıyor.
- Giden İade Faturaları: **PASS**; ekleme bağlantısı, özet, arama/filtre/sütun kontrolleri ve tablo kolonları açılıyor.
- Gider Faturası: **PASS/EMPTY**; liste ve ekleme bağlantısı açılıyor, aktif kayıt yok mesajı gösteriliyor.
- Proforma Fatura: **PASS/EMPTY**; liste ve ekleme bağlantısı açılıyor, aktif kayıt yok mesajı gösteriliyor.
- `/admin/muhasebe/faturalar/iade-faturalar` doğrudan **404 / yanlış route** ile public “Sayfa bulunamadı” ekranına gidiyor: **P1**. İade işlemi için menüdeki gerçek gelen/giden iade yolları kullanılmalı veya bu route düzeltilmeli.
- Tüm Faturalar: **PASS**; Fatura Ekle bağlantısı, özet, arama/filtre/sütun kontrolleri ve birleşik tablo açılıyor.

### Depo/stok ekranları ek taraması

- Depo Stokları: **PASS**; depo seçimi (Tüm depolar ve firma depoları), stok arama, fiziksel/rezerve/kullanılabilir miktar, birim maliyet, stok değeri ve toplam ölçü kolonları mevcut.
- Depo Sayım Geçmişi: **PASS/EMPTY**; sayım öncesi/sonrası açıklaması, server-side tablo kontrolleri ve “Sayım Kaydı Yok” durumu mevcut.
- Depo Transfer Geçmişi: **PASS**; tarih, transfer no, stok, kaynak/hedef depo, miktar, durum ve açıklama kolonları mevcut.
- Depolar Arası Transfer: **PASS/FORM**; stok, kaynak depo, hedef depo, miktar, tarih ve açıklama alanları ile Transferi kaydet butonu mevcut. Kayıt oluşturma adımı ayrıca aksiyon-zamanı güvenlik onayı gerektirdiğinden tetiklenmedi.
- Seri No Barkodları: **PASS**; depo ve durum filtreleri (stokta/satılan/çıkış/tüm), seri-barkod araması, CSV/Excel uyumlu CSV ve yazdırma aksiyonları mevcut. Satılmış seri kayıtları için son satış fiyatı ve gerçekleşen kâr alanı tanımlı.
- Cari Hareketleri: **PASS/EMPTY**; arama, filtre ve sütun görünürlüğü kontrolleri açılıyor; aktif görünür hareket bulunmayan durumda boş tablo davranışı izleniyor.
- Cari Yaşlandırma: **PASS**; para birimi filtresi ve gün aralıkları (vadesi gelmemiş, 1–30, 31–60, 61–90, 90+) ile cari bazlı bakiye tablosu çalışıyor. QA cari kaydı `0.00` bakiye ile listeleniyor.
- Depo Stok Sayımı: **PASS/FORM**; basit stok/depo seçimi, sayım sonucu, açıklama ve “Sayımı uygula” aksiyonu mevcut. Seri numaralı ürünlerin ayrı akıştan yönetileceği açıkça belirtiliyor; kayıt aksiyonu tetiklenmedi.
- Birimler: **PASS**; ekleme bağlantısı, arama/filtre/sütun kontrolleri ve sabit kod/ad/varsayılan/aktif kolonları mevcut.
- Vergi Oranları: **PASS**; ekleme bağlantısı, kod/ad/oran/aktif kolonları ve tablo arama yapısı mevcut. Filtre/sütun butonları kayıt durumuna göre disabled görünüyor.
- Cari Grupları: **PASS**; ekleme bağlantısı, arama/filtre/sütun kontrolleri ve firma/kullanıcı kapsamı doğrulandı.
- Marka/Model/Tasarım/Varyant tanım route'u: **FAIL/P1**; `/admin/muhasebe/tanimlar/marka-model-tasarim-varyant` admin yerine public 404 sayfasına düşüyor.
- Giden Fatura oluşturma formu: **PASS/FORM**; cari, proje, tür/durum, tarih-vade, belge bilgileri, e-belge tipi, para birimi/kuru, stok kalemi, miktar/birim fiyat/KDV/iskonto, toplamlar ve oluşturma aksiyonları mevcut. Cari seçici blokajı nedeniyle yeni kayıt oluşturma akışı başlatılamadı.
- Giden fatura oluşturma ve çoklu birimli stok detay sayfalarında tarayıcı console error/warning kaydı gözlenmedi; uygulama içi fonksiyonel P1 bulguları yine de devam ediyor.
- 27.08.2026 devam taramasında uygulama yeniden başlatılmaya çalışıldı ancak `laravel-core/public/index.php` dosyası fiziksel olarak bulunamadı. `php -S` bu nedenle Laravel front controller'ı açamadı; `php artisan serve` de 8000 portuna dinleme başlatamadı. Canlı tarama bu noktada **P0 ortam blokajı** ile durdu. Dosya oluşturulmadı/değiştirilmedi; mevcut kullanıcı değişikliklerine dokunulmadı.
- 27.08.2026 yeniden teşhis: doğru document root'un `public_html` olduğu doğrulandı; `public_html/index.php` mevcut ve ana sayfa HTTP 200 dönüyor. MySQL `127.0.0.1:3306` dinliyor, `.env` de DB_PORT=3306 gösteriyor. `/admin` HTTP 302 ile `/yonetici-giris` adresine, `/yonetici-giris` ise HTTP 302 ile `/` adresine dönüyor; dolayısıyla public site açılırken yönetici giriş/panel akışı açılamıyor. Bu, **P1 yönetici giriş yönlendirme/oturum teşhisi** olarak kaydedildi. `public/index.php` bu projede beklenen document root değildir; eksik olması tek başına proje açılış hatası değildir.
- 27.08.2026 düzeltmesi: `OnePagePublicSiteMiddleware` içine `giris`, `yonetici-giris`, `uye-giris`, `kayit`, `uye-kayit` ve `firma-kodumu-bul` kimlik doğrulama yolları eklendi. Böylece public tek-sayfa fallback'i giriş controller'larını engellemiyor.
- Düzeltme doğrulaması: `GET /yonetici-giris` HTTP 200 dönüyor; Chrome'da Yönetici Girişi formu görüldü. `GET /admin` artık `/yonetici-giris` adresine yönleniyor ve login ekranı görünüyor.
- Otomatik doğrulama: `tests/Feature/GirisEkranlariTest.php` — **4 test geçti, 24 assertion**. PHP lint — **PASS**.
- 27.08.2026 canlı doğrulama: `public_html` document root ile PHP sunucusu yeniden başlatıldı. Firma giriş akışı üzerinden QA oturumu açıldı ve `/admin` ekranında firma `QA Full Test`, kullanıcı `QA Full Test Yöneticisi` olarak doğrulandı. Yönetici giriş endpoint'inin 302 davranışı artık root'a düşürme değil, doğru firma giriş akışına yönlenme olarak çalışıyor.
- Finans mutabakatı canlı doğrulaması: `Finans mutabakatı` ekranı **PASS**; bulgu sayısı `0`, kritik bulgu `0`, toplam bulgu `0`.
- 240 TRY virman sonrası finans paneli tekrar okundu; QA açıklaması hareketlerde mevcut ve özetler kasa `1.320,00 TRY`, banka `-8.400,00 TRY` olarak güncellendi. Kaynak/hedef bakiye etkisi **PASS**.
- 27.08.2026 gelir-gider raporu yenileme aksiyonu çalıştırıldı: **PASS**; dönem 01.08.2026–27.08.2026, 42 onaylı fatura ve TRY kırılımı korunuyor.

### 27.08.2026 devam taraması — muhasebe satış/entegrasyon ekranları

- Barkod Etiket Yazdırma (`/admin/muhasebe/satis/barkod-etiket-yazdirma`): **PASS**; ekran açıldı, filtre/etiketleme arayüzü görüldü, tarayıcı hata günlüğü boş.
- Barkod Eşleme Listesi (`/admin/muhasebe/satis/barkod-esleme-listesi`): **PASS**; ekran açıldı, stok/barkod eşleme listesi görüldü, tarayıcı hata günlüğü boş.
- Barkodlu Satış Ayarları (`/admin/muhasebe/satis/barkodlu-satis-ayarlar`): **PASS**; ayar formu açıldı, tarayıcı hata günlüğü boş.
- Barkodlu Satış Geçmişi (`/admin/muhasebe/satis/barkodlu-satis-gecmisi`): **FAIL/P1**; sayfa başlığı ve kabuk yüklenmesine rağmen içerikte HTTP 500 / Server Error iframe'i oluşuyor. Console error kaydı üretilmedi; sunucu tarafı hata ayrıntısı uygulama loglarından ayrıca incelenmeli.
- Barkodlu Satış İade Geçmişi (`/admin/muhasebe/satis/barkodlu-satis-iade-gecmisi`): **FAIL/P1**; ekran kabuğu açılıyor ancak içerikte HTTP 500 / Server Error iframe'i var. Console error kaydı üretilmedi.
- Barkodlu Satış Muhasebe Mutabakatı (`/admin/muhasebe/satis/barkodlu-satis-muhasebe-mutabakat`): **PASS**; mutabakat ekranı açıldı, tarayıcı hata günlüğü boş.

Bu iki HTTP 500 bulgusu nedeniyle barkodlu satış geçmişi ve iade geçmişinin kayıt listeleme/ayrıntı/iade bağlantıları test edilemedi; muhasebe modülü kapanış kriteri olarak açık kaldı.

### HTTP 500 kök neden doğrulaması

- `laravel-core/storage/logs/laravel.log` içinde iki ekranın Livewire güncellemesinde aynı kök neden doğrulandı: `The "intl" PHP extension is required to use the [currency] method.`
- Hata, Filament `TextColumn` para biçimlendirmesinin `Illuminate\Support\Number::currency()` çağrısında oluşuyor; çağrı sırasında para değeri `12.0`, para birimi `TRY`, locale `tr` olarak loglandı.
- İstisna `BarkodluSatisIadeGecmisiSayfasi` tablosunun para kolonu render edilirken tetikleniyor ve `POST /livewire/update` isteği HTTP 500 dönüyor. Aynı `currency` formatlama paterni Barkodlu Satış Geçmişi ekranında da 500 üretmiş durumda.
- Bulguyu uygulama kodunu değiştirmeden, canlı browser + sunucu logu korelasyonu ile doğruladım. Çözüm adayı: canlı PHP CLI/web SAPI için `intl` uzantısının etkinleştirilmesi veya para kolonunun uzantı bağımlılığı olmayan güvenli formatter'a alınması; çözüm sonrası iki ekranın yeniden canlı test edilmesi gerekir.

### 27.08.2026 yardımcı finans/sistem ekranları

- Çek Yönetimi (`/admin/muhasebe/finans/cek`): **PASS**; ekran açıldı, 500/404 gözlenmedi.
- Senet Takibi (`/admin/muhasebe/finans/senet`): **PASS**; ekran açıldı, 500/404 gözlenmedi.
- Kur Farkı Hareketleri (`/admin/muhasebe/finans/kur-farki-hareketleri`): **PASS**; ekran açıldı, hata günlüğü boş.
- Nette Fatura Entegrasyonu (`/admin/muhasebe/entegrasyonlar/nette-fatura`): **PASS/FORM**; entegrasyon ekranı açıldı, 500/404 gözlenmedi.
- Sistem Olayları (`/admin/muhasebe/sistem-olaylari`): **FAIL/P1**; ekran HTTP 500 ile açılıyor. `laravel.log` kök nedeni yine eksik `intl` uzantısı: Filament pagination görünümünde `Illuminate\Support\Number::format(1)` çağrısı uzantı yokluğu nedeniyle patlıyor.

`intl` eksikliği tek bir ekranla sınırlı değildir: para kolonları `currency`, pagination ise `format` çağrısıyla etkileniyor. Bu nedenle PHP web SAPI yapılandırması düzeltilmeden muhasebe içindeki liste ekranlarının tamamı için güvenilir kapanış testi yapılamaz.

Canlı sunucu kök nedeninin son doğrulaması: sistemde PATH üzerindeki `php` Winget PHP 8.2.33 binary'sine işaret ediyor ve `intl` modülü listelenmiyor; buna karşılık `C:\xampp\php\php.exe` PHP 8.2.12 ile `intl=yes` dönüyor. Mevcut `php -S` sunucusu PATH üzerindeki uzantısız PHP ile çalıştığı için browser isteklerinde hata oluşuyor. Bu, uygulama kodundan bağımsız **P0/P1 test ortamı yapılandırma bulgusu** olarak kaydedildi.

### 27.08.2026 `intl` destekli PHP ile yeniden doğrulama

- Sunucu durdurulup `C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public_html` ile yeniden başlatıldı; PHP web sunucusu 8.2.12 olarak başladı ve `intl` yüklü.
- Barkodlu Satış Geçmişi: **PASS**; ekran ve Livewire güncellemesi HTTP 200.
- Barkodlu Satış İade Geçmişi: **PASS**; ekran, tablo ve Livewire güncellemesi HTTP 200.
- Sistem Olayları: yeniden yükleme sırasında ağır Livewire yanıtı gözlendi; sunucu logunda ilgili güncelleme HTTP 200 tamamlandı. `intl` kaynaklı önceki 500 tekrarlanmadı; performans notu olarak izlenmelidir.
- Önceki üç ekran için açılan **P1 ortam kaynaklı 500** bulgusu çözümlenmiş/yeniden testte kapatılmıştır. Kar/Zarar ve Bilanço iskelet bulguları uygulama eksikliği olarak açık kalmaktadır.

### 27.08.2026 finans hesapları ve para tanımları ek taraması

- Kasa Hesapları (`/admin/muhasebe/finans/kasa-hesaplari`): **PASS**; hesap tablosu, bakiye/para birimi/durum ve yönetim aksiyonları açıldı.
- Banka Hesapları (`/admin/muhasebe/finans/banka-hesaplari`): **PASS**; banka hesap tablosu ve bakiye/para birimi/durum bilgileri açıldı.
- POS’lar (`/admin/muhasebe/finans/poslar`): **PASS**; POS ekranı açıldı, 500/404 gözlenmedi.
- Para Birimleri (`/admin/muhasebe/tanimlar/para-birimleri`): **PASS**; tanım listesi açıldı.
- Döviz Kurları (`/admin/muhasebe/tanimlar/doviz-kurlari`): **PASS**; kur listesi ve ekran kabuğu açıldı.
- Ödeme Yöntemleri (`/admin/muhasebe/tanimlar/odeme-yontemleri`): **PASS**; tanım ekranı açıldı.

### 27.08.2026 stok/tanım ek taraması

- Stok Kategorileri: **PASS**; `/admin/muhasebe/tanimlar/stok-kategorileri` açıldı.
- Stok Türleri: **PASS**; `/admin/muhasebe/tanimlar/stok-turleri` açıldı.
- Depolar: **PASS**; `/admin/muhasebe/tanimlar/depolar` açıldı.
- Malzeme Türleri: **PASS**; `/admin/muhasebe/tanimlar/malzeme-turleri` açıldı.
- Ürün Markaları: **PASS**; `/admin/muhasebe/tanimlar/markalar` açıldı.
- Stok Modelleri: **PASS**; `/admin/muhasebe/tanimlar/stok-modelleri` açıldı.
- Tasarımlar: **PASS**; `/admin/muhasebe/tanimlar/tasarimlar` açıldı.
- Varyantlar: **PASS**; `/admin/muhasebe/tanimlar/varyantlar` açıldı.
- Marka Üreticileri: **PASS**; doğru adres `/admin/muhasebe/tanimlar/marka-ureticileri` ile ekran açıldı.

### 27.08.2026 finans hesap/tanım oluşturma formları

- Kasa Ekle, Banka Ekle, POS Ekle, Para Birimi Ekle ve Kur Ekle formları canlı oturumda açıldı; 500/404 görülmedi: **PASS/FORM**.
- Boş Kur Ekle formunda `Oluştur` butonu DOM'da etkin görünüyor. Boş gönderim finans/tanım kaydı oluşturabileceğinden aksiyon tetiklenmedi; zorunlu alanların sunucu validasyonu ayrıca kontrollü negatif test olarak açık tutuldu.

### 27.08.2026 fatura oluşturma türleri form taraması

- Gelen Fatura Ekle: **PASS/FORM**; cari/tedarikçi, stok/kalem alanları ve fatura form kabuğu açıldı.
- Giden Fatura Ekle: **PASS/FORM**; cari/müşteri, stok/kalem alanları ve fatura form kabuğu açıldı.
- Gelen İade Faturası Ekle: **PASS/FORM**; cari ve stok/kalem alanları açıldı.
- Giden İade Faturası Ekle: **PASS/FORM**; cari ve stok/kalem alanları açıldı.
- Gider Faturası Ekle: **PASS/FORM**; cari ve stok/kalem alanları açıldı.
- Proforma Fatura Ekle: **PASS/FORM**; cari ve stok/kalem alanları açıldı.
- Altı oluşturma formunda da `intl` destekli PHP ile 500/404 gözlenmedi. Cari seçici arama sonuçlarının daha önce boş kalması nedeniyle gerçek fatura kaydı oluşturma akışı hâlâ P1 blokajlıdır; bu formlarda kayıt düğmesine basılmadı.

### 27.08.2026 birleşik fatura listesi kontrolü

- Tüm Faturalar ekranı canlı oturumda yeniden açıldı: **PASS**; arama kutusu, `Filtrele`, `Sütunları göster/gizle`, tarih filtresi, özet yükleme ve `Sonraki` sayfalama kontrolü mevcut.
- Arama alanına `QA` girildi ve Livewire yanıtı sonrası ekran HTTP 200 kaldı; 500/404 oluşmadı.
- Arama temizlenip sayfalama durumu tekrar kontrol edildi; liste tekrar yüklendi ve `Sonraki` kontrolü görünür kaldı: **PASS**.

### 27.08.2026 cari seçici yeniden doğrulaması

- Fatura oluşturma ekranında `Taraf > Cari` Choices alanı önce açıldı, ardından görünür `Aramak için yazmaya başlayın...` alanına `QA-FINAL-A` tuş bazlı yazıldı ve sonuçların yüklenmesi beklendi.
- Sonuçlar doğru şekilde listelendi: `CR-1005 - QA-FINAL-A-MUSTERI` ve `CR-1006 - QA-FINAL-A-TEDARIKCI`. `CR-1006 - QA-FINAL-A-TEDARIKCI` seçildi; seçim formda görünür kaldı: **PASS**.
- Önceki “cari seçenekleri yüklenmiyor” bulgusu yanlış/erken kontrol kaynaklı olarak düzeltilmiştir. Yeni doğru test prosedürü: alanı aç → cloned Choices input'a tuş bazlı yaz → `Aranıyor...` durumunun bitmesini bekle → sonucu seç.
- Cari seçimi sonrası Giden Fatura türü seçildi; stok seçici açıldı ve çoklu birim/parçalı stok senaryosuna uygun stok/birim alanları ile miktar, birim fiyat, iskonto, KDV ve toplam alanları görünür: **PASS/FORM**.
- Onaylı canlı fatura senaryosunda `STK000019 - QA-20260826-MUH-COKLU-BIRIM` satırı seçildi, miktar `1.5` ve birim fiyat `125` girildi. İlk DOM durumunda değerler görünmesine rağmen `Net Toplam`, `Toplam Tutar` ve `KDV Dahil Tutar` `0` kaldı.
- Birim fiyat alanından `Tab` ile çıkılıp Livewire yanıtı beklendiğinde birim fiyat tekrar `0` oldu ve toplamlar da `0` kaldı. Bu nedenle parçalı miktarlı fatura kaydı **gönderilmedi**; yanlış/0 tutarlı finansal kayıt oluşturulmadı. Bulguyu **P1 fatura kalemleri hesaplama/Livewire binding** olarak açtım.

### 27.08.2026 tahsilat/ödeme cari seçici devam testi

- Tahsilat formunda cari Choices alanı açıldı; `QA-FINAL-A` aramasında `CR-1005 - QA-FINAL-A-MUSTERI` ve `CR-1006 - QA-FINAL-A-TEDARIKCI` sonuçları geldi: **PASS**.
- Ödeme formunda hesap alanı ile cari alanı ayrıştırıldı; doğru cari combobox açıldı ve aynı aramada iki cari sonucu geldi: **PASS**.
- Tahsilat/ödeme kaydetme aksiyonları tetiklenmedi; finansal yan etkili kayıtlar ayrı onay gerektirir.

### 27.08.2026 stok takip yöntemi geri dönme yeniden testi

- Yeni stok kartı formunda başlangıç değeri `Standart` olarak doğrulandı.
- `data.olculu_takip_turu` alanı `Uzunluk + Adet` seçildi; 450 ms Livewire beklemesi sonrası alan değeri hâlâ `uzunluk`, seçili seçenek hâlâ `Uzunluk + Adet` kaldı: **PASS**.
- `Fiyat` ve `Stok` sekmelerine geçilip geri dönüldü; takip yöntemi `Uzunluk + Adet` olarak korundu, 500 oluşmadı: **PASS**.
- Uzunluk modunda `Ölçü yapısı`, `Ölçü giriş birimi` ve ölçülü stok açıklamaları görünür. Ana `Birim` alanının disabled olması bu modda beklenen davranış; ölçü birimi ayrı alandan `metre` olarak yönetiliyor.
- Bu yeniden testte önceki “Standart’a geri dönme” davranışı tekrarlanmadı. Bulguyu **P2 intermittent / yeniden üretilemedi** durumuna çektim; özellikle takip yöntemi seçimi ile birim/ölçü alanlarının aynı anda değiştirildiği sıralı kullanıcı akışı çözüm sonrası tekrar test edilmelidir.

### 27.08.2026 stok kaydında geçici hata bildirimi P2 yeniden testi

- Yeni ölçülü/parçalı QA kaydı ile canlı gönderim yapıldı: `QA-20260827-P2-GECICI-HATA`.
- Senaryo: `Uzunluk + Adet`, sabit ölçü, `250 cm`, açılış adedi `3`, parçalı kullanım açık, satış fiyatı `125 TRY`, alış fiyatı `80 TRY`.
- Gönderim sonrasında oluşturma ekranı aynı URL’de kaldı; form butonları disabled/loading durumunda kaldı ve istemci tarafında başarılı yönlendirme/başarı bildirimi alınamadı. Bu, kullanıcıya işlemin tamamlanmadığı izlenimini veren davranışı **yeniden üretti**.
- Ayrı liste ekranında kayıt hemen oluşmuş olarak doğrulandı: stok kodu `STK000022`, kayıt ID `256`; liste değerleri `₺125,00`, mevcut `3`, toplam ölçü `7,5 m`, durum `Aktif`.
- Sonuç: kayıt ve stok açılış işlemi tamamlanıyor, ancak gönderim isteğinin UI tamamlanma durumu ile veritabanı sonucu eşleşmiyor. Önceki testte görülen geçici hata bildirimiyle birlikte değerlendirildiğinde bulgu **P2 doğrulandı**.
- Etki: kullanıcı aynı formu tekrar gönderirse mükerrer stok kartı oluşturma riski var. Başarılı işlemden sonra deterministik başarı bildirimi ve listeye yönlendirme; başarısız/timeout durumunda ise idempotency veya tekrar gönderim koruması gerekir.
- Gönderim hemen sonrasında `laravel.log` içinde `2026-08-27 04:20:25` zaman damgalı `SQLSTATE[HY000] [2002] ... bağlantı kurulamadı` hatası görüldü; ayrıntı sayfası da bu nedenle HTTP 500 verdi. Bu nedenle bu tekrarın UI semptomunu doğruladık, ancak bu koşuda semptomun doğrudan uygulama kodundan mı yoksa MySQL/MariaDB kesintisinin Livewire isteğini yarıda bırakmasından mı kaynaklandığı kesinleştirilemedi. Kod incelemesinde `CreateStokKarti::create()` içindeki `ValidationException` yakalayıcısının “Stok kartı oluşturulamadı” bildirimi ürettiği görüldü; uygulama bildirimi ile altyapı bağlantı hatası ayrıştırılmalıdır.

### 27.08.2026 P2 tekrar testi — MySQL yeniden çalışırken

- XAMPP MySQL yeniden başlatıldı; `SELECT 1` bağlantı kontrolü başarılı.
- Aynı ölçülü/parçalı senaryo ikinci kez gönderildi: `QA-20260827-P2-STABIL-TEKRAR`.
- Livewire isteği sunucu tarafında HTTP 200 döndü; PHP/Laravel logunda bu gönderime ait yeni hata oluşmadı.
- Buna rağmen tarayıcı oluşturma ekranında kaldı ve başarılı yönlendirme/başarı bildirimi görünmedi.
- Ayrı liste kontrolünde kayıt oluştu: `STK000023`, ID `257`; fiyat `125 TL`, mevcut `3`, toplam ölçü `7,5 m`.
- Detay ekranı da açıldı ve açılış hareketi `7,5`, birim maliyet `80` olarak görüldü.
- Sonuç: MySQL kesintisi ilk denemedeki 500’ü açıklıyor, fakat veritabanı stabilken de kayıt sonrası UI’nin tamamlanmaması devam ediyor. P2 **uygulama tarafında doğrulandı**; ayrıca altyapı kesintisi ayrı P0/P1 ortam bulgusu olarak izlenmelidir.

### 27.08.2026 stok kaydı P2 düzeltmesi

- Admin paneli SPA modunda stok oluşturma sayfasının Livewire başarı yönlendirmesini tamamlamadığı doğrulandığı için `muhasebe/stok/stok-listesi/create` yolu SPA istisnalarına alındı.
- Böylece bu form kayıt sonrasında SPA içi yönlendirme yerine tam sayfa yönlendirme kullanır; başarılı kayıt yanıtının tarayıcıda takılı kalması ve tekrar gönderim riski azaltılır.
- Düzeltme sonrası oluşturulan `QA-20260827-P2-FIX-SONRASI` kaydı liste ekranında `STK000024` / ID `258` olarak doğru göründü; ölçü, stok, fiyat ve parçalı kullanım değerleri korundu.
- Değiştirilen dosya: `laravel-core/app/Providers/Filament/AdminPanelProvider.php`.
- PHP lint kontrolleri başarılıdır. Uygulama sunucusu ve MySQL çalışır durumdadır.

### 27.08.2026 düzeltme sonrası canlı doğrulama

- `QA-20260827-P2-REDIRECT-TEST` kaydı gönderildi.
- Kayıt sonrası tarayıcı liste ekranına yönlendirildi: `/admin/muhasebe/stok/stok-listesi`.
- Liste kaydı doğrulandı: `STK000025`, ID `259`; `3` adet, `250 cm`, toplam `7,5 m`, satış fiyatı `125 TL`, parçalı kullanım senaryosu korunmuş.
- Sonuç: kayıt sonrası takılı kalma/geçici başarısızlık belirtisi bu düzeltme sonrası tekrar oluşmadı; P2 düzeltmesi **PASS**.

### 27.08.2026 stok fiyatları P1 inceleme ve düzeltme

- Eski kayıt `STK000019` / ID `253` kontrol edildi; fiyatların veritabanında gerçekten `0` olduğu ve düzenleme ekranına da `0` geldiği doğrulandı. Bu kayıt için geçmişte girilen `125/80 TL` değerleri sonradan geri getirilemez; veri kaybı daha önce oluşmuş.
- Yeni kayıt `STK000025` / ID `259` kontrolünde liste ve düzenleme ekranında fiyatlar doğruydu (`125/80 TL`), ancak detay ekranında boş görünüyordu.
- Kök neden bulundu: detay route binding sorgusu yalnızca `id`, `firma_id`, `ad`, `stok_miktari` alanlarını seçiyor; `alis_fiyati`, `satis_fiyati`, `kdv_orani` ve diğer detay alanları yüklenmiyordu.
- Düzeltme: detay görüntüleme için eksik kısıtlı `select` kaldırıldı; tam stok modeli yükleniyor. Detaydaki fiyat bölümü doğrudan model değerlerini görünür biçimde sunuyor.
- Tam model yüklenince ölçü bakiyesi tablosundaki mevcut `sprintf` hücre/değer sayısı hatası da açığa çıktı ve düzeltildi; aksi halde detay sayfası HTTP 500 veriyordu.
- Canlı doğrulama: detay ekranı HTTP 200, `₺80,00` alış ve `₺125,00` satış fiyatı görünür, ölçü stokları bölümü ve açılış hareketi görünür: **PASS**.
- P1 durumu: yeni kayıtlar için fiyatların “kaybolması” kapatıldı; eski kayıttaki gerçek veri kaybı ayrı veri düzeltme konusu olarak açık kaldı.

### 27.08.2026 stok fiyat P1 tekrar testi

- `STK000025` / ID `259` detay ekranı yeniden açıldı; HTTP 500 oluşmadı ve `₺80,00` alış / `₺125,00` satış değerleri görünür kaldı.
- Aynı kaydın düzenleme ekranında fiyat sekmesi kontrol edildi; satış `125.00000000`, alış `80.00000000` olarak korundu.
- Sonuç: yeni kayıtlar için fiyatların kaybolması P1 **PASS / kapatıldı**. ID `253` eski kaydındaki `0` değerleri geçmiş veri kaybı olarak ayrıca açık kaldı.

### 27.08.2026 stok fiyat P1 canlı kullanıcı tekrar doğrulaması
- Test kaydı: `STK000025` / `QA-20260827-P2-REDIRECT-TEST`.
- Liste ekranı: satış fiyatı `₺125,00` görüntülendi; kayıt mevcut. **PASS**
- Detay ekranı: alış `₺80,00`, satış `₺125,00`, ölçü stokları ve açılış hareketi görüntülendi; HTTP 500/server error yok. **PASS**
- Düzenleme > Fiyat sekmesi: alış `80.00000000`, satış `125.00000000` değerleri korundu; HTTP 500/server error yok. **PASS**
- Sonuç: yeni stok kaydında fiyat kaybolması tekrar üretilemedi; P1 düzeltmesi canlı tarayıcıda yeniden **PASS**. Eski ID `253` kaydındaki sıfır değerler geçmiş veri kaybı olarak açık kalır.

### 27.08.2026 madde 9 — fatura kalemi birim fiyatı ve toplam hesabı tekrar testi
- Giden fatura oluşturma ekranı doğru admin route üzerinden açıldı; yanlış `/giden-faturalar/create` yolu ise public 404 verdi. Kullanılan gerçek route: `/admin/muhasebe/fatura-kaynagis/create`.
- Stok `STK000025` seçildi, miktar `2`, birim fiyat `125` girildi.
- Alanlardan çıkıp Livewire yanıtı beklendikten sonra birim fiyat `125` olarak kaldı; ancak satır net toplamı `0`, fatura toplamı `0` kaldı.
- Server Error oluşmadı; bulgu hesaplama/binding kaynaklı fonksiyonel hata olarak tekrar üretildi.
- Sonuç: **FAIL / P1 açık**. Fatura kaydı gönderilmedi; yanlış tutarlı finansal kayıt oluşturulmadı.

### 27.08.2026 madde 9 ek inceleme — miktar alanının boş kalması
- Aynı fatura formunda ölçülü/parçalı stok (`STK000025`) seçildiğinde `Miktar` alanı boş kaldı; `Satış ölçüsü=Adet`, birim fiyat `125`, KDV `%20` görünür durumdaydı.
- Karşılaştırma amacıyla basit stok (`STK000001`) seçildiğinde de miktar alanı boş kaldı. Bu nedenle davranış doğrudan stok takip veya parçalı kullanım özelliğine özgü değil; ortak fatura repeater miktar alanı başlatma/hydrate akışında görülüyor.
- Kod incelemesi: `miktar` alanında `default(1)` var, ancak stok seçimi callback'i miktarı açıkça `1` ile doldurmuyor. Başlangıç repeater satırı boş state ile geliyor; `default(1)` bu durumda görünür state'e uygulanmıyor. Hesaplama da boş miktarı `0` kabul ediyor; ölçülü stokta otomatik ölçü dağılımı bu yüzden üretilemiyor.
- Sonuç: **P1 alt bulgu doğrulandı** — yeni satırda miktar kullanıcı tarafından elle girilmezse ekran sessizce `0` toplam gösterebiliyor. Stok takip/parçalı satış bunu tetiklemiyor, ancak ölçülü/parçalı akışta etkisi daha kritik.

### 27.08.2026 madde 9 miktar varsayılanı düzeltmesi
- Fatura kalemi `miktar` alanına hydrate aşamasında boş state için `1` varsayılanı eklendi; stok seçimi callback'lerinde de miktar `1` olarak başlatılıyor.
- PHP lint: **PASS**.
- Canlı doğrulama: fatura formu yenilendikten sonra stok seçimi öncesi satır miktarı `1` göründü; boş miktar kaynaklı sessiz `0` durumu giderildi. Kayıt gönderilmedi.
- Alan/m² için ek etiket veya adet eşdeğeri gösterimi eklenmedi; mevcut dönüşüm mantığı korunuyor.

### 27.08.2026 fatura depo/seri ayrıntıları UX sıkıştırması
- `Depo / Seri ayrıntıları` bölümü drawer/modal'a taşınmadan mevcut açılır-kapanır yapı içinde kompaktlaştırıldı.
- Uzun açıklama kaldırıldı, bölüm içi grid daha dar düzene alındı ve başlık/içerik padding değerleri azaltıldı.
- Seri ve garanti alanlarının koşullu görünürlük mantığı korunuyor; işlevsel davranış değişmedi.
- Değiştirilen dosyalar: `app/Filament/Clusters/Muhasebe/Resources/FaturaKaynagi.php`, `resources/css/filament/cork-admin-forms.css`.
- Vite production build ve PHP lint: **PASS**. Canlı fatura formu açıldı; satır yüksekliği ve miktar varsayılanı `1` olarak doğrulandı.
- Kullanıcı tekrar kontrolünde bölümün satır genişliğinin tamamını kapladığı görüldü; ek düzeltmede panel `fit-content` genişliğe alındı ve yalnız içeriği kadar yer kaplayacak şekilde CSS daraltıldı. Vite build: **PASS**.
- Derin incelemede section içindeki CSS'in tek başına yeterli olmadığı bulundu: Filament'in dış `col-[--col-span-default]` sarmalayıcısı `grid-column: 1 / -1` ile tüm satırı kaplıyordu. Dış sarmalayıcı da `:has(> .fatura-kalem-detay-paneli)` ile otomatik kolon ve içerik genişliğine alındı. Vite build ve PHP lint: **PASS**.
- Kullanıcının “tek satır” geri bildirimi üzerine section iki kolonlu yatay disclosure düzenine alındı: başlık ve depo/seri alanı aynı satırda, mobilde tek kolona düşüyor. Vite build: **PASS**.
- Son UX düzenlemesinde section kart kabuğu kaldırılarak başlık ve içerik repeater satırına alındı; depo/seri ayrıntıları artık ayrı büyük bir blok yerine satır içi kompakt aç/kapat alanı olarak gösteriliyor. Mobilde tek kolona düşüyor. Vite build: **PASS**.
- Kullanıcı önerisiyle başlık yüksekliği masaüstünde `5px` tabanına indirildi; tıklanabilirlik için gerçek satır yüksekliği `1.5rem` bırakıldı. Mobilde okunabilirlik için minimum `1.75rem` ve sarma davranışı korundu. Dış sarmalayıcıda minimum yükseklik sıfırlandı. Vite build: **PASS**.
- Son kontrolde daraltma davranışının bozulma nedeni bulundu: `.fi-section-content-ctn` üzerinde `display:flex !important` kullanımı Filament'in kapalı durumda uyguladığı `display:none` stilini eziyordu. Bu override kaldırıldı; section tekrar normal Filament aç/kapat akışına döndürüldü, yalnızca kompakt padding ve başlık yüksekliği bırakıldı. Vite build: **PASS**.
- Kullanıcı geri bildirimiyle neden analizi: `STK000020 - gokhan parçalı alan` veritabanında `olculu_takip_turu=alan`, `parcali_kullanima_izin=1` ve `depo_id=5` olan ölçülü stoktur. `depoAlanGosterilmeli()` ölçülü stoklarda depo alanını zorunlu görünür kıldığı için bölüm açılır; bu bir modal değildir. Depo modülü aktifken `varsayilanDepoIdForForm()` ölçülü stok için bilinçli olarak `null` döndürdüğü için mevcut stok deposu `Merkez Depo` yerine ekranda `Deposuz` görünmektedir. Bu, **P2 UX/varsayılan depo davranışı** olarak ayrıca izlenmelidir.
- Son düzenleme: başlık `Depo / Seri` olarak kısaltıldı, açıklama kaldırıldı; bölüm `compact + collapsible + collapsed` olarak bırakıldı. Böylece kapalı durumda yalnızca dar başlık/ok, açık durumda depo-seri alanları görünür. Miktar alanında boş hydrate state için `1` varsayılanı ve stok seçimi sırasında güvenli başlangıç eklendi. PHP lint ve Vite production build: **PASS**. Tarayıcı bağlantısı yeniden kurulamadığı için bu son görünüm doğrulaması canlı ekran üzerinde ayrıca beklemektedir.
- Canlı ekran geri bildirimi sonrası ek düzeltme: `visible` koşulu section'ın tamamını gizlediği için kullanıcı açma okunu göremiyordu. Section artık her fatura satırında kapalı kompakt başlık olarak görünür; depo/seri kontrolleri kendi koşullu görünürlükleriyle yalnızca gerektiğinde açılır. PHP lint ve Vite production build: **PASS**.
- Onay sonrası iş kuralı düzeltmesi: depo alanının görünürlüğü artık pozitif bakiye bulunan depo sayısına (`>1`) bağlı değil; aktif depo mevcutsa hedef depo seçimi açılabiliyor. Böylece standart, seri ve ölçülü stoklarda özellikle gelen faturada henüz bakiye yokken depo seçimi kaybolmuyor. Tek aktif/varsayılan depo hydrate aşamasında otomatik seçiliyor, çoklu depoda kullanıcı seçimi korunuyor. PHP lint: **PASS**; Vite build çalıştırıldı.
