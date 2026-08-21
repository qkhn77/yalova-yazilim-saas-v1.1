<?php

/**
 * Admin panel menü yapısı.
 * Filament Resource/Page oluştururken navigationGroup() için bu etiketleri kullanın.
 * Detaylı açıklama: docs/admin-menu-yapisi.md
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Aşama 1: Web sitesi (şu an)
    |--------------------------------------------------------------------------
    */
    'groups' => [
        'Kontrol Paneli',
        'İçerik',
        'İletişim',
        'Ayarlar',
    ],

    /*
    |--------------------------------------------------------------------------
    | İleride SaaS için eklenecek gruplar (navigationGroups'a eklenecek)
    |--------------------------------------------------------------------------
    */
    'groups_future_saas' => [
        'Firma',
        'Muhasebe',
        'Raporlar',
        'Sistem',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource/Page önerilen gruplar (kopyala-yapıştır için)
    |--------------------------------------------------------------------------
    */
    'resource_groups' => [
        'dashboard' => 'Kontrol Paneli',
        'content'   => 'İçerik',      // Servisler, Servis Kategorileri, WebProje, Proje Kategorileri, Blog, Blog Kategorileri
        'contact'   => 'İletişim',    // Gelen mesajlar
        'settings'  => 'Ayarlar',     // Site ayarları, Kullanıcılar
    ],

    // Panel menü sırası (İçerik grubu): 1 Servisler, 2 Servis Kategorileri, 3 WebProje, 4 Proje Kategorileri, 5 Blog, 6 Blog Kategorileri
];
