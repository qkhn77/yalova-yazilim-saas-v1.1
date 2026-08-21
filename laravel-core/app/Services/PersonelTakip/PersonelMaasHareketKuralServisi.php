<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Validation\ValidationException;

final class PersonelMaasHareketKuralServisi
{
    public function hazirlaVeDogrula(PersonelMaasHareketi $hareket): void
    {
        if (! $hareket->firma_id || ! $hareket->maas_donemi_id || ! $hareket->personel_id) {
            return;
        }

        $hatalar = [];
        $donem = $this->donem($hareket);
        $personel = $this->personel($hareket);

        if (! $donem) {
            $hatalar['maas_donemi_id'][] = 'Seçilen maaş dönemi bu firmaya ait değil.';
        }

        if (! $personel) {
            $hatalar['personel_id'][] = 'Seçilen personel bu firmaya ait değil.';
        }

        if ($donem && $personel && $donem->sube_id && $personel->sube_id && (int) $donem->sube_id !== (int) $personel->sube_id) {
            $hatalar['personel_id'][] = 'Personel maaş döneminin şubesiyle uyumlu değil.';
        }

        $net = $this->netTutar($hareket);
        $odenen = (float) $hareket->odenen_tutar;

        if ($net < 0) {
            $hatalar['net_tutar'][] = 'Net maaş tutarı negatif olamaz.';
        }

        if ($odenen > $net) {
            $hatalar['odenen_tutar'][] = 'Ödenen tutar net tutarı aşamaz.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        $hareket->net_tutar = $net;
        $hareket->kalan_tutar = max(0, $net - $odenen);
    }

    private function netTutar(PersonelMaasHareketi $hareket): float
    {
        $gelir = (float) $hareket->brut_tutar
            + (float) $hareket->fazla_mesai_tutari
            + (float) $hareket->prim_tutari
            + (float) $hareket->ek_odeme_tutari;

        $kesinti = (float) $hareket->avans_kesintisi
            + (float) $hareket->devamsizlik_kesintisi
            + (float) $hareket->diger_kesinti;

        return round($gelir - $kesinti, 2);
    }

    private function donem(PersonelMaasHareketi $hareket): ?PersonelMaasDonemi
    {
        return PersonelMaasDonemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $hareket->firma_id)
            ->whereKey($hareket->maas_donemi_id)
            ->first();
    }

    private function personel(PersonelMaasHareketi $hareket): ?Personel
    {
        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $hareket->firma_id)
            ->whereKey($hareket->personel_id)
            ->first();
    }
}
