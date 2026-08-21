<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Scopes\FirmaIdTenantScope;

final class PersonelAvansMahsupServisi
{
    public function maasHareketiTamOdendigindeMahsupEt(PersonelMaasHareketi $hareket): int
    {
        if ((float) $hareket->kalan_tutar > 0 || (float) $hareket->avans_kesintisi <= 0) {
            return 0;
        }

        $donem = PersonelMaasDonemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $hareket->firma_id)
            ->whereKey($hareket->maas_donemi_id)
            ->first();

        if (! $donem) {
            return 0;
        }

        $kalanMahsup = round((float) $hareket->avans_kesintisi, 2);
        $mahsupEdilen = 0;

        $avanslar = PersonelAvansi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $hareket->firma_id)
            ->where('personel_id', $hareket->personel_id)
            ->where('durum', 'onaylandi')
            ->where('maastan_dusuldu_mu', false)
            ->where('kalan_tutar', '>', 0)
            ->whereBetween('tarih', [
                $donem->baslangic_tarihi?->toDateString(),
                $donem->bitis_tarihi?->toDateString(),
            ])
            ->orderBy('tarih')
            ->orderBy('id')
            ->get();

        foreach ($avanslar as $avans) {
            if ($kalanMahsup <= 0) {
                break;
            }

            $avansKalan = round((float) $avans->kalan_tutar, 2);
            if ($avansKalan <= 0) {
                continue;
            }

            $mahsupTutari = min($avansKalan, $kalanMahsup);
            $yeniKalan = round($avansKalan - $mahsupTutari, 2);

            $avans->forceFill([
                'kalan_tutar' => $yeniKalan,
                'maastan_dusuldu_mu' => $yeniKalan <= 0,
                'mahsup_durumu' => $yeniKalan <= 0 ? 'mahsup_edildi' : 'kismi_mahsup',
            ])->save();

            $kalanMahsup = round($kalanMahsup - $mahsupTutari, 2);
            $mahsupEdilen++;
        }

        return $mahsupEdilen;
    }
}
