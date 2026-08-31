# BT Varlık Yönetimi Modülü

## Kurulum ve Geliştirme Ana Yönergesi

**Belgenin amacı:** Bu dosya, BT Varlık Yönetimi modülünü başka bir sohbette veya başka bir geliştirici tarafından aynı kapsam, menü ve çalışma mantığı korunarak geliştirebilmek için hazırlanmış ana proje sözleşmesidir.

**Kaynak önceliği:** Bu belge ile mevcut proje mimarisi çelişirse önce mevcut proje kuralları, tenant/yetki mimarisi ve `AGENTS.md` uygulanır. BT referans sitesinin menü ve ekran yaklaşımı korunur; ancak mevcut SaaS mimarisine uyarlanır.

**Önemli uygulama kuralı:** Bu belge okunmadan migration, model, resource, page veya menü kodu yazılmamalıdır. Önce kapsam ve ekran sözleşmesi doğrulanmalı, sonra çekirdek geliştirilmelidir.

---

## 1. Ürün tanımı

BT Varlık Yönetimi; BT donanımlarını, altyapı kayıtlarını, kullanıcı zimmetlerini, dokümanları, elektronik belgeleri, lisansları, domain/hosting hizmetlerini ve parolaları firma bazında merkezi olarak yöneten web tabanlı SaaS modülüdür.

Ürün hedefleri:

- Sade ve hızlı kullanım.
- Türkçe, İngilizce ve Almanca dil desteği.
- Masaüstü, tablet ve telefon uyumluluğu.
- Bulut veya şirket sunucusunda çalışabilme.
- Firma/tenant izolasyonu.
- Varlık yaşam döngüsünün izlenmesi.
- Zimmet, belge, parola ve teknik bilgilerinin tek kayıtta ilişkilendirilmesi.
- Active Directory, ping, SNMP, WMI/CIM ve VMware gibi entegrasyonlara hazır yapı.
- Varlık sayısına göre lisanslama.

---

## 2. Referans alınan ekran ve menü yaklaşımı

Referans: `https://www.btenvanteri.com/`

Referans alınan ana kullanım yaklaşımı:

- Varlık listeleri sade tablolardır.
- Satır detayları açılır veya detay sayfasında gösterilir.
- Bilgisayar, sunucu, ağ cihazı ve kabinet kayıtlarında teknik detaylar ilişkilidir.
- Dokümanlar kategori bazında tutulur.
- Parolalar ayrı ve güvenli bir alandadır.
- Kişisel sayfada işler, makaleler ve notlar bulunur.
- Fotoğraflar slayt olarak görüntülenebilir.
- Barkod ve QR/karekod üretilebilir.
- Zimmet formu oluşturulabilir ve e-posta ile gönderilebilir.
- Garanti, destek, lisans, sözleşme ve domain süreleri izlenebilir.

Menüde `Yazılımlar` diye bir üst başlık kullanılmayacaktır. Doğru yapı:

```text
Uygulamalar
├── PC Uygulamaları
└── Sistem Uygulamaları
```

---

## 3. Nihai menü yapısı

```text
BT Varlık Yönetimi
│
├── Özet
│
├── Varlıklar
│   ├── Tüm Varlıklar
│   ├── Bilgisayarlar
│   ├── Sunucular
│   ├── Ağ Cihazları
│   ├── Çevre Birimleri
│   ├── Clusterlar
│   ├── Kabinetler
│   ├── Parçalar
│   ├── İşletim Sistemleri
│   ├── Uygulamalar
│   │   ├── PC Uygulamaları
│   │   └── Sistem Uygulamaları
│   ├── GSM Hatları
│   ├── DSL Hatları
│   ├── Metro Ethernet Hatları
│   ├── Domain ve Hosting
│   ├── IP Listesi
│   ├── Kullanıcılar
│   ├── Active Directory
│   └── Özel Tablolar
│
├── Dokümanlar
│   ├── Özet
│   ├── Faturalar
│   ├── Formlar
│   │   ├── Servis Formları
│   │   ├── Zimmet Formları
│   │   └── Diğer Formlar
│   ├── Konfigürasyonlar
│   ├── Lisanslar
│   ├── Prosedürler
│   ├── Sözleşmeler
│   ├── Teklifler
│   ├── Topolojiler
│   ├── Raporlar
│   ├── Makaleler
│   └── İşlem Logları
│
├── Parolalar
│   ├── Özet
│   ├── AD Parolaları
│   ├── Cihaz Parolaları
│   ├── Mail Parolaları
│   ├── Portal Parolaları
│   ├── Tüm Parolalar
│   └── İşlem Logları
│
└── Kişisel Sayfam
    ├── Özet
    ├── İşlerim
    ├── Makalelerim
    └── Notlarım
```

