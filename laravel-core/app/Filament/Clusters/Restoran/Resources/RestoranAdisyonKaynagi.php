<?php

namespace App\Filament\Clusters\Restoran\Resources;

use App\Filament\Clusters\Restoran as RestoranCluster;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranFilamentErisimYardimcisi;
use App\Filament\Clusters\Restoran\Kaynaklar\RestoranKaynakErisimi;
use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi\Pages;
use App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi\RelationManagers\TahsilatlarRelationManager;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonKalemi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Restoran\RestoranMasasi;
use App\Models\Restoran\RestoranMenuUrunu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\Restoran\RestoranMasaOperasyonServisi;
use App\Services\Restoran\RestoranFaturaServisi;
use App\Services\Restoran\RestoranSiparisKalemServisi;
use App\Services\Restoran\RestoranTahsilatServisi;
use App\Services\TenantContextService;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RestoranAdisyonKaynagi extends Resource
{
    use RestoranKaynakErisimi;

    /** @var array<int, array<int, string>> */
    private static array $personelSecenekleriCache = [];

    protected static ?string $model = RestoranAdisyonu::class;

    protected static ?string $cluster = RestoranCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Adisyonlar';

    protected static ?string $modelLabel = 'Adisyon';

    protected static ?string $pluralModelLabel = 'Adisyonlar';

    protected static ?string $slug = 'adisyonlar';

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
                        RestoranAdisyonu::DURUM_ACIK => 'Açık',
                        RestoranAdisyonu::DURUM_ODEMEDE => 'Ödemede',
                        RestoranAdisyonu::DURUM_KAPANDI => 'Kapandı',
                        RestoranAdisyonu::DURUM_IPTAL => 'İptal',
                    ])
                    ->default(RestoranAdisyonu::DURUM_ACIK)
                    ->required(),
            ]);
        }

        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): ?int => app(TenantContextService::class)->aktifFirmaId())
                ->dehydrated(),
            ...($detayModu ? [
                Forms\Components\Section::make('Adisyon bilgileri')
                    ->schema([
                        Forms\Components\TextInput::make('adisyon_no')
                            ->label('Adisyon no')
                            ->maxLength(64)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Select::make('siparis_tipi')
                            ->label('Sipariş tipi')
                            ->options([
                                'masa' => 'Masa',
                                'paket' => 'Paket',
                                'gel-al' => 'Gel-al',
                                'qr' => 'QR',
                                'online' => 'Online',
                            ])
                            ->default('masa')
                            ->required(),
                        Forms\Components\Select::make('masa_id')
                            ->label('Masa')
                            ->options(fn (): array => static::masaSecenekleri())
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('durum')
                            ->label('Durum')
                            ->options([
                                RestoranAdisyonu::DURUM_ACIK => 'Açık',
                                RestoranAdisyonu::DURUM_ODEMEDE => 'Ödemede',
                                RestoranAdisyonu::DURUM_KAPANDI => 'Kapandı',
                                RestoranAdisyonu::DURUM_IPTAL => 'İptal',
                            ])
                            ->default(RestoranAdisyonu::DURUM_ACIK)
                            ->required(),
                        Forms\Components\Select::make('cari_id')
                            ->label('Cari')
                            ->getSearchResultsUsing(fn (string $search): array => static::cariAramaSonuclari($search))
                            ->getOptionLabelUsing(fn ($value): ?string => static::cariSecenekEtiketi((int) $value))
                            ->searchable()
                            ->searchPrompt('Cari adı, kodu veya telefon ile ara')
                            ->noSearchResultsMessage('Eşleşen cari bulunamadı'),
                        Forms\Components\Select::make('garson_personel_id')
                            ->label('Garson')
                            ->options(fn (): array => static::personelSecenekleri())
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('kasiyer_personel_id')
                            ->label('Kasiyer')
                            ->options(fn (): array => static::personelSecenekleri())
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('kurye_personel_id')
                            ->label('Kurye')
                            ->options(fn (): array => static::personelSecenekleri())
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('musteri_sayisi')
                            ->label('Müşteri sayısı')
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                        Forms\Components\TextInput::make('para_birimi')
                            ->label('Para birimi')
                            ->maxLength(3)
                            ->default('TRY'),
                        Forms\Components\Textarea::make('notlar')
                            ->label('Notlar')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ] : [
                Forms\Components\Section::make('Adisyon')
                    ->schema([
                        Forms\Components\Select::make('durum')
                            ->label('Durum')
                            ->options([
                                RestoranAdisyonu::DURUM_ACIK => 'Açık',
                                RestoranAdisyonu::DURUM_ODEMEDE => 'Ödemede',
                                RestoranAdisyonu::DURUM_KAPANDI => 'Kapandı',
                                RestoranAdisyonu::DURUM_IPTAL => 'İptal',
                            ])
                            ->default(RestoranAdisyonu::DURUM_ACIK)
                            ->required(),
                    ]),
            ]),
            ...($detayModu ? [
            Forms\Components\Section::make('Paket servis')
                ->schema([
                    Forms\Components\Select::make('paket_durum')
                        ->label('Paket durumu')
                        ->options([
                            RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR => 'Hazırlanıyor',
                            RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI => 'Kuryeye atandı',
                            RestoranAdisyonu::PAKET_DURUM_YOLDA => 'Yolda',
                            RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI => 'Teslim edildi',
                            RestoranAdisyonu::PAKET_DURUM_IPTAL => 'İptal',
                        ]),
                    Forms\Components\TextInput::make('teslimat_telefon')
                        ->label('Teslimat telefonu')
                        ->maxLength(32),
                    Forms\Components\DateTimePicker::make('tahmini_teslimat_at')
                        ->label('Tahmini teslimat')
                        ->seconds(false),
                    Forms\Components\Textarea::make('teslimat_adresi')
                        ->label('Teslimat adresi')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('teslimat_notu')
                        ->label('Teslimat notu')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            ] : []),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('adisyon_no')->label('Adisyon')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('masa.ad')->label('Masa')->sortable(),
                Tables\Columns\TextColumn::make('siparis_tipi')->label('Tip')->badge(),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
                Tables\Columns\TextColumn::make('garson.ad_soyad')->label('Garson')->sortable(),
                Tables\Columns\TextColumn::make('kurye.ad_soyad')->label('Kurye')->sortable(),
                Tables\Columns\TextColumn::make('genel_toplam')->label('Toplam')->money('TRY')->sortable(),
                Tables\Columns\TextColumn::make('acilis_at')->label('Açılış')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        RestoranAdisyonu::DURUM_ACIK => 'Açık',
                        RestoranAdisyonu::DURUM_ODEMEDE => 'Ödemede',
                        RestoranAdisyonu::DURUM_KAPANDI => 'Kapandı',
                        RestoranAdisyonu::DURUM_IPTAL => 'İptal',
                    ]),
                Tables\Filters\SelectFilter::make('siparis_tipi')
                    ->label('Sipariş tipi')
                    ->options([
                        'masa' => 'Masa',
                        'paket' => 'Paket',
                        'gel-al' => 'Gel-al',
                        'qr' => 'QR',
                        'online' => 'Online',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('menu_urunu_ekle')
                    ->label('Urun ekle')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn (RestoranAdisyonu $record): bool => static::operasyonAksiyonuGorunurMu($record))
                    ->form([
                        Forms\Components\Select::make('menu_urunu_id')
                            ->label('Menu urunu')
                            ->options(fn (RestoranAdisyonu $record): array => static::menuUrunuSecenekleri($record))
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('miktar')
                            ->label('Miktar')
                            ->numeric()
                            ->minValue(0.0001)
                            ->default(1)
                            ->required(),
                        Forms\Components\Textarea::make('mutfak_notu')
                            ->label('Mutfak notu')
                            ->columnSpanFull(),
                    ])
                    ->action(function (RestoranAdisyonu $record, array $data): void {
                        $menuUrunu = RestoranMenuUrunu::query()
                            ->withoutGlobalScope(FirmaIdTenantScope::class)
                            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                            ->whereKey($data['menu_urunu_id'])
                            ->firstOrFail();

                        app(RestoranSiparisKalemServisi::class)->menuUrunuEkle(
                            $record,
                            $menuUrunu,
                            (float) ($data['miktar'] ?? 1),
                            $data['mutfak_notu'] ?? null,
                        );

                        Notification::make()
                            ->title('Menu urunu adisyona eklendi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('masa_tasi')
                    ->label('Masa taşı')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (RestoranAdisyonu $record): bool => static::operasyonAksiyonuGorunurMu($record))
                    ->form([
                        Forms\Components\Select::make('hedef_masa_id')
                            ->label('Hedef masa')
                            ->options(fn (RestoranAdisyonu $record): array => RestoranMasasi::query()
                                ->withoutGlobalScope(FirmaIdTenantScope::class)
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->where('aktif_mi', true)
                                ->whereKeyNot($record->masa_id)
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (RestoranAdisyonu $record, array $data): void {
                        $hedefMasa = RestoranMasasi::query()
                            ->withoutGlobalScope(FirmaIdTenantScope::class)
                            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                            ->whereKey($data['hedef_masa_id'])
                            ->firstOrFail();

                        app(RestoranMasaOperasyonServisi::class)->masaTasi($record, $hedefMasa);

                        Notification::make()
                            ->title('Adisyon hedef masaya taşındı.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('birlestir')
                    ->label('Birleştir')
                    ->icon('heroicon-o-link')
                    ->visible(fn (RestoranAdisyonu $record): bool => static::operasyonAksiyonuGorunurMu($record))
                    ->form([
                        Forms\Components\Select::make('hedef_adisyon_id')
                            ->label('Hedef adisyon')
                            ->options(fn (RestoranAdisyonu $record): array => RestoranAdisyonu::query()
                                ->withoutGlobalScope(FirmaIdTenantScope::class)
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->whereKeyNot($record->id)
                                ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE])
                                ->orderByDesc('acilis_at')
                                ->pluck('adisyon_no', 'id')
                                ->all())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (RestoranAdisyonu $record, array $data): void {
                        $hedefAdisyon = RestoranAdisyonu::query()
                            ->withoutGlobalScope(FirmaIdTenantScope::class)
                            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                            ->whereKey($data['hedef_adisyon_id'])
                            ->firstOrFail();

                        app(RestoranMasaOperasyonServisi::class)->masalariBirlestir($record, $hedefAdisyon);

                        Notification::make()
                            ->title('Adisyonlar birleştirildi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('adisyon_bol')
                    ->label('Böl')
                    ->icon('heroicon-o-scissors')
                    ->visible(fn (RestoranAdisyonu $record): bool => static::operasyonAksiyonuGorunurMu($record))
                    ->form([
                        Forms\Components\Select::make('kalem_idleri')
                            ->label('Taşınacak kalemler')
                            ->options(fn (RestoranAdisyonu $record): array => RestoranAdisyonKalemi::query()
                                ->withoutGlobalScope(FirmaIdTenantScope::class)
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->where('adisyon_id', $record->id)
                                ->where('durum', '!=', RestoranAdisyonKalemi::DURUM_IPTAL)
                                ->orderBy('urun_adi')
                                ->get()
                                ->mapWithKeys(static fn (RestoranAdisyonKalemi $kalem): array => [
                                    (int) $kalem->id => $kalem->urun_adi.' x '.rtrim(rtrim((string) $kalem->miktar, '0'), '.'),
                                ])
                                ->all())
                            ->multiple()
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('hedef_masa_id')
                            ->label('Hedef masa')
                            ->options(fn (RestoranAdisyonu $record): array => RestoranMasasi::query()
                                ->withoutGlobalScope(FirmaIdTenantScope::class)
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->where('aktif_mi', true)
                                ->when($record->masa_id, fn ($query) => $query->whereKeyNot($record->masa_id))
                                ->whereDoesntHave('adisyonlar', function ($query): void {
                                    $query
                                        ->withoutGlobalScope(FirmaIdTenantScope::class)
                                        ->whereIn('durum', [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE]);
                                })
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all())
                            ->searchable(),
                    ])
                    ->action(function (RestoranAdisyonu $record, array $data): void {
                        $hedefMasa = null;
                        if (! empty($data['hedef_masa_id'])) {
                            $hedefMasa = RestoranMasasi::query()
                                ->withoutGlobalScope(FirmaIdTenantScope::class)
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->whereKey($data['hedef_masa_id'])
                                ->firstOrFail();
                        }

                        app(RestoranMasaOperasyonServisi::class)->adisyonuBol(
                            $record,
                            array_map('intval', $data['kalem_idleri'] ?? []),
                            $hedefMasa,
                        );

                        Notification::make()
                            ->title('Adisyon bölündü.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('tahsilat')
                    ->label('Tahsilat')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (RestoranAdisyonu $record): bool => static::tahsilatAksiyonuGorunurMu($record))
                    ->form([
                        Forms\Components\Select::make('odeme_kanali')
                            ->label('Ödeme kanalı')
                            ->options([
                                'kasa' => 'Kasa',
                                'banka' => 'Banka',
                                'pos' => 'POS',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('tutar')
                            ->label('Tutar')
                            ->numeric()
                            ->minValue(0.01)
                            ->default(fn (RestoranAdisyonu $record): float => static::kalanTahsilatTutari($record))
                            ->required(),
                        Forms\Components\Select::make('kasa_hesap_id')
                            ->label('Kasa hesabı')
                            ->options(fn (): array => KasaHesabi::query()
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all())
                            ->searchable(),
                        Forms\Components\Select::make('banka_hesap_id')
                            ->label('Banka hesabı')
                            ->options(fn (): array => BankaHesabi::query()
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all())
                            ->searchable(),
                        Forms\Components\Select::make('pos_hesap_id')
                            ->label('POS hesabı')
                            ->options(fn (): array => PosHesabi::query()
                                ->withoutGlobalScope(FirmaIdTenantScope::class)
                                ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all())
                            ->searchable(),
                        Forms\Components\Textarea::make('notlar')
                            ->label('Notlar')
                            ->columnSpanFull(),
                    ])
                    ->action(function (RestoranAdisyonu $record, array $data): void {
                        app(RestoranTahsilatServisi::class)->parcaliTahsilatOlustur($record->refresh(), [
                            'odeme_kanali' => $data['odeme_kanali'] ?? null,
                            'kasa_hesap_id' => $data['kasa_hesap_id'] ?? null,
                            'banka_hesap_id' => $data['banka_hesap_id'] ?? null,
                            'pos_hesap_id' => $data['pos_hesap_id'] ?? null,
                            'tutar' => $data['tutar'] ?? 0,
                            'notlar' => $data['notlar'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Tahsilat oluşturuldu.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('bekleyen_fatura_olustur')
                    ->label('Bekleyen fatura')
                    ->icon('heroicon-o-document-plus')
                    ->visible(fn (RestoranAdisyonu $record): bool => static::faturaAksiyonuGorunurMu($record))
                    ->form([
                        Forms\Components\Select::make('e_belge_tipi')
                            ->label('Belge tipi')
                            ->options([
                                'fatura' => 'Fatura',
                                'e_arsiv' => 'E-Arşiv',
                                'e_fatura' => 'E-Fatura',
                            ])
                            ->default('fatura')
                            ->required(),
                    ])
                    ->action(function (RestoranAdisyonu $record, array $data): void {
                        $fatura = app(RestoranFaturaServisi::class)->bekleyenFaturaOlustur(
                            $record->refresh(),
                            (string) ($data['e_belge_tipi'] ?? 'fatura'),
                        );

                        Notification::make()
                            ->title('Bekleyen fatura hazır.')
                            ->body('Fatura kaydı #'.(int) $fatura->getKey().' olarak oluşturuldu.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestoranAdisyonlari::route('/'),
            'create' => Pages\CreateRestoranAdisyon::route('/create'),
            'edit' => Pages\EditRestoranAdisyon::route('/{record}/edit'),
        ];
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (! static::detayModu() && filled(request()->route('record'))) {
            return RestoranAdisyonu::query()
                ->select(['id', 'firma_id', 'durum'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function getRelations(): array
    {
        return static::detayModu() ? [
            TahsilatlarRelationManager::class,
        ] : [];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay') || request()->boolean('tahsilat_detay');
    }

    public static function tahsilatDetaylariGoster(): bool
    {
        return static::detayModu();
    }

    /**
     * @return array<int, string>
     */
    private static function personelSecenekleri(): array
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();

        if (array_key_exists($firmaId, self::$personelSecenekleriCache)) {
            return self::$personelSecenekleriCache[$firmaId];
        }

        return self::$personelSecenekleriCache[$firmaId] = Cache::remember(
            "restoran:adisyon:personel-secenekleri:v1:{$firmaId}",
            now()->addMinutes(5),
            static fn (): array => Personel::query()
                ->where('firma_id', $firmaId)
                ->where('durum', Personel::DURUM_AKTIF)
                ->orderBy('ad_soyad')
                ->pluck('ad_soyad', 'id')
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    private static function masaSecenekleri(): array
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        $cacheFirmaAnahtari = $firmaId ?: 'genel';

        return Cache::remember(
            "restoran:adisyon:masa-secenekleri:v1:{$cacheFirmaAnahtari}",
            now()->addMinutes(5),
            static fn (): array => RestoranMasasi::query()
                ->where('firma_id', $firmaId)
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    private static function cariAramaSonuclari(string $search): array
    {
        $aranan = trim($search);
        $terim = str_replace(['%', '_'], ['\\%', '\\_'], $aranan);

        return Cari::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
            ->when($aranan !== '', function ($query) use ($terim): void {
                $query->where(function ($inner) use ($terim): void {
                    $inner
                        ->where('ad', 'like', '%'.$terim.'%')
                        ->orWhere('kod', 'like', '%'.$terim.'%')
                        ->orWhere('telefon', 'like', '%'.$terim.'%');
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(static fn (Cari $cari): array => [
                (int) $cari->id => static::cariSecenekMetni($cari),
            ])
            ->all();
    }

    private static function cariSecenekEtiketi(int $cariId): ?string
    {
        if ($cariId < 1) {
            return null;
        }

        $cari = Cari::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
            ->whereKey($cariId)
            ->first(['id', 'ad', 'kod']);

        return $cari ? static::cariSecenekMetni($cari) : null;
    }

    private static function cariSecenekMetni(Cari $cari): string
    {
        $kod = trim((string) $cari->kod);
        $ad = trim((string) $cari->ad);

        return $kod !== '' ? "{$kod} - {$ad}" : $ad;
    }

    private static function tahsilatAksiyonuGorunurMu(RestoranAdisyonu $record): bool
    {
        return ! $record->finans_hareketi_id
            && $record->durum !== RestoranAdisyonu::DURUM_IPTAL
            && (float) $record->genel_toplam > 0
            && RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::ADISYON_TAHSILAT)
            && RestoranFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record);
    }

    private static function kalanTahsilatTutari(RestoranAdisyonu $record): float
    {
        $tahsilEdilen = (float) RestoranAdisyonTahsilati::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $record->firma_id)
            ->where('adisyon_id', $record->id)
            ->where('durum', RestoranAdisyonTahsilati::DURUM_AKTIF)
            ->sum('tutar');

        return round(max(0, (float) $record->genel_toplam - $tahsilEdilen), 2);
    }

    private static function operasyonAksiyonuGorunurMu(RestoranAdisyonu $record): bool
    {
        return ! $record->finans_hareketi_id
            && in_array((string) $record->durum, [RestoranAdisyonu::DURUM_ACIK, RestoranAdisyonu::DURUM_ODEMEDE], true)
            && RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::ADISYON_GUNCELLE)
            && RestoranFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record);
    }

    private static function faturaAksiyonuGorunurMu(RestoranAdisyonu $record): bool
    {
        return $record->durum === RestoranAdisyonu::DURUM_KAPANDI
            && (float) $record->genel_toplam > 0
            && RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::ADISYON_FATURA)
            && RestoranFilamentErisimYardimcisi::kayitAktifFirmayaAitMi($record);
    }

    /**
     * @return array<int, string>
     */
    private static function menuUrunuSecenekleri(RestoranAdisyonu $adisyon): array
    {
        return RestoranMenuUrunu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->with('kategori')
            ->where('firma_id', app(TenantContextService::class)->aktifFirmaId())
            ->where('aktif_mi', true)
            ->where('stokta_var_mi', true)
            ->whereHas('kategori', function ($query) use ($adisyon): void {
                $query
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->where('firma_id', $adisyon->firma_id)
                    ->where('aktif_mi', true)
                    ->when($adisyon->sube_id, function ($inner) use ($adisyon): void {
                        $inner->where(function ($subeQuery) use ($adisyon): void {
                            $subeQuery
                                ->whereNull('sube_id')
                                ->orWhere('sube_id', $adisyon->sube_id);
                        });
                    });
            })
            ->orderBy('ad')
            ->get()
            ->mapWithKeys(static fn (RestoranMenuUrunu $urun): array => [
                (int) $urun->id => trim(($urun->kategori?->ad ? $urun->kategori->ad.' - ' : '').$urun->ad),
            ])
            ->all();
    }
}
