<?php

namespace App\Models\Scopes;

use App\Models\Concerns\HasFirmaTenantScope;
use App\Models\User;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * firma_id + aktif firma bağlamı ile kiracı sınırı (muhasebe çekirdeği modelleri).
 *
 * Bypass: ilgili modelde {@see HasFirmaTenantScope::tenantScopeOlmadan}.
 * Süper yönetici: tüm firmalar. Konsol: unit test dışında scope uygulanmaz.
 */
final class FirmaIdTenantScope implements Scope
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

        if (app(\App\Services\TenantContextService::class)->sistemFirmaKapsamiAktifMi()) {
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

        $builder->where($model->getTable().'.firma_id', $fid);
    }
}
