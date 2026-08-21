<?php

use App\Http\Middleware\AktifFirmaMiddleware;
use App\Http\Middleware\EcommerceFrontErisimMiddleware;
use App\Http\Middleware\FrontTercihMiddleware;
use App\Http\Middleware\GzipResponseMiddleware;
use App\Http\Middleware\ModulErisimMiddleware;
use App\Http\Middleware\OnePagePublicSiteMiddleware;
use App\Http\Middleware\SaltOkunurMiddleware;
use App\Http\Middleware\SistemBakimModuMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            FrontTercihMiddleware::class,
            GzipResponseMiddleware::class,
            SistemBakimModuMiddleware::class,
            OnePagePublicSiteMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request): string {
            return route('tenant.login');
        });
        $middleware->validateCsrfTokens(except: [
            'api/odeme/*',
            'api/kargo/*',
        ]);
        $middleware->alias([
            'aktif.firma' => AktifFirmaMiddleware::class,
            'ecommerce.front.erisim' => EcommerceFrontErisimMiddleware::class,
            'modul.erisim' => ModulErisimMiddleware::class,
            'salt.okunur' => SaltOkunurMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Oturum yenilendiğinde veya sayfa uzun süre açık kaldığında eski CSRF
        // belirteci ile gönderilen giriş formlarını 419 ekranına düşürme.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() === 419 && $request->isMethod('post') && in_array($request->path(), [
                'giris',
                'uye-giris',
                'yonetici-giris',
                'firma-kodumu-bul',
            ], true)) {
                return redirect()
                    ->to(url()->previous() ?: route('tenant.login'))
                    ->with('status', 'Oturumunuz yenilendi. Güvenliğiniz için formu yeniden göndermeniz gerekir.');
            }
        });
    })
    ->create()
    ->usePublicPath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'public_html');

