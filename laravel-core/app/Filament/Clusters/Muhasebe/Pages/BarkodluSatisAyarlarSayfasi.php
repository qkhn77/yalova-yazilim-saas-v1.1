<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\BarkodluSatisTelegramBildirimServisi;
use App\Services\FirmaAyarDeposu;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class BarkodluSatisAyarlarSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Barkodlu satis ayarlari';

    protected static ?string $slug = 'satis/barkodlu-satis-ayarlar';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkodlu-satis-ayarlar-sayfasi';

    /** @var array<string,mixed> */
    public array $data = [];

    public function getHeading(): string|Htmlable
    {
        return 'Barkodlu satis ayarlari';
    }

    public function getSubheading(): ?string
    {
        return 'Barkodlu satis modulu icin davranis ayarlarini yonetin.';
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GORUNTULE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GUNCELLE,
        ]);
    }

    public function mount(): void
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }

        $depo = app(FirmaAyarDeposu::class);
        $telegramAyarlari = app(BarkodluSatisTelegramBildirimServisi::class)->ayarlariGetir($firmaId);

        $this->form->fill([
            'barkodlu_iade_geri_alma_suresi_saniye' => (int) $depo->oku($firmaId, 'barkodlu_iade_geri_alma_suresi_saniye', 5),
            'barkodlu_satis_eksi_stok_izinli' => (bool) $depo->oku($firmaId, 'barkodlu_satis_eksi_stok_izinli', false),
            'barkodlu_satis_varsayilan_odeme_tipi' => (string) $depo->oku($firmaId, 'barkodlu_satis_varsayilan_odeme_tipi', 'nakit'),
            'barkodlu_satis_iade_ultra_hizli_varsayilan' => (bool) $depo->oku($firmaId, 'barkodlu_satis_iade_ultra_hizli_varsayilan', true),
            'barkodlu_satis_perakende_cari_ad' => (string) $depo->oku($firmaId, 'barkodlu_satis_perakende_cari_ad', 'Perakende Musteri'),
            'barkodlu_satis_gorunen_stok_turleri' => $this->stokTurleriniNormalizeEt(
                $depo->oku($firmaId, 'barkodlu_satis_gorunen_stok_turleri', $this->varsayilanStokTurleri())
            ),
            ...$telegramAyarlari,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Hizli iade')
                    ->schema([
                        Forms\Components\TextInput::make('barkodlu_iade_geri_alma_suresi_saniye')
                            ->label('Geri alma suresi (sn)')
                            ->helperText('Otomatik iade kaydindan sonra geri alma penceresi suresi.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(5)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi())
                            ->required(),
                    ]),
                Forms\Components\Section::make('Satis davranisi')
                    ->schema([
                        Forms\Components\Toggle::make('barkodlu_satis_eksi_stok_izinli')
                            ->label('Eksi stok satisina izin ver')
                            ->helperText('Kapaliysa stok yetersizliginde satis tamamlanmaz.')
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Select::make('barkodlu_satis_varsayilan_odeme_tipi')
                            ->label('Varsayilan odeme tipi')
                            ->options([
                                'nakit' => 'Nakit',
                                'kart' => 'Kart',
                                'havale' => 'Havale/EFT',
                                'diger' => 'Diger',
                            ])
                            ->default('nakit')
                            ->required()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Toggle::make('barkodlu_satis_iade_ultra_hizli_varsayilan')
                            ->label('Iadede ultra hizli mod varsayilan acik')
                            ->helperText('Tek kalemde otomatik iade kaydi varsayilanini belirler.')
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('barkodlu_satis_perakende_cari_ad')
                            ->label('Perakende cari adi')
                            ->helperText('Cari secimsiz satislar bu cari adi ile otomatik kaydedilir (kod sabit: PERAKENDE-MUSTERI).')
                            ->maxLength(255)
                            ->default('Perakende Musteri')
                            ->required()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\CheckboxList::make('barkodlu_satis_gorunen_stok_turleri')
                            ->label('Barkodlu satis modülünde görünen stok türleri')
                            ->helperText('Secili stok turleri urun kartlarinda, barkod okutma ve hizli aramada kullanilir.')
                            ->options($this->stokTuruSecenekleri())
                            ->columns(3)
                            ->bulkToggleable()
                            ->default($this->varsayilanStokTurleri())
                            ->required()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi())
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Telegram bildirimleri')
                    ->description('Barkodlu satis olay bildirimlerini yonetin. Ortak bot ve chat bilgileri Firma Ayarları sayfasındadır.')
                    ->schema([
                        Forms\Components\Toggle::make('barkodlu_satis_telegram_aktif_mi')
                            ->label('Telegram bildirimi aktif')
                            ->live()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Toggle::make('barkodlu_satis_telegram_satis_aktif_mi')
                            ->label('Satis tamamlandi bildirimi')
                            ->default(true)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Toggle::make('barkodlu_satis_telegram_iptal_aktif_mi')
                            ->label('Satis iptal bildirimi')
                            ->default(true)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Toggle::make('barkodlu_satis_telegram_iade_aktif_mi')
                            ->label('Iade bildirimi')
                            ->default(true)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Kullanim notlari')
                    ->schema([
                        Forms\Components\Placeholder::make('ayar_notlari')
                            ->content(new HtmlString(implode('<br>', [
                                '1) Eksi stok izni sadece kontrollu operasyonlarda acik tutulmalidir.',
                                '2) Varsayilan odeme tipi POS acilisinda otomatik secilir.',
                                '3) Iadede ultra hizli mod aciksa tek kalemli satislarda iade otomatik kaydolur.',
                                '4) Cari secimsiz satislar otomatik Perakende carisine baglanir.',
                                '5) Ortak Telegram bot ve chat bilgileri Firma Ayarları sayfasından yönetilir.',
                                '6) Gorunen stok turleri ayari urun kartlari, barkod okutma ve hizli aramayi birlikte etkiler.',
                                '7) Bu ayarlar firma bazlidir; her firma kendi ayarini ayrica yonetir.',
                            ]))),
                    ]),
            ])
            ->statePath('data');
    }

    public function kaydet(): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }

        $state = $this->form->getState();
        app(FirmaAyarDeposu::class)->yaz(
            $firmaId,
            'barkodlu_iade_geri_alma_suresi_saniye',
            max(1, min(30, (int) ($state['barkodlu_iade_geri_alma_suresi_saniye'] ?? 5)))
        );
        app(FirmaAyarDeposu::class)->yaz(
            $firmaId,
            'barkodlu_satis_eksi_stok_izinli',
            (bool) ($state['barkodlu_satis_eksi_stok_izinli'] ?? false)
        );
        app(FirmaAyarDeposu::class)->yaz(
            $firmaId,
            'barkodlu_satis_varsayilan_odeme_tipi',
            in_array((string) ($state['barkodlu_satis_varsayilan_odeme_tipi'] ?? 'nakit'), ['nakit', 'kart', 'havale', 'diger'], true)
                ? (string) $state['barkodlu_satis_varsayilan_odeme_tipi']
                : 'nakit'
        );
        app(FirmaAyarDeposu::class)->yaz(
            $firmaId,
            'barkodlu_satis_iade_ultra_hizli_varsayilan',
            (bool) ($state['barkodlu_satis_iade_ultra_hizli_varsayilan'] ?? true)
        );
        app(FirmaAyarDeposu::class)->yaz(
            $firmaId,
            'barkodlu_satis_perakende_cari_ad',
            mb_substr(trim((string) ($state['barkodlu_satis_perakende_cari_ad'] ?? 'Perakende Musteri')), 0, 255)
        );
        app(FirmaAyarDeposu::class)->yaz(
            $firmaId,
            'barkodlu_satis_gorunen_stok_turleri',
            $this->stokTurleriniNormalizeEt($state['barkodlu_satis_gorunen_stok_turleri'] ?? [])
        );
        app(BarkodluSatisTelegramBildirimServisi::class)->kaydetAyarlar($firmaId, [
            'barkodlu_satis_telegram_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_aktif_mi'] ?? false),
            'barkodlu_satis_telegram_satis_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_satis_aktif_mi'] ?? true),
            'barkodlu_satis_telegram_iptal_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_iptal_aktif_mi'] ?? true),
            'barkodlu_satis_telegram_iade_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_iade_aktif_mi'] ?? true),
        ]);

        Notification::make()
            ->title('Barkodlu satis ayarlari kaydedildi.')
            ->success()
            ->send();
    }

    public function testTelegramGonder(): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }

        $state = $this->form->getState();
        app(BarkodluSatisTelegramBildirimServisi::class)->kaydetAyarlar($firmaId, [
            'barkodlu_satis_telegram_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_aktif_mi'] ?? false),
            'barkodlu_satis_telegram_satis_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_satis_aktif_mi'] ?? true),
            'barkodlu_satis_telegram_iptal_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_iptal_aktif_mi'] ?? true),
            'barkodlu_satis_telegram_iade_aktif_mi' => (bool) ($state['barkodlu_satis_telegram_iade_aktif_mi'] ?? true),
        ]);

        $basarili = app(BarkodluSatisTelegramBildirimServisi::class)->testMesajiGonder($firmaId);

        $bildirim = Notification::make()
            ->title($basarili ? 'Telegram test mesaji gonderildi.' : 'Telegram test mesaji gonderilemedi.');

        if (! $basarili) {
            $bildirim->body('Bot token, chat ID veya Telegram API yanitini kontrol edin.');
        }

        ($basarili ? $bildirim->success() : $bildirim->danger())->send();
    }

    protected function aktifFirmaId(): ?int
    {
        return app(TenantContextService::class)->aktifFirmaId();
    }

    public function ayarGuncelleYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::barkodluSatisYetkisiVarMi(
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_AYAR_GUNCELLE
        );
    }

    /**
     * @return array<string, string>
     */
    private function stokTuruSecenekleri(): array
    {
        return collect(StokKartiTuru::cases())
            ->mapWithKeys(fn (StokKartiTuru $tur): array => [$tur->value => $tur->etiket()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function varsayilanStokTurleri(): array
    {
        return array_map(
            static fn (StokKartiTuru $tur): string => $tur->value,
            StokKartiTuru::cases()
        );
    }

    /**
     * @return array<int, string>
     */
    private function stokTurleriniNormalizeEt(mixed $turler): array
    {
        $izinli = $this->varsayilanStokTurleri();
        $turler = is_array($turler) ? $turler : [];

        $secili = array_values(array_intersect(
            array_map(static fn (mixed $tur): string => (string) $tur, $turler),
            $izinli
        ));

        return $secili !== [] ? $secili : $izinli;
    }
}
