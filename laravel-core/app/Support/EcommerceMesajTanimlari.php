<?php

namespace App\Support;

class EcommerceMesajTanimlari
{
    public const KONU_TIPI_MUSTERI = 'musteri';
    public const KONU_TIPI_URUN = 'urun';

    public const DURUM_YENI = 'yeni';
    public const DURUM_OKUNMAMIS = 'okunmamis';
    public const DURUM_YANITLANDI = 'yanitlandi';
    public const DURUM_MUSTERI_YANITI_GELDI = 'musteri_yaniti_geldi';
    public const DURUM_TAMAMLANDI = 'tamamlandi';

    public const GONDEREN_MUSTERI = 'musteri';
    public const GONDEREN_ADMIN = 'admin';

    /**
     * @return array<string, string>
     */
    public static function konuTipleri(): array
    {
        return [
            self::KONU_TIPI_MUSTERI => 'Musteri Mesaji',
            self::KONU_TIPI_URUN => 'Urun Mesaji',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function durumlar(): array
    {
        return [
            self::DURUM_YENI => 'Yeni',
            self::DURUM_OKUNMAMIS => 'Okunmamis',
            self::DURUM_YANITLANDI => 'Yanitlandi',
            self::DURUM_MUSTERI_YANITI_GELDI => 'Musteri Yaniti Geldi',
            self::DURUM_TAMAMLANDI => 'Tamamlandi',
        ];
    }

    /**
     * @return list<string>
     */
    public static function slaTakipDurumlari(): array
    {
        return [
            self::DURUM_YENI,
            self::DURUM_OKUNMAMIS,
            self::DURUM_MUSTERI_YANITI_GELDI,
        ];
    }
}