<?php

namespace App\Http\Middleware;

use App\Providers\Filament\AdminPanelProvider;
use App\Support\UygulamaUrl;
use Filament\Http\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Filament panelinde yerleşik login yok; kimlik doğrulanmamış istekler yönlendirilir.
 *
 * Çift giriş:
 * - Firma kullanıcıları: `/giris` (tenant.login). Panel sonrası TenantContextMiddleware aktif firma ister.
 * - Süper admin: `/yonetici-giris` (yonetici.login); giriş formunda firma girişine link vardır.
 * Misafir `/admin` isteği önce buraya düşer; Laravel AuthenticationException ile `url.intended` saklanır.
 */
class FilamentAuthenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if ($request instanceof Request) {
            $panelPath = trim(AdminPanelProvider::adminPath(), '/');
            $requestPath = trim($request->path(), '/');

            if ($requestPath === $panelPath || str_starts_with($requestPath, $panelPath.'/')) {
                return UygulamaUrl::rota('yonetici.login', [], $request);
            }
        }

        return UygulamaUrl::rota('tenant.login', [], $request);
    }
}

