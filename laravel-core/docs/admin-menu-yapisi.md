# Admin Panel Menü Yapısı

Bu doküman, hem mevcut **web sitesi** yönetimi hem de ileride **çok firmalı ön muhasebe SaaS** için önerilen menü yapısını tanımlar.

---

## 1. Genel prensipler

- **Şimdi:** Site içeriği (sayfalar, servisler, projeler, blog) ve temel ayarlar.
- **İleride:** Aynı gruplar korunur; "İçerik" yanına "Muhasebe" ve "Firma" odaklı menüler eklenir.
- **Çok firmalı SaaS:** Her firma kendi verisiyle çalışır; super admin tüm firmaları yönetir.

---

## 2. Aşama 1 – Web sitesi (şu an)

Menü grupları ve sırası:

| Sıra | Grup        | Açıklama |
|------|-------------|----------|
| 1    | **Kontrol Paneli** | Dashboard, özet bilgiler (varsayılan) |
| 2    | **İçerik**  | Site içeriği: sayfalar, servisler, projeler, blog |
| 3    | **İletişim**| İletişim formu mesajları, talep yönetimi |
| 4    | **Ayarlar** | Site ayarları, menü, SEO, kullanıcı (ileride rol) |

### 2.1 Detaylı menü (Aşama 1)

```
Kontrol Paneli
  └── Dashboard

İçerik
  └── Sayfalar          (Hakkımızda, İletişim, anasayfa blokları – ileride)
  └── Servisler         (Servis listesi, slug, açıklama)
  └── Projeler          (Proje listesi, slug, görseller)
  └── Blog              (Yazılar, kategoriler)

İletişim
  └── Gelen mesajlar    (Form talepleri, teklif istekleri)

Ayarlar
  └── Site ayarları     (Logo, iletişim bilgileri, sosyal linkler, seo etiketleri,)
  └── Kullanıcılar      (Admin kullanıcıları – ileride rollere açılır)
```

---

## 3. Aşama 2 – Çok firmalı ön muhasebe SaaS (ileride)

Mevcut gruplar kalır; **Muhasebe** ve **Firma** grupları eklenir. Firma seçimi (tenant) üst bar veya dashboard’da yapılır.

### 3.1 Önerilen menü sırası (SaaS)

| Sıra | Grup            | Açıklama |
|------|-----------------|----------|
| 1    | **Kontrol Paneli** | Dashboard (firma bazlı özet) |
| 2    | **Firma**        | Firma bilgileri, kullanıcılar, abonelik (tenant) |
| 3    | **Muhasebe**     | Cari, faturalar, banka/kasa, defterler |
| 4    | **İçerik**      | (Opsiyonel) Firma web sayfası / katalog |
| 5    | **İletişim**    | Müşteri iletişim kayıtları |
| 6    | **Raporlar**    | Ön muhasebe raporları, özetler |
| 7    | **Sistem**      | Super admin: firmalar, planlar, genel ayarlar |

### 3.2 Muhasebe alt menüsü (ön muhasebe)

```
Muhasebe
  └── Cari hesaplar
  └── Satış faturaları
  └── Alış faturaları
  └── Banka / Kasa
  └── Giderler
  └── Yevmiye / Defter (opsiyonel)
```

### 3.3 Firma grubu (tenant yönetimi)

```
Firma
  └── Firma bilgileri   (Ünvan, vergi no, adres)
  └── Kullanıcılar      (Bu firmadaki kullanıcılar ve roller)
  └── Abonelik / Plan   (Paket, limitler)
```

### 3.4 Sistem (sadece super admin)

```
Sistem
  └── Firmalar         (Tüm tenant’lar)
  └── Planlar / Fiyatlandırma
  └── Genel ayarlar
  └── Kullanıcılar     (Tüm panel kullanıcıları)
```

---

## 4. Teknik notlar (Filament)

- Menü grupları `AdminPanelProvider` içinde `navigationGroups()` ile tanımlı; sıra burada belirlenir.
- Her Resource/Page’de `navigationGroup('Grup Adı')` ve `navigationSort(n)` kullanılır.
- İleride çok tenant için:
  - Filament’in tenancy desteği veya global scope ile `company_id` filtreleri kullanılabilir.
  - “Firma seçimi” için üst bara custom bir tenant switcher eklenebilir.

Bu yapı, önce web sitesi adminini toparlamanıza, sonra aynı paneli SaaS’a genişletmenize uyumludur.
