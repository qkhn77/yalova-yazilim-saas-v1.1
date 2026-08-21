<?php

namespace App\Filament\Clusters\Restoran\Resources;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranKaynakErisimi;
use App\Filament\Clusters\Restoran\Resources\RestoranMenuKategoriKaynagi\Pages;
use App\Models\Restoran\RestoranMenuKategorisi;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RestoranMenuKategoriKaynagi extends Resource
{
    use RestoranKaynakErisimi;

    protected static ?string $model = RestoranMenuKategorisi::class;

    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Menü Kategorileri';

    protected static ?string $modelLabel = 'Menü Kategorisi';

    protected static ?string $pluralModelLabel = 'Menü Kategorileri';

    protected static ?string $slug = 'qr-menu/kategoriler';

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
            Forms\Components\Section::make('Kategori bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Ad')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('slug')
                        ->label('Kısa ad')
                        ->maxLength(128),
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
                    'slug',
                    'aktif_mi',
                    'siralama',
                ])
                ->with(['sube:id,ad']))
            ->columns([
                Tables\Columns\TextColumn::make('ad')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Kısa ad')->searchable(),
                Tables\Columns\TextColumn::make('sube.ad')->label('Şube')->sortable(),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
                Tables\Columns\TextColumn::make('siralama')->label('Sıra')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('aktif_mi')->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (RestoranMenuKategorisi $record): bool => ! DB::table('restoran_menu_urunleri')->where('kategori_id', (int) $record->getKey())->exists()),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestoranMenuKategorileri::route('/'),
            'create' => Pages\CreateRestoranMenuKategori::route('/create'),
            'edit' => Pages\EditRestoranMenuKategori::route('/{record}/edit'),
        ];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return RestoranMenuKategorisi::query()
                ->select(['id', 'firma_id', 'ad'])
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
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::detayModu();
    }

    /**
     * @return array<int, string>
     */
    private static function subeSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheFirmaAnahtari = $firmaId ?: 'genel';

        return Cache::remember(
            "restoran:menu-kategori:sube-secenekleri:v1:{$cacheFirmaAnahtari}",
            now()->addMinutes(5),
            static fn (): array => Sube::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }
}
