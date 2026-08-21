<?php

namespace App\Filament\Clusters\Restoran\Resources;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranKaynakErisimi;
use App\Filament\Clusters\Restoran\Resources\RestoranMenuUrunKaynagi\Pages;
use App\Models\Muhasebe\StokKarti;
use App\Models\Restoran\RestoranMenuKategorisi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RestoranMenuUrunKaynagi extends Resource
{
    use RestoranKaynakErisimi;

    /** @var array<int, array<int, string>> */
    private static array $kategoriSecenekleri = [];

    protected static ?string $model = RestoranMenuUrunu::class;

    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Menü Ürünleri';

    protected static ?string $modelLabel = 'Menü Ürünü';

    protected static ?string $pluralModelLabel = 'Menü Ürünleri';

    protected static ?string $slug = 'qr-menu/urunler';

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
                    'ad',
                    'fiyat',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\TextInput::make('ad')
                    ->label('Ad')
                    ->maxLength(191),
                Forms\Components\TextInput::make('fiyat')
                    ->label('Fiyat')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Menü ürünü')
                ->schema([
                    Forms\Components\Select::make('kategori_id')
                        ->label('Kategori')
                        ->options(fn (): array => self::kategoriSecenekleri())
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('stok_karti_id')
                        ->label('Stok kartı')
                        ->getSearchResultsUsing(fn (string $search): array => self::stokKartiAramaSonuclari($search))
                        ->getOptionLabelUsing(fn ($value): ?string => self::stokKartiEtiketi((int) $value))
                        ->searchable(),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->maxLength(191),
                    Forms\Components\TextInput::make('fiyat')
                        ->label('Fiyat')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('kdv_orani')
                        ->label('KDV oranı')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('gorsel_yolu')
                        ->label('Görsel yolu')
                        ->maxLength(191),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('siralama')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\Toggle::make('qr_menu_gorunur_mu')
                        ->label('QR menüde göster')
                        ->default(true),
                    Forms\Components\Toggle::make('stokta_var_mi')
                        ->label('Stokta var')
                        ->default(true),
                ])
                ->columns(3),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function stokKartiAramaSonuclari(string $search): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        if (! $firmaId) {
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

    /**
     * @return array<int, string>
     */
    private static function kategoriSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        if (! $firmaId) {
            return [];
        }

        return self::$kategoriSecenekleri[$firmaId] ??= RestoranMenuKategorisi::query()
            ->where('firma_id', $firmaId)
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'kategori_id',
                    'stok_karti_id',
                    'ad',
                    'fiyat',
                    'kdv_orani',
                    'qr_menu_gorunur_mu',
                    'stokta_var_mi',
                    'aktif_mi',
                ])
                ->with([
                    'kategori:id,ad',
                    'stokKarti:id,ad',
                    'recete:id,menu_urunu_id,aktif_mi',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ürün')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kategori.ad')->label('Kategori')->sortable(),
                Tables\Columns\TextColumn::make('stokKarti.ad')->label('Stok kartı')->toggleable(),
                Tables\Columns\IconColumn::make('recete.aktif_mi')->label('Recete')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('fiyat')->label('Fiyat')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('kdv_orani')->label('KDV')->suffix('%')->sortable(),
                Tables\Columns\IconColumn::make('qr_menu_gorunur_mu')->label('QR')->boolean(),
                Tables\Columns\IconColumn::make('stokta_var_mi')->label('Stok')->boolean(),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->options(fn (): array => self::kategoriSecenekleri()),
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
                Tables\Filters\TernaryFilter::make('qr_menu_gorunur_mu')->label('QR menü'),
                Tables\Filters\TernaryFilter::make('stokta_var_mi')->label('Stokta var'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (RestoranMenuUrunu $record): bool => ! DB::table('restoran_adisyon_kalemleri')->where('menu_urunu_id', (int) $record->getKey())->exists()
                        && ! DB::table('restoran_receteleri')->where('menu_urunu_id', (int) $record->getKey())->exists()),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestoranMenuUrunleri::route('/'),
            'create' => Pages\CreateRestoranMenuUrun::route('/create'),
            'edit' => Pages\EditRestoranMenuUrun::route('/{record}/edit'),
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
