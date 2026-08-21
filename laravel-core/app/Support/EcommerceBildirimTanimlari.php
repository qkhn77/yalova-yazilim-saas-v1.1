<?php

namespace App\Support;

final class EcommerceBildirimTanimlari
{
    public const KANAL_EMAIL = 'email';
    public const KANAL_PANEL = 'panel';
    public const KANAL_SMS = 'sms';

    public static function kanallar(): array
    {
        return [
            self::KANAL_EMAIL => 'E-posta',
            self::KANAL_PANEL => 'Panel',
            self::KANAL_SMS => 'SMS/WhatsApp',
        ];
    }

    public static function olaylar(): array
    {
        return [
            'siparis_alindi' => 'Sipariş Alındı (Onay Bekliyor)',
            'siparis_onaylandi' => 'Sipariş Onaylandı',
            'kargoya_verildi' => 'Kargoya Verildi',
            'kargo_bilgisi_guncellendi' => 'Kargo Bilgisi Güncellendi',
            'teslim_edildi' => 'Teslim Edildi',
            'iptal_talebi' => 'İptal Talebi Açıldı',
            'iptal_edildi' => 'İptal Edildi',
            'iade_talebi' => 'İade Talebi Açıldı',
            'iade_edildi' => 'İade Edildi',
            'odeme_basarisiz' => 'Başarısız Ödeme',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function varsayilanKanalHaritasi(): array
    {
        return [
            'siparis_alindi' => [self::KANAL_EMAIL, self::KANAL_PANEL],
            'siparis_onaylandi' => [self::KANAL_EMAIL, self::KANAL_SMS],
            'kargoya_verildi' => [self::KANAL_EMAIL, self::KANAL_SMS],
            'kargo_bilgisi_guncellendi' => [self::KANAL_EMAIL, self::KANAL_SMS],
            'teslim_edildi' => [self::KANAL_EMAIL],
            'iptal_talebi' => [self::KANAL_EMAIL, self::KANAL_PANEL],
            'iptal_edildi' => [self::KANAL_EMAIL],
            'iade_talebi' => [self::KANAL_EMAIL, self::KANAL_PANEL],
            'iade_edildi' => [self::KANAL_EMAIL],
            'odeme_basarisiz' => [self::KANAL_EMAIL, self::KANAL_PANEL],
        ];
    }

    /**
     * @return array<string, array<int, array{kanal: string, hedef: string}>>
     */
    public static function varsayilanGonderimler(): array
    {
        return [
            'siparis_alindi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'admin'],
                ['kanal' => self::KANAL_PANEL, 'hedef' => 'admin'],
            ],
            'siparis_onaylandi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
                ['kanal' => self::KANAL_SMS, 'hedef' => 'musteri'],
            ],
            'kargoya_verildi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
                ['kanal' => self::KANAL_SMS, 'hedef' => 'musteri'],
            ],
            'kargo_bilgisi_guncellendi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
                ['kanal' => self::KANAL_SMS, 'hedef' => 'musteri'],
            ],
            'teslim_edildi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
            ],
            'iptal_talebi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'admin'],
                ['kanal' => self::KANAL_PANEL, 'hedef' => 'admin'],
            ],
            'iptal_edildi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
            ],
            'iade_talebi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'admin'],
                ['kanal' => self::KANAL_PANEL, 'hedef' => 'admin'],
            ],
            'iade_edildi' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
            ],
            'odeme_basarisiz' => [
                ['kanal' => self::KANAL_EMAIL, 'hedef' => 'musteri'],
                ['kanal' => self::KANAL_PANEL, 'hedef' => 'admin'],
                ['kanal' => self::KANAL_PANEL, 'hedef' => 'musteri'],
            ],
        ];
    }
}
