<?php

namespace App\TeklifYonetimi\Servisler;

use App\Models\Muhasebe\Teklif;
use App\Models\TeklifYonetimi\TeklifNumaraSayaci;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TeklifNumaraServisi
{
    private const PREFIX = 'TKL';

    public function sonrakiAday(int $firmaId, mixed $tarih = null): string
    {
        $yil = $this->yil($tarih);
        $sonSira = max(
            (int) (TeklifNumaraSayaci::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('yil', $yil)
                ->where('prefix', self::PREFIX)
                ->value('son_sira') ?? 0),
            $this->kayitlardanEnYuksekSira($firmaId, $yil)
        );

        return $this->formatla($yil, $sonSira + 1);
    }

    public function benzersizUret(int $firmaId, mixed $tarih = null): string
    {
        if ($firmaId < 1) {
            throw new RuntimeException('Teklif numarası üretmek için geçerli firma bulunamadı.');
        }

        $yil = $this->yil($tarih);

        return DB::transaction(fn (): string => $this->benzersizUretKilitle($firmaId, $yil), 3);
    }

    private function benzersizUretKilitle(int $firmaId, int $yil): string
    {
        $sayac = $this->sayaciKilitleVeGetir($firmaId, $yil);
        $sonSira = max((int) $sayac->son_sira, $this->kayitlardanEnYuksekSira($firmaId, $yil));

        do {
            $sonSira++;
            $teklifNo = $this->formatla($yil, $sonSira);
        } while ($this->teklifNoKullaniliyorMu($firmaId, $teklifNo));

        $sayac->update(['son_sira' => $sonSira]);

        return $teklifNo;
    }

    private function sayaciKilitleVeGetir(int $firmaId, int $yil): TeklifNumaraSayaci
    {
        $sayac = TeklifNumaraSayaci::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('yil', $yil)
            ->where('prefix', self::PREFIX)
            ->lockForUpdate()
            ->first();

        if ($sayac instanceof TeklifNumaraSayaci) {
            return $sayac;
        }

        try {
            return TeklifNumaraSayaci::query()
                ->withoutGlobalScopes()
                ->create([
                    'firma_id' => $firmaId,
                    'yil' => $yil,
                    'prefix' => self::PREFIX,
                    'son_sira' => $this->kayitlardanEnYuksekSira($firmaId, $yil),
                ]);
        } catch (QueryException) {
            $sayac = TeklifNumaraSayaci::query()
                ->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('yil', $yil)
                ->where('prefix', self::PREFIX)
                ->lockForUpdate()
                ->first();

            if (! $sayac instanceof TeklifNumaraSayaci) {
                throw new RuntimeException('Teklif numara sayacı oluşturulamadı.');
            }

            return $sayac;
        }
    }

    private function kayitlardanEnYuksekSira(int $firmaId, int $yil): int
    {
        $prefix = self::PREFIX.'-'.$yil.'-';
        $sorgu = Teklif::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereYear('tarih', $yil)
            ->where('teklif_no', 'like', $prefix.'%');

        if (DB::connection()->getDriverName() === 'mysql') {
            return (int) $sorgu
                ->selectRaw(
                    'MAX(CASE WHEN teklif_no REGEXP ? THEN CAST(SUBSTRING(teklif_no, ?) AS UNSIGNED) ELSE 0 END) as max_sira',
                    ['^'.preg_quote($prefix, '/').'[0-9]+$', strlen($prefix) + 1]
                )
                ->value('max_sira');
        }

        return (int) $sorgu
            ->pluck('teklif_no')
            ->reduce(function (int $max, mixed $teklifNo) use ($prefix): int {
                if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $teklifNo, $eslesme)) {
                    return max($max, (int) $eslesme[1]);
                }

                return $max;
            }, 0);
    }

    private function teklifNoKullaniliyorMu(int $firmaId, string $teklifNo): bool
    {
        return Teklif::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('teklif_no', $teklifNo)
            ->exists();
    }

    private function yil(mixed $tarih): int
    {
        return Carbon::parse($tarih ?? now())->year;
    }

    private function formatla(int $yil, int $sira): string
    {
        return sprintf('%s-%d-%04d', self::PREFIX, $yil, $sira);
    }
}
