<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FIFO eşleşme logları
    |--------------------------------------------------------------------------
    |
    | Varsayılan: rutin eşleşme satırları debug (production gürültüsünü azaltır).
    | MUHASEBE_FIFO_RUTIN_INFO=true ile info seviyesine çekilir.
    |
    | MUHASEBE_FIFO_LOG_CHANNEL=muhasebe ile ayrı daily dosyaya yönlendirin;
    | config/logging.php içinde "muhasebe" kanalı tanımlı olmalıdır.
    |
    */
    'fifo' => [
        'log_channel' => env('MUHASEBE_FIFO_LOG_CHANNEL'),
        'rutin_info_seviyesi' => (bool) env('MUHASEBE_FIFO_RUTIN_INFO', false),
    ],

    'stok' => [
        // En güvenli varsayılan: negatif stok kapalı.
        'negatif_stok_izinli' => (bool) env('MUHASEBE_STOK_NEGATIF_IZINLI', false),
        'log_channel' => env('MUHASEBE_STOK_LOG_CHANNEL', 'muhasebe'),
        // Üretimde hareket oluşturma satırlarını debug bırakıp, uyarı/hata sinyallerini öne çıkar.
        'olusturma_log_seviyesi' => env('MUHASEBE_STOK_OLUSTURMA_LOG_SEVIYESI', 'debug'),
        'yeniden_hesaplama_batch' => (int) env('MUHASEBE_STOK_YENIDEN_HESAPLAMA_BATCH', 500),
        'maliyet_hata_hard_fail' => (bool) env('MUHASEBE_STOK_MALIYET_HATA_HARD_FAIL', false),
        // production ortamında rebuild komutunu güvenlik için kapatabilirsiniz.
        'rebuild_canli_izinli' => (bool) env('MUHASEBE_STOK_REBUILD_CANLI_IZINLI', false),
        // Negatif stok mutlak miktarı bu eşiğe eşit/üstündeyse ek error log (null = sadece kritik warning).
        'negatif_stok_kritik_esik' => is_numeric(env('MUHASEBE_STOK_NEGATIF_KRITIK_ESIK'))
            ? (string) env('MUHASEBE_STOK_NEGATIF_KRITIK_ESIK')
            : null,
        // stok:maliyet-yeniden-hesapla içinde zincir bozuksa exception fırlat.
        'zincir_hata_hard_fail' => (bool) env('MUHASEBE_STOK_ZINCIR_HATA_HARD_FAIL', false),
    ],

    'sistem' => [
        'log_channel' => env('MUHASEBE_SISTEM_LOG_CHANNEL', env('MUHASEBE_STOK_LOG_CHANNEL', 'muhasebe')),
    ],

    'alacak_plan_onay_limiti' => (float) env('MUHASEBE_ALACAK_PLAN_ONAY_LIMITI', 1000),

    /*
    |--------------------------------------------------------------------------
    | Avans mahsup + finans otomatik dağıtım (STEP 16.1)
    |--------------------------------------------------------------------------
    |
    | Varsayılanlar: otomasyon açık; üretimde ihtiyaç halinde env ile kapatılabilir.
    | dagitim_stratejisi: fifo = eski fatura önce (tarih↑, id↑); tarih = yeni fatura önce (tarih↓, id↓).
    |
    */
    'otomasyon' => [
        'avans_otomatik_mahsup' => (bool) env('MUHASEBE_AVANS_OTO_MAHSUP', true),
        'finans_otomatik_dagitim' => (bool) env('MUHASEBE_FINANS_OTO_DAGITIM', true),
        'dagitim_stratejisi' => env('MUHASEBE_DAGITIM_STRATEJISI', 'fifo'),
        'log_channel' => env('MUHASEBE_OTOMASYON_LOG_CHANNEL', env('MUHASEBE_FATURA_LOG_CHANNEL', 'muhasebe')),
    ],

    'fatura' => [
        'log_channel' => env('MUHASEBE_FATURA_LOG_CHANNEL', 'muhasebe'),
        // Onay/iptal/iade kritik; oluşturma/güncelleme debug seviyesinde kalır.
        'olusturma_log_seviyesi' => env('MUHASEBE_FATURA_OLUSTURMA_LOG_SEVIYESI', 'debug'),
        // true yapılırsa idempotent-atla sırasında kısmi hareket tutarsızlığı hard fail olur.
        'idempotent_tutarsizlik_hata' => (bool) env('MUHASEBE_FATURA_IDEMPOTENT_TUTARSIZLIK_HATA', false),
        // false yapılırsa fazla ödeme iş kuralı hataya düşer.
        'fazla_odeme_izinli' => (bool) env('MUHASEBE_FATURA_FAZLA_ODEME_IZINLI', true),
        // reconciliation tutarsızlıklarında hard fail kontrolü.
        'kapama_tutarsizlik_hard_fail' => (bool) env('MUHASEBE_FATURA_KAPAMA_TUTARSIZLIK_HARD_FAIL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Çoklu para birimi (Faz 1 - sadece altyapı)
    |--------------------------------------------------------------------------
    |
    | Bu aşamada davranış değişmez. Mevcut birebir para birimi kuralı korunur.
    | Alanlar sadece ileri fazlar için hazırlanır.
    |
    */
    'coklu_para_birimi' => [
        'aktif' => (bool) env('MUHASEBE_COKLU_PARA_BIRIMI_AKTIF', false),
        'baz_para_birimi' => strtoupper((string) env('MUHASEBE_BAZ_PARA_BIRIMI', 'TRY')),
        'kur_donusumu_aktif' => (bool) env('MUHASEBE_KUR_DONUSUMU_AKTIF', false),
    ],

    'doviz' => [
        'timeout_saniye' => (int) env('MUHASEBE_DOVIZ_TIMEOUT_SANIYE', 10),
        'tcmb_base_url' => (string) env('MUHASEBE_DOVIZ_TCMB_BASE_URL', 'https://www.tcmb.gov.tr/kurlar'),
        'tcmb_deger_tipi' => (string) env('MUHASEBE_DOVIZ_TCMB_DEGER_TIPI', 'ForexSelling'),
        'tcmb_geri_git_gun_sayisi' => (int) env('MUHASEBE_DOVIZ_TCMB_GERI_GIT_GUN', 7),
    ],

];
