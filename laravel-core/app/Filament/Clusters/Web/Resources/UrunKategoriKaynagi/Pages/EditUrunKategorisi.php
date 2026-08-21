<?php

namespace App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages\EditStokKategorisi;
use App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi;

class EditUrunKategorisi extends EditStokKategorisi
{
    protected static string $resource = UrunKategoriKaynagi::class;

    protected static ?string $title = 'Ürün kategorisi düzenle';
}
