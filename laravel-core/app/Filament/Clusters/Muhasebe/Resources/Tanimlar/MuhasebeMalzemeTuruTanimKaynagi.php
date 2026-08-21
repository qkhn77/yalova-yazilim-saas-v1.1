<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMalzemeTuruTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeMalzemeTuru;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;

class MuhasebeMalzemeTuruTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeMalzemeTuru::class;

    protected static ?string $slug = 'tanimlar/malzeme-turleri';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'Malzeme türü';

    protected static ?string $pluralModelLabel = 'Malzeme türleri';

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Checkbox::make('aktif_mi')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        return parent::form($form);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return MuhasebeMalzemeTuru::query()
            ->select(['id', 'firma_id', 'is_sabit', 'kod', 'ad', 'aktif_mi'])
            ->whereKey($key)
            ->first();
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMuhasebeMalzemeTurleri::route('/'),
            'create' => Pages\CreateMuhasebeMalzemeTuru::route('/create'),
            'edit' => Pages\EditMuhasebeMalzemeTuru::route('/{record}/edit'),
        ];
    }
}
