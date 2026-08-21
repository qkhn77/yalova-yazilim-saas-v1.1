<?php

namespace Tests\Unit;

use App\Models\FirmaKullanici;
use App\Services\ModulErisimService;
use App\Services\YetkiService;
use PHPUnit\Framework\TestCase;

class YetkiServiceTest extends TestCase
{
    public function test_firma_ust_yonetim_rolu_join_ozetinden_cozulur(): void
    {
        $servis = new YetkiService(new ModulErisimService());

        $firmaKullanici = new FirmaKullanici(['rol_id' => 10]);
        $firmaKullanici->setAttribute('rol_kod', 'firma_yoneticisi_3');
        $firmaKullanici->setAttribute('rol_sistem_rolu_mu', true);

        $this->assertTrue($servis->firmaUstYonetimRoluMu($firmaKullanici));
    }

    public function test_sistem_rolu_olmayan_rol_ust_yonetim_sayilmaz(): void
    {
        $servis = new YetkiService(new ModulErisimService());

        $firmaKullanici = new FirmaKullanici(['rol_id' => 11]);
        $firmaKullanici->setAttribute('rol_kod', 'firma_yoneticisi');
        $firmaKullanici->setAttribute('rol_sistem_rolu_mu', false);

        $this->assertFalse($servis->firmaUstYonetimRoluMu($firmaKullanici));
    }
}
