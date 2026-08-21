<?php

namespace App\Services\PersonelTakip;

use App\Models\FirmaKullanici;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelGorevi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class PersonelKayitKuralServisi
{
    public function dogrula(Personel $personel): void
    {
        if (! $personel->firma_id) {
            return;
        }

        $personel->personel_no = is_string($personel->personel_no) ? trim($personel->personel_no) : $personel->personel_no;
        if (blank($personel->personel_no)) {
            $personel->personel_no = $this->siradakiPersonelNo((int) $personel->firma_id);
        }

        $hatalar = [];

        $sube = $this->sube($personel->firma_id, $personel->sube_id);
        if ($personel->sube_id && ! $sube) {
            $hatalar['sube_id'][] = 'Seçilen şube bu firmaya ait değil.';
        }

        $departman = $this->departman($personel->firma_id, $personel->departman_id);
        if ($personel->departman_id && ! $departman) {
            $hatalar['departman_id'][] = 'Seçilen departman bu firmaya ait değil.';
        }

        $gorev = $this->gorev($personel->firma_id, $personel->gorev_id);
        if ($personel->gorev_id && ! $gorev) {
            $hatalar['gorev_id'][] = 'Seçilen görev bu firmaya ait değil.';
        }

        if ($personel->kullanici_id && ! $this->kullaniciFirmayaAitMi((int) $personel->firma_id, (int) $personel->kullanici_id)) {
            $hatalar['kullanici_id'][] = 'Seçilen kullanıcı bu firmaya ait değil.';
        }

        if ($sube && $departman && $departman->sube_id && (int) $departman->sube_id !== (int) $sube->id) {
            $hatalar['departman_id'][] = 'Seçilen departman personelin şubesiyle uyumlu değil.';
        }

        if ($departman && $gorev && $gorev->departman_id && (int) $gorev->departman_id !== (int) $departman->id) {
            $hatalar['gorev_id'][] = 'Seçilen görev personelin departmanıyla uyumlu değil.';
        }

        if ($personel->ise_giris_tarihi && $personel->isten_cikis_tarihi
            && $personel->isten_cikis_tarihi->lt($personel->ise_giris_tarihi)) {
            $hatalar['isten_cikis_tarihi'][] = 'İşten çıkış tarihi işe giriş tarihinden önce olamaz.';
        }

        if ($this->personelNoKullanimdaMi($personel)) {
            $hatalar['personel_no'][] = 'Bu personel numarası aynı firmadaki başka bir personelde kullanılıyor.';
        }

        $personel->pin_kodu = is_string($personel->pin_kodu) ? trim($personel->pin_kodu) : $personel->pin_kodu;
        $pinZorunlu = (bool) (app(PersonelAyarlariServisi::class)->genel((int) $personel->firma_id)['pin_zorunlu'] ?? false);
        $mevcutPinVarMi = filled($personel->pin_kodu_hash) || filled($personel->getOriginal('pin_kodu_hash')) || filled($personel->getOriginal('pin_kodu'));
        if ($pinZorunlu && blank($personel->pin_kodu) && ! $mevcutPinVarMi) {
            $hatalar['pin_kodu'][] = 'Firma ayarlarına göre personel PIN kodu zorunludur.';
        }

        if ($personel->pin_kodu !== null && $personel->pin_kodu !== '') {
            if (! preg_match('/^[0-9]{4,12}$/', (string) $personel->pin_kodu)) {
                $hatalar['pin_kodu'][] = 'PIN kodu 4-12 haneli sayısal bir değer olmalıdır.';
            }

            if ($this->pinKullanimdaMi($personel, (string) $personel->pin_kodu)) {
                $hatalar['pin_kodu'][] = 'Bu PIN kodu aynı firmadaki başka bir personelde kullanılıyor.';
            }

            if (! isset($hatalar['pin_kodu'])) {
                $personel->pin_kodu_hash = Hash::make((string) $personel->pin_kodu);
                $personel->pin_kodu = null;
            }
        }

        if ($hatalar !== []) {
            throw ValidationException::withMessages($hatalar);
        }
    }

    private function pinKullanimdaMi(Personel $personel, string $pin): bool
    {
        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->withTrashed()
            ->where('firma_id', $personel->firma_id)
            ->when($personel->exists, fn ($query) => $query->whereKeyNot($personel->getKey()))
            ->where(function ($query): void {
                $query->whereNotNull('pin_kodu_hash')
                    ->orWhereNotNull('pin_kodu');
            })
            ->get(['id', 'pin_kodu', 'pin_kodu_hash'])
            ->contains(fn (Personel $digerPersonel): bool => $this->pinEslesiyor($digerPersonel, $pin));
    }

    private function pinEslesiyor(Personel $personel, string $pin): bool
    {
        if (filled($personel->pin_kodu_hash) && Hash::check($pin, (string) $personel->pin_kodu_hash)) {
            return true;
        }

        return filled($personel->pin_kodu) && hash_equals((string) $personel->pin_kodu, $pin);
    }

    private function sube(int $firmaId, mixed $subeId): ?Sube
    {
        if (! $subeId) {
            return null;
        }

        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($subeId)
            ->first();
    }

    private function departman(int $firmaId, mixed $departmanId): ?PersonelDepartmani
    {
        if (! $departmanId) {
            return null;
        }

        return PersonelDepartmani::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($departmanId)
            ->first();
    }

    private function gorev(int $firmaId, mixed $gorevId): ?PersonelGorevi
    {
        if (! $gorevId) {
            return null;
        }

        return PersonelGorevi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($gorevId)
            ->first();
    }

    private function kullaniciFirmayaAitMi(int $firmaId, int $kullaniciId): bool
    {
        return FirmaKullanici::query()
            ->where('firma_id', $firmaId)
            ->where('kullanici_id', $kullaniciId)
            ->where('durum', 'aktif')
            ->exists();
    }

    private function personelNoKullanimdaMi(Personel $personel): bool
    {
        if (blank($personel->personel_no)) {
            return false;
        }

        return Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->withTrashed()
            ->where('firma_id', $personel->firma_id)
            ->where('personel_no', $personel->personel_no)
            ->when($personel->exists, fn ($query) => $query->whereKeyNot($personel->getKey()))
            ->exists();
    }

    private function siradakiPersonelNo(int $firmaId): string
    {
        $sonId = (int) Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->withTrashed()
            ->where('firma_id', $firmaId)
            ->max('id');

        $sira = max(1, $sonId + 1);

        do {
            $personelNo = 'P-'.str_pad((string) $sira, 6, '0', STR_PAD_LEFT);
            $sira++;
        } while (Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->withTrashed()
            ->where('firma_id', $firmaId)
            ->where('personel_no', $personelNo)
            ->exists());

        return $personelNo;
    }
}