### Menü sadeleştirme kuralları

Aşağıdakiler ana menüye eklenmeyecektir:

- Lokasyonlar
- Departmanlar
- Varlık durum tanımları
- Entegrasyon ayarları
- Bildirim ayarları
- Lisans ayarları
- Varlık türü teknik ayarları

Bu bilgiler ilgili formlarda hızlı oluşturulacak veya modül ayarlarında yönetilecektir.

---

## 4. Ortak kullanım standardı

Her liste sayfası aynı davranış standardını uygulamalıdır:

- Sayfa başlığı ve kısa açıklama.
- Sağ üstte `Yeni kayıt` aksiyonu.
- Sunucu taraflı arama.
- Sunucu taraflı filtreleme.
- Sunucu taraflı sıralama.
- Sunucu taraflı pagination.
- Varsayılan sayfa boyutu ortak tablo standardından alınır.
- CSV/Excel dışa aktarma yalnız kullanıcı aksiyonuyla çalışır.
- Toplam kayıt hesabı yalnız gerektiğinde çalışır.
- `1000` ve `Hepsi` seçenekleri yalnız açıkça seçilirse kullanılabilir.
- Normal açılışta tüm kayıtlar tarayıcıya yüklenmez.
- N+1 sorgu oluşturulmaz.
- Her listeye özel ikinci bir tablo motoru eklenmez.

Yeni tablolar mevcut `admin-table-standard.md` sözleşmesine uymalıdır. Kartlar `yk-info-card` ve `yk-info-card-grid` standardını kullanmalıdır.

---

## 5. Ortak varlık modeli

Tüm lisanslanabilir varlıklar ortak bir ana varlık kaydına bağlanmalıdır.

### Ortak varlık alanları

- `firma_id`
- Varlık türü
- Takip numarası
- Varlık adı
- Marka
- Model
- Seri numarası
- Ürün numarası
- Barkod
- QR/karekod
- Satıcı firma
- Sipariş numarası
- Satın alma tarihi
- Satın alma bedeli
- Garanti başlangıç tarihi
- Garanti bitiş tarihi
- Destek başlangıç tarihi
- Destek bitiş tarihi
- Lokasyon
- Şube/departman
- Atanmış kullanıcı
- Durum
- Kritik seviye
- İzleme açık/kapalı
- Son görülme zamanı
- Son ping durumu
- Açıklama
- Oluşturan kullanıcı
- Güncelleyen kullanıcı
- Pasife alınma bilgisi

### Varlık durumları

```text
Taslak
Aktif
Depoda
Kullanımda
Bakımda
Arızalı
Kayıp
Hurdaya Ayrıldı
Emekli
```

Fiziksel kayıtlar silinmemeli, mümkün olduğunca pasife alınmalıdır.

---

## 6. Varlık türleri ve alanları

### 6.1 Bilgisayarlar

Liste kolonları:

- Bilgisayar adı
- Bilgisayar türü
- Marka
- Model
- Seri numarası
- Ürün numarası
- Lokasyon
- Atanan kullanıcı
- İşletim sistemi
- Durum
- Son görülme

Detay alanları:

- İşlemci
- RAM
- Disk
- Ekran kartı
- MAC adresi
- IP adresi
- Bilgisayar adı
- Domain durumu
- İşletim sistemi
- Kurulu PC uygulamaları
- Fotoğraflar
- Garanti ve destek
- Zimmetler
- Servis kayıtları
- Ping geçmişi

### 6.2 Çevre birimleri

Örnek türler:

- Monitör
- Yazıcı
- Tarayıcı
- Klavye
- Mouse
- UPS
- Projektör
- Harici disk
- Telefon
- Diğer çevre birimleri

