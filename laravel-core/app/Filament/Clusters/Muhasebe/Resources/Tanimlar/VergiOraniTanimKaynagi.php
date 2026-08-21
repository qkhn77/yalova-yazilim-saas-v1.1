<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\VergiOraniTanimKaynagi\Pages;
use App\Models\Muhasebe\VergiOrani;
use App\Muhasebe\Filament\AbstractKaynaklar\StandartMuhasebeTanimKaynakResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class VergiOraniTanimKaynagi extends StandartMuhasebeTanimKaynakResource
{
    public static function form(Form $form): Form
    {
        if (static::hizliVergiDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\TextInput::make('oran')
                    ->label('Oran (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(20)
                    ->suffix('%'),
            ]);
        }

        return parent::form($form);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliVergiDuzenlemeModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'firma_id',
                    'is_sabit',
                    'oran',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    private static function hizliVergiDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::detayModu();
    }

    protected static ?string $model = VergiOrani::class;

    protected static ?string $slug = 'tanimlar/vergi-oranlari';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $modelLabel = 'Vergi oranı';

    protected static ?string $pluralModelLabel = 'Vergi oranları';

    /**
     * @return array<Forms\Components\Component>
     */
    protected static function tanimFormEkstraKodSonrasi(): array
    {
        return [
            Forms\Components\TextInput::make('oran')
                ->label('Oran (%)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->maxValue(100)
                ->default(20)
                ->suffix('%'),
        ];
    }

    /**
     * @return array<Tables\Columns\Column>
     */
    protected static function tanimTabloAdSonrasiSutunlari(): array
    {
        return [
            Tables\Columns\TextColumn::make('oran')
                ->label('Oran %')
                ->numeric(decimalPlaces: 4)
                ->sortable(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVergiOranlari::route('/'),
            'create' => Pages\CreateVergiOrani::route('/create'),
            'edit' => Pages\EditVergiOrani::route('/{record}/edit'),
        ];
    }
}
