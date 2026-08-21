<?php

namespace App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages\ListStokKategorileri;
use App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi;

class ListUrunKategorileri extends ListStokKategorileri
{
    protected static string $resource = UrunKategoriKaynagi::class;

    protected static ?string $title = 'Ürün kategorileri';
}
