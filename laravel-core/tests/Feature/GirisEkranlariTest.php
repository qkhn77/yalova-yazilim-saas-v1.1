<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tam sayfa GET testi auth view’larında Setting vb. DB erişimi gerektirdiği için
 * burada en azından route’ların tanımlı olduğu doğrulanır.
 * Manuel: tarayıcıda /yonetici-giris ve /giris açılışını kontrol edin.
 */
class GirisEkranlariTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::put('settings.all', [
            'site_title' => 'Yalova Kamera',
            'site_logo' => '',
        ], 3600);

        $runtimeSettings = (new ReflectionClass(Setting::class))->getProperty('runtimeSettings');
        $runtimeSettings->setAccessible(true);
        $runtimeSettings->setValue(null, null);

        View::share('errors', new ViewErrorBag);
    }

    public function test_yonetici_giris_route_tanimli(): void
    {
        $this->assertTrue(Route::has('yonetici.login'));
        $this->assertTrue(Route::has('yonetici.login.attempt'));

        $rota = Route::getRoutes()->getByName('yonetici.login');
        $this->assertNotNull($rota);
        $this->assertContains('GET', $rota->methods());
        $this->assertStringContainsString('yonetici-giris', (string) $rota->uri());
    }

    public function test_tenant_giris_route_tanimli(): void
    {
        $this->assertTrue(Route::has('tenant.login'));
        $this->assertTrue(Route::has('tenant.login.attempt'));

        $rota = Route::getRoutes()->getByName('tenant.login');
        $this->assertNotNull($rota);
        $this->assertContains('GET', $rota->methods());
        $this->assertStringContainsString('giris', (string) $rota->uri());
    }

    public function test_tenant_giris_view_hafif_auth_shell_kullanir(): void
    {
        $html = (string) view('auth.tenant-login')->render();

        $this->assertStringContainsString('theme/yalovakamera/css/auth-shell.css', $html);
        $this->assertStringContainsString('theme/yalovakamera/js/auth-shell.js', $html);
        $this->assertStringContainsString('data-auth-lazy-video', $html);
        $this->assertStringContainsString('preload="none"', $html);
        $this->assertStringContainsString('<source data-src=', $html);
        $this->assertStringNotContainsString('preload="auto"', $html);
        $this->assertStringNotContainsString('theme/yalovakamera/js/function.js', $html);
    }

    public function test_yonetici_giris_view_hafif_auth_shell_kullanir(): void
    {
        $html = (string) view('auth.yonetici-giris')->render();

        $this->assertStringContainsString('theme/yalovakamera/css/auth-shell.css', $html);
        $this->assertStringContainsString('theme/yalovakamera/js/auth-shell.js', $html);
        $this->assertStringContainsString('data-auth-lazy-video', $html);
        $this->assertStringContainsString('preload="none"', $html);
        $this->assertStringContainsString('<source data-src=', $html);
        $this->assertStringNotContainsString('preload="auto"', $html);
        $this->assertStringNotContainsString('theme/yalovakamera/js/function.js', $html);
    }
}