Alanlar:

- Cihaz türü
- Marka/model
- Seri numarası
- Lokasyon
- Atanan kullanıcı
- Bağlı bilgisayar
- Durum
- Garanti
- Fotoğraf
- Açıklama

### 6.3 Sunucular

Alanlar:

- Sunucu adı
- Sunucu türü
- Marka/model
- Seri numarası
- Ürün numarası
- IP adresi
- İşlemci
- RAM
- Disk/RAID
- Lokasyon
- Kabinet
- Rack unit
- Sanallaştırma bilgisi
- İşletim sistemi
- Kritik seviye
- Durum
- Son ping

İlişkiler:

- Cluster
- Sanal makineler
- İşletim sistemleri
- IP adresleri
- Uygulamalar
- Lisanslar
- Konfigürasyonlar
- Servis kayıtları

### 6.4 Ağ cihazları

Alanlar:

- Cihaz türü
- Marka/model
- Seri numarası
- Cihaz adı
- IP adresi
- MAC adresi
- Lokasyon
- Kabinet
- Rack unit
- VLAN bilgisi
- Yönetim protokolü
- Durum
- Son ping
- Son SNMP kontrolü

Desteklenecek türler:

- Switch
- Router
- Firewall
- Access Point
- Modem
- VPN cihazı
- Load balancer
- Kablosuz kontrol cihazı

### 6.5 Clusterlar

Cluster alanları:

- Cluster adı
- Cluster türü
- Cluster IP adresi
- Lokasyon
- Ortam
- Durum
- Açıklama

Cluster üyeleri:

- Sunucu
- Marka/model
- Seri numarası
- Sunucu adı
- IP adresi
- Üyelik durumu

### 6.6 Kabinetler

Alanlar:

- Kabinet adı
- Lokasyon
- Salon/oda
- Marka/model
- Seri numarası
- Toplam rack unit
- Kullanılan rack unit
- Fotoğraf
- Açıklama

Kabinet detayında cihazların rack sıralaması gösterilmelidir.

### 6.7 Parçalar

Alanlar:

- Parça türü
- Marka/model
- Seri numarası
- Stok durumu
- Kullanıldığı varlık
- Lokasyon
- Garanti
- Durum
- Açıklama

### 6.8 İşletim sistemleri

Alanlar:

- Sistem adı
- İşletim sistemi
- Versiyon
- IP adresi
- İşlev
- Ortam
- Bağlı sunucu
- İşlemci
- Disk
- RAM
- Lisans
- Durum

### 6.9 Uygulamalar

#### PC Uygulamaları

- Uygulama adı
- Üretici
- Versiyon
- Lisans türü
- Lisans adedi
- Kullanılan lisans adedi
- Kurulum tarihi
- İlgili bilgisayarlar
- İlgili kullanıcılar
- Dokümanlar

#### Sistem Uygulamaları

- Uygulama adı
- Üretici
- Versiyon
- Çalıştığı sunucu
- Bağlı sistem/veritabanı
- Ortam
- Sorumlu kişi
- Kritik seviye
- Lisans
- Başlangıç/bitiş tarihi
- Konfigürasyon
- Dokümanlar

### 6.10 GSM hatları

- Hat türü
- Operatör
- SIM kart numarası
- GSM numarası
- Kısa kod
- PIN/PUK
- Tarife
- Aylık ücret
- Taahhüt başlangıç/bitiş tarihi
- Sözleşme
- Atanan kullanıcı
- Lokasyon
- Durum

### 6.11 DSL ve Metro Ethernet hatları

- Hat türü
- Operatör
- Devre numarası
- Statik IP bloğu
- Bant genişliği
- Modem
- Lokasyon
- Sözleşme
- Aylık ücret
- Taahhüt tarihi
- Durum

### 6.12 Domain ve hosting

- Domain adı
- Hosting sağlayıcısı
- DNS sağlayıcısı
- Yönetim paneli
- Başlangıç tarihi
- Bitiş/yenileme tarihi
- SSL bitiş tarihi
- Ücret
- Sorumlu kişi
- İlgili parola
- Durum
- Açıklama

---

