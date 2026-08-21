<?php

namespace App\TeknikServis\Filament;

use App\Models\TeknikServis\TeknikServisDurumTanimi;

/**
 * Servis durumu {@see TeknikServisDurumTanimi::$kod} değerleri.
 */
final class TeknikServisDurumKodlari
{
    public const YENI = 'yeni_kayit';

    public const ACIK = 'acik';

    public const TEZGAHTA = 'tezgahta';

    public const PARCA_BEKLEYEN = 'parca_bekleyen';

    public const GARANTIYE_GONDERILDI = 'garantiye_gonderilen';

    public const FIYAT_VERILDI = 'fiyat_verilen';

    public const TESLIM_BEKLEYEN = 'teslim_bekleyen';

    public const TAMAMLANDI = 'tamamlandi';

    public const DIS_SERVIS_TAMAMLANDI = 'dis_servis_tamamlandi';

    public const TESLIM_EDILDI = 'teslim_edilen';

    public const IPTAL = 'iptal';

    public const IADE = 'iade';

    // Geriye uyumluluk
    public const YENI_ESKI = 'yeni';
    public const PARCA_BEKLIYOR = 'parca_bekliyor';
    public const TESLIM_BEKLIYOR = 'teslim_bekliyor';
}