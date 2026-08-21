<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi\Pages;
use App\Models\Muhasebe\Birim;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Tables;

class BirimTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = Birim::class;

    protected static ?string $slug = 'tanimlar/birimler';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $modelLabel = 'Birim';

    protected static ?string $pluralModelLabel = 'Birimler';

    protected static function tanimFormEkstraKodSonrasi(): array
    {
        return [
            Forms\Components\Toggle::make('varsayilan_mi')
                ->label('Varsayılan birim')
                ->helperText('Bu firma için yeni stok kartlarında otomatik seçilir.')
                ->default(false)
                ->visible(fn (Get $get): bool => ! (bool) ($get('is_sabit') ?? false)),
        ];
    }

    protected static function tanimTabloAdSonrasiSutunlari(): array
    {
        return [
            Tables\Columns\IconColumn::make('varsayilan_mi')
                ->label('Varsayılan')
                ->boolean(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBirimler::route('/'),
            'create' => Pages\CreateBirim::route('/create'),
            'edit' => Pages\EditBirim::route('/{record}/edit'),
        ];
    }
}
