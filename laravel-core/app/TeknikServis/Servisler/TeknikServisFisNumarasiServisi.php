<?php

namespace App\TeknikServis\Servisler;

use App\Models\TeknikServis\TeknikServisFisNumarasi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Services\TeknikServisGenelAyarServisi;
use Illuminate\Support\Facades\DB;

class TeknikServisFisNumarasiServisi
{
    private const VARSAYILAN_PREFIX = 'YB-SER';

    public function sonrakiAday(int $firmaId, ?string $prefix = null): string
    {
        $prefix = $this->prefix($firmaId, $prefix);
        $yil = (int) now()->format('Y');
        $sayacSonSira = (int) (TeknikServisFisNumarasi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('yil', $yil)
            ->where('prefix', $prefix)
            ->value('son_sira') ?? 0);

        return $prefix.(max(999, $sayacSonSira, $this->kayitlardanEnYuksekSira($prefix)) + 1);
    }

    public function benzersizUret(int $firmaId, ?string $prefix = null): string
    {
        $prefix = $this->prefix($firmaId, $prefix);

        return DB::transaction(function () use ($firmaId, $prefix): string {
            $yil = (int) now()->format('Y');

            $sayac = TeknikServisFisNumarasi::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('yil', $yil)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            $sonSira = max(999, (int) ($sayac?->son_sira ?? 0), $this->kayitlardanEnYuksekSira($prefix));

            do {
                $sonSira++;
                $fisNo = $prefix.$sonSira;
            } while ($this->fisNoKullaniliyorMu($fisNo));

            if ($sayac) {
                $sayac->update(['son_sira' => $sonSira]);
            } else {
                TeknikServisFisNumarasi::query()->withoutGlobalScopes()->create([
                    'firma_id' => $firmaId,
                    'yil' => $yil,
                    'prefix' => $prefix,
                    'son_sira' => $sonSira,
                ]);
            }

            return $fisNo;
        }, 3);
    }

    public function fisNoKullaniliyorMu(string $fisNo): bool
    {
        return TeknikServisKaydi::query()
            ->withoutGlobalScopes()
            ->where('fis_no', $fisNo)
            ->exists();
    }

    private function baslangicSirasi(string $prefix): int
    {
        $yil = (int) now()->format('Y');
        $sayacSonSira = (int) (TeknikServisFisNumarasi::query()
            ->withoutGlobalScopes()
            ->where('yil', $yil)
            ->where('prefix', $prefix)
            ->max('son_sira') ?? 0);

        return max(999, $sayacSonSira, $this->kayitlardanEnYuksekSira($prefix));
    }

    private function kayitlardanEnYuksekSira(string $prefix): int
    {
        $sorgu = TeknikServisKaydi::query()
            ->withoutGlobalScopes()
            ->where('fis_no', 'like', $prefix.'%');

        if (DB::connection()->getDriverName() === 'mysql') {
            return (int) $sorgu
                ->selectRaw(
                    'MAX(CASE WHEN fis_no REGEXP ? THEN CAST(SUBSTRING(fis_no, ?) AS UNSIGNED) ELSE 0 END) as max_sira',
                    ['^'.preg_quote($prefix, '/').'[0-9]+$', strlen($prefix) + 1]
                )
                ->value('max_sira');
        }

        return (int) $sorgu
            ->pluck('fis_no')
            ->reduce(function (int $max, mixed $fisNo) use ($prefix): int {
                if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $fisNo, $eslesme)) {
                    return max($max, (int) $eslesme[1]);
                }

                return $max;
            }, 0);
    }

    private function prefix(int $firmaId, ?string $prefix): string
    {
        $prefix = trim((string) $prefix);

        if ($prefix !== '') {
            return $prefix;
        }

        return app(TeknikServisGenelAyarServisi::class)->fisNoPrefix($firmaId);
    }
}
