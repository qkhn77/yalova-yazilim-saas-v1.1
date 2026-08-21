<?php

return [

    /*
    | Ödeme bekleniyor siparişlerinin otomatik iptal süresi (dakika).
    */
    'odeme_dakika' => (int) env('ECOMMERCE_ODEME_DAKIKA', 15),

    /*
    | Ödeme başarılı olunca kasa tahsilatı için muhasebe kayıtları (firma başına .env ile verilir).
    */
    'tahsilat_cari_id' => env('ECOMMERCE_TAHSILAT_CARI_ID') !== null && env('ECOMMERCE_TAHSILAT_CARI_ID') !== ''
        ? (int) env('ECOMMERCE_TAHSILAT_CARI_ID')
        : null,
    'tahsilat_kasa_id' => env('ECOMMERCE_TAHSILAT_KASA_ID') !== null && env('ECOMMERCE_TAHSILAT_KASA_ID') !== ''
        ? (int) env('ECOMMERCE_TAHSILAT_KASA_ID')
        : null,
    /*
    |--------------------------------------------------------------------------
    | Cron fallback token
    |--------------------------------------------------------------------------
    | Hosting/cPanel ortamında scheduler çalışmasa bile ödeme zaman aşımı
    | işlemi web endpoint üzerinden tetiklenebilir.
    */
    'cron_fallback_token' => env('ECOMMERCE_CRON_FALLBACK_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Cron fallback throttle (dakika)
    |--------------------------------------------------------------------------
    | Aynı işi yoğun trafik altında üst üste çalıştırmamak için.
    */
    'cron_fallback_throttle_dakika' => (int) env('ECOMMERCE_CRON_FALLBACK_THROTTLE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Finans iadesi otomatik ters kayıt
    |--------------------------------------------------------------------------
    | Sipariş iptalinde başarılı ödeme varsa finans tahsilatının ters kaydı
    | otomatik oluşturulup oluşturulmayacağını belirler.
    */
    'finans_iade_otomatik' => filter_var(env('ECOMMERCE_FINANS_IADE_OTOMATIK', 'true'), FILTER_VALIDATE_BOOL),
];
