<?php

namespace App\Http\Middleware;

use App\Services\SistemBakimModuServisi;
use App\Services\TenantContextService;
use App\Support\UygulamaUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SistemBakimModuMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $bakim = app(SistemBakimModuServisi::class);

        if (! $bakim->aktifMi() || $this->yoneticiGirisIstisnasi($request)) {
            return $next($request);
        }

        $kullanici = Auth::user();
        $yoneticiMi = $kullanici
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));

        if ($yoneticiMi) {
            return $next($request);
        }

        if ($kullanici) {
            app(TenantContextService::class)->temizle();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || $request->isXmlHttpRequest()) {
            return response()->json([
                'message' => $bakim->mesaj(),
                'maintenance' => true,
            ], 503);
        }

        return response()->view('errors.bakim-modu', [
            'mesaj' => $bakim->mesaj(),
        ], 503);
    }

    private function yoneticiGirisIstisnasi(Request $request): bool
    {
        return $request->routeIs('yonetici.login', 'yonetici.login.attempt')
            || $request->is(trim(parse_url(UygulamaUrl::rota('yonetici.login', [], $request), PHP_URL_PATH) ?: '', '/'));
    }
}