## 7. Kullanıcılar ve zimmet

Mevcut SaaS kullanıcı, personel, departman ve firma kayıtları yeniden oluşturulmamalıdır. BT modülü mevcut kayıtlarla ilişki kurmalıdır.

### Zimmet akışı

```text
Varlık seçilir
→ Kullanıcı seçilir
→ Teslim bilgileri girilir
→ Zimmet formu oluşturulur
→ PDF hazırlanır
→ Kullanıcıya e-posta gönderilir
→ Zimmet aktif olur
```

Zimmet kaydı:

- Varlık
- Kullanıcı
- Teslim eden
- Teslim alan
- Teslim tarihi
- İade tarihi
- Fiziksel durum
- Eksik/hasarlı parçalar
- Açıklama
- Form dosyası
- Onay durumu

İade sırasında varlığın durumu `Depoda`, `Bakımda` veya `Arızalı` olarak güncellenebilmelidir.

---

## 8. Barkod, QR ve fotoğraf

Her lisanslanabilir varlık için otomatik takip numarası üretilmelidir.

Örnek:

```text
BT-PC-000001
BT-SRV-000001
BT-NET-000001
```

Desteklenecek işlemler:

- Tekli barkod yazdırma
- Toplu barkod yazdırma
- QR/karekod yazdırma
- Etiket çıktısı
- QR okutma ile varlık detayına erişim
- Çoklu fotoğraf yükleme
- Kapak fotoğrafı
- Fotoğraf sıralama
- Slayt gösterisi
- Fotoğraf açıklaması

---

## 9. Doküman yönetimi

Doküman türleri:

- Fatura
- Servis formu
- Zimmet formu
- Diğer form
- Konfigürasyon
- Lisans
- Prosedür
- Sözleşme
- Teklif
- Topoloji
- Makale

Ortak alanlar:

- Başlık
- Doküman türü
- Dosya
- Firma/tedarikçi
- İlgili varlık
- İlgili kullanıcı
- Belge tarihi
- Geçerlilik başlangıç/bitiş tarihi
- Etiket
- Açıklama
- Gizlilik seviyesi
- Yükleyen kullanıcı

Dokümanlar lisanslamaya dahil değildir.

Hassas dokümanlar public dosya yolu ile doğrudan sunulmamalıdır. Yetki kontrolü ve süreli indirme bağlantısı kullanılmalıdır.

---

## 10. Parola yönetimi

Kategoriler:

- AD parolaları
- Cihaz parolaları
- Mail parolaları
- Portal parolaları
- Diğer parolalar

Alanlar:

- Başlık
- Kullanıcı adı
- Parola
- Parola tekrarı
- URL
- İlgili varlık
- İlgili sistem
- Notlar
- Son değiştirilme tarihi
- Son erişim tarihi
- Parola gücü

Zorunlu güvenlik özellikleri:

- Veritabanında şifreli saklama.
- Listelerde maskeleme.
- Görüntüleme yetkisi.
- Kopyalama yetkisi.
- Görüntüleme/kopyalama logu.
- Zayıf parola analizi.
- Aynı kullanılan parola analizi.
- Boş parola uyarısı.
- Parola üretici.
- Hassas değerlerin audit loga yazılmaması.

Parolalar lisanslama hesabına dahil değildir.

---

## 11. Active Directory

Active Directory modülü aşağıdaki bölümlerden oluşmalıdır:

- AD Bilgisayarları
- AD Kullanıcıları
- Değişiklikler
- Eşleştirme önerileri
- Tarama geçmişi

İzlenecek bilgiler:

- Bilgisayar adı
- Kullanıcı adı
- Departman
- IP
- İşletim sistemi
- Son oturum
- Son görülme
- Aktif/pasif durumu

Yeni veya değişen kayıtlar otomatik olarak bildirim üretmelidir.

Otomatik tarama mevcut BT varlıklarını kullanıcı onayı olmadan silmemeli veya kritik alanlarını doğrudan ezmemelidir.

---

## 12. Ping ve izleme

IP adresi olan izlenebilir varlıklarda ping takibi yapılmalıdır.

Kaydedilecek bilgiler:

