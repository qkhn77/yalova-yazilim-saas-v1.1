<?php

namespace App\Http\Middleware;

use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use App\Services\YetkiService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SaltOkunurMiddleware
{
    public function handle(Request $request, Closure $next, string $modulKodu): Response
    {
        $kullanici = Auth::user();
        if (! $kullanici) {
            abort(401);
        }

        if ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false)) {
            return $next($request);
        }

        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            abort(403, 'Aktif firma bulunamadı.');
        }

        if (app(YetkiService::class)->firmaUstYonetimRoluMuKullaniciIcin($kullanici, (int) $firmaId)) {
            return $next($request);
        }

        $erisimService = app(ModulErisimService::class);
        if ($erisimService->modulSaltOkunurMu($firmaId, $modulKodu)) {
            abort(403, 'Bu modül salt okunur durumdadır.');
        }

        return $next($request);
    }
}
