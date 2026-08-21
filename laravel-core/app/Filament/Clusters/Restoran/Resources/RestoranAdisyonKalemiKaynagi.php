<?php

namespace App\Filament\Clusters\Restoran\Resources;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranKaynakErisimi;
use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKalemiKaynagi\Pages;
use App\Models\Muhasebe\StokKarti;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RestoranAdisyonKalemiKaynagi extends Resource
{
    use RestoranKaynakErisimi;

    protected static ?string $model = RestoranAdisyonKalemi::class;

    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Adisyon Kalemleri';

    protected static ?string $modelLabel = 'Adisyon Kalemi';

    protected static ?string $pluralModelLabel = 'Adisyon Kalemleri';

    protected static ?string $slug = 'adisyon-kalemleri';

    protected static function goruntuleYetkisi(): string
    {
        return RestoranYetkiSablonlari::ADISYON_GORUNTULE;
    }

    protected static function olusturYetkisi(): string
    {
        return RestoranYetkiSablonlari::ADISYON_OLUSTUR;
    }

    protected static function guncelleYetkisi(): string
    {
        return RestoranYetkiSablonlari::ADISYON_GUNCELLE;
    }

    protected static function silYetkisi(): string
    {
        return RestoranYetkiSablonlari::ADISYON_IPTAL;
    }

    public static function form(Form $form): Form
    {
        $detayModu = static::detayModu();

        if (! $detayModu) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options([
                        RestoranAdisyonKalemi::DURUM_YENI => 'Yeni',
                        RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => 'Hazırlanıyor',
                        RestoranAdisyonKalemi::DURUM_HAZIR => 'Hazır',
                        RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI => 'Servis edildi',
                        RestoranAdisyonKalemi::DURUM_IPTAL => 'İptal',
                    ])
                    ->default(RestoranAdisyonKalemi::DURUM_YENI)
                    ->native()
                    ->required(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            Forms\Components\Section::make('Kalem bilgileri')
                ->schema([
                    ...($detayModu ? [
                    Forms\Components\Select::make('adisyon_id')
                        ->label('Adisyon')
                        ->getSearchResultsUsing(fn (string $search): array => static::adisyonAramaSonuclari($search))
                        ->getOptionLabelUsing(fn ($value): ?string => static::adisyonSecenekEtiketi((int) $value))
                        ->required()
                        ->searchable()
                        ->searchPrompt('Adisyon numarası ile ara')
                        ->noSearchResultsMessage('Eşleşen adisyon bulunamadı'),
                    Forms\Components\Select::make('stok_karti_id')
                        ->label('Stok kartı')
                        ->getSearchResultsUsing(fn (string $search): array => static::stokAramaSonuclari($search))
                        ->getOptionLabelUsing(fn ($value): ?string => static::stokSecenekEtiketi((int) $value))
                        ->searchable()
                        ->searchPrompt('Stok adı veya kodu ile ara')
                        ->noSearchResultsMessage('Eşleşen stok bulunamadı'),
                    ] : []),
                    Forms\Components\TextInput::make('urun_adi')
                        ->label('Ürün adı')
                        ->maxLength(191),
                    Forms\Components\TextInput::make('miktar')
                        ->label('Miktar')
                        ->numeric()
                        ->minValue(0.0001)
                        ->default(1)
                        ->required(),
                    ...($detayModu ? [
                    Forms\Components\TextInput::make('birim_fiyat')
                        ->label('Birim fiyat')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('kdv_orani')
                        ->label('KDV oranı')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('iskonto_tutari')
                        ->label('İskonto')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    ] : []),
                    ...($detayModu ? [
                    Forms\Components\Select::make('hazirlayan_personel_id')
                        ->label('Hazırlayan')
                        ->options(fn (): array => static::personelSecenekleri())
                        ->searchable()
                        ->preload(),
                    ] : []),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options([
                            RestoranAdisyonKalemi::DURUM_YENI => 'Yeni',
                            RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => 'Hazırlanıyor',
                            RestoranAdisyonKalemi::DURUM_HAZIR => 'Hazır',
                            RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI => 'Servis edildi',
                            RestoranAdisyonKalemi::DURUM_IPTAL => 'İptal',
                        ])
                        ->default(RestoranAdisyonKalemi::DURUM_YENI)
                        ->required(),
                    Forms\Components\Textarea::make('mutfak_notu')
                        ->label('Mutfak notu')
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (! static::detayModu() && filled(request()->route('record'))) {
            return static::getModel()::query()
                ->select(['id', 'durum'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'adisyon:id,adisyon_no',
                'hazirlayan:id,ad_soyad',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('adisyon.adisyon_no')->label('Adisyon')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('urun_adi')->label('Ürün')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('miktar')->label('Miktar')->sortable(),
                Tables\Columns\TextColumn::make('birim_fiyat')->label('Birim')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('toplam_tutar')->label('Toplam')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
                Tables\Columns\TextColumn::make('hazirlayan.ad_soyad')->label('Hazırlayan')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Kayıt')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        RestoranAdisyonKalemi::DURUM_YENI => 'Yeni',
                        RestoranAdisyonKalemi::DURUM_HAZIRLANIYOR => 'Hazırlanıyor',
                        RestoranAdisyonKalemi::DURUM_HAZIR => 'Hazır',
                        RestoranAdisyonKalemi::DURUM_SERVIS_EDILDI => 'Servis edildi',
                        RestoranAdisyonKalemi::DURUM_IPTAL => 'İptal',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestoranAdisyonKalemleri::route('/'),
            'create' => Pages\CreateRestoranAdisyonKalemi::route('/create'),
            'edit' => Pages\EditRestoranAdisyonKalemi::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function adisyonAramaSonuclari(string $search): array
    {
        $aranan = trim($search);
        $terim = str_replace(['%', '_'], ['\\%', '\\_'], $aranan);

        return RestoranAdisyonu::query()
            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
            ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
            ->when($aranan !== '', fn ($query) => $query->where('adisyon_no', 'like', '%'.$terim.'%'))
            ->orderByDesc('acilis_at')
            ->limit(50)
            ->pluck('adisyon_no', 'id')
            ->all();
    }

    private static function adisyonSecenekEtiketi(int $adisyonId): ?string
    {
        if ($adisyonId < 1) {
            return null;
        }

        return RestoranAdisyonu::query()
            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
            ->whereKey($adisyonId)
            ->value('adisyon_no');
    }

    /**
     * @return array<int, string>
     */
    private static function stokAramaSonuclari(string $search): array
    {
        $aranan = trim($search);
        $terim = str_replace(['%', '_'], ['\\%', '\\_'], $aranan);

        return StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
            ->when($aranan !== '', function ($query) use ($terim): void {
                $query->where(function ($inner) use ($terim): void {
                    $inner
                        ->where('ad', 'like', '%'.$terim.'%')
                        ->orWhere('kod', 'like', '%'.$terim.'%');
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(static fn (StokKarti $stok): array => [
                (int) $stok->id => static::stokSecenekMetni($stok),
            ])
            ->all();
    }

    private static function stokSecenekEtiketi(int $stokId): ?string
    {
        if ($stokId < 1) {
            return null;
        }

        $stok = StokKarti::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
            ->whereKey($stokId)
            ->first(['id', 'ad', 'kod']);

        return $stok ? static::stokSecenekMetni($stok) : null;
    }

    private static function stokSecenekMetni(StokKarti $stok): string
    {
        $kod = trim((string) $stok->kod);
        $ad = trim((string) $stok->ad);

        return $kod !== '' ? "{$kod} - {$ad}" : $ad;
    }

    /**
     * @return array<int, string>
     */
    private static function personelSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheFirmaAnahtari = $firmaId ?: 'genel';

        return Cache::remember(
            "restoran:adisyon-kalemi:personel-secenekleri:v1:{$cacheFirmaAnahtari}",
            now()->addMinutes(5),
            static fn (): array => Personel::query()
                ->where('firma_id', $firmaId)
                ->where('durum', Personel::DURUM_AKTIF)
                ->orderBy('ad_soyad')
                ->pluck('ad_soyad', 'id')
                ->all()
        );
    }
}
