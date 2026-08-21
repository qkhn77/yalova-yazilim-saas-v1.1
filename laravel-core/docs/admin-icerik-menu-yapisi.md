# Admin İkili Menü Yapısı

Panel menüsü **iki seviyeli**: gruplar (Sayfalar, Modüller, Servisler, …) ve her grubun altında ilgili sayfalar.

---

## Gruplar ve alt menüler

### Sayfalar
| Menü | Açıklama |
|------|----------|
| Sayfa Listesi | Sayfaların listesi, düzenleme butonu (İletişim, Hakkımızda) |
| Sayfa Ekle | Yeni sayfa ekleme |

### Modüller
| Menü | Açıklama |
|------|----------|
| Menü | Menü ekleme, silme, düzenleme, renk ayarları |
| Slider | Slider düzenleme |
| Neden Biz | neden-biz.blade düzenleme |
| Neler Yapıyoruz | neler-yapiyoruz.blade |
| Referanslar | referanslar.blade |
| Rakamlarla Biz | rakamlarla-biz.blade |
| Teknik Destek | teknik-destek.blade |
| Müşteri Yorumları | musteri-yorumlari.blade |
| S.S.S. / F.A.Q.S | faqs.blade |

### Servisler
| Menü | Açıklama |
|------|----------|
| Servis Listesi | Servisler sayfası (listeleme + düzenleme) |
| Servis Ekle | Servis ekleme |
| Kategori Listesi | Servis kategori sayfası |
| Kategori Ekle | Servis kategorisi ekleme |

### Projeler
| Menü | Açıklama |
|------|----------|
| Proje Listesi | Projelerin listelendiği sayfa |
| Proje Ekle | Proje ekleme |
| Kategori Listesi | Proje kategorileri listesi |
| Kategori Ekle | Proje kategori ekleme |

### Bloglar
| Menü | Açıklama |
|------|----------|
| Blog Listesi | Blogların listelendiği sayfa |
| Blog Ekle | Blog ekleme |
| Kategori Listesi | Blog kategorileri listesi |
| Kategori Ekle | Blog kategori ekleme |

### Ayarlar
| Menü | Açıklama |
|------|----------|
| Genel Ayarlar | Site logosu, footer logo, favicon, Site URL, Site başlığı, SEO açıklama |
| Api Ayarları | WhatsApp, Google Analytics, Webmaster, reCAPTCHA, Instagram token |
| Mail Ayarları | Mail ayarları |

### Kullanıcılar
| Menü | Açıklama |
|------|----------|
| Kullanıcı Listesi | Kullanıcılar sayfası |
| Kullanıcı Ekle | Kullanıcı ekleme |
| Grup Listesi | Grup / yetki sayfası |
| Grup Ekle | Grup ekleme |

---

## Dosya konumları

- **Sayfalar:** `PageResource`, `SayfaEkle` (yönlendirme)
- **Modüller:** `App\Filament\Pages\Modul*`
- **Servisler / Projeler / Bloglar:** İlgili Resource + `*Ekle`, `*KategoriEkle` sayfaları
- **Ayarlar:** `AyarlarGenel`, `AyarlarApi`, `AyarlarMail`
- **Kullanıcılar:** `UserResource`, `RoleResource`, `KullaniciEkle`, `GrupEkle`
