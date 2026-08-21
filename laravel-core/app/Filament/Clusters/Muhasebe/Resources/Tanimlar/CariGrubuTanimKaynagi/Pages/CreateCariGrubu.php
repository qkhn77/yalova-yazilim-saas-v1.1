<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use Filament\Resources\Pages\CreateRecord;

class CreateCariGrubu extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = CariGrubuTanimKaynagi::class;

    protected static ?string $title = 'Cari grubu ekle';
}
