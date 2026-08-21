<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait HasFirma
{
    protected static array $firmaKolonuOnbellek = [];

    protected static function bootHasFirma(): void
    {
        static::creating(function ($model): void {
            if (App::runningInConsole()) {
                return;
            }

            if (static::superAdminMi()) {
                return;
            }

            if (! static::modelFirmaKolonuVar($model->getTable())) {
                return;
            }

            if (! empty($model->firma_id)) {
                return;
            }

            $aktifFirmaId = static::aktifFirmaId();
            if ($aktifFirmaId !== null) {
                $model->firma_id = $aktifFirmaId;
            }
        });

        static::addGlobalScope('firma', function (Builder $builder): void {
            if (App::runningInConsole()) {
                return;
            }

            if (static::superAdminMi()) {
                return;
            }

            $model = $builder->getModel();
            if (! static::modelFirmaKolonuVar($model->getTable())) {
                return;
            }

            $aktifFirmaId = static::aktifFirmaId();
            if ($aktifFirmaId === null) {
                return;
            }

            $builder->where($model->getTable().'.firma_id', $aktifFirmaId);
        });
    }

    public function scopeFirmaFiltreYok(Builder $query): Builder
    {
        return $query->withoutGlobalScope('firma');
    }

    protected static function aktifFirmaId(): ?int
    {
        if (! app()->bound('session')) {
            return null;
        }

        $id = session('aktif_firma_id');
        return $id ? (int) $id : null;
    }

    protected static function superAdminMi(): bool
    {
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        return (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
    }

    protected static function modelFirmaKolonuVar(string $tablo): bool
    {
        if (array_key_exists($tablo, static::$firmaKolonuOnbellek)) {
            return static::$firmaKolonuOnbellek[$tablo];
        }

        try {
            return static::$firmaKolonuOnbellek[$tablo] = Schema::hasColumn($tablo, 'firma_id');
        } catch (\Throwable) {
            return false;
        }
    }
}