- Son kontrol zamanı
- Son başarılı zaman
- Son başarısız zaman
- Yanıt süresi
- Hata mesajı
- Başarısız deneme sayısı
- İzleme durumu

Durumlar:

```text
Çevrimiçi
Çevrimdışı
Kontrol Edilmedi
İzleme Dışı
```

Ping işlemleri web isteği içinde yapılmamalı; queue/scheduler ile yürütülmelidir.

---

## 13. Özel tablolar

Kullanıcılar kendi varlık tablolarını oluşturabilmelidir.

Özel tablo özellikleri:

- Tablo oluşturma
- Tablo adını değiştirme
- Kolon ekleme
- Kolon silme
- Kolon sıralama
- Zorunlu alan
- Kolon açıklaması
- Seçenek listesi
- Kayıt ekleme/güncelleme
- Arama/filtreleme
- CSV dışa aktarma
- Yetkilendirme
- Pasife alma

Kolon tipleri:

- Metin
- Uzun metin
- Sayı
- Para
- Tarih
- Tarih-saat
- Tekli seçim
- Çoklu seçim
- Evet/hayır
- Dosya
- Fotoğraf
- Kullanıcı ilişkisi
- Varlık ilişkisi
- Lokasyon ilişkisi

Özel tablolar da tenant izolasyonu, yetki, işlem logu ve sunucu taraflı tablo standardına uymalıdır.

---

## 14. Domain, lisans, sözleşme ve bildirimler

Takip edilecek süreli kayıtlar:

- Garanti
- Destek
- Lisans
- Sözleşme
- Domain
- Hosting
- SSL
- GSM taahhüdü
- DSL/Metro Ethernet taahhüdü

Uyarı eşikleri varsayılan olarak:

- 90 gün
- 60 gün
- 30 gün
- 7 gün
- Süresi doldu

Firma yöneticisi eşikleri değiştirebilmelidir.

Bildirim kanalı ilk aşamada e-posta ve sistem içi bildirim olmalıdır.

---

## 15. Kişisel sayfam

```text
Kişisel Sayfam
├── Özet
├── İşlerim
├── Makalelerim
└── Notlarım
```

### İşlerim

- Başlık
- Açıklama
- Öncelik
- Durum
- Başlangıç/bitiş tarihi
- Hatırlatma
- İlgili varlık

### Makalelerim

- Başlık
- İçerik
- Kategori
- Etiket
- Taslak/yayın durumu
- Görünürlük

### Notlarım

- Başlık
- İçerik
- Etiket
- Renk
- Sabitleme
- Hatırlatma tarihi
- İlgili varlık

---

## 16. Lisanslama

Lisanslama varlık sayısı üzerinden çalışmalıdır.

Lisans hesabına dahil olanlar:

- Bilgisayarlar
- Sunucular
- Ağ cihazları
- Çevre birimleri
- Clusterlar
- Kabinetler
- Parçalar
- İşletim sistemleri
- Uygulamalar
- GSM/DSL/Metro Ethernet hatları
- Domain/hosting hizmetleri
- Özel tablo varlıkları

Lisans hesabına dahil olmayanlar:

- Kullanıcılar
- Dokümanlar
- Elektronik belgeler
- Parolalar
- İşlem logları
- Kişisel sayfa kayıtları

Lisans aşımı durumunda:

- Kullanıcıya açık uyarı gösterilir.
- Firma yöneticisi bilgilendirilir.
- Yeni lisanslanabilir varlık ekleme sınırlandırılabilir.
- Mevcut kayıtlar görüntülenmeye devam eder.

---

## 17. Yetki modeli

Temel yetkiler:

- Görüntüleme
- Oluşturma
- Güncelleme
- Pasife alma
- İçe aktarma
- Dışa aktarma
- Parola görüntüleme
- Parola kopyalama
- Doküman indirme
- Zimmet oluşturma
- Zimmet onaylama
- Entegrasyon çalıştırma
- İşlem logu görüntüleme
- Rapor görüntüleme

Örnek roller:

```text
BT yöneticisi       → Tüm BT kayıtları
Destek personeli    → Bilgisayarlar, çevre birimleri, zimmetler
Ağ yöneticisi       → Ağ cihazları, IP, kabinet, sunucu
İK                  → Kullanıcılar ve zimmet formları
Denetçi             → Salt okunur kayıtlar ve loglar
Standart kullanıcı  → Kendi zimmetleri ve kişisel sayfası
```

