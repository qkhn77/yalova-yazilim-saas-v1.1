<?php

namespace App\Filament\Clusters\Restoran\Resources;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranKaynakErisimi;
use App\Filament\Clusters\Restoran\Resources\RestoranSalonKaynagi\Pages;
use App\Models\Restoran\RestoranSalonu;
use App\Models\Sube;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RestoranSalonKaynagi extends Resource
{
    use RestoranKaynakErisimi;

    protected static ?string $model = RestoranSalonu::class;

    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Salonlar';

    protected static ?string $modelLabel = 'Salon';

    protected static ?string $pluralModelLabel = 'Salonlar';

    protected static ?string $slug = 'tanimlar/salonlar';

    protected static function goruntuleYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_DUZENLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_DUZENLE;
    }

    protected static function silYetkisi(): string
    {
        return RestoranYetkiSablonlari::MASA_DUZENLE;
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
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
            Forms\Components\Section::make('Salon bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('kod')
                        ->label('Kod')
                        ->maxLength(64),
                    Forms\Components\Select::make('sube_id')
                        ->label('Şube')
                        ->options(fn (): array => static::subeSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('siralama')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
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
                    'firma_id',
                    'sube_id',
                    'ad',
                    'kod',
                    'aktif_mi',
                    'siralama',
                ])
                ->with(['sube:id,ad']))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('siralama')->label('Sıra')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
                Tables\Filters\SelectFilter::make('sube_id')
                    ->label('Şube')
                    ->options(fn (): array => static::subeSecenekleri()),
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
            'index' => Pages\ListRestoranSalonlari::route('/'),
            'create' => Pages\CreateRestoranSalon::route('/create'),
            'edit' => Pages\EditRestoranSalon::route('/{record}/edit'),
        ];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return RestoranSalonu::query()
                ->select(['id', 'aktif_mi'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    private static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }

    /**
     * @return array<int, string>
     */
    private static function subeSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheFirmaAnahtari = $firmaId ?: 'genel';

        return Cache::remember(
            "restoran:salon:sube-secenekleri:v1:{$cacheFirmaAnahtari}",
            now()->addMinutes(5),
            static fn (): array => Sube::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }
}
