<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Support\EcommerceMesajTanimlari;

class MusteriMesajlariSayfasi extends MesajYonetimiBaseSayfasi
{
    protected static ?string $title = null;

    protected static ?string $slug = 'mesaj-yonetimi/musteri-mesajlari';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_mesaj.musteri_goruntule';

    protected static string $konuTipi = EcommerceMesajTanimlari::KONU_TIPI_MUSTERI;

    protected static string $sayfaBaslik = 'filament.ecommerce.messages.customer_title';

    public function getTitle(): string
    {
        return __('filament.ecommerce.messages.customer_title');
    }
}
