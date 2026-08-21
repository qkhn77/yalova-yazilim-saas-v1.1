<?php

namespace App\Services\PersonelTakip;

use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Scopes\FirmaIdTenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PersonelMaasDonemiOnayServisi
{
    public function onayla(int $firmaId, int $donemId, ?int $onaylayanId = null): PersonelMaasDonemi
    {
        return DB::transaction(function () use ($firmaId, $donemId, $onaylayanId): PersonelMaasDonemi {
            $donem = $this->donemBul($firmaId, $donemId);

            $hareketSayisi = PersonelMaasHareketi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $firmaId)
                ->where('maas_donemi_id', $donem->id)
                ->count();

            if ($hareketSayisi === 0) {
                throw ValidationException::withMessages([
                    'maas_donemi' => 'Maaş dönemi onaylanmadan önce hesaplanmalıdır.',
                ]);
            }

            if (in_array((string) $donem->durum, ['odendi', 'iptal'], true)) {
                throw ValidationException::withMessages([
                    'maas_donemi' => 'Ödenmiş veya iptal edilmiş maaş dönemi tekrar onaylanamaz.',
                ]);
            }

            PersonelMaasHareketi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $firmaId)
                ->where('maas_donemi_id', $donem->id)
                ->whereIn('durum', ['taslak', 'hesaplandi'])
                ->update(['durum' => 'onaylandi']);

            $donem->forceFill([
                'durum' => 'onaylandi',
                'onaylayan_id' => $onaylayanId,
                'onay_at' => now(),
            ])->save();

            return $donem->refresh();
        });
    }

    public function odemeDurumunuGuncelle(int $firmaId, int $donemId): void
    {
        $donem = PersonelMaasDonemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->whereKey($donemId)
            ->first();

        if (! $donem || ! in_array((string) $donem->durum, ['onaylandi', 'odendi'], true)) {
            return;
        }

        $kalan = (float) PersonelMaasHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('maas_donemi_id', $donemId)
            ->sum('kalan_tutar');

        $hareketSayisi = PersonelMaasHareketi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('maas_donemi_id', $donemId)
            ->count();

        if ($hareketSayisi === 0) {
            return;
        }

        $yeniDurum = $kalan <= 0 ? 'odendi' : 'onaylandi';
        if ($donem->durum !== $yeniDurum) {
            $donem->forceFill(['durum' => $yeniDurum])->save();
        }
    }

    private function donemBul(int $firmaId, int $donemId): PersonelMaasDonemi
    {
        $donem = PersonelMaasDonemi::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->lockForUpdate()
            ->where('firma_id', $firmaId)
            ->whereKey($donemId)
            ->first();

        if (! $donem) {
            throw ValidationException::withMessages([
                'maas_donemi' => 'Maaş dönemi bu firmaya ait değil.',
            ]);
        }

        return $donem;
    }
}
