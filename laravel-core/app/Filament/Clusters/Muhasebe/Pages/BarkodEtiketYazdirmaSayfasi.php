<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\BarkodluSatis\Guvenlik\BarkodluSatisFilamentErisimYardimcisi;
use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Models\Muhasebe\EtiketSablonu;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokParcasi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Services\TenantContextService;
use App\Support\Barcode\Code128SvgUretici;
use App\Support\Barcode\Ean13SvgUretici;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BarkodEtiketYazdirmaSayfasi extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $etiketler = [];

    /** @var array<int, array<string, mixed>> */
    public array $etiketSepeti = [];

    /** @var array<int, string> */
    public array $etiketUyarilari = [];

    /** @var array<string, mixed> */
    public array $seciliSablon = [];

    public bool $otoYazdirTalebi = false;

    public bool $sablonYonetimiAcik = false;

    public ?int $stokParcasiId = null;

    private ?int $aktifFirmaIdCache = null;

    private ?int $varsayilanSablonIdCache = null;

    /** @var array<int, string>|null */
    private ?array $sablonSecenekleriCache = null;

    /** @var array<int, array<string, mixed>> */
    private array $sablonBilgisiCache = [];

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Barkod etiket yazdirma';

    protected static ?string $slug = 'satis/barkod-etiket-yazdirma';

    protected static string $view = 'filament.clusters.muhasebe.pages.barkod-etiket-yazdirma-sayfasi';

    public function getHeading(): string|Htmlable
    {
        return 'Barkod etiket yazdirma';
    }

    public function getSubheading(): ?string
    {
        return 'Stok secip etiket adedi belirleyin, onizleme alin ve yazdirin.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::BARKODLU_SATIS_ETIKET_YAZDIR;
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_ETIKET_YAZDIR,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GORUNTULE,
        ];
    }

    public static function canAccess(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi(static::muhasebeSayfasiYetkiKodlari())
            || MuhasebeFilamentErisimYardimcisi::herhangiBirMuhasebeYetkisiVarMi([
                MuhasebeYetkiSablonlari::STOK_PARTI_GORUNTULE,
                MuhasebeYetkiSablonlari::STOK_PARTI_DUZELT,
                MuhasebeYetkiSablonlari::STOK_GORUNTULE,
            ]);
    }

    public function mount(): void
    {
        $this->enCokKullanilanSablonlariTamamla();
        $varsayilanSablonId = $this->varsayilanSablonId();
        $stokId = (int) request()->query('stok_id', 0);
        $this->stokParcasiId = (int) request()->query('stok_parcasi_id', 0) ?: null;
        $adet = max(1, min(500, (int) request()->query('adet', 1)));
        $this->otoYazdirTalebi = (bool) request()->boolean('auto_print', false);

        $this->form->fill([
            'stok_id' => $stokId > 0 ? $stokId : null,
            'adet' => $adet,
            'onizleme_olcek' => '100',
            'baski_modu' => 'rulo',
            'sayfa_ust_bosluk_mm' => 0,
            'sayfa_sol_bosluk_mm' => 0,
            'etiket_yatay_bosluk_mm' => 2,
            'etiket_dikey_bosluk_mm' => 2,
            'sayfa_sutun_sayisi' => 3,
            'stok_adi_goster' => true,
            'stok_kodu_goster' => true,
            'fiyat_goster' => true,
            'barkod_yazisi_goster' => true,
            'etiket_sablonu_id' => $varsayilanSablonId,
            'sablon_ad' => '',
            'sablon_kod' => '',
            'sablon_genislik_mm' => 50,
            'sablon_yukseklik_mm' => 30,
            'sablon_barkod_tipi' => 'ean13',
            'sablon_tasarim_tipi' => 'standart',
            'sablon_aktif' => true,
            'sablon_varsayilan_mi' => false,
        ]);
        $this->seciliSablon = $this->varsayilanSablonBilgisi();
        if ($varsayilanSablonId > 0) {
            $this->seciliSablon = $this->sablonBilgisiGetir((int) $varsayilanSablonId);
        }

        if ($this->stokParcasiId) {
            $parti = StokParcasi::query()
                ->with('stokKarti:id,firma_id,kod,ad,satis_fiyati,para_birimi')
                ->where('firma_id', $this->aktifFirmaId())
                ->where('parca_mi', true)
                ->find($this->stokParcasiId);
            if ($parti?->stokKarti) {
                $this->data['stok_id'] = (int) $parti->stok_id;
                $this->etiketSepeti = [[
                    'stok_id' => (int) $parti->stok_id,
                    'stok_parcasi_id' => (int) $parti->id,
                    'stok_adi' => (string) $parti->stokKarti->ad,
                    'kod' => (string) ($parti->parca_kodu ?: $parti->parca_kodu),
                    'barkod' => (string) ($parti->barkod ?: $parti->parca_kodu ?: $parti->parca_kodu),
                    'fiyat' => number_format((float) ($parti->stokKarti->satis_fiyati ?? 0), 2, ',', '.'),
                    'para_birimi' => strtoupper((string) ($parti->stokKarti->para_birimi ?? 'TRY')),
                    'stok_miktari' => (float) $parti->kalan_miktar,
                    'adet' => $adet,
                    'barkod_tipi' => 'code128',
                ]];
                $this->etiketleriOlustur();

                return;
            }
        }

        if ($stokId > 0) {
            $this->etiketleriOlustur();
        }
    }

    public function form(Form $form): Form
    {
        $schema = [
            Forms\Components\Section::make('Etiket ayarlari')
                ->schema([
                    Forms\Components\Select::make('stok_id')
                        ->label('Stok')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => $this->stokSecenekleri($search))
                        ->getOptionLabelUsing(fn ($value): ?string => $this->stokSecenegiEtiketi((int) $value))
                        ->required(),
                    Forms\Components\TextInput::make('adet')
                        ->label('Etiket adedi')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(500)
                        ->default(1)
                        ->required(),
                    Forms\Components\Select::make('onizleme_olcek')
                        ->label('Onizleme olcegi')
                        ->options([
                            'real' => 'Gercek Boyut',
                            '50' => '%50',
                            '75' => '%75',
                            '100' => '%100',
                        ])
                        ->default('100')
                        ->live(),
                    Forms\Components\Select::make('etiket_sablonu_id')
                        ->label('Etiket sablonu')
                        ->options(fn (): array => $this->sablonSecenekleri())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state): void {
                            $this->seciliSablon = $this->sablonBilgisiGetir((int) $state);

                            if ($this->sablonYonetimiAcik) {
                                $this->sablonDuzenlemeVerisiniYukle((int) $state);
                            }
                        }),
                ])
                ->columns(4),
            Forms\Components\Section::make('Baski ayarlari')
                ->schema([
                    Forms\Components\Select::make('baski_modu')
                        ->label('Baski modu')
                        ->options([
                            'rulo' => 'Rulo / etiket yazici',
                            'a4' => 'A4 etiket kagidi',
                        ])
                        ->default('rulo')
                        ->live(),
                    Forms\Components\TextInput::make('sayfa_ust_bosluk_mm')
                        ->label('Ust bosluk (mm)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->live(),
                    Forms\Components\TextInput::make('sayfa_sol_bosluk_mm')
                        ->label('Sol bosluk (mm)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->live(),
                    Forms\Components\TextInput::make('etiket_yatay_bosluk_mm')
                        ->label('Yatay ara (mm)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->default(2)
                        ->live(),
                    Forms\Components\TextInput::make('etiket_dikey_bosluk_mm')
                        ->label('Dikey ara (mm)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->default(2)
                        ->live(),
                    Forms\Components\TextInput::make('sayfa_sutun_sayisi')
                        ->label('Sutun sayisi')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(8)
                        ->default(3)
                        ->live(),
                    Forms\Components\Toggle::make('stok_adi_goster')
                        ->label('Urun adi')
                        ->default(true)
                        ->live(),
                    Forms\Components\Toggle::make('stok_kodu_goster')
                        ->label('Stok kodu')
                        ->default(true)
                        ->live(),
                    Forms\Components\Toggle::make('fiyat_goster')
                        ->label('Fiyat')
                        ->default(true)
                        ->live(),
                    Forms\Components\Toggle::make('barkod_yazisi_goster')
                        ->label('Barkod yazisi')
                        ->default(true)
                        ->live(),
                ])
                ->columns(5),
        ];

        if ($this->sablonYonetimiAcik) {
            $schema[] = Forms\Components\Section::make('Sablon yonetimi')
                ->schema([
                    Forms\Components\TextInput::make('sablon_ad')
                        ->label('Sablon adi')
                        ->maxLength(191)
                        ->required(),
                    Forms\Components\TextInput::make('sablon_kod')
                        ->label('Sablon kodu')
                        ->helperText('Bos birakilirsa sablon adindan otomatik olusturulur.')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('sablon_genislik_mm')
                        ->label('Genislik (mm)')
                        ->numeric()
                        ->minValue(20)
                        ->maxValue(200)
                        ->required(),
                    Forms\Components\TextInput::make('sablon_yukseklik_mm')
                        ->label('Yukseklik (mm)')
                        ->numeric()
                        ->minValue(10)
                        ->maxValue(200)
                        ->required(),
                    Forms\Components\Select::make('sablon_barkod_tipi')
                        ->label('Barkod tipi')
                        ->options([
                            'ean13' => 'EAN13',
                            'code128' => 'Code128',
                        ])
                        ->default('ean13')
                        ->required(),
                    Forms\Components\Select::make('sablon_tasarim_tipi')
                        ->label('Tasarim tipi')
                        ->options(fn (): array => $this->tasarimTipiSecenekleri())
                        ->default('standart')
                        ->required(),
                    Forms\Components\Toggle::make('sablon_aktif')
                        ->label('Aktif')
                        ->default(true),
                    Forms\Components\Toggle::make('sablon_varsayilan_mi')
                        ->label('Varsayilan sablon yap'),
                ])
                ->columns(4)
                ->collapsible()
                ->collapsed();
        }

        return $form
            ->statePath('data')
            ->schema($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('olustur')
                ->label('Etiketleri Olustur')
                ->icon('heroicon-o-tag')
                ->color('warning')
                ->visible(fn (): bool => $this->etiketYetkisiVarMi())
                ->action('etiketleriOlustur'),
        ];
    }

    public function stokSepeteEkle(): void
    {
        if (! $this->etiketYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $state = $this->form->getState();
        $firmaId = $this->aktifFirmaId();
        $stokId = (int) ($state['stok_id'] ?? 0);
        $adet = max(1, min(500, (int) ($state['adet'] ?? 1)));
        $stok = $this->stokBul($stokId, $firmaId);

        if (! $stok) {
            Notification::make()->title('Stok secimi gecersiz')->danger()->send();

            return;
        }

        foreach ($this->etiketSepeti as $index => $satir) {
            if ((int) ($satir['stok_id'] ?? 0) === $stokId) {
                $this->etiketSepeti[$index]['adet'] = min(500, (int) ($satir['adet'] ?? 0) + $adet);
                $this->etiketSepeti[$index]['stok_miktari'] = (float) ($stok->stok_miktari ?? 0);
                $this->etiketSepeti[$index]['fiyat'] = number_format((float) ($stok->satis_fiyati ?? 0), 2, ',', '.');

                return;
            }
        }

        $this->etiketSepeti[] = [
            'stok_id' => $stokId,
            'stok_adi' => (string) $stok->ad,
            'kod' => (string) ($stok->kod ?? ''),
            'barkod' => trim((string) ($stok->barkod ?: $stok->kod ?: $stok->id)),
            'fiyat' => number_format((float) ($stok->satis_fiyati ?? 0), 2, ',', '.'),
            'para_birimi' => strtoupper((string) ($stok->para_birimi ?? 'TRY')),
            'stok_miktari' => (float) ($stok->stok_miktari ?? 0),
            'adet' => $adet,
        ];
    }

    public function seciliUrunStokAdediniKullan(): void
    {
        $stok = $this->stokBul((int) ($this->data['stok_id'] ?? 0), $this->aktifFirmaId());
        if (! $stok) {
            return;
        }

        $this->data['adet'] = max(1, min(500, (int) floor((float) ($stok->stok_miktari ?? 1))));
    }

    public function sepetSatirAdediGuncelle(int $index, mixed $adet): void
    {
        if (! isset($this->etiketSepeti[$index])) {
            return;
        }

        $this->etiketSepeti[$index]['adet'] = max(1, min(500, (int) $adet));
    }

    public function sepetSatiriniSil(int $index): void
    {
        if (! isset($this->etiketSepeti[$index])) {
            return;
        }

        unset($this->etiketSepeti[$index]);
        $this->etiketSepeti = array_values($this->etiketSepeti);
    }

    public function sepetiTemizle(): void
    {
        $this->etiketSepeti = [];
        $this->etiketler = [];
        $this->etiketUyarilari = [];
    }

    public function etiketleriOlustur(): void
    {
        if (! $this->etiketYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $data = $this->form->getState();
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()->title('Aktif firma bulunamadi')->danger()->send();

            return;
        }

        $stokId = (int) ($data['stok_id'] ?? 0);
        $adet = (int) ($data['adet'] ?? 1);
        $sablonId = (int) ($data['etiket_sablonu_id'] ?? 0);
        $adet = max(1, min(500, $adet));
        $this->seciliSablon = $this->sablonBilgisiGetir($sablonId);

        $kaynakSatirlar = $this->etiketSepeti;
        if ($kaynakSatirlar === []) {
            $stok = $this->stokBul($stokId, $firmaId);

            if (! $stok) {
                Notification::make()->title('Stok secimi gecersiz')->danger()->send();

                return;
            }

            $kaynakSatirlar[] = [
                'stok_id' => (int) $stok->id,
                'stok_adi' => (string) $stok->ad,
                'kod' => (string) ($stok->kod ?? ''),
                'fiyat' => number_format((float) ($stok->satis_fiyati ?? 0), 2, ',', '.'),
                'para_birimi' => strtoupper((string) ($stok->para_birimi ?? 'TRY')),
                'barkod' => trim((string) ($stok->barkod ?: $stok->kod ?: $stok->id)),
                'adet' => $adet,
            ];
        }

        $etiketler = [];
        $uyarilar = [];
        foreach ($kaynakSatirlar as $satir) {
            $satirAdet = max(1, min(500, (int) ($satir['adet'] ?? 1)));
            $barkod = trim((string) ($satir['barkod'] ?? $satir['kod'] ?? $satir['stok_id'] ?? ''));
            $barkodTipi = (string) ($satir['barkod_tipi'] ?? $this->seciliBarkodTipi());
            $svg = $this->barkodSvgOlustur($barkod, $barkodTipi);
            if (! $svg) {
                $uyarilar[] = trim((string) ($satir['stok_adi'] ?? 'Stok')).' icin '.strtoupper($barkodTipi).' barkod uretilemedi: '.$barkod;
            }

            for ($i = 0; $i < $satirAdet; $i++) {
                $etiketler[] = [
                    'stok_adi' => (string) ($satir['stok_adi'] ?? ''),
                    'kod' => (string) ($satir['kod'] ?? ''),
                    'fiyat' => (string) ($satir['fiyat'] ?? '0,00'),
                    'para_birimi' => strtoupper((string) ($satir['para_birimi'] ?? 'TRY')),
                    'barkod' => $barkod,
                    'svg' => $svg,
                    'tasarim_tipi' => (string) ($this->seciliSablon['tasarim_tipi'] ?? 'standart'),
                ];
            }
        }

        $this->etiketler = $etiketler;
        $this->etiketUyarilari = array_values(array_unique($uyarilar));

        Notification::make()
            ->title('Etiket onizlemesi hazir')
            ->body(count($etiketler).' etiket hazirlandi.')
            ->color($this->etiketUyarilari === [] ? 'success' : 'warning')
            ->send();

        if ($this->otoYazdirTalebi) {
            $this->dispatch('etiket-oto-yazdir');
            $this->otoYazdirTalebi = false;
        }
    }

    public function sablonKaydet(): void
    {
        if (! $this->etiketYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            Notification::make()->title('Aktif firma bulunamadi')->danger()->send();

            return;
        }

        $state = $this->form->getState();
        $validated = validator($state, [
            'sablon_ad' => ['required', 'string', 'max:191'],
            'sablon_kod' => ['nullable', 'string', 'max:64'],
            'sablon_genislik_mm' => ['required', 'integer', 'min:20', 'max:200'],
            'sablon_yukseklik_mm' => ['required', 'integer', 'min:10', 'max:200'],
            'sablon_barkod_tipi' => ['required', 'in:ean13,code128'],
            'sablon_tasarim_tipi' => ['required', 'in:standart,fiyat_odakli,mini,raf,kargo,depo'],
            'sablon_aktif' => ['nullable', 'boolean'],
            'sablon_varsayilan_mi' => ['nullable', 'boolean'],
        ])->validate();

        $seciliId = (int) ($state['etiket_sablonu_id'] ?? 0);
        $sablon = $seciliId > 0
            ? EtiketSablonu::query()->where('firma_id', $firmaId)->find($seciliId)
            : null;

        $kod = $this->sablonKoduUret(
            firmaId: $firmaId,
            ad: (string) $validated['sablon_ad'],
            kod: (string) ($validated['sablon_kod'] ?? ''),
            haricId: $sablon?->id
        );

        if ($sablon) {
            $sablon->forceFill([
                'ad' => (string) $validated['sablon_ad'],
                'kod' => $kod,
                'genislik_mm' => (int) $validated['sablon_genislik_mm'],
                'yukseklik_mm' => (int) $validated['sablon_yukseklik_mm'],
                'barkod_tipi' => (string) $validated['sablon_barkod_tipi'],
                'tasarim_tipi' => (string) $validated['sablon_tasarim_tipi'],
                'aktif' => (bool) ($validated['sablon_aktif'] ?? true),
            ])->save();
        } else {
            $sablon = EtiketSablonu::query()->create([
                'firma_id' => $firmaId,
                'ad' => (string) $validated['sablon_ad'],
                'kod' => $kod,
                'genislik_mm' => (int) $validated['sablon_genislik_mm'],
                'yukseklik_mm' => (int) $validated['sablon_yukseklik_mm'],
                'barkod_tipi' => (string) $validated['sablon_barkod_tipi'],
                'tasarim_tipi' => (string) $validated['sablon_tasarim_tipi'],
                'aktif' => (bool) ($validated['sablon_aktif'] ?? true),
                'varsayilan_mi' => false,
            ]);
        }

        if ((bool) ($validated['sablon_varsayilan_mi'] ?? false)) {
            $this->sablonuVarsayilanYap((int) $sablon->id);
        }

        $this->sablonCacheTemizle($firmaId, (int) $sablon->id);
        $this->data['etiket_sablonu_id'] = (int) $sablon->id;
        $this->seciliSablon = $this->sablonBilgisiGetir((int) $sablon->id);
        $this->sablonDuzenlemeVerisiniYukle((int) $sablon->id);

        Notification::make()
            ->title('Etiket sablonu kaydedildi')
            ->success()
            ->send();
    }

    public function sablonYonetiminiDegistir(): void
    {
        $this->sablonYonetimiAcik = ! $this->sablonYonetimiAcik;

        if ($this->sablonYonetimiAcik) {
            $seciliId = (int) ($this->data['etiket_sablonu_id'] ?? 0);
            $this->sablonDuzenlemeVerisiniYukle($seciliId);
        }
    }

    public function sablonSil(): void
    {
        if (! $this->etiketYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $firmaId = $this->aktifFirmaId();
        $seciliId = (int) ($this->data['etiket_sablonu_id'] ?? 0);
        if ($firmaId < 1 || $seciliId < 1) {
            return;
        }

        $sablon = EtiketSablonu::query()->where('firma_id', $firmaId)->find($seciliId);
        if (! $sablon) {
            return;
        }

        $adet = EtiketSablonu::query()->where('firma_id', $firmaId)->count();
        if ($adet <= 1) {
            Notification::make()
                ->title('Son sablon silinemez')
                ->body('En az bir etiket sablonu kalmalidir.')
                ->warning()
                ->send();

            return;
        }

        $silinenVarsayilan = (bool) $sablon->varsayilan_mi;
        $sablon->delete();
        $this->sablonCacheTemizle($firmaId, (int) $sablon->id);

        if ($silinenVarsayilan) {
            $yeniVarsayilan = EtiketSablonu::query()
                ->where('firma_id', $firmaId)
                ->orderBy('id')
                ->first();
            if ($yeniVarsayilan) {
                $this->sablonuVarsayilanYap((int) $yeniVarsayilan->id);
            }
        }

        $yeniSeciliId = $this->varsayilanSablonId();
        $this->data['etiket_sablonu_id'] = $yeniSeciliId > 0 ? $yeniSeciliId : null;
        $this->sablonDuzenlemeVerisiniYukle((int) $yeniSeciliId);

        Notification::make()
            ->title('Etiket sablonu silindi')
            ->success()
            ->send();
    }

    public function sablonYeni(): void
    {
        $this->data['etiket_sablonu_id'] = null;
        $this->data['sablon_ad'] = '';
        $this->data['sablon_kod'] = '';
        $this->data['sablon_genislik_mm'] = 50;
        $this->data['sablon_yukseklik_mm'] = 30;
        $this->data['sablon_barkod_tipi'] = 'ean13';
        $this->data['sablon_tasarim_tipi'] = 'standart';
        $this->data['sablon_aktif'] = true;
        $this->data['sablon_varsayilan_mi'] = false;
    }

    public function hazirSablonlariYukle(): void
    {
        if (! $this->etiketYetkisiVarMi()) {
            Notification::make()->title('Bu islem icin yetkiniz yok')->danger()->send();

            return;
        }

        $eklenen = $this->enCokKullanilanSablonlariTamamla();
        $varsayilanId = $this->varsayilanSablonId();
        $this->data['etiket_sablonu_id'] = $varsayilanId > 0 ? $varsayilanId : null;
        $this->sablonDuzenlemeVerisiniYukle((int) $varsayilanId);

        Notification::make()
            ->title('Hazir 6 sablon yuklendi')
            ->body('Eklenen sablon: '.$eklenen)
            ->success()
            ->send();
    }

    public function seciliSablonuYenile(): void
    {
        $seciliId = (int) ($this->data['etiket_sablonu_id'] ?? 0);
        $this->sablonDuzenlemeVerisiniYukle($seciliId);
    }

    private function aktifFirmaId(): int
    {
        return $this->aktifFirmaIdCache ??= (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }

    private function stokBul(int $stokId, int $firmaId): ?StokKarti
    {
        if ($stokId < 1 || $firmaId < 1) {
            return null;
        }

        return StokKarti::query()
            ->where('firma_id', $firmaId)
            ->find($stokId);
    }

    /**
     * @return array<int, string>
     */
    private function stokSecenekleri(?string $arama = null): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        $arama = trim((string) $arama);

        return StokKarti::query()
            ->select(['id', 'kod', 'ad'])
            ->where('firma_id', $firmaId)
            ->when($arama !== '', function ($query) use ($arama): void {
                $query->where(function ($query) use ($arama): void {
                    $query
                        ->where('ad', 'like', '%'.$arama.'%')
                        ->orWhere('kod', 'like', '%'.$arama.'%')
                        ->orWhere('barkod', 'like', '%'.$arama.'%');
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (StokKarti $stok): array => [
                $stok->id => trim(($stok->kod ? $stok->kod.' - ' : '').$stok->ad),
            ])
            ->all();
    }

    private function stokSecenegiEtiketi(int $stokId): ?string
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1 || $stokId < 1) {
            return null;
        }

        $stok = StokKarti::query()
            ->select(['id', 'kod', 'ad'])
            ->where('firma_id', $firmaId)
            ->find($stokId);

        if (! $stok) {
            return null;
        }

        return trim(($stok->kod ? $stok->kod.' - ' : '').$stok->ad);
    }

    /**
     * @return array<int, string>
     */
    private function sablonSecenekleri(): array
    {
        if ($this->sablonSecenekleriCache !== null) {
            return $this->sablonSecenekleriCache;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return [];
        }

        $this->varsayilanSablonId();

        return $this->sablonSecenekleriCache = Cache::remember(
            $this->sablonSecenekleriCacheKey($firmaId),
            now()->addSeconds(60),
            fn (): array => EtiketSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('aktif', true)
                ->orderByDesc('varsayilan_mi')
                ->orderBy('ad')
                ->get(['id', 'ad', 'genislik_mm', 'yukseklik_mm', 'tasarim_tipi'])
                ->mapWithKeys(fn (EtiketSablonu $sablon): array => [
                    $sablon->id => $sablon->ad.' ('.$sablon->genislik_mm.'x'.$sablon->yukseklik_mm.' mm | '.strtoupper((string) ($sablon->tasarim_tipi ?: 'standart')).')',
                ])
                ->all()
        );
    }

    private function varsayilanSablonId(): int
    {
        if ($this->varsayilanSablonIdCache !== null) {
            return $this->varsayilanSablonIdCache;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return 0;
        }

        $varsayilanId = (int) Cache::remember(
            $this->varsayilanSablonIdCacheKey($firmaId),
            now()->addSeconds(60),
            fn (): int => (int) EtiketSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('varsayilan_mi', true)
                ->value('id')
        );

        if ($varsayilanId > 0) {
            return $this->varsayilanSablonIdCache = $varsayilanId;
        }

        $ilkId = (int) EtiketSablonu::query()
            ->where('firma_id', $firmaId)
            ->value('id');

        if ($ilkId > 0) {
            EtiketSablonu::query()->whereKey($ilkId)->update(['varsayilan_mi' => true]);
            Cache::put($this->varsayilanSablonIdCacheKey($firmaId), $ilkId, now()->addSeconds(60));

            return $this->varsayilanSablonIdCache = $ilkId;
        }

        $olusan = EtiketSablonu::query()->create([
            'firma_id' => $firmaId,
            'ad' => 'Standart 50x30',
            'kod' => 'standart_50x30',
            'genislik_mm' => 50,
            'yukseklik_mm' => 30,
            'barkod_tipi' => 'ean13',
            'tasarim_tipi' => 'standart',
            'varsayilan_mi' => true,
            'aktif' => true,
        ]);

        Cache::put($this->varsayilanSablonIdCacheKey($firmaId), (int) $olusan->id, now()->addSeconds(60));
        Cache::forget($this->sablonSecenekleriCacheKey($firmaId));

        return $this->varsayilanSablonIdCache = (int) $olusan->id;
    }

    private function sablonDuzenlemeVerisiniYukle(int $sablonId): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1 || $sablonId < 1) {
            $this->seciliSablon = $this->varsayilanSablonBilgisi();

            return;
        }

        $sablon = $this->sablonBilgisiCache[$sablonId] ?? null;

        if ($sablon === null) {
            $kayit = EtiketSablonu::query()
                ->where('firma_id', $firmaId)
                ->find($sablonId);

            $sablon = $kayit ? [
                'genislik_mm' => (int) $kayit->genislik_mm,
                'yukseklik_mm' => (int) $kayit->yukseklik_mm,
                'ad' => (string) $kayit->ad,
                'kod' => (string) $kayit->kod,
                'tasarim_tipi' => (string) ($kayit->tasarim_tipi ?: 'standart'),
                'barkod_tipi' => (string) ($kayit->barkod_tipi ?: 'ean13'),
                'aktif' => (bool) $kayit->aktif,
                'varsayilan_mi' => (bool) $kayit->varsayilan_mi,
            ] : false;

            $this->sablonBilgisiCache[$sablonId] = $sablon;
        }

        if ($sablon === false) {
            $this->seciliSablon = $this->varsayilanSablonBilgisi();

            return;
        }

        $this->seciliSablon = [
            'genislik_mm' => (int) $sablon['genislik_mm'],
            'yukseklik_mm' => (int) $sablon['yukseklik_mm'],
            'ad' => (string) $sablon['ad'],
            'tasarim_tipi' => (string) $sablon['tasarim_tipi'],
            'barkod_tipi' => (string) $sablon['barkod_tipi'],
        ];
        $this->data['sablon_ad'] = (string) $sablon['ad'];
        $this->data['sablon_kod'] = (string) $sablon['kod'];
        $this->data['sablon_genislik_mm'] = (int) $sablon['genislik_mm'];
        $this->data['sablon_yukseklik_mm'] = (int) $sablon['yukseklik_mm'];
        $this->data['sablon_barkod_tipi'] = (string) $sablon['barkod_tipi'];
        $this->data['sablon_tasarim_tipi'] = (string) $sablon['tasarim_tipi'];
        $this->data['sablon_aktif'] = (bool) $sablon['aktif'];
        $this->data['sablon_varsayilan_mi'] = (bool) $sablon['varsayilan_mi'];
    }

    private function sablonuVarsayilanYap(int $sablonId): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1 || $sablonId < 1) {
            return;
        }

        $oncekiVarsayilanId = (int) EtiketSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('varsayilan_mi', true)
            ->value('id');

        EtiketSablonu::query()
            ->where('firma_id', $firmaId)
            ->update(['varsayilan_mi' => false]);

        EtiketSablonu::query()
            ->where('firma_id', $firmaId)
            ->whereKey($sablonId)
            ->update(['varsayilan_mi' => true]);

        $this->sablonCacheTemizle($firmaId, $sablonId);
        if ($oncekiVarsayilanId > 0 && $oncekiVarsayilanId !== $sablonId) {
            Cache::forget($this->sablonBilgisiCacheKey($firmaId, $oncekiVarsayilanId));
        }
    }

    private function sablonCacheTemizle(?int $firmaId = null, ?int $sablonId = null): void
    {
        $firmaId ??= $this->aktifFirmaId();
        $this->varsayilanSablonIdCache = null;
        $this->sablonSecenekleriCache = null;
        $this->sablonBilgisiCache = [];

        if ($firmaId < 1) {
            return;
        }

        Cache::forget($this->varsayilanSablonIdCacheKey($firmaId));
        Cache::forget($this->sablonSecenekleriCacheKey($firmaId));

        if ($sablonId !== null && $sablonId > 0) {
            Cache::forget($this->sablonBilgisiCacheKey($firmaId, $sablonId));
        }
    }

    private function varsayilanSablonIdCacheKey(int $firmaId): string
    {
        return 'barkod-etiket:varsayilan-sablon-id:v2:firma:'.$firmaId;
    }

    private function sablonSecenekleriCacheKey(int $firmaId): string
    {
        return 'barkod-etiket:sablon-secenekleri:v2:firma:'.$firmaId;
    }

    private function sablonBilgisiCacheKey(int $firmaId, int $sablonId): string
    {
        return 'barkod-etiket:sablon-bilgisi:v2:firma:'.$firmaId.':sablon:'.$sablonId;
    }

    private function sablonKoduUret(int $firmaId, string $ad, string $kod = '', ?int $haricId = null): string
    {
        $ham = trim($kod);
        if ($ham === '') {
            $ham = Str::slug($ad, '_');
        }
        if ($ham === '') {
            $ham = 'etiket_sablonu';
        }

        $temel = Str::lower($ham);
        $uret = $temel;
        $sayac = 2;
        while (EtiketSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('kod', $uret)
            ->when($haricId, fn ($q) => $q->where('id', '!=', $haricId))
            ->exists()) {
            $uret = $temel.'_'.$sayac;
            $sayac++;
        }

        return $uret;
    }

    /**
     * @return array<string, string>
     */
    private function tasarimTipiSecenekleri(): array
    {
        return [
            'standart' => 'Standart',
            'fiyat_odakli' => 'Fiyat Odakli',
            'mini' => 'Mini',
            'raf' => 'Raf',
            'kargo' => 'Kargo',
            'depo' => 'Depo',
        ];
    }

    private function enCokKullanilanSablonlariTamamla(): int
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return 0;
        }

        return Cache::remember('barkod-etiket:sablon-tamamla:firma:'.$firmaId, now()->addSeconds(60), function () use ($firmaId): int {
            if (EtiketSablonu::query()->where('firma_id', $firmaId)->count() >= 6) {
                return 0;
            }

            $hazirSablonlar = [
            ['ad' => 'Mini Fiyat 40x20', 'kod' => 'mini_fiyat_40x20', 'genislik_mm' => 40, 'yukseklik_mm' => 20, 'barkod_tipi' => 'ean13', 'tasarim_tipi' => 'mini'],
            ['ad' => 'Standart Raf 50x30', 'kod' => 'standart_raf_50x30', 'genislik_mm' => 50, 'yukseklik_mm' => 30, 'barkod_tipi' => 'ean13', 'tasarim_tipi' => 'standart'],
            ['ad' => 'Fiyat Odakli 58x40', 'kod' => 'fiyat_odakli_58x40', 'genislik_mm' => 58, 'yukseklik_mm' => 40, 'barkod_tipi' => 'ean13', 'tasarim_tipi' => 'fiyat_odakli'],
            ['ad' => 'Raf Etiketi 76x50', 'kod' => 'raf_etiketi_76x50', 'genislik_mm' => 76, 'yukseklik_mm' => 50, 'barkod_tipi' => 'ean13', 'tasarim_tipi' => 'raf'],
            ['ad' => 'Kargo Etiketi 100x50', 'kod' => 'kargo_etiketi_100x50', 'genislik_mm' => 100, 'yukseklik_mm' => 50, 'barkod_tipi' => 'code128', 'tasarim_tipi' => 'kargo'],
            ['ad' => 'Depo Etiketi 100x75', 'kod' => 'depo_etiketi_100x75', 'genislik_mm' => 100, 'yukseklik_mm' => 75, 'barkod_tipi' => 'code128', 'tasarim_tipi' => 'depo'],
        ];

            $eklenen = 0;
            foreach ($hazirSablonlar as $hazir) {
                $varMi = EtiketSablonu::query()
                ->where('firma_id', $firmaId)
                ->where('kod', (string) $hazir['kod'])
                ->exists();

                if ($varMi) {
                    continue;
                }

                EtiketSablonu::query()->create([
                'firma_id' => $firmaId,
                'ad' => (string) $hazir['ad'],
                'kod' => (string) $hazir['kod'],
                'genislik_mm' => (int) $hazir['genislik_mm'],
                'yukseklik_mm' => (int) $hazir['yukseklik_mm'],
                'barkod_tipi' => (string) $hazir['barkod_tipi'],
                'tasarim_tipi' => (string) $hazir['tasarim_tipi'],
                'varsayilan_mi' => false,
                'aktif' => true,
            ]);
                $eklenen++;
            }

            return $eklenen;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sablonBilgisiGetir(int $sablonId): array
    {
        if ($sablonId < 1) {
            return $this->varsayilanSablonBilgisi();
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId < 1) {
            return $this->varsayilanSablonBilgisi();
        }

        $sablon = Cache::remember(
            $this->sablonBilgisiCacheKey($firmaId, $sablonId),
            now()->addSeconds(60),
            fn (): array|false => EtiketSablonu::query()
                ->where('firma_id', $firmaId)
                ->whereKey($sablonId)
                ->first(['id', 'genislik_mm', 'yukseklik_mm', 'ad', 'tasarim_tipi', 'barkod_tipi'])
                ?->only(['genislik_mm', 'yukseklik_mm', 'ad', 'tasarim_tipi', 'barkod_tipi'])
                ?? false
        );

        if ($sablon === false) {
            return $this->varsayilanSablonBilgisi();
        }

        return [
            'genislik_mm' => (int) $sablon['genislik_mm'],
            'yukseklik_mm' => (int) $sablon['yukseklik_mm'],
            'ad' => (string) $sablon['ad'],
            'tasarim_tipi' => (string) ($sablon['tasarim_tipi'] ?: 'standart'),
            'barkod_tipi' => (string) ($sablon['barkod_tipi'] ?: 'ean13'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function varsayilanSablonBilgisi(): array
    {
        return [
            'genislik_mm' => 50,
            'yukseklik_mm' => 30,
            'ad' => 'Standart 50x30',
            'tasarim_tipi' => 'standart',
            'barkod_tipi' => 'ean13',
        ];
    }

    private function seciliBarkodTipi(): string
    {
        $tip = (string) ($this->seciliSablon['barkod_tipi'] ?? $this->data['sablon_barkod_tipi'] ?? 'ean13');

        return in_array($tip, ['ean13', 'code128'], true) ? $tip : 'ean13';
    }

    private function barkodSvgOlustur(string $barkod, ?string $tip = null): ?string
    {
        return ($tip ?: $this->seciliBarkodTipi()) === 'code128'
            ? Code128SvgUretici::svgOlustur($barkod, 220, 68)
            : Ean13SvgUretici::svgOlustur($barkod, 220, 68);
    }

    private function etiketYetkisiVarMi(): bool
    {
        return BarkodluSatisFilamentErisimYardimcisi::herhangiBirBarkodluSatisYetkisiVarMi([
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_ETIKET_YAZDIR,
            MuhasebeYetkiSablonlari::BARKODLU_SATIS_GUNCELLE,
        ]) || MuhasebeFilamentErisimYardimcisi::herhangiBirMuhasebeYetkisiVarMi([
            MuhasebeYetkiSablonlari::STOK_PARTI_GORUNTULE,
            MuhasebeYetkiSablonlari::STOK_PARTI_DUZELT,
            MuhasebeYetkiSablonlari::STOK_GORUNTULE,
        ]);
    }

    public function onizlemeOlcekYuzdesi(): int
    {
        $ham = (string) ($this->data['onizleme_olcek'] ?? '100');
        if ($ham === 'real') {
            return 100;
        }
        $deger = (int) $ham;

        return in_array($deger, [50, 75, 100], true) ? $deger : 100;
    }

    public function gercekBoyutModuAktifMi(): bool
    {
        return (string) ($this->data['onizleme_olcek'] ?? '') === 'real';
    }

    public function onizlemeEtiketStili(): string
    {
        $genislik = (int) ($this->seciliSablon['genislik_mm'] ?? 50);
        $yukseklik = (int) ($this->seciliSablon['yukseklik_mm'] ?? 30);
        $oran = $this->onizlemeOlcekYuzdesi() / 100;

        return 'width: '.round($genislik * $oran, 2).'mm; height: '.round($yukseklik * $oran, 2).'mm;';
    }

    public function onizlemeIzgaraStili(): string
    {
        $oran = $this->onizlemeOlcekYuzdesi() / 100;
        $izgara = round(5 * $oran, 2);

        return 'background-size: '.$izgara.'mm '.$izgara.'mm;';
    }

    public function etiketToplamAdedi(): int
    {
        return array_sum(array_map(
            fn (array $satir): int => max(1, min(500, (int) ($satir['adet'] ?? 1))),
            $this->etiketSepeti
        ));
    }

    public function baskiAlaniStili(): string
    {
        $ust = max(0, min(100, (float) ($this->data['sayfa_ust_bosluk_mm'] ?? 0)));
        $sol = max(0, min(100, (float) ($this->data['sayfa_sol_bosluk_mm'] ?? 0)));
        $yatay = max(0, min(50, (float) ($this->data['etiket_yatay_bosluk_mm'] ?? 2)));
        $dikey = max(0, min(50, (float) ($this->data['etiket_dikey_bosluk_mm'] ?? 2)));
        $sutun = max(1, min(8, (int) ($this->data['sayfa_sutun_sayisi'] ?? 3)));
        $mod = (string) ($this->data['baski_modu'] ?? 'rulo');

        $stil = 'padding-top: '.$ust.'mm; padding-left: '.$sol.'mm; gap: '.$dikey.'mm '.$yatay.'mm;';
        if ($mod === 'a4') {
            $stil .= ' display: grid; grid-template-columns: repeat('.$sutun.', max-content); align-content: start;';
        }

        return $stil;
    }

    public function etiketAlaniSinifi(): string
    {
        return (string) ($this->data['baski_modu'] ?? 'rulo') === 'a4'
            ? 'rounded-lg border border-dashed border-gray-300 p-3'
            : 'flex flex-wrap rounded-lg border border-dashed border-gray-300 p-3';
    }

    public function alanGosterilsinMi(string $alan): bool
    {
        $anahtar = match ($alan) {
            'ad' => 'stok_adi_goster',
            'kod' => 'stok_kodu_goster',
            'fiyat' => 'fiyat_goster',
            'barkod_yazisi' => 'barkod_yazisi_goster',
            default => '',
        };

        return $anahtar !== '' && (bool) ($this->data[$anahtar] ?? true);
    }
}
