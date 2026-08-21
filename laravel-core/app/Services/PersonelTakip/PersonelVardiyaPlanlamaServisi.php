<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelVardiyaSablonu;
use App\Models\Personel\PersonelVardiyasi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Models\Sube;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PersonelVardiyaPlanlamaServisi
{
    /**
     * @param  array<int, int|string>  $personelIds
     * @param  array<int, int|string>  $gunler Iso hafta gunleri: 1=Pazartesi, 7=Pazar
     * @return array{olusan:int, atlanan:int, hatalar:array<int, string>}
     */
    public function sablondanAralikOlustur(
        int $firmaId,
        int $sablonId,
        array $personelIds,
        string $baslangicTarihi,
        string $bitisTarihi,
        array $gunler = [],
        ?int $subeId = null,
    ): array {
        $sablon = PersonelVardiyaSablonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($sablonId)
            ->first();

        if (! $sablon) {
            throw ValidationException::withMessages(['vardiya_sablonu_id' => 'Vardiya şablonu bu firmaya ait değil.']);
        }

        if (! $sablon->aktif_mi) {
            throw ValidationException::withMessages(['vardiya_sablonu_id' => 'Pasif vardiya şablonu kullanılamaz.']);
        }

        if ($subeId && ! $this->subeFirmayaAitMi($firmaId, $subeId)) {
            throw ValidationException::withMessages(['sube_id' => 'Seçilen şube bu firmaya ait değil.']);
        }

        if ($subeId && $sablon->sube_id && (int) $subeId !== (int) $sablon->sube_id) {
            throw ValidationException::withMessages(['sube_id' => 'Seçilen şube vardiya şablonunun şubesiyle uyumlu değil.']);
        }

        $personelIds = collect($personelIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($personelIds === []) {
            throw ValidationException::withMessages(['personel_ids' => 'En az bir personel seçilmelidir.']);
        }

        $baslangic = Carbon::parse($baslangicTarihi)->startOfDay();
        $bitis = Carbon::parse($bitisTarihi)->startOfDay();

        if ($bitis->lessThan($baslangic)) {
            throw ValidationException::withMessages(['bitis_tarihi' => 'Bitiş tarihi başlangıçtan önce olamaz.']);
        }

        $gunler = collect($gunler)
            ->map(fn (int|string $gun): int => (int) $gun)
            ->filter(fn (int $gun): bool => $gun >= 1 && $gun <= 7)
            ->unique()
            ->values()
            ->all();

        return DB::transaction(function () use ($firmaId, $sablon, $personelIds, $baslangic, $bitis, $gunler, $subeId): array {
            $olusan = 0;
            $atlanan = 0;
            $hatalar = [];
            $tarih = $baslangic->copy();

            while ($tarih->lessThanOrEqualTo($bitis)) {
                if ($gunler !== [] && ! in_array($tarih->dayOfWeekIso, $gunler, true)) {
                    $tarih->addDay();

                    continue;
                }

                foreach ($personelIds as $personelId) {
                    try {
                        PersonelVardiyasi::query()
                            ->withoutGlobalScope(FirmaIdTenantScope::class)
                            ->create([
                                'firma_id' => $firmaId,
                                'sube_id' => $subeId ?: $sablon->sube_id,
                                'personel_id' => $personelId,
                                'vardiya_sablonu_id' => $sablon->id,
                                'tarih' => $tarih->toDateString(),
                                'durum' => 'planlandi',
                            ]);
                        $olusan++;
                    } catch (ValidationException $exception) {
                        $atlanan++;
                        $hatalar[] = $tarih->toDateString().' #'.$personelId.': '.$this->ilkHata($exception);
                    }
                }

                $tarih->addDay();
            }

            return [
                'olusan' => $olusan,
                'atlanan' => $atlanan,
                'hatalar' => $hatalar,
            ];
        });
    }

    private function ilkHata(ValidationException $exception): string
    {
        foreach ($exception->errors() as $hatalar) {
            foreach ($hatalar as $hata) {
                return (string) $hata;
            }
        }

        return 'Kayıt oluşturulamadı.';
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
