<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class PersonelMaasDonemiKuralServisi
{
    public function hazirlaVeDogrula(PersonelMaasDonemi $donem): void
    {
        if (! $donem->firma_id || ! $donem->baslangic_tarihi || ! $donem->bitis_tarihi) {
            return;
        }

        $baslangic = Carbon::parse($donem->baslangic_tarihi);
        $bitis = Carbon::parse($donem->bitis_tarihi);
        $hatalar = [];

        if ($bitis->lt($baslangic)) {
            $hatalar['bitis_tarihi'][] = 'Dönem bitişi başlangıç tarihinden önce olamaz.';
        }

        if ($donem->donem_ay && ($donem->donem_ay < 1 || $donem->donem_ay > 12)) {
            $hatalar['donem_ay'][] = 'Dönem ayı 1 ile 12 arasında olmalıdır.';
        }

        if ($donem->sube_id && ! $this->subeFirmayaAitMi($donem)) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        $donem->donem_yil = $donem->donem_yil ?: (int) $baslangic->year;
        $donem->donem_ay = $donem->donem_ay ?: (int) $baslangic->month;
        $donem->ad = $donem->ad ?: sprintf('%04d-%02d Maaş Dönemi', $donem->donem_yil, $donem->donem_ay);
        $donem->para_birimi = $donem->para_birimi ?: 'TRY';
    }

    private function subeFirmayaAitMi(PersonelMaasDonemi $donem): bool
    {
        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $donem->firma_id)
            ->whereKey($donem->sube_id)
            ->exists();
    }
}
