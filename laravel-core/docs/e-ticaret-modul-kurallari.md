# E-Ticaret Modül Kuralları

Bu doküman, e-ticaret modülünün temel çalışma kurallarını uygulama içinde standart hale getirmek için eklenmiştir.

## 1. Aktivasyon Mantığı

- Modül erişimi iki koşulla birlikte değerlendirilir:
- `e_ticaret` SaaS modülü firma için erişilebilir olmalıdır.
- Firma ayarı `ecommerce_etkin_mi = true` olmalıdır.

Bu iki koşuldan biri sağlanmıyorsa modül kapalı kabul edilir.

## 2. Kapalı Mod Davranışı

- Frontend'de `Sepete Ekle` butonu gösterilmez.
- Header'daki `Sepet` bağlantısı gösterilmez.
- `/sepet`, `/checkout`, `/odeme/*`, `/siparis-basarili`, `/siparis-takip` rotaları dışarıdan erişime kapatılır (404).
- Kapalı modda bu rotalara gelen erişim denetim kaydına yazılır.

## 3. İlk Kurulum Kuralı

- Firma e-ticareti ilk kez aktif ederse bir defalık kurulum tamamlanır.
- `ecommerce_initialized_at` ve `ecommerce_kurulum_versiyon` değerleri kaydedilir.
- Daha önce aktif edilmiş bir firmada tekrar aç-kapat-aç yapıldığında ilk kurulum tekrar çalışmaz.

## 4. Denetim (Audit) Kuralları

- `ecommerce_etkin_mi` değiştiğinde denetim kaydı zorunludur.
- İlk kurulum tamamlandığında denetim kaydı zorunludur.
- Kapalı mod erişim denemeleri denetim kaydına yazılır.

## 5. Muhasebe Güvenliği

- Bu kurallar muhasebe, stok, finans ve fatura çekirdek servislerinin işleyişini değiştirmez.
- E-ticaret kapatma/açma kuralı sadece e-ticaret giriş noktaları (route/UI) üzerinde uygulanır.
