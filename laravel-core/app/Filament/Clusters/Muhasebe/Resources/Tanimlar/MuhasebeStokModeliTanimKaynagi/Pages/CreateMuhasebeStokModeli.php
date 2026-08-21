<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimOlustur;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeStokModeliTanimKaynagi;
use Filament\Resources\Pages\CreateRecord;

class CreateMuhasebeStokModeli extends CreateRecord
{
    use MutatesStandartMuhasebeTanimOlustur;

    protected static string $resource = MuhasebeStokModeliTanimKaynagi::class;

    protected static ?string $title = 'Ürün modeli ekle';
}
