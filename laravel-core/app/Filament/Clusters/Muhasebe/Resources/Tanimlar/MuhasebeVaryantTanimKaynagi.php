<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeVaryant;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;

class MuhasebeVaryantTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeVaryant::class;

    protected static ?string $slug = 'tanimlar/varyantlar';

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $modelLabel = 'Varyant';

    protected static ?string $pluralModelLabel = 'Varyantlar';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMuhasebeVaryantlari::route('/'),
            'create' => Pages\CreateMuhasebeVaryant::route('/create'),
            'edit' => Pages\EditMuhasebeVaryant::route('/{record}/edit'),
        ];
    }
}
