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
            // Kimlik doğrulama ekranları public tek-sayfa fallback'ine düşmemeli.
            // Aksi hâlde /admin -> /yonetici-giris yönlendirmesi root'a geri döner.
            'giris',
            'yonetici-giris',
            'uye-giris',
            'kayit',
            'uye-kayit',
            'firma-kodumu-bul',
            'restoran/qr-menu',
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
