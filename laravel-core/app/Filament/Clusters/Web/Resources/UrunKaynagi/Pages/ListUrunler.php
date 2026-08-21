<?php

namespace App\Filament\Clusters\Web\Resources\UrunKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages\ListStokKartlari;
use App\Filament\Clusters\Web\Resources\UrunKaynagi;

class ListUrunler extends ListStokKartlari
{
    protected static string $resource = UrunKaynagi::class;
}
