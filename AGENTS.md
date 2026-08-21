# Proje geliştirme kuralları

- Tüm dosya ve işlemlerde UTF-8 kullan.
- Yönetim datatable geliştirmelerinde `laravel-core/docs/architecture/admin-table-standard.md` sözleşmesine uy.
- Filament Table yerine jQuery DataTables, CORK DataTables veya ikinci bir istemci tarafı tablo motoru ekleme.
- Arama, filtreleme, sıralama ve pagination sunucu tarafında kalmalı; normal sayfa açılışında bütün kayıtları tarayıcıya yükleme.
- Ortak tablo davranışını tablo sınıflarına kopyalama. Önce `Table::configureUsing(...)`, `TablePaginationDefaults` ve `cork-admin-tables.css` katmanlarını kullan.
- Yeni ortak tablo özelliği normal açılışa ek sorgu, N+1 sorgu, tablo başına JavaScript/Alpine bileşeni veya pahalı toplam sorgusu eklememeli.
- `1000` ve `Hepsi` yalnız kullanıcı açıkça seçtiğinde çalışmalı. CSV/Excel ve toplam hesapları yalnız aksiyon çağrıldığında sorgulanmalı.
- Print/PDF/fiş, küçük ilişki seçicileri ve dashboard özetlerini klasik datatable kabul edip otomatik dönüştürme.
- Cariler üzerinde onaylanan ortak tablo standardını koru; yeni ortak özellikleri önce tek temsilci tabloda doğrulamadan proje geneline yayma.
- Yönetim panelindeki KPI/özet/bilgi kartlarında `laravel-core/docs/architecture/admin-card-standard.md` sözleşmesine uy; yeni kartlarda `yk-info-card`, kart gruplarında `yk-info-card-grid` kullan ve sayfa içine ayrı kart CSS'i kopyalama.
