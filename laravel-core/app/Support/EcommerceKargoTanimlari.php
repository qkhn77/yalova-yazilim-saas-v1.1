<?php

namespace App\Support;

class EcommerceKargoTanimlari
{
    /**
     * @return array<string, string>
     */
    public static function ucretTipleri(): array
    {
        return [
            'sabit' => 'Sabit ücret',
            'desi' => 'Desi bazlı',
            'tutar' => 'Sipariş tutarına göre',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function entegrasyonlar(): array
    {
        return [
            'ups' => 'UPS',
            'dhl' => 'DHL',
            'ship_entegrasyon' => 'Ship Entegre',
            'hepsijet' => 'HepsiJet',
            'aras' => 'Aras',
            'yurtici' => 'Yurtiçi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function hizmetTipleri(): array
    {
        return [
            'standart' => 'Standart teslimat',
            'ekspres' => 'Ekspres teslimat',
            'same_day' => 'Aynı gün teslimat',
            'international_standard' => 'Yurt dışı standart',
            'international_express' => 'Yurt dışı ekspres',
        ];
    }
}
