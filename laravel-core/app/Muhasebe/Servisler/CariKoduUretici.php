<?php

namespace App\Muhasebe\Servisler;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CariKoduUretici
{
    public function sonraki(int $firmaId): string
    {
        if ($firmaId < 1) {
            throw new InvalidArgumentException('Cari kodu için geçerli bir firma gerekir.');
        }

        return DB::transaction(function () use ($firmaId): string {
            $firmaVar = DB::table('firmalar')
                ->where('id', $firmaId)
                ->lockForUpdate()
                ->exists();

            if (! $firmaVar) {
                throw new RuntimeException('Cari kodu için firma bulunamadı.');
            }

            $sayac = DB::table('cari_kod_sayaclari')
                ->where('firma_id', $firmaId)
                ->lockForUpdate()
                ->first();

            if (! $sayac) {
                $sonNumara = $this->mevcutEnBuyukNumara($firmaId);
                try {
                    DB::table('cari_kod_sayaclari')->insert([
                        'firma_id' => $firmaId,
                        'son_numara' => $sonNumara,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException) {
                    $sayac = DB::table('cari_kod_sayaclari')
                        ->where('firma_id', $firmaId)
                        ->lockForUpdate()
                        ->first();
                    $sonNumara = (int) ($sayac?->son_numara ?? $sonNumara);
                }
            } else {
                $sonNumara = (int) $sayac->son_numara;
            }

            $sonNumara++;
            if ($sonNumara > 2147483647) {
                throw new RuntimeException('Cari kodu sıra numarası sınırına ulaştı.');
            }

            DB::table('cari_kod_sayaclari')
                ->where('firma_id', $firmaId)
                ->update([
                    'son_numara' => $sonNumara,
                    'updated_at' => now(),
                ]);

            return 'CR-'.$sonNumara;
        });
    }

    private function mevcutEnBuyukNumara(int $firmaId): int
    {
        $enBuyuk = 999;

        DB::table('cariler')
            ->where('firma_id', $firmaId)
            ->where('kod', 'like', 'CR-%')
            ->pluck('kod')
            ->each(function (mixed $kod) use (&$enBuyuk): void {
                if (preg_match('/^CR-(\d+)$/', (string) $kod, $eslesme) === 1) {
                    $enBuyuk = max($enBuyuk, (int) $eslesme[1]);
                }
            });

        return $enBuyuk;
    }
}
