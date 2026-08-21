<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\OdemeYontemiTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeOdemeYontemi;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;

class OdemeYontemiTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeOdemeYontemi::class;

    protected static ?string $slug = 'tanimlar/odeme-yontemleri';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $modelLabel = 'Ödeme yöntemi';

    protected static ?string $pluralModelLabel = 'Ödeme yöntemleri';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOdemeYontemleri::route('/'),
            'create' => Pages\CreateOdemeYontemi::route('/create'),
            'edit' => Pages\EditOdemeYontemi::route('/{record}/edit'),
        ];
    }
}
