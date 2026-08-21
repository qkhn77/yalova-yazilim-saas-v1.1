<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi\Pages;
use App\Models\Muhasebe\CariGrubu;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;

class CariGrubuTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    protected static ?string $model = CariGrubu::class;

    protected static ?string $slug = 'tanimlar/cari-gruplari';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'Cari grubu';

    protected static ?string $pluralModelLabel = 'Cari grupları';

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Toggle::make('aktif_mi')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        return parent::form($form);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return CariGrubu::query()
            ->select(static::hizliDuzenlemeModu()
                ? ['id', 'firma_id', 'is_sabit', 'aktif_mi']
                : ['id', 'firma_id', 'is_sabit', 'kod', 'ad', 'aktif_mi'])
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
            'index' => Pages\ListCariGruplari::route('/'),
            'create' => Pages\CreateCariGrubu::route('/create'),
            'edit' => Pages\EditCariGrubu::route('/{record}/edit'),
        ];
    }
}
