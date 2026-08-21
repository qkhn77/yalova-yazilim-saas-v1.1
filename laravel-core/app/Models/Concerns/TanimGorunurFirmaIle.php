<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tanım listelerinde: seçilen firma + sabit (firma_id null, is_sabit) kayıtlar.
 * Süper yönetici formunda firma seçildiğinde kullanılır (global scope tüm satırları açtığı için).
 *
 * @phpstan-require-extends Model
 */
trait TanimGorunurFirmaIle
{
    /**
     * @param  Builder<static>  $sorgu
     */
    public function scopeGorunurFirmaIle(Builder $sorgu, int $firmaId): Builder
    {
        $tablo = $sorgu->getModel()->getTable();

        return $sorgu->where(function (Builder $q) use ($tablo, $firmaId): void {
            $q->where($tablo.'.firma_id', $firmaId)
                ->orWhere(function (Builder $q2) use ($tablo): void {
                    $q2->whereNull($tablo.'.firma_id')
                        ->where($tablo.'.is_sabit', true);
                });
        });
    }
}
