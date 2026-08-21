<?php

namespace App\TeknikServis\Filament;

/**
 * Teknik servis kayıt listesi ön ayarı — {@see TeknikServisListePresetleri} ile eşleşir.
 */
enum TeknikServisListePreset: string
{
    case Tum = 'tum';

    case Yeni = 'yeni';

    case Acik = 'acik';

    case Tezgahta = 'tezgahta';

    case ParcaBekleyen = 'parca_bekleyen';

    case GarantiyeGonderilen = 'garantiye_gonderilen';

    case FiyatVerilen = 'fiyat_verilen';

    case TeslimBekleyen = 'teslim_bekleyen';

    case TamamlananDisServis = 'tamamlanan_dis_servis';

    case TeslimEdilen = 'teslim_edilen';

    case Iptal = 'iptal';

    case Iade = 'iade';
}