Yetki kontrolü hem menüde hem resource/page seviyesinde hem de policy seviyesinde yapılmalıdır.

---

## 18. İşlem logları

Loglanacak işlemler:

- Kayıt oluşturma
- Kayıt güncelleme
- Durum değişikliği
- Kullanıcı atama
- Zimmet oluşturma/iade
- Doküman yükleme/indirme
- Parola görüntüleme/kopyalama
- Entegrasyon taraması
- Otomatik bilgi değişikliği
- Pasife alma
- Özel tablo/kolon değişikliği

Her logda:

- İşlem türü
- Tarih/saat
- Kullanıcı
- IP adresi
- İlgili kayıt
- Eski değer
- Yeni değer
- Açıklama

Parola ve gizli belge içerikleri loglanmamalıdır.

---

## 19. Entegrasyon tasarımı

İlk mimari entegrasyonlar aşağıdakilere hazır olmalıdır:

- ICMP Ping
- SNMP
- Active Directory
- WMI/CIM
- VMware/vSphere
- CSV içe aktarma
- E-posta

Genel akış:

```text
Tarama talebi
→ Kuyruk
→ Connector
→ Ham sonuç
→ Eşleştirme/fark ekranı
→ Kullanıcı onayı
→ BT varlığına işleme
→ İşlem logu
```

Entegrasyonlar normal sayfa yüklenmesi sırasında çalıştırılmamalıdır.

---

## 20. Mobil ve çoklu dil

Responsive web arayüzü zorunludur.

Mobilde öncelikli işlemler:

- QR okutma
- Varlık görüntüleme
- Fotoğraf çekip ekleme
- Zimmet görüntüleme
- Zimmet onayı
- Servis kaydı oluşturma
- Ping durumunu görüntüleme

Desteklenecek diller:

- Türkçe
- İngilizce
- Almanca

Çeviri kapsamı:

- Menü
- Formlar
- Uyarılar
- Bildirimler
- E-postalar
- PDF formları
- Raporlar

---

## 21. Veri ilişkileri

```text
Firma
└── Lokasyon
    └── Varlık
        ├── Bilgisayar
        ├── Sunucu
        ├── Ağ Cihazı
        ├── Çevre Birimi
        ├── Kabinet
        ├── Cluster
        ├── Parça
        └── Hat

Varlık
├── Kullanıcı/Zimmet
├── Fotoğraf
├── Doküman
├── IP adresi
├── Uygulama
├── İşletim sistemi
├── Lisans
├── Konfigürasyon
├── Servis kaydı
├── Ping kontrolü
└── İşlem logu
```

Mevcut `Firma`, kullanıcı, personel, departman, şube, teknik servis, bildirim ve denetim altyapıları yeniden yazılmamalıdır. BT modülü bunlara ilişki kurmalıdır.

---

## 22. Geliştirme sırası

### Aşama 0 — Hazırlık

- Mevcut tenant mimarisini incele.
- Mevcut firma/kullanıcı/personel ilişkilerini incele.
- Mevcut Filament tablo standardını incele.
- Mevcut yetki, bildirim, dosya ve audit servislerini yeniden kullan.
- Bu belgeyi kaynak sözleşme olarak kabul et.

### Aşama 1 — Çekirdek

- BT cluster
- Modül menüsü
- Lisans sayımı
- Ortak varlık tablosu
- Lokasyon ilişkisi
- Özet ekranı
- Bilgisayarlar
- Kullanıcı ilişkisi

### Aşama 2 — Zimmet ve görseller

- Zimmet kayıtları
- Zimmet formu
- E-posta gönderimi
- Barkod
- QR/karekod
- Fotoğraflar
- Slayt gösterisi

### Aşama 3 — Altyapı varlıkları

- Sunucular
- Ağ cihazları
- Çevre birimleri
- Kabinetler
- Clusterlar
- Parçalar
- IP listesi
- İşletim sistemleri

### Aşama 4 — Uygulamalar ve hizmetler

