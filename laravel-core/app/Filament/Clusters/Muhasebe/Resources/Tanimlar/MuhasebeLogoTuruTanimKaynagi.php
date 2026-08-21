<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeLogoTuruTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeLogoTuru;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;

class MuhasebeLogoTuruTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeLogoTuru::class;

    protected static ?string $slug = 'tanimlar/logo-turleri';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $modelLabel = 'Logo türü';

    protected static ?string $pluralModelLabel = 'Logo türleri';

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
        return MuhasebeLogoTuru::query()
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
            'index' => Pages\ListMuhasebeLogoTurleri::route('/'),
            'create' => Pages\CreateMuhasebeLogoTuru::route('/create'),
            'edit' => Pages\EditMuhasebeLogoTuru::route('/{record}/edit'),
        ];
    }
}
