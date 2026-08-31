<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Muhasebe\StokBarkodu;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokSeriNo;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Servisler\BarkodluSatisServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Renderless;

class BarkodluSatisSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    private static ?bool $stokBarkodTablosuVar = null;

    private ?int $aktifFirmaIdCache = null;

    public ?array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $kalemler = [];

    public ?int $seciliKalemIndex = null;

    public bool $indirimGirisAcik = false;

    public string $indirimTutari = '';

    /** @var array<int, array<string,mixed>> */
    public array $bekleyenSepetler = [];

    public int $etiketYazdirmaAdedi = 1;

    /** @var array<int, array<string,mixed>> */
    public array $barkodAdaylari = [];

    /** @var array<int, array<string,mixed>> */
    public array $hizliUrunAdaylari = [];

    public int $hizliUrunAdayToplam = 0;

    public int $hizliUrunAramaLimit = 8;

    public string $hizliUrunAramaSiralamasi = 'ilgili';

    /** @var array{ara_toplam:float,iskonto_toplami:float,kdv_toplami:float,genel_toplam:float}|null */
    private ?array $sepetOzetiCache = null;

    private ?string $sepetOzetiCacheHash = null;

    private ?string $aktifSepetKayitImzasi = null;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Barkodlu satis';

    protected static ?string $slug = 'satis/barkodlu-satis';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-sayfasi';

    public function getHeading(): string|Htmlable
    {
        return 'Barkodlu satis';
    }

    public function getSubheading(): ?string
    {
        return 'Barkod okut, sepete ekle ve satisi tek ekrandan tamamla.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE;
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_OLUSTUR,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_IPTAL,
        ];
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi(
            static::muhasebeSayfasiYetkiKodlari()
        );
    }

    public function mount(): void
    {
        $varsayilanOdemeTipi = $this->varsayilanOdemeTipi();
        $varsayilanParaBirimi = 'TRY';
        $varsayilanKasaHesapId = $varsayilanOdemeTipi === 'nakit'
            ? $this->varsayilanKasaHesapId($varsayilanParaBirimi)
            : null;
        $varsayilanBankaHesapId = $varsayilanOdemeTipi === 'havale'
            ? $this->varsayilanBankaHesapId($varsayilanParaBirimi)
            : null;
        $varsayilanPosHesapId = $varsayilanOdemeTipi === 'kart'
            ? $this->varsayilanPosHesapId($varsayilanParaBirimi)
            : null;
        $this->etiketYazdirmaAdedi = 1;

        $this->form->fill([
            'satis_tarihi' => now()->format('Y-m-d H:i'),
            'cari_id' => null,
            'odeme_tipi' => $varsayilanOdemeTipi,
            'kasa_hesap_id' => $varsayilanKasaHesapId,
            'banka_hesap_id' => $varsayilanBankaHesapId,
            'pos_hesap_id' => $varsayilanPosHesapId,
            'para_birimi' => $varsayilanParaBirimi,
            'not' => null,
            'pesinat_tutari' => 0,
            'pesinat_odeme_tipi' => 'nakit',
            'vade_farki_uygula' => false,
            'vade_farki_tipi' => 'tek_seferlik',
            'vade_farki_orani' => 0,
            'vade_farki_tutari' => 0,
            'vade_tarihi' => now()->addDays(30)->format('Y-m-d'),
            'taksit_sayisi' => 1,
            'taksit_araligi_gun' => 30,
            'barkod' => null,
            'hizli_urun_ara' => null,
        ]);
        $this->bekleyenSepetleriYukle();
        $this->aktifSepetiYukle();
        $this->aktifSepetKayitImzasi = $this->aktifSepetImzasi();
    }

    public function dehydrate(): void
    {
        $this->aktifSepetiKaydet();
    }

    public function hydrate(): void
    {
        $this->aktifSepetKayitImzasi = $this->aktifSepetImzasi();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Satis bilgileri')
                    ->schema([
                        Forms\Components\TextInput::make('barkod')
                            ->label('Barkod')
                            ->placeholder('Barkod okutun veya yazip Enter kullanin')
                            ->live(debounce: 500)
                            ->extraInputAttributes([
                                'id' => 'pos-barkod-input',
                                'autofocus' => 'autofocus',
                                'wire:keydown.enter.prevent' => 'barkodEkle',
                            ])
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('ekle')
                                    ->icon('heroicon-o-plus')
                                    ->tooltip('Sepete ekle')
                                    ->action(fn () => $this->barkodEkle())
                            ),
                        Forms\Components\TextInput::make('hizli_urun_ara')
                            ->label('Hizli urun ara')
                            ->placeholder('Kod / ad / barkod yazin ve Enter')
                            ->live(debounce: 500)
                            ->extraInputAttributes([
                                'id' => 'pos-hizli-ara-input',
                                'wire:keydown.enter.prevent' => 'hizliAramadanEkle',
                            ])
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('hizliEkle')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->tooltip('Ilk urunu sepete ekle')
                                    ->action(fn () => $this->hizliAramadanEkle())
                            ),
                        Forms\Components\DateTimePicker::make('satis_tarihi')
                            ->label('Satis tarihi')
                            ->native(false)
                            ->seconds(false)
                            ->required(),
                        Forms\Components\Select::make('cari_id')
                            ->label('Cari (opsiyonel)')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->cariEtiketi((int) $value))
                            ->live()
                            ->afterStateUpdated(function (mixed $state): void {
                                $paraBirimi = $this->cariParaBirimi((int) ($state ?? 0));
                                if ($paraBirimi === null) {
                                    return;
                                }

                                $this->data['para_birimi'] = $paraBirimi;
                                $this->varsayilanTahsilatHesabiAta($paraBirimi);
                            })
                            ->required(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true)),
                        Forms\Components\Select::make('odeme_tipi')
                            ->label('Odeme tipi')
                            ->options([
                                'nakit' => 'Nakit',
                                'kart' => 'Kart',
                                'havale' => 'Havale/EFT',
                                'veresiye' => 'Veresiye',
                                'taksitli' => 'Taksitli',
                                'diger' => 'Diger',
                            ])
                            ->extraInputAttributes([
                                'id' => 'pos-odeme-tipi-input',
                            ])
                            ->live()
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->varsayilanTahsilatHesabiAta((string) ($this->data['para_birimi'] ?? 'TRY'));
                            })
                            ->required(),
                        Forms\Components\Select::make('kasa_hesap_id')
                            ->label('Kasa secimi')
                            ->options(fn (Forms\Get $get): array => (string) $get('odeme_tipi') === 'nakit' ? $this->kasaSecenekleri() : [])
                            ->helperText(fn (Forms\Get $get): ?string => $this->hesapSecimiYardimMetni('kasa', $get))
                            ->suffixAction(fn (Forms\Get $get): ?Forms\Components\Actions\Action => $this->hesapTanimAction('kasa', $get))
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'nakit')
                            ->required(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'nakit'),
                        Forms\Components\Select::make('banka_hesap_id')
                            ->label('Banka secimi')
                            ->options(fn (Forms\Get $get): array => (string) $get('odeme_tipi') === 'havale' ? $this->bankaSecenekleri() : [])
                            ->helperText(fn (Forms\Get $get): ?string => $this->hesapSecimiYardimMetni('banka', $get))
                            ->suffixAction(fn (Forms\Get $get): ?Forms\Components\Actions\Action => $this->hesapTanimAction('banka', $get))
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'havale')
                            ->required(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'havale'),
                        Forms\Components\Select::make('pos_hesap_id')
                            ->label('POS secimi')
                            ->options(fn (Forms\Get $get): array => (string) $get('odeme_tipi') === 'kart' ? $this->posSecenekleri() : [])
                            ->helperText(fn (Forms\Get $get): ?string => $this->hesapSecimiYardimMetni('pos', $get))
                            ->suffixAction(fn (Forms\Get $get): ?Forms\Components\Actions\Action => $this->hesapTanimAction('pos', $get))
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'kart')
                            ->required(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'kart'),
                        Forms\Components\DatePicker::make('vade_tarihi')
                            ->label('Ilk vade tarihi')
                            ->native(false)
                            ->visible(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true))
                            ->required(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true)),
                        Forms\Components\TextInput::make('pesinat_tutari')
                            ->label('Pesinat')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->visible(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true)),
                        Forms\Components\Select::make('pesinat_odeme_tipi')
                            ->label('Pesinat odeme')
                            ->options([
                                'nakit' => 'Nakit',
                                'kart' => 'Kart',
                                'havale' => 'Havale/EFT',
                            ])
                            ->default('nakit')
                            ->live()
                            ->afterStateUpdated(function (mixed $state): void {
                                $tip = in_array((string) ($state ?? 'nakit'), ['nakit', 'kart', 'havale'], true)
                                    ? (string) ($state ?? 'nakit')
                                    : 'nakit';
                                $this->data['pesinat_odeme_tipi'] = $tip;
                                $this->varsayilanTahsilatHesabiAta((string) ($this->data['para_birimi'] ?? 'TRY'));
                            })
                            ->visible(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true)),
                        Forms\Components\Toggle::make('vade_farki_uygula')
                            ->label('Vade farki uygula')
                            ->default(false)
                            ->live()
                            ->visible(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true)),
                        Forms\Components\Select::make('vade_farki_tipi')
                            ->label('Vade farki tipi')
                            ->options([
                                'tek_seferlik' => 'Tek seferlik',
                                'aylik' => 'Aylik',
                                'yillik' => 'Yillik',
                            ])
                            ->default('tek_seferlik')
                            ->live()
                            ->visible(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true) && (bool) $get('vade_farki_uygula')),
                        Forms\Components\TextInput::make('vade_farki_orani')
                            ->label('Vade farki (%)')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->default(0)
                            ->live()
                            ->visible(fn (Forms\Get $get): bool => in_array((string) $get('odeme_tipi'), ['veresiye', 'taksitli'], true) && (bool) $get('vade_farki_uygula')),
                        Forms\Components\Hidden::make('vade_farki_tutari')
                            ->default(0),
                        Forms\Components\TextInput::make('taksit_sayisi')
                            ->label('Taksit sayisi')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->visible(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'taksitli')
                            ->required(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'taksitli'),
                        Forms\Components\TextInput::make('taksit_araligi_gun')
                            ->label('Taksit araligi (gun)')
                            ->numeric()
                            ->minValue(1)
                            ->default(30)
                            ->visible(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'taksitli')
                            ->required(fn (Forms\Get $get): bool => (string) $get('odeme_tipi') === 'taksitli'),
                        Forms\Components\Select::make('para_birimi')
                            ->label('Para birimi')
                            ->options([
                                'TRY' => 'TRY',
                                'USD' => 'USD',
                                'EUR' => 'EUR',
                            ])
                            ->live()
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->varsayilanTahsilatHesabiAta((string) ($state ?? 'TRY'));
                            })
                            ->default('TRY')
                            ->required(),
                        Forms\Components\Textarea::make('not')
                            ->label('Not')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('barkodEkle')
                ->label('Barkodu Ekle')
                ->icon('heroicon-o-plus')
                ->color('warning')
                ->visible(fn (): bool => $this->islemYetkisiVarMi())
                ->action('barkodEkle'),
            Actions\Action::make('satisiTamamla')
                ->label('Satisi Tamamla')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->islemYetkisiVarMi())
                ->action('satisiTamamla'),
            Actions\Action::make('satisiTamamlaVeYazdir')
                ->label('Kaydet + Yazdir')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(fn (): bool => $this->islemYetkisiVarMi())
                ->action('satisiTamamlaVeYazdir'),
        ];
    }

    public function barkodEkle(): void
    {
        if (! $this->islemYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()->title('Aktif firma bulunamadi')->danger()->send();

            return;
        }

        $barkod = trim((string) ($this->data['barkod'] ?? ''));
        if ($barkod === '') {
            Notification::make()->title('Barkod giriniz')->warning()->send();
            $this->dispatch('barkod-odakla');

            return;
        }

        $seri = StokSeriNo::query()
            ->where('firma_id', $firmaId)
            ->where('durum', 'stokta')
            ->where(function ($query) use ($barkod): void {
                $query->where('barkod', $barkod)->orWhere('seri_no', $barkod);
            })
            ->with('stok')
            ->first();
        if ($seri?->stok && in_array((string) $seri->stok->tur?->value, $this->barkodluSatisGorunenStokTurleri(), true)) {
            $this->stoktanSepeteEkle($seri->stok, $barkod, (string) $seri->seri_no);
            $this->data['barkod'] = null;
            $this->barkodAdaylari = [];
            $this->dispatch('barkod-odakla');

            return;
        }

        $stok = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
            ->where(function ($query) use ($barkod): void {
                $query->where('barkod', $barkod)
                    ->orWhere('kod', $barkod);
            })
            ->first();

        if (! $stok && $this->stokBarkodTablosuVarMi()) {
            $esleme = StokBarkodu::query()
                ->where('firma_id', $firmaId)
                ->where('aktif', true)
                ->where('barkod', $barkod)
                ->with('stok')
                ->first();
            $stok = $esleme?->stok;
            if ($stok && ! in_array((string) $stok->tur?->value, $this->barkodluSatisGorunenStokTurleri(), true)) {
                $stok = null;
            }
        }

        if (! $stok) {
            Notification::make()
                ->title('Stok bulunamadi')
                ->body('Girilen barkod/kod ile eslesen stok karti yok.')
                ->danger()
                ->send();
            $this->dispatch('barkod-odakla');

            return;
        }

        $this->stoktanSepeteEkle($stok, $barkod);

        $this->data['barkod'] = null;
        $this->barkodAdaylari = [];
        $this->dispatch('barkod-odakla');
    }

    public function hizliAramadanEkle(): void
    {
        if (! $this->islemYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()->title('Aktif firma bulunamadi')->danger()->send();

            return;
        }

        $arama = trim((string) ($this->data['hizli_urun_ara'] ?? ''));
        if ($arama === '') {
            Notification::make()->title('Arama metni giriniz')->warning()->send();

            return;
        }

        $stok = StokKarti::query()
            ->select([
                'id',
                'firma_id',
                'kod',
                'ad',
                'barkod',
                'tur',
                'birim',
                'indirimli_fiyat',
                'satis_fiyati',
                'kdv_orani',
            ])
            ->where('firma_id', $firmaId)
            ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
            ->where(function ($query) use ($arama): void {
                $query->where('kod', 'like', '%'.$arama.'%')
                    ->orWhere('ad', 'like', '%'.$arama.'%')
                    ->orWhere('barkod', 'like', '%'.$arama.'%');
            })
            ->with(['gorseller' => fn ($query) => $query
                ->select(['id', 'stok_karti_id', 'dosya_yolu', 'sira', 'kapak_mi', 'aktif_mi'])
                ->where('aktif_mi', true)])
            ->orderBy('ad')
            ->first();

        if (! $stok) {
            Notification::make()
                ->title('Urun bulunamadi')
                ->body('Arama ile eslesen stok karti yok.')
                ->warning()
                ->send();

            return;
        }

        $this->stoktanSepeteEkle($stok, (string) ($stok->barkod ?: $stok->kod ?: ''));
        $this->data['barkod'] = null;
        $this->data['hizli_urun_ara'] = null;
        $this->hizliUrunAdaylari = [];
        $this->dispatch('barkod-odakla');
    }

    public function barkodAdaydanEkle(int $stokId): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return;
        }

        $stok = StokKarti::query()
            ->select([
                'id',
                'firma_id',
                'kod',
                'ad',
                'barkod',
                'tur',
                'birim',
                'indirimli_fiyat',
                'satis_fiyati',
                'kdv_orani',
            ])
            ->where('firma_id', $firmaId)
            ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
            ->with(['gorseller' => fn ($query) => $query
                ->select(['id', 'stok_karti_id', 'dosya_yolu', 'sira', 'kapak_mi', 'aktif_mi'])
                ->where('aktif_mi', true)])
            ->find($stokId);
        if (! $stok) {
            return;
        }

        $this->stoktanSepeteEkle($stok, (string) ($stok->barkod ?: $stok->kod ?: ''));
        $this->data['barkod'] = null;
        $this->barkodAdaylari = [];
        $this->dispatch('barkod-odakla');
    }

    public function hizliAdaydanEkle(int $stokId): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return;
        }

        $stok = StokKarti::query()
            ->select([
                'id',
                'firma_id',
                'kod',
                'ad',
                'barkod',
                'tur',
                'birim',
                'indirimli_fiyat',
                'satis_fiyati',
                'kdv_orani',
            ])
            ->where('firma_id', $firmaId)
            ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
            ->with(['gorseller' => fn ($query) => $query
                ->select(['id', 'stok_karti_id', 'dosya_yolu', 'sira', 'kapak_mi', 'aktif_mi'])
                ->where('aktif_mi', true)])
            ->find($stokId);
        if (! $stok) {
            return;
        }

        $this->stoktanSepeteEkle($stok, (string) ($stok->barkod ?: $stok->kod ?: ''));
        $this->data['hizli_urun_ara'] = null;
        $this->hizliUrunAdaylari = [];
        $this->dispatch('barkod-odakla');
    }

    public function kalemSil(int $index): void
    {
        if (! isset($this->kalemler[$index])) {
            return;
        }

        unset($this->kalemler[$index]);
        $this->kalemler = array_values($this->kalemler);
        if ($this->seciliKalemIndex !== null && $this->seciliKalemIndex >= count($this->kalemler)) {
            $this->seciliKalemIndex = count($this->kalemler) > 0 ? count($this->kalemler) - 1 : null;
        }
        $this->aktifSepetiKaydet();
    }

    public function kalemSec(int $index): void
    {
        if (! isset($this->kalemler[$index])) {
            return;
        }

        $this->seciliKalemIndex = $index;
    }

    public function seciliKalemMiktarArttir(): void
    {
        if ($this->seciliKalemIndex === null || ! isset($this->kalemler[$this->seciliKalemIndex])) {
            return;
        }

        $this->kalemler[$this->seciliKalemIndex]['miktar'] = max(0.0001, (float) ($this->kalemler[$this->seciliKalemIndex]['miktar'] ?? 0)) + 1;
        $this->kalemleriNormalizeEt();
        $this->yetkiyeGoreKalemleriGuvenliHaleGetir();
        $this->aktifSepetiKaydet();
    }

    public function seciliKalemMiktarAzalt(): void
    {
        if ($this->seciliKalemIndex === null || ! isset($this->kalemler[$this->seciliKalemIndex])) {
            return;
        }

        $miktar = max(0.0001, (float) ($this->kalemler[$this->seciliKalemIndex]['miktar'] ?? 0));
        $yeni = max(0.0001, $miktar - 1);
        $this->kalemler[$this->seciliKalemIndex]['miktar'] = $yeni;
        $this->kalemleriNormalizeEt();
        $this->yetkiyeGoreKalemleriGuvenliHaleGetir();
        $this->aktifSepetiKaydet();
    }

    public function seciliKalemSil(): void
    {
        if ($this->seciliKalemIndex === null) {
            return;
        }

        $this->kalemSil($this->seciliKalemIndex);
    }

    public function indirimGirisiniAc(): void
    {
        if (! $this->iskontoUygulamaYetkisiVarMi()) {
            Notification::make()->title('İndirim uygulama yetkiniz yok')->warning()->send();

            return;
        }

        if ($this->seciliKalemIndex === null || ! isset($this->kalemler[$this->seciliKalemIndex])) {
            Notification::make()->title('İndirim için sepetten bir satır seçin')->warning()->send();

            return;
        }

        $this->indirimTutari = number_format((float) ($this->kalemler[$this->seciliKalemIndex]['iskonto_tutari'] ?? 0), 2, ',', '');
        $this->indirimGirisAcik = true;
    }

    public function indirimGirisiniKapat(): void
    {
        $this->indirimGirisAcik = false;
        $this->indirimTutari = '';
    }

    public function seciliKalemeIndirimUygula(): void
    {
        if (! $this->iskontoUygulamaYetkisiVarMi()) {
            Notification::make()->title('İndirim uygulama yetkiniz yok')->warning()->send();

            return;
        }

        if ($this->seciliKalemIndex === null || ! isset($this->kalemler[$this->seciliKalemIndex])) {
            Notification::make()->title('İndirim için sepetten bir satır seçin')->warning()->send();

            return;
        }

        $miktar = max(0.0001, (float) ($this->kalemler[$this->seciliKalemIndex]['miktar'] ?? 1));
        $fiyat = max(0, (float) ($this->kalemler[$this->seciliKalemIndex]['birim_fiyat'] ?? 0));
        $satirBrut = $miktar * $fiyat;
        $indirim = min($satirBrut, $this->parasalDegeriCoz($this->indirimTutari));

        $this->kalemler[$this->seciliKalemIndex]['iskonto_tutari'] = $indirim;
        $this->kalemleriNormalizeEt();
        $this->yetkiyeGoreKalemleriGuvenliHaleGetir();
        $this->aktifSepetiKaydet();
        $this->indirimGirisiniKapat();
    }

    public function sepetiTemizle(bool $odakla = true): void
    {
        $this->kalemler = [];
        $this->seciliKalemIndex = null;
        $this->etiketYazdirmaAdedi = 1;
        $this->data['barkod'] = null;
        $this->data['hizli_urun_ara'] = null;
        $this->barkodAdaylari = [];
        $this->hizliUrunAdaylari = [];
        $this->aktifSepetiTemizle();
        $this->aktifSepetKayitImzasi = $this->aktifSepetImzasi();

        if ($odakla) {
            $this->dispatch('barkod-odakla');
        }
    }

    public function etiketYazdirmaAdediDegistir(int $delta): void
    {
        $yeni = $this->etiketYazdirmaAdedi + $delta;
        $this->etiketYazdirmaAdedi = max(1, min(500, $yeni));
    }

    public function seciliEtiketYazdirUrl(): ?string
    {
        if ($this->seciliKalemIndex === null || ! isset($this->kalemler[$this->seciliKalemIndex])) {
            return null;
        }

        $stokId = (int) ($this->kalemler[$this->seciliKalemIndex]['stok_id'] ?? 0);
        if ($stokId < 1) {
            return null;
        }

        return BarkodEtiketYazdirmaSayfasi::getUrl([
            'stok_id' => $stokId,
            'adet' => max(1, min(500, (int) $this->etiketYazdirmaAdedi)),
            'auto_print' => 1,
        ]);
    }

    public function odemeTipiSec(string $odemeTipi): void
    {
        $izinli = ['nakit', 'kart', 'havale', 'veresiye', 'taksitli', 'diger'];
        if (! in_array($odemeTipi, $izinli, true)) {
            return;
        }

        $this->data['odeme_tipi'] = $odemeTipi;
        if (in_array($odemeTipi, ['veresiye', 'taksitli'], true)) {
            $this->data['pesinat_odeme_tipi'] = in_array((string) ($this->data['pesinat_odeme_tipi'] ?? 'nakit'), ['nakit', 'kart', 'havale'], true)
                ? (string) ($this->data['pesinat_odeme_tipi'] ?? 'nakit')
                : 'nakit';
        }

        $this->varsayilanTahsilatHesabiAta((string) ($this->data['para_birimi'] ?? 'TRY'));
        $this->aktifSepetiKaydet();
    }

    public function sepetBeklet(bool $bildirimGonder = true): void
    {
        if (count($this->kalemler) === 0) {
            if ($bildirimGonder) {
                Notification::make()->title('Sepet bos, bekletilemez')->warning()->send();
            }

            return;
        }

        /** @var array<string, mixed> $state */
        $state = is_array($this->data) ? $this->data : [];
        $kayit = [
            'etiket' => 'Sepet '.now()->format('d.m H:i:s'),
            'olusturma' => now()->toDateTimeString(),
            'kalemler' => $this->kalemler,
            'data' => [
                'cari_id' => $state['cari_id'] ?? null,
                'odeme_tipi' => $state['odeme_tipi'] ?? 'nakit',
                'kasa_hesap_id' => $state['kasa_hesap_id'] ?? null,
                'banka_hesap_id' => $state['banka_hesap_id'] ?? null,
                'pos_hesap_id' => $state['pos_hesap_id'] ?? null,
                'para_birimi' => $state['para_birimi'] ?? 'TRY',
                'not' => $state['not'] ?? null,
                'pesinat_tutari' => $state['pesinat_tutari'] ?? 0,
                'pesinat_odeme_tipi' => $state['pesinat_odeme_tipi'] ?? 'nakit',
                'vade_farki_uygula' => (bool) ($state['vade_farki_uygula'] ?? $state['faiz_uygula'] ?? false),
                'vade_farki_tipi' => $state['vade_farki_tipi'] ?? 'tek_seferlik',
                'vade_farki_orani' => $state['vade_farki_orani'] ?? $state['faiz_orani'] ?? 0,
                'vade_farki_tutari' => $state['vade_farki_tutari'] ?? $state['faiz_tutari'] ?? 0,
                'vade_tarihi' => $this->tarihDegeriniDateInputaCevir($state['vade_tarihi'] ?? null),
                'taksit_sayisi' => $state['taksit_sayisi'] ?? 1,
                'taksit_araligi_gun' => $state['taksit_araligi_gun'] ?? 30,
            ],
        ];

        array_unshift($this->bekleyenSepetler, $kayit);
        $this->bekleyenSepetler = array_slice($this->bekleyenSepetler, 0, 20);
        $this->bekleyenSepetleriKaydet();

        $this->sepetiTemizle(odakla: false);

        if ($bildirimGonder) {
            Notification::make()->title('Sepet beklemeye alindi')->success()->send();
        }
    }

    public function bekleyenSepetiYukle(int $index, bool $odakla = true): void
    {
        if (! isset($this->bekleyenSepetler[$index])) {
            return;
        }

        $kayit = $this->bekleyenSepetler[$index];
        $this->kalemler = (array) ($kayit['kalemler'] ?? []);
        $this->seciliKalemIndex = count($this->kalemler) > 0 ? 0 : null;

        $data = (array) ($kayit['data'] ?? []);
        $this->data['cari_id'] = $data['cari_id'] ?? null;
        $this->data['odeme_tipi'] = $data['odeme_tipi'] ?? 'nakit';
        $this->data['kasa_hesap_id'] = $data['kasa_hesap_id'] ?? null;
        $this->data['banka_hesap_id'] = $data['banka_hesap_id'] ?? null;
        $this->data['pos_hesap_id'] = $data['pos_hesap_id'] ?? null;
        $this->data['para_birimi'] = $data['para_birimi'] ?? 'TRY';
        $this->data['not'] = $data['not'] ?? null;
        $this->data['pesinat_tutari'] = $data['pesinat_tutari'] ?? 0;
        $this->data['pesinat_odeme_tipi'] = $data['pesinat_odeme_tipi'] ?? 'nakit';
        $this->data['vade_farki_uygula'] = (bool) ($data['vade_farki_uygula'] ?? $data['faiz_uygula'] ?? false);
        $this->data['vade_farki_tipi'] = $data['vade_farki_tipi'] ?? 'tek_seferlik';
        $this->data['vade_farki_orani'] = $data['vade_farki_orani'] ?? $data['faiz_orani'] ?? 0;
        $this->data['vade_farki_tutari'] = $data['vade_farki_tutari'] ?? $data['faiz_tutari'] ?? 0;
        $this->data['vade_tarihi'] = $this->tarihDegeriniDateInputaCevir($data['vade_tarihi'] ?? null);
        $this->data['taksit_sayisi'] = $data['taksit_sayisi'] ?? 1;
        $this->data['taksit_araligi_gun'] = $data['taksit_araligi_gun'] ?? 30;
        $this->data['barkod'] = null;
        $this->data['hizli_urun_ara'] = null;

        $this->aktifSepetiKaydet();

        if ($odakla) {
            $this->dispatch('barkod-odakla');
        }
    }

    #[Renderless]
    public function bekleyenSepetiSil(int $index): void
    {
        if (! isset($this->bekleyenSepetler[$index])) {
            return;
        }

        unset($this->bekleyenSepetler[$index]);
        $this->bekleyenSepetler = array_values($this->bekleyenSepetler);
        $this->bekleyenSepetleriKaydet();
    }

    public function satisiTamamla(): void
    {
        $this->satisiKaydet(yazdir: false);
    }

    public function satisiTamamlaVeYazdir(): void
    {
        $this->satisiKaydet(yazdir: true);
    }

    private function satisiKaydet(bool $yazdir = false): void
    {
        if (! $this->islemYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()->title('Aktif firma bulunamadi')->danger()->send();

            return;
        }

        if (count($this->kalemler) === 0) {
            Notification::make()->title('Sepet bos')->warning()->send();

            return;
        }

        $this->kalemleriNormalizeEt();
        $this->yetkiyeGoreKalemleriGuvenliHaleGetir();
        $veri = $this->form->getState();
        if (
            in_array((string) ($veri['odeme_tipi'] ?? ''), ['veresiye', 'taksitli'], true)
            && (int) ($veri['cari_id'] ?? 0) < 1
        ) {
            Notification::make()
                ->title('Musteri / Cari secin')
                ->body('Veresiye veya taksitli satisi tamamlamadan once musteri veya cari secimi yapmalisiniz.')
                ->warning()
                ->send();
            $this->dispatch('barkod-odakla');

            return;
        }

        $veri['vade_tarihi'] = $this->tarihDegeriniDateInputaCevir($veri['vade_tarihi'] ?? null);
        $veri['pesinat_tutari'] = $this->parasalDegeriCoz((string) ($veri['pesinat_tutari'] ?? 0));
        $veri['pesinat_odeme_tipi'] = in_array((string) ($veri['pesinat_odeme_tipi'] ?? 'nakit'), ['nakit', 'kart', 'havale'], true)
            ? (string) ($veri['pesinat_odeme_tipi'] ?? 'nakit')
            : 'nakit';
        $veri['vade_farki_uygula'] = (bool) ($veri['vade_farki_uygula'] ?? false);
        $veri['vade_farki_tipi'] = $this->vadeFarkiTipi((string) ($veri['vade_farki_tipi'] ?? 'tek_seferlik'));
        $veri['vade_farki_orani'] = $this->parasalDegeriCoz((string) ($veri['vade_farki_orani'] ?? 0));
        $finansOzeti = $this->vadeliSatisFinansOzeti();
        $veri['vade_farki_tutari'] = $veri['vade_farki_uygula'] ? $finansOzeti['vade_farki_tutari'] : 0;
        $veri['taksit_sayisi'] = max(1, (int) ($veri['taksit_sayisi'] ?? 1));
        $veri['taksit_araligi_gun'] = max(1, (int) ($veri['taksit_araligi_gun'] ?? 30));
        if (
            in_array((string) ($veri['odeme_tipi'] ?? ''), ['veresiye', 'taksitli'], true)
            && (float) $veri['pesinat_tutari'] > 0
        ) {
            $this->data['pesinat_odeme_tipi'] = $veri['pesinat_odeme_tipi'];
            $this->varsayilanTahsilatHesabiAta((string) ($veri['para_birimi'] ?? 'TRY'));
            $veri['kasa_hesap_id'] = $veri['kasa_hesap_id'] ?? $this->data['kasa_hesap_id'] ?? null;
            $veri['banka_hesap_id'] = $veri['banka_hesap_id'] ?? $this->data['banka_hesap_id'] ?? null;
            $veri['pos_hesap_id'] = $veri['pos_hesap_id'] ?? $this->data['pos_hesap_id'] ?? null;
        }
        if (! $this->tahsilatHesabiParaBirimiUyumluMu($veri)) {
            return;
        }

        $veri['kalemler'] = $this->kalemler;
        $veri['eksi_stok_izinli'] = $this->eksiStokIzinliMi();

        try {
            $satis = app(BarkodluSatisServisi::class)->satisTamamla(
                firmaId: $firmaId,
                kullaniciId: (int) auth()->id(),
                veri: $veri,
            );

            $this->kalemler = [];
            $this->seciliKalemIndex = null;
            $this->data['barkod'] = null;
            $this->aktifSepetiTemizle();

            Notification::make()
                ->title('Satis kaydedildi')
                ->body('Satis No: '.$satis->satis_no)
                ->actions([
                    NotificationAction::make('fis')
                        ->label('Satis Fisi')
                        ->url(BarkodluSatisFisiSayfasi::getUrl(['satis' => (int) $satis->id]))
                        ->openUrlInNewTab(),
                ])
                ->success()
                ->send();

            if ($yazdir) {
                $this->dispatch('satis-fisi-ac', url: BarkodluSatisFisiSayfasi::getUrl([
                    'satis' => (int) $satis->id,
                    'auto_print' => 1,
                ]));
            }

            $this->dispatch('barkod-odakla');
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Satis kaydedilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->dispatch('barkod-odakla');
        }
    }

    /**
     * @return array{ara_toplam:float,iskonto_toplami:float,kdv_toplami:float,genel_toplam:float}
     */
    public function sepetOzeti(): array
    {
        $cacheHash = md5(json_encode($this->kalemler, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '');

        if ($this->sepetOzetiCache !== null && $this->sepetOzetiCacheHash === $cacheHash) {
            return $this->sepetOzetiCache;
        }

        $this->sepetOzetiCache = app(BarkodluSatisServisi::class)->ozetHesapla($this->kalemler);
        $this->sepetOzetiCacheHash = $cacheHash;

        return $this->sepetOzetiCache;
    }

    /**
     * @return array{ana_tutar:float,pesinat_tutari:float,vade_farki_orani:float,vade_farki_tutari:float,planlanacak_tutar:float}
     */
    public function vadeliSatisFinansOzeti(): array
    {
        $ozet = $this->sepetOzeti();
        $anaTutar = max(0, (float) ($ozet['genel_toplam'] ?? 0));
        $pesinat = min($anaTutar, $this->parasalDegeriCoz((string) ($this->data['pesinat_tutari'] ?? 0)));
        $vadeFarkiUygula = (bool) ($this->data['vade_farki_uygula'] ?? false);
        $vadeFarkiOrani = $vadeFarkiUygula ? $this->parasalDegeriCoz((string) ($this->data['vade_farki_orani'] ?? 0)) : 0.0;
        $vadeFarkiTipi = $this->vadeFarkiTipi((string) ($this->data['vade_farki_tipi'] ?? 'tek_seferlik'));
        $vadeFarkiTutari = $vadeFarkiUygula ? $this->vadeFarkiTutariHesapla(max(0, $anaTutar - $pesinat), $vadeFarkiOrani, $vadeFarkiTipi) : 0.0;
        $planlanacak = max(0, $anaTutar + $vadeFarkiTutari - $pesinat);

        return [
            'ana_tutar' => round($anaTutar, 2),
            'pesinat_tutari' => round($pesinat, 2),
            'vade_farki_orani' => round($vadeFarkiOrani, 4),
            'vade_farki_tutari' => round($vadeFarkiTutari, 2),
            'planlanacak_tutar' => round($planlanacak, 2),
        ];
    }

    public function updatedKalemler(mixed $value = null, ?string $name = null): void
    {
        $this->kalemleriNormalizeEt();
        $this->yetkiyeGoreKalemleriGuvenliHaleGetir();
        $this->aktifSepetiKaydet();
    }

    public function updatedDataBarkod(mixed $value): void
    {
        $this->barkodAdaylari = $this->stokAdaylariGetir((string) ($value ?? ''));
    }

    public function updatedDataHizliUrunAra(mixed $value): void
    {
        $this->hizliUrunAramaLimit = 8;
        $this->hizliUrunAdaylariniYenile((string) ($value ?? ''));
    }

    public function updatedDataPesinatOdemeTipi(mixed $value): void
    {
        $this->data['pesinat_odeme_tipi'] = in_array((string) ($value ?? 'nakit'), ['nakit', 'kart', 'havale'], true)
            ? (string) ($value ?? 'nakit')
            : 'nakit';
        $this->varsayilanTahsilatHesabiAta((string) ($this->data['para_birimi'] ?? 'TRY'));
        $this->aktifSepetiKaydet();
    }

    public function updatedDataParaBirimi(mixed $value): void
    {
        $paraBirimi = strtoupper((string) ($value ?: 'TRY'));
        $this->data['para_birimi'] = in_array($paraBirimi, ['TRY', 'USD', 'EUR'], true) ? $paraBirimi : 'TRY';
        $this->varsayilanTahsilatHesabiAta($this->data['para_birimi']);
        $this->aktifSepetiKaydet();
    }

    public function updatedHizliUrunAramaSiralamasi(mixed $value): void
    {
        $izinliSiralamalar = ['ilgili', 'ad', 'fiyat_artan', 'fiyat_azalan', 'stok_fazla'];
        $this->hizliUrunAramaSiralamasi = in_array($value, $izinliSiralamalar, true) ? (string) $value : 'ilgili';
        $this->hizliUrunAramaLimit = 8;
        $this->hizliUrunAdaylariniYenile((string) ($this->data['hizli_urun_ara'] ?? ''));
    }

    public function hizliUrunAramaDahaFazla(): void
    {
        $this->hizliUrunAramaLimit += 8;
        $this->hizliUrunAdaylariniYenile((string) ($this->data['hizli_urun_ara'] ?? ''));
    }

    public function hizliCariSec(?int $cariId = null): void
    {
        if (! $cariId || $cariId < 1) {
            $this->data['cari_id'] = null;

            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1 || ! Cari::query()->where('firma_id', $firmaId)->whereKey($cariId)->exists()) {
            return;
        }

        $this->data['cari_id'] = $cariId;
    }

    public function hizliUrunAramayiTemizle(): void
    {
        $this->data['hizli_urun_ara'] = null;
        $this->hizliUrunAdaylari = [];
        $this->hizliUrunAdayToplam = 0;
        $this->hizliUrunAramaLimit = 8;
    }

    /**
     * @return array<int, string>
     */
    private function cariAramaSonuclari(string $search): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        $search = trim($search);

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('ad', 'like', '%'.$search.'%')
                        ->orWhere('kod', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('ad')
            ->limit(25)
            ->get(['id', 'ad', 'kod', 'para_birimi'])
            ->mapWithKeys(fn (Cari $cari): array => [
                $cari->id => $this->cariSecenekEtiketi($cari),
            ])
            ->all();
    }

    private function cariEtiketi(int $cariId): ?string
    {
        if ($cariId < 1) {
            return null;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return null;
        }

        $cari = Cache::remember(
            'muhasebe:barkodlu-satis:cari-etiketi:v1:'.$firmaId.':'.$cariId,
            now()->addSeconds(60),
            fn (): ?Cari => Cari::query()
                ->where('firma_id', $firmaId)
                ->whereKey($cariId)
                ->first(['id', 'ad', 'kod', 'para_birimi'])
        );

        return $cari instanceof Cari ? $this->cariSecenekEtiketi($cari) : null;
    }

    private function cariSecenekEtiketi(Cari $cari): string
    {
        return trim($cari->ad.($cari->kod ? ' - '.$cari->kod : '').' ('.strtoupper((string) ($cari->para_birimi ?: 'TRY')).')');
    }

    private function cariParaBirimi(int $cariId): ?string
    {
        if ($cariId < 1) {
            return null;
        }

        $firmaId = $this->aktifFirmaId();
        $paraBirimi = Cache::remember(
            'muhasebe:barkodlu-satis:cari-para-birimi:v1:'.$firmaId.':'.$cariId,
            now()->addSeconds(60),
            fn (): ?string => Cari::query()
                ->where('firma_id', $firmaId)
                ->whereKey($cariId)
                ->value('para_birimi')
        );

        return $paraBirimi ? strtoupper((string) $paraBirimi) : null;
    }

    /**
     * @return array<int, string>
     */
    private function hesapSecimiYardimMetni(string $tur, Forms\Get $get): ?string
    {
        $odemeTipi = (string) $get('odeme_tipi');
        $beklenenTip = match ($tur) {
            'kasa' => 'nakit',
            'banka' => 'havale',
            'pos' => 'kart',
            default => '',
        };

        if ($odemeTipi !== $beklenenTip) {
            return null;
        }

        $secenekler = match ($tur) {
            'kasa' => $this->kasaSecenekleri(),
            'banka' => $this->bankaSecenekleri(),
            'pos' => $this->posSecenekleri(),
            default => [],
        };

        return $secenekler === []
            ? 'Bu para biriminde aktif hesap yok. Yanındaki butondan yeni tanım açın.'
            : 'Yalnızca bu firmaya ait aktif ve aynı para birimindeki hesaplar gösterilir.';
    }

    private function hesapTanimAction(string $tur, Forms\Get $get): ?Forms\Components\Actions\Action
    {
        $odemeTipi = (string) $get('odeme_tipi');
        $beklenenTip = match ($tur) {
            'kasa' => 'nakit',
            'banka' => 'havale',
            'pos' => 'kart',
            default => '',
        };

        if ($odemeTipi !== $beklenenTip) {
            return null;
        }

        $secenekler = match ($tur) {
            'kasa' => $this->kasaSecenekleri(),
            'banka' => $this->bankaSecenekleri(),
            'pos' => $this->posSecenekleri(),
            default => [],
        };

        if ($secenekler !== []) {
            return null;
        }

        [$label, $url] = match ($tur) {
            'kasa' => ['Yeni kasa aç', KasaHesabiKaynagi::getUrl('create')],
            'banka' => ['Yeni banka hesabı aç', BankaHesabiKaynagi::getUrl('create')],
            'pos' => ['Yeni POS hesabı aç', PosHesabiKaynagi::getUrl('create')],
            default => ['Yeni hesap aç', '#'],
        };

        return Forms\Components\Actions\Action::make('yeni_'.$tur)
            ->label($label)
            ->icon('heroicon-m-plus')
            ->url($url)
            ->openUrlInNewTab();
    }

    /**
     * @return array<int, string>
     */
    private function kasaSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        $paraBirimi = strtoupper((string) ($this->data['para_birimi'] ?? 'TRY'));

        return KasaHesabi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif->value)
            ->where('para_birimi', $paraBirimi)
            ->orderBy('ad')
            ->get(['id', 'ad', 'para_birimi'])
            ->mapWithKeys(fn (KasaHesabi $hesap): array => [
                $hesap->id => $hesap->ad.' ('.strtoupper((string) ($hesap->para_birimi ?? 'TRY')).')',
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function bankaSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        $paraBirimi = strtoupper((string) ($this->data['para_birimi'] ?? 'TRY'));

        return BankaHesabi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif->value)
            ->where('para_birimi', $paraBirimi)
            ->orderBy('ad')
            ->get(['id', 'ad', 'para_birimi'])
            ->mapWithKeys(fn (BankaHesabi $hesap): array => [
                $hesap->id => $hesap->ad.' ('.strtoupper((string) ($hesap->para_birimi ?? 'TRY')).')',
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function posSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        $paraBirimi = strtoupper((string) ($this->data['para_birimi'] ?? 'TRY'));

        return PosHesabi::query()
            ->where('firma_id', $firmaId)
            ->where('durum', HesapDurumu::Aktif->value)
            ->where('para_birimi', $paraBirimi)
            ->orderBy('ad')
            ->get(['id', 'ad', 'para_birimi'])
            ->mapWithKeys(fn (PosHesabi $hesap): array => [
                $hesap->id => $hesap->ad.' ('.strtoupper((string) ($hesap->para_birimi ?? 'TRY')).')',
            ])
            ->all();
    }

    private function aktifFirmaId(): int
    {
        return $this->aktifFirmaIdCache ??= (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }

    /**
     * @return array<int, string>
     */
    protected function barkodluSatisGorunenStokTurleri(): array
    {
        $varsayilan = array_map(
            static fn (StokKartiTuru $tur): string => $tur->value,
            StokKartiTuru::cases()
        );

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return $varsayilan;
        }

        $turler = app(FirmaAyarDeposu::class)->oku($firmaId, 'barkodlu_satis_gorunen_stok_turleri', $varsayilan);
        $turler = is_array($turler) ? $turler : [];

        $secili = array_values(array_intersect(
            array_map(static fn (mixed $tur): string => (string) $tur, $turler),
            $varsayilan
        ));

        return $secili !== [] ? $secili : $varsayilan;
    }

    private function stokBarkodTablosuVarMi(): bool
    {
        if (self::$stokBarkodTablosuVar !== null) {
            return self::$stokBarkodTablosuVar;
        }

        self::$stokBarkodTablosuVar = Schema::hasTable('stok_barkodlari');

        return self::$stokBarkodTablosuVar;
    }

    private function varsayilanOdemeTipi(): string
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return 'nakit';
        }

        $odemeTipi = (string) app(FirmaAyarDeposu::class)->oku($firmaId, 'barkodlu_satis_varsayilan_odeme_tipi', 'nakit');

        return in_array($odemeTipi, ['nakit', 'kart', 'havale', 'veresiye', 'taksitli', 'diger'], true) ? $odemeTipi : 'nakit';
    }

    private function odemeTipineGoreVarsayilanHesapAta(string $odemeTipi, string $paraBirimi): void
    {
        if ($odemeTipi === 'nakit') {
            $this->data['kasa_hesap_id'] = $this->varsayilanKasaHesapId($paraBirimi);

            return;
        }

        if ($odemeTipi === 'havale') {
            $this->data['banka_hesap_id'] = $this->varsayilanBankaHesapId($paraBirimi);

            return;
        }

        if ($odemeTipi === 'kart') {
            $this->data['pos_hesap_id'] = $this->varsayilanPosHesapId($paraBirimi);
        }
    }

    private function varsayilanTahsilatHesabiAta(string $paraBirimi): void
    {
        $odemeTipi = (string) ($this->data['odeme_tipi'] ?? 'nakit');
        if (in_array($odemeTipi, ['veresiye', 'taksitli'], true)) {
            $odemeTipi = in_array((string) ($this->data['pesinat_odeme_tipi'] ?? 'nakit'), ['nakit', 'kart', 'havale'], true)
                ? (string) ($this->data['pesinat_odeme_tipi'] ?? 'nakit')
                : 'nakit';
        }

        $this->data['kasa_hesap_id'] = null;
        $this->data['banka_hesap_id'] = null;
        $this->data['pos_hesap_id'] = null;
        $this->odemeTipineGoreVarsayilanHesapAta($odemeTipi, $paraBirimi);
    }

    private function varsayilanKasaHesapId(string $paraBirimi): ?int
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return null;
        }

        $kayit = Cache::remember(
            'barkodlu_satis:varsayilan_hesap:kasa:firma:'.$firmaId.':'.strtoupper($paraBirimi),
            now()->addMinutes(3),
            fn () => KasaHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->where('para_birimi', strtoupper($paraBirimi))
                ->orderBy('ad')
                ->value('id')
        );

        if ($kayit !== null) {
            return (int) $kayit;
        }

        return null;
    }

    private function varsayilanBankaHesapId(string $paraBirimi): ?int
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return null;
        }

        $kayit = Cache::remember(
            'barkodlu_satis:varsayilan_hesap:banka:firma:'.$firmaId.':'.strtoupper($paraBirimi),
            now()->addMinutes(3),
            fn () => BankaHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->where('para_birimi', strtoupper($paraBirimi))
                ->orderBy('ad')
                ->value('id')
        );

        if ($kayit !== null) {
            return (int) $kayit;
        }

        return null;
    }

    private function varsayilanPosHesapId(string $paraBirimi): ?int
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return null;
        }

        $kayit = Cache::remember(
            'barkodlu_satis:varsayilan_hesap:pos:firma:'.$firmaId.':'.strtoupper($paraBirimi),
            now()->addMinutes(3),
            fn () => PosHesabi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', HesapDurumu::Aktif->value)
                ->where('para_birimi', strtoupper($paraBirimi))
                ->orderBy('ad')
                ->value('id')
        );

        if ($kayit !== null) {
            return (int) $kayit;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $veri
     */
    private function tahsilatHesabiParaBirimiUyumluMu(array $veri): bool
    {
        $paraBirimi = strtoupper((string) ($veri['para_birimi'] ?? 'TRY'));
        $odemeTipi = (string) ($veri['odeme_tipi'] ?? 'nakit');

        if (in_array($odemeTipi, ['veresiye', 'taksitli'], true)) {
            if ((float) ($veri['pesinat_tutari'] ?? 0) <= 0) {
                return true;
            }

            $odemeTipi = in_array((string) ($veri['pesinat_odeme_tipi'] ?? 'nakit'), ['nakit', 'kart', 'havale'], true)
                ? (string) ($veri['pesinat_odeme_tipi'] ?? 'nakit')
                : 'nakit';
        }

        $hesap = match ($odemeTipi) {
            'nakit' => [KasaHesabi::class, 'kasa_hesap_id', 'Kasa'],
            'kart' => [PosHesabi::class, 'pos_hesap_id', 'POS'],
            'havale' => [BankaHesabi::class, 'banka_hesap_id', 'Banka'],
            default => null,
        };

        if ($hesap === null) {
            return true;
        }

        [$model, $alan, $etiket] = $hesap;
        $hesapId = (int) ($veri[$alan] ?? 0);
        if ($hesapId < 1) {
            Notification::make()
                ->title($etiket.' secin')
                ->body($paraBirimi.' para birimine ait bir '.$etiket.' secmelisiniz.')
                ->warning()
                ->send();

            return false;
        }

        $uygunMu = $model::query()
            ->where('firma_id', $this->aktifFirmaId())
            ->where('durum', HesapDurumu::Aktif->value)
            ->where('para_birimi', $paraBirimi)
            ->whereKey($hesapId)
            ->exists();

        if ($uygunMu) {
            return true;
        }

        Notification::make()
            ->title($etiket.' para birimi uyumsuz')
            ->body('Secilen '.$etiket.' '.$paraBirimi.' para birimine ait degil. Lutfen '.$paraBirimi.' hesabi secin.')
            ->warning()
            ->send();

        return false;
    }

    private function eksiStokIzinliMi(): bool
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return false;
        }

        return (bool) app(FirmaAyarDeposu::class)->oku($firmaId, 'barkodlu_satis_eksi_stok_izinli', false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stokAdaylariGetir(string $arama, ?int $limit = null, string $siralama = 'ilgili'): array
    {
        $firmaId = $this->aktifFirmaId();
        $arama = trim($arama);
        if ($firmaId < 1 || mb_strlen($arama) < 2) {
            return [];
        }

        return $this->stokAdaylariSorgusu($arama)
            ->select([
                'id',
                'firma_id',
                'kod',
                'ad',
                'barkod',
                'stok_miktari',
                'birim',
                'indirimli_fiyat',
                'satis_fiyati',
            ])
            ->with(['gorseller' => fn ($query) => $query
                ->select(['id', 'stok_karti_id', 'dosya_yolu', 'sira', 'kapak_mi', 'aktif_mi'])
                ->where('aktif_mi', true)])
            ->tap(fn ($query) => $this->stokAdaylariSirala($query, $arama, $siralama))
            ->limit($limit ?? 8)
            ->get()
            ->map(fn (StokKarti $stok): array => [
                'id' => (int) $stok->id,
                'kod' => (string) ($stok->kod ?? ''),
                'ad' => (string) $stok->ad,
                'barkod' => (string) ($stok->barkod ?? ''),
                'stok' => (float) ($stok->stok_miktari ?? 0),
                'birim' => (string) ($stok->birim ?: 'AD'),
                'fiyat' => (float) ($stok->indirimli_fiyat ?: $stok->satis_fiyati ?: 0),
                'gorsel_url' => (string) ($stok->kapak_gorsel_url ?? ''),
            ])
            ->all();
    }

    private function stokAdaylariToplam(string $arama): int
    {
        $firmaId = $this->aktifFirmaId();
        $arama = trim($arama);
        if ($firmaId < 1 || mb_strlen($arama) < 2) {
            return 0;
        }

        return (int) $this->stokAdaylariSorgusu($arama)->count();
    }

    private function hizliUrunAdaylariniYenile(string $arama): void
    {
        $arama = trim($arama);

        if ($this->aktifFirmaId() < 1 || mb_strlen($arama) < 2) {
            $this->hizliUrunAdayToplam = 0;
            $this->hizliUrunAdaylari = [];

            return;
        }

        $cacheKey = 'barkodlu_satis:stok_arama:firma:'.$this->aktifFirmaId().':'.md5(json_encode([
            'arama' => mb_strtolower($arama),
            'limit' => $this->hizliUrunAramaLimit,
            'siralama' => $this->hizliUrunAramaSiralamasi,
            'stok_turleri' => $this->barkodluSatisGorunenStokTurleri(),
        ], JSON_UNESCAPED_UNICODE) ?: '');

        $sonuc = Cache::remember($cacheKey, now()->addSeconds(20), fn (): array => [
            'toplam' => $this->stokAdaylariToplam($arama),
            'adaylar' => $this->stokAdaylariGetir(
                $arama,
                $this->hizliUrunAramaLimit,
                $this->hizliUrunAramaSiralamasi
            ),
        ]);

        $this->hizliUrunAdayToplam = (int) ($sonuc['toplam'] ?? 0);
        $this->hizliUrunAdaylari = (array) ($sonuc['adaylar'] ?? []);
    }

    private function stokAdaylariSorgusu(string $arama)
    {
        return StokKarti::query()
            ->where('firma_id', $this->aktifFirmaId())
            ->whereIn('tur', $this->barkodluSatisGorunenStokTurleri())
            ->where(function ($query) use ($arama): void {
                $query->where('kod', 'like', '%'.$arama.'%')
                    ->orWhere('ad', 'like', '%'.$arama.'%')
                    ->orWhere('barkod', 'like', '%'.$arama.'%');
            });
    }

    private function stokAdaylariSirala($query, string $arama, string $siralama): void
    {
        match ($siralama) {
            'ad' => $query->orderBy('ad'),
            'fiyat_artan' => $query->orderByRaw('COALESCE(NULLIF(indirimli_fiyat, 0), satis_fiyati, 0) asc')->orderBy('ad'),
            'fiyat_azalan' => $query->orderByRaw('COALESCE(NULLIF(indirimli_fiyat, 0), satis_fiyati, 0) desc')->orderBy('ad'),
            'stok_fazla' => $query->orderByDesc('stok_miktari')->orderBy('ad'),
            default => $query
                ->orderByRaw(
                    'CASE WHEN kod = ? OR barkod = ? THEN 0 WHEN ad LIKE ? THEN 1 WHEN ad LIKE ? THEN 2 ELSE 3 END',
                    [$arama, $arama, $arama.'%', '%'.$arama.'%']
                )
                ->orderBy('ad'),
        };
    }

    protected function stoktanSepeteEkle(StokKarti $stok, string $girilenBarkod = '', ?string $seriNo = null): void
    {
        if (! in_array((string) $stok->tur?->value, $this->barkodluSatisGorunenStokTurleri(), true)) {
            Notification::make()
                ->title('Stok türü barkodlu satışta görünmüyor')
                ->body('Bu ürünün stok türü barkodlu satış ayarlarında kapalı.')
                ->warning()
                ->send();

            return;
        }

        $satisFiyati = (float) ($stok->indirimli_fiyat ?: $stok->satis_fiyati ?: 0);

        $this->stokDizisindenSepeteEkle([
            'id' => (int) $stok->id,
            'kod' => (string) ($stok->kod ?? ''),
            'barkod' => (string) ($stok->barkod ?: $stok->kod ?: ''),
            'ad' => (string) $stok->ad,
            'stok' => (float) ($stok->stok_miktari ?? 0),
            'gorsel_url' => (string) ($stok->kapak_gorsel_url ?? ''),
            'birim' => (string) ($stok->birim ?: 'AD'),
            'fiyat' => max(0, $satisFiyati),
            'indirimli_fiyat' => max(0, (float) ($stok->indirimli_fiyat ?? 0)),
            'kdv_orani' => max(0, (float) ($stok->kdv_orani ?? 0)),
        ], $girilenBarkod, $seriNo);
    }

    /**
     * @param  array<string, mixed>  $stok
     */
    protected function stokDizisindenSepeteEkle(array $stok, string $girilenBarkod = '', ?string $seriNo = null): void
    {
        $stokId = (int) ($stok['id'] ?? 0);
        if ($stokId < 1) {
            return;
        }

        $index = collect($this->kalemler)->search(fn (array $kalem): bool => (int) ($kalem['stok_id'] ?? 0) === $stokId);
        if ($index !== false) {
            if ($seriNo !== null && $seriNo !== '') {
                $mevcutSeriler = array_values(array_filter(array_map('strval', (array) ($this->kalemler[$index]['seri_nolari'] ?? []))));
                if (in_array($seriNo, $mevcutSeriler, true)) {
                    Notification::make()->title('Bu Seri No Barkodu sepette zaten var')->warning()->send();
                    return;
                }
                $mevcutSeriler[] = $seriNo;
                $this->kalemler[$index]['seri_nolari'] = $mevcutSeriler;
            }
            $this->kalemler[$index]['miktar'] = max(0, (float) ($this->kalemler[$index]['miktar'] ?? 0)) + 1.0;
            $this->seciliKalemIndex = (int) $index;
            $this->aktifSepetiKaydet();
            return;
        }

        $this->kalemler[] = [
            'stok_id' => $stokId,
            'stok_kod' => (string) ($stok['kod'] ?? ''),
            'barkod' => $girilenBarkod !== '' ? $girilenBarkod : (string) (($stok['barkod'] ?? '') ?: ($stok['kod'] ?? '')),
            'stok_adi' => (string) ($stok['ad'] ?? ''),
            'stok_miktari' => max(0, (float) ($stok['stok'] ?? $stok['stok_miktari'] ?? 0)),
            'gorsel_url' => (string) ($stok['gorsel_url'] ?? ''),
            'birim' => (string) (($stok['birim'] ?? '') ?: 'AD'),
            'miktar' => 1.0,
            'birim_fiyat' => max(0, (float) ($stok['fiyat'] ?? 0)),
            'indirimli_fiyat' => max(0, (float) ($stok['indirimli_fiyat'] ?? 0)),
            'iskonto_tutari' => 0.0,
            'kdv_orani' => max(0, (float) ($stok['kdv_orani'] ?? 0)),
            'seri_nolari' => $seriNo !== null && $seriNo !== '' ? [$seriNo] : [],
        ];
        $this->seciliKalemIndex = count($this->kalemler) - 1;
        $this->aktifSepetiKaydet();
    }

    private function kalemleriNormalizeEt(): void
    {
        $this->kalemler = array_values(array_map(function (array $kalem): array {
            $kalem['miktar'] = max(0.0001, (float) ($kalem['miktar'] ?? 1));
            $kalem['birim_fiyat'] = max(0, (float) ($kalem['birim_fiyat'] ?? 0));
            $kalem['iskonto_tutari'] = max(0, (float) ($kalem['iskonto_tutari'] ?? 0));
            $kalem['kdv_orani'] = max(0, (float) ($kalem['kdv_orani'] ?? 0));
            return $kalem;
        }, $this->kalemler));
    }

    private function parasalDegeriCoz(string $deger): float
    {
        $temiz = preg_replace('/[^0-9,.\-]/', '', $deger) ?? '';
        if (str_contains($temiz, ',') && str_contains($temiz, '.')) {
            $temiz = str_replace('.', '', $temiz);
        }

        return max(0, (float) str_replace(',', '.', $temiz));
    }

    private function tarihDegeriniDateInputaCevir(mixed $deger): string
    {
        if (blank($deger)) {
            return now()->addDays(30)->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse((string) $deger)->format('Y-m-d');
        } catch (\Throwable) {
            return now()->addDays(30)->format('Y-m-d');
        }
    }

    private function vadeFarkiTipi(string $tip): string
    {
        return in_array($tip, ['tek_seferlik', 'aylik', 'yillik'], true) ? $tip : 'tek_seferlik';
    }

    private function vadeFarkiTutariHesapla(float $anapara, float $oran, string $tip): float
    {
        if ($anapara <= 0 || $oran <= 0) {
            return 0.0;
        }

        if ($tip === 'tek_seferlik') {
            return round($anapara * ($oran / 100), 2);
        }

        $taksitSayisi = (string) ($this->data['odeme_tipi'] ?? 'nakit') === 'taksitli'
            ? max(1, (int) ($this->data['taksit_sayisi'] ?? 1))
            : 1;
        $aralikGun = max(1, (int) ($this->data['taksit_araligi_gun'] ?? 30));
        $baslangic = \Carbon\Carbon::parse((string) ($this->data['satis_tarihi'] ?? now()->toDateString()))->startOfDay();
        $ilkVade = \Carbon\Carbon::parse($this->tarihDegeriniDateInputaCevir($this->data['vade_tarihi'] ?? null))->startOfDay();
        $taksitTutari = $anapara / max(1, $taksitSayisi);
        $toplam = 0.0;

        for ($index = 0; $index < $taksitSayisi; $index++) {
            $vade = $ilkVade->copy()->addDays($aralikGun * $index);
            $gun = max(0, $baslangic->diffInDays($vade, false));
            $donem = $tip === 'aylik' ? ($gun / 30) : ($gun / 365);
            $toplam += $taksitTutari * ($oran / 100) * $donem;
        }

        return round($toplam, 2);
    }

    private function yetkiyeGoreKalemleriGuvenliHaleGetir(): void
    {
        $fiyatYetkisi = $this->fiyatDegistirmeYetkisiVarMi();
        $iskontoYetkisi = $this->iskontoUygulamaYetkisiVarMi();
        if ($fiyatYetkisi && $iskontoYetkisi) {
            return;
        }

        $stokIdleri = collect($this->kalemler)
            ->map(fn (array $kalem): int => (int) ($kalem['stok_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $stokHaritasi = StokKarti::query()
            ->whereIn('id', $stokIdleri)
            ->get()
            ->keyBy('id');

        $this->kalemler = array_map(function (array $kalem) use ($fiyatYetkisi, $iskontoYetkisi, $stokHaritasi): array {
            $stokId = (int) ($kalem['stok_id'] ?? 0);
            $stok = $stokHaritasi->get($stokId);

            if (! $fiyatYetkisi && $stok) {
                $kalem['birim_fiyat'] = max(0, (float) ($stok->indirimli_fiyat ?: $stok->satis_fiyati ?: 0));
            }

            if (! $iskontoYetkisi) {
                $kalem['iskonto_tutari'] = 0.0;
            }

            return $kalem;
        }, $this->kalemler);
    }

    protected function islemYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_OLUSTUR,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
        ]);
    }

    public function fiyatDegistirmeYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::barkodluSatisYetkisiVarMi(
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_FIYAT_GUNCELLE
        );
    }

    public function iskontoUygulamaYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::barkodluSatisYetkisiVarMi(
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_ISKONTO_UYGULA
        );
    }

    private function bekleyenSepetCacheKey(): string
    {
        $firmaId = $this->aktifFirmaId();
        $kullaniciId = (int) (auth()->id() ?? 0);

        return 'barkodlu_satis:bekleyen_sepetler:firma:'.$firmaId.':kullanici:'.$kullaniciId;
    }

    private function aktifSepetCacheKey(): string
    {
        $firmaId = $this->aktifFirmaId();
        $kullaniciId = (int) (auth()->id() ?? 0);

        return 'barkodlu_satis:aktif_sepet:firma:'.$firmaId.':kullanici:'.$kullaniciId;
    }

    private function bekleyenSepetleriYukle(): void
    {
        $this->bekleyenSepetler = (array) (Cache::get($this->bekleyenSepetCacheKey(), []));
    }

    private function bekleyenSepetleriKaydet(): void
    {
        Cache::put($this->bekleyenSepetCacheKey(), $this->bekleyenSepetler, now()->addDays(7));
    }

    private function aktifSepetiYukle(): void
    {
        $kayit = Cache::get($this->aktifSepetCacheKey());
        if (! is_array($kayit)) {
            return;
        }

        $kalemler = (array) ($kayit['kalemler'] ?? []);
        if ($kalemler === []) {
            return;
        }

        $this->kalemler = $kalemler;
        $this->kalemleriNormalizeEt();
        $this->yetkiyeGoreKalemleriGuvenliHaleGetir();
        $this->seciliKalemIndex = count($this->kalemler) > 0 ? 0 : null;

        $data = (array) ($kayit['data'] ?? []);
        foreach ([
            'cari_id',
            'odeme_tipi',
            'kasa_hesap_id',
            'banka_hesap_id',
            'pos_hesap_id',
            'para_birimi',
            'not',
            'pesinat_tutari',
            'pesinat_odeme_tipi',
            'vade_farki_uygula',
            'vade_farki_tipi',
            'vade_farki_orani',
            'vade_farki_tutari',
            'vade_tarihi',
            'taksit_sayisi',
            'taksit_araligi_gun',
        ] as $alan) {
            if (array_key_exists($alan, $data)) {
                $this->data[$alan] = $alan === 'vade_tarihi'
                    ? $this->tarihDegeriniDateInputaCevir($data[$alan] ?? null)
                    : $data[$alan];
            }
        }

        $this->data['barkod'] = null;
        $this->data['hizli_urun_ara'] = null;
        $this->aktifSepetKayitImzasi = $this->aktifSepetImzasi();
    }

    protected function aktifSepetiKaydet(): void
    {
        $imza = $this->aktifSepetImzasi();
        if ($this->aktifSepetKayitImzasi === $imza) {
            return;
        }

        if ($this->kalemler === []) {
            $this->aktifSepetiTemizle();
            $this->aktifSepetKayitImzasi = $imza;

            return;
        }

        $state = is_array($this->data) ? $this->data : [];
        Cache::put($this->aktifSepetCacheKey(), [
            'kalemler' => $this->kalemler,
            'data' => [
                'cari_id' => $state['cari_id'] ?? null,
                'odeme_tipi' => $state['odeme_tipi'] ?? 'nakit',
                'kasa_hesap_id' => $state['kasa_hesap_id'] ?? null,
                'banka_hesap_id' => $state['banka_hesap_id'] ?? null,
                'pos_hesap_id' => $state['pos_hesap_id'] ?? null,
                'para_birimi' => $state['para_birimi'] ?? 'TRY',
                'not' => $state['not'] ?? null,
                'pesinat_tutari' => $state['pesinat_tutari'] ?? 0,
                'pesinat_odeme_tipi' => $state['pesinat_odeme_tipi'] ?? 'nakit',
                'vade_farki_uygula' => (bool) ($state['vade_farki_uygula'] ?? $state['faiz_uygula'] ?? false),
                'vade_farki_tipi' => $state['vade_farki_tipi'] ?? 'tek_seferlik',
                'vade_farki_orani' => $state['vade_farki_orani'] ?? $state['faiz_orani'] ?? 0,
                'vade_farki_tutari' => $state['vade_farki_tutari'] ?? $state['faiz_tutari'] ?? 0,
                'vade_tarihi' => $this->tarihDegeriniDateInputaCevir($state['vade_tarihi'] ?? null),
                'taksit_sayisi' => $state['taksit_sayisi'] ?? 1,
                'taksit_araligi_gun' => $state['taksit_araligi_gun'] ?? 30,
            ],
        ], now()->addDays(1));

        $this->aktifSepetKayitImzasi = $imza;
    }

    private function aktifSepetImzasi(): string
    {
        $state = is_array($this->data) ? $this->data : [];

        return md5(json_encode([
            'kalemler' => $this->kalemler,
            'data' => [
                'cari_id' => $state['cari_id'] ?? null,
                'odeme_tipi' => $state['odeme_tipi'] ?? 'nakit',
                'kasa_hesap_id' => $state['kasa_hesap_id'] ?? null,
                'banka_hesap_id' => $state['banka_hesap_id'] ?? null,
                'pos_hesap_id' => $state['pos_hesap_id'] ?? null,
                'para_birimi' => $state['para_birimi'] ?? 'TRY',
                'not' => $state['not'] ?? null,
                'pesinat_tutari' => $state['pesinat_tutari'] ?? 0,
                'pesinat_odeme_tipi' => $state['pesinat_odeme_tipi'] ?? 'nakit',
                'vade_farki_uygula' => (bool) ($state['vade_farki_uygula'] ?? $state['faiz_uygula'] ?? false),
                'vade_farki_tipi' => $state['vade_farki_tipi'] ?? 'tek_seferlik',
                'vade_farki_orani' => $state['vade_farki_orani'] ?? $state['faiz_orani'] ?? 0,
                'vade_farki_tutari' => $state['vade_farki_tutari'] ?? $state['faiz_tutari'] ?? 0,
                'vade_tarihi' => $this->tarihDegeriniDateInputaCevir($state['vade_tarihi'] ?? null),
                'taksit_sayisi' => $state['taksit_sayisi'] ?? 1,
                'taksit_araligi_gun' => $state['taksit_araligi_gun'] ?? 30,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '');
    }

    private function aktifSepetiTemizle(): void
    {
        Cache::forget($this->aktifSepetCacheKey());
    }
}

