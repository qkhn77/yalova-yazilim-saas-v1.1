<?php

namespace App\Filament\Clusters\Web\Resources\UrunKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages\CreateStokKarti;
use App\Filament\Clusters\Web\Resources\UrunKaynagi;

class CreateUrun extends CreateStokKarti
{
    protected static string $resource = UrunKaynagi::class;
}
