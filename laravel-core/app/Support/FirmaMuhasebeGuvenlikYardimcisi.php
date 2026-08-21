<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Firma silme / arşiv öncesi muhasebe verisi kontrolü.
 */
class FirmaMuhasebeGuvenlikYardimcisi
{
    /**
     * Bu firmaya ait herhangi bir muhasebe çekirdeği kaydı var mı?
     */
    public static function muhasebeKaydiVarMi(int $firmaId): bool
    {
        $tablolar = [
            'faturalar',
            'fatura_numara_sayaclari',
            'teklif_numara_sayaclari',
            'cariler',
            'cari_hareketleri',
            'cari_hareket_eslesmeleri',
            'stok_kartlari',
            'stok_hareketleri',
            'finans_hareketleri',
            'kasa_hareketleri',
            'banka_hareketleri',
            'pos_hareketleri',
        ];

        foreach ($tablolar as $tablo) {
            if (! SaaSemaYardimcisi::tabloVarMi($tablo)) {
                continue;
            }
            if (DB::table($tablo)->where('firma_id', $firmaId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
