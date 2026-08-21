<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TanimFirmaTenantScope;
use App\Models\User;
use App\Muhasebe\Tanimlar\TanimKullanimDenetleyicisi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Muhasebe tanım kayıtları: kiracı + sabit görünürlük, silme kuralları, tanim_firma_kapsami senkronu.
 *
 * @phpstan-require-extends Model
 */
trait HasMuhasebeTanimKaydi
{
    protected static int $muhasebeTanimTenantScopeBypassDerinlik = 0;

    protected static function bootHasMuhasebeTanimKaydi(): void
    {
        static::addGlobalScope(new TanimFirmaTenantScope);

        static::saving(function (Model $model): void {
            if (! $model->getAttribute('is_sabit')) {
                if ($model->getAttribute('firma_id') === null) {
                    throw ValidationException::withMessages([
                        'firma_id' => 'Firma tanımı için firma zorunludur.',
                    ]);
                }
            } else {
                $model->setAttribute('firma_id', null);
            }

            $fid = $model->getAttribute('firma_id');
            $model->setAttribute('tanim_firma_kapsami', $fid === null ? 0 : (int) $fid);
        });

        static::deleting(function (Model $model): void {
            if (app()->runningInConsole() && ! App::runningUnitTests()) {
                return;
            }

            $kullanici = Auth::user();

            if ((bool) $model->getAttribute('is_sabit')) {
                if (! ($kullanici instanceof User && KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici))) {
                    throw ValidationException::withMessages([
                        'delete' => 'Sabit tanımları yalnızca süper yönetici silebilir.',
                    ]);
                }
            } else {
                if ($kullanici instanceof User && KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici)) {
                    // süper admin her firma tanımını silebilir
                } else {
                    $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                    if ($aktif < 1 || (int) $model->getAttribute('firma_id') !== $aktif) {
                        throw ValidationException::withMessages([
                            'delete' => 'Bu tanımı silme yetkiniz yok.',
                        ]);
                    }
                }
            }

            if (TanimKullanimDenetleyicisi::kullanimdaMi($model)) {
                throw ValidationException::withMessages([
                    'delete' => TanimKullanimDenetleyicisi::KULLANIMDA_MESAJI,
                ]);
            }
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $islem
     * @return T
     */
    public static function tenantScopeOlmadan(Closure $islem): mixed
    {
        static::$muhasebeTanimTenantScopeBypassDerinlik++;

        try {
            return $islem();
        } finally {
            static::$muhasebeTanimTenantScopeBypassDerinlik--;
        }
    }

    public static function tenantScopeBypassAktifMi(): bool
    {
        return static::$muhasebeTanimTenantScopeBypassDerinlik > 0;
    }

    public static function kullaniciSuperAdminMi(User $kullanici): bool
    {
        return KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici);
    }
}
