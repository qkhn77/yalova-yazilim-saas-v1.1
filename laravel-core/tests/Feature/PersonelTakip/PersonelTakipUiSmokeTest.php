<?php

namespace Tests\Feature\PersonelTakip;

use App\Filament\Clusters\PersonelTakip\Pages\PersonelAyarlariSayfasi;
use App\Filament\Clusters\PersonelTakip\Pages\PersonelPinTerminalSayfasi;
use App\Filament\Clusters\PersonelTakip\Pages\PersonelRaporlariSayfasi;
use App\Filament\Clusters\PersonelTakip\Pages\PersonelTakipOzetSayfasi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelAvansKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelDepartmanKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelGirisCikisKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelGorevKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelIzinKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaSablonuKaynagi;
use App\Filament\Clusters\PersonelTakip\Resources\SubeKaynagi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonelTakipUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_personel_takip_filament_url_yapisi_sabit_kalir(): void
    {
        $beklenenler = [
            [PersonelTakipOzetSayfasi::getUrl(), '/admin/personel-takip/ozet'],
            [PersonelKaynagi::getUrl(), '/admin/personel-takip/personeller'],
            [SubeKaynagi::getUrl(), '/admin/personel-takip/tanimlar/subeler'],
            [PersonelDepartmanKaynagi::getUrl(), '/admin/personel-takip/tanimlar/departmanlar'],
            [PersonelGorevKaynagi::getUrl(), '/admin/personel-takip/tanimlar/gorevler'],
            [PersonelVardiyaSablonuKaynagi::getUrl(), '/admin/personel-takip/tanimlar/vardiya-sablonlari'],
            [PersonelVardiyaKaynagi::getUrl(), '/admin/personel-takip/vardiyalar'],
            [PersonelGirisCikisKaynagi::getUrl(), '/admin/personel-takip/giris-cikis'],
            [PersonelPinTerminalSayfasi::getUrl(), '/admin/personel-takip/terminal/pin-giris-cikis'],
            [PersonelIzinKaynagi::getUrl(), '/admin/personel-takip/izinler'],
            [PersonelAvansKaynagi::getUrl(), '/admin/personel-takip/avanslar'],
            [PersonelMaasDonemiKaynagi::getUrl(), '/admin/personel-takip/maas-donemleri'],
            [PersonelRaporlariSayfasi::getUrl(), '/admin/personel-takip/raporlar/personel-ozeti'],
            [PersonelAyarlariSayfasi::getUrl(), '/admin/personel-takip/ayarlar'],
        ];

        foreach ($beklenenler as [$url, $path]) {
            $this->assertStringEndsWith($path, parse_url($url, PHP_URL_PATH) ?: $url);
        }
    }
}
