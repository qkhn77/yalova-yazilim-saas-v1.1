<?php

namespace App\Filament\Clusters\TeknikServis\Concerns;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\ParaBirimi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\VergiOrani;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\TeknikServis\TeknikServisAksesuarTanimi;
use App\Models\TeknikServis\TeknikServisArizaTanimi;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMesajSablonu;
use App\Models\TeknikServis\TeknikServisMarkaTanimi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisTahsilati;
use App\Livewire\TeknikServis\YapilanTahsilatlarTablosu;
use App\Services\TeknikServisGenelAyarServisi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\CariKoduUretici;
use App\Services\TenantContextService;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\TeknikServis\Enumlar\MusteriOnayDurumu;
use App\TeknikServis\Enumlar\OdemeDurumu;
use App\TeknikServis\Enumlar\Oncelik;
use App\TeknikServis\Enumlar\ServisKanali;
use App\TeknikServis\Enumlar\ServisTipi;
use App\TeknikServis\Enumlar\TeknikServisKalemMuhasebeDurumu;
use App\TeknikServis\Enumlar\TeknikServisKalemRolu;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeIslemTipi;
use App\TeknikServis\Filament\TeknikServisDurumKodlari;
use App\TeknikServis\Servisler\TeknikServisFisNumarasiServisi;
use App\TeknikServis\Servisler\TeknikServisFormSecenekCache;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Support\RawJs;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TeknikServisKayitFormSchema
{
    /** @var array<string, array<int|string, string>> */
    private static array $secenekCache = [];

    private static ?int $aktifFirmaIdCache = null;

    private static ?int $gecerliFirmaIdCache = null;

    /** @var array<int, string|null> */
    private static array $cariParaBirimiCache = [];

    /** @var array<string, Cari|null> */
    private static array $cariKaydiCache = [];

    /** @var array<string, \Illuminate\Support\Collection<int, Cari>> */
    private static array $cariTelefonSonuclariCache = [];

    /** @var array<string, StokKarti|null> */
    private static array $stokKaydiCache = [];

    /** @var array<string, TeknikServisDurumTanimi|null> */
    private static array $servisDurumuKaydiCache = [];

    /** @var array<string, array{ad:string,kod:string}|null> */
    private static array $servisDurumuOzetiCache = [];

    /**
     * @return array<int, Component>
     */
    public static function bilesenler(bool $olusturma, ?ServisTipi $sabitServisTipi = null): array
    {
        $inlineTanimOlusturmaAktif = true;
        $detayliOlusturma = true;
        $hizliArizaliOlusturma = false;
        $hizliDisServisOlusturma = false;
        $hizliArizaliVeyaDisServisOlusturma = false;
        $hizliBakimOlusturma = false;
        $hafifServisOlusturma = false;
        $hizliDuzenleme = false;
        $agirServisDetaylariIlkRenderda = ! $hafifServisOlusturma && ! $hizliDuzenleme;
        $arizaBilgileriIlkRenderda = ! $hizliBakimOlusturma
            && (! $hizliArizaliVeyaDisServisOlusturma || $detayliOlusturma)
            && ! $hizliDuzenleme;
        $garantiGorevIlkRenderda = ! $hizliArizaliVeyaDisServisOlusturma
            && (! $hizliBakimOlusturma || $detayliOlusturma)
            && ! $hizliDuzenleme;
        $cihazAksesuarIlkRenderda = $olusturma || ! $hizliDuzenleme;
        if ($hizliDuzenleme) {
            return self::hizliDuzenlemeBilesenleri();
        }

        if ($hizliArizaliOlusturma && ! $detayliOlusturma) {
            return self::hizliArizaliCihazOlusturmaBilesenleri($sabitServisTipi);
        }

        $bilesenler = [
            ...($olusturma ? [
                Forms\Components\Hidden::make('create_idempotency_key')
                    ->default(fn (): string => (string) Str::uuid()),
            ] : []),
            Forms\Components\Section::make("Servis kimli\u{011F}i")
                ->columns(['default' => 1, 'md' => 2, 'xl' => 6])
                ->schema([
                    self::servisTipiAlani($olusturma, $sabitServisTipi),
                    Forms\Components\Select::make('oncelik')->label("\u{00D6}ncelik")->options(self::enumSecenekleri(Oncelik::class))->required()->default(fn (): string => self::varsayilanOncelik()),
                    Forms\Components\Select::make('servis_kanali')->label("Servis kanal\u{0131}")->options(self::enumSecenekleri(ServisKanali::class))->required()->default(fn (): string => self::varsayilanServisKanali()),
                    Forms\Components\TextInput::make('fis_no')
                        ->label("Fi\u{015F} no")
                        ->required()
                        ->default(fn (): string => self::sonrakiFisNo(self::gecerliFirmaId()))
                        ->maxLength(64)
                        ->suffixAction(
                            FormAction::make('fis_no_uret')
                                ->label("No \u{00DC}ret")
                                ->icon('heroicon-m-bolt')
                                ->action(function (Set $set): void {
                                    $set('fis_no', self::sonrakiFisNo(self::gecerliFirmaId()));
                                })
                        ),
                    Forms\Components\DateTimePicker::make('kabul_tarihi')->label("Kabul tarihi")->default(now())->required()->native(),
                    Forms\Components\Select::make('servis_durumu_id')
                        ->label("Servis durumu")
                        ->options(fn (): array => self::servisDurumuSecenekleri())
                        ->default(fn (): ?int => self::varsayilanYeniKayitDurumuId())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateHydrated(function ($state, Set $set): void {
                            self::servisDurumunaGoreEkBolumleriAyarla($state, $set);
                        })
                        ->afterStateUpdated(function ($state, Set $set): void {
                            self::servisDurumunaGoreEkBolumleriAyarla($state, $set);
                        })
                        ->required(),
                    Forms\Components\Select::make('whatsapp_sablon_kodu')
                        ->label('WhatsApp mesaj sablonu')
                        ->options(fn (Forms\Get $get): array => (! $olusturma && self::teslimBekleyenDurumuSeciliMi($get)) ? self::whatsappSablonSecenekleri() : [])
                        ->default('teslim_bekleyen_mesaji')
                        ->afterStateHydrated(function ($state, Set $set): void {
                            if (blank($state)) {
                                $set('whatsapp_sablon_kodu', 'teslim_bekleyen_mesaji');
                            }
                        })
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->visible(fn (Forms\Get $get): bool => ! $olusturma && self::teslimBekleyenDurumuSeciliMi($get)),
                    Forms\Components\Placeholder::make('whatsapp_teslim_bekleyen_gonder')
                        ->label('')
                        ->hiddenLabel()
                        ->extraAttributes(['class' => 'teknik-servis-whatsapp-gonder-alani'])
                        ->content(function (Forms\Get $get): HtmlString {
                            $url = self::teslimBekleyenWhatsappUrlFromFormState($get);

                            if (! $url) {
                                return new HtmlString('<span class="text-gray-500">Gecerli telefon bulunamadi.</span>');
                            }

                            return new HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener" style="color:#16a34a;font-weight:600;">WhatsApp Mesaji Gonder</a>');
                        })
                        ->columnSpanFull()
                        ->visible(fn (Forms\Get $get): bool => ! $olusturma && self::teslimBekleyenDurumuSeciliMi($get)),
                ]),

            Forms\Components\Section::make("Cari bilgileri")
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    ...self::telefonAlanBilesenleri('musteri_tel', 'Cari telefon no'),
                    Forms\Components\Select::make('cari_id')
                        ->label("Cari Adı")
                        ->default(fn (): ?int => (int) request()->query('cari_id', 0) ?: null)
                        ->getSearchResultsUsing(fn (string $search): array => self::cariAramaSonuclari($search))
                        ->getOptionLabelUsing(fn ($value): ?string => self::cariSecenekEtiketi((int) $value))
                        ->searchable()
                        ->searchPrompt('Cari adı veya telefon ile ara')
                        ->noSearchResultsMessage('Eşleşen cari bulunamadı')
                        ->required()
                        ->live()
                        ->createOptionForm($inlineTanimOlusturmaAktif ? [
                            ...self::telefonAlanBilesenleri('telefon', 'Telefon', true),
                            Forms\Components\TextInput::make('ad')
                                ->label("Ad Soyad / \u{00DC}nvan")
                                ->required(fn (Forms\Get $get): bool => self::cariTelefonSonuclari((string) ($get('telefon') ?? ''))->isEmpty())
                                ->maxLength(191),
                            ...self::telefonAlanBilesenleri('gsm', '2. Tel'),
                            Forms\Components\Textarea::make('adres')
                                ->label('Adres')
                                ->rows(3)
                                ->columnSpanFull(),
                         ] : null)
                         ->createOptionAction(fn (FormAction $action): FormAction => $action->fillForm(
                             fn ($livewire): array => self::hizliCariTelefonFormVerisi($livewire)
                         ))
                         ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data): int {
                            $firmaId = self::gecerliFirmaId();

                            $cari = new Cari();
                            $cari->firma_id = $firmaId;
                            $cari->kod = app(CariKoduUretici::class)->sonraki($firmaId);
                            $cari->ad = trim((string) ($data['ad'] ?? ''));
                            $cari->tur = CariTuru::Musteri;
                            $telefon = self::telefonuBirlestir(
                                (string) ($data['telefon_ulke_kodu'] ?? '+90'),
                                (string) ($data['telefon'] ?? '')
                            );
                            $ikinciTelefon = self::telefonuBirlestir(
                                (string) ($data['gsm_ulke_kodu'] ?? '+90'),
                                (string) ($data['gsm'] ?? '')
                            );

                            $telefonCarileri = $telefon ? self::cariTelefonSonuclari($telefon) : collect();
                            if ($telefonCarileri->count() === 1 && ($mevcutCari = $telefonCarileri->first())) {
                                return (int) $mevcutCari->getKey();
                            }

                            $cari->telefon = $telefon ?? trim((string) ($data['telefon'] ?? ''));
                            $cari->gsm = $ikinciTelefon;
                            $cari->adres = filled($data['adres'] ?? null)
                                ? trim((string) $data['adres'])
                                : null;
                            $cari->para_birimi = 'TRY';
                            $cari->durum = 'aktif';
                            $cari->save();

                            self::$secenekCache = [];
                            self::$cariKaydiCache = [];
                            self::$cariTelefonSonuclariCache = [];
                            self::$cariParaBirimiCache = [];

                            return (int) $cari->getKey();
                        } : null)
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('gecmis_cihaz_id', null);

                            if (! $state) {
                                return;
                            }

                            $cari = self::cariKaydi((int) $state);
                            if (! $cari) {
                                return;
                            }

                            self::telefonAlaniniDoldur($set, 'musteri_tel', (string) ($cari->telefon ?: $cari->gsm ?: ''));
                            $set('tahsilat_para_birimi', strtoupper((string) ($cari->para_birimi ?: 'TRY')));
                            $set('tahsilat_doviz_kuru', null);
                            $set('tahsilat_hedef_tutar', null);
                        }),
                    Forms\Components\Select::make('gecmis_cihaz_id')
                        ->label('Cari cihazı / geçmiş kayıt')
                        ->default(fn (): ?int => (int) request()->query('kayitli_cihaz_id', 0) ?: null)
                        ->options(fn (Forms\Get $get): array => self::cariCihazSecenekleri((int) ($get('cari_id') ?? 0)))
                        ->placeholder('Cari seçtikten sonra cihaz seçin')
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, Set $set): void {
                            self::gecmisCihazSeciminiUygula((int) $state, $set);
                        })
                        ->suffixAction(
                            FormAction::make('gecmis_servis_kayitlari')
                                ->label('Cihazın servis kayıtlarını gör')
                                ->icon('heroicon-o-clock')
                                ->url(fn (Forms\Get $get): string => self::cariCihazServisKayitlariUrl($get))
                                ->openUrlInNewTab()
                                ->visible(fn (Forms\Get $get): bool => (int) ($get('cari_id') ?? 0) > 0
                                    && ((int) ($get('cihaz_id') ?? 0) > 0 || filled($get('model_no')) || filled($get('seri_no')))),
                        )
                        ->helperText(fn (Forms\Get $get): ?string => self::cariCihazGecmisiMetni(
                            (int) ($get('cari_id') ?? 0),
                            (int) ($get('cihaz_id') ?? 0),
                            (int) ($get('marka_id') ?? 0),
                            (string) ($get('model_no') ?? ''),
                            (string) ($get('seri_no') ?? ''),
                        ))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                ]),

            Forms\Components\Section::make("Cihaz bilgileri")
                ->columns(['default' => 1, 'md' => 2, 'xl' => 6])
                ->schema([
                    Forms\Components\Placeholder::make('kayitli_cihaz_no')
                        ->label('Benzersiz cihaz numarası')
                        ->content(fn (?TeknikServisKaydi $record): string => $record?->kayitliCihaz?->cihaz_no ?? 'Kayıt oluşturulduktan sonra atanır')
                        ->columnSpanFull(),
                    Forms\Components\Select::make('cihaz_id')
                        ->label("Cihaz")
                        ->default(fn (): ?int => (int) request()->query('cihaz_id', 0) ?: null)
                        ->extraFieldWrapperAttributes(['class' => 'teknik-servis-single-line-select'])
                        ->getSearchResultsUsing(fn (string $search): array => self::tanimModelAramaSonuclari(TeknikServisCihazTanimi::class, $search))
                        ->getOptionLabelUsing(fn ($value): ?string => self::tanimModelAdi(TeknikServisCihazTanimi::class, $value))
                        ->searchable()
                        ->createOptionForm($inlineTanimOlusturmaAktif ? [
                            Forms\Components\TextInput::make('ad')->label("Ad")->required()->maxLength(191),
                            Forms\Components\TextInput::make('kod')->label("Kod")->maxLength(64),
                         ] : null)
                         ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data): int {
                            $ad = trim((string) ($data['ad'] ?? ''));
                            $kod = trim((string) ($data['kod'] ?? '')) ?: null;

                            $kayit = TeknikServisCihazTanimi::query()
                                ->withoutGlobalScopes()
                                ->whereNull('deleted_at')
                                ->whereNull('firma_id')
                                ->where('ad', $ad)
                                ->first();

                            if ($kayit) {
                                if (filled($kayit->deleted_at)) {
                                    $kayit->restore();
                                }

                                $guncel = [
                                    'aktif' => true,
                                ];

                                if ($kod && blank($kayit->kod)) {
                                    $guncel['kod'] = $kod;
                                }

                                if (! empty($guncel)) {
                                    $kayit->update($guncel);
                                }
                            } else {
                                $kayit = TeknikServisCihazTanimi::query()->create([
                                    'firma_id' => null,
                                    'ad' => $ad,
                                    'kod' => $kod,
                                    'aktif' => true,
                                    'siralama' => 0,
                                    'varsayilan_mi' => false,
                                ]);
                            }

                            self::$secenekCache = [];

                            return (int) $kayit->getKey();
                        } : null),
                    Forms\Components\Select::make('marka_id')
                        ->label("Marka")
                        ->default(fn (): ?int => (int) request()->query('marka_id', 0) ?: null)
                        ->extraFieldWrapperAttributes(['class' => 'teknik-servis-single-line-select'])
                        ->getSearchResultsUsing(fn (string $search): array => self::tanimModelAramaSonuclari(TeknikServisMarkaTanimi::class, $search))
                        ->getOptionLabelUsing(fn ($value): ?string => self::tanimModelAdi(TeknikServisMarkaTanimi::class, $value))
                        ->searchable()
                        ->createOptionForm($inlineTanimOlusturmaAktif ? [
                            Forms\Components\TextInput::make('ad')->label("Ad")->required()->maxLength(191),
                            Forms\Components\TextInput::make('kod')->label("Kod")->maxLength(64),
                        ] : null)
                        ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data): int {
                            $ad = trim((string) ($data['ad'] ?? ''));
                            $kod = trim((string) ($data['kod'] ?? '')) ?: null;

                            $kayit = TeknikServisMarkaTanimi::query()
                                ->withoutGlobalScopes()
                                ->whereNull('deleted_at')
                                ->whereNull('firma_id')
                                ->firstOrCreate(
                                    [
                                        'firma_id' => null,
                                        'ad' => $ad,
                                    ],
                                    [
                                        'kod' => $kod,
                                        'aktif' => true,
                                        'siralama' => 0,
                                        'varsayilan_mi' => false,
                                    ]
                                );

                            if ($kod && blank($kayit->kod)) {
                                $kayit->update(['kod' => $kod]);
                            }

                            self::$secenekCache = [];

                            return (int) $kayit->getKey();
                        } : null),
                    Forms\Components\TextInput::make('model_no')->label("Model no")->default(fn (): ?string => request()->query('model_no'))->maxLength(128),
                    Forms\Components\TextInput::make('seri_no')
                        ->label("Seri no")
                        ->default(fn (): ?string => request()->query('seri_no'))
                        ->maxLength(128)
                        ->required(fn (Forms\Get $get): bool => self::yeniKayitDurumuSeciliMi($get))
                        ->validationMessages([
                            'required' => "Yeni kay\u{0131}t durumunda Seri no zorunludur.",
                        ]),
                    Forms\Components\TextInput::make('km_bilgisi')
                        ->label("Km bilgisi")
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => self::disServisTipiSeciliMi($get, $sabitServisTipi)),
                    ...($cihazAksesuarIlkRenderda ? [
                    Forms\Components\Select::make('aksesuarlar')
                        ->label("Aksesuarlar")
                        ->multiple()
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->relationship(
                            name: 'aksesuarlar',
                            titleAttribute: 'ad',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->withoutGlobalScopes()
                                ->whereNull('teknik_servis_tanim_aksesuarlar.deleted_at')
                                ->whereNull('teknik_servis_tanim_aksesuarlar.firma_id')
                                ->orderBy('teknik_servis_tanim_aksesuarlar.ad')
                        )
                        ->pivotData([
                            'firma_id' => self::gecerliFirmaId(),
                            'adet' => 1,
                        ])
                        ->saveRelationshipsUsing(function (TeknikServisKaydi $record, $state): void {
                            self::aksesuarIliskisiniKaydet($record, $state);
                        })
                        ->getSearchResultsUsing(fn (string $search): array => self::tanimModelAramaSonuclari(TeknikServisAksesuarTanimi::class, $search))
                        ->getOptionLabelsUsing(fn (array $values): array => self::tanimModelAdlari(TeknikServisAksesuarTanimi::class, $values))
                        ->searchable()
                        ->createOptionForm($inlineTanimOlusturmaAktif ? [
                            Forms\Components\TextInput::make('ad')->label("Ad")->required()->maxLength(191),
                            Forms\Components\TextInput::make('kod')->label("Kod")->maxLength(64),
                        ] : null)
                        ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data): int {
                            $ad = trim((string) ($data['ad'] ?? ''));
                            $kod = trim((string) ($data['kod'] ?? '')) ?: null;

                            $kayit = TeknikServisAksesuarTanimi::query()
                                ->withoutGlobalScopes()
                                ->whereNull('deleted_at')
                                ->whereNull('firma_id')
                                ->firstOrCreate(
                                    [
                                        'firma_id' => null,
                                        'ad' => $ad,
                                    ],
                                    [
                                        'kod' => $kod,
                                        'aktif' => true,
                                        'siralama' => 0,
                                        'varsayilan_mi' => false,
                                    ]
                                );

                            if ($kod && blank($kayit->kod)) {
                                $kayit->update(['kod' => $kod]);
                            }

                            self::$secenekCache = [];

                            return (int) $kayit->getKey();
                        } : null),
                    ] : []),
                ]),

            ...($arizaBilgileriIlkRenderda ? [
            Forms\Components\Section::make("Ar\u{0131}za bilgileri")
                ->columns(2)
                ->schema([
                    Forms\Components\Hidden::make('ariza_id'),
                    Forms\Components\Select::make('arizalar')
                        ->label("Arıza tanımları")
                        ->multiple()
                        ->extraFieldWrapperAttributes(['class' => 'teknik-servis-ariza-secici'])
                        ->relationship(
                            name: 'arizalar',
                            titleAttribute: 'ad',
                            modifyQueryUsing: fn (Builder $query) => self::arizaSecimSorgusu($query)
                        )
                        ->pivotData([
                            'firma_id' => self::gecerliFirmaId(),
                        ])
                        ->saveRelationshipsUsing(function (TeknikServisKaydi $record, $state): void {
                            self::arizaIliskisiniKaydet($record, $state);
                        })
                        ->getSearchResultsUsing(fn (string $search): array => self::tanimModelAramaSonuclari(TeknikServisArizaTanimi::class, $search))
                        ->getOptionLabelsUsing(fn (array $values): array => self::tanimModelAdlari(TeknikServisArizaTanimi::class, $values))
                        ->searchable()
                        ->afterStateHydrated(function ($state, Set $set): void {
                            $ids = array_values(array_filter((array) $state));
                            $set('ariza_id', $ids[0] ?? null);
                        })
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $ids = array_values(array_filter((array) $state));
                            $set('ariza_id', $ids[0] ?? null);
                        })
                        ->createOptionForm($inlineTanimOlusturmaAktif ? [
                            Forms\Components\TextInput::make('ad')->label("Ad")->required()->maxLength(191),
                            Forms\Components\TextInput::make('kod')->label("Kod")->maxLength(64),
                        ] : null)
                        ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data): int {
                            $ad = trim((string) ($data['ad'] ?? ''));
                            $kod = trim((string) ($data['kod'] ?? '')) ?: null;

                            $mevcut = TeknikServisArizaTanimi::query()
                                ->withoutGlobalScopes()
                                ->whereNull('deleted_at')
                                ->whereNull('firma_id')
                                ->whereRaw('LOWER(TRIM(ad)) = ?', [mb_strtolower($ad, 'UTF-8')])
                                ->first();

                            if ($mevcut) {
                                if ($kod && blank($mevcut->kod)) {
                                    $mevcut->update(['kod' => $kod]);
                                }

                                return (int) $mevcut->getKey();
                            }

                            $kayit = TeknikServisArizaTanimi::query()->create([
                                'firma_id' => null,
                                'cihaz_id' => null, // kategorisiz ariza tanimi
                                'ad' => $ad,
                                'kod' => $kod,
                                'aktif' => true,
                                'siralama' => 0,
                                'varsayilan_mi' => false,
                            ]);

                            return (int) $kayit->getKey();
                        } : null),
                    Forms\Components\Actions::make([
                        FormAction::make('cihaz_gorsellerini_yukle')
                            ->label('Görsel ekle')
                            ->icon('heroicon-o-plus')
                            ->color('success')
                            ->button()
                            ->url('?gorsel_detay=1')
                            ->extraAttributes([
                                'class' => 'teknik-servis-cihaz-gorseli-ekle-btn',
                                'style' => 'background:linear-gradient(180deg,#22c55e 0%,#16a34a 100%);border-color:#16a34a;color:#fff;font-weight:700;',
                            ]),
                    ])
                        ->label('Arıza görselleri')
                        ->extraAttributes([
                            'class' => 'teknik-servis-cihaz-gorselleri-hazirla',
                        ])
                        ->visible(fn (Forms\Get $get): bool => ! request()->boolean('gorsel_detay')
                            && ! (bool) $get('cihaz_gorselleri_yukle')
                            && count(array_filter((array) $get('cihaz_gorseller'))) === 0)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                    Forms\Components\FileUpload::make('cihaz_gorseller')
                        ->label('Arıza görselleri')
                        ->hintAction(
                            FormAction::make('cihaz_gorseli_sec')
                                ->label('Görsel ekle')
                                ->icon('heroicon-o-plus')
                                ->color('success')
                                ->button()
                                ->extraAttributes([
                                    'class' => 'teknik-servis-cihaz-gorseli-ekle-btn',
                                ])
                                ->alpineClickHandler(<<<'JS'
const input = $el.closest('.fi-fo-field-wrp')?.querySelector('.teknik-servis-cihaz-gorselleri input[type="file"]');

if (input) {
    input.click();
}
JS)
                        )
                        ->disk('public')
                        ->directory('teknik-servis/cihaz-gorseller')
                        ->visibility('public')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/pjpeg', 'image/jfif'])
                        ->imagePreviewHeight('64')
                        ->itemPanelAspectRatio(1)
                        ->multiple()
                        ->appendFiles()
                        ->maxParallelUploads(1)
                        ->reorderable()
                        ->panelLayout('grid')
                        ->extraAttributes([
                            'class' => 'teknik-servis-cihaz-gorselleri',
                            'style' => 'width: 100%;',
                        ])
                        ->extraFieldWrapperAttributes([
                            'class' => 'teknik-servis-cihaz-gorselleri-alani',
                        ])
                        ->extraInputAttributes([
                            'accept' => 'image/*',
                            'capture' => 'environment',
                        ])
                        ->helperText('Bilgisayardan ekleyin veya mobilde kamera ile yeni fotoğraf çekin.')
                        ->visible(fn (Forms\Get $get): bool => request()->boolean('gorsel_detay')
                            || (bool) $get('cihaz_gorselleri_yukle')
                            || count(array_filter((array) $get('cihaz_gorseller'))) > 0)
                        ->columnSpanFull(),
                ]),
            ] : []),

            ...($agirServisDetaylariIlkRenderda ? [
            Forms\Components\Section::make("Stok kartları")
                ->visible(fn (Forms\Get $get): bool => ! self::yeniKayitDurumuSeciliMi($get)
                    && ($olusturma || (bool) $get('stok_kalemlerini_goster')))
                ->description("Teslim Bekleyen / Teslim Edilen durum ge\u{00E7}i\u{015F}lerinde en az bir stok kalemi zorunludur.")
                ->extraAttributes(['class' => 'teklif-editor-card teklif-line-card teknik-servis-line-card'])
                ->schema([
                    Forms\Components\Repeater::make('kalemler')
                        ->label("Stok kalemleri")
                        ->relationship('kalemler')
                        ->collapsible()
                        ->collapsed()
                        ->reorderable(false)
                        ->extraAttributes(['class' => 'teklif-line-repeater teknik-servis-line-repeater'])
                        ->addAction(fn (FormAction $action): FormAction => $action
                            ->icon('heroicon-o-plus')
                            ->color('success')
                        )
                        ->extraItemActions([
                            FormAction::make('guncelle_kalem')
                                ->label('Güncelle')
                                ->icon('heroicon-m-arrow-path')
                                ->button()
                                ->color('gray')
                                ->action(function (array $arguments, Forms\Components\Repeater $component): void {
                                    $component->callAfterStateUpdated();
                                }),
                        ])
                        ->itemLabel(fn (?array $state = null): string => self::stokKalemSatirOzetiDuzMetin($state ?? []))
                        ->addActionLabel('Stok kalemi ekle')
                        ->schema([
                            Forms\Components\Hidden::make('firma_id')->default(fn (): int => self::gecerliFirmaId()),
                            Forms\Components\Hidden::make('kalem_rolu')->default(TeknikServisKalemRolu::Satis->value),
                            Forms\Components\Hidden::make('muhasebe_durumu')->default(TeknikServisKalemMuhasebeDurumu::Taslak->value),
                            Forms\Components\Hidden::make('kdv_dahil_mi')->default(false),
                            Forms\Components\Hidden::make('iskonto_tipi')->default('oran'),
                            Forms\Components\Hidden::make('satir_toplami')->default(0),
                            Forms\Components\Placeholder::make('satir_no_gosterge')
                                ->label('Sıra No')
                                ->content(fn (Forms\Get $get, Component $component): HtmlString => self::stokKalemSatirNoGosterimi($get, $component))
                                ->dehydrated(false)
                                ->extraAttributes(['class' => 'teknik-servis-line-index'])
                                ->columnSpan(['default' => 1, 'xl' => 1]),
                            Forms\Components\Select::make('stok_id')
                                ->label(self::ikiSatirliZorunluAlanEtiketi('Stok', 'Adı'))
                                ->extraAttributes(['class' => 'teknik-servis-stok-secici'])
                                ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-stok'])
                                ->markAsRequired(false)
                                ->getSearchResultsUsing(fn (string $search, Forms\Get $get): array => self::stokAramaSonuclari($search, (int) ($get('firma_id') ?: self::gecerliFirmaId())))
                                ->getOptionLabelUsing(fn ($value, Forms\Get $get): ?string => self::stokSecenekEtiketiById((int) $value, (int) ($get('firma_id') ?: self::gecerliFirmaId())))
                                ->searchable()
                                ->native(false)
                                ->placeholder('Stok adı yazın veya seçin')
                                ->searchPrompt('Stok adı veya stok kodu ile ara')
                                ->noSearchResultsMessage('Eşleşen stok bulunamadı')
                                ->required()
                                ->live()
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->helperText("Bo\u{015F} b\u{0131}rak\u{0131}l\u{0131}rsa otomatik STK kodu \u{00FC}retilir.")
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')->label('Ad')->required()->maxLength(255),
                                    Forms\Components\Select::make('tur')->label("T\u{00FC}r")->options(self::stokTurSecenekleri())->required()->default(StokKartiTuru::TicariMal->value),
                                    Forms\Components\Select::make('birim')->label('Birim')->options(fn (): array => self::birimSecenekleri())->required()->default('AD')->searchable(),
                                    Forms\Components\TextInput::make('satis_fiyati')->label("Sat\u{0131}\u{015F} fiyat\u{0131}")->numeric()->required()->default(0),
                                    Forms\Components\TextInput::make('alis_fiyati')->label("Al\u{0131}\u{015F} fiyat\u{0131}")->numeric()->required()->default(0),
                                    Forms\Components\TextInput::make('stok_miktari')->label('Mevcut stok')->numeric()->required()->default(0),
                                    Forms\Components\Select::make('kdv_orani')
                                        ->label("KDV oran\u{0131}")
                                        ->options(fn (Forms\Get $get): array => self::vergiOraniSecenekleri((int) ($get('firma_id') ?: self::gecerliFirmaId())))
                                        ->required()
                                        ->default('20')
                                        ->searchable(),
                                    Forms\Components\Select::make('durum')->label('Durum')->options(self::hesapDurumuSecenekleri())->required()->default(HesapDurumu::Aktif->value),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data): int {
                                    $firmaId = self::gecerliFirmaId();
                                    $kod = trim((string) ($data['kod'] ?? ''));
                                    if ($kod === '') {
                                        $kod = self::sonrakiStokKodu($firmaId);
                                    }

                                    $stok = StokKarti::query()->create([
                                        'firma_id' => $firmaId,
                                        'kod' => $kod,
                                        'ad' => trim((string) ($data['ad'] ?? '')),
                                        'tur' => (string) ($data['tur'] ?? StokKartiTuru::TicariMal->value),
                                        'birim' => (string) ($data['birim'] ?? 'AD'),
                                        'satis_fiyati' => (float) ($data['satis_fiyati'] ?? 0),
                                        'alis_fiyati' => (float) ($data['alis_fiyati'] ?? 0),
                                        'kdv_orani' => (float) ($data['kdv_orani'] ?? 20),
                                        'para_birimi' => strtoupper((string) ($data['para_birimi'] ?? 'TRY')),
                                        'durum' => (string) ($data['durum'] ?? HesapDurumu::Aktif->value),
                                        'stok_takip' => true,
                                        'stok_miktari' => (float) ($data['stok_miktari'] ?? 0),
                                        'minimum_stok' => 0,
                                        'maksimum_stok' => 0,
                                        'negative_flag' => false,
                                    ]);

                                    self::$secenekCache = [];
                                    self::$stokKaydiCache = [];

                                    return (int) $stok->getKey();
                                } : null)
                                ->afterStateUpdated(function ($state, Set $set, Forms\Get $get): void {
                                    if (! $state) {
                                        return;
                                    }

                                    $stok = self::stokKaydi((int) $state, (int) ($get('firma_id') ?: self::gecerliFirmaId()));
                                    if (! $stok) {
                                        return;
                                    }

                                    $set('aciklama', (string) $stok->ad);
                                    $set('birim', strtoupper(trim((string) ($stok->birim ?: 'AD'))) ?: 'AD');
                                    $set('birim_fiyat', (float) $stok->satis_fiyati);
                                    $set('kdv_orani', (string) $stok->kdv_orani);
                                    $set('para_birimi', (string) ($stok->para_birimi ?: 'TRY'));
                                    self::kalemHesabiUygula($get, $set);
                                })
                                ->columnSpan(['default' => 1, 'md' => 3, 'lg' => 3, 'xl' => 3]),
                            Forms\Components\Select::make('birim')
                                ->label('Birim')
                                ->options(fn (): array => self::birimGosterimSecenekleri())
                                ->default('AD')
                                ->required()
                                ->native()
                                ->extraAttributes(['class' => 'teknik-servis-kalem-birim'])
                                ->columnSpan(['default' => 1, 'xl' => 1]),
                            Forms\Components\Hidden::make('aciklama')
                                ->required(),
                            Forms\Components\TextInput::make('miktar')
                                ->label(self::ikiSatirliZorunluAlanEtiketi('Miktar'))
                                ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-miktar'])
                                ->numeric()
                                ->required()
                                ->markAsRequired(false)
                                ->default(1)
                                ->live(onBlur: true)
                                ->afterStateHydrated(fn (Forms\Get $get, Set $set) => self::kalemHesabiUygula($get, $set))
                                ->afterStateUpdated(fn (Forms\Get $get, Set $set) => self::kalemHesabiUygula($get, $set, 'miktar'))
                                ->columnSpan(['default' => 1, 'xl' => 1]),
                            Forms\Components\TextInput::make('birim_fiyat')
                                ->label(self::ikiSatirliZorunluAlanEtiketi('Birim', 'Fiyat'))
                                ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-birim-fiyat'])
                                ->numeric()
                                ->required()
                                ->markAsRequired(false)
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Forms\Get $get, Set $set) => self::kalemHesabiUygula($get, $set, 'birim_fiyat'))
                                ->columnSpan(['default' => 1, 'xl' => 2]),
                            Forms\Components\TextInput::make('brut_fiyat_gosterim')->label(self::ikiSatirliAlanEtiketi('Brüt', 'Fiyat'))->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-brut-fiyat'])->numeric()->readOnly()->dehydrated(false)->columnSpan(['default' => 1, 'xl' => 2]),
                            Forms\Components\TextInput::make('iskonto_orani')
                                ->label(self::ikiSatirliAlanEtiketi('İskonto', 'Oranı'))
                                ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-iskonto-orani'])
                                ->numeric()
                                ->default(0)
                                ->maxValue(100)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Forms\Get $get, Set $set) => self::kalemHesabiUygula($get, $set, 'iskonto_orani'))
                                ->columnSpan(['default' => 1, 'xl' => 2]),
                            Forms\Components\TextInput::make('iskonto_tutari')
                                ->label(self::ikiSatirliAlanEtiketi('İskonto', 'Tutarı'))
                                ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-iskonto-tutari'])
                                ->numeric()
                                ->default(0)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Forms\Get $get, Set $set) => self::kalemHesabiUygula($get, $set, 'iskonto_tutari'))
                                ->columnSpan(['default' => 1, 'xl' => 2]),
                            Forms\Components\Select::make('kdv_orani')
                                ->label(self::ikiSatirliZorunluAlanEtiketi('KDV', 'Oranı'))
                                ->extraAttributes(['class' => 'teknik-servis-kdv-secici'])
                                ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-kdv-orani'])
                                ->options(fn (Forms\Get $get): array => self::vergiOraniSecenekleri((int) ($get('firma_id') ?: self::gecerliFirmaId())))
                                ->required()
                                ->markAsRequired(false)
                                ->default('20')
                                ->native()
                                ->live()
                                ->afterStateHydrated(fn (Forms\Get $get, Set $set) => self::kalemHesabiUygula($get, $set, 'kdv_orani'))
                                ->afterStateUpdated(fn (Forms\Get $get, Set $set) => self::kalemHesabiUygula($get, $set, 'kdv_orani'))
                                ->columnSpan(['default' => 1, 'xl' => 3]),
                            Forms\Components\TextInput::make('net_toplam_gosterim')
                                ->label(self::ikiSatirliAlanEtiketi('Net', 'Toplam'))
                                ->extraFieldWrapperAttributes(['class' => 'teknik-servis-kalem-net-toplam'])
                                ->numeric()
                                ->default(0)
                                ->dehydrated(false)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Get $get, Set $set): void {
                                    self::netToplamdanBirimFiyatHesapla($get, $set);
                                })
                                ->columnSpan(['default' => 1, 'md' => 1, 'lg' => 1, 'xl' => 1]),
                            Forms\Components\Hidden::make('kdv_tutari')->default(0),
                            Forms\Components\Hidden::make('para_birimi')->default('TRY'),
                        ])
                        ->columns(['default' => 1, 'md' => 19, 'lg' => 19, 'xl' => 19]),
                            Forms\Components\Section::make("Kalem özeti")
                        ->extraAttributes(['style' => 'max-width: 460px; margin-left: auto;'])
                        ->schema([
                            Forms\Components\Placeholder::make('stok_kalem_ozeti')
                                ->hiddenLabel()
                                ->content(fn (Forms\Get $get): HtmlString => self::stokKalemOzetiMetni($get))
                                ->columnSpanFull(),
                        ]),
                ]),
            ] : []),
            ...($agirServisDetaylariIlkRenderda ? [
            Forms\Components\Section::make('Muhasebe Kayıtları')
                ->visible(fn (Forms\Get $get): bool => $olusturma || self::kayitOlusturulduMu($get))
                ->schema([
                    Forms\Components\Placeholder::make('yeni_kayit_muhasebe_uyarisi')
                        ->hiddenLabel()
                        ->visible(fn (Forms\Get $get): bool => ! self::kayitOlusturulduMu($get))
                        ->content(new HtmlString('<div style="color:#b91c1c;font-weight:600;">Muhasebe hareketlerini görebilmek ve tahsilat girebilmek için önce servis kaydını oluşturmanız gerekir. Kayıt oluştuktan sonra bu bölüm aktif olur.</div>'))
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('muhasebe_durum_ozeti')
                        ->hiddenLabel()
                        ->visible(fn (Forms\Get $get): bool => self::kayitOlusturulduMu($get))
                        ->content(fn (?TeknikServisKaydi $record): HtmlString => self::muhasebeDurumOzetiHtml($record))
                        ->columnSpanFull(),
                    Forms\Components\Livewire::make(YapilanTahsilatlarTablosu::class)
                        ->data(['showHeaderActions' => true])
                        ->visible(fn (Forms\Get $get): bool => self::kayitOlusturulduMu($get))
                        ->key(fn (Forms\Get $get): string => 'servis-tahsilatlar-'.self::servisKaydiId($get))
                        ->lazy()
                        ->columnSpanFull(),
                ]),
            ] : []),

            ...($garantiGorevIlkRenderda ? [
            Forms\Components\Section::make("Garanti ve görev")
                ->visible(fn (Forms\Get $get): bool => ! self::yeniKayitDurumuSeciliMi($get)
                    && ($olusturma || (bool) $get('garanti_gorev_goster')))
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\DatePicker::make('garanti_baslangic_tarihi')
                        ->label("Garanti ba\u{015F}lang\u{0131}\u{00E7}")
                        ->native()
                        ->default(fn (): ?string => $olusturma && self::varsayilanGarantiAy() > 0 ? now()->format('Y-m-d') : null)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('garanti_baslangic_simdi')
                                ->label("\u{015E}imdi")
                                ->icon('heroicon-m-clock')
                                ->action(function (Set $set): void {
                                    $set('garanti_baslangic_tarihi', now()->format('Y-m-d'));
                                })
                        ),
                    Forms\Components\DatePicker::make('garanti_bitis_tarihi')
                        ->label("Garanti biti\u{015F}")
                        ->native()
                        ->default(fn (): ?string => $olusturma && self::varsayilanGarantiAy() > 0
                            ? now()->addMonthsNoOverflow(self::varsayilanGarantiAy())->format('Y-m-d')
                            : null)
                        ->hintActions([
                            Forms\Components\Actions\Action::make('garanti_bitis_6_ay')
                                ->label('6 Ay')
                                ->color('gray')
                                ->action(fn (Set $set, Forms\Get $get) => self::garantiBitisTarihiAta($set, $get, 6)),
                            Forms\Components\Actions\Action::make('garanti_bitis_12_ay')
                                ->label('12 Ay')
                                ->color('gray')
                                ->action(fn (Set $set, Forms\Get $get) => self::garantiBitisTarihiAta($set, $get, 12)),
                            Forms\Components\Actions\Action::make('garanti_bitis_18_ay')
                                ->label('18 Ay')
                                ->color('gray')
                                ->action(fn (Set $set, Forms\Get $get) => self::garantiBitisTarihiAta($set, $get, 18)),
                            Forms\Components\Actions\Action::make('garanti_bitis_24_ay')
                                ->label('24 Ay')
                                ->color('gray')
                                ->action(fn (Set $set, Forms\Get $get) => self::garantiBitisTarihiAta($set, $get, 24)),
                        ])
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('garanti_bitis_temizle')
                                ->label('Temizle')
                                ->icon('heroicon-m-x-mark')
                                ->color('danger')
                                ->action(function (Set $set): void {
                                    $set('garanti_bitis_tarihi', null);
                                })
                        ),
                    Forms\Components\Hidden::make('bakim_periyot_ay')
                        ->default(function () use ($olusturma, $sabitServisTipi): ?int {
                            if ($olusturma && $sabitServisTipi === ServisTipi::Bakim) {
                                return self::varsayilanBakimPeriyotAy();
                            }

                            return null;
                        }),
                    Forms\Components\DatePicker::make('bakim_tarihi')
                        ->label("Periyodik bak\u{0131}m tarihi")
                        ->native()
                        ->default(function () use ($olusturma, $sabitServisTipi): ?string {
                            if ($olusturma && $sabitServisTipi === ServisTipi::Bakim) {
                                return Carbon::today()->addMonthsNoOverflow(self::varsayilanBakimPeriyotAy())->format('Y-m-d');
                            }

                            return null;
                        })
                        ->hintActions([
                            Forms\Components\Actions\Action::make('bakim_6_ay')
                                ->label('6 Ay')
                                ->color('gray')
                                ->action(fn (Set $set) => self::bakimTarihiAta($set, 6)),
                            Forms\Components\Actions\Action::make('bakim_12_ay')
                                ->label('12 Ay')
                                ->color('gray')
                                ->action(fn (Set $set) => self::bakimTarihiAta($set, 12)),
                        ])
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('bakim_temizle')
                                ->label('Temizle')
                                ->icon('heroicon-m-x-mark')
                                ->color('danger')
                                ->action(function (Set $set): void {
                                    $set('bakim_tarihi', null);
                                    $set('bakim_periyot_ay', null);
                                })
                        )
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (blank($state)) {
                                $set('bakim_periyot_ay', null);
                            }
                        }),
                ]),
            ] : []),

            ...($agirServisDetaylariIlkRenderda ? [
            Forms\Components\Section::make("Teklif ve onay")
                ->visible(fn (Forms\Get $get): bool => ! self::yeniKayitDurumuSeciliMi($get)
                    && ($olusturma || (bool) $get('teklif_onay_goster')))
                ->extraAttributes(['class' => 'teknik-servis-onay-card'])
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    Forms\Components\TextInput::make('teklif_tutari')->label("Teklif tutar\u{0131}")->numeric(),
                    Forms\Components\DateTimePicker::make('teklif_tarihi')
                        ->label("Teklif tarihi")
                        ->native()
                        ->hintAction(
                            FormAction::make('teklif_tarihi_simdi')
                                ->label("\u{015E}imdi")
                                ->icon('heroicon-m-clock')
                                ->action(function (Set $set): void {
                                    $set('teklif_tarihi', now()->format('Y-m-d H:i:s'));
                                })
                        )
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (blank($state)) {
                                return;
                            }

                            $dt = \Illuminate\Support\Carbon::parse((string) $state);
                            if ($dt->format('H:i:s') === '00:00:00') {
                                $now = now();
                                $set('teklif_tarihi', $dt->setTime($now->hour, $now->minute, $now->second)->format('Y-m-d H:i:s'));
                            }
                        }),
                    Forms\Components\Select::make('musteri_onay_durumu')->label("M\u{00FC}\u{015F}teri onay\u{0131}")->options(self::enumSecenekleri(MusteriOnayDurumu::class))->default(fn (): string => self::varsayilanMusteriOnayDurumu())->placeholder('Bir seçenek seçin'),
                    self::notAlaniTextarea('onay_notu', "Onay notu")
                        ->columnSpan(['default' => 'full', 'xl' => 3]),
                ]),
            ] : []),

            Forms\Components\Section::make("Notlar")
                ->extraAttributes(['class' => 'teklif-editor-card teklif-notes-card teknik-servis-notes-card'])
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    self::notAlaniTextarea('musteri_sikayeti', "M\u{00FC}\u{015F}teri \u{015F}ikayeti")
                        ->required(),
                    self::notAlaniTextarea('ic_servis_notu', "\u{0130}\u{00E7} servis notu")
                        ->hidden(fn (): bool => $olusturma),
                    self::notAlaniTextarea('musteriye_gorunen_not', 'Servis Notu'),
                    self::notAlaniTextarea('yapilan_islemler', 'Yapılan İşlemler'),
                ]),
        ];

        $olusturmaAnahtariAlani = null;

        if ($olusturma && array_key_exists(0, $bilesenler) && $bilesenler[0] instanceof Forms\Components\Hidden) {
            $olusturmaAnahtariAlani = array_shift($bilesenler);
        }

        $stokKalemleriKontrolu = Forms\Components\Section::make("Ek bölümler")
            ->visible(fn (Forms\Get $get): bool => ! $olusturma && ! self::yeniKayitDurumuSeciliMi($get))
            ->collapsible()
            ->collapsed()
            ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
            ->schema([
                Forms\Components\Toggle::make('stok_kalemlerini_goster')
                    ->label('Stok kalemlerini düzenle')
                    ->default(false)
                    ->dehydrated(false)
                    ->live(),
            Forms\Components\Toggle::make('muhasebe_kayitlarini_goster')
                ->label('Muhasebe kayıtlarını göster')
                ->default(fn (?TeknikServisKaydi $record): bool => (bool) $record?->tahsilatlar()->exists())
                ->dehydrated(false)
                ->live(),
                Forms\Components\Toggle::make('garanti_gorev_goster')
                    ->label('Garanti ve görevi düzenle')
                    ->default(false)
                    ->dehydrated(false)
                    ->live(),
                Forms\Components\Toggle::make('teklif_onay_goster')
                    ->label('Teklif ve onayı düzenle')
                    ->default(false)
                    ->dehydrated(false)
                    ->live(),
                Forms\Components\Toggle::make('teslim_bilgilerini_goster')
                    ->label('Teslim bilgilerini düzenle')
                    ->default(false)
                    ->dehydrated(false)
                    ->live(),
            ]);

        if ($hizliArizaliVeyaDisServisOlusturma) {
            $bilesenler = $arizaBilgileriIlkRenderda
                ? [
                    $bilesenler[0], // Servis kimligi
                    $bilesenler[1], // Cari bilgileri
                    $bilesenler[2], // Cihaz bilgileri
                    $bilesenler[3], // Ariza bilgileri
                    $bilesenler[4], // Notlar
                ]
                : [
                    $bilesenler[0], // Servis kimligi
                    $bilesenler[1], // Cari bilgileri
                    $bilesenler[2], // Cihaz bilgileri
                    $bilesenler[3], // Notlar
                ];
        } elseif ($hizliBakimOlusturma) {
            $bilesenler = $garantiGorevIlkRenderda
                ? [
                    $bilesenler[0], // Servis kimligi
                    $bilesenler[1], // Cari bilgileri
                    $bilesenler[2], // Cihaz bilgileri
                    $bilesenler[3], // Garanti ve bakim
                    $bilesenler[4], // Notlar
                ]
                : [
                    $bilesenler[0], // Servis kimligi
                    $bilesenler[1], // Cari bilgileri
                    $bilesenler[2], // Cihaz bilgileri
                    $bilesenler[3], // Notlar
                ];
        } elseif ($olusturma) {
            $bilesenler = [
                $bilesenler[0], // Servis kimligi
                $bilesenler[1], // Cari bilgileri
                $bilesenler[2], // Cihaz bilgileri
                $bilesenler[3], // Ariza bilgileri
                $bilesenler[4], // Stok kartlari
                $bilesenler[5], // Muhasebe kayitlari
                $bilesenler[8], // Notlar
                $bilesenler[7], // Teklif ve onay
                $bilesenler[6], // Garanti ve gorev
            ];
        } elseif ($hizliDuzenleme) {
            $bilesenler = $arizaBilgileriIlkRenderda
                ? [
                    $bilesenler[0], // Servis kimligi
                    $bilesenler[1], // Cari bilgileri
                    $bilesenler[3], // Ariza bilgileri
                    $bilesenler[4], // Notlar
                ]
                : [
                    $bilesenler[0], // Servis kimligi
                    $bilesenler[1], // Cari bilgileri
                    $bilesenler[3], // Notlar
                ];
        } else {
            $bilesenler = [
                $bilesenler[0], // Servis kimligi
                $bilesenler[1], // Cari bilgileri
                $bilesenler[2], // Cihaz bilgileri
                $bilesenler[3], // Ariza bilgileri
                $stokKalemleriKontrolu,
                $bilesenler[4], // Stok kartlari
                $bilesenler[5], // Muhasebe kayitlari
                $bilesenler[8], // Notlar
                $bilesenler[7], // Teklif ve onay
                $bilesenler[6], // Garanti ve gorev
            ];
        }

        if ($olusturmaAnahtariAlani instanceof Forms\Components\Hidden) {
            array_unshift($bilesenler, $olusturmaAnahtariAlani);
        }

        if (! $olusturma && ! $hizliDuzenleme) {
            $bilesenler[] = Forms\Components\Section::make("Teslim")
                ->visible(fn (Forms\Get $get): bool => (bool) $get('teslim_bilgilerini_goster'))
                ->extraAttributes(['class' => 'teknik-servis-teslim-card'])
                ->columns(['default' => 1, 'md' => 4, 'xl' => 4])
                ->schema([
                    Forms\Components\DateTimePicker::make('teslim_tarihi')
                        ->label("Teslim tarihi")
                        ->native()
                        ->suffixAction(
                            FormAction::make('teslim_tarihi_simdi')
                                ->label("\u{015E}imdi")
                                ->icon('heroicon-m-clock')
                                ->action(function (Set $set): void {
                                    $set('teslim_tarihi', now()->format('Y-m-d H:i:s'));
                                })
                        )
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                    self::notAlaniTextarea('teslim_notu', "Teslim notu")
                        ->columnSpan(['default' => 1, 'md' => 3, 'xl' => 3]),
                ]);
        }

        return $bilesenler;
    }

    private static function hizliArizaliCihazKabulFormuMu(?ServisTipi $sabitServisTipi): bool
    {
        return $sabitServisTipi === ServisTipi::ArizaliCihaz;
    }

    private static function hizliBakimKaydiFormuMu(?ServisTipi $sabitServisTipi): bool
    {
        return $sabitServisTipi === ServisTipi::Bakim;
    }

    private static function hizliDisServisKaydiFormuMu(?ServisTipi $sabitServisTipi): bool
    {
        return $sabitServisTipi === ServisTipi::DisServis;
    }

    public static function formuOlustur(Form $form, bool $olusturma, ?ServisTipi $sabitServisTipi = null): Form
    {
        return $form
            ->columns(['default' => 1, 'xl' => 2])
            ->schema(self::bilesenler($olusturma, $sabitServisTipi));
    }

    /**
     * @return array<int, Component>
     */
    private static function hizliArizaliCihazOlusturmaBilesenleri(?ServisTipi $sabitServisTipi): array
    {
        return [
            Forms\Components\Hidden::make('create_idempotency_key')
                ->default(fn (): string => (string) Str::uuid()),
            Forms\Components\Hidden::make('servis_tipi')
                ->default($sabitServisTipi?->value ?? ServisTipi::ArizaliCihaz->value)
                ->dehydrated(),
            Forms\Components\Section::make("Servis kimliği")
                ->columns(['default' => 1, 'md' => 2, 'xl' => 5])
                ->schema([
                    Forms\Components\Select::make('oncelik')
                        ->label("Öncelik")
                        ->options(self::enumSecenekleri(Oncelik::class))
                        ->required()
                        ->default(fn (): string => self::varsayilanOncelik()),
                    Forms\Components\Select::make('servis_kanali')
                        ->label("Servis kanalı")
                        ->options(self::enumSecenekleri(ServisKanali::class))
                        ->required()
                        ->default(fn (): string => self::varsayilanServisKanali()),
                    Forms\Components\TextInput::make('fis_no')
                        ->label("Fiş no")
                        ->required()
                        ->default(fn (): string => self::sonrakiFisNo(self::gecerliFirmaId()))
                        ->maxLength(64),
                    Forms\Components\DateTimePicker::make('kabul_tarihi')
                        ->label("Kabul tarihi")
                        ->default(now())
                        ->required()
                        ->native(),
                    Forms\Components\Select::make('servis_durumu_id')
                        ->label("Servis durumu")
                        ->options(fn (): array => self::servisDurumuSecenekleri())
                        ->default(fn (): ?int => self::varsayilanYeniKayitDurumuId())
                        ->required()
                        ->native(),
                ]),
            Forms\Components\Section::make("Cari ve cihaz")
                ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                ->schema([
                    ...self::telefonAlanBilesenleri('musteri_tel', 'Cari telefon no'),
                     Forms\Components\Select::make('cari_id')
                         ->label("Cari Adı")
                         ->default(fn (): ?int => (int) request()->query('cari_id', 0) ?: null)
                         ->getSearchResultsUsing(fn (string $search): array => self::cariAramaSonuclari($search))
                         ->getOptionLabelUsing(fn ($value): ?string => self::cariSecenekEtiketi((int) $value))
                         ->searchable()
                         ->searchPrompt('Cari adı veya telefon ile ara')
                         ->noSearchResultsMessage('Eşleşen cari bulunamadı')
                         ->required()
                         ->live()
                         ->afterStateUpdated(function ($state, Set $set): void {
                            $set('gecmis_cihaz_id', null);

                            if (! $state) {
                                return;
                            }

                            $cari = self::cariKaydi((int) $state);
                            if (! $cari) {
                                return;
                            }

                            $set('musteri_tel', (string) ($cari->telefon ?: $cari->gsm ?: ''));
                            $set('tahsilat_para_birimi', strtoupper((string) ($cari->para_birimi ?: 'TRY')));
                        }),
                    Forms\Components\Select::make('gecmis_cihaz_id')
                        ->label('Cari cihazı / geçmiş kayıt')
                        ->default(fn (): ?int => (int) request()->query('kayitli_cihaz_id', 0) ?: null)
                        ->options(fn (Forms\Get $get): array => self::cariCihazSecenekleri((int) ($get('cari_id') ?? 0)))
                        ->placeholder('Cari seçtikten sonra cihaz seçin')
                        ->searchable()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, Set $set): void {
                            self::gecmisCihazSeciminiUygula((int) $state, $set);
                        })
                        ->suffixAction(
                            FormAction::make('gecmis_servis_kayitlari')
                                ->label('Cihazın servis kayıtlarını gör')
                                ->icon('heroicon-o-clock')
                                ->url(fn (Forms\Get $get): string => self::cariCihazServisKayitlariUrl($get))
                                ->openUrlInNewTab()
                                ->visible(fn (Forms\Get $get): bool => (int) ($get('cari_id') ?? 0) > 0
                                    && ((int) ($get('cihaz_id') ?? 0) > 0 || filled($get('model_no')) || filled($get('seri_no')))),
                        )
                        ->helperText(fn (Forms\Get $get): ?string => self::cariCihazGecmisiMetni(
                            (int) ($get('cari_id') ?? 0),
                            (int) ($get('cihaz_id') ?? 0),
                            (int) ($get('marka_id') ?? 0),
                            (string) ($get('model_no') ?? ''),
                            (string) ($get('seri_no') ?? ''),
                        ))
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                    Forms\Components\Placeholder::make('kayitli_cihaz_no')
                        ->label('Benzersiz cihaz numarası')
                        ->content(fn (?TeknikServisKaydi $record): string => $record?->kayitliCihaz?->cihaz_no ?? 'Kayıt oluşturulduktan sonra atanır'),
                    Forms\Components\Select::make('cihaz_id')
                        ->label("Cihaz")
                        ->default(fn (): ?int => (int) request()->query('cihaz_id', 0) ?: null)
                        ->extraFieldWrapperAttributes(['class' => 'teknik-servis-single-line-select'])
                        ->options(fn (): array => self::cihazSecenekleri())
                        ->native(),
                    Forms\Components\Select::make('marka_id')
                        ->label("Marka")
                        ->default(fn (): ?int => (int) request()->query('marka_id', 0) ?: null)
                        ->extraFieldWrapperAttributes(['class' => 'teknik-servis-single-line-select'])
                        ->options(fn (): array => self::markaSecenekleri())
                        ->native(),
                    Forms\Components\TextInput::make('model_no')
                        ->label("Model no")
                        ->default(fn (): ?string => request()->query('model_no'))
                        ->maxLength(128),
                    Forms\Components\TextInput::make('seri_no')
                        ->label("Seri no")
                        ->default(fn (): ?string => request()->query('seri_no'))
                        ->maxLength(128)
                        ->required(),
                ]),
            Forms\Components\Section::make("Arıza ve not")
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Hidden::make('ariza_id'),
                    Forms\Components\Select::make('arizalar')
                        ->label("Arıza tanımları")
                        ->multiple()
                        ->relationship(
                            name: 'arizalar',
                            titleAttribute: 'ad',
                            modifyQueryUsing: fn (Builder $query) => self::arizaSecimSorgusu($query)
                        )
                        ->pivotData([
                            'firma_id' => self::gecerliFirmaId(),
                        ])
                        ->saveRelationshipsUsing(function (TeknikServisKaydi $record, $state): void {
                            self::arizaIliskisiniKaydet($record, $state);
                        })
                        ->options(fn (): array => self::arizaSecenekleri())
                        ->afterStateHydrated(function ($state, Set $set): void {
                            $ids = array_values(array_filter((array) $state));
                            $set('ariza_id', $ids[0] ?? null);
                        })
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $ids = array_values(array_filter((array) $state));
                            $set('ariza_id', $ids[0] ?? null);
                        }),
                    Forms\Components\Textarea::make('musteri_sikayeti')
                        ->label("Müşteri şikayeti")
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('musteriye_gorunen_not')
                        ->label('Servis Notu')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Hidden::make('toplam_tutar')->default(0)->dehydrated(),
            Forms\Components\Hidden::make('odenen_tutar')->default(0)->dehydrated(),
            Forms\Components\Hidden::make('odeme_durumu')->default(OdemeDurumu::Odenmedi->value)->dehydrated(),
            Forms\Components\Hidden::make('musteri_onay_durumu')->default(fn (): string => self::varsayilanMusteriOnayDurumu())->dehydrated(),
            Forms\Components\Hidden::make('tahsilat_para_birimi')->default('TRY')->dehydrated(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private static function hizliDuzenlemeBilesenleri(): array
    {
        return [
            Forms\Components\Select::make('servis_durumu_id')
                ->label("Servis durumu")
                ->options(fn (): array => self::servisDurumuSecenekleri())
                ->default(fn (): ?int => self::varsayilanYeniKayitDurumuId())
                ->native()
                ->required(),
        ];
    }

    private static function formSecenekCache(): TeknikServisFormSecenekCache
    {
        return app(TeknikServisFormSecenekCache::class);
    }

    public static function servisDurumuAdi(int $durumId): ?string
    {
        $durum = self::servisDurumuOzeti($durumId);
        $ad = trim((string) ($durum['ad'] ?? ''));

        return $ad !== '' ? $ad : null;
    }

    /**
     * @return array<int,string>
     */
    public static function hizliServisDurumuSecenekleri(): array
    {
        return self::servisDurumuSecenekleri();
    }

    private static function bakimTarihiAta(Set $set, int $ay): void
    {
        $tarih = Carbon::today()->addMonthsNoOverflow($ay);
        $set('bakim_tarihi', $tarih->format('Y-m-d'));
        $set('bakim_periyot_ay', $ay);
    }

    /**
     * @return array<int,string>
     */
    private static function servisDurumuSecenekleri(): array
    {
        return collect(self::servisDurumuOzetleri())
            ->mapWithKeys(static fn (array $durum, int $id): array => [$id => $durum['ad']])
            ->all();
    }

    /**
     * @return array<int, array{ad:string,kod:string}>
     */
    private static function servisDurumuOzetleri(): array
    {
        return self::tanimSecenekGruplari()[TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU];
    }

    /**
     * @return array<int,string>
     */
    private static function cihazSecenekleri(): array
    {
        return self::tanimSecenekGruplari()[TeknikServisFormSecenekCache::GROUP_CIHAZ];
    }

    /**
     * @return array<int,string>
     */
    private static function markaSecenekleri(): array
    {
        return self::tanimSecenekGruplari()[TeknikServisFormSecenekCache::GROUP_MARKA];
    }

    /**
     * @return array<int,string>
     */
    private static function aksesuarSecenekleri(): array
    {
        return self::tanimSecenekGruplari()[TeknikServisFormSecenekCache::GROUP_AKSESUAR];
    }

    /**
     * @return array<int,string>
     */
    private static function arizaSecenekleri(): array
    {
        return self::tanimSecenekGruplari()[TeknikServisFormSecenekCache::GROUP_ARIZA];
    }

    private static function tanimSecenekAdi(string $grup, int $id): ?string
    {
        if ($id < 1) {
            return null;
        }

        $deger = self::tanimSecenekGruplari()[$grup][$id] ?? null;
        if (is_array($deger)) {
            $deger = $deger['ad'] ?? null;
        }

        $ad = trim((string) $deger);

        return $ad !== '' ? $ad : null;
    }

    /**
     * @param  class-string  $modelSinifi
     * @return array<int,string>
     */
    private static function tanimModelAramaSonuclari(string $modelSinifi, string $search): array
    {
        $aranan = trim($search);

        return $modelSinifi::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNull('firma_id')
            ->when($aranan !== '', fn (Builder $q): Builder => $q->where('ad', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $aranan).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @param  class-string  $modelSinifi
     */
    private static function tanimModelAdi(string $modelSinifi, mixed $id): ?string
    {
        $id = (int) $id;
        if ($id < 1) {
            return null;
        }

        $adlar = self::tanimModelAdlari($modelSinifi, [$id]);

        return $adlar[$id] ?? null;
    }

    /**
     * @param  class-string  $modelSinifi
     * @param  array<int|string,mixed>  $ids
     * @return array<int,string>
     */
    private static function tanimModelAdlari(string $modelSinifi, array $ids): array
    {
        $idListe = collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($idListe === []) {
            return [];
        }

        $cacheKey = 'tanim-model-adlari|'.$modelSinifi;
        $cache = self::$secenekCache[$cacheKey] ?? [];
        $eksikIdler = array_values(array_diff($idListe, array_map('intval', array_keys($cache))));

        if ($eksikIdler !== []) {
            $cache += $modelSinifi::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $eksikIdler)
                ->pluck('ad', 'id')
                ->all();

            self::$secenekCache[$cacheKey] = $cache;
        }

        return array_intersect_key($cache, array_flip($idListe));
    }

    /**
     * @return array{
     *     servis_durumu: array<int, array{ad:string,kod:string}>,
     *     cihaz: array<int,string>,
     *     marka: array<int,string>,
     *     aksesuar: array<int,string>,
     *     ariza: array<int,string>
     * }
     */
    private static function tanimSecenekGruplari(): array
    {
        return self::formSecenekCache()->remember(
            TeknikServisFormSecenekCache::GROUP_TANIM_SECENEKLERI,
            'global',
            function (): array {
                $hamGruplar = [
                    TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU => [],
                    TeknikServisFormSecenekCache::GROUP_CIHAZ => [],
                    TeknikServisFormSecenekCache::GROUP_MARKA => [],
                    TeknikServisFormSecenekCache::GROUP_AKSESUAR => [],
                    TeknikServisFormSecenekCache::GROUP_ARIZA => [],
                ];

                foreach (self::tanimSecenekSatirlari() as $satir) {
                    $grup = (string) $satir->grup;

                    if (! array_key_exists($grup, $hamGruplar)) {
                        continue;
                    }

                    $hamGruplar[$grup][] = [
                        'id' => (int) $satir->id,
                        'ad' => (string) $satir->ad,
                        'kod' => (string) ($satir->kod ?? ''),
                        'siralama' => (int) ($satir->siralama ?? 0),
                    ];
                }

                $sonuc = [
                    TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU => [],
                    TeknikServisFormSecenekCache::GROUP_CIHAZ => [],
                    TeknikServisFormSecenekCache::GROUP_MARKA => [],
                    TeknikServisFormSecenekCache::GROUP_AKSESUAR => [],
                    TeknikServisFormSecenekCache::GROUP_ARIZA => [],
                ];

                foreach ($hamGruplar as $grup => $satirlar) {
                    usort($satirlar, static function (array $sol, array $sag) use ($grup): int {
                        if ($grup === TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU) {
                            return [$sol['siralama'], $sol['ad'], $sol['id']] <=> [$sag['siralama'], $sag['ad'], $sag['id']];
                        }

                        return [$sol['ad'], $sol['id']] <=> [$sag['ad'], $sag['id']];
                    });

                    foreach ($satirlar as $satir) {
                        if ($grup === TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU) {
                            $sonuc[$grup][$satir['id']] = [
                                'ad' => $satir['ad'],
                                'kod' => $satir['kod'],
                            ];

                            continue;
                        }

                        $sonuc[$grup][$satir['id']] = $satir['ad'];
                    }
                }

                return $sonuc;
            }
        );
    }

    private static function tanimSecenekSatirlari(): iterable
    {
        $sorgu = DB::table('teknik_servis_tanim_servis_durumlari')
            ->selectRaw("'".TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU."' as grup, id, ad, kod, siralama")
            ->whereNull('firma_id');

        foreach ([
            TeknikServisFormSecenekCache::GROUP_CIHAZ => 'teknik_servis_tanim_cihazlar',
            TeknikServisFormSecenekCache::GROUP_MARKA => 'teknik_servis_tanim_markalar',
            TeknikServisFormSecenekCache::GROUP_AKSESUAR => 'teknik_servis_tanim_aksesuarlar',
            TeknikServisFormSecenekCache::GROUP_ARIZA => 'teknik_servis_tanim_arizalar',
        ] as $grup => $tablo) {
            $sorgu->unionAll(
                DB::table($tablo)
                    ->selectRaw("'".$grup."' as grup, id, ad, kod, siralama")
                    ->whereNull('deleted_at')
                    ->whereNull('firma_id')
            );
        }

        return DB::query()
            ->fromSub($sorgu, 'tanimlar')
            ->get(['grup', 'id', 'ad', 'kod', 'siralama']);
    }

    private static function servisDurumuKaydi(int $durumId, bool $withoutGlobalScopes = false): ?TeknikServisDurumTanimi
    {
        if ($durumId < 1) {
            return null;
        }

        $cacheKey = ($withoutGlobalScopes ? 'without' : 'scoped').'|'.$durumId;

        if (! array_key_exists($cacheKey, self::$servisDurumuKaydiCache)) {
            $query = TeknikServisDurumTanimi::query();

            if ($withoutGlobalScopes) {
                $query->withoutGlobalScopes();
            }

            self::$servisDurumuKaydiCache[$cacheKey] = $query->find($durumId);
        }

        return self::$servisDurumuKaydiCache[$cacheKey];
    }

    /**
     * @return array{ad:string,kod:string}|null
     */
    private static function servisDurumuOzeti(int $durumId, bool $withoutGlobalScopes = false): ?array
    {
        if ($durumId < 1) {
            return null;
        }

        $globalOzetler = self::servisDurumuOzetleri();
        if (array_key_exists($durumId, $globalOzetler)) {
            return $globalOzetler[$durumId];
        }

        $cacheKey = ($withoutGlobalScopes ? 'without' : 'scoped').'|'.$durumId;
        if (! array_key_exists($cacheKey, self::$servisDurumuOzetiCache)) {
            $query = TeknikServisDurumTanimi::query();

            if ($withoutGlobalScopes) {
                $query->withoutGlobalScopes();
            }

            $durum = $query->find($durumId, ['id', 'ad', 'kod']);

            self::$servisDurumuOzetiCache[$cacheKey] = $durum ? [
                'ad' => (string) $durum->ad,
                'kod' => (string) ($durum->kod ?? ''),
            ] : null;
        }

        return self::$servisDurumuOzetiCache[$cacheKey];
    }

    private static function servisDurumunaGoreEkBolumleriAyarla($durumId, Set $set): void
    {
        $bayraklar = self::servisDurumuEkBolumBayraklari((int) $durumId);

        foreach ($bayraklar as $alan => $aktif) {
            $set($alan, $aktif);
        }
    }

    /**
     * @return array<string,bool>
     */
    private static function servisDurumuEkBolumBayraklari(int $durumId): array
    {
        $kapali = [
            'stok_kalemlerini_goster' => false,
            'muhasebe_kayitlarini_goster' => false,
            'garanti_gorev_goster' => false,
            'teklif_onay_goster' => false,
            'teslim_bilgilerini_goster' => false,
        ];

        $durum = self::servisDurumuOzeti($durumId, true);
        if (! $durum || self::durumYeniKayitMi($durum)) {
            return $kapali;
        }

        $durumKaydi = self::servisDurumuKaydi($durumId, true);
        $kod = (string) ($durum['kod'] ?? '');
        $ad = (string) ($durum['ad'] ?? '');

        $fiyatVerilenMi = (bool) ($durumKaydi?->is_fiyat_verildi ?? false)
            || $kod === TeknikServisDurumKodlari::FIYAT_VERILDI
            || $ad === 'Fiyat Verilen';

        $teslimBekleyenMi = in_array($kod, [TeknikServisDurumKodlari::TESLIM_BEKLEYEN, TeknikServisDurumKodlari::TESLIM_BEKLIYOR], true)
            || $ad === 'Teslim Bekleyen';

        $teslimEdilenMi = (bool) ($durumKaydi?->is_teslim_edildi ?? false)
            || $kod === TeknikServisDurumKodlari::TESLIM_EDILDI
            || $ad === 'Teslim Edilen';

        $tamamlananMi = in_array($kod, [TeknikServisDurumKodlari::TAMAMLANDI, TeknikServisDurumKodlari::DIS_SERVIS_TAMAMLANDI], true)
            || in_array($ad, ['Tamamlandı', 'Tamamlandi', 'Dış Servis Tamamlandı', 'Dis Servis Tamamlandi'], true);

        $garantiyeGonderilenMi = $kod === TeknikServisDurumKodlari::GARANTIYE_GONDERILDI
            || $ad === 'Garantiye Gönderilen';

        $iptalVeyaIadeMi = (bool) ($durumKaydi?->is_iptal ?? false)
            || (bool) ($durumKaydi?->is_iade ?? false)
            || in_array($kod, [TeknikServisDurumKodlari::IPTAL, TeknikServisDurumKodlari::IADE], true)
            || in_array($ad, ['İptal', 'Iptal', 'İade', 'Iade'], true);

        if ($iptalVeyaIadeMi) {
            return [
                'stok_kalemlerini_goster' => true,
                'muhasebe_kayitlarini_goster' => true,
                'garanti_gorev_goster' => true,
                'teklif_onay_goster' => true,
                'teslim_bilgilerini_goster' => true,
            ];
        }

        $stokVeTahsilatGerekliMi = $teslimBekleyenMi || $teslimEdilenMi || $tamamlananMi;

        return [
            'stok_kalemlerini_goster' => $stokVeTahsilatGerekliMi,
            'muhasebe_kayitlarini_goster' => $stokVeTahsilatGerekliMi,
            'garanti_gorev_goster' => $garantiyeGonderilenMi || $teslimBekleyenMi || $teslimEdilenMi,
            'teklif_onay_goster' => $fiyatVerilenMi || $teslimBekleyenMi || $teslimEdilenMi,
            'teslim_bilgilerini_goster' => $teslimEdilenMi,
        ];
    }

    /**
     * @param array{ad:string,kod:string} $durum
     */
    private static function durumYeniKayitMi(array $durum): bool
    {
        return in_array((string) ($durum['kod'] ?? ''), [TeknikServisDurumKodlari::YENI, TeknikServisDurumKodlari::YENI_ESKI], true)
            || in_array((string) ($durum['ad'] ?? ''), ['Yeni Kayıt', 'Yeni Servis'], true);
    }

    /**
     * @return array<string,string>
     */
    private static function whatsappSablonSecenekleri(): array
    {
        $secenekler = [];

        foreach (self::whatsappSablonlari() as $kod => $sablon) {
            $secenekler[$kod] = $sablon['ad'];
        }

        return $secenekler;
    }

    /**
     * @return array<string, array{ad:string,mesaj:string}>
     */
    private static function whatsappSablonlari(): array
    {
        return self::formSecenekCache()->remember(
            TeknikServisFormSecenekCache::GROUP_MESAJ_SABLONU,
            'whatsapp|firma:'.self::gecerliFirmaId(),
            static fn (): array => TeknikServisMesajSablonu::query()
                ->where('kanal', 'whatsapp')
                ->where('aktif', true)
                ->orderBy('siralama')
                ->orderBy('ad')
                ->get(['kod', 'ad', 'mesaj'])
                ->mapWithKeys(static fn (TeknikServisMesajSablonu $sablon): array => [
                    (string) $sablon->kod => [
                        'ad' => (string) $sablon->ad,
                        'mesaj' => (string) $sablon->mesaj,
                    ],
                ])
                ->all()
        );
    }

    private static function whatsappSablonMesaji(string $kod): ?string
    {
        $sablonlar = self::whatsappSablonlari();

        return $sablonlar[$kod]['mesaj']
            ?? $sablonlar['teslim_bekleyen_mesaji']['mesaj']
            ?? null;
    }

    private static function teslimBekleyenDurumuSeciliMi(Forms\Get $get): bool
    {
        $durumId = (int) ($get('servis_durumu_id') ?? 0);
        if ($durumId <= 0) {
            return false;
        }

        $durum = self::servisDurumuOzeti($durumId);
        if (! $durum) {
            return false;
        }

        return in_array((string) ($durum['kod'] ?? ''), [TeknikServisDurumKodlari::TESLIM_BEKLEYEN, TeknikServisDurumKodlari::TESLIM_BEKLIYOR], true)
            || (string) ($durum['ad'] ?? '') === 'Teslim Bekleyen';
    }

    private static function teslimBekleyenWhatsappUrlFromFormState(Forms\Get $get): ?string
    {
        $sablonKodu = trim((string) ($get('whatsapp_sablon_kodu') ?? 'teslim_bekleyen_mesaji'));
        $telefon = self::normalizeTelefon(
            (string) ($get('musteri_tel') ?? '')
        );

        if (! $telefon) {
            $cari = self::cariKaydi((int) ($get('cari_id') ?? 0));
            $telefon = self::normalizeTelefon((string) ($cari?->telefon ?: $cari?->gsm ?: ''));
        }

        if (! $telefon) {
            return null;
        }

        $cari = self::cariKaydi((int) ($get('cari_id') ?? 0));
        $cihazAdi = self::tanimSecenekAdi(TeknikServisFormSecenekCache::GROUP_CIHAZ, (int) ($get('cihaz_id') ?? 0));
        $markaAdi = self::tanimSecenekAdi(TeknikServisFormSecenekCache::GROUP_MARKA, (int) ($get('marka_id') ?? 0));
        $arizaAdi = self::tanimSecenekAdi(TeknikServisFormSecenekCache::GROUP_ARIZA, (int) ($get('ariza_id') ?? 0));
        $stokKartlari = self::stokKartlariMetni((array) ($get('kalemler') ?? []));

        $mesaj = trim((string) (self::whatsappSablonMesaji($sablonKodu) ?? ''));
        if ($mesaj === '') {
            $mesaj = 'Merhaba Sayin Musterimiz, cihaziniza ait servis islemleri tamamlanmis olup cihaziniz teslime hazirdir.';
        }

        $mesaj = strtr($mesaj, [
            '{cari_ad}' => (string) ($get('musteri_ad_soyad') ?: $cari?->ad ?: '-'),
            '{cari_tel}' => (string) ($get('musteri_tel') ?: $cari?->telefon ?: $cari?->gsm ?: '-'),
            '{fis_no}' => (string) ($get('fis_no') ?: '-'),
            '{cihaz}' => (string) ($cihazAdi ?: '-'),
            '{marka_model}' => trim((string) (($markaAdi ?: '').' '.((string) ($get('model_no') ?? '')))) ?: '-',
            '{ariza_bilgisi}' => (string) ($arizaAdi ?: (string) ($get('musteri_sikayeti') ?? '-') ?: '-'),
            '{musteriye_gorunen_not}' => (string) ($get('musteriye_gorunen_not') ?: '-'),
            '{stok_kartlari}' => $stokKartlari,
            '{teslim_tarihi}' => now()->format('d.m.Y'),
        ]);

        return 'https://wa.me/'.$telefon.'?text='.urlencode($mesaj);
    }

    /**
     * @param array<int,mixed> $kalemler
     */
    private static function stokKartlariMetni(array $kalemler): string
    {
        $satirlar = [];

        foreach ($kalemler as $kalem) {
            if (! is_array($kalem)) {
                continue;
            }

            $stokId = (int) ($kalem['stok_id'] ?? 0);
            $aciklama = trim((string) ($kalem['aciklama'] ?? ''));
            $miktar = (float) ($kalem['miktar'] ?? 0);

            $stokAdi = '';
            if ($stokId > 0) {
                $stok = self::stokKaydi($stokId);
                $stokAdi = trim((string) ($stok?->ad ?? ''));
            }

            $ad = $stokAdi !== '' ? $stokAdi : $aciklama;
            if ($ad === '') {
                continue;
            }

            $satirlar[] = $ad.($miktar > 0 ? ' x'.rtrim(rtrim(number_format($miktar, 2, '.', ''), '0'), '.') : '');
        }

        return $satirlar !== [] ? implode(', ', $satirlar) : '-';
    }

    private static function normalizeTelefon(string $telefon): ?string
    {
        $telefon = preg_replace('/\D+/', '', $telefon) ?? '';
        if ($telefon === '') {
            return null;
        }

        if (str_starts_with($telefon, '0')) {
            $telefon = '90'.substr($telefon, 1);
        } elseif (! str_starts_with($telefon, '90')) {
            $telefon = '90'.$telefon;
        }

        return strlen($telefon) >= 11 ? $telefon : null;
    }

    private static function telefonDogrulamaKurali(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            $telefon = trim((string) ($value ?? ''));

            if ($telefon === '') {
                return;
            }

            $rakamlar = preg_replace('/\D+/', '', $telefon) ?? '';

            if ($rakamlar === '') {
                $fail('Telefon numarası geçerli formatta olmalıdır.');

                return;
            }

            $gecerliUzunluk = match (true) {
                str_starts_with($rakamlar, '0090') => strlen($rakamlar) === 14,
                str_starts_with($rakamlar, '90') => strlen($rakamlar) === 12,
                str_starts_with($rakamlar, '0') => strlen($rakamlar) === 11,
                default => strlen($rakamlar) === 10,
            };

            if (! $gecerliUzunluk) {
                $fail('Telefon numarası geçerli formatta olmalıdır.');
            }
        };
    }

    /**
     * @return array<int, Component>
     */
    private static function telefonAlanBilesenleri(string $alan, string $etiket, bool $zorunlu = false): array
    {
        return [
            Forms\Components\Hidden::make($alan.'_ulke_kodu')
                ->default('+90')
                ->dehydrated(false),
            Forms\Components\TextInput::make($alan)
                ->label($etiket)
                ->key($alan.'_input')
                ->type('text')
                ->inputMode('tel')
                ->placeholder('(555) 000 11 22')
                ->maxLength(32)
                // Cari eşleşmesi son rakam girildiği anda çalışmalı; sorgu yalnızca
                // normalize edilmiş ve yeterli uzunluktaki numaralarda yapılır.
                ->live(debounce: $alan === 'musteri_tel' ? 200 : 75)
                ->extraAlpineAttributes(fn (Forms\Get $get): array => [
                    'x-on:input' => self::telefonInputFormatterJs(
                        (string) ($get($alan.'_ulke_kodu') ?: '+90')
                    ),
                    'x-on:blur' => self::telefonInputFormatterJs(
                        (string) ($get($alan.'_ulke_kodu') ?: '+90')
                    ),
                ])
                ->prefixAction(
                    FormAction::make($alan.'_ulke_kodu_sec')
                        ->label(fn (Forms\Get $get): string => trim((string) ($get($alan.'_ulke_kodu') ?: '+90')) ?: '+90')
                        ->button()
                        ->color('gray')
                        ->icon('heroicon-m-chevron-down')
                        ->iconPosition('after')
                        ->visible(true)
                        ->tooltip('Ülke kodunu değiştir')
                        ->modalHeading('Ülke kodu seç')
                        ->fillForm(fn (Forms\Get $get): array => [
                            'ulke_kodu' => (string) ($get($alan.'_ulke_kodu') ?: '+90'),
                        ])
                        ->form([
                            Forms\Components\Select::make('ulke_kodu')
                                ->label('Ülke / Kod')
                                ->options(self::telefonUlkeKoduSecenekleri())
                                ->default('+90')
                                ->native()
                                ->required(),
                        ])
                        ->action(function (array $data, Set $set, Forms\Get $get) use ($alan): void {
                            $ulkeKodu = (string) ($data['ulke_kodu'] ?? '+90');

                            $set($alan.'_ulke_kodu', $ulkeKodu);
                            $set($alan, self::telefonYerelGorunumuneFormatla(
                                (string) ($get($alan) ?? ''),
                                $ulkeKodu
                            ));
                        }),
                    true
                )
                ->afterStateHydrated(function (Set $set, ?string $state) use ($alan): void {
                    self::telefonAlaniniDoldur($set, $alan, (string) ($state ?? ''));
                })
                ->afterStateUpdated(function (?string $state, Set $set, Forms\Get $get, Component $component) use ($alan): void {
                    if ($alan === 'telefon') {
                        self::hizliCariEslesmesiniUygula($set, (string) ($state ?? ''), $component);

                        return;
                    }

                    if ($alan !== 'musteri_tel') {
                        return;
                    }

                    self::telefonCariSeciminiOner($set, (string) ($state ?? ''), $get);
                })
                ->dehydrateStateUsing(fn (?string $state, Forms\Get $get): ?string => self::telefonuBirlestir(
                    (string) ($get($alan.'_ulke_kodu') ?: '+90'),
                    $state
                ))
                ->rule(fn (Forms\Get $get): \Closure => self::telefonDogrulamaKuraliUlkeyeGore(
                    (string) ($get($alan.'_ulke_kodu') ?: '+90')
                ))
                ->validationMessages(self::telefonDogrulamaMesajlari())
                ->required($zorunlu),
        ];
    }

    private static function hizliCariEslesmesiniUygula(Set $set, string $telefon, Component $component): void
    {
        $telefon = self::normalizeTelefon($telefon);
        if (! $telefon) {
            return;
        }

        $telefonCarileri = self::cariTelefonSonuclari($telefon);
        if ($telefonCarileri->count() !== 1) {
            return;
        }

        $cari = $telefonCarileri->first();
        self::cariBulunduBildiriminiGonder($cari);

        // Inline cari modalının içinden ana servis formundaki cari seçimini güncelle.
        $set('data.cari_id', (int) $cari->getKey(), true);
        $set('data.musteri_tel_ulke_kodu', '+90', true);
        $set('data.musteri_tel', self::telefonYerelGorunumuneFormatla(
            (string) ($cari->telefon ?: $cari->gsm ?: $telefon),
            '+90'
        ), true);
        $set('data.tahsilat_para_birimi', strtoupper((string) ($cari->para_birimi ?: 'TRY')), true);

        $livewire = $component->getLivewire();
        if (method_exists($livewire, 'unmountFormComponentAction')) {
            $livewire->unmountFormComponentAction(shouldCancelParentActions: false, shouldCloseModal: true);
        }
    }

    private static function telefonAlaniniDoldur(Set $set, string $alan, string $telefon): void
    {
        $ayristirilmis = self::telefonuAyristir($telefon);

        $set($alan.'_ulke_kodu', $ayristirilmis['ulke_kodu']);
        $set($alan, $ayristirilmis['yerel_numara']);
    }

    /**
     * Cari hızlı ekleme penceresini ana servis formundaki telefonla başlatır.
     *
     * @return array<string, string>
     */
    private static function hizliCariTelefonFormVerisi(mixed $livewire): array
    {
        $telefon = trim((string) data_get($livewire, 'data.musteri_tel', ''));
        if ($telefon === '') {
            return [];
        }

        $ayristirilmis = self::telefonuAyristir($telefon);

        return [
            'telefon_ulke_kodu' => $ayristirilmis['ulke_kodu'],
            'telefon' => $ayristirilmis['yerel_numara'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function telefonuAyristir(?string $telefon): array
    {
        $telefon = trim((string) ($telefon ?? ''));
        $rakamlar = preg_replace('/\D+/', '', $telefon) ?? '';

        if ($rakamlar === '') {
            return [
                'ulke_kodu' => '+90',
                'yerel_numara' => '',
            ];
        }

        if (str_starts_with($rakamlar, '0090')) {
            $rakamlar = substr($rakamlar, 2);
        } elseif (str_starts_with($rakamlar, '0') && strlen($rakamlar) === 11) {
            $rakamlar = '90'.substr($rakamlar, 1);
        } elseif (! str_starts_with($rakamlar, '90') && strlen($rakamlar) === 10) {
            $rakamlar = '90'.$rakamlar;
        }

        foreach (array_keys(self::telefonUlkeKoduSecenekleri()) as $ulkeKodu) {
            $ulkeRakam = ltrim($ulkeKodu, '+');

            if (str_starts_with($rakamlar, $ulkeRakam) && strlen($rakamlar) > strlen($ulkeRakam)) {
                return [
                    'ulke_kodu' => $ulkeKodu,
                    'yerel_numara' => self::telefonYerelGorunumuneFormatla(substr($rakamlar, strlen($ulkeRakam)), $ulkeKodu),
                ];
            }
        }

        return [
            'ulke_kodu' => '+90',
            'yerel_numara' => self::telefonYerelGorunumuneFormatla($rakamlar, '+90'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function telefonUlkeKoduSecenekleri(): array
    {
        return [
            '+90' => 'Türkiye (+90)',
            '+49' => 'Almanya (+49)',
            '+1' => 'ABD / Kanada (+1)',
            '+44' => 'Birleşik Krallık (+44)',
            '+31' => 'Hollanda (+31)',
            '+33' => 'Fransa (+33)',
            '+43' => 'Avusturya (+43)',
            '+32' => 'Belçika (+32)',
            '+41' => 'İsviçre (+41)',
            '+7' => 'Rusya / Kazakistan (+7)',
            '+994' => 'Azerbaycan (+994)',
            '+359' => 'Bulgaristan (+359)',
            '+357' => 'Kıbrıs (+357)',
            '+98' => 'İran (+98)',
            '+964' => 'Irak (+964)',
            '+966' => 'Suudi Arabistan (+966)',
            '+971' => 'Birleşik Arap Emirlikleri (+971)',
            '+30' => 'Yunanistan (+30)',
            '+39' => 'İtalya (+39)',
            '+995' => 'Gürcistan (+995)',
        ];
    }

    private static function telefonDogrulamaKuraliUlkeyeGore(string $ulkeKodu): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($ulkeKodu): void {
            $telefon = trim((string) ($value ?? ''));

            if ($telefon === '') {
                return;
            }

            $rakamlar = preg_replace('/\D+/', '', $telefon) ?? '';

            if ($rakamlar === '') {
                $fail('Telefon numarası geçerli formatta olmalıdır.');

                return;
            }

            if ($ulkeKodu === '+90' && strlen($rakamlar) !== 10) {
                $fail('Telefon numarası `555 000 11 22` formatında olmalıdır.');

                return;
            }

            if ($ulkeKodu !== '+90' && (strlen($rakamlar) < 4 || strlen($rakamlar) > 15)) {
                $fail('Telefon numarası geçerli formatta olmalıdır.');
            }
        };
    }

    private static function telefonGorunumuneFormatla(?string $telefon): ?string
    {
        $telefon = trim((string) ($telefon ?? ''));

        if ($telefon === '') {
            return null;
        }

        $rakamlar = preg_replace('/\D+/', '', $telefon) ?? '';

        if ($rakamlar === '') {
            return $telefon;
        }

        if (str_starts_with($rakamlar, '0090')) {
            $rakamlar = substr($rakamlar, 2);
        } elseif (str_starts_with($rakamlar, '0')) {
            $rakamlar = '90'.substr($rakamlar, 1);
        } elseif (! str_starts_with($rakamlar, '90') && strlen($rakamlar) === 10) {
            $rakamlar = '90'.$rakamlar;
        }

        if (strlen($rakamlar) !== 12 || ! str_starts_with($rakamlar, '90')) {
            return $telefon;
        }

        $ulkeKodu = substr($rakamlar, 0, 2);
        $alanKodu = substr($rakamlar, 2, 3);
        $ilkBlok = substr($rakamlar, 5, 3);
        $ikinciBlok = substr($rakamlar, 8, 2);
        $ucuncuBlok = substr($rakamlar, 10, 2);

        return sprintf('+%s (%s) %s %s %s', $ulkeKodu, $alanKodu, $ilkBlok, $ikinciBlok, $ucuncuBlok);
    }

    private static function telefonYerelGorunumuneFormatla(?string $telefon, string $ulkeKodu = '+90'): string
    {
        $telefon = trim((string) ($telefon ?? ''));
        $rakamlar = preg_replace('/\D+/', '', $telefon) ?? '';

        if ($rakamlar === '') {
            return '';
        }

        $ulkeRakam = ltrim($ulkeKodu, '+');

        if (str_starts_with($rakamlar, '00'.$ulkeRakam)) {
            $rakamlar = substr($rakamlar, 2 + strlen($ulkeRakam));
        } elseif (str_starts_with($rakamlar, $ulkeRakam) && strlen($rakamlar) > strlen($ulkeRakam)) {
            $rakamlar = substr($rakamlar, strlen($ulkeRakam));
        } elseif ($ulkeKodu === '+90' && str_starts_with($rakamlar, '0')) {
            $rakamlar = substr($rakamlar, 1);
        }

        if ($ulkeKodu !== '+90') {
            return trim(chunk_split(substr($rakamlar, 0, 15), 3, ' '));
        }

        $rakamlar = substr($rakamlar, 0, 10);
        $uzunluk = strlen($rakamlar);

        if ($uzunluk <= 3) {
            return $rakamlar;
        }

        if ($uzunluk <= 6) {
            return '('.substr($rakamlar, 0, 3).') '.substr($rakamlar, 3);
        }

        if ($uzunluk <= 8) {
            return '('.substr($rakamlar, 0, 3).') '.substr($rakamlar, 3, 3).' '.substr($rakamlar, 6);
        }

        return '('.substr($rakamlar, 0, 3).') '.substr($rakamlar, 3, 3).' '.substr($rakamlar, 6, 2).' '.substr($rakamlar, 8);
    }

    private static function telefonuBirlestir(string $ulkeKodu, ?string $telefon): ?string
    {
        $yerel = self::telefonYerelGorunumuneFormatla($telefon, $ulkeKodu);

        if ($yerel === '') {
            return null;
        }

        return $ulkeKodu.' '.$yerel;
    }

    private static function telefonMaskesi(string $ulkeKodu): RawJs
    {
        if ($ulkeKodu === '+90') {
            return RawJs::make(<<<'JS'
$input.startsWith('(')
    ? '(999) 999 99 99'
    : '9999999999'
JS);
        }

        return RawJs::make(<<<'JS'
'999 999 999 999999'
JS);
    }

    private static function telefonInputFormatterJs(string $ulkeKodu): string
    {
        if ($ulkeKodu !== '+90') {
            return <<<'JS'
let digits = ($el.value || '').replace(/\D+/g, '').slice(0, 15);
let parts = digits.match(/.{1,3}/g) || [];
$el.value = parts.join(' ');
JS;
        }

        return <<<'JS'
let digits = ($el.value || '').replace(/\D+/g, '').slice(0, 10);
// Yalnızca baştaki sıfırları kaldır; numaranın içindeki sıfırlara izin ver.
if (digits.startsWith('0')) {
    digits = digits.replace(/^0+/, '');
}
let formatted = '';

if (digits.length <= 3) {
    formatted = digits;
} else if (digits.length <= 6) {
    formatted = `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
} else if (digits.length <= 8) {
    formatted = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)} ${digits.slice(6)}`;
} else {
    formatted = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)} ${digits.slice(6, 8)} ${digits.slice(8, 10)}`;
}

$el.value = formatted;
JS;
    }

    /**
     * @return array<string, string>
     */
    private static function telefonDogrulamaMesajlari(): array
    {
        return [
            'required' => 'Telefon numarası zorunludur.',
        ];
    }

    private static function garantiBitisTarihiAta(Set $set, Forms\Get $get, int $ay): void
    {
        $referans = self::garantiReferansTarihi($get('garanti_baslangic_tarihi'));
        $tarih = $referans->copy()->addMonthsNoOverflow($ay);

        $set('garanti_bitis_tarihi', $tarih->format('Y-m-d'));
    }

    private static function garantiReferansTarihi(mixed $garantiBaslangicTarihi): Carbon
    {
        if (blank($garantiBaslangicTarihi)) {
            return Carbon::today();
        }

        return Carbon::parse((string) $garantiBaslangicTarihi)->startOfDay();
    }

    /**
     * @param  class-string<\BackedEnum>  $sinif
     * @return array<string, string>
     */
    private static function enumSecenekleri(string $sinif): array
    {
        $cikti = [];
        foreach ($sinif::cases() as $vaka) {
            $cikti[$vaka->value] = self::enumEtiketi($vaka);
        }

        return $cikti;
    }

    private static function enumEtiketi(\BackedEnum $vaka): string
    {
        return match ($vaka::class) {
            ServisTipi::class => match ($vaka) {
                ServisTipi::ArizaliCihaz => "Ar\u{0131}zal\u{0131} cihaz",
                ServisTipi::DisServis => "D\u{0131}\u{015F} servis",
                ServisTipi::Bakim => "Bak\u{0131}m",
            },
            Oncelik::class => match ($vaka) {
                Oncelik::Dusuk => "D\u{00FC}\u{015F}\u{00FC}k",
                Oncelik::Normal => "Normal",
                Oncelik::Acil => "Acil",
            },
            ServisKanali::class => match ($vaka) {
                ServisKanali::Magaza => "Ma\u{011F}aza",
                ServisKanali::Telefon => "Telefon",
                ServisKanali::Whatsapp => "WhatsApp",
                ServisKanali::Web => "Web",
                ServisKanali::Saha => "Saha",
            },
            MusteriOnayDurumu::class => match ($vaka) {
                MusteriOnayDurumu::Beklemede => "Beklemede",
                MusteriOnayDurumu::Onaylandi => "Onayland\u{0131}",
                MusteriOnayDurumu::Reddedildi => "Reddedildi",
            },
            OdemeDurumu::class => match ($vaka) {
                OdemeDurumu::Odenmedi => "\u{00D6}denmedi",
                OdemeDurumu::Kismi => "K\u{0131}smi",
                OdemeDurumu::Odendi => "\u{00D6}dendi",
                OdemeDurumu::Iade => "\u{0130}ade",
                OdemeDurumu::Iptal => "\u{0130}ptal",
            },
            default => $vaka->name,
        };
    }

    private static function servisTipiAlani(bool $olusturma, ?ServisTipi $sabitServisTipi): Component
    {
        if ($olusturma && $sabitServisTipi instanceof ServisTipi) {
            return Forms\Components\Hidden::make('servis_tipi')
                ->default($sabitServisTipi->value)
                ->required()
                ->dehydrated();
        }

        return Forms\Components\Select::make('servis_tipi')
            ->label("Servis tipi")
            ->options(self::enumSecenekleri(ServisTipi::class))
            ->required()
            ->live()
            ->disabled(fn () => $olusturma && $sabitServisTipi !== null)
            ->dehydrated();
    }

    private static function disServisTipiSeciliMi(Forms\Get $get, ?ServisTipi $sabitServisTipi = null): bool
    {
        $seciliDeger = (string) ($get('servis_tipi') ?? $sabitServisTipi?->value ?? '');

        return $seciliDeger === ServisTipi::DisServis->value;
    }

    private static function aktifFirmaId(): int
    {
        return self::$aktifFirmaIdCache ??= (int) app(TenantContextService::class)->aktifFirmaId();
    }

    private static function gecerliFirmaId(): int
    {
        if (self::$gecerliFirmaIdCache !== null) {
            return self::$gecerliFirmaIdCache;
        }

        $firmaId = self::aktifFirmaId();

        if ($firmaId > 0) {
            return self::$gecerliFirmaIdCache = $firmaId;
        }

        return self::$gecerliFirmaIdCache = (int) \App\Models\Firma::query()->orderBy('id')->value('id');
    }

    private static function cariSecimSorgusu(Builder $query): Builder
    {
        $query = $query->withoutGlobalScopes()->orderBy('ad');
        $firmaId = self::aktifFirmaId();

        if ($firmaId > 0) {
            $query->where('firma_id', $firmaId);
        }

        return $query;
    }

    private static function cariKaydi(int $cariId): ?Cari
    {
        if ($cariId < 1) {
            return null;
        }

        $cacheKey = self::aktifFirmaId().'|'.$cariId;

        if (! array_key_exists($cacheKey, self::$cariKaydiCache)) {
            self::$cariKaydiCache[$cacheKey] = self::cariSecimSorgusu(Cari::query())
                ->whereKey($cariId)
                ->first();
        }

        return self::$cariKaydiCache[$cacheKey];
    }

    private static function cariParaBirimi(int $cariId): ?string
    {
        if ($cariId < 1) {
            return null;
        }

        if (array_key_exists($cariId, self::$cariParaBirimiCache)) {
            return self::$cariParaBirimiCache[$cariId];
        }

        $cari = self::cariKaydi($cariId);

        return self::$cariParaBirimiCache[$cariId] = $cari ? strtoupper((string) ($cari->para_birimi ?: 'TRY')) : null;
    }

    /**
     * @return array<int, string>
     */
    /**
     * Cari seçildikten sonra daha önce servise alınmış cihazları listeler.
     * Eşleşme; seri numarası, cihaz türü, marka ve model alanlarının birlikte
     * aynı olmasına göre yapılır. Bu alanlardan biri farklıysa ayrı cihazdır.
     *
     * @return array<int, string>
     */
    private static function cariCihazSecenekleri(int $cariId): array
    {
        if ($cariId < 1) {
            return [];
        }

        $kayitlar = TeknikServisKaydi::query()
            ->where('firma_id', self::gecerliFirmaId())
            ->where('cari_id', $cariId)
            ->where(function (Builder $query): void {
                $query->whereNotNull('seri_no')
                    ->where('seri_no', '<>', '')
                    ->orWhereNotNull('model_no')
                    ->where('model_no', '<>', '')
                    ->orWhereNotNull('cihaz_id');
            })
            ->with(['cihaz:id,ad', 'marka:id,ad'])
            ->latest('kabul_tarihi')
            ->get();

        $secenekler = [];
        $gorulen = [];

        foreach ($kayitlar as $kayit) {
            $seriNo = trim((string) $kayit->seri_no);
            $modelNo = trim((string) $kayit->model_no);
            $anahtar = 'seri:'.mb_strtolower($seriNo, 'UTF-8')
                .'|cihaz:'.((int) $kayit->cihaz_id)
                .'|marka:'.((int) $kayit->marka_id)
                .'|model:'.mb_strtolower($modelNo, 'UTF-8');

            if (isset($gorulen[$anahtar])) {
                continue;
            }

            $gorulen[$anahtar] = true;
            $parcalar = array_filter([
                $kayit->cihaz?->ad,
                $kayit->marka?->ad,
                $modelNo !== '' ? 'Model: '.$modelNo : null,
                $seriNo !== '' ? 'Seri: '.$seriNo : null,
            ]);
            $secenekler[(int) $kayit->getKey()] = implode(' / ', $parcalar)
                .' — Son servis: '.optional($kayit->kabul_tarihi)->format('d.m.Y');
        }

        return $secenekler;
    }

    private static function gecmisCihazSeciminiUygula(int $servisKaydiId, Set $set): void
    {
        if ($servisKaydiId < 1) {
            return;
        }

        $kayit = TeknikServisKaydi::query()
            ->where('firma_id', self::gecerliFirmaId())
            ->find($servisKaydiId);

        if (! $kayit) {
            return;
        }

        $set('cihaz_id', $kayit->cihaz_id);
        $set('marka_id', $kayit->marka_id);
        $set('model_no', $kayit->model_no);
        $set('seri_no', $kayit->seri_no);
    }

    private static function cariCihazGecmisiMetni(int $cariId, int $cihazId, int $markaId, string $modelNo, string $seriNo): ?string
    {
        if ($cariId < 1 || ($cihazId < 1 && $markaId < 1 && trim($modelNo) === '' && trim($seriNo) === '')) {
            return null;
        }

        $sorgu = TeknikServisKaydi::query()
            ->where('firma_id', self::gecerliFirmaId())
            ->where('cari_id', $cariId)
            ->latest('kabul_tarihi');

        $sorgu->where('seri_no', trim($seriNo) !== '' ? trim($seriNo) : null)
            ->where('cihaz_id', $cihazId > 0 ? $cihazId : null)
            ->where('marka_id', $markaId > 0 ? $markaId : null)
            ->where('model_no', trim($modelNo) !== '' ? trim($modelNo) : null);

        $islemler = $sorgu->limit(5)->get(['kabul_tarihi', 'fis_no', 'yapilan_islemler'])
            ->map(function (TeknikServisKaydi $kayit): string {
                $islem = trim((string) $kayit->yapilan_islemler);

                return optional($kayit->kabul_tarihi)->format('d.m.Y').' / '.($kayit->fis_no ?: '-')
                    .' — '.($islem !== '' ? Str::limit($islem, 120) : 'İşlem notu girilmemiş');
            })
            ->implode(' | ');

        return $islemler !== '' ? 'Önceki işlemler: '.$islemler : null;
    }

    private static function cariCihazServisKayitlariUrl(Forms\Get $get): string
    {
        return TeknikServisKaydiKaynagi::getUrl('index', [
            'cari_id' => (int) ($get('cari_id') ?? 0),
            'cihaz_id' => (int) ($get('cihaz_id') ?? 0),
            'marka_id' => (int) ($get('marka_id') ?? 0),
            'model_no' => trim((string) ($get('model_no') ?? '')),
            'seri_no' => trim((string) ($get('seri_no') ?? '')),
        ]);
    }

    private static function cariAramaSonuclari(string $arama): array
    {
        $arama = trim($arama);

        return self::cariSecimSorgusu(Cari::query())
            ->when($arama !== '', function (Builder $query) use ($arama): void {
                $query->where(function (Builder $query) use ($arama): void {
                    $query->where('ad', 'like', '%'.$arama.'%')
                        ->orWhere('telefon', 'like', '%'.$arama.'%')
                        ->orWhere('gsm', 'like', '%'.$arama.'%')
                        ->orWhere('kod', 'like', '%'.$arama.'%');
                });
            })
            ->limit(25)
            ->get(['id', 'ad', 'telefon', 'gsm'])
            ->mapWithKeys(fn (Cari $cari): array => [
                (int) $cari->id => self::cariEtiketi($cari),
            ])
            ->all();
    }

    /**
     * Telefon yazıldığında tek bir cari eşleşiyorsa cari seçimini otomatik doldurur.
     */
    private static function telefonCariSeciminiOner(Set $set, string $telefon, ?Forms\Get $get = null): void
    {
        $telefon = self::normalizeTelefon($telefon);
        if (! $telefon) {
            $set('cari_id', null);
            return;
        }

        $cariler = self::cariTelefonSonuclari($telefon);
        if ($cariler->count() !== 1) {
            $set('cari_id', null);
            return;
        }

        $cari = $cariler->first();
        $cariId = (int) $cari->getKey();
        if ((int) ($get ? $get('cari_id') : 0) !== $cariId) {
            self::cariBulunduBildiriminiGonder($cari);
        }

        $set('cari_id', $cariId);
        $set('tahsilat_para_birimi', strtoupper((string) ($cari->para_birimi ?: 'TRY')));
    }

    private static function cariBulunduBildiriminiGonder(Cari $cari): void
    {
        $sayilar = self::cariServisKayitSayilari((int) $cari->getKey());

        Notification::make()
            ->info()
            ->title('Kayıtlı cari bulundu')
            ->body(
                (string) $cari->ad
                .' — '.((string) ($cari->telefon ?: $cari->gsm ?: 'Telefon bilgisi yok'))
                .' cari otomatik seçildi. Açık servis: '.$sayilar['acik']
                .' | Kapalı servis: '.$sayilar['kapali']
            )
            ->send();
    }

    /**
     * @return array{acik:int,kapali:int}
     */
    private static function cariServisKayitSayilari(int $cariId): array
    {
        if ($cariId < 1) {
            return ['acik' => 0, 'kapali' => 0];
        }

        $tablo = (new TeknikServisKaydi)->getTable();
        $durumTablo = (new \App\Models\TeknikServis\TeknikServisDurumTanimi)->getTable();
        $ozet = TeknikServisKaydi::query()
            ->leftJoin($durumTablo.' as durum', 'durum.id', '=', $tablo.'.servis_durumu_id')
            ->where($tablo.'.firma_id', self::gecerliFirmaId())
            ->where($tablo.'.cari_id', $cariId)
            ->selectRaw('COUNT(*) as toplam')
            ->selectRaw(
                'SUM(CASE WHEN durum.id IS NOT NULL'
                .' AND COALESCE(durum.is_teslim_edildi, 0) = 0'
                .' AND COALESCE(durum.is_iptal, 0) = 0'
                .' AND COALESCE(durum.is_iade, 0) = 0'
                .' THEN 1 ELSE 0 END) as acik'
            )
            ->first();

        $toplam = (int) ($ozet?->toplam ?? 0);
        $acik = (int) ($ozet?->acik ?? 0);

        return [
            'acik' => $acik,
            'kapali' => max(0, $toplam - $acik),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cari>
     */
    private static function cariTelefonSonuclari(string $telefon): \Illuminate\Support\Collection
    {
        $telefon = self::normalizeTelefon($telefon);
        if (! $telefon) {
            return collect();
        }

        if (array_key_exists($telefon, self::$cariTelefonSonuclariCache)) {
            return self::$cariTelefonSonuclariCache[$telefon];
        }

        $yerel = str_starts_with($telefon, '90') ? substr($telefon, 2) : $telefon;
        return self::$cariTelefonSonuclariCache[$telefon] = self::cariSecimSorgusu(Cari::query())
            ->where(function (Builder $query) use ($telefon, $yerel): void {
                $query
                    ->whereIn('telefon_normalize', [$telefon, '0'.$yerel, $yerel])
                    ->orWhereIn('gsm_normalize', [$telefon, '0'.$yerel, $yerel]);
            })
            ->limit(10)
            ->get(['id', 'ad', 'kod', 'telefon', 'gsm', 'para_birimi']);
    }

    public static function acikServisKaydiCariIcin(int $cariId, ?int $haricKayitId = null): ?TeknikServisKaydi
    {
        if ($cariId < 1) {
            return null;
        }

        return TeknikServisKaydi::query()
            ->where('firma_id', self::gecerliFirmaId())
            ->where('cari_id', $cariId)
            ->when($haricKayitId, fn (Builder $query): Builder => $query->where('id', '<>', $haricKayitId))
            ->whereHas('servisDurumu', function (Builder $query): void {
                $query->where('is_teslim_edildi', false)
                    ->where('is_iptal', false)
                    ->where('is_iade', false);
            })
            ->with('servisDurumu:id,ad')
            ->latest('id')
            ->first(['id', 'fis_no', 'servis_durumu_id']);
    }

    private static function cariSecenekEtiketi(int $cariId): ?string
    {
        $cari = self::cariKaydi($cariId);

        return $cari ? self::cariEtiketi($cari) : null;
    }

    private static function cariEtiketi(Cari $cari): string
    {
        $telefon = trim((string) ($cari->telefon ?: $cari->gsm ?: ''));

        return trim((string) $cari->ad.($telefon !== '' ? ' - '.$telefon : ''));
    }

    private static function stokSecimSorgusu(Builder $query, ?int $firmaId = null): Builder
    {
        $query = $query
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->orderBy('ad');
        $firmaId = $firmaId ?: self::gecerliFirmaId();

        if ($firmaId > 0) {
            $query->where('firma_id', $firmaId);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private static function stokSecenekleri(?int $firmaId = null): array
    {
        $firmaId = $firmaId ?: self::gecerliFirmaId();
        $cacheKey = 'stok|'.$firmaId;

        if (array_key_exists($cacheKey, self::$secenekCache)) {
            return self::$secenekCache[$cacheKey];
        }

        return self::$secenekCache[$cacheKey] = self::stokSecimSorgusu(StokKarti::query(), $firmaId)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [(int) $stok->getKey() => self::stokSecenekEtiketi($stok)])
            ->all();
    }

    private static function stokSecenekEtiketi(StokKarti $stok): string
    {
        $kod = trim((string) ($stok->kod ?? ''));
        $ad = trim((string) ($stok->ad ?? ''));

        if ($ad !== '' && $kod !== '') {
            return $ad.' ('.$kod.')';
        }

        return $ad !== '' ? $ad : $kod;
    }

    private static function stokKaydi(int $stokId, ?int $firmaId = null): ?StokKarti
    {
        if ($stokId < 1) {
            return null;
        }

        $firmaId = $firmaId ?: self::gecerliFirmaId();
        $cacheKey = $firmaId.'|'.$stokId;

        if (! array_key_exists($cacheKey, self::$stokKaydiCache)) {
            self::$stokKaydiCache[$cacheKey] = self::stokSecimSorgusu(StokKarti::query(), $firmaId)
                ->whereKey($stokId)
                ->first(['id', 'kod', 'ad', 'birim', 'satis_fiyati', 'kdv_orani', 'para_birimi']);
        }

        return self::$stokKaydiCache[$cacheKey];
    }

    /**
     * @param array<int, int|string|null> $stokIdleri
     */
    public static function stokKayitlariniCachele(array $stokIdleri, ?int $firmaId = null): void
    {
        $firmaId = $firmaId ?: self::gecerliFirmaId();
        $stokIdleri = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $stokIdleri),
            static fn (int $id): bool => $id > 0
        )));

        if ($stokIdleri === []) {
            return;
        }

        $eksikIdler = array_values(array_filter(
            $stokIdleri,
            static fn (int $id): bool => ! array_key_exists($firmaId.'|'.$id, self::$stokKaydiCache)
        ));

        if ($eksikIdler === []) {
            return;
        }

        $stoklar = self::stokSecimSorgusu(StokKarti::query(), $firmaId)
            ->whereKey($eksikIdler)
            ->get(['id', 'kod', 'ad', 'birim', 'satis_fiyati', 'kdv_orani', 'para_birimi'])
            ->keyBy(fn (StokKarti $stok): int => (int) $stok->getKey());

        foreach ($eksikIdler as $stokId) {
            self::$stokKaydiCache[$firmaId.'|'.$stokId] = $stoklar->get($stokId);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function stokAramaSonuclari(string $arama, ?int $firmaId = null): array
    {
        $arama = trim($arama);

        if ($arama === '') {
            return self::stokSecimSorgusu(StokKarti::query(), $firmaId)
                ->limit(50)
                ->get(['id', 'kod', 'ad'])
                ->mapWithKeys(fn (StokKarti $stok): array => [(int) $stok->getKey() => self::stokSecenekEtiketi($stok)])
                ->all();
        }

        return self::stokSecimSorgusu(StokKarti::query(), $firmaId)
            ->where(function (Builder $query) use ($arama): void {
                $query
                    ->where('ad', 'like', '%'.$arama.'%')
                    ->orWhere('kod', 'like', '%'.$arama.'%');
            })
            ->limit(50)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (StokKarti $stok): array => [(int) $stok->getKey() => self::stokSecenekEtiketi($stok)])
            ->all();
    }

    private static function stokSecenekEtiketiById(int $stokId, ?int $firmaId = null): ?string
    {
        if ($stokId < 1) {
            return null;
        }

        $stok = self::stokKaydi($stokId, $firmaId);

        return $stok instanceof StokKarti
            ? self::stokSecenekEtiketi($stok)
            : null;
    }

    private static function ikiSatirliAlanEtiketi(string $ilkSatir, ?string $ikinciSatir = null): HtmlString
    {
        $parcalar = array_values(array_filter([
            trim($ilkSatir),
            $ikinciSatir !== null ? trim($ikinciSatir) : '',
        ], fn (string $deger): bool => $deger !== ''));

        return new HtmlString('<span class="teknik-servis-inline-label">'.e(implode(' ', $parcalar)).'</span>');
    }

    private static function ikiSatirliZorunluAlanEtiketi(string $ilkSatir, ?string $ikinciSatir = null): HtmlString
    {
        $parcalar = array_values(array_filter([
            trim($ilkSatir),
            $ikinciSatir !== null ? trim($ikinciSatir) : '',
        ], fn (string $deger): bool => $deger !== ''));

        return new HtmlString(
            '<span class="teknik-servis-inline-label">'.e(implode(' ', $parcalar)).'<span style="color:#dc2626;">*</span></span>'
        );
    }

    private static function arizaSecimSorgusu(Builder $query): Builder
    {
        return $query
            ->withoutGlobalScopes()
            ->whereNull('teknik_servis_tanim_arizalar.deleted_at')
            ->whereNull('teknik_servis_tanim_arizalar.firma_id')
            ->orderBy('teknik_servis_tanim_arizalar.ad');
    }

    private static function aksesuarIliskisiniKaydet(TeknikServisKaydi $record, mixed $state): void
    {
        $secilenIdler = self::iliskiStateIdleri($state);
        $mevcutIdler = self::mevcutGlobalAksesuarIdleri($record);

        if (self::idListeleriAyniMi($mevcutIdler, $secilenIdler)) {
            return;
        }

        $iliskiler = $record->aksesuarlar();
        $cikarilacakIdler = array_values(array_diff($mevcutIdler, $secilenIdler));
        if ($cikarilacakIdler !== []) {
            $iliskiler->detach($cikarilacakIdler);
        }

        if ($secilenIdler !== []) {
            $iliskiler->syncWithPivotValues($secilenIdler, [
                'firma_id' => self::gecerliFirmaId(),
                'adet' => 1,
            ], detaching: false);
        }
    }

    private static function arizaIliskisiniKaydet(TeknikServisKaydi $record, mixed $state): void
    {
        $secilenIdler = self::iliskiStateIdleri($state);
        $mevcutIdler = self::mevcutGlobalArizaIdleri($record);

        if (self::idListeleriAyniMi($mevcutIdler, $secilenIdler)) {
            return;
        }

        $iliskiler = $record->arizalar();
        $cikarilacakIdler = array_values(array_diff($mevcutIdler, $secilenIdler));
        if ($cikarilacakIdler !== []) {
            $iliskiler->detach($cikarilacakIdler);
        }

        if ($secilenIdler !== []) {
            $iliskiler->syncWithPivotValues($secilenIdler, [
                'firma_id' => self::gecerliFirmaId(),
            ], detaching: false);
        }
    }

    /**
     * @return array<int,int>
     */
    private static function iliskiStateIdleri(mixed $state): array
    {
        $degerler = is_array($state) ? $state : ($state === null ? [] : [$state]);
        $idler = [];

        foreach ($degerler as $deger) {
            $id = (int) $deger;
            if ($id > 0) {
                $idler[$id] = $id;
            }
        }

        return array_values($idler);
    }

    /**
     * @return array<int,int>
     */
    private static function mevcutGlobalAksesuarIdleri(TeknikServisKaydi $record): array
    {
        return DB::table('teknik_servis_aksesuar_kayitlari')
            ->join(
                'teknik_servis_tanim_aksesuarlar',
                'teknik_servis_tanim_aksesuarlar.id',
                '=',
                'teknik_servis_aksesuar_kayitlari.aksesuar_id'
            )
            ->where('teknik_servis_aksesuar_kayitlari.teknik_servis_kaydi_id', (int) $record->getKey())
            ->whereNull('teknik_servis_tanim_aksesuarlar.deleted_at')
            ->whereNull('teknik_servis_tanim_aksesuarlar.firma_id')
            ->pluck('teknik_servis_aksesuar_kayitlari.aksesuar_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int,int>
     */
    private static function mevcutGlobalArizaIdleri(TeknikServisKaydi $record): array
    {
        return DB::table('teknik_servis_ariza_kayitlari')
            ->join(
                'teknik_servis_tanim_arizalar',
                'teknik_servis_tanim_arizalar.id',
                '=',
                'teknik_servis_ariza_kayitlari.ariza_id'
            )
            ->where('teknik_servis_ariza_kayitlari.teknik_servis_kaydi_id', (int) $record->getKey())
            ->whereNull('teknik_servis_tanim_arizalar.deleted_at')
            ->whereNull('teknik_servis_tanim_arizalar.firma_id')
            ->pluck('teknik_servis_ariza_kayitlari.ariza_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param array<int,int> $sol
     * @param array<int,int> $sag
     */
    private static function idListeleriAyniMi(array $sol, array $sag): bool
    {
        sort($sol);
        sort($sag);

        return $sol === $sag;
    }

    private static function varsayilanYeniKayitDurumuId(): ?int
    {
        $ayarDurumId = app(TeknikServisGenelAyarServisi::class)->varsayilanServisDurumuId(self::gecerliFirmaId());
        if ($ayarDurumId !== null) {
            return $ayarDurumId;
        }

        foreach (self::servisDurumuOzetleri() as $id => $durum) {
            $kod = (string) ($durum['kod'] ?? '');
            $ad = (string) ($durum['ad'] ?? '');

            if (
                in_array($kod, [TeknikServisDurumKodlari::YENI, TeknikServisDurumKodlari::YENI_ESKI], true)
                || $ad === "Yeni Kay\u{0131}t"
            ) {
                return (int) $id;
            }
        }

        $veri = self::formSecenekCache()->remember(
            TeknikServisFormSecenekCache::GROUP_SERVIS_DURUMU,
            'varsayilan_yeni',
            function (): array {
                $kayit = TeknikServisDurumTanimi::query()
                    ->withoutGlobalScopes()
                    ->whereNull('firma_id')
                    ->where(function (Builder $q): void {
                        $q->where('kod', TeknikServisDurumKodlari::YENI)
                            ->orWhere('kod', TeknikServisDurumKodlari::YENI_ESKI)
                            ->orWhere('ad', "Yeni Kay\u{0131}t")
                            ->orWhere('varsayilan_mi', true);
                    })
                    ->orderByDesc('varsayilan_mi')
                    ->orderBy('siralama')
                    ->first(['id']);

                return ['id' => $kayit ? (int) $kayit->getKey() : null];
            }
        );

        $id = is_array($veri) ? ($veri['id'] ?? null) : null;

        return is_numeric($id) ? (int) $id : null;
    }

    private static function varsayilanOncelik(): string
    {
        return app(TeknikServisGenelAyarServisi::class)->varsayilanOncelik(self::gecerliFirmaId());
    }

    private static function varsayilanServisKanali(): string
    {
        return app(TeknikServisGenelAyarServisi::class)->varsayilanServisKanali(self::gecerliFirmaId());
    }

    private static function varsayilanMusteriOnayDurumu(): string
    {
        return app(TeknikServisGenelAyarServisi::class)->varsayilanMusteriOnayDurumu(self::gecerliFirmaId());
    }

    private static function varsayilanBakimPeriyotAy(): int
    {
        return app(TeknikServisGenelAyarServisi::class)->varsayilanBakimPeriyotAy(self::gecerliFirmaId());
    }

    private static function varsayilanGarantiAy(): int
    {
        return app(TeknikServisGenelAyarServisi::class)->varsayilanGarantiAy(self::gecerliFirmaId());
    }

    private static function notAlaniTextarea(string $alan, string $etiket): Forms\Components\Textarea
    {
        $gerektigindeBuyut = <<<'JS'
setInitialHeight();
const wrapper = wrapperEl ?? $el.parentElement;
const lineHeight = parseFloat(getComputedStyle($el).lineHeight || '20');
const paddingTop = parseFloat(getComputedStyle($el).paddingTop || '0');
const paddingBottom = parseFloat(getComputedStyle($el).paddingBottom || '0');
const singleLineHeight = Math.ceil(lineHeight + paddingTop + paddingBottom);
const neededHeight = Math.ceil($el.scrollHeight);
const hasContent = String(state ?? '').trim().length > 0;

if (hasContent && neededHeight > (singleLineHeight + 1)) {
    wrapper.style.height = neededHeight + 'px';
} else {
    setInitialHeight();
}
JS;

        return Forms\Components\Textarea::make($alan)
            ->label($etiket)
            ->rows(1)
            ->extraInputAttributes([
                'class' => 'teknik-servis-expandable-note teknik-servis-single-line-note',
                'x-init' => '$nextTick(() => setInitialHeight())',
                'x-bind:title' => "state || ''",
                'x-on:mouseenter' => $gerektigindeBuyut,
                'x-on:mouseleave' => '$el !== document.activeElement ? setInitialHeight() : null',
                'x-on:focus' => $gerektigindeBuyut,
                'x-on:click' => $gerektigindeBuyut,
                'x-on:blur' => 'setInitialHeight()',
                'x-on:input' => $gerektigindeBuyut,
            ]);
    }

    private static function servisKaydiId(Forms\Get $get): int
    {
        $servisId = (int) ($get('id') ?? 0);

        if ($servisId > 0) {
            return $servisId;
        }

        $record = request()->route('record');

        if ($record instanceof TeknikServisKaydi) {
            return (int) $record->getKey();
        }

        if (is_numeric($record)) {
            return (int) $record;
        }

        return 0;
    }

    private static function servisKaydiFirmaId(Forms\Get $get, int $servisId = 0): int
    {
        $firmaId = (int) ($get('firma_id') ?? 0);

        if ($firmaId > 0) {
            return $firmaId;
        }

        $servisId = $servisId > 0 ? $servisId : self::servisKaydiId($get);

        if ($servisId > 0) {
            $firmaId = (int) TeknikServisKaydi::query()
                ->withoutGlobalScopes()
                ->whereKey($servisId)
                ->value('firma_id');

            if ($firmaId > 0) {
                return $firmaId;
            }
        }

        return self::aktifFirmaId();
    }

    private static function stokKalemSatirNoGosterimi(Forms\Get $get, Component $component): HtmlString
    {
        $statePath = $component->getStatePath();
        if (! preg_match('/(?:^|\.)kalemler\.([^.]+)\.satir_no_gosterge$/', $statePath, $matches)) {
            return new HtmlString('<span class="teknik-servis-line-index-value">-</span>');
        }

        $satirAnahtari = (string) $matches[1];
        $kalemler = $get('data.kalemler', true);

        if (! is_array($kalemler)) {
            $kalemler = [];
        }

        $anahtarlar = array_map('strval', array_keys($kalemler));
        $sira = array_search($satirAnahtari, $anahtarlar, true);

        $satirNo = $sira === false ? 1 : ((int) $sira + 1);

        return new HtmlString('<span class="teknik-servis-line-index-value">'.e((string) $satirNo).'</span>');
    }

    private static function kayitOlusturulduMu(Forms\Get $get): bool
    {
        return self::servisKaydiId($get) > 0;
    }

    private static function muhasebeDurumOzetiHtml(?TeknikServisKaydi $record): HtmlString
    {
        if (! $record || ! $record->getKey()) {
            return new HtmlString('<div style="color:#64748b;">Muhasebe özeti kayıt oluşturulduktan sonra görünür.</div>');
        }

        $servisId = (int) $record->getKey();
        $firmaId = (int) $record->firma_id;
        $kalemSayisi = (int) $record->kalemler()->count();

        $satisBaglantisi = TeknikServisMuhasebeBaglantisi::query()
            ->where('firma_id', $firmaId)
            ->where('teknik_servis_kaydi_id', $servisId)
            ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->orderByDesc('id')
            ->first();

        $fatura = $satisBaglantisi?->satis_faturasi_id
            ? Fatura::query()
                ->where('firma_id', $firmaId)
                ->whereKey((int) $satisBaglantisi->satis_faturasi_id)
                ->first(['id', 'fatura_no', 'tur', 'durum', 'genel_toplam', 'odenecek_tutar', 'acik_tutar', 'para_birimi'])
            : null;

        $aktifTahsilatSorgusu = TeknikServisTahsilati::query()
            ->where('firma_id', $firmaId)
            ->where('teknik_servis_kaydi_id', $servisId)
            ->where('durum', 'aktif');

        $aktifTahsilatSayisi = (clone $aktifTahsilatSorgusu)->count();
        $aktifTahsilatTutari = (float) (clone $aktifTahsilatSorgusu)->sum('hedef_tutar');
        if ($aktifTahsilatTutari <= 0) {
            $aktifTahsilatTutari = (float) (clone $aktifTahsilatSorgusu)->sum('tutar');
        }

        $faturaId = (int) ($fatura?->getKey() ?? 0);
        $faturasizTahsilatSayisi = (clone $aktifTahsilatSorgusu)
            ->where(function (Builder $query) use ($faturaId): void {
                $query->whereNull('satis_faturasi_id');

                if ($faturaId > 0) {
                    $query->orWhere('satis_faturasi_id', '!=', $faturaId);
                }
            })
            ->count();

        $uyarilar = [];
        if ($kalemSayisi > 0 && ! $fatura) {
            $uyarilar[] = 'Stok kalemi var ancak bağlı satış faturası yok.';
        }

        if ($aktifTahsilatSayisi > 0 && $faturasizTahsilatSayisi > 0) {
            $uyarilar[] = 'Tahsilatlardan bazıları faturaya bağlı değil.';
        }

        if ($satisBaglantisi && ! $fatura) {
            $uyarilar[] = 'Muhasebe bağlantısı var ancak fatura kaydı bulunamadı.';
        }

        $faturaMetni = 'Fatura yok';
        $faturaUrl = null;
        if ($fatura) {
            $faturaNo = trim((string) ($fatura->fatura_no ?? ''));
            $faturaMetni = ($faturaNo !== '' ? $faturaNo : '#'.$fatura->getKey())
                .' | '.self::enumMetni($fatura->tur)
                .' / '.self::enumMetni($fatura->durum)
                .' | '.self::paraMetni((float) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? 0), (string) ($fatura->para_birimi ?? 'TRY'));
            $faturaUrl = FaturaKaynagi::getUrl('edit', ['record' => $fatura]);
        }

        $senkronMetni = $satisBaglantisi
            ? self::enumMetni($satisBaglantisi->senkron_durumu)
            : 'Bağlantı yok';

        $parcalar = [
            self::muhasebeOzetRozeti('Kalem', $kalemSayisi > 0 ? $kalemSayisi.' adet' : 'Yok', $kalemSayisi > 0 ? '#ecfdf5' : '#f8fafc', $kalemSayisi > 0 ? '#047857' : '#64748b'),
            self::muhasebeOzetRozeti('Fatura', $faturaMetni, $fatura ? '#eff6ff' : '#fff7ed', $fatura ? '#1d4ed8' : '#c2410c', $faturaUrl),
            self::muhasebeOzetRozeti('Senkron', $senkronMetni, $satisBaglantisi ? '#f0fdf4' : '#f8fafc', $satisBaglantisi ? '#15803d' : '#64748b'),
            self::muhasebeOzetRozeti('Tahsilat', $aktifTahsilatSayisi > 0 ? $aktifTahsilatSayisi.' kayıt | '.self::paraMetni($aktifTahsilatTutari, (string) ($fatura->para_birimi ?? 'TRY')) : 'Yok', $aktifTahsilatSayisi > 0 ? '#f0f9ff' : '#f8fafc', $aktifTahsilatSayisi > 0 ? '#0369a1' : '#64748b'),
        ];

        $uyariHtml = '';
        if ($uyarilar !== []) {
            $uyariHtml = '<div style="margin-top:10px;color:#b91c1c;font-weight:600;">'
                .e(implode(' ', $uyarilar))
                .'</div>';
        }

        return new HtmlString(
            '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#fff;">'
            .'<div style="font-weight:700;color:#111827;margin-bottom:8px;">Muhasebe durumu</div>'
            .'<div style="display:flex;flex-wrap:wrap;gap:8px;">'.implode('', $parcalar).'</div>'
            .$uyariHtml
            .'</div>'
        );
    }

    private static function muhasebeOzetRozeti(string $etiket, string $deger, string $arkaPlan, string $renk, ?string $url = null): string
    {
        $icerik = '<span style="font-weight:600;">'.e($etiket).':</span> '.e($deger);

        if ($url !== null) {
            $icerik = '<a href="'.e($url).'" target="_blank" style="color:inherit;text-decoration:underline;">'.$icerik.'</a>';
        }

        return '<span style="display:inline-flex;align-items:center;min-height:28px;padding:5px 9px;border-radius:7px;background:'
            .e($arkaPlan)
            .';color:'
            .e($renk)
            .';font-size:13px;">'
            .$icerik
            .'</span>';
    }

    private static function enumMetni(mixed $deger): string
    {
        $metin = $deger instanceof \BackedEnum ? (string) $deger->value : (string) $deger;
        $metin = str_replace('_', ' ', $metin);

        return $metin !== '' ? mb_convert_case($metin, MB_CASE_TITLE, 'UTF-8') : '-';
    }

    private static function paraMetni(float $tutar, string $paraBirimi): string
    {
        return number_format($tutar, 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    private static function yeniKayitDurumuSeciliMi(Forms\Get $get): bool
    {
        $durumId = (int) ($get('servis_durumu_id') ?? 0);

        if ($durumId <= 0) {
            $varsayilanId = self::varsayilanYeniKayitDurumuId();

            return $varsayilanId !== null;
        }

        $durum = self::servisDurumuOzeti($durumId, true);

        if (! $durum) {
            return false;
        }

        return in_array((string) $durum['kod'], [TeknikServisDurumKodlari::YENI, TeknikServisDurumKodlari::YENI_ESKI], true)
            || in_array((string) $durum['ad'], ['Yeni Kayıt', 'Yeni Servis'], true);
    }

    /**
     * @return array<string, string>
     */
    private static function paraBirimiSecenekleri(): array
    {
        $firmaId = self::aktifFirmaId();
        if ($firmaId < 1) {
            return ['TRY' => 'TRY'];
        }

        $cacheKey = 'para_birimi|'.$firmaId;

        if (array_key_exists($cacheKey, self::$secenekCache)) {
            return self::$secenekCache[$cacheKey];
        }

        $secenekler = ParaBirimi::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->orderBy('kod')
            ->get(['kod', 'ad'])
            ->mapWithKeys(fn (ParaBirimi $pb) => [
                strtoupper((string) $pb->kod) => strtoupper((string) $pb->kod).' - '.((string) ($pb->ad ?: strtoupper((string) $pb->kod))),
            ])
            ->all();

        return self::$secenekCache[$cacheKey] = $secenekler !== [] ? $secenekler : ['TRY' => 'TRY'];
    }

    private static function tahsilatHesapParaBirimi(string $tip, int $hesapId): ?string
    {
        if ($hesapId < 1) {
            return null;
        }

        $model = match ($tip) {
            'kasa' => KasaHesabi::class,
            'banka' => BankaHesabi::class,
            'pos' => PosHesabi::class,
            default => KasaHesabi::class,
        };

        $hesap = $model::query()->find($hesapId);

        return $hesap ? strtoupper((string) $hesap->para_birimi) : null;
    }

    private static function tahsilatHedefParaBirimiGuncelle(string $tip, int $hesapId, Set $set, Forms\Get $get): void
    {
        if ($hesapId < 1) {
            $set('tahsilat_hedef_para_birimi', null);
            $set('tahsilat_hedef_tutar', null);
            return;
        }

        $hedefPb = self::tahsilatHesapParaBirimi($tip, $hesapId);
        $set('tahsilat_hedef_para_birimi', $hedefPb);
        $set('tahsilat_doviz_kuru', null);

        self::tahsilatOtomatikKurDoldur($get, $set);
        self::tahsilatHedefTutarGuncelle($get, $set);
    }

    private static function tahsilatFarkliParaBirimiSeciliMi(Forms\Get $get): bool
    {
        $kaynak = strtoupper((string) ($get('tahsilat_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('tahsilat_hedef_para_birimi') ?? ''));

        return $kaynak !== '' && $hedef !== '' && $kaynak !== $hedef;
    }

    private static function tahsilatHedefTutarOnizleme(Forms\Get $get): ?string
    {
        $tutar = (string) ($get('tahsilat_tutari') ?? '0');
        $kur = (string) ($get('tahsilat_doviz_kuru') ?? '0');
        $kaynakPb = strtoupper((string) ($get('tahsilat_para_birimi') ?? ''));
        $hedefPb = strtoupper((string) ($get('tahsilat_hedef_para_birimi') ?? ''));

        if (bccomp($tutar, '0', 8) <= 0 || (float) $kur <= 0 || $hedefPb === '') {
            return null;
        }

        if ($kaynakPb === 'TRY' && $hedefPb !== 'TRY') {
            return bcdiv($tutar, $kur, 8);
        }

        return bcmul($tutar, $kur, 8);
    }

    private static function tahsilatKurGuncelleHedefTutardan(Forms\Get $get, Set $set): void
    {
        $tutar = (string) ($get('tahsilat_tutari') ?? '0');
        $hedefTutar = (string) ($get('tahsilat_hedef_tutar') ?? '0');
        $kaynakPb = strtoupper((string) ($get('tahsilat_para_birimi') ?? ''));
        $hedefPb = strtoupper((string) ($get('tahsilat_hedef_para_birimi') ?? ''));
        if (bccomp($tutar, '0', 8) <= 0 || bccomp($hedefTutar, '0', 8) <= 0) {
            return;
        }

        $kur = ($kaynakPb === 'TRY' && $hedefPb !== 'TRY')
            ? bcdiv($tutar, $hedefTutar, 8)
            : bcdiv($hedefTutar, $tutar, 8);

        if ((float) $kur > 0) {
            $set('tahsilat_doviz_kuru', $kur);
        }
    }

    private static function tahsilatHedefTutarGuncelle(Forms\Get $get, Set $set): void
    {
        $set('tahsilat_hedef_tutar', self::tahsilatHedefTutarOnizleme($get));
    }

    private static function tahsilatOtomatikKurDoldur(Forms\Get $get, Set $set): void
    {
        $kurTuru = (string) ($get('tahsilat_doviz_kuru_turu') ?? 'otomatik');
        if ($kurTuru !== 'otomatik') {
            return;
        }

        $kaynak = strtoupper((string) ($get('tahsilat_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('tahsilat_hedef_para_birimi') ?? ''));
        if ($kaynak === '' || $hedef === '' || $kaynak === $hedef) {
            return;
        }

        $tarih = (string) ($get('tahsilat_tarihi') ?? now()->format('Y-m-d H:i:s'));
        $kur = self::tahsilatOtomatikKurBul($kaynak, $hedef, $tarih);
        if ($kur !== null) {
            $set('tahsilat_doviz_kuru', $kur);
        }

        self::tahsilatHedefTutarGuncelle($get, $set);
    }

    private static function tahsilatOtomatikKurBul(string $kaynak, string $hedef, string $tarih): ?string
    {
        $gun = Carbon::parse($tarih)->toDateString();
        $kurTipi = self::tahsilatOtomatikKurTipiBelirle($kaynak, $hedef);

        try {
            $sonuc = app(DovizKurServisi::class)->otomatikKurGetirKurTipi($kaynak, $hedef, $gun, $kurTipi);
            $kur = number_format((float) ($sonuc['kur'] ?? 0), 8, '.', '');
            if ($kaynak === 'TRY' && $hedef !== 'TRY' && (float) $kur > 0) {
                $kur = number_format((float) (1 / (float) $kur), 8, '.', '');
            }

            return $kur;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function tahsilatOtomatikKurTipiBelirle(string $kaynak, string $hedef): string
    {
        $kaynak = strtoupper(trim($kaynak));
        $hedef = strtoupper(trim($hedef));
        if ($kaynak !== 'TRY' && $hedef === 'TRY') {
            return 'alis';
        }
        if ($kaynak === 'TRY' && $hedef !== 'TRY') {
            return 'satis';
        }

        return 'satis';
    }

    private static function tahsilatKurGosterimMetni(Forms\Get $get): string
    {
        $kaynak = strtoupper((string) ($get('tahsilat_para_birimi') ?? ''));
        $hedef = strtoupper((string) ($get('tahsilat_hedef_para_birimi') ?? ''));
        $kur = (float) ($get('tahsilat_doviz_kuru') ?? 0);
        if ($kaynak === '' || $hedef === '' || $kur <= 0) {
            return 'Hesaplamada kullanilan kur bu alandaki degerdir.';
        }

        $etiket = self::tahsilatOtomatikKurTipiBelirle($kaynak, $hedef) === 'alis' ? 'Alis Kuru' : 'Satis Kuru';
        $kurFmt = number_format($kur, 8, '.', '');
        $ters = number_format(1 / $kur, 8, '.', '');
        if ($kaynak === 'TRY' && $hedef !== 'TRY') {
            return 'Kullanilan kur: '.$etiket.' ('.$kurFmt.') | 1 '.$hedef.' = '.$kurFmt.' TRY | 1 TRY = '.$ters.' '.$hedef;
        }
        if ($kaynak !== 'TRY' && $hedef === 'TRY') {
            return 'Kullanilan kur: '.$etiket.' ('.$kurFmt.') | 1 '.$kaynak.' = '.$kurFmt.' TRY | 1 TRY = '.$ters.' '.$kaynak;
        }

        return 'Kullanilan kur: '.$etiket.' ('.$kurFmt.')';
    }

    private static function stokKalemOzetiMetni(Forms\Get $get): HtmlString
    {
        $ozet = self::stokOzetHesapla($get);
        $satirlar = [
            'Mal Hizmet Toplam Tutarı' => number_format((float) ($ozet['mal_hizmet_toplam_tutari'] ?? 0), 2, ',', '.'),
            'Toplam İskonto' => number_format((float) ($ozet['toplam_iskonto'] ?? 0), 2, ',', '.'),
            'Hesaplanan KDV' => number_format((float) ($ozet['hesaplanan_kdv'] ?? 0), 2, ',', '.'),
            'Vergiler Dahil Toplam Tutar' => number_format((float) ($ozet['vergiler_dahil_toplam_tutar'] ?? 0), 2, ',', '.'),
            'Ödenecek Tutar' => number_format((float) ($ozet['odenecek_tutar'] ?? 0), 2, ',', '.'),
        ];

        $html = '<div style="display:grid;gap:8px;">';

        foreach ($satirlar as $etiket => $deger) {
            $html .= '<div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center;">'
                .'<span style="font-weight:500;color:#111827;">'.e($etiket).'</span>'
                .'<span style="font-variant-numeric:tabular-nums;font-weight:600;color:#111827;text-align:right;">'.e($deger).'</span>'
                .'</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    private static function stokKalemSatirOzetiMetni(Forms\Get $get): HtmlString
    {
        $satir = self::satirHesabi([
            'miktar' => $get('miktar'),
            'birim_fiyat' => $get('birim_fiyat'),
            'iskonto_orani' => $get('iskonto_orani'),
            'iskonto_tutari' => $get('iskonto_tutari'),
            'kdv_orani' => $get('kdv_orani'),
            'kdv_tutari' => $get('kdv_tutari'),
        ]);

        $paraBirimi = self::stokKalemSatirParaBirimi($get('para_birimi'));
        $parcalar = [
            'Net' => max($satir['brut_fiyat'] - $satir['iskonto_tutari'], 0),
            'KDV' => $satir['kdv_tutari'],
            'Toplam' => $satir['net_toplam'],
        ];

        $html = '<div class="teklif-line-summary teknik-servis-line-summary">';

        $ilkParca = true;

        foreach ($parcalar as $etiket => $deger) {
            if (! $ilkParca) {
                $html .= '<span class="teknik-servis-line-summary-separator" aria-hidden="true">&nbsp;&nbsp;</span>';
            }

            $html .= '<span class="teknik-servis-line-summary-item">'
                . '<span class="teknik-servis-line-summary-label">' . e($etiket) . ':</span> '
                . '<span class="teknik-servis-line-summary-value">' . e(self::stokKalemSatirParaMetni($deger, $paraBirimi)) . '</span>'
                . '</span>';

            $ilkParca = false;
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * @param  array<string, mixed>  $kalem
     */
    private static function stokKalemSatirOzetiDuzMetin(array $kalem): string
    {
        $satir = self::satirHesabi([
            'miktar' => $kalem['miktar'] ?? null,
            'birim_fiyat' => $kalem['birim_fiyat'] ?? null,
            'iskonto_orani' => $kalem['iskonto_orani'] ?? null,
            'iskonto_tutari' => $kalem['iskonto_tutari'] ?? null,
            'kdv_orani' => $kalem['kdv_orani'] ?? null,
            'kdv_tutari' => $kalem['kdv_tutari'] ?? null,
        ]);

        $paraBirimi = self::stokKalemSatirParaBirimi($kalem['para_birimi'] ?? 'TRY');
        $net = max($satir['brut_fiyat'] - $satir['iskonto_tutari'], 0);

        return 'Net: '.self::stokKalemSatirParaMetni($net, $paraBirimi)
            .'  KDV: '.self::stokKalemSatirParaMetni($satir['kdv_tutari'], $paraBirimi)
            .'  Toplam: '.self::stokKalemSatirParaMetni($satir['net_toplam'], $paraBirimi);
    }

    private static function stokKalemSatirParaMetni(float $tutar, string $paraBirimi): string
    {
        return number_format($tutar, 2, ',', '.') . ' ' . $paraBirimi;
    }

    private static function stokKalemSatirParaBirimi(mixed $deger): string
    {
        $paraBirimi = strtoupper(trim((string) $deger));

        return $paraBirimi !== '' ? $paraBirimi : 'TRY';
    }

    /**
     * @return array<string, string>
     */
    private static function stokTurSecenekleri(): array
    {
        $secenekler = [];
        foreach (StokKartiTuru::cases() as $tur) {
            $secenekler[$tur->value] = $tur->etiket();
        }

        return $secenekler;
    }

    /**
     * @return array<string, string>
     */
    private static function hesapDurumuSecenekleri(): array
    {
        return [
            HesapDurumu::Aktif->value => 'Aktif',
            HesapDurumu::Pasif->value => 'Pasif',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function birimSecenekleri(): array
    {
        $firmaId = self::gecerliFirmaId();

        return self::formSecenekCache()->remember(
            TeknikServisFormSecenekCache::GROUP_BIRIM,
            'firma|'.$firmaId,
            function (): array {
                $secenekler = Birim::query()
                    ->where('aktif_mi', true)
                    ->orderBy('kod')
                    ->get()
                    ->mapWithKeys(fn (Birim $birim) => [(string) $birim->kod => (string) ($birim->kod.' - '.$birim->ad)])
                    ->all();

                if (! array_key_exists('AD', $secenekler)) {
                    $secenekler = ['AD' => 'AD'] + $secenekler;
                }

                return $secenekler;
            }
        );
    }

    /**
     * @return array<string, string>
     */
    private static function birimGosterimSecenekleri(): array
    {
        return array_map(static function (string $etiket): string {
            $parcalar = explode(' - ', $etiket, 2);

            return trim($parcalar[1] ?? $etiket) ?: $etiket;
        }, self::birimSecenekleri());
    }

    /**
     * @return array<string, string>
     */
    private static function vergiOraniSecenekleri(?int $firmaId = null): array
    {
        $firmaId = $firmaId ?: self::gecerliFirmaId();

        return self::formSecenekCache()->remember(
            TeknikServisFormSecenekCache::GROUP_VERGI_ORANI,
            'firma|'.$firmaId,
            function () use ($firmaId): array {
                $secenekler = VergiOrani::query()
                    ->withoutGlobalScopes()
                    ->where('aktif_mi', true)
                    ->orderBy('oran')
                    ->where(function (Builder $query) use ($firmaId): void {
                        if ($firmaId > 0) {
                            $query->where('firma_id', $firmaId)
                                ->orWhere(function (Builder $globalQuery): void {
                                    $globalQuery->whereNull('firma_id')
                                        ->where('is_sabit', true);
                                });

                            return;
                        }

                        $query->whereNull('firma_id');
                    })
                    ->get()
                    ->mapWithKeys(function (VergiOrani $vergi): array {
                        $oran = rtrim(rtrim((string) number_format((float) $vergi->oran, 2, '.', ''), '0'), '.');

                        return [$oran => '%'.$oran];
                    })
                    ->all();

                if ($secenekler === []) {
                    $secenekler = [
                        '0' => '%0',
                        '10' => '%10',
                        '20' => '%20',
                    ];
                }

                return $secenekler;
            }
        );
    }

    private static function sonrakiStokKodu(int $firmaId): string
    {
        $tum = StokKarti::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereNotNull('kod')
            ->where('kod', 'like', 'STK%')
            ->pluck('kod')
            ->all();

        $max = 0;
        foreach ($tum as $kod) {
            $parca = preg_replace('/[^0-9]/', '', substr((string) $kod, 3));
            if ($parca === '' || ! ctype_digit($parca)) {
                continue;
            }
            $max = max($max, (int) $parca);
        }

        do {
            $max++;
            $aday = 'STK'.str_pad((string) $max, 6, '0', STR_PAD_LEFT);
        } while (StokKarti::query()->withoutGlobalScopes()->where('firma_id', $firmaId)->where('kod', $aday)->exists());

        return $aday;
    }

    /**
     * @param  array<string, mixed>  $kalem
     * @return array{miktar:float,birim_fiyat:float,brut_fiyat:float,iskonto_orani:float,iskonto_tutari:float,kdv_orani:float,kdv_tutari:float,net_toplam:float}
     */
    private static function satirHesabi(array $kalem, string $guncellenenAlan = ''): array
    {
        $miktar = max(0, (float) ($kalem['miktar'] ?? 0));
        $birimFiyat = max(0, (float) ($kalem['birim_fiyat'] ?? 0));
        $kdvOrani = max(0, (float) ($kalem['kdv_orani'] ?? 0));
        $kdvTutari = max(0, (float) ($kalem['kdv_tutari'] ?? 0));
        $iskontoOrani = max(0, min(100, (float) ($kalem['iskonto_orani'] ?? 0)));
        $iskontoTutari = max(0, (float) ($kalem['iskonto_tutari'] ?? 0));

        $brutFiyat = $miktar * $birimFiyat;

        if ($guncellenenAlan === 'iskonto_tutari') {
            $iskontoTutari = min($iskontoTutari, $brutFiyat);
            $iskontoOrani = $brutFiyat > 0 ? ($iskontoTutari / $brutFiyat) * 100 : 0;
        } else {
            // Kayit/hydrate asamasinda oran kirpilmis olsa bile tutari koru.
            if ($guncellenenAlan === '' && array_key_exists('iskonto_tutari', $kalem)) {
                $iskontoTutari = min(max((float) ($kalem['iskonto_tutari'] ?? 0), 0), $brutFiyat);
                $iskontoOrani = $brutFiyat > 0 ? ($iskontoTutari / $brutFiyat) * 100 : 0;
            } else {
                $iskontoTutari = $brutFiyat * $iskontoOrani / 100;
            }
        }

        $iskontoTutari = min(max($iskontoTutari, 0), $brutFiyat);
        $kdvMatrahi = max($brutFiyat - $iskontoTutari, 0);
        if ($guncellenenAlan === 'kdv_tutari') {
            $kdvTutari = max(0, $kdvTutari);
            $kdvOrani = $kdvMatrahi > 0 ? ($kdvTutari / $kdvMatrahi) * 100 : 0;
        } elseif (
            $guncellenenAlan === ''
            && array_key_exists('kdv_tutari', $kalem)
            && trim((string) ($kalem['kdv_orani'] ?? '')) === ''
        ) {
            $kdvTutari = max(0, (float) ($kalem['kdv_tutari'] ?? 0));
            $kdvOrani = $kdvMatrahi > 0 ? ($kdvTutari / $kdvMatrahi) * 100 : 0;
        } else {
            $kdvTutari = $kdvMatrahi * ($kdvOrani / 100);
        }
        $netToplam = $kdvMatrahi + $kdvTutari;

        return [
            'miktar' => $miktar,
            'birim_fiyat' => $birimFiyat,
            'brut_fiyat' => $brutFiyat,
            'iskonto_orani' => $iskontoOrani,
            'iskonto_tutari' => $iskontoTutari,
            'kdv_orani' => $kdvOrani,
            'kdv_tutari' => $kdvTutari,
            'net_toplam' => $netToplam,
        ];
    }

    private static function kalemHesabiUygula(Forms\Get $get, Set $set, string $guncellenenAlan = ''): void
    {
        $girdiDegerleri = [
            'miktar' => (float) ($get('miktar') ?? 0),
            'birim_fiyat' => (float) ($get('birim_fiyat') ?? 0),
            'iskonto_orani' => (float) ($get('iskonto_orani') ?? 0),
            'iskonto_tutari' => (float) ($get('iskonto_tutari') ?? 0),
            'kdv_orani' => (float) ($get('kdv_orani') ?? 0),
        ];

        $satir = self::satirHesabi([
            'miktar' => $girdiDegerleri['miktar'],
            'birim_fiyat' => $girdiDegerleri['birim_fiyat'],
            'iskonto_orani' => $girdiDegerleri['iskonto_orani'],
            'iskonto_tutari' => $girdiDegerleri['iskonto_tutari'],
            'kdv_orani' => $girdiDegerleri['kdv_orani'],
            'kdv_tutari' => $get('kdv_tutari'),
        ], $guncellenenAlan);

        if (
            $guncellenenAlan === 'iskonto_orani'
            && abs($girdiDegerleri['iskonto_orani'] - $satir['iskonto_orani']) > 0.0001
        ) {
            Notification::make()
                ->title('İskonto oranı %100 değerini geçemez')
                ->warning()
                ->send();
        }

        self::hesaplananKalemDegeriniYaz($set, 'miktar', $satir['miktar'], $guncellenenAlan, $girdiDegerleri['miktar']);
        self::hesaplananKalemDegeriniYaz($set, 'birim_fiyat', $satir['birim_fiyat'], $guncellenenAlan, $girdiDegerleri['birim_fiyat']);
        self::hesaplananKalemDegeriniYaz($set, 'iskonto_orani', $satir['iskonto_orani'], $guncellenenAlan, $girdiDegerleri['iskonto_orani']);
        self::hesaplananKalemDegeriniYaz($set, 'iskonto_tutari', $satir['iskonto_tutari'], $guncellenenAlan, $girdiDegerleri['iskonto_tutari']);
        self::hesaplananKalemDegeriniYaz($set, 'kdv_orani', $satir['kdv_orani'], $guncellenenAlan, $girdiDegerleri['kdv_orani']);
        $set('kdv_tutari', $satir['kdv_tutari']);
        $set('satir_toplami', $satir['net_toplam']);
        $set('brut_fiyat_gosterim', $satir['brut_fiyat']);
        $set('net_toplam_gosterim', $satir['net_toplam']);
    }

    private static function netToplamdanBirimFiyatHesapla(Forms\Get $get, Set $set): void
    {
        $netToplam = (float) ($get('net_toplam_gosterim') ?? 0);
        $miktar = (float) ($get('miktar') ?? 0);
        $kdvOrani = (float) ($get('kdv_orani') ?? 0);

        if ($miktar <= 0) {
            return;
        }

        $kdvCarpani = 1 + ($kdvOrani / 100);
        $kdvHaricToplam = $kdvCarpani > 0 ? $netToplam / $kdvCarpani : $netToplam;

        $set('birim_fiyat', round($kdvHaricToplam / $miktar, 8));
        $set('iskonto_orani', 0);
        $set('iskonto_tutari', 0);
        self::kalemHesabiUygula($get, $set, 'birim_fiyat');
    }

    private static function hesaplananKalemDegeriniYaz(Set $set, string $alan, float $deger, string $guncellenenAlan = '', ?float $mevcutDeger = null): void
    {
        if (
            $guncellenenAlan === $alan
            && ($mevcutDeger === null || abs($mevcutDeger - $deger) < 0.0001)
        ) {
            return;
        }

        $set($alan, $deger);
    }

    /**
     * @return array{mal_hizmet_toplam_tutari:float,toplam_iskonto:float,hesaplanan_kdv:float,vergiler_dahil_toplam_tutar:float,odenecek_tutar:float}
     */
    private static function stokOzetHesapla(Forms\Get $get): array
    {
        $kalemler = (array) ($get('kalemler') ?? []);
        return self::stokOzetHesaplaKalemDizisi($kalemler);
    }

    /**
     * @param  array<int, mixed>  $kalemler
     * @return array{mal_hizmet_toplam_tutari:float,toplam_iskonto:float,hesaplanan_kdv:float,vergiler_dahil_toplam_tutar:float,odenecek_tutar:float}
     */
    public static function stokOzetHesaplaKalemDizisi(array $kalemler): array
    {
        $malHizmetToplam = 0.0;
        $toplamIskonto = 0.0;
        $hesaplananKdv = 0.0;
        $vergilerDahilToplam = 0.0;

        foreach ($kalemler as $kalem) {
            if (! is_array($kalem)) {
                continue;
            }

            $satir = self::satirHesabi($kalem);
            $malHizmetToplam += $satir['brut_fiyat'];
            $toplamIskonto += $satir['iskonto_tutari'];
            $hesaplananKdv += $satir['kdv_tutari'];
            $vergilerDahilToplam += $satir['net_toplam'];
        }

        $odenecek = $malHizmetToplam - $toplamIskonto + $hesaplananKdv;

        return [
            'mal_hizmet_toplam_tutari' => $malHizmetToplam,
            'toplam_iskonto' => $toplamIskonto,
            'hesaplanan_kdv' => $hesaplananKdv,
            'vergiler_dahil_toplam_tutar' => $vergilerDahilToplam,
            'odenecek_tutar' => $odenecek,
        ];
    }

    private static function sonrakiFisNo(int $firmaId): string
    {
        return app(TeknikServisFisNumarasiServisi::class)->sonrakiAday($firmaId);
    }

}
