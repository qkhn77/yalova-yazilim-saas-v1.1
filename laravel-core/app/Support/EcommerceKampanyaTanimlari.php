<?php

namespace App\Support;

class EcommerceKampanyaTanimlari
{
    /**
     * @return array<string, string>
     */
    public static function tipler(): array
    {
        return [
            'yuzde' => 'Yuzde indirim',
            'sabit_tutar' => 'Sabit tutar',
            'x_al_y_ode' => 'X al Y ode',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function hedefTipleri(): array
    {
        return [
            'genel' => 'Tum urunler',
            'kategori' => 'Kategori bazli',
            'urun' => 'Urun bazli',
            'kullanici' => 'Kullanici bazli',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paraBirimleri(): array
    {
        return [
            'TRY' => 'TRY',
            'USD' => 'USD',
            'EUR' => 'EUR',
            'GBP' => 'GBP',
        ];
    }
}