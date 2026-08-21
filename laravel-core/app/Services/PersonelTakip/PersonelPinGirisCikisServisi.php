<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\Personel;
use App\Models\Personel\PersonelGirisCikisi;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class PersonelPinGirisCikisServisi
{
    public function pinIleIslemYap(int $firmaId, string $pin, ?int $subeId = null, ?CarbonInterface $zaman = null): PersonelGirisCikisi
    {
        $zaman = $zaman ? Carbon::parse($zaman) : now();
        $pin = trim($pin);

        if ($pin === '') {
            throw ValidationException::withMessages(['pin_kodu' => 'PIN kodu girilmelidir.']);
        }

        if ($subeId && ! $this->subeFirmayaAitMi($firmaId, $subeId)) {
            throw ValidationException::withMessages(['sube_id' => 'Seçilen şube bu firmaya ait değil.']);
        }

        return DB::transaction(function () use ($firmaId, $pin, $subeId, $zaman): PersonelGirisCikisi {
            $personel = $this->personelBul($firmaId, $pin, $subeId);
            $acikKayit = PersonelGirisCikisi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $firmaId)
                ->where('personel_id', $personel->id)
                ->whereNull('cikis_at')
                ->orderByDesc('giris_at')
                ->lockForUpdate()
                ->first();

            if ($acikKayit) {
                $acikKayit->forceFill([
                    'cikis_at' => $zaman,
                    'cikis_tipi' => 'pin',
                    'kaynak' => 'pin',
                ])->save();

                return $acikKayit->refresh();
            }

            $vardiya = $this->uygunVardiya($personel, $zaman);

            return PersonelGirisCikisi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->create([
                    'firma_id' => $firmaId,
                    'sube_id' => $vardiya?->sube_id ?: $personel->sube_id,
                    'personel_id' => $personel->id,
                    'vardiya_id' => $vardiya?->id,
                    'tarih' => $zaman->toDateString(),
                    'giris_at' => $zaman,
                    'giris_tipi' => 'pin',
                    'kaynak' => 'pin',
                    'onay_durumu' => 'onay_bekliyor',
                ]);
        });
    }

    private function personelBul(int $firmaId, string $pin, ?int $subeId): Personel
    {
        $personeller = Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('durum', Personel::DURUM_AKTIF)
            ->when($subeId, fn ($query) => $query->where('sube_id', $subeId))
            ->where(function ($query): void {
                $query->whereNotNull('pin_kodu_hash')
                    ->orWhereNotNull('pin_kodu');
            })
            ->get(['id', 'firma_id', 'sube_id', 'pin_kodu', 'pin_kodu_hash', 'durum'])
            ->filter(fn (Personel $personel): bool => $this->pinEslesiyor($personel, $pin))
            ->values();

        if ($personeller->count() === 0) {
            throw ValidationException::withMessages(['pin_kodu' => 'Aktif personel bulunamadı.']);
        }

        if ($personeller->count() > 1) {
            throw ValidationException::withMessages(['pin_kodu' => 'PIN kodu birden fazla aktif personelde kullanılıyor.']);
        }

        return $personeller->first();
    }

    private function pinEslesiyor(Personel $personel, string $pin): bool
    {
        if (filled($personel->pin_kodu_hash) && Hash::check($pin, (string) $personel->pin_kodu_hash)) {
            return true;
        }

        return filled($personel->pin_kodu) && hash_equals((string) $personel->pin_kodu, $pin);
    }

    private function uygunVardiya(Personel $personel, CarbonInterface $zaman): ?PersonelVardiyasi
    {
        return PersonelVardiyasi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $personel->firma_id)
            ->where('personel_id', $personel->id)
            ->whereDate('tarih', $zaman->toDateString())
            ->where('durum', '!=', 'iptal')
            ->get()
            ->filter(fn (PersonelVardiyasi $vardiya): bool => (bool) ($vardiya->baslangic_at && $vardiya->bitis_at))
            ->sortBy(fn (PersonelVardiyasi $vardiya): int => abs($zaman->diffInMinutes($vardiya->baslangic_at, false)))
            ->first();
    }

    private function subeFirmayaAitMi(int $firmaId, int $subeId): bool
    {
        return Sube::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($subeId)
            ->exists();
    }
}
