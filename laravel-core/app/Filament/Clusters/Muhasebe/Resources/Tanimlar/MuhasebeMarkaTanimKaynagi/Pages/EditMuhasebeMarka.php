<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi;
use Filament\Resources\Pages\EditRecord;

class EditMuhasebeMarka extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = MuhasebeMarkaTanimKaynagi::class;

    protected static ?string $title = 'Ürün markası düzenle';
}
