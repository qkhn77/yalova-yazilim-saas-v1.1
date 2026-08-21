<?php

namespace Tests\Feature;

use App\Filament\Clusters\Ayarlar\Pages\MesajMerkeziSayfasi;
use App\Models\Iletisim\KullaniciBildirimi;
use App\Models\Iletisim\KullaniciMesaji;
use App\Models\Iletisim\KullaniciMesajKatilimcisi;
use App\Models\Iletisim\KullaniciMesajKonusu;
use App\Services\MesajMerkeziServisi;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MesajMerkeziSayfasiTest extends TestCase
{
    public function test_mesaj_merkezi_rotasi_tanimli(): void
    {
        $this->assertTrue(Route::has('filament.admin.ayarlar.pages.mesaj-merkezi'));

        $rota = Route::getRoutes()->getByName('filament.admin.ayarlar.pages.mesaj-merkezi');

        $this->assertNotNull($rota);
        $this->assertContains('GET', $rota->methods());
        $this->assertSame('admin/ayarlar/mesaj-merkezi', $rota->uri());
        $this->assertSame(MesajMerkeziSayfasi::class, $rota->getActionName());
    }

    public function test_mesajlasma_model_ve_servisleri_yuklenebilir(): void
    {
        $this->assertSame('kullanici_mesaj_konulari', (new KullaniciMesajKonusu)->getTable());
        $this->assertSame('kullanici_mesajlari', (new KullaniciMesaji)->getTable());
        $this->assertSame('kullanici_mesaj_katilimcilari', (new KullaniciMesajKatilimcisi)->getTable());
        $this->assertSame('kullanici_bildirimleri', (new KullaniciBildirimi)->getTable());
        $this->assertInstanceOf(MesajMerkeziServisi::class, app(MesajMerkeziServisi::class));
    }

    public function test_livewire_update_adresi_alt_dizini_tekrar_etmez(): void
    {
        URL::forceRootUrl('http://localhost/yalova-kamera');

        $rota = Route::getRoutes()->getByName('default.livewire.update');

        $this->assertNotNull($rota);
        $this->assertSame('livewire/update', $rota->uri());
        $this->assertSame('http://localhost/yalova-kamera/livewire/update', route('default.livewire.update'));
        $this->assertStringNotContainsString('/yalova-kamera/yalova-kamera/', route('default.livewire.update'));
    }
}
