<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources;

use App\Filament\Clusters\TeklifYonetimi;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages\CreateTeklif;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages\EditTeklif;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages\ListTeklifler;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages\ViewTeklif;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Teklif;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\CariKoduUretici;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\Services\TenantContextService;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use App\TeklifYonetimi\Servisler\TeklifNumaraServisi;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeklifKaynagi extends Resource
{
    /** @var array<string, array<int|string, string>> */
    protected static array $secenekCache = [];

    protected static ?int $gecerliFirmaIdCache = null;

    protected static ?string $cluster = TeklifYonetimi::class;

    protected static ?string $model = Teklif::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $slug = 'teklifler';

    protected static ?string $modelLabel = 'Teklif';

    protected static ?string $pluralModelLabel = 'Teklifler';

    public static function canViewAny(): bool
    {
        if (static::adminKullaniciMi()) {
            return true;
        }

        return Gate::allows('viewAny', Teklif::class);
    }

    public static function canView(Model $record): bool
    {
        if (static::adminKullaniciMi()) {
            return $record instanceof Teklif;
        }

        return $record instanceof Teklif && Gate::allows('view', $record);
    }

    public static function canCreate(): bool
    {
        if (static::adminKullaniciMi()) {
            return true;
        }

        return Gate::allows('create', Teklif::class);
    }

    public static function canEdit(Model $record): bool
    {
        if (static::adminKullaniciMi()) {
            return $record instanceof Teklif;
        }

        return $record instanceof Teklif && Gate::allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        if (static::adminKullaniciMi()) {
            return $record instanceof Teklif;
        }

        return $record instanceof Teklif && Gate::allows('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        if (static::adminKullaniciMi()) {
            return true;
        }

        return Gate::allows('deleteAny', Teklif::class);
    }

    private static function adminKullaniciMi(): bool
    {
        $kullanici = Auth::user();

        return (bool) ($kullanici?->super_admin_mi ?? false)
            || (bool) ($kullanici?->is_admin ?? false);
    }

    public static function form(Form $form): Form
    {
        $kalemDetaylariGoster = static::kalemDetaylariGoster();

        return $form
            ->columns(12)
            ->schema([
                ...($kalemDetaylariGoster ? [
                Hidden::make('kur_seti'),
                Hidden::make('kur_seti_alindi_at'),
                Hidden::make('kur_seti_kaynagi'),
                Hidden::make('kur_seti_kur_tipi'),
                Hidden::make('kur_hata_mesaji')->dehydrated(false),
                Hidden::make('kur_init_yapildi')->default(false)->dehydrated(false),
                Hidden::make('para_birimi_onceki')->dehydrated(false),
                ] : [
                    Hidden::make('teklif_baski_sablonu_id'),
                    Hidden::make('gecerlilik_tarihi'),
                    Hidden::make('para_birimi')->default('TRY'),
                    Hidden::make('kur_seti'),
                    Hidden::make('kur_seti_alindi_at'),
                    Hidden::make('kur_seti_kaynagi'),
                    Hidden::make('kur_seti_kur_tipi'),
                    Hidden::make('revizyon_no')->default(1),
                    Hidden::make('teslim_suresi'),
                    Hidden::make('aciklama'),
                    Hidden::make('notlar'),
                    Hidden::make('kosullar'),
                    Hidden::make('odeme_plani'),
                ]),

                Section::make('Teklif Başlığı')
                    ->extraAttributes(['class' => 'teklif-hero-card'])
                    ->schema([
                        Grid::make(12)
                            ->schema([
                                Section::make('Müşteri ve teklif bilgileri')
                                    ->extraAttributes(['class' => 'teklif-editor-card teklif-customer-card'])
                                    ->schema([
                                        Select::make('firma_id')
                                            ->relationship('firma', 'ad')
                                            ->searchable()
                                            ->default(fn (): ?int => static::aktifFirmaId() ?: null)
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set): void {
                                                $set('teklif_baski_sablonu_id', static::varsayilanTeklifSablonuId((int) $state));
                                            })
                                            ->dehydrated()
                                            ->dehydratedWhenHidden()
                                            ->visible((bool) (Auth::user()?->super_admin_mi || Auth::user()?->is_admin)),
                                        Select::make('cari_id')
                                            ->label('Cari')
                                            ->getSearchResultsUsing(fn (string $search): array => static::cariAramaSonuclari($search))
                                            ->getOptionLabelUsing(fn ($value): ?string => filled($value)
                                                ? Cari::tenantScopeOlmadan(fn () => Cari::query()->whereKey($value)->value('ad'))
                                                : null)
                                            ->searchable()
                                            ->createOptionForm([
                                                TextInput::make('ad')
                                                    ->label('Ad Soyad / Ünvan')
                                                    ->required()
                                                    ->maxLength(191),
                                                TextInput::make('telefon')
                                                    ->label('Telefon')
                                                    ->maxLength(32),
                                                Textarea::make('adres')
                                                    ->label('Adres')
                                                    ->rows(3)
                                                    ->columnSpanFull(),
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                $firmaId = static::gecerliFirmaId();
                                                if ($firmaId < 1) {
                                                    throw ValidationException::withMessages([
                                                        'cari_id' => 'Önce firma seçin veya aktif firma oturumu açın.',
                                                    ]);
                                                }

                                                $cari = new Cari();
                                                $cari->firma_id = $firmaId;
                                                $cari->kod = app(CariKoduUretici::class)->sonraki($firmaId);
                                                $cari->ad = trim((string) ($data['ad'] ?? ''));
                                                $cari->telefon = filled($data['telefon'] ?? null) ? trim((string) $data['telefon']) : null;
                                                $cari->adres = filled($data['adres'] ?? null) ? trim((string) $data['adres']) : null;
                                                $cari->tur = CariTuru::Musteri;
                                                $cari->durum = 'aktif';
                                                $cari->para_birimi = 'TRY';
                                                $cari->save();
                                                Cache::forget('teklif_cari_arama|'.$firmaId.'||20');

                                                return (int) $cari->getKey();
                                            })
                                            ->required(),
                                        TextInput::make('baslik')
                                            ->label('Teklif başlığı')
                                            ->placeholder('Örn. Kamera sistemi yenileme teklifi')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('teklif_no')
                                            ->label('Teklif no')
                                            ->placeholder('Otomatik üretilecek')
                                            ->maxLength(64),
                                        ...($kalemDetaylariGoster ? [
                                        Select::make('teklif_baski_sablonu_id')
                                            ->label('Ön izleme şablonu')
                                            ->getSearchResultsUsing(fn (string $search, Get $get): array => static::teklifSablonAramaSonuclari($search, (int) ($get('firma_id') ?: static::aktifFirmaId())))
                                            ->getOptionLabelUsing(fn ($value): ?string => static::teklifSablonEtiketiById((int) $value))
                                            ->searchable()
                                            ->native(false),
                                        ] : []),
                                        DatePicker::make('tarih')
                                            ->label('Teklif tarihi')
                                            ->default(now())
                                            ->required(),
                                        ...($kalemDetaylariGoster ? [
                                        DatePicker::make('gecerlilik_tarihi')
                                            ->label('Geçerlilik tarihi')
                                            ->default(now()->addDays(15)),
                                        Select::make('para_birimi')
                                            ->label('Para birimi')
                                            ->options(static::paraBirimiSecenekleri())
                                            ->default('TRY')
                                            ->searchable(fn (): bool => count(static::paraBirimiSecenekleri()) > 8)
                                            ->afterStateHydrated(function ($state, Get $get, Set $set): void {
                                                $hedefParaBirimi = static::normalizeParaBirimiKodu((string) ($state ?: 'TRY'));
                                                $set('para_birimi', $hedefParaBirimi);
                                                $set('para_birimi_onceki', $hedefParaBirimi);

                                                if (! (bool) $get('kur_init_yapildi')) {
                                                    $kalemler = static::hamKalemleriGetir($get);
                                                    if (($get('kur_seti') ?? null) === null && ! static::kalemlerdeSeciliStokVarMi($kalemler)) {
                                                        static::kurDurumunuYerelHazirla($set, $hedefParaBirimi);
                                                    } else {
                                                        static::kurDurumunuHazirla($get, $set, $hedefParaBirimi, 'ilk_yukleme');
                                                    }
                                                    $set('kur_init_yapildi', true);
                                                }
                                            })
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                                $hedefParaBirimi = static::normalizeParaBirimiKodu((string) ($state ?: 'TRY'));
                                                $oncekiParaBirimi = static::normalizeParaBirimiKodu((string) ($get('para_birimi_onceki') ?: $hedefParaBirimi));

                                                $set('para_birimi', $hedefParaBirimi);
                                                static::kurDurumunuHazirla(
                                                    $get,
                                                    $set,
                                                    $hedefParaBirimi,
                                                    $oncekiParaBirimi === $hedefParaBirimi ? 'ilk_yukleme' : 'para_birimi_degisti'
                                                );
                                                $set('para_birimi_onceki', $hedefParaBirimi);
                                            })
                                            ->suffixAction(
                                                Action::make('kurlari_yenile')
                                                    ->label('Kurları yenile')
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(function (Get $get, Set $set): void {
                                                        static::kurDurumunuHazirla(
                                                            $get,
                                                            $set,
                                                            static::normalizeParaBirimiKodu((string) ($get('para_birimi') ?: 'TRY')),
                                                            'kur_yenile'
                                                        );
                                                    })
                                            )
                                            ->required(),
                                        Select::make('durum')
                                            ->options(Teklif::DURUMLAR)
                                            ->default('taslak')
                                            ->required(),
                                        TextInput::make('revizyon_no')
                                            ->label('Revizyon no')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required(),
                                        TextInput::make('teslim_suresi')
                                            ->label('Teslim süresi')
                                            ->placeholder('Örn. 7 iş günü'),
                                        ] : []),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3,
                                        '2xl' => 4,
                                    ])
                                    ->columnSpan(['default' => 12, 'xl' => 12]),
                            ]),
                    ])
                    ->columnSpanFull(),

                ...($kalemDetaylariGoster ? [
                Section::make('Kur bilgisi')
                    ->extraAttributes(['class' => 'teklif-editor-card teklif-kur-card'])
                    ->schema([
                        Placeholder::make('kur_bilgi_kutusu')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->content(fn (Get $get): HtmlString => static::kurBilgiKutusu($get)),
                    ])
                    ->visible(fn (Get $get): bool => static::normalizeParaBirimiKodu((string) ($get('para_birimi') ?: 'TRY')) !== 'TRY' || filled($get('kur_hata_mesaji')))
                    ->columnSpanFull(),
                ] : []),

                ...($kalemDetaylariGoster ? [
                Section::make('Kalemler')
                    ->extraAttributes(['class' => 'teklif-editor-card teklif-line-card'])
                    ->schema([
                        Placeholder::make('kalemler_basliklari')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->content(new HtmlString('
                                <div class="teklif-line-head">
                                    <span>Sıra No</span>
                                    <span>Stoklar</span>
                                    <span>Birim</span>
                                    <span>Miktar</span>
                                    <span>Birim Fiyat</span>
                                    <span>İsk. %</span>
                                    <span>KDV %</span>
                                </div>
                            ')),
                        static::kalemlerAlani(),
                    ])
                    ->columnSpanFull(),

                Section::make('Canlı özet')
                    ->extraAttributes(['class' => 'teklif-summary-card teklif-summary-horizontal'])
                    ->schema(static::ozetAlanlari())
                    ->columns(['default' => 2, 'md' => 3, 'xl' => 6])
                    ->columnSpanFull(),
                ] : []),

                ...($kalemDetaylariGoster ? [
                Section::make('Notlar ve şartlar')
                    ->extraAttributes(['class' => 'teklif-editor-card teklif-notes-card'])
                    ->schema([
                        Textarea::make('aciklama')
                            ->label('Açıklama')
                            ->rows(1)
                            ->autosize()
                            ->placeholder('Teklif kapsamı, müşteri beklentileri veya genel açıklamalar'),
                        Textarea::make('notlar')
                            ->label('İç notlar')
                            ->rows(1)
                            ->autosize()
                            ->placeholder('Ekip için iç notlar'),
                        Textarea::make('kosullar')
                            ->label('Şartlar')
                            ->rows(1)
                            ->autosize()
                            ->placeholder('Garanti, kapsam dışı hizmetler, revizyon koşulları'),
                        Textarea::make('odeme_plani')
                            ->label('Ödeme planı')
                            ->rows(1)
                            ->autosize()
                            ->placeholder('Örn. %50 peşin, %50 teslimde'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                ] : []),
            ]);
    }

    public static function kalemDetaylariGoster(): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select([
                    'id',
                    'firma_id',
                    'cari_id',
                    'teklif_no',
                    'baslik',
                    'durum',
                    'tarih',
                    'gecerlilik_tarihi',
                    'genel_toplam',
                    'para_birimi',
                ])
                ->with(['cari:id,ad'])
                ->latest('id'))
            ->columns([
                TextColumn::make('teklif_no')
                    ->label('Teklif no')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('baslik')
                    ->label('Başlık')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('durum')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Teklif::DURUMLAR[$state ?? ''] ?? (string) $state)
                    ->color(fn (?string $state): string => static::durumRengi($state)),
                TextColumn::make('cari.ad')
                    ->label('Cari')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('tarih')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('gecerlilik_tarihi')
                    ->label('Geçerlilik')
                    ->date('d.m.Y')
                    ->toggleable(),
                TextColumn::make('genel_toplam')
                    ->label('Genel toplam')
                    ->state(fn (Teklif $record): string => static::formatMoney($record->genel_toplam, $record->para_birimi ?: 'TRY')),
            ])
            ->filters([
                SelectFilter::make('durum')
                    ->options(Teklif::DURUMLAR),
                SelectFilter::make('para_birimi')
                    ->options(static::paraBirimiSecenekleri()),
            ])
            ->actions([
                \Filament\Tables\Actions\ViewAction::make(),
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeklifler::route('/'),
            'create' => CreateTeklif::route('/create'),
            'view' => ViewTeklif::route('/{record}'),
            'edit' => EditTeklif::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'firma_id',
                    'cari_id',
                    'teklif_baski_sablonu_id',
                    'baslik',
                    'teklif_no',
                    'tarih',
                    'gecerlilik_tarihi',
                    'para_birimi',
                    'durum',
                    'revizyon_no',
                    'teslim_suresi',
                    'aciklama',
                    'notlar',
                    'kosullar',
                    'odeme_plani',
                    'kur_seti',
                    'kur_seti_alindi_at',
                    'kur_seti_kaynagi',
                    'kur_seti_kur_tipi',
                ])
                ->whereKey($key)
                ->first();
        }

        if (static::hizliGorunumModu()) {
            return static::getModel()::query()
                ->select([
                    'id',
                    'firma_id',
                    'baslik',
                    'teklif_no',
                    'tarih',
                    'durum',
                    'genel_toplam',
                    'para_birimi',
                ])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return false;
    }

    public static function hizliGorunumModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.view')
            && request()->boolean('hizli');
    }

    public static function teklifVerisiniHazirla(array $data, int $firmaId, bool $teklifNoUret = true): array
    {
        unset($data['kur_hata_mesaji'], $data['kur_init_yapildi'], $data['para_birimi_onceki']);

        if (! empty($data['cari_id']) && ! static::cariKaydiGecerliMi($firmaId, (int) $data['cari_id'])) {
            throw ValidationException::withMessages(['cari_id' => 'Seçilen cari aktif firmaya ait değil.']);
        }

        if (! empty($data['teklif_baski_sablonu_id']) && ! static::teklifSablonuGecerliMi($firmaId, (int) $data['teklif_baski_sablonu_id'])) {
            throw ValidationException::withMessages(['teklif_baski_sablonu_id' => 'Seçilen ön izleme şablonu aktif firmaya ait değil.']);
        }

        $data['firma_id'] = $firmaId;
        $data['para_birimi'] = static::normalizeParaBirimiKodu((string) ($data['para_birimi'] ?? 'TRY'));
        $data['durum'] = (string) ($data['durum'] ?? 'taslak');
        if (! array_key_exists($data['durum'], Teklif::DURUMLAR)) {
            throw ValidationException::withMessages(['durum' => 'Gecersiz teklif durumu.']);
        }

        $data['tarih'] = $data['tarih'] ?? now();
        $data['teklif_baski_sablonu_id'] = filled($data['teklif_baski_sablonu_id'] ?? null)
            ? (int) $data['teklif_baski_sablonu_id']
            : static::varsayilanTeklifSablonuId($firmaId);
        $data['teklif_no'] = filled($data['teklif_no'] ?? null)
            ? (string) $data['teklif_no']
            : ($teklifNoUret ? static::sonrakiTeklifNo($firmaId, $data['tarih']) : null);
        $data['kur_seti'] = static::kurSetiniMetneCevir($data['kur_seti'] ?? null);
        $data['kur_seti_alindi_at'] = filled($data['kur_seti_alindi_at'] ?? null) ? $data['kur_seti_alindi_at'] : null;
        $data['kur_seti_kaynagi'] = filled($data['kur_seti_kaynagi'] ?? null) ? (string) $data['kur_seti_kaynagi'] : null;
        $data['kur_seti_kur_tipi'] = filled($data['kur_seti_kur_tipi'] ?? null) ? (string) $data['kur_seti_kur_tipi'] : null;

        if (array_key_exists('kalemler', $data)) {
            $kalemler = [];
            foreach (array_values($data['kalemler'] ?? []) as $index => $kalem) {
                unset($kalem['is_internal_update']);

                $hazirKalem = static::kalemHesapla($kalem, $data['para_birimi']);
                $hazirKalem['firma_id'] = $firmaId;
                $hazirKalem['satir_no'] = $index + 1;

                if (! empty($hazirKalem['stok_id']) && ! static::stokKaydiGecerliMi($firmaId, (int) $hazirKalem['stok_id'])) {
                    throw ValidationException::withMessages(["kalemler.{$index}.stok_id" => 'Seçilen stok kartı aktif firmaya ait değil.']);
                }

                $kalemler[] = $hazirKalem;
            }

            $ozet = static::toplamlariHesapla($kalemler);

            $data['kalemler'] = $kalemler;
            $data['ara_toplam'] = $ozet['ara_toplam'];
            $data['toplam_indirim'] = $ozet['toplam_indirim'];
            $data['kdv_toplam'] = $ozet['kdv_toplam'];
            $data['genel_toplam'] = $ozet['genel_toplam'];
        }

        return $data;
    }

    public static function sonrakiTeklifNo(int $firmaId, mixed $tarih = null): string
    {
        return app(TeklifNumaraServisi::class)->benzersizUret($firmaId, $tarih);
    }

    public static function aktifFirmaId(): int
    {
        return (int) app(TenantContextService::class)->aktifFirmaId();
    }

    protected static function gecerliFirmaId(): int
    {
        if (static::$gecerliFirmaIdCache !== null) {
            return static::$gecerliFirmaIdCache;
        }

        $firmaId = static::aktifFirmaId();

        if ($firmaId > 0) {
            return static::$gecerliFirmaIdCache = $firmaId;
        }

        return static::$gecerliFirmaIdCache = (int) \App\Models\Firma::query()->orderBy('id')->value('id');
    }

    protected static function formFirmaId(Get $get): int
    {
        foreach (['firma_id', '../firma_id', '../../firma_id'] as $path) {
            try {
                $firmaId = (int) ($get($path) ?: 0);
            } catch (\Throwable) {
                $firmaId = 0;
            }

            if ($firmaId > 0) {
                return $firmaId;
            }
        }

        return static::gecerliFirmaId();
    }

    protected static function stokAramaFirmaId(Get $get): ?int
    {
        foreach (['firma_id', '../firma_id', '../../firma_id'] as $path) {
            try {
                $firmaId = (int) ($get($path) ?: 0);
            } catch (\Throwable) {
                $firmaId = 0;
            }

            if ($firmaId > 0) {
                return $firmaId;
            }
        }

        $aktifFirmaId = static::aktifFirmaId();

        return $aktifFirmaId > 0 ? $aktifFirmaId : null;
    }

    /**
     * @return array<string, string>
     */
    public static function paraBirimiSecenekleri(): array
    {
        if (array_key_exists('para_birimi|aktif', static::$secenekCache)) {
            /** @var array<string, string> $cached */
            $cached = static::$secenekCache['para_birimi|aktif'];

            return $cached;
        }

        $kaliciCacheKey = 'teklif_para_birimi_secenekleri|aktif';
        $kaliciCache = Cache::get($kaliciCacheKey);
        if (is_array($kaliciCache)) {
            return static::$secenekCache['para_birimi|aktif'] = $kaliciCache;
        }

        $varsayilanlar = [
            'TRY' => 'TRY',
            'USD' => 'USD',
            'EUR' => 'EUR',
        ];

        $dinamikler = ParaBirimi::query()
            ->where('aktif_mi', true)
            ->orderBy('kod')
            ->pluck('kod', 'kod')
            ->all();

        $secenekler = collect($dinamikler + $varsayilanlar)
            ->mapWithKeys(fn ($value, $key): array => [static::normalizeParaBirimiKodu((string) $key) => static::normalizeParaBirimiKodu((string) $value)])
            ->all();

        Cache::put($kaliciCacheKey, $secenekler, now()->addMinutes(30));

        return static::$secenekCache['para_birimi|aktif'] = $secenekler;
    }

    public static function formatMoney(mixed $amount, string $currency = 'TRY'): string
    {
        return number_format((float) $amount, 2, ',', '.').' '.$currency;
    }

    /**
     * @param  array<string, mixed>  $kalem
     * @return array<string, mixed>
     */
    public static function kalemHesapla(array $kalem, string $paraBirimi = 'TRY'): array
    {
        $miktar = round((float) ($kalem['miktar'] ?? 1), 4);
        $birimFiyat = round((float) ($kalem['birim_fiyat'] ?? 0), 8);
        $indirimOrani = max(0, round((float) ($kalem['indirim_orani'] ?? 0), 2));
        $kdvOrani = max(0, round((float) ($kalem['kdv_orani'] ?? 0), 2));

        $araToplam = round($miktar * $birimFiyat, 8);
        $indirimTutari = round($araToplam * ($indirimOrani / 100), 8);
        $netTutar = round($araToplam - $indirimTutari, 8);
        $kdvTutari = round($netTutar * ($kdvOrani / 100), 8);
        $toplam = round($netTutar + $kdvTutari, 8);

        return [
            'stok_id' => filled($kalem['stok_id'] ?? null) ? (int) $kalem['stok_id'] : null,
            'kalem_tipi' => (string) ($kalem['kalem_tipi'] ?? 'stok_kalemi'),
            'hizmet_mi' => (bool) ($kalem['hizmet_mi'] ?? false),
            'aciklama' => (string) ($kalem['aciklama'] ?? ''),
            'birim' => (string) ($kalem['birim'] ?? 'AD'),
            'miktar' => $miktar,
            'birim_fiyat' => $birimFiyat,
            'indirim_orani' => $indirimOrani,
            'kdv_orani' => $kdvOrani,
            'net_tutar' => round($netTutar, 2),
            'kdv_tutari' => round($kdvTutari, 2),
            'toplam' => round($toplam, 2),
            'para_birimi' => $paraBirimi,
            'kaynak_para_birimi' => filled($kalem['kaynak_para_birimi'] ?? null) ? strtoupper((string) $kalem['kaynak_para_birimi']) : null,
            'kaynak_birim_fiyat' => round((float) ($kalem['kaynak_birim_fiyat'] ?? 0), 8),
            'ozel_fiyat_mi' => (bool) ($kalem['ozel_fiyat_mi'] ?? false),
            'fiyat_uyari' => filled($kalem['fiyat_uyari'] ?? null) ? (string) $kalem['fiyat_uyari'] : null,
            'kaynak_verisi' => static::kaynakVerisiniMetneCevir($kalem['kaynak_verisi'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $kalemler
     * @return array{ara_toplam: float, toplam_indirim: float, kdv_toplam: float, genel_toplam: float}
     */
    public static function toplamlariHesapla(array $kalemler): array
    {
        $araToplam = 0.0;
        $toplamIndirim = 0.0;
        $kdvToplam = 0.0;
        $genelToplam = 0.0;

        foreach ($kalemler as $kalem) {
            $miktar = (float) ($kalem['miktar'] ?? 0);
            $birimFiyat = (float) ($kalem['birim_fiyat'] ?? 0);
            $satirAraToplam = round($miktar * $birimFiyat, 2);

            $araToplam += $satirAraToplam;
            $toplamIndirim += round($satirAraToplam - (float) ($kalem['net_tutar'] ?? 0), 2);
            $kdvToplam += (float) ($kalem['kdv_tutari'] ?? 0);
            $genelToplam += (float) ($kalem['toplam'] ?? 0);
        }

        return [
            'ara_toplam' => round($araToplam, 2),
            'toplam_indirim' => round($toplamIndirim, 2),
            'kdv_toplam' => round($kdvToplam, 2),
            'genel_toplam' => round($genelToplam, 2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function ozetAlanlari(): array
    {
        $ozet = function (Get $get): array {
            static $cache = [];

            $kalemler = static::hamKalemleriGetir($get);
            $cacheKey = md5(json_encode($kalemler, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

            return $cache[$cacheKey] ??= static::toplamlariHesapla($kalemler);
        };

        return [
            Placeholder::make('ozet_kartlari')
                ->hiddenLabel()
                ->dehydrated(false)
                ->columnSpanFull()
                ->content(function (Get $get) use ($ozet): HtmlString {
                    $paraBirimi = (string) ($get('para_birimi') ?? 'TRY');
                    $toplamlar = $ozet($get);
                    $degerler = [
                        'Durum' => Teklif::DURUMLAR[(string) ($get('durum') ?? 'taslak')] ?? 'Taslak',
                        'Geçerlilik' => filled($get('gecerlilik_tarihi')) ? Carbon::parse((string) $get('gecerlilik_tarihi'))->format('d.m.Y') : 'Belirlenmedi',
                        'Ara toplam' => static::formatMoney($toplamlar['ara_toplam'], $paraBirimi),
                        'Toplam indirim' => static::formatMoney($toplamlar['toplam_indirim'], $paraBirimi),
                        'KDV toplam' => static::formatMoney($toplamlar['kdv_toplam'], $paraBirimi),
                        'Genel toplam' => '<span class="teklif-grand-total">'.e(static::formatMoney($toplamlar['genel_toplam'], $paraBirimi)).'</span>',
                    ];

                    $html = collect($degerler)
                        ->map(fn (string $deger, string $baslik): string => '<div class="teklif-summary-item"><span>'.e($baslik).'</span><strong>'.$deger.'</strong></div>')
                        ->implode('');

                    return new HtmlString('<div class="teklif-summary-grid">'.$html.'</div>');
                }),
        ];
    }

    protected static function kalemlerAlani(): Repeater
    {
        return Repeater::make('kalemler')
            ->hiddenLabel()
            ->relationship()
            ->defaultItems(0)
            ->reorderable(false)
            ->collapsible(false)
            ->cloneable(false)
            ->extraAttributes(['class' => 'teklif-line-repeater'])
            ->extraItemActions([
                Action::make('guncelle_kalem')
                    ->label('Güncelle')
                    ->icon('heroicon-m-arrow-path')
                    ->button()
                    ->color('gray')
                    ->action(function (array $arguments, Repeater $component): void {
                        $component->callAfterStateUpdated();
                    }),
            ])
            ->schema([
                Placeholder::make('satir_no_gosterge')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->content(new HtmlString(''))
                    ->extraAttributes(['class' => 'teklif-line-index'])
                    ->columnSpan(1),
                Select::make('stok_id')
                    ->label('Stok / hizmet')
                    ->hiddenLabel()
                    ->placeholder('Stok / hizmet')
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::stokAramaSonuclari($search, static::stokAramaFirmaId($get)))
                    ->getOptionLabelUsing(fn ($value, Get $get): ?string => static::stokSecenekEtiketiById((int) $value, static::stokAramaFirmaId($get)))
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        if (! $state) {
                            return;
                        }

                        try {
                            $satir = static::stoktanKalemBilgisiHazirla(
                                (int) $state,
                                strtoupper((string) ($get('../../para_birimi') ?: 'TRY')),
                                static::kurSetiniCoz($get('../../kur_seti'))
                            );
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Stok kalemi eklenemedi')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            $set('stok_id', null);

                            return;
                        }

                        $set('aciklama', $satir['aciklama']);
                        $set('birim', $satir['birim']);
                        $set('birim_fiyat', $satir['birim_fiyat']);
                        $set('kdv_orani', 0);
                        $set('kaynak_para_birimi', $satir['kaynak_para_birimi']);
                        $set('kaynak_birim_fiyat', $satir['kaynak_birim_fiyat']);
                        $set('ozel_fiyat_mi', false);
                        $set('fiyat_uyari', $satir['fiyat_uyari']);
                        $set('kaynak_verisi', $satir['kaynak_verisi']);
                        $set('is_internal_update', true);
                    })
                    ->columnSpan(11),
                TextInput::make('aciklama')
                    ->hidden()
                    ->dehydrated(),
                Hidden::make('kaynak_para_birimi'),
                Hidden::make('kaynak_birim_fiyat'),
                Hidden::make('ozel_fiyat_mi')->default(false),
                Hidden::make('fiyat_uyari'),
                Hidden::make('kaynak_verisi'),
                Hidden::make('is_internal_update')->dehydrated(false),
                TextInput::make('birim')
                    ->label('Birim')
                    ->default('AD')
                    ->maxLength(32)
                    ->columnSpan(2),
                TextInput::make('miktar')
                    ->label('Miktar')
                    ->numeric()
                    ->integer()
                    ->default(1)
                    ->minValue(1)
                    ->step(1)
                    ->required()
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? (string) ((int) round((float) $state)) : null)
                    ->columnSpan(2),
                TextInput::make('birim_fiyat')
                    ->label('Birim fiyat')
                    ->numeric()
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        if ((bool) ($get('is_internal_update') ?? false)) {
                            $set('is_internal_update', false);

                            return;
                        }

                        $hedefParaBirimi = strtoupper((string) ($get('../../para_birimi') ?: 'TRY'));
                        $manuelFiyat = round((float) ($state ?? 0), 8);

                        $set('ozel_fiyat_mi', true);
                        $set('kaynak_para_birimi', $hedefParaBirimi);
                        $set('kaynak_birim_fiyat', $manuelFiyat);
                        $set('fiyat_uyari', null);
                        $set('kaynak_verisi', static::kaynakVerisiniMetneCevir([
                            'mod' => 'ozel_fiyat',
                            'kaynak_para_birimi' => $hedefParaBirimi,
                            'kaynak_birim_fiyat' => $manuelFiyat,
                        ]));
                    })
                    ->suffixAction(
                        Action::make('satir_fiyatini_sifirla')
                            ->label('Sıfırla')
                            ->icon('heroicon-m-arrow-uturn-left')
                            ->color('gray')
                            ->visible(fn (Get $get): bool => (bool) ($get('ozel_fiyat_mi') ?? false) && filled($get('stok_id')))
                            ->action(function (Get $get, Set $set): void {
                                $satir = static::stoktanKalemBilgisiHazirla(
                                    (int) $get('stok_id'),
                                    strtoupper((string) ($get('../../para_birimi') ?: 'TRY')),
                                    static::kurSetiniCoz($get('../../kur_seti'))
                                );

                                $set('birim_fiyat', $satir['birim_fiyat']);
                                $set('kaynak_para_birimi', $satir['kaynak_para_birimi']);
                                $set('kaynak_birim_fiyat', $satir['kaynak_birim_fiyat']);
                                $set('ozel_fiyat_mi', false);
                                $set('fiyat_uyari', $satir['fiyat_uyari']);
                                $set('kaynak_verisi', $satir['kaynak_verisi']);
                                $set('is_internal_update', true);
                            })
                    )
                    ->required()
                    ->columnSpan(4),
                TextInput::make('indirim_orani')
                    ->label('İsk. %')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->minValue(0)
                    ->step(1)
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? (string) ((int) round((float) $state)) : null)
                    ->columnSpan(2),
                TextInput::make('kdv_orani')
                    ->label('KDV %')
                    ->numeric()
                    ->default(0)
                    ->columnSpan(2),
                Placeholder::make('satir_ozeti')
                    ->label('Satır özeti')
                    ->visible(fn (Get $get): bool => filled($get('stok_id')) || (float) ($get('birim_fiyat') ?? 0) > 0)
                    ->content(function (Get $get): HtmlString {
                        $kalem = static::kalemHesapla([
                            'stok_id' => $get('stok_id'),
                            'kalem_tipi' => $get('kalem_tipi'),
                            'hizmet_mi' => $get('hizmet_mi'),
                            'aciklama' => $get('aciklama'),
                            'birim' => $get('birim'),
                            'miktar' => $get('miktar'),
                            'birim_fiyat' => $get('birim_fiyat'),
                            'indirim_orani' => $get('indirim_orani'),
                            'kdv_orani' => $get('kdv_orani'),
                            'ozel_fiyat_mi' => $get('ozel_fiyat_mi'),
                        ], (string) ($get('../../para_birimi') ?? 'TRY'));

                        $indirimTutari = round((($kalem['miktar'] ?? 0) * ($kalem['birim_fiyat'] ?? 0)) - ($kalem['net_tutar'] ?? 0), 2);

                        return new HtmlString(
                            '<div class="teklif-line-summary">'.
                            ((bool) ($get('ozel_fiyat_mi') ?? false) ? '<span class="teklif-line-badge">Özel Fiyat</span>' : '').
                            '<span>Net: '.e(static::formatMoney($kalem['net_tutar'], (string) ($kalem['para_birimi'] ?? 'TRY'))).'</span>'.
                            '<span>İskonto: '.e(static::formatMoney($indirimTutari, (string) ($kalem['para_birimi'] ?? 'TRY'))).'</span>'.
                            '<span>KDV: '.e(static::formatMoney($kalem['kdv_tutari'], (string) ($kalem['para_birimi'] ?? 'TRY'))).'</span>'.
                            '<strong>Toplam: '.e(static::formatMoney($kalem['toplam'], (string) ($kalem['para_birimi'] ?? 'TRY'))).'</strong>'.
                            '</div>'
                        );
                    })
                    ->columnSpanFull(),
                Placeholder::make('satir_fiyat_uyarisi')
                    ->hiddenLabel()
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => filled($get('fiyat_uyari')))
                    ->content(fn (Get $get): HtmlString => new HtmlString(filled($get('fiyat_uyari')) ? '<div class="teklif-line-warning">'.e((string) $get('fiyat_uyari')).'</div>' : ''))
                    ->columnSpanFull(),
            ])
            ->columns(24)
            ->columnSpanFull()
            ->addActionLabel('Kalem ekle')
            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                unset($data['is_internal_update']);

                $kalem = static::kalemHesapla($data, (string) ($get('../../para_birimi') ?? 'TRY'));
                $kalem['firma_id'] = static::formFirmaId($get);

                return $kalem;
            })
            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): array {
                unset($data['is_internal_update']);

                $kalem = static::kalemHesapla($data, (string) ($get('../../para_birimi') ?? 'TRY'));
                $kalem['firma_id'] = static::formFirmaId($get);

                return $kalem;
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function hamKalemleriGetir(Get $get): array
    {
        $kalemler = $get('kalemler');

        return is_array($kalemler) ? array_values($kalemler) : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $kalemler
     */
    protected static function kalemlerdeSeciliStokVarMi(array $kalemler): bool
    {
        foreach ($kalemler as $kalem) {
            if (filled($kalem['stok_id'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected static function cariSecimSorgusu(Builder $query): Builder
    {
        $query = $query->withoutGlobalScopes()->orderBy('ad');
        $firmaId = static::aktifFirmaId();

        if ($firmaId > 0) {
            $query->where('firma_id', $firmaId);
        }

        return $query;
    }

    protected static function cariSecenekleri(): array
    {
        $cacheKey = 'cari|'.static::aktifFirmaId();

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        return static::$secenekCache[$cacheKey] = static::cariSecimSorgusu(Cari::query())
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function cariAramaSonuclari(string $search): array
    {
        $arama = trim($search);
        $firmaId = static::aktifFirmaId();
        $limit = $arama === '' ? 20 : 50;
        $cacheKey = 'cari_arama|'.$firmaId.'|'.mb_strtolower($arama, 'UTF-8').'|'.$limit;

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        if ($arama === '') {
            $kaliciCache = Cache::get('teklif_'.$cacheKey);
            if (is_array($kaliciCache)) {
                return static::$secenekCache[$cacheKey] = $kaliciCache;
            }
        }

        $sonuclar = static::cariSecimSorgusu(Cari::query())
            ->when($arama !== '', function (Builder $query) use ($arama): void {
                $query->where(function (Builder $query) use ($arama): void {
                    $query
                        ->where('ad', 'like', '%'.$arama.'%')
                        ->orWhere('telefon', 'like', '%'.$arama.'%')
                        ->orWhere('gsm', 'like', '%'.$arama.'%');
                });
            })
            ->limit($limit)
            ->pluck('ad', 'id')
            ->all();

        if ($arama === '') {
            Cache::put('teklif_'.$cacheKey, $sonuclar, now()->addMinutes(10));
        }

        return static::$secenekCache[$cacheKey] = $sonuclar;
    }

    /**
     * @return array<int, string>
     */
    protected static function stokSecenekleri(int $firmaId): array
    {
        $cacheKey = 'stok|'.$firmaId;

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        return static::$secenekCache[$cacheKey] = static::stokSecimSorgusu(StokKarti::query(), $firmaId)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [(int) $stok->getKey() => static::stokSecenekEtiketi($stok)])
            ->all();
    }

    protected static function stokSecimSorgusu(Builder $query, ?int $firmaId = null, bool $firmaFallback = true): Builder
    {
        $query = $query
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('ad');
        $firmaId = $firmaId ?: ($firmaFallback ? static::gecerliFirmaId() : 0);

        if ($firmaId > 0) {
            $query->where('firma_id', $firmaId);
        }

        return $query;
    }

    protected static function stokSecenekEtiketi(StokKarti $stok): string
    {
        $kod = trim((string) ($stok->kod ?? ''));
        $ad = trim((string) ($stok->ad ?? ''));

        return $kod !== '' ? $kod.' - '.$ad : $ad;
    }

    protected static function stokAramaSonuclari(string $search, ?int $firmaId): array
    {
        $arama = trim($search);

        if ($arama === '') {
            return static::stokSecimSorgusu(StokKarti::query(), $firmaId, false)
                ->limit(50)
                ->get(['id', 'kod', 'ad'])
                ->mapWithKeys(fn (StokKarti $stok): array => [(string) $stok->getKey() => static::stokSecenekEtiketi($stok)])
                ->all();
        }

        return static::stokSecimSorgusu(StokKarti::query(), $firmaId, false)
            ->where(function (Builder $query) use ($arama): void {
                $query
                    ->where('ad', 'like', '%'.$arama.'%')
                    ->orWhere('kod', 'like', '%'.$arama.'%');
            })
            ->limit(50)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [(string) $stok->getKey() => static::stokSecenekEtiketi($stok)])
            ->all();
    }

    protected static function stokSecenekEtiketiById(int $stokId, ?int $firmaId = null): ?string
    {
        if ($stokId < 1) {
            return null;
        }

        $stok = static::stokSecimSorgusu(StokKarti::query(), $firmaId, false)
            ->whereKey($stokId)
            ->first(['id', 'kod', 'ad']);

        return $stok instanceof StokKarti
            ? static::stokSecenekEtiketi($stok)
            : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function teklifSablonSecenekleri(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        $cacheKey = 'teklif_sablon|'.$firmaId;

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        return static::$secenekCache[$cacheKey] = TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function teklifSablonAramaSonuclari(string $search, int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        $search = trim($search);
        $cacheKey = 'teklif_sablon_arama|'.$firmaId.'|'.mb_strtolower($search, 'UTF-8');

        if (array_key_exists($cacheKey, static::$secenekCache)) {
            return static::$secenekCache[$cacheKey];
        }

        if ($search === '') {
            $kaliciCache = Cache::get($cacheKey);
            if (is_array($kaliciCache)) {
                return static::$secenekCache[$cacheKey] = $kaliciCache;
            }
        }

        $sonuclar = TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->when($search !== '', fn (Builder $query) => $query->where('ad', 'like', '%'.$search.'%'))
            ->orderByDesc('varsayilan_mi')
            ->orderBy('ad')
            ->limit(20)
            ->pluck('ad', 'id')
            ->all();

        if ($search === '') {
            Cache::put($cacheKey, $sonuclar, now()->addMinutes(10));
        }

        return static::$secenekCache[$cacheKey] = $sonuclar;
    }

    protected static function teklifSablonEtiketiById(int $sablonId): ?string
    {
        if ($sablonId < 1) {
            return null;
        }

        return TeklifBaskiSablonu::query()
            ->whereKey($sablonId)
            ->value('ad');
    }

    public static function varsayilanTeklifSablonuId(int $firmaId): ?int
    {
        if ($firmaId < 1) {
            return null;
        }

        return app(TeklifBaskiSablonuServisi::class)
            ->varsayilanSablonId($firmaId);
    }

    protected static function cariKaydiGecerliMi(int $firmaId, int $cariId): bool
    {
        return Cari::tenantScopeOlmadan(fn () => Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->exists());
    }

    protected static function stokKaydiGecerliMi(int $firmaId, int $stokId): bool
    {
        return StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereKey($stokId)
            ->exists());
    }

    protected static function teklifSablonuGecerliMi(int $firmaId, int $sablonId): bool
    {
        return TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->whereKey($sablonId)
            ->exists();
    }

    public static function durumRengi(?string $durum): string
    {
        return match ($durum) {
            'onaylandi' => 'success',
            'reddedildi', 'suresi_doldu' => 'danger',
            'gonderildi', 'revizyon_bekliyor' => 'warning',
            default => 'gray',
        };
    }

    protected static function kurDurumunuHazirla(Get $get, Set $set, string $hedefParaBirimi, string $mod): void
    {
        try {
            $kurSeti = static::kurSetiOlustur(static::normalizeParaBirimiKodu($hedefParaBirimi), (int) ($get('firma_id') ?: static::aktifFirmaId()));

            $set('kur_seti', json_encode($kurSeti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $set('kur_seti_alindi_at', $kurSeti['alindi_at'] ?? now()->toDateTimeString());
            $set('kur_seti_kaynagi', $kurSeti['kaynak'] ?? 'TCMB');
            $set('kur_seti_kur_tipi', $kurSeti['kur_tipi'] ?? static::varsayilanKurTipiEtiketi());
            $set('kur_hata_mesaji', null);
            $set('kalemler', static::kalemleriGuncelle(static::hamKalemleriGetir($get), $hedefParaBirimi, $kurSeti, $mod));
        } catch (\Throwable $exception) {
            $set('kur_seti', null);
            $set('kur_seti_alindi_at', null);
            $set('kur_seti_kaynagi', null);
            $set('kur_seti_kur_tipi', null);
            $set('kur_hata_mesaji', 'Kur bilgileri alınamadı. Fiyatlar 0 olarak gösteriliyor. Kurları yenileyerek tekrar deneyebilirsiniz.');
            $set('kalemler', static::kalemleriSifirla(static::hamKalemleriGetir($get), $hedefParaBirimi));

            Notification::make()
                ->title('Kur bilgileri alınamadı')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function kurDurumunuYerelHazirla(Set $set, string $hedefParaBirimi): void
    {
        $kurSeti = static::yerelKurSetiOlustur($hedefParaBirimi);

        $set('kur_seti', json_encode($kurSeti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $set('kur_seti_alindi_at', $kurSeti['alindi_at']);
        $set('kur_seti_kaynagi', $kurSeti['kaynak']);
        $set('kur_seti_kur_tipi', $kurSeti['kur_tipi']);
        $set('kur_hata_mesaji', null);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function kurDurumunuFormDurumundaYenile(array $state, string $mod = 'kur_yenile'): array
    {
        $hedefParaBirimi = static::normalizeParaBirimiKodu((string) ($state['para_birimi'] ?? 'TRY'));
        $firmaId = (int) ($state['firma_id'] ?? static::aktifFirmaId());

        $state['para_birimi'] = $hedefParaBirimi;
        $state['para_birimi_onceki'] = $hedefParaBirimi;
        $state['kur_init_yapildi'] = true;

        try {
            $kurSeti = static::kurSetiOlustur($hedefParaBirimi, $firmaId);

            $state['kur_seti'] = json_encode($kurSeti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $state['kur_seti_alindi_at'] = $kurSeti['alindi_at'] ?? now()->toDateTimeString();
            $state['kur_seti_kaynagi'] = $kurSeti['kaynak'] ?? 'TCMB';
            $state['kur_seti_kur_tipi'] = $kurSeti['kur_tipi'] ?? static::varsayilanKurTipiEtiketi();
            $state['kur_hata_mesaji'] = null;
            $state['kalemler'] = static::kalemleriGuncelle(
                is_array($state['kalemler'] ?? null) ? array_values($state['kalemler']) : [],
                $hedefParaBirimi,
                $kurSeti,
                $mod
            );

            return $state;
        } catch (\Throwable $exception) {
            $state['kur_seti'] = null;
            $state['kur_seti_alindi_at'] = null;
            $state['kur_seti_kaynagi'] = null;
            $state['kur_seti_kur_tipi'] = null;
            $state['kur_hata_mesaji'] = 'Kur bilgileri alınamadı. Fiyatlar 0 olarak gösteriliyor. Kurları yenileyerek tekrar deneyebilirsiniz.';
            $state['kalemler'] = static::kalemleriSifirla(
                is_array($state['kalemler'] ?? null) ? array_values($state['kalemler']) : [],
                $hedefParaBirimi
            );

            return $state;
        }
    }

    protected static function kurBilgiKutusu(Get $get): HtmlString
    {
        $hataMesaji = (string) ($get('kur_hata_mesaji') ?? '');
        if ($hataMesaji !== '') {
            return new HtmlString('<div class="teklif-kur-note teklif-kur-note--danger">'.e($hataMesaji).'</div>');
        }

        $paraBirimi = static::normalizeParaBirimiKodu((string) ($get('para_birimi') ?: 'TRY'));
        $kurSeti = static::kurSetiniCoz($get('kur_seti'));
        $alindiAt = filled($get('kur_seti_alindi_at')) ? Carbon::parse((string) $get('kur_seti_alindi_at'))->format('d.m.Y H:i') : 'Belirsiz';
        $ozet = is_array($kurSeti['ozet'] ?? null) ? $kurSeti['ozet'] : [];
        $pariteler = is_array($kurSeti['pariteler'] ?? null) ? $kurSeti['pariteler'] : [];
        $surekliKodlar = ['TRY', 'USD', 'EUR'];
        $kurBilgileri = [
            'Teklif para birimi' => $paraBirimi,
            'Kur zamanı' => $alindiAt,
            'Kur tipi' => (string) ($ozet['kur_tipi'] ?? $get('kur_seti_kur_tipi') ?? static::varsayilanKurTipiEtiketi()),
            'Kur tutarı' => $paraBirimi === 'TRY' ? '-' : (string) ($ozet['kur'] ?? '-'),
            'Kaynak' => (string) ($ozet['kaynak'] ?? $get('kur_seti_kaynagi') ?? 'TCMB'),
        ];

        foreach ($surekliKodlar as $kod) {
            $kurDegeri = $kod === $paraBirimi
                ? '1.00000000'
                : (string) ($pariteler[$kod]['kur'] ?? '-');

            $kurBilgileri[$kod] = $kurDegeri;
        }

        return new HtmlString(
            '<div class="teklif-kur-note">'.
            collect($kurBilgileri)
                ->map(fn (string $deger, string $baslik): string => '<span class="teklif-kur-note__item"><strong>'.e($baslik).'</strong><em>'.e($deger).'</em></span>')
                ->implode('').
            '</div>'
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function kurSetiOlustur(string $hedefParaBirimi, int $firmaId): array
    {
        $hedef = static::normalizeParaBirimiKodu($hedefParaBirimi);
        $servis = app(DovizKurServisi::class);
        $pariteler = [];

        foreach (static::aktifParaBirimiKodlari($firmaId) as $kaynakKod) {
            $kaynak = static::normalizeParaBirimiKodu($kaynakKod);
            $sonuc = $servis->otomatikKurGetir($kaynak, $hedef);

            $pariteler[$kaynak] = [
                'kur' => (string) ($sonuc['kur'] ?? '1.00000000'),
                'tarih' => (string) ($sonuc['tarih'] ?? now()->toDateString()),
                'kaynak' => (string) ($sonuc['kaynak'] ?? 'TCMB'),
                'aciklama' => (string) ($sonuc['aciklama'] ?? ''),
            ];
        }

        $ozetKur = null;
        if ($hedef !== 'TRY') {
            $ozetSonuc = $servis->otomatikKurGetir('TRY', $hedef);
            $ozetKur = (string) ($ozetSonuc['kur'] ?? '');
        }

        return [
            'hedef_para_birimi' => $hedef,
            'alindi_at' => now()->toDateTimeString(),
            'kaynak' => 'TCMB',
            'kur_tipi' => static::varsayilanKurTipiEtiketi(),
            'ozet' => [
                'kur' => $ozetKur,
                'kaynak' => 'TCMB',
                'kur_tipi' => static::varsayilanKurTipiEtiketi(),
            ],
            'pariteler' => $pariteler,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function yerelKurSetiOlustur(string $hedefParaBirimi): array
    {
        $hedef = static::normalizeParaBirimiKodu($hedefParaBirimi);

        return [
            'hedef_para_birimi' => $hedef,
            'alindi_at' => now()->toDateTimeString(),
            'kaynak' => 'Sistem',
            'kur_tipi' => static::varsayilanKurTipiEtiketi(),
            'ozet' => [
                'kur' => $hedef === 'TRY' ? null : '',
                'kaynak' => 'Sistem',
                'kur_tipi' => static::varsayilanKurTipiEtiketi(),
            ],
            'pariteler' => [
                $hedef => [
                    'kur' => '1.00000000',
                    'tarih' => now()->toDateString(),
                    'kaynak' => 'Sistem',
                    'aciklama' => 'Ayni para birimi icin kur 1 olarak ayarlandi.',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $kalemler
     * @return array<int, array<string, mixed>>
     */
    protected static function kalemleriGuncelle(array $kalemler, string $hedefParaBirimi, array $kurSeti, string $mod): array
    {
        $hedef = static::normalizeParaBirimiKodu($hedefParaBirimi);

        return array_map(function (array $kalem) use ($hedef, $kurSeti, $mod): array {
            $ozelFiyatMi = (bool) ($kalem['ozel_fiyat_mi'] ?? false);
            $stokId = filled($kalem['stok_id'] ?? null) ? (int) $kalem['stok_id'] : null;

            if ($stokId && ! $ozelFiyatMi) {
                if ($mod === 'para_birimi_degisti') {
                    $donusumKaynakParaBirimi = static::normalizeParaBirimiKodu((string) ($kalem['para_birimi'] ?? $kalem['kaynak_para_birimi'] ?? $hedef));
                    $donusumKaynakBirimFiyat = round((float) ($kalem['birim_fiyat'] ?? 0), 8);
                    $donusturulmusBirimFiyat = static::kurSetiIleDonustur($donusumKaynakBirimFiyat, $donusumKaynakParaBirimi, $hedef, $kurSeti);

                    $kalem['birim_fiyat'] = $donusturulmusBirimFiyat;
                    $kalem['kaynak_para_birimi'] = $hedef;
                    $kalem['kaynak_birim_fiyat'] = $donusturulmusBirimFiyat;
                    $kalem['fiyat_uyari'] = null;
                    $kalem['kaynak_verisi'] = static::kaynakVerisiniMetneCevir([
                        'mod' => 'para_birimi_donusumu',
                        'onceki_para_birimi' => $donusumKaynakParaBirimi,
                        'kaynak_birim_fiyat' => $donusumKaynakBirimFiyat,
                    ]);
                } else {
                    $kalem = array_merge($kalem, static::stoktanKalemBilgisiHazirla($stokId, $hedef, $kurSeti), [
                        'ozel_fiyat_mi' => false,
                    ]);
                }
            } elseif ($ozelFiyatMi) {
                $kaynakParaBirimi = static::normalizeParaBirimiKodu((string) ($kalem['kaynak_para_birimi'] ?: ($kalem['para_birimi'] ?? $hedef)));
                $kaynakBirimFiyat = round((float) ($kalem['kaynak_birim_fiyat'] ?? $kalem['birim_fiyat'] ?? 0), 8);

                if ($mod === 'para_birimi_degisti') {
                    $donusumKaynakParaBirimi = static::normalizeParaBirimiKodu((string) ($kalem['para_birimi'] ?? $kaynakParaBirimi ?: $hedef));
                    $donusumKaynakBirimFiyat = round((float) ($kalem['birim_fiyat'] ?? 0), 8);
                    $donusturulmusBirimFiyat = static::kurSetiIleDonustur($donusumKaynakBirimFiyat, $donusumKaynakParaBirimi, $hedef, $kurSeti);

                    $kalem['birim_fiyat'] = $donusturulmusBirimFiyat;
                    $kaynakParaBirimi = $hedef;
                    $kaynakBirimFiyat = $donusturulmusBirimFiyat;
                } else {
                    $kalem['birim_fiyat'] = round((float) ($kalem['birim_fiyat'] ?? 0), 8);
                }

                $kalem['kaynak_para_birimi'] = $kaynakParaBirimi;
                $kalem['kaynak_birim_fiyat'] = $kaynakBirimFiyat;
                $kalem['fiyat_uyari'] = null;
                $kalem['kaynak_verisi'] = static::kaynakVerisiniMetneCevir([
                    'mod' => 'ozel_fiyat',
                    'kaynak_para_birimi' => $kaynakParaBirimi,
                    'kaynak_birim_fiyat' => $kaynakBirimFiyat,
                ]);
            } elseif (filled($kalem['fiyat_uyari'] ?? null)) {
                $kalem['birim_fiyat'] = 0;
            }

            return static::kalemHesapla($kalem, $hedef);
        }, array_values($kalemler));
    }

    /**
     * @param  array<int, array<string, mixed>>  $kalemler
     * @return array<int, array<string, mixed>>
     */
    protected static function kalemleriSifirla(array $kalemler, string $hedefParaBirimi): array
    {
        return array_map(function (array $kalem) use ($hedefParaBirimi): array {
            if (! (bool) ($kalem['ozel_fiyat_mi'] ?? false)) {
                $kalem['birim_fiyat'] = 0;
            }

            return static::kalemHesapla($kalem, static::normalizeParaBirimiKodu($hedefParaBirimi));
        }, array_values($kalemler));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function stoktanKalemBilgisiHazirla(int $stokId, string $hedefParaBirimi, array $kurSeti): array
    {
        $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($stokId));

        if (! $stok) {
            return [
                'aciklama' => '',
                'birim' => 'AD',
                'birim_fiyat' => 0,
                'kaynak_para_birimi' => null,
                'kaynak_birim_fiyat' => 0,
                'fiyat_uyari' => static::problemliFiyatUyariMetni(),
                'kaynak_verisi' => static::kaynakVerisiniMetneCevir(['mod' => 'eksik_stok']),
            ];
        }

        $kaynakParaBirimi = static::normalizeParaBirimiKodu((string) ($stok->para_birimi ?? ''));
        $kaynakBirimFiyat = round((float) ($stok->satis_fiyati ?? 0), 8);
        $uyari = null;
        $birimFiyat = 0.0;

        if ($kaynakParaBirimi === '' || $kaynakBirimFiyat <= 0) {
            $uyari = static::problemliFiyatUyariMetni();
        } else {
            if ($kaynakParaBirimi !== static::normalizeParaBirimiKodu($hedefParaBirimi) && empty($kurSeti['pariteler'][$kaynakParaBirimi]['kur'])) {
                try {
                    $kurSeti = static::kurSetiOlustur(static::normalizeParaBirimiKodu($hedefParaBirimi), static::gecerliFirmaId());
                } catch (\Throwable) {
                    $uyari = 'Kur bilgisi alınamadı. Kurları yenileyerek fiyatı tekrar hesaplayabilirsiniz.';
                }
            }

            $birimFiyat = static::kurSetiIleDonustur($kaynakBirimFiyat, $kaynakParaBirimi, static::normalizeParaBirimiKodu($hedefParaBirimi), $kurSeti);
        }

        return [
            'aciklama' => (string) ($stok->ad ?? ''),
            'birim' => (string) ($stok->birim ?? 'AD'),
            'birim_fiyat' => $birimFiyat,
            'kaynak_para_birimi' => $kaynakParaBirimi !== '' ? $kaynakParaBirimi : null,
            'kaynak_birim_fiyat' => $kaynakBirimFiyat,
            'fiyat_uyari' => $uyari,
            'kaynak_verisi' => static::kaynakVerisiniMetneCevir([
                'mod' => 'stok',
                'stok_id' => $stokId,
                'kaynak_para_birimi' => $kaynakParaBirimi,
                'kaynak_birim_fiyat' => $kaynakBirimFiyat,
            ]),
        ];
    }

    protected static function kurSetiIleDonustur(float $tutar, string $kaynakParaBirimi, string $hedefParaBirimi, array $kurSeti): float
    {
        $kaynak = static::normalizeParaBirimiKodu($kaynakParaBirimi);
        $hedef = static::normalizeParaBirimiKodu($hedefParaBirimi);

        if ($kaynak === '' || $hedef === '') {
            return 0.0;
        }

        if ($kaynak === $hedef) {
            return round($tutar, 8);
        }

        $kur = (float) ($kurSeti['pariteler'][$kaynak]['kur'] ?? 0);

        if ($kur <= 0) {
            return 0.0;
        }

        return round($tutar * $kur, 8);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function kurSetiniCoz(mixed $kurSeti): array
    {
        if (is_array($kurSeti)) {
            return $kurSeti;
        }

        if (! is_string($kurSeti) || trim($kurSeti) === '') {
            return [];
        }

        $cozulmus = json_decode($kurSeti, true);

        return is_array($cozulmus) ? $cozulmus : [];
    }

    protected static function kurSetiniMetneCevir(mixed $kurSeti): ?string
    {
        if (is_array($kurSeti)) {
            return json_encode($kurSeti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return is_string($kurSeti) && trim($kurSeti) !== '' ? $kurSeti : null;
    }

    protected static function kaynakVerisiniMetneCevir(mixed $kaynakVerisi): ?string
    {
        if (is_array($kaynakVerisi)) {
            return json_encode($kaynakVerisi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return is_string($kaynakVerisi) && trim($kaynakVerisi) !== '' ? $kaynakVerisi : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function aktifParaBirimiKodlari(int $firmaId): array
    {
        $kodlar = ParaBirimi::query()
            ->where('aktif_mi', true)
            ->where(function (Builder $query) use ($firmaId): void {
                $query->whereNull('firma_id')
                    ->orWhere('firma_id', $firmaId);
            })
            ->pluck('kod')
            ->filter()
            ->map(fn ($kod): string => static::normalizeParaBirimiKodu((string) $kod))
            ->values()
            ->all();

        return array_values(array_unique(array_merge($kodlar, ['TRY', 'USD', 'EUR'])));
    }

    protected static function varsayilanKurTipiEtiketi(): string
    {
        return Str::upper((string) config('muhasebe.doviz.tcmb_deger_tipi', 'ForexSelling'));
    }

    protected static function problemliFiyatUyariMetni(): string
    {
        return 'Bu stok için satış fiyatı veya para birimi tanımlı değil. İsterseniz manuel fiyat girerek özel fiyat modunda devam edebilirsiniz.';
    }

    protected static function normalizeParaBirimiKodu(string $kod): string
    {
        $normalize = Str::upper(trim($kod));

        return match ($normalize) {
            '$', 'US$', 'DOLAR', 'DOLLAR', 'USDOLAR' => 'USD',
            '€', 'EURO' => 'EUR',
            '₺', 'TL', 'TRY', 'TURK LIRASI', 'TÜRK LİRASI' => 'TRY',
            default => $normalize !== '' ? $normalize : 'TRY',
        };
    }
}
