<?php

namespace App\Filament\Clusters\ETicaret;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

abstract class ETicaretTaslakSayfa extends Page
{
    /** @var array<string, bool> */
    private static array $erisilebilirlikCache = [];

    protected static ?string $cluster = ETicaretCluster::class;

    protected static string $view = 'filament.clusters.e-ticaret.pages.taslak';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $gerekenYetkiKodu = 'e_ticaret.goruntule';

    public static function canAccess(): bool
    {
        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheKey = static::class.'|'.((int) $kullanici->id).'|'.((int) ($firmaId ?? 0)).'|'.((string) static::$gerekenYetkiKodu);

        if (array_key_exists($cacheKey, self::$erisilebilirlikCache)) {
            return self::$erisilebilirlikCache[$cacheKey];
        }

        return self::$erisilebilirlikCache[$cacheKey] = app(SidebarService::class)->menuGorunurMu(
            $kullanici,
            $firmaId,
            'e_ticaret',
            static::$gerekenYetkiKodu
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'aciklama' => static::taslakAciklamasi(),
        ];
    }

    protected static function taslakAciklamasi(): string
    {
        return __('filament.ecommerce.draft.note');
    }
}
