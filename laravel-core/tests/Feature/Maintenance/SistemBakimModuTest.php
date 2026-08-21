<?php

namespace Tests\Feature\Maintenance;

use App\Filament\Pages\SistemBakimModuSayfasi;
use App\Models\Setting;
use App\Models\User;
use App\Http\Middleware\SistemBakimModuMiddleware;
use App\Services\SistemBakimModuServisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SistemBakimModuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_bakim_modu_firma_girisini_503_ile_kapatir(): void
    {
        app(SistemBakimModuServisi::class)->kaydet(true, 'Kısa bir bakım çalışması yapıyoruz.');

        $response = app(SistemBakimModuMiddleware::class)->handle(
            Request::create('/giris', 'GET'),
            fn ($request) => response('next')
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('Kısa bir bakım çalışması yapıyoruz.', $response->getContent());
    }

    public function test_bakim_modunda_yonetici_giris_ekrani_acik_kalir(): void
    {
        app(SistemBakimModuServisi::class)->kaydet(true, 'Bakım var.');

        $request = Request::create('/yonetici-giris', 'GET');
        $route = new Route(['GET'], '/yonetici-giris', fn () => response('route'));
        $route->name('yonetici.login');
        $request->setRouteResolver(fn () => $route);

        $response = app(SistemBakimModuMiddleware::class)->handle(
            $request,
            fn ($request) => response('next')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('next', $response->getContent());
    }

    public function test_bakim_modu_yonetici_istisnasini_korur(): void
    {
        $yonetici = User::factory()->create(['super_admin_mi' => true]);
        app(SistemBakimModuServisi::class)->kaydet(true, 'Bakım var.');

        $this->assertTrue($yonetici->super_admin_mi);
    }

    public function test_bakim_sayfasi_sadece_sistem_yoneticisine_aciktir(): void
    {
        $firmaKullanicisi = User::factory()->create(['super_admin_mi' => false]);
        $yonetici = User::factory()->create(['super_admin_mi' => true]);

        $this->actingAs($firmaKullanicisi);
        $this->assertFalse(SistemBakimModuSayfasi::canAccess());

        $this->actingAs($yonetici);
        $this->assertTrue(SistemBakimModuSayfasi::canAccess());
    }
}
