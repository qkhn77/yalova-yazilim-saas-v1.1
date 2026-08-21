<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\SubeKaynagi\Pages;
use App\Models\Sube;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubeKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    protected static ?string $model = Sube::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Şubeler';

    protected static ?string $modelLabel = 'Şube';

    protected static ?string $pluralModelLabel = 'Şubeler';

    protected static ?string $slug = 'tanimlar/subeler';

    protected static function goruntuleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return PersonelTakipYetkiSablonlari::TANIM_GUNCELLE;
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select(['id', 'aktif_mi'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        if ($form->getOperation() !== 'create' && static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Checkbox::make('aktif_mi')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Şube bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Şube adı')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('kod')
                        ->label('Şube kodu')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('telefon')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(64),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\Textarea::make('adres')
                        ->label('Adres')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'ad',
                'kod',
                'telefon',
                'aktif_mi',
                'updated_at',
            ]))
            ->defaultSort('ad')
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label('Şube')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('telefon')
                    ->label('Telefon')
                    ->searchable(),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Güncellendi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')
                    ->label('Aktif'),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubeler::route('/'),
            'create' => Pages\CreateSube::route('/create'),
            'edit' => Pages\EditSube::route('/{record}/edit'),
        ];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }
}
