<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaKalemi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use Illuminate\Support\Facades\DB;

/**
 * Faturayı yalnız ticari taslak verisi olarak kopyalar.
 * Stok/cari/finans hareketleri, ölçü dağılımları ve tarihsel iade bağlantıları
 * yeni belgeye taşınmaz.
 */
class FaturaKopyalamaServisi
{
    public function kopyala(Fatura $kaynak): Fatura
    {
        return DB::transaction(function () use ($kaynak): Fatura {
            $kaynak = Fatura::query()->lockForUpdate()->with(['kalemler.olcuDagilimlari'])->findOrFail($kaynak->getKey());

            $kopya = $kaynak->replicate();
            $kopya->fill([
                'durum' => FaturaDurumu::Taslak->value,
                'fatura_no' => null,
                'bagli_fatura_id' => null,
                'odeme_durumu' => 'odenmedi',
                'odendi_tutari' => '0',
                'baz_odendi_tutari' => '0',
                'acik_tutar' => $kaynak->odenecek_tutar,
                'baz_acik_tutar' => $kaynak->baz_odenecek_tutar,
                'iptal_nedeni' => null,
                'iptal_edildi_at' => null,
                'iptal_eden_kullanici_id' => null,
                'e_belge_uuid' => null,
                'e_belge_durumu' => null,
                'e_belge_gonderildi_at' => null,
                'e_belge_saglayici_belge_id' => null,
                'e_belge_hash' => null,
                'e_belge_yanit_kodu' => null,
                'e_belge_yanit_mesaji' => null,
                'e_belge_son_hata' => null,
            ]);
            $kopya->save();

            foreach ($kaynak->kalemler as $kaynakKalem) {
                $kalem = $kaynakKalem->replicate();
                $olculu = $kaynakKalem->olcuDagilimlari->isNotEmpty();
                $kalem->fill([
                    'fatura_id' => $kopya->getKey(),
                    'kaynak_fatura_kalemi_id' => null,
                    'olcu_donusum_snapshot' => null,
                    'parca_dagilimi' => null,
                ]);
                if ($olculu) {
                    // Ölçülü çıkış kopyası yeni depo/ölçü/parti seçimi olmadan onaylanamaz.
                    $kalem->depo_id = null;
                }
                $kalem->save();
            }

            return $kopya->fresh(['kalemler.olcuDagilimlari']);
        }, 3);
    }
}
