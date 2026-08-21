<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;

class MuhasebeMarkaTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeMarka::class;

    protected static ?string $slug = 'tanimlar/markalar';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $modelLabel = 'Ürün Markası';

    protected static ?string $pluralModelLabel = 'Ürün Markaları';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMuhasebeMarkalari::route('/'),
            'create' => Pages\CreateMuhasebeMarka::route('/create'),
            'edit' => Pages\EditMuhasebeMarka::route('/{record}/edit'),
        ];
    }
}
