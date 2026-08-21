<?php

namespace App\Http\Middleware;

use App\Providers\Filament\AdminPanelProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public siteyi geçici olarak tek sayfalı tema ile sınırlar.
 * Yönetim, kimlik doğrulama, entegrasyon ve dosya servisleri kapsam dışıdır.
 */
class OnePagePublicSiteMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Feature testleri gerçek public route davranışını doğrular; tek sayfa
        // tema kısıtı yalnızca gerçek çalışma ortamlarında uygulanmalıdır.
        if (app()->environment('testing')) {
            return $next($request);
        }

        if (! $this->publicPageShouldRedirect($request)) {
            return $next($request);
        }

        return redirect()->route('home');
    }

    private function publicPageShouldRedirect(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        $path = trim($request->path(), '/');

        if ($path === '') {
            return false;
        }

        $allowedPrefixes = [
            'admin',
            trim(AdminPanelProvider::adminPath(), '/'),
            'restoran/qr-menu',
            'urunler',
            'kategori',
            'urun',
            'api',
            'sistem',
            'storage',
            'uploads',
            'livewire',
        ];

        foreach (array_unique($allowedPrefixes) as $allowedPrefix) {
            if ($path === $allowedPrefix || str_starts_with($path, $allowedPrefix.'/')) {
                return false;
            }
        }

        return ! in_array($path, ['sitemap.xml', 'robots.txt', 'up'], true);
    }
}
