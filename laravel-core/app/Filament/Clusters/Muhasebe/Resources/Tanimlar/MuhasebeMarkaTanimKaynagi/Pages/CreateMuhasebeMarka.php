<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateMuhasebeMarka extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = MuhasebeMarkaTanimKaynagi::class;

    protected static ?string $title = 'Ürün markası ekle';
}
