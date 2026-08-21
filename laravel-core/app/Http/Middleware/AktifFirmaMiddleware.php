<?php

namespace App\Http\Middleware;

use App\Models\FirmaKullanici;
use App\Services\TenantContextService;
use App\Support\UygulamaUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AktifFirmaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantContext = app(TenantContextService::class);

        if (! Auth::check() || ! $tenantContext->hasAktifFirma()) {
            $tenantContext->temizle();
            return $this->girisYonlendir($request);
        }

        $firma = $tenantContext->aktifFirma();
        if (! $firma || $firma->durum !== 'aktif') {
            $tenantContext->temizle();
            return $this->girisYonlendir($request, 'Firma durumu uygun değil. Lütfen tekrar giriş yapın.');
        }

        $kullanici = Auth::user();
        $firmaKullanici = FirmaKullanici::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firma->id)
            ->where('kullanici_id', $kullanici->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $firmaKullanici || $firmaKullanici->durum !== 'aktif') {
            $tenantContext->temizle();
            return $this->girisYonlendir($request, 'Kullanıcı-firma bağlantınız aktif değil.');
        }

        if (isset($firmaKullanici->onay_durumu)
            && $firmaKullanici->onay_durumu !== null
            && (string) $firmaKullanici->onay_durumu !== 'aktif') {
            $tenantContext->temizle();
            return $this->girisYonlendir($request, 'Hesabınız henüz onaylanmamış.');
        }

        if ($tenantContext->aktifKullaniciFirmaId() !== (int) $firmaKullanici->id) {
            $tenantContext->firmaAyarla($firma, $firmaKullanici->rol_id ? (int) $firmaKullanici->rol_id : null, (int) $firmaKullanici->id);
        }

        return $next($request);
    }

    protected function girisYonlendir(Request $request, string $mesaj = 'Devam etmek için firma bazlı giriş yapmalısınız.'): Response
    {
        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(UygulamaUrl::rota('tenant.login', [], $request))->withErrors(['firma' => $mesaj]);
    }
}


