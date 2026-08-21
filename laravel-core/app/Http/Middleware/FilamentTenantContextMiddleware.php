<?php

namespace App\Http\Middleware;

use App\Services\TenantContextService;
use App\Support\UygulamaUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FilamentTenantContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $kullanici = Auth::user();
        if (! $kullanici) {
            return $next($request);
        }

        if ((bool) $request->session()->get('uye_oturumu', false)) {
            return redirect()->to(UygulamaUrl::rota('account.index', [], $request))
                ->withErrors(['panel' => 'Üye hesapları yönetim paneline erişemez.']);
        }

        $superAdminMi = (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
        if ($superAdminMi) {
            return $next($request);
        }

        $tenantContext = app(TenantContextService::class);
        if ($tenantContext->hasAktifFirma()) {
            return $next($request);
        }

        $tenantContext->temizle();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(UygulamaUrl::rota('tenant.login', [], $request))
            ->withErrors(['firma' => 'Panel erişimi için aktif firma seçimi gereklidir.']);
    }
}
