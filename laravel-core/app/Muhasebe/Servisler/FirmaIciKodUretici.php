<?php

namespace App\Muhasebe\Servisler;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Üç finans hesabı türü için mevcut tablo/şema üzerinde firma içi kod üretir.
 */
class FirmaIciKodUretici
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function sonraki(int $firmaId, string $modelClass, string $onEk): string
    {
        if ($firmaId < 1 || ! is_a($modelClass, Model::class, true) || $onEk === '') {
            throw new InvalidArgumentException('Kod üretimi için geçerli firma, model ve ön ek gerekir.');
        }

        return DB::transaction(function () use ($firmaId, $modelClass, $onEk): string {
            if (! DB::table('firmalar')->where('id', $firmaId)->lockForUpdate()->exists()) {
                throw new RuntimeException('Kod üretimi için firma bulunamadı.');
            }

            $sira = 999;
            $modelClass::query()
                ->where('firma_id', $firmaId)
                ->where('kod', 'like', $onEk.'-%')
                ->pluck('kod')
                ->each(function (mixed $kod) use (&$sira, $onEk): void {
                    if (preg_match('/^'.preg_quote($onEk, '/').'-(\d+)$/', (string) $kod, $eslesme) === 1) {
                        $sira = max($sira, (int) $eslesme[1]);
                    }
                });

            do {
                $kod = $onEk.'-'.(++$sira);
            } while ($modelClass::query()->where('firma_id', $firmaId)->where('kod', $kod)->exists());

            return $kod;
        });
    }
}
