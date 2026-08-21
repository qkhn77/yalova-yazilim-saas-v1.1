<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeTasarimTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeTasarim;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;

class MuhasebeTasarimTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeTasarim::class;

    protected static ?string $slug = 'tanimlar/tasarimlar';

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $modelLabel = 'Tasarım';

    protected static ?string $pluralModelLabel = 'Tasarımlar';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMuhasebeTasarimlari::route('/'),
            'create' => Pages\CreateMuhasebeTasarim::route('/create'),
            'edit' => Pages\EditMuhasebeTasarim::route('/{record}/edit'),
        ];
    }
}
