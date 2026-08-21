<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\KiraciOzetWidget;
use App\Filament\Widgets\SistemYonetimiOzetWidget;
use App\Models\Firma;
use App\Models\User;
use App\Services\TenantContextService;
use App\Support\SaaSemaYardimcisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class KiraciDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_aktif_firma_varken_admin_dashboard_kiraci_ozetini_gosterir(): void
    {
        $this->saasSemaCacheTemizle();

        $firma = Firma::query()->create([
            'ad' => 'Yalova Bilgisayar',
            'kisa_ad' => 'YB',
            'firma_kodu' => 'YB-'.uniqid(),
            'durum' => Firma::DURUM_AKTIF,
            'onaylandi_mi' => true,
        ]);

        $user = User::factory()->create([
            'super_admin_mi' => true,
        ]);

        $this->actingAs($user);
        session([TenantContextService::SESSION_AKTIF_FIRMA_ID => $firma->id]);

        $widgetlar = app(Dashboard::class)->getWidgets();

        $this->assertSame([KiraciOzetWidget::class], $widgetlar);
        $this->assertTrue(KiraciOzetWidget::canView());
    }

    public function test_aktif_firma_yokken_dashboard_sistem_ozetine_doner(): void
    {
        $this->saasSemaCacheTemizle();

        $user = User::factory()->create([
            'super_admin_mi' => true,
        ]);

        $this->actingAs($user);

        $widgetlar = app(Dashboard::class)->getWidgets();

        $this->assertContains(SistemYonetimiOzetWidget::class, $widgetlar);
    }

    private function saasSemaCacheTemizle(): void
    {
        $yansima = new ReflectionClass(SaaSemaYardimcisi::class);

        foreach (['tabloCache', 'kolonCache', 'kolonListesiCache'] as $ozellikAdi) {
            $ozellik = $yansima->getProperty($ozellikAdi);
            $ozellik->setValue(null, []);
        }
    }
}
