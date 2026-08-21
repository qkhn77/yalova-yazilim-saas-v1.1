<?php

namespace App\Support;

class EcommercePazaryeriTanimlari
{
    public const PAZARYERI_AMAZON = 'amazon';
    public const PAZARYERI_EBAY = 'ebay';
    public const PAZARYERI_TRENDYOL = 'trendyol';
    public const PAZARYERI_HEPSIBURADA = 'hepsiburada';
    public const PAZARYERI_N11 = 'n11';

    /**
     * @return array<string, string>
     */
    public static function pazaryerleri(): array
    {
        return [
            self::PAZARYERI_AMAZON => 'Amazon',
            self::PAZARYERI_EBAY => 'eBay',
            self::PAZARYERI_TRENDYOL => 'Trendyol',
            self::PAZARYERI_HEPSIBURADA => 'Hepsiburada',
            self::PAZARYERI_N11 => 'N11',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function senkronYonleri(): array
    {
        return [
            'tek_yon' => 'Tek yon (pazaryerine gonder)',
        ];
    }

    public static function pazaryeriAdi(string $kod): string
    {
        return self::pazaryerleri()[$kod] ?? $kod;
    }
}