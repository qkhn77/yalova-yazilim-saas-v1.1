<?php

namespace App\Database\Scopes;

use App\Support\KullaniciTablosuYardimcisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * users.deleted_at kolonu yokken whereNull eklemez; kolon migration ile gelince Laravel soft delete ile uyumlu kalır.
 */
class KullaniciSoftDeletingScope extends SoftDeletingScope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! KullaniciTablosuYardimcisi::usersDeletedAtKolonuVarMi()) {
            return;
        }

        parent::apply($builder, $model);
    }
}
