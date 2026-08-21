<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeMarkaUreticiTanimKaynagi\Pages;
use App\Models\Muhasebe\MuhasebeMarkaUretici;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;

class MuhasebeMarkaUreticiTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = MuhasebeMarkaUretici::class;

    protected static ?string $slug = 'tanimlar/marka-ureticileri';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $modelLabel = 'Marka Üretici';

    protected static ?string $pluralModelLabel = 'Marka Üreticileri';

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
        return MuhasebeMarkaUretici::query()
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
            'index' => Pages\ListMuhasebeMarkaUreticileri::route('/'),
            'create' => Pages\CreateMuhasebeMarkaUretici::route('/create'),
            'edit' => Pages\EditMuhasebeMarkaUretici::route('/{record}/edit'),
        ];
    }
}
