<?php

namespace App\Filament\Clusters\PersonelTakip\Resources;

use App\Filament\Clusters\PersonelTakip as PersonelTakipCluster;
use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipKaynakErisimi;
use App\Filament\Clusters\PersonelTakip\Resources\PersonelGorevKaynagi\Pages;
use App\Models\Personel\PersonelDepartmani;
use App\Models\Personel\PersonelGorevi;
use App\Services\TenantContextService;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PersonelGorevKaynagi extends Resource
{
    use PersonelTakipKaynakErisimi;

    protected static ?string $model = PersonelGorevi::class;

    protected static ?string $cluster = PersonelTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Görevler';

    protected static ?string $modelLabel = 'Görev';

    protected static ?string $pluralModelLabel = 'Görevler';

    protected static ?string $slug = 'tanimlar/gorevler';

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

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\TextInput::make('ad')
                    ->label('Ad')
                    ->required()
                    ->maxLength(191),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Görev bilgileri')
                ->schema([
                    Forms\Components\Select::make('departman_id')
                        ->label('Departman')
                        ->options(fn (): array => PersonelDepartmani::query()->orderBy('ad')->pluck('ad', 'id')->all())
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->maxLength(64),
                    Forms\Components\Select::make('varsayilan_maas_tipi')
                        ->label('Varsayılan maaş tipi')
                        ->options([
                            'aylik' => 'Aylık',
                            'gunluk' => 'Günlük',
                            'saatlik' => 'Saatlik',
                            'primli' => 'Primli',
                            'karma' => 'Karma',
                        ]),
                    Forms\Components\TextInput::make('varsayilan_ucret')
                        ->label('Varsayılan ücret')
                        ->numeric(),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\TextInput::make('siralama')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'ad',
                    'departman_id',
                    'kod',
                    'aktif_mi',
                    'siralama',
                ])
                ->with('departman:id,ad'))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('departman.ad')->label('Departman')->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('siralama')->label('Sıra')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonelGorevleri::route('/'),
            'create' => Pages\CreatePersonelGorev::route('/create'),
            'edit' => Pages\EditPersonelGorev::route('/{record}/edit'),
        ];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return PersonelGorevi::query()
                ->select(['id', 'ad'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
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
