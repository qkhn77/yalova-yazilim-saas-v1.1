# Admin bilgi kartı standardı

Yönetim panelindeki KPI, özet ve bilgi kartları finans panelindeki yatay CORK kart düzenini izler.

## Görsel sözleşme

- Kart kökü `yk-info-card` sınıfını kullanır.
- Aynı sıradaki kartlar `yk-info-card-grid` içinde yatay ve eşit genişlikte dizilir.
- Genişlik `minmax(180px, 1fr)` ile akışkan olmalı; kartlara sabit piksel genişliği verilmemelidir.
- Kart yüzeyi, kenarlık, radius, gölge ve hover davranışı merkezi `cork-admin-widgets.css` katmanından gelir.
- Etiketler kısa ve muted, ana değerler belirgin ve tabular-numeric, açıklamalar küçük ve ikincil olmalıdır.
- Mobilde grid otomatik olarak alt satıra geçer; yatay taşma oluşturulmaz.

## Uygulama

Yeni bir bilgi kartı eklerken yalnızca markup'a `yk-info-card` ekleyin. Kart grubu için `yk-info-card-grid` kullanın. Renk, gölge, radius ve hover CSS'ini sayfa veya modül içine kopyalamayın; merkezi standardı kullanın.

Bu katman veri sorgusu, Livewire state'i veya kartların hesaplama davranışını değiştirmez.
