<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Muhasebe tanım tabloları: kiracı kendi kayıtları + sabit (firma_id null, is_sabit) kayıtları görür.
 *
 * @see HasMuhasebeTanimKaydi
 */
final class TanimFirmaTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $class = $model::class;

        if (! is_subclass_of($class, Model::class)
            || ! method_exists($class, 'tenantScopeBypassAktifMi')
            || ! method_exists($class, 'kullaniciSuperAdminMi')) {
            return;
        }

        /** @var class-string<Model> $class */
        if ($class::tenantScopeBypassAktifMi()) {
            return;
        }

        $kullanici = Auth::user();
        if ($kullanici instanceof User && KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
            return;
        }

        if (app()->runningInConsole() && ! App::runningUnitTests()) {
            return;
        }

        $fid = app(TenantContextService::class)->aktifFirmaId();
        if (! $fid) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $tablo = $model->getTable();
        $builder->where(function (Builder $q) use ($tablo, $fid): void {
            $q->where($tablo.'.firma_id', $fid)
                ->orWhere(function (Builder $q2) use ($tablo): void {
                    $q2->whereNull($tablo.'.firma_id')
                        ->where($tablo.'.is_sabit', true);
                });
        });
    }
}
