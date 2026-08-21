<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\StokBarkodu;
use App\Models\Muhasebe\StokKarti;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StokBarkodServisi
{
    public function barkodBelirle(StokKarti $stok, bool $force = false): string
    {
        $mevcut = trim((string) ($stok->barkod ?? ''));
        if (! $force && $mevcut !== '') {
            return $mevcut;
        }

        return $this->benzersizEan13Uret($stok);
    }

    public function barkodUygula(StokKarti $stok, string $barkod): void
    {
        $barkod = trim($barkod);
        if ($barkod === '') {
            return;
        }

        DB::transaction(function () use ($stok, $barkod): void {
            StokKarti::tenantScopeOlmadan(function () use ($stok, $barkod): void {
                StokKarti::query()
                    ->whereKey($stok->getKey())
                    ->update(['barkod' => $barkod]);
            });

            $stok->forceFill(['barkod' => $barkod]);

            if (! $this->stokBarkodlariTablosuVarMi()) {
                return;
            }

            StokBarkodu::tenantScopeOlmadan(function () use ($stok, $barkod): void {
                StokBarkodu::query()
                    ->where('firma_id', (int) $stok->firma_id)
                    ->where('stok_id', (int) $stok->getKey())
                    ->update(['varsayilan_mi' => false]);

                StokBarkodu::query()->updateOrCreate(
                    [
                        'firma_id' => (int) $stok->firma_id,
                        'stok_id' => (int) $stok->getKey(),
                        'barkod' => $barkod,
                    ],
                    [
                        'varsayilan_mi' => true,
                        'aktif' => true,
                    ]
                );
            });
        });
    }

    public function barkodOlusturVeyaSenkronizeEt(StokKarti $stok, bool $force = false): string
    {
        $barkod = $this->barkodBelirle($stok, $force);
        $this->barkodUygula($stok, $barkod);

        return $barkod;
    }

    private function benzersizEan13Uret(StokKarti $stok): string
    {
        $firmaId = (int) $stok->firma_id;
        $stokId = (int) $stok->getKey();

        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $ilk12 = $this->adayIlk12HaneyiUret($firmaId, $stokId, $attempt);
            $barkod = $ilk12.$this->ean13KontrolHanesi($ilk12);

            if (! $this->barkodKullanimdaMi($barkod, $stok)) {
                return $barkod;
            }
        }

        throw new \RuntimeException('Benzersiz barkod üretilemedi.');
    }

    private function adayIlk12HaneyiUret(int $firmaId, int $stokId, int $attempt): string
    {
        $ham = sprintf('%u', crc32('firma:'.$firmaId.'|stok:'.$stokId.'|deneme:'.$attempt));
        $payload = str_pad(substr($ham, -10), 10, '0', STR_PAD_LEFT);

        return '29'.$payload;
    }

    private function ean13KontrolHanesi(string $ilk12): int
    {
        $toplam = 0;
        for ($i = 0; $i < 12; $i++) {
            $hane = (int) $ilk12[$i];
            $toplam += $i % 2 === 0 ? $hane : ($hane * 3);
        }

        return (10 - ($toplam % 10)) % 10;
    }

    private function barkodKullanimdaMi(string $barkod, StokKarti $stok): bool
    {
        $firmaId = (int) $stok->firma_id;
        $stokId = (int) $stok->getKey();

        $stokKartindaVar = StokKarti::tenantScopeOlmadan(function () use ($firmaId, $stokId, $barkod): bool {
            return StokKarti::query()
                ->where('firma_id', $firmaId)
                ->where('barkod', $barkod)
                ->whereKeyNot($stokId)
                ->exists();
        });

        if ($stokKartindaVar) {
            return true;
        }

        if (! $this->stokBarkodlariTablosuVarMi()) {
            return false;
        }

        return StokBarkodu::tenantScopeOlmadan(function () use ($firmaId, $stokId, $barkod): bool {
            return StokBarkodu::query()
                ->where('firma_id', $firmaId)
                ->where('barkod', $barkod)
                ->where('stok_id', '!=', $stokId)
                ->exists();
        });
    }

    private function stokBarkodlariTablosuVarMi(): bool
    {
        return Schema::hasTable('stok_barkodlari');
    }
}
