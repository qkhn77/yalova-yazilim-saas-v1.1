<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class PersonelVardiyaKuralServisi
{
    public function dogrula(PersonelVardiyasi $vardiya): void
    {
        if (! $vardiya->firma_id || ! $vardiya->personel_id) {
            return;
        }

        $hatalar = [];
        $this->sablondanSaatleriDoldur($vardiya, $hatalar);

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        if (! $vardiya->baslangic_at || ! $vardiya->bitis_at) {
            return;
        }

        $baslangic = Carbon::parse($vardiya->baslangic_at);
        $bitis = Carbon::parse($vardiya->bitis_at);

        if ($bitis->lessThanOrEqualTo($baslangic)) {
            $hatalar['bitis_at'][] = 'Vardiya bitişi başlangıçtan sonra olmalıdır.';
        }

        $personel = Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $vardiya->firma_id)
            ->whereKey($vardiya->personel_id)
            ->first();

        if (! $personel) {
            $hatalar['personel_id'][] = 'Seçilen personel bu firmaya ait değil.';
        } else {
            if ($personel->durum !== Personel::DURUM_AKTIF) {
                $hatalar['personel_id'][] = 'Pasif veya işten ayrılmış personele vardiya yazılamaz.';
            }

            if ($vardiya->sube_id && $personel->sube_id && (int) $vardiya->sube_id !== (int) $personel->sube_id) {
                $hatalar['sube_id'][] = 'Vardiya şubesi personelin bağlı olduğu şubeyle uyumlu değil.';
            }
        }

        if ($vardiya->sube_id && ! $this->subeFirmayaAitMi($vardiya)) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        if ($hatalar === [] && $this->cakisanVardiyaVarMi($vardiya, $baslangic, $bitis)) {
            $hatalar['baslangic_at'][] = 'Bu personelin aynı saat aralığında başka bir vardiyası var.';
        }

        if ($hatalar === [] && $this->onayliIzinCakismasiVarMi($vardiya, $baslangic, $bitis)) {
            $hatalar['personel_id'][] = 'Bu personelin seçilen zaman aralığında onaylı izin kaydı var.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $hatalar
     */
    private function sablondanSaatleriDoldur(PersonelVardiyasi $vardiya, array &$hatalar): void
    {
        if (! $vardiya->vardiya_sablonu_id) {
            return;
        }

        $sablon = PersonelVardiyaSablonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $vardiya->firma_id)
            ->whereKey($vardiya->vardiya_sablonu_id)
            ->first();

        if (! $sablon) {
            $hatalar['vardiya_sablonu_id'][] = 'Seçilen vardiya şablonu bu firmaya ait değil.';

            return;
        }

        if (! $sablon->aktif_mi) {
            $hatalar['vardiya_sablonu_id'][] = 'Pasif vardiya şablonu kullanılamaz.';
        }

        if ($vardiya->sube_id && $sablon->sube_id && (int) $vardiya->sube_id !== (int) $sablon->sube_id) {
            $hatalar['sube_id'][] = 'Vardiya şablonu seçilen şube ile uyumlu değil.';
        }

        if (! $vardiya->sube_id && $sablon->sube_id) {
            $vardiya->sube_id = $sablon->sube_id;
        }

        $vardiya->mola_dakika ??= $sablon->mola_dakika ?? 0;

        if ($vardiya->baslangic_at && $vardiya->bitis_at) {
            return;
        }

        if (! $vardiya->tarih || ! $sablon->baslangic_saati || ! $sablon->bitis_saati) {
            return;
        }

        $tarih = Carbon::parse($vardiya->tarih)->toDateString();
        $baslangic = Carbon::parse($tarih.' '.$sablon->baslangic_saati);
        $bitis = Carbon::parse($tarih.' '.$sablon->bitis_saati);

        if ($bitis->lessThanOrEqualTo($baslangic)) {
            $bitis->addDay();
        }

        $vardiya->baslangic_at ??= $baslangic;
        $vardiya->bitis_at ??= $bitis;
        $vardiya->baslangic_saati ??= $sablon->baslangic_saati;
        $vardiya->bitis_saati ??= $sablon->bitis_saati;
    }

    private function cakisanVardiyaVarMi(PersonelVardiyasi $vardiya, Carbon $baslangic, Carbon $bitis): bool
    {
        return PersonelVardiyasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $vardiya->firma_id)
            ->where('personel_id', $vardiya->personel_id)
            ->where('durum', '!=', 'iptal')
            ->when($vardiya->exists, fn ($query) => $query->whereKeyNot($vardiya->getKey()))
            ->where('baslangic_at', '<', $bitis)
            ->where('bitis_at', '>', $baslangic)
            ->exists();
    }

    private function onayliIzinCakismasiVarMi(PersonelVardiyasi $vardiya, Carbon $baslangic, Carbon $bitis): bool
    {
        return PersonelIzni::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $vardiya->firma_id)
            ->where('personel_id', $vardiya->personel_id)
            ->where(function ($query): void {
                $query->where('durum', 'onaylandi')
                    ->orWhere('onay_durumu', 'onaylandi');
            })
            ->where('baslangic_at', '<', $bitis)
            ->where('bitis_at', '>', $baslangic)
            ->exists();
    }

    private function subeFirmayaAitMi(PersonelVardiyasi $vardiya): bool
    {
        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $vardiya->firma_id)
            ->whereKey($vardiya->sube_id)
            ->exists();
    }
}
