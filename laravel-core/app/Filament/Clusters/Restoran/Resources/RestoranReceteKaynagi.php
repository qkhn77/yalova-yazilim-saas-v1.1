<?php

namespace App\Filament\Clusters\Restoran\Resources;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranKaynakErisimi;
use App\Filament\Clusters\Restoran\Resources\RestoranReceteKaynagi\Pages;
use App\Models\Muhasebe\StokKarti;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Restoran\RestoranRecetesi;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RestoranReceteKaynagi extends Resource
{
    use RestoranKaynakErisimi;

    private static ?int $aktifFirmaIdCache = null;

    protected static ?string $model = RestoranRecetesi::class;

    protected static ?string $cluster = RestoranCluster::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Receteler';

    protected static ?string $navigationGroup = 'QR Menu';

    protected static ?int $navigationSort = 33;

    protected static ?string $modelLabel = 'Recete';

    protected static ?string $pluralModelLabel = 'Receteler';

    protected static ?string $slug = 'qr-menu/receteler';

    protected static function goruntuleYetkisi(): string
    {
        return RestoranYetkiSablonlari::QR_MENU_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return RestoranYetkiSablonlari::QR_MENU_GUNCELLE;
    }

    protected static function guncelleYetkisi(): string
    {
        return RestoranYetkiSablonlari::QR_MENU_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return RestoranYetkiSablonlari::QR_MENU_GUNCELLE;
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'firma_id',
                    'aktif_mi',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        $olusturma = $form->getOperation() === 'create';

        if (! $olusturma && static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Checkbox::make('aktif_mi')
                    ->label('Aktif')
                    ->default(true),
            ]);
        }

        $kalemlerRepeater = Forms\Components\Repeater::make('kalemler')
            ->label('Recete kalemleri')
            ->schema([
                Forms\Components\Hidden::make('firma_id')
                    ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                    ->dehydrated(),
                Forms\Components\Select::make('stok_karti_id')
                    ->label('Stok karti')
                    ->getSearchResultsUsing(fn (string $search): array => self::stokKartiAramaSonuclari($search))
                    ->getOptionLabelUsing(fn ($value): ?string => self::stokKartiEtiketi((int) $value))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('miktar')
                    ->label('Miktar')
                    ->numeric()
                    ->minValue(0.0001)
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('fire_orani')
                    ->label('Fire %')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\Textarea::make('notlar')
                    ->label('Not')
                    ->columnSpanFull(),
            ])
            ->columns(4)
            ->defaultItems(1)
            ->reorderable(false)
            ->collapsible()
            ->columnSpanFull();

        if (! $olusturma) {
            $kalemlerRepeater->relationship();
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Recete')
                ->schema([
                    Forms\Components\Select::make('menu_urunu_id')
                        ->label('Menu urunu')
                        ->getSearchResultsUsing(fn (string $search): array => self::menuUrunuAramaSonuclari($search))
                        ->getOptionLabelUsing(fn ($value): ?string => self::menuUrunuEtiketi((int) $value))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('ad')
                        ->label('Recete adi')
                        ->maxLength(191),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\Textarea::make('notlar')
                        ->label('Notlar')
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Forms\Components\Section::make('Malzemeler')
                ->schema([
                    $kalemlerRepeater,
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function menuUrunuAramaSonuclari(string $search): array
    {
        $firmaId = self::aktifFirmaId();

        if ($firmaId === null) {
            return [];
        }

        return RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->when(trim($search) !== '', fn ($query) => $query->where('ad', 'like', '%'.trim($search).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    private static function menuUrunuEtiketi(int $id): ?string
    {
        if ($id < 1) {
            return null;
        }

        return RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->whereKey($id)
            ->value('ad');
    }

    /**
     * @return array<int, string>
     */
    private static function stokKartiAramaSonuclari(string $search): array
    {
        $firmaId = self::aktifFirmaId();

        if ($firmaId === null) {
            return [];
        }

        $search = trim($search);

        return StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('ad', 'like', '%'.$search.'%')
                    ->orWhere('kod', 'like', '%'.$search.'%')
                    ->orWhere('barkod', 'like', '%'.$search.'%');
            }))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    private static function stokKartiEtiketi(int $id): ?string
    {
        if ($id < 1) {
            return null;
        }

        return StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->whereKey($id)
            ->value('ad');
    }

    private static function aktifFirmaId(): ?int
    {
        $firmaId = self::$aktifFirmaIdCache ??= app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('menuUrunu.ad')->label('Menu urunu')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ad')->label('Recete')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kalemler_count')->label('Malzeme')->counts('kalemler')->sortable(),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Guncelleme')->dateTime('d.m.Y H:i')->sortable(),
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestoranReceteleri::route('/'),
            'create' => Pages\CreateRestoranRecete::route('/create'),
            'edit' => Pages\EditRestoranRecete::route('/{record}/edit'),
        ];
    }
}
