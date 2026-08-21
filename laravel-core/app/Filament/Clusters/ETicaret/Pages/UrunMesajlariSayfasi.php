<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Support\EcommerceMesajTanimlari;

class UrunMesajlariSayfasi extends MesajYonetimiBaseSayfasi
{
    protected static ?string $title = null;

    protected static ?string $slug = 'mesaj-yonetimi/urun-mesajlari';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_mesaj.urun_goruntule';

    protected static string $konuTipi = EcommerceMesajTanimlari::KONU_TIPI_URUN;

    protected static string $sayfaBaslik = 'filament.ecommerce.messages.product_title';

    public function getTitle(): string
    {
        return __('filament.ecommerce.messages.product_title');
    }
}
