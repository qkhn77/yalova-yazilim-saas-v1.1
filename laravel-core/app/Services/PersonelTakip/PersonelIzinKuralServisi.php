<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelIzni;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class PersonelIzinKuralServisi
{
    public function hazirlaVeDogrula(PersonelIzni $izin): void
    {
        if (! $izin->firma_id || ! $izin->personel_id || ! $izin->baslangic_at || ! $izin->bitis_at) {
            return;
        }

        $baslangic = Carbon::parse($izin->baslangic_at);
        $bitis = Carbon::parse($izin->bitis_at);
        $hatalar = [];

        if ($bitis->lessThanOrEqualTo($baslangic)) {
            $hatalar['bitis_at'][] = 'İzin bitişi başlangıçtan sonra olmalıdır.';
        }

        $personelVar = Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $izin->firma_id)
            ->whereKey($izin->personel_id)
            ->exists();

        if (! $personelVar) {
            $hatalar['personel_id'][] = 'Seçilen personel bu firmaya ait değil.';
        }

        if ($this->onayliMi($izin) && $izin->izin_turu !== 'devamsizlik' && $this->aktifVardiyaCakismasiVarMi($izin, $baslangic, $bitis)) {
            $hatalar['baslangic_at'][] = 'Bu personelin seçilen izin aralığında aktif vardiyası var.';
        }

        if ($this->onayliMi($izin) && $this->onayliIzinCakismasiVarMi($izin, $baslangic, $bitis)) {
            $hatalar['baslangic_at'][] = 'Bu personelin seçilen aralıkta başka bir onaylı izin kaydı var.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        $this->varsayilanlariDoldur($izin, $baslangic, $bitis);
    }

    private function varsayilanlariDoldur(PersonelIzni $izin, Carbon $baslangic, Carbon $bitis): void
    {
        $izin->baslangic_tarihi = $baslangic->toDateString();
        $izin->bitis_tarihi = $bitis->toDateString();
        $izin->onay_durumu = $izin->onay_durumu ?: $izin->durum;

        $saat = round($baslangic->diffInMinutes($bitis) / 60, 2);
        if (! $izin->saat_sayisi) {
            $izin->saat_sayisi = $saat;
        }

        if (! $izin->gun_sayisi) {
            $izin->gun_sayisi = max(1, (int) ceil($saat / 24));
        }
    }

    private function aktifVardiyaCakismasiVarMi(PersonelIzni $izin, Carbon $baslangic, Carbon $bitis): bool
    {
        return PersonelVardiyasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $izin->firma_id)
            ->where('personel_id', $izin->personel_id)
            ->where('durum', '!=', 'iptal')
            ->where('baslangic_at', '<', $bitis)
            ->where('bitis_at', '>', $baslangic)
            ->exists();
    }

    private function onayliIzinCakismasiVarMi(PersonelIzni $izin, Carbon $baslangic, Carbon $bitis): bool
    {
        return PersonelIzni::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $izin->firma_id)
            ->where('personel_id', $izin->personel_id)
            ->where(function ($query): void {
                $query->where('durum', 'onaylandi')
                    ->orWhere('onay_durumu', 'onaylandi');
            })
            ->when($izin->exists, fn ($query) => $query->whereKeyNot($izin->getKey()))
            ->where('baslangic_at', '<', $bitis)
            ->where('bitis_at', '>', $baslangic)
            ->exists();
    }

    private function onayliMi(PersonelIzni $izin): bool
    {
        return $izin->durum === 'onaylandi' || $izin->onay_durumu === 'onaylandi';
    }
}
