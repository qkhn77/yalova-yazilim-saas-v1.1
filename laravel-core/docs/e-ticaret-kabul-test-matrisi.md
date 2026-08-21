# E-Ticaret Kabul Test Matrisi

## Format
Her test kaydi su alanlarla yazilir:
- ID
- Modul
- Onkosul
- Adimlar
- Beklenen Sonuc
- Negatif Durum
- Oncelik (P1/P2/P3)
- Sorumlu
- Kanit (ekran goruntusu, log, query sonucu)

## P1 (Canliya cikis bloklayici)

### M-01 Modul aktif/pasif
- Kural: Modul kapaliyken menu ve buton gizli olmali.
- Kural: `/sepet`, `/checkout` ve ilgili endpointler 403/404 donmeli.
- Kural: Kapali mod istekleri audit log'a dusmeli.

### O-01 Siparis durum gecis guard
- Kural: Izinli gecisler basarili olmali.
- Kural: Yasak gecisler engellenmeli ve kullaniciya anlamli hata donmeli.
- Kural: Gonderildi durumunda takip no zorunlu olmali.

### P-01 Odeme
- Basarili odeme: durum `onaylandi`, stok dusum ve log kaydi.
- Basarisiz odeme: durum `basarisiz_odeme`, tekrar deneme akisi.
- Retry limiti: route rate limit ile korunmali.

### I-01 Iptal/Iade
- Tam iptal: stok iade, durum gecmisi, audit kaydi.
- Kismi iade: satir bazli iade kaydi ve finans etkisi logu.

### K-01 Kargo
- Kargo yontemi CRUD audit kaydi.
- Takip no giris zorunlulugu.

### MSG-01 Mesaj yonetimi
- Ilk mesaj: `yeni`.
- Admin yaniti: `yanitlandi`.
- Sonraki musteri mesaji: `okunmamis`.
- Tamamlandi tik: `tamamlandi`.
- 12 saat SLA: ihlalde `sla_ihlal_mi=true`.

### B-01 Bildirim
- Olay-kanal eslesmeleri dogru tetiklenmeli.
- Log kaydi ve tekrar gonderim calismali.

### SEC-01 Guvenlik
- Tenant login brute-force: 5 hatada 15 dk kilit.
- Kademeli bekleme: 3. denemeden sonra cooldown.
- Checkout/odeme retry route limitleri aktif.

### AUD-01 Audit
- Modül ac/kapat, siparis durum degisimi, odeme/kargo/kampanya/pazaryeri/mesaj guncellemeleri audit'te olmalı.

## P2 (Canliyi engellemeyen ama zorunlu hedef)
- Raporlar, filtreler, export ekranlari
- Kampanya kural cesitleri (x al y ode, kupon limitleri)
- Pazaryeri senkron hata/uyari ekranlari
- Kullanilabilirlik ve mobil duzen kontrolleri

## Go/No-Go
- P1: acik bug = 0
- P2: kritik olmayan acik bug olabilir, cozum tarihi atanmis olmali
- Guvenlik testleri gecmis olmali
- Teknik + urun sorumlusu yazili onay vermeli