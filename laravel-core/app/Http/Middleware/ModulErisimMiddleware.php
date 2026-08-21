<?php

namespace App\Http\Middleware;

use App\Services\ModulErisimService;
use App\Services\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ModulErisimMiddleware
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

        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        if (! $firmaId) {
            abort(403, 'Aktif firma bulunamadı.');
        }

        $erisimService = app(ModulErisimService::class);
        if (! $erisimService->modulErisilebilirMi($firmaId, $modulKodu)) {
            abort(403, 'Bu modüle erişim yetkiniz bulunmuyor.');
        }

        return $next($request);
    }
}

