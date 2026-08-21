<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateVergiOrani extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = VergiOraniTanimKaynagi::class;

    protected static ?string $title = 'Vergi oranı ekle';
}
