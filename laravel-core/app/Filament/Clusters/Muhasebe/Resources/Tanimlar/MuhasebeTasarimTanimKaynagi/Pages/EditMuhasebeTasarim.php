<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi;
use Filament\Resources\Pages\EditRecord;

class EditMuhasebeTasarim extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = MuhasebeTasarimTanimKaynagi::class;

    protected static ?string $title = 'Tasarım düzenle';
}
