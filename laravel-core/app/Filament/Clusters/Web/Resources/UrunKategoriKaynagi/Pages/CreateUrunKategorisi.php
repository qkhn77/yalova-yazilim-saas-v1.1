<?php

namespace App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages\CreateStokKategorisi;
use App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi;

class CreateUrunKategorisi extends CreateStokKategorisi
{
    protected static string $resource = UrunKategoriKaynagi::class;

    protected static ?string $title = 'Ürün kategorisi ekle';
}