- PC uygulamaları
- Sistem uygulamaları
- GSM hatları
- DSL hatları
- Metro Ethernet
- Domain/hosting

### Aşama 5 — Doküman ve parola

- Doküman merkezi
- Faturalar
- Formlar
- Lisanslar
- Sözleşmeler
- Konfigürasyonlar
- Parola kasası
- Parola analizleri

### Aşama 6 — Özel tablolar

- Tablo oluşturucu
- Dinamik kolonlar
- Dinamik kayıt ekranları
- Yetkiler
- Dışa aktarma
- İşlem logları

### Aşama 7 — Entegrasyonlar

- Active Directory
- Ping
- SNMP
- WMI/CIM
- VMware
- Kuyruk ve scheduler
- Fark/eşleştirme ekranı

### Aşama 8 — Raporlama ve kalite

- Envanter raporları
- Zimmet raporları
- Garanti/lisans raporları
- Ping raporları
- Yetki testleri
- Tenant izolasyonu testleri
- Performans testleri
- Mobil testler
- Dil testleri

---

## 23. Kabul kriterleri

Modül tamamlanmış sayılmadan önce aşağıdaki şartlar sağlanmalıdır:

- Firma dışı kayıt görülememeli.
- Kullanıcı yetkisi olmayan varlığı görememeli.
- Bilgisayar kullanıcıya zimmetlenebilmeli.
- Zimmet formu üretilebilmeli.
- Zimmet formu e-posta ile gönderilebilmeli.
- Barkod ve QR oluşturulabilmeli.
- Birden fazla fotoğraf yüklenebilmeli ve slayt gösterilebilmeli.
- Dokümanlar varlıkla ilişkilendirilebilmeli.
- Doküman indirmeleri yetki kontrollü olmalı.
- Parolalar şifreli saklanmalı ve maskeli gösterilmeli.
- Aynı ve zayıf parolalar raporlanabilmeli.
- Domain, lisans ve sözleşme bitişleri izlenebilmeli.
- E-posta uyarıları gönderilebilmeli.
- Active Directory farkları gösterilebilmeli.
- Yeni AD kayıtları bildirim üretebilmeli.
- Kullanıcı özel tablo oluşturabilmeli.
- Özel tablo kolonları değiştirilebilmeli.
- Tüm önemli işlemler loglanmalı.
- Liste ekranları sunucu taraflı çalışmalı.
- Mobil ekranda QR, fotoğraf ve zimmet işlemleri kullanılabilmeli.
- Türkçe, İngilizce ve Almanca metinler çalışmalı.
- Varlık sayısına göre lisans sınırı uygulanmalı.
- Kullanıcı, doküman ve parola lisans sayımına dahil edilmemeli.

---

## 24. Başka bir sohbette kullanılacak başlangıç yönergesi

Yeni sohbette aşağıdaki metin kullanılabilir:

> `docs/bt-varlik-yonetimi-kurulum-ve-gelistirme-kilavuzu.md` dosyasını tamamen oku. Bu belge BT Varlık Yönetimi modülünün ana kaynak sözleşmesidir. Önce mevcut projedeki AGENTS.md ve ilgili mimari dokümanları incele. Kod yazmadan önce mevcut BT modül durumunu, mevcut tenant/yetki/bildirim/dosya/audit yapılarını ve bu kılavuzdaki gereksinimleri karşılaştır. Eksik ve çelişkili noktaları raporla. Onay verilmeden migration veya uygulama kodu yazma. Geliştirme başladığında referans menü yapısını koru, `Uygulamalar > PC Uygulamaları / Sistem Uygulamaları` ayrımını bozma, mevcut SaaS altyapılarını yeniden kullan, sunucu taraflı tablo standardına uy ve her aşamayı test ederek ilerle.`

---

## 25. Son karar

Bu kılavuzdaki kapsam, BT Varlık Yönetimi modülünün asgari ve genişletilebilir ürün tanımıdır.

Önce ekran sözleşmeleri ve veri ilişkileri onaylanmalı; ardından Aşama 0 ve Aşama 1 uygulanmalıdır. Entegrasyonlar ve otomasyonlar temel varlık, zimmet, doküman ve parola yapısı doğrulanmadan geliştirilmemelidir.

