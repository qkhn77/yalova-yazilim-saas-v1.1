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
 * Teknik servis tanım tabloları: aktif firma + global (firma_id NULL) kayıtlar birlikte görünür.
 *
 * Muhasebe {@see FirmaIdTenantScope} değiştirilmez; bu scope yalnızca TS tanım modellerinde kullanılır.
 *
 * Bypass: {@see HasTeknikServisTanimTenantScope::tenantScopeOlmadan}.
 */
final class TeknikServisTanimFirmaScope implements Scope
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
            $builder->whereNull($model->getTable().'.firma_id');

            return;
        }

        $tablo = $model->getTable();
        $builder->where(function (Builder $alt) use ($tablo, $fid): void {
            $alt->where($tablo.'.firma_id', $fid)->orWhereNull($tablo.'.firma_id');
        });
    }
}
