# Yerel Tam Sistem Test Günlüğü — 30 Ağustos 2026

## Amaç

`yalova-yazilim-saas-v1.1` yerel veritabanında, izole QA firması ve tam yetkili firma yöneticisi ile modül erişimi, iş akışları, regresyon ve HTTP kontrollerini doğrulamak.

## Test izolasyonu

- QA firma kodu: `qa-full-20260821`
- QA kullanıcı: `qa-full-admin@yalovayazilim.test`
- Kullanıcı rolü: `firma_yoneticisi` (164/164 aktif tanımlı yetki)
- Modül durumu: tüm aktif sistem modülleri aktif
- Test verisi yalnız QA firması altında oluşturulacaktır.

## Çalışma kaydı

- 02:27 — `QaFullSaaSTestSeeder` çalıştırıldı. QA firması (ID 507), yönetici hesabı ve 14 etkin modül oluşturuldu.
- 02:31 — Firma listesinde 500 hatası gözlendi. Kök neden, Filament'in uzaktaki Bunny font sağlayıcısının 60 saniyelik zaman aşımıydı.
- 02:35 — Yönetim paneli yerel font sağlayıcısına geçirildi, önbellekler temizlendi ve yerel geliştirme sunucusu yeniden başlatıldı.
- 02:39 — Tarayıcı doğrulaması: `/admin/sistem-firmalar` başarıyla açıldı; 500 yok ve QA firması listede görünüyor.
- 02:43 — Tarayıcı doğrulaması: QA firma yöneticisi, ayrı `localhost` oturumunda firma kodu ile giriş yaptı. Panelde 14 modül görünür durumda.
- 02:45 — Tarayıcı modül açılışları başarılı: Muhasebe, Masraf Takibi, Proje Yönetimi, Personel Takip ve Restoran.
- 02:46 — Teklif listesinde 500 gözlendi. Apache eski PHP süreciyle çalıştığından `intl` uzantısı yüklenmemişti.
- 02:48 — Apache yeniden başlatıldı. Tarayıcı doğrulaması: Teklif listesi hatasız açılıyor.
- 02:50 — Tarayıcı modül açılışları başarılı: Ajanda ve Görevler, Barkodlu Satış, Teknik Servis, E-Ticaret Siparişleri ve Web Servis Yönetimi.
- 02:52 — Tarayıcı modül açılışları başarılı: Depo Stokları, Ürün Yönetimi ve Ayarlar / Mesaj Merkezi. Tüm 14 ana modül QA yöneticisi için görünür ve açılabilir.
- 02:53 — Tarayıcı form testi: `QA Test Müşteri AŞ` müşterisi oluşturuldu ve cari listesinde doğrulandı (`CR-1000`).
- 02:55 — Tarayıcı form testi: `QA Test Stok Kartı` oluşturuldu ve stok listesinde doğrulandı (`STK000001`, birim `AD`).
- 02:56 — Tarayıcı form testi: `QA Uçtan Uca Kontrol Görevi` oluşturuldu; QA firma kapsamındaki görev kaydı ile doğrulandı.
- 02:58 — Tarayıcı form testi: `QA Uçtan Uca Test Teklifi` oluşturuldu ve teklif listesinde doğrulandı (`TKL-2026-0001`, taslak, 0,00 TRY).
- 02:59 — Tarayıcı doğrulaması: Arızalı cihaz teknik servis kayıt formu hatasız açılıyor; cari, cihaz, marka, servis durumu ve müşteri şikayeti alanları mevcut.
- İncelenecek — Stok kartı oluşturma formunda girilen ana barkod, oluşturulan kayıtta boş kaldı. Ekran-kayıt eşlemesi ayrıca düzeltilmeden barkodlu satış akışı satışa hazır kabul edilmemeli.
- 03:04 — Muhasebe tarayıcı testi: ilk giden fatura (`ID 871`) stok ve cari seçimine rağmen 0,00 TRY olarak kaydedildi. Birim fiyat/KDV canlı hesaplaması submit anında tamamlanmadan kayıt alınabiliyor.
- 03:08 — Muhasebe tarayıcı testi: fiyat alanından çıkış yapıp canlı toplamlar güncellendikten sonra ikinci giden fatura (`ID 872`) doğru kaydedildi: ara toplam 100,00 TRY, KDV 20,00 TRY, ödenecek 120,00 TRY.
- Kritik bulgu — Fatura ekranında fiyat alanını güncelledikten hemen sonra kaydetme yarışı, sıfır toplamlı fatura üretme riski taşıyor. Satışa çıkış öncesi submit tarafında zorunlu sunucu tarafı hesaplama/doğrulama ile kapatılmalı.
- Not — Filament oluşturma sayfaları kayıt sonrası aynı URL'de kalıyor; her başarılı gönderim ilgili liste ekranı ve doğrudan QA firma kapsamındaki veritabanı kaydıyla doğrulandı.
- 03:09 — Tarayıcı form testi: `QA Test Kasası` TRY para birimiyle oluşturuldu ve `kasa_hesaplari` kaydında doğrulandı (ID 116).
- 12:33 — Apache işçi süreçleri yenilendi; yüksek CPU kullanan takılı işlem sonlandı. Yönetim paneli yeniden açıldı.
- 12:34 — QA kullanıcı oturumu tarayıcı üzerinden yeniden doğrulandı; finans hareketleri listesi hata vermeden açıldı ve başlangıçta hareket kaydı olmadığı görüldü.
- 12:35 — 872 numaralı 120,00 TL tutarlı taslak giden fatura detay sayfası tarayıcıda açıldı. Onay aksiyonu ilk denemede durum değişikliği üretmedi; fatura hâlâ taslak ve buna bağlı finans hareketi oluşmamış durumda. Onay aksiyonunun koşulu/tetikleyicisi incelenecek.
- 12:42 — Canlı form aksiyonlarının çalışmama kök nedeni belirlendi: Livewire betiği `defer` ile Filament yüklemesinden sonra başlatılıyordu. `AppServiceProvider` içindeki gecikmeli betik niteliği kaldırıldı; tarayıcıda fatura onay aksiyonu tekrar çalıştı.
- 12:43 — Tarayıcıda fatura onayı bu kez stok bakiyesini negatife düşüreceği için doğru iş kuralı ile reddedildi; hata bildirimi kullanıcıya gösterildi.
- 12:45 — Tarayıcı üzerinden `STK000001` için Merkez Depo sayımı 10 adet olarak uygulandı; fatura onay tekrar testi sıradaki adımdır.
- 12:47 — Tarayıcıda ikinci onay denemesi tamamlandı: 872 numaralı fatura onaylı duruma geçti, sistem `2026-000001` fatura numarasını verdi ve 1 adet satış stok hareketi oluşturdu (depo bakiyesi 10'dan 9'a indi). Fatura tutarı 120,00 TL, ödeme durumu `ödenmedi` olarak kaldı; tahsilat/vade zinciri bu kayıtla test edilecek.
- 12:51 — Tarayıcıda doğrudan `fatura_id=872` ile açılan tahsilat formu, cari ve 120,00 TL açık tutarı güvenle ön doldurdu. QA Test Kasası seçilerek tahsilat kaydedildi; finans hareketi oluştu ve fatura açık tutarı 0,00 TRY, ödeme durumu `odendi` oldu.
- 13:06 — Tarayıcı formlarından QA Test Bankası (TRY) ile bu banka hesabına bağlı QA Test POS oluşturuldu; POS oluşturma işlemi başarı bildirimi ile tamamlandı.
- 13:48 — Tarayıcıda vade takibi ekranı açıldı ve tahsilat sonrası açık alacak, geciken vade ve bugünkü vade özetlerinin tamamı 0,00 TRY olarak doğrulandı.
- 13:52 — Tarayıcıda Gelir / gider raporu açıldı; QA kapsamındaki 1 onaylı fatura için 120,00 TRY gelir, 0,00 TRY gider ve 120,00 TRY net doğru raporlandı.
- 14:01 — Ortak dinamik cari seçici, tahsilat ve çek girişinde `Aranıyor...` durumunda kalacak şekilde yeniden üretildi. Aynı anda Apache işçi süreci anormal CPU kullanıyordu ve hata günlüğünde yoğun `VirtualProtect() failed [87]` OPcache hataları vardı. Yerel XAMPP için OPcache devre dışı bırakıldı ve Apache doğru yapılandırma köküyle yeniden başlatıldı; seçici regresyonu yeniden test edilecek.
- Kritik açık — OPcache kapatıldıktan sonra da tarayıcıdaki dinamik cari araması `Aranıyor...` durumunda kalmaktadır. Sunucu günlüğünde buna karşılık gelen uygulama hatası yoktur. Fatura ID ile tahsilat formunu ön doldurma, kasa tahsilatı ve fatura kapama çalıştığından işlem için güvenli geçici yol mevcuttur; ancak genel cari araması çözülmeden ürün satışa hazır kabul edilmemelidir.
- 14:57 — Tarayıcıda Senet Takibi > Senet girişi formu açıldı. Formdaki `Senedi veren cari` arama bileşeni görünür seçim alanını oluşturmasına rağmen arama girişini etkileşime açmadı; Çek/Tahsilat ekranlarındaki `Aranıyor...` bulgusuyla aynı dinamik cari seçici sorununu doğruluyor. Kayıt gönderilmedi; QA verisine yeni senet eklenmedi.
- 14:58 — Tarayıcıda muhasebe raporları kontrol edildi. Gelir/Gider raporu QA onaylı faturayı 120,00 TRY gelir ve 120,00 TRY net olarak doğru gösteriyor. Bilanço ile Kar/Zarar ekranları ise açıkça “yapısal iskelet” olduklarını ve iş mantığı/veritabanı bağlantısının henüz eklenmediğini bildiriyor. Bu iki menü mevcut haliyle satışa hazır finansal rapor değildir.
- 14:59 — Tarayıcıda Bankalar, POS'lar ve Kasalar listeleri açıldı; QA Test Bankası, QA Test POS ve QA Test Kasası kayıtları listelerde hata olmadan görünür ve erişilebilir.
- 15:05 — Dinamik cari seçici tarayıcıda hem Tahsilat hem Fatura oluşturma formunda tekrar denendi. `QA` araması `/livewire/update` isteğini 200 ile tamamlıyor ancak seçim listesi `Aranıyor...` durumunda kalıyor; tarayıcı konsolu ve uygulama günlüğünde hata yok. Bu nedenle sorun sorgu sonucu veya HTTP hata kodu değil, Livewire/Filament seçim bileşeni yanıtının istemciye uygulanmaması katmanında görünüyor. Yanlış kapsamlı bir “tüm carileri ön yükle” çözümü uygulanmadı.
- 15:10 — Fatura kaleminde miktar ve birim fiyat alanlarının 300 ms gecikmeli Livewire eşitlemesi kaldırıldı. Böylece kullanıcı son fiyatı yazdıktan hemen sonra kaydettiğinde eski sunucu durumuyla 0 toplamlı fatura oluşturma yarışı engellenir. Önbellekler temizlendi; tarayıcıda Fatura oluşturma ekranı 500 hatası olmadan yeniden açıldı. Dinamik cari seçici engeli nedeniyle tam yeni-fatura gönderim regresyonu bu turda yapılamadı.
- 15:14 — Dinamik cari seçicisinin arama yanıtını istemciye uygulamama sorunu için Tahsilat, Çek girişi ve Senet girişi formlarına mevcut firma kapsamında en fazla 50 aktif cariyle sınırlı başlangıç seçenekleri eklendi; tüm kayıtlar ön yüklenmedi. Tarayıcıda Tahsilat, Çek ve Senet açılır listeleri `CR-1000 — QA Test Müşteri AŞ` seçeneğini yükledi. Kayıt gönderilmedi; bundan sonraki giriş akışları seçilebilir cariyle tamamlanabilir.
- 15:20 — Tarayıcıda Senet girişi ve Çek girişi formlarında QA müşterisi seçildi; seçim her iki formda da kalıcı olarak görünür hâle geldi. Bu, form seçimi ve ilgili Livewire durum güncellemesinin düzeltme sonrasında çalıştığını doğrular. Yeni kıymetli evrak kaydı oluşturulmadı.
- 15:24 — Tarayıcıda Tahsilat formu QA müşteri seçimiyle POS ve Banka kanallarında ayrı ayrı çalıştırıldı. QA Test POS (TRY) ve QA Test Bankası (TRY) doğru şekilde yüklendi, seçildi ve form kaydetmeye hazır duruma geldi. Finansal hareket oluşturmamak için kaydetme adımı çalıştırılmadı.
- 15:28 — Ödeme oluşturma formunda aynı dinamik cari seçici sorunu bulundu ve mevcut firma kapsamında en fazla 50 aktif cari başlangıç seçeneğiyle düzeltildi. Önbellek temizliği sonrası tarayıcıda QA müşterisi seçenek olarak yüklendi. Ödeme finansal hareketi oluşturulmadı.
- 15:32 — Tarayıcıda Transfer (virman) formu açıldı. Kaynak türü kasa ve hedef türü banka için QA Test Kasası ile QA Test Bankası doğru yüklendi ve seçildi; form kaydetmeye hazır hâle geldi. Virman oluşturulmadı.
- 15:36 — Fatura oluşturma ekranındaki cari seçici de aynı güvenli başlangıç-seçeneği yaklaşımıyla düzeltildi ve cari aramasına mevcut firma filtresi eklendi. Bu, başka firmaların carilerinin fatura seçicisine sızmasını engeller. Önbellek temizliği sonrası tarayıcıda yalnız QA firmasının `CR-1000 - QA Test Müşteri AŞ` kaydı seçenek olarak yüklendi; 500 hatası yok.
- 15:40 — Tarayıcıda Giden Fatura taslağı için QA müşteri ve QA stok kartı seçildi; birim fiyat 100 girildiğinde satır neti ve genel toplam 120 (KDV dahil) olarak hemen güncellendi. Bu, fiyat/miktar eşitleme düzeltmesinin form hesaplamasını koruduğunu doğrular. Test formu gönderilmedi; yeni fatura oluşmadı.
- 15:44 — Çek çıkışı ve Senet çıkışı formlarındaki aynı cari seçicisine de firma kapsamlı, 50 kayıt sınırındaki başlangıç seçenekleri eklendi. İlgili PHP dosyaları sözdizimi denetiminden geçti. Tarayıcı doğrulaması sonraki turda tamamlanacak.
- 15:48 — Tarayıcıda Çek çıkışı formu açıldı; QA müşterisi başlangıç seçeneği olarak yüklendi. Çek çıkışı oluşturulmadı.
- 15:52 — Tarayıcıda Senet çıkışı formu açıldı; QA müşterisi başlangıç seçeneği olarak yüklendi. Senet çıkışı oluşturulmadı.
- 15:55 — Tarayıcıda Finans Paneli tekrar doğrulandı: bugünkü tahsilat 120,00 TRY, net akış 120,00 TRY, toplam kasa 120,00 TRY ve açık tahsilat yok. Son finans hareketindeki fatura tahsilatı doğru kaynak/hedef tutarla görünür; panelde 500 hatası yok.
- 15:58 — Tarayıcıda Muhasebe Özeti açıldı. Onaylı fatura `2026-000001` ödenmiş ve 0,00 TRY açık tutarla; tahsilat 120,00 TRY, ödeme 0,00 TRY, net akış 120,00 TRY olarak tutarlı görünüyor. Kritik/negatif stok göstergeleri 0; 500 hatası yok.
- 16:01 — Tarayıcıda Kur Farkı Hareketleri listesi açıldı; QA firmasında kayıt olmaması doğru boş durum mesajıyla gösteriliyor ve 500 hatası yok.
- 16:06 — Tarayıcıda Stok Hareketleri listesi açıldı. QA Test Stok Kartı için açılış sonrası 10,0000 ve onaylı satış sonrası 9,0000 bakiye zinciri görünür; ilgili cari de doğru gösteriliyor. 500 hatası yok.
- 16:09 — Tarayıcıda Depo Sayım Geçmişi açıldı. QA Test Stok Kartı için sayım düzeltmesi +10,0000 olarak görünür; stok giriş/sayım akışının kayıt izi doğrulandı ve hata yok.
- 16:12 — Tarayıcıda Fatura listesi açıldı. QA müşterisi için `2026-000001` numaralı ve 120,00 TRY tutarlı onaylı fatura listede görünür; 500 hatası yok.
- 16:15 — Tarayıcıda Finans Hareketleri listesi açıldı; sayfa 500 hatası olmadan yüklendi. Hareket görünürlüğü için tarih/filtre senaryosu sonraki turda ayrıntılandırılacak.
- 16:20 — Finans Hareketleri listesi ayrıntılı doğrulandı: 30.08.2026 13:50 tarihli Fatura tahsilatı 120,00 TRY olarak, `Cari: QA Test Müşteri AŞ` kaynağından `Kasa: QA Test Kasası` hedefine ve “Tahsilat (alacak kapaması)” etkisiyle görünür. Liste 1 sonuç gösteriyor.
- 16:25 — Bu QA turunda değiştirilen Tahsilat, Ödeme, Çek, Senet ve Fatura form kaynakları PHP sözdizimi denetiminden geçti.
- 16:30 — Tarayıcıda POS listesi tekrar açıldı. QA Test POS, TRY para birimi ve 0,00 TRY başlangıç bakiyesiyle görünür; 500 hatası yok.
- 16:33 — Tarayıcıda Banka Hesapları listesi tekrar açıldı. QA Test Bankası, QA Bank sağlayıcısı ve TRY para birimiyle görünür; 500 hatası yok.
- 16:36 — Tarayıcıda Kasa Hesapları listesi tekrar açıldı. QA Test Kasası TRY ve 120,00 TRY bakiye ile görünür; fatura tahsilatı kasa bakiyesine doğru yansımış, 500 hatası yok.
- 16:39 — Tarayıcıda Veresiye / Taksit Takibi açıldı. Operasyon ve detay-rapor paneli görünür, alacak plan taksit listesi QA verisi için boş; 500 hatası yok.
- 16:42 — Vade takip detayları tarayıcıda yüklendi: açık alacak, geciken, bugün ve 7 gün göstergelerinin tamamı 0,00 TRY / 0 vade. Tahsilat sonrası vade bakiyeleri doğru kapanmış.
- 19:13 — Kullanıcının Chrome seçimi doğrultusunda yeni Chrome sekmesinde `http://localhost/admin` açıldı; uygulama yönetici giriş ekranına yönlendirdi. Chrome açık sekmelerinde yerel uygulamaya ait girişli bir sekme bulunmadığından muhasebe QA akışı güvenli biçimde beklemeye alındı; kullanıcı Chrome’da giriş yaptığında aynı sekmeden devam edilecek.
- 19:38 — Kullanıcı Chrome’da giriş yaptıktan sonra `QA Full Test / QA Full Test Yöneticisi` oturumu doğrulandı. Chrome üzerinden Muhasebe özeti, Stok Hareketleri, Fatura, Gelir-Gider ve Finans Hareketleri ekranları art arda açıldı; tamamı 200/normal içerikle yüklendi, 500 hatası görülmedi. Stok zinciri 10,0000 açılış → 9,0000 satış sonrası; onaylı fatura `2026-000001` 120,00 TRY ve açık tutar 0,00 TRY; gelir-gider TRY neti 120,00 TRY olarak doğrulandı.
- 19:42 — Chrome’da Finans menüsündeki gerçek bağlantılar üzerinden Çek (`/admin/muhasebe/finans/cek`) ve Senet (`/admin/muhasebe/finans/senet`) takip sayfaları açıldı. Her iki sayfa da ilgili başlıklarla normal yüklendi, QA firmasında kayıt yokluğu boş liste olarak gösterildi ve 500 hatası görülmedi.
- 19:47 — Chrome’da Çek Takibi ekranındaki “Çek girişi” formu açılıp incelendi; cari, çek no, banka/şube, tutar, para birimi ve tarih alanları yükleniyor. Form gönderilmeden kapatıldı; QA verisine yeni finansal kayıt eklenmedi.
- 19:52 — Chrome’da Senet Takibi ekranı yeniden açıldı; sayfa 500 hatası olmadan `Senet Takibi` / `Senet` başlıklarıyla yüklendi. Navigasyon yaklaşık 30 saniye sürdü; performans bulgusu olarak kaydedildi.
- 19:56 — Kullanıcı “devam” talebi sonrası Chrome’daki girişli `Genel Bakış - Yalova Yazılım` sekmesi yeniden devralındı. Senet Takibi sayfası tekrar açıldı; `Senet Takibi` / `Senet` başlıkları ve giriş/çıkış butonları normal, 500 hatası yok.
- 19:59 — Chrome’da Senet Takibi üzerinde “Senet girişi” butonu etkileşime hazır durumda kontrol edildi; ekran 500 hatası vermedi ve herhangi bir kayıt gönderilmedi.
- 20:02 — Chrome Senet Takibi sekmesinin tarayıcı konsol kayıtları kontrol edildi; hata/uyarı kaydı bulunmadı.
- 20:05 — Chrome’da Stok > Stok Ekle formu açıldı. `Ad*`, `Tür*`, `Kısa ad`, `Barkod`, `Seri No`, `IMEI No` ve `Durum*` alanları normal yüklendi; 500 hatası yok. Form gönderilmedi ve QA verisine yeni kart eklenmedi.
- 20:08 — Chrome’da Stok Kartları ve Kritik Stoklar listeleri açıldı. QA Test Stok Kartı mevcut miktar 9 AD olarak görünür; kritik stok listesi boş durumla doğru yüklenir. Her iki ekranda 500 hatası yok.
- 20:11 — Chrome’da Fatura Oluştur formu açıldı. Tür/Durum, tarih-vade, belge numaraları, e-belge, kur, satır miktar/fiyat/iskonto/KDV/toplam ve açıklama alanları normal yüklendi; 500 hatası yok. Finansal kayıt oluşturulmadan formdan çıkıldı.
- 20:15 — Chrome’da yeni stok formunda `Uzunluk + Adet` seçeneği seçildikten sonra `Birden fazla ölçü` ve ölçü birimi seçenekleri DOM’da görünmesine rağmen ikinci seçim etkileşimi zaman aşımına uğradı; kayıt oluşturulmadı. Parçalı/çoklu birim akışında dinamik alanın etkileşim kararsızlığı bulgu olarak kaydedildi.
- 20:18 — Kaynak incelemesiyle `Birden fazla ölçü` seçeneğinin uygulama tarafından bilinçli olarak devre dışı bırakıldığı ve form yardım metninde “kullanıma kapalıdır” denildiği doğrulandı. 20:15 bulgusu işlev hatası değil, devre dışı seçeneğe tarayıcıdan seçim denemesinin beklenen zaman aşımıdır; ürün kararı olarak açık/kapalı davranışı ayrıca netleştirilmeli.
- 20:35 — Kullanıcının açık onayı sonrası Chrome’da parçalı kullanım açık, sabit ölçülü QA stok kartı oluşturuldu: `STK000002 QA Parçalı Çoklu Birim Stok`, takip `Uzunluk + Adet`, sabit ölçü `10 cm`, açılış adedi `2`, toplam ölçü `0,2 m`, mevcut `2 AD`. Listeye başarıyla yansıdı; kayıt sonucu tarayıcıda doğrulandı.
- 20:38 — Yeni kartın Stok Kartları listesinde tekrar görünürlüğü Chrome’da doğrulandı; `10 cm`, `0,2 m` ve parçalı kullanım bilgileri listede mevcut, 500 hatası yok.
- 20:45 — Kullanıcının talebiyle Chrome’da stok kayıtları kontrol edildi: Depo sayım geçmişinde STK000001 için 0→10 (+10) düzeltmesi; Depo Stokları’nda STK000001 kullanılabilir 9 AD ve STK000002 kullanılabilir 0,2000 AD (ölçü toplamı) görünüyor. Her iki ekran normal yüklendi, 500 hatası yok.
- 20:52 — Chrome’da QA Test Stok Kartı / Merkez Depo için fiili miktar 9 ve açıklama `QA tarayıcı stok sayım testi` ile sayım akışı çalıştırıldı. Mevcut bakiye zaten 9 olduğundan fark oluşmadı ve yeni hareket kaydı üretilmedi; sayım geçmişi değişmeden, 500 hatasız kaldı (beklenen no-op davranışı).
- 20:55 — Chrome’da Depo Transfer Geçmişi boş durumla 500 hatasız yüklendi; Depolar Arası Transfer formunda miktar, tarih ve açıklama alanları mevcut ve normal yüklendi. Transfer gönderilmedi.
- 20:58 — Chrome’da Bilanço ve Kar/Zarar raporları yeniden açıldı; sayfalar 500 hatası olmadan yüklendi ve mevcut uygulama kapsamının yapısal rapor iskeleti olduğu doğrulandı. Ticari hesaplama iş mantığı bu ekranlarda uygulanmamış olarak kaldı.
- 21:02 — Chrome’da onaylı fatura 2026-000001 düzenleme ekranı keşfedildi. UI’da “İptal Et” ve “İade Et” yaşam döngüsü aksiyonları mevcut; fatura alanları kilitli ve QA cari/stok bağlantıları doğru gösteriliyor. Bu aksiyonlar finansal ters kayıt oluşturabileceğinden gönderilmeden bırakıldı.
- 21:08 — Chrome’da Faturalar alt listelerinin gerçek bağlantıları doğrulandı: Giden Faturalar’da 2026-000001 (120,00 TRY, odendi) ve #871 taslak görünür; Giden İade, Gelen ve İptal listeleri QA firmasında doğru boş durumla ve 500 hatası olmadan yüklendi.
- 21:15 — Chrome’da Masraf Takibi, Masraf Raporları ve Masraf Türleri ekranları açıldı. Masraf kayıtları ve türleri QA firmasında boş durumla, rapor ekranı tarih filtresi alanlarıyla normal yüklendi; üçünde de 500 hatası yok.
- 2026-08-31 00:10 — Campaign prompt doğrultusunda yalnız QA tenant’ta Chrome UI üzerinden `E2E_QA_20260830_DEPO_001 / E2E QA Test Deposu 001` deposu oluşturuldu ve Depolar listesinde doğrulandı; 500 hatası yok.
- 2026-08-31 00:14 — Depolar Arası Transfer formunda stok seçici açıldı ve `QA` araması denendi; sonuç listesinde QA stok seçeneği görünmedi (boş listbox, hata mesajı yok). Transfer kaydı oluşturulmadan bırakıldı. P2 aday: transfer stok seçicisinin tenant verisini yüklememesi; ayrı form bileşeni için kök neden analizi gerekli.
- 2026-08-31 00:22 — Chrome’da Cari listesi ve Cari Ekle formu keşfedildi. QA Test Müşteri AŞ aktif ve bakiye 0,00 TRY görünüyor; Cari Ekle formunda ünvan, tür/durum, vergi, iletişim, adres, risk limiti, vade ve para birimi alanları normal yüklendi, 500 hatası yok.
- 2026-08-31 00:30 — Chrome’da Cari Hareketleri ve Cari Ekstre ekranları açıldı; QA tenant’ta kayıt/filtre alanları boş durumla normal yüklendi ve 500 hatası görülmedi.
- 2026-08-31 00:36 — Chrome’da Gider Faturası, Bekleyen Faturalar ve Proforma Fatura listeleri açıldı; QA tenant’ta kayıt yokluğu doğru boş durumla gösterildi, 500 hatası yok.
