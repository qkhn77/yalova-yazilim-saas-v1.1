<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class PersonelGirisCikisKuralServisi
{
    public function hazirlaVeDogrula(PersonelGirisCikisi $kayit): void
    {
        if (! $kayit->firma_id || ! $kayit->personel_id) {
            return;
        }

        $hatalar = [];
        $personel = $this->personel($kayit);

        if (! $personel) {
            $hatalar['personel_id'][] = 'Seçilen personel bu firmaya ait değil.';
        }

        $vardiya = $this->vardiya($kayit);
        if ($kayit->vardiya_id && ! $vardiya) {
            $hatalar['vardiya_id'][] = 'Seçilen vardiya bu firmaya ait değil.';
        }

        if ($personel && $vardiya && (int) $vardiya->personel_id !== (int) $personel->id) {
            $hatalar['vardiya_id'][] = 'Seçilen vardiya bu personele ait değil.';
        }

        if ($personel && $kayit->sube_id && $personel->sube_id && (int) $kayit->sube_id !== (int) $personel->sube_id) {
            $hatalar['sube_id'][] = 'Giriş-çıkış şubesi personelin şubesiyle uyumlu değil.';
        }

        if ($kayit->sube_id && ! $this->subeFirmayaAitMi($kayit)) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        $giris = $kayit->giris_at ? Carbon::parse($kayit->giris_at) : null;
        $cikis = $kayit->cikis_at ? Carbon::parse($kayit->cikis_at) : null;

        if ($giris && $cikis && $cikis->lessThanOrEqualTo($giris)) {
            $hatalar['cikis_at'][] = 'Çıkış zamanı giriş zamanından sonra olmalıdır.';
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }

        $this->varsayilanlariDoldur($kayit, $personel, $vardiya, $giris);
        $this->dakikalariHesapla($kayit, $vardiya, $giris, $cikis);
    }

    private function varsayilanlariDoldur(
        PersonelGirisCikisi $kayit,
        ?Personel $personel,
        ?PersonelVardiyasi $vardiya,
        ?Carbon $giris
    ): void {
        if (! $kayit->sube_id) {
            $kayit->sube_id = $vardiya?->sube_id ?: $personel?->sube_id;
        }

        if (! $kayit->tarih) {
            $kayit->tarih = $giris?->toDateString() ?: $vardiya?->tarih?->toDateString();
        }
    }

    private function dakikalariHesapla(
        PersonelGirisCikisi $kayit,
        ?PersonelVardiyasi $vardiya,
        ?Carbon $giris,
        ?Carbon $cikis
    ): void {
        if (! $vardiya) {
            return;
        }

        $vardiyaBaslangic = Carbon::parse($vardiya->baslangic_at);
        $vardiyaBitis = Carbon::parse($vardiya->bitis_at);

        if ($giris) {
            $kayit->gec_kalma_dakika = max(0, $vardiyaBaslangic->diffInMinutes($giris, false));
        }

        if ($cikis) {
            $kayit->erken_cikis_dakika = max(0, $cikis->diffInMinutes($vardiyaBitis, false));
            $kayit->fazla_mesai_dakika = max(0, $vardiyaBitis->diffInMinutes($cikis, false));
        }

        $kayit->eksik_calisma_dakika = (int) $kayit->gec_kalma_dakika + (int) $kayit->erken_cikis_dakika;
    }

    private function personel(PersonelGirisCikisi $kayit): ?Personel
    {
        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kayit->firma_id)
            ->whereKey($kayit->personel_id)
            ->first();
    }

    private function vardiya(PersonelGirisCikisi $kayit): ?PersonelVardiyasi
    {
        if (! $kayit->vardiya_id) {
            return null;
        }

        return PersonelVardiyasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kayit->firma_id)
            ->whereKey($kayit->vardiya_id)
            ->first();
    }

    private function subeFirmayaAitMi(PersonelGirisCikisi $kayit): bool
    {
        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $kayit->firma_id)
            ->whereKey($kayit->sube_id)
            ->exists();
    }
}
