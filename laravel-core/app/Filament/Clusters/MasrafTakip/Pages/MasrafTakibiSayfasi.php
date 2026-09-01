<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafKategorileriSayfasi;
use App\Filament\Clusters\MasrafTakip\Pages\MasrafRaporlariSayfasi;
use App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\OdemeOlusturSayfasi;
use App\Models\Masraf\Arac;
use App\Models\Masraf\DuzenliFaturaTanimi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Proje\IsletmeProjesi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\TeknikServis\Filament\ServisGiderFaturasiDestegi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaSinifi;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Servisler\MasrafFaturaKayitServisi;
use App\Muhasebe\Servisler\MasrafKaynakDogrulamaServisi;
use App\Services\TenantContextService;
use App\Support\MasrafTakipYetkiSablonlari;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasrafTakibiSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use MasrafTakipSayfaErisimleri;

    protected static ?string $cluster = MasrafTakipCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Masraf Takibi';

    protected static ?string $slug = 'masraflar';

    protected static string $view = 'filament.clusters.masraf-takip.pages.masraf-takibi';

    /** @var array<string, mixed> */
    public array $masraf = [];

    /** @var array<string, mixed> */
    public array $masrafPopup = [];

    /** @var array{baslangic:string, bitis:string, kategori:string, isletme_proje_id:int|string, durum:string} */
    public array $filtreler = [];

    public string $idempotencyKey = '';

    /** @var array<string, array<int|string, string>> */
    private array $kategoriAltSecenekleriCache = [];

    /** @var array<int, array{id:int, ad:string, ust_kategori_id:int|null}>|null */
    private ?array $kategoriAgaciCache = null;

    private ?int $kategoriSeviyeSayisiCache = null;

    /** @var array<string, array<int, int>> */
    public array $kategoriSecimState = [];

    public function mount(): void
    {
        $this->idempotencyKeyYenile();
        $this->filtreleriVarsayilanla();

        if ($firmaId = $this->aktifFirmaId()) {
            MasrafKategorisi::varsayilanlariHazirla($firmaId);
        }

        $this->masrafFormunuDoldur();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Masraf takibi';
    }

    public function getSubheading(): ?string
    {
        return 'Personel, elektrik, araç ve diğer işletme masraflarını birkaç saniyede kaydedin.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('masrafEkle')
                ->label('Masraf ekle')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->masrafOlusturabilirMi())
                ->form(fn (Form $form): Form => $form
                    ->schema($this->masrafPopupFormSchema())
                    ->columns([
                        'default' => 1,
                        'sm' => 1,
                        'md' => 2,
                        'lg' => 2,
                        'xl' => 2,
                        '2xl' => 2,
                    ]))
                ->modalWidth('7xl')
                ->extraModalWindowAttributes(['class' => 'masraf-ekle-modal teknik-servis-masraf-ekle-modal'], merge: true)
                ->action(fn (array $data): mixed => $this->masrafKaydet($data)),
            Action::make('masrafTurleri')
                ->label('Masraf tanımları')
                ->icon('heroicon-o-adjustments-horizontal')
                ->url(MasrafKategorileriSayfasi::getUrl())
                ->visible(fn (): bool => MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR)
                    || MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE)),
            Action::make('masrafRaporlari')
                ->label('Masraf raporları')
                ->icon('heroicon-o-chart-bar')
                ->url(MasrafRaporlariSayfasi::getUrl()),
        ];
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('tarih')
                    ->label('Tarih')
                    ->required()
                    ->native(false)
                    ->default(now()->toDateString()),
                Forms\Components\Select::make('isletme_proje_id')
                    ->label('İşletme projesi')
                    ->searchable()
                    ->options(fn (): array => $this->projeSecenekleri())
                    ->getSearchResultsUsing(fn (string $search): array => $this->projeSecenekleri($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->projeEtiketi($value))
                    ->helperText('Masrafı Proje Yönetimi modülünde tanımlı bir projeye bağlar.')
                    ->native(false),
                ...$this->kategoriSecimBilesenleri(),
                Forms\Components\TextInput::make('tutar')
                    ->label('Tutar')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step('0.01')
                    ->inputMode('decimal'),
                Forms\Components\Select::make('para_birimi')
                    ->label('Para birimi')
                    ->options(['TRY' => '₺ Türk Lirası', 'USD' => '$ Amerikan Doları', 'EUR' => '€ Euro', 'GBP' => '£ İngiliz Sterlini'])
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('fatura_modu')
                    ->label('Masraf türü')
                    ->options([
                        'yok' => 'Faturasız masraf',
                        'mevcut' => 'Mevcut gider faturasına bağla',
                        'yeni' => 'Yeni gider faturası oluştur',
                    ])
                    ->default('yok')
                    ->live()
                    ->required()
                    ->native(false)
                    ->columnSpan(2),
                Forms\Components\Select::make('fatura_id')
                    ->label('Gider faturası')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->giderFaturaSecenekleri($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->giderFaturaEtiketi($value))
                    ->required(fn (Get $get): bool => $get('fatura_modu') === 'mevcut')
                    ->visible(fn (Get $get): bool => $get('fatura_modu') === 'mevcut')
                    ->native(false)
                    ->columnSpan(2),
                Forms\Components\Select::make('fatura_cari_id')
                    ->label('Fatura carisi')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->cariSecenekleri($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                    ->required(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                    ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                    ->native(false)
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('fatura_vade_tarihi')
                    ->label('Fatura vade tarihi')
                    ->native(false)
                    ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('fatura_aciklama')
                    ->label('Fatura açıklaması')
                    ->maxLength(191)
                    ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                    ->columnSpan(2),
                Forms\Components\Select::make('kaynak_turu')
                    ->label('Masraf kaynağı (isteğe bağlı)')
                    ->options(MasrafKaynakDogrulamaServisi::turSecenekleri())
                    ->helperText('Masrafı personel, araç, düzenli fatura veya teknik servis kaydıyla ilişkilendirmek için kullanılır. Normal masraflarda boş bırakabilirsiniz.')
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set): mixed => $set('kaynak_id', null))
                    ->native(false)
                    ->columnSpan(2),
                Forms\Components\Select::make('kaynak_id')
                    ->label('Takip kaydı')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->kaynakSecenekleri((string) ($get('kaynak_turu') ?? ''), $search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->kaynakEtiketi((string) ($this->masraf['kaynak_turu'] ?? ''), $value))
                    ->required(fn (Get $get): bool => filled($get('kaynak_turu')))
                    ->visible(fn (Get $get): bool => filled($get('kaynak_turu')))
                    ->native(false)
                    ->columnSpan(2),
                Forms\Components\TextInput::make('yakit_litre')
                    ->label('Yakıt litre')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.001')
                    ->visible(fn (Get $get): bool => $get('kaynak_turu') === MasrafKaynakDogrulamaServisi::ARAC)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('litre_fiyati')
                    ->label('Litre fiyatı')
                    ->numeric()
                    ->minValue(0)
                    ->step('0.0001')
                    ->visible(fn (Get $get): bool => $get('kaynak_turu') === MasrafKaynakDogrulamaServisi::ARAC)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('kaynak_kilometre')
                    ->label('Kilometre')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => $get('kaynak_turu') === MasrafKaynakDogrulamaServisi::ARAC)
                    ->columnSpan(1),
                Forms\Components\TextInput::make('aciklama')
                    ->label('Kısa açıklama')
                    ->placeholder('Örn. Ocak elektrik faturası')
                    ->required()
                    ->maxLength(191)
                    ->columnSpan(2),
                Forms\Components\Textarea::make('notlar')
                    ->label('Not (isteğe bağlı)')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpan(2),
                Forms\Components\FileUpload::make('belge_yolu')
                    ->label('Belge / fiş / fatura')
                    ->helperText('PDF, JPG veya PNG; en fazla 10 MB. Mobilde kamera ile fotoğraf çekebilirsiniz.')
                    ->disk('public')
                    ->directory(fn (): string => 'masraflar/'.($this->aktifFirmaId() ?? 0))
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->extraInputAttributes(['capture' => 'environment'])
                ->maxSize(10240)
                ->validationMessages([
                    'mimetypes' => 'Belge yalnızca PDF, JPG veya PNG olabilir.',
                    'max' => 'Belge boyutu en fazla 10 MB olabilir.',
                ])
                ->storeFileNamesIn('belge_adi')
                    ->columnSpan(2),
            ])
            ->columns([
                'default' => 1,
                'sm' => 2,
                'xl' => 4,
            ])
            ->statePath('masraf');
    }

    public function filtreForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('baslangic')
                    ->label('Başlangıç')
                    ->required()
                    ->native(false),
                Forms\Components\DatePicker::make('bitis')
                    ->label('Bitiş')
                    ->required()
                    ->native(false),
                Forms\Components\Select::make('kategori')
                    ->label('Masraf türü')
                    ->options(fn (): array => ['' => 'Tüm türler'] + $this->kategoriSecenekleri())
                    ->native(false),
                Forms\Components\Select::make('isletme_proje_id')
                    ->label('Proje')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->projeSecenekleri($search, false))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->projeEtiketi($value))
                    ->native(false),
                Forms\Components\Select::make('durum')
                    ->label('Kayıt durumu')
                    ->options(['aktif' => 'Aktif kayıtlar', 'iptal' => 'İptal kayıtlar', 'tumu' => 'Tümü'])
                    ->native(false)
                    ->default('aktif'),
            ])
            ->columns([
                'default' => 1,
                'sm' => 2,
                'xl' => 4,
            ])
            ->statePath('filtreler');
    }

    /** @return array<int, Forms\Components\Component> */
    private function masrafPopupFormSchema(): array
    {
        return [
            Forms\Components\Select::make('fatura_modu')
                ->label('Masraf türü')
                ->options([
                    'yok' => 'Faturasız masraf',
                    'mevcut' => 'Mevcut gider faturasına bağla',
                    'yeni' => 'Yeni gider faturası oluştur',
                ])
                ->default('yok')
                ->live()
                ->required()
                ->native(false)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\DatePicker::make('tarih')
                ->label('Masraf tarihi')
                ->required()
                ->native(false)
                ->default(now()->toDateString())
                ->live()
                ->afterStateUpdated(function ($state, $old, Forms\Set $set, Get $get): void {
                    $faturaTarihi = $get('fatura_tarihi');
                    if (blank($faturaTarihi) || (string) $faturaTarihi === (string) $old) {
                        $set('fatura_tarihi', $state);
                    }
                }),
        Forms\Components\Select::make('isletme_proje_id')
                ->label('İşletme projesi')
                ->searchable()
                ->options(fn (): array => $this->projeSecenekleri())
                ->getSearchResultsUsing(fn (string $search): array => $this->projeSecenekleri($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->projeEtiketi($value))
                ->helperText('Masrafı Proje Yönetimi modülünde tanımlı bir projeye bağlar.')
                ->native(false),
            ...$this->kategoriSecimBilesenleri(),
            Forms\Components\TextInput::make('tutar')
                ->label('Masraf tutarı')
                ->required(fn (Get $get): bool => $get('fatura_modu') === 'yok')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->inputMode('decimal')
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yok'),
            Forms\Components\Select::make('para_birimi')
                ->label('Fatura para birimi')
                ->options(fn (): array => FaturaKaynagi::paraBirimiSecenekleri((int) ($this->aktifFirmaId() ?? 0)))
                ->default('TRY')
                ->required(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->native(false),
            Forms\Components\Select::make('masraf_para_birimi')
                ->label('Masraf para birimi')
                ->options(['TRY' => '₺ Türk Lirası', 'USD' => '$ Amerikan Doları', 'EUR' => '€ Euro', 'GBP' => '£ İngiliz Sterlini'])
                ->default('TRY')
                ->required(fn (Get $get): bool => $get('fatura_modu') === 'yok')
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yok')
                ->native(false),
            Forms\Components\Placeholder::make('mevcut_fatura_tutar_bilgisi')
                ->label('Masraf tutarı')
                ->content(fn (Get $get): string => $this->giderFaturaTutarEtiketi($get('fatura_id')))
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'mevcut')
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\Select::make('fatura_id')
                ->label('Gider faturası')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->giderFaturaSecenekleri($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->giderFaturaEtiketi($value))
                ->disableOptionWhen(fn ($value): bool => $this->giderFaturasiPasifMi($value))
                ->required(fn (Get $get): bool => $get('fatura_modu') === 'mevcut')
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'mevcut')
                ->native(false)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\Select::make('fatura_cari_id')
                ->label('Fatura carisi')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->cariSecenekleri($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                ->required(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->native(false)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\DatePicker::make('fatura_tarihi')
                ->label('Fatura tarihi')
                ->default(fn (Get $get): string => (string) ($get('tarih') ?: now()->toDateString()))
                ->required(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->native(false)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\DatePicker::make('fatura_vade_tarihi')
                ->label('Fatura vade tarihi')
                ->native(false)
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\Section::make('Yeni gider faturası')
                ->schema([
                    Forms\Components\Hidden::make('firma_id')
                        ->default(fn (): ?int => $this->aktifFirmaId())
                        ->dehydrated(),
                    // Teknik Servis gider faturası formundaki bölüm hiyerarşisiyle aynı.
                    Forms\Components\Section::make('Kalemler')
                        ->schema([
                            // Teknik Servis Masraf Ekle ile aynı stok kalemi bileşeni.
                            ServisGiderFaturasiDestegi::stokKalemleriRepeater((int) ($this->aktifFirmaId() ?? 0)),
                        ])
                        ->columnSpanFull(),
                    Forms\Components\Section::make('Fatura tutar özeti')
                        ->schema(FaturaKaynagi::tutarOzetiFormAlanlari())
                        ->columns(3)
                        ->columnSpanFull(),
                    Forms\Components\Section::make('Fatura açıklamaları')
                        ->schema([
                            Forms\Components\Textarea::make('fatura_aciklama')
                                ->label('Fatura açıklaması')
                                ->rows(2)
                                ->maxLength(191),
                            Forms\Components\Textarea::make('fatura_notlar')
                                ->label('Fatura notu')
                                ->rows(2)
                                ->maxLength(2000),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('durum')
                        ->default(FaturaDurumu::Taslak->value)
                        ->dehydrated(),
                    Forms\Components\Hidden::make('doviz_kuru')
                        ->default(1)
                        ->dehydrated(),
                ])
                ->visible(fn (Get $get): bool => $get('fatura_modu') === 'yeni')
                ->columnSpanFull(),
            Forms\Components\Select::make('kaynak_turu')
                ->label('Masraf kaynağı (isteğe bağlı)')
                ->options(MasrafKaynakDogrulamaServisi::turSecenekleri())
                ->helperText('Masrafı personel, araç, düzenli fatura veya teknik servis kaydıyla ilişkilendirmek için kullanılır. Normal masraflarda boş bırakabilirsiniz.')
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set): mixed => $set('kaynak_id', null))
                ->native(false)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\Select::make('kaynak_id')
                ->label('Takip kaydı')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->kaynakSecenekleri((string) ($get('kaynak_turu') ?? ''), $search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->kaynakEtiketi((string) ($this->masrafPopup['kaynak_turu'] ?? ''), $value))
                ->required(fn (Get $get): bool => filled($get('kaynak_turu')))
                ->visible(fn (Get $get): bool => filled($get('kaynak_turu')))
                ->native(false)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\TextInput::make('yakit_litre')
                ->label('Yakıt litre')
                ->numeric()
                ->minValue(0)
                ->step('0.001')
                ->visible(fn (Get $get): bool => $get('kaynak_turu') === MasrafKaynakDogrulamaServisi::ARAC)
                ->columnSpan(1),
            Forms\Components\TextInput::make('litre_fiyati')
                ->label('Litre fiyatı')
                ->numeric()
                ->minValue(0)
                ->step('0.0001')
                ->visible(fn (Get $get): bool => $get('kaynak_turu') === MasrafKaynakDogrulamaServisi::ARAC)
                ->columnSpan(1),
            Forms\Components\TextInput::make('kaynak_kilometre')
                ->label('Kilometre')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->visible(fn (Get $get): bool => $get('kaynak_turu') === MasrafKaynakDogrulamaServisi::ARAC)
                ->columnSpan(1),
            Forms\Components\TextInput::make('aciklama')
                ->label('Kısa açıklama')
                ->placeholder('Örn. Ocak elektrik faturası')
                ->required()
                ->maxLength(191)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\Textarea::make('notlar')
                ->label('Not (isteğe bağlı)')
                ->rows(2)
                ->maxLength(2000)
                ->columnSpan(['default' => 1, '2xl' => 2]),
            Forms\Components\FileUpload::make('belge_yolu')
                ->label('Belge / fiş / fatura')
                ->helperText('PDF, JPG veya PNG; en fazla 10 MB. Mobilde kamera ile fotoğraf çekebilirsiniz.')
                ->disk('public')
                ->directory(fn (): string => 'masraflar/'.($this->aktifFirmaId() ?? 0))
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->extraInputAttributes(['capture' => 'environment'])
                ->maxSize(10240)
                ->validationMessages([
                    'mimetypes' => 'Belge yalnızca PDF, JPG veya PNG olabilir.',
                    'max' => 'Belge boyutu en fazla 10 MB olabilir.',
                ])
                ->storeFileNamesIn('belge_adi')
                ->columnSpan(['default' => 1, '2xl' => 2]),
        ];
    }

    public function masrafKaydet(?array $actionData = null): void
    {
        if (! $this->masrafOlusturabilirMi()) {
            $this->uyariGoster('Yetki yok', 'Masraf kaydı oluşturmak için masraf oluşturma yetkisi gerekir.');

            return;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            $this->uyariGoster('Aktif firma bulunamadı', 'Masraf kaydetmek için önce aktif firma seçin.');

            return;
        }

        $data = $actionData ?? $this->form->getState();
        if (isset($data['masrafPopup']) && is_array($data['masrafPopup'])) {
            $data = $data['masrafPopup'];
        } elseif (isset($data['masraf']) && is_array($data['masraf'])) {
            $data = $data['masraf'];
        }

        try {
            [$masrafAlanlari, $faturaAlanlari] = $this->masrafVeFaturaVerileriniHazirla($firmaId, $data);

            app(MasrafFaturaKayitServisi::class)->kaydet(
                $firmaId,
                $masrafAlanlari,
                (string) ($data['fatura_modu'] ?? 'yok'),
                $faturaAlanlari,
                auth()->id() ? (int) auth()->id() : null,
                $this->idempotencyKey,
            );
        } catch (IsKuraliIstisnasi $exception) {
            Notification::make()
                ->title('Masraf kaydedilemedi')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->idempotencyKeyYenile();
        $this->masrafFormunuDoldur();
        $this->resetTable();

        Notification::make()
            ->title('Masraf kaydedildi')
            ->body('Kayıt kategori raporlarına dahil edildi.')
            ->success()
            ->send();
    }

    /**
     * Masraf tutarını seçilen üç aşamalı akışa göre normalize eder.
     * Fatura tutarı kullanıcıdan tekrar alınmaz; ilgili faturadan veya kalem özetinden gelir.
     *
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function masrafVeFaturaVerileriniHazirla(int $firmaId, array $data): array
    {
        $faturaModu = (string) ($data['fatura_modu'] ?? 'yok');

        if ($faturaModu === 'yok') {
            $data['para_birimi'] = strtoupper((string) ($data['masraf_para_birimi'] ?? 'TRY'));

            return [$data, $data];
        }

        if ($faturaModu === 'mevcut') {
            $fatura = $this->giderFaturasi($firmaId, $data['fatura_id'] ?? null);
            if (! $fatura || $this->giderFaturasiPasifMi($fatura)) {
                throw new IsKuraliIstisnasi('Seçilen gider faturası pasif, kullanılamaz veya bulunamadı.');
            }

            $tutar = $this->faturaTavanTutari($fatura);
            if (bccomp($tutar, '0', 2) <= 0) {
                throw new IsKuraliIstisnasi('Seçilen gider faturasının bağlanabilir tutarı sıfırdan büyük olmalıdır.');
            }

            $data['tutar'] = $tutar;
            $data['para_birimi'] = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));

            return [$data, $data];
        }

        if ($faturaModu !== 'yeni') {
            throw new IsKuraliIstisnasi('Geçersiz masraf türü seçildi.');
        }

        $data['tarih'] = $data['tarih'] ?? now()->toDateString();
        $data['fatura_tarihi'] = $data['fatura_tarihi'] ?? $data['tarih'];
        $data['para_birimi'] = strtoupper((string) ($data['para_birimi'] ?? 'TRY'));

        $hesap = FaturaKaynagi::hesaplaFormKalemleriVeOzet([
            ...$data,
            'tarih' => $data['fatura_tarihi'],
            'odendi_tutari' => 0,
            'tevkifat_orani' => $data['tevkifat_orani'] ?? 0,
        ]);
        $tutar = (string) ($hesap['odenecek_tutar'] ?? $hesap['genel_toplam'] ?? 0);
        if (bccomp($tutar, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Yeni gider faturası toplamı sıfırdan büyük olmalıdır.');
        }

        $data['tutar'] = $tutar;
        $data['kalemler'] = $hesap['kalemler'] ?? $data['kalemler'] ?? [];

        return [$data, $data];
    }

    public function filtreleriUygula(): void
    {
        $this->filtreForm->getState();
        $this->resetTable();
    }

    public function filtreleriSifirla(): void
    {
        $this->filtreleriVarsayilanla();
        $this->filtreFormunuDoldur();
        $this->resetTable();
    }

    public function masrafCsvIndir(bool $excelUyumlu = false): StreamedResponse
    {
        $this->filtreler = $this->filtreForm->getState();
        $query = $this->masrafSorgusu()->orderBy('tarih')->orderBy('id');
        $filtreler = $this->filtreler;
        $delimiter = $excelUyumlu ? ';' : ',';
        $dosyaAdi = 'masraf-raporu-'.now()->format('Ymd_His').($excelUyumlu ? '-excel' : '').'.csv';

        return response()->streamDownload(function () use ($query, $filtreler, $delimiter, $excelUyumlu): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) {
                return;
            }

            if ($excelUyumlu) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            fputcsv($out, ['Rapor', 'Masraf Takibi'], $delimiter);
            fputcsv($out, ['Olusturulma', now()->format('d.m.Y H:i:s')], $delimiter);
            fputcsv($out, ['Baslangic', (string) ($filtreler['baslangic'] ?? '-')], $delimiter);
            fputcsv($out, ['Bitis', (string) ($filtreler['bitis'] ?? '-')], $delimiter);
            fputcsv($out, ['Masraf Turu', (string) ($filtreler['kategori'] ?? '') ?: 'Tumu'], $delimiter);
            fputcsv($out, ['Durum', (string) ($filtreler['durum'] ?? 'aktif')], $delimiter);
            fputcsv($out, [], $delimiter);
            fputcsv($out, ['Tarih', 'Masraf Turu', 'Aciklama', 'Tutar', 'Para Birimi', 'Durum', 'Kaydeden', 'Not'], $delimiter);

            foreach ($query->lazy(500) as $masraf) {
                fputcsv($out, [
                    optional($masraf->tarih)->format('Y-m-d'),
                    (string) ($masraf->kategori?->ad ?? ''),
                    (string) $masraf->aciklama,
                    (string) $masraf->tutar,
                    strtoupper((string) ($masraf->para_birimi ?: 'TRY')),
                    $masraf->durum === Masraf::DURUM_IPTAL ? 'Iptal' : 'Aktif',
                    (string) ($masraf->olusturanKullanici?->name ?? ''),
                    (string) ($masraf->notlar ?? ''),
                ], $delimiter);
            }

            fclose($out);
        }, $dosyaAdi, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function masrafExcelCsvIndir(): StreamedResponse
    {
        return $this->masrafCsvIndir(true);
    }

    public function masrafOlusturabilirMi(): bool
    {
        return $this->yetkiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR);
    }

    public function masrafiIptalEt(int $masrafId, ?string $neden = null): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return;
        }

        app(MasrafKayitServisi::class)->iptalEt(
            $firmaId,
            $masrafId,
            auth()->id() ? (int) auth()->id() : null,
            $neden,
        );

        $this->resetTable();
        Notification::make()
            ->title('Masraf iptal edildi')
            ->body('Kayıt silinmedi; raporlardan çıkarıldı ve iptal bilgisi korundu.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->masrafSorgusu(true))
            ->deferLoading()
            ->defaultSort('tarih', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tarih')
                    ->label('Tarih')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kategori.ad')
                    ->label('Tür')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('isletmeProjesi.ad')
                    ->label('Proje')
                    ->placeholder('—')
                    ->url(fn (Masraf $record): ?string => $record->isletme_proje_id
                        ? ProjeRaporlariSayfasi::getUrl(['proje_id' => $record->isletme_proje_id])
                        : null)
                    ->color('primary')
                    ->hiddenFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, Masraf $record): string => $this->tutarGoster($state, (string) $record->para_birimi))
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => $state === Masraf::DURUM_IPTAL ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === Masraf::DURUM_IPTAL ? 'İptal' : 'Aktif'),
                Tables\Columns\TextColumn::make('olusturanKullanici.name')
                    ->label('Kaydeden')
                    ->placeholder('—')
                    ->hiddenFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('belge_adi')
                    ->label('Belge')
                    ->placeholder('—')
                    ->url(fn (Masraf $record): ?string => $record->belge_yolu ? route('masraf.belge', ['masraf' => $record->getKey()]) : null, shouldOpenInNewTab: true)
                    ->hiddenFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('faturaOdeme')
                    ->label('Ödeme yap')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (Masraf $record): bool => MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR)
                        && $this->odemeIcinFatura($record) !== null)
                    ->url(fn (Masraf $record): string => OdemeOlusturSayfasi::getUrl([
                        'fatura_id' => (int) $this->odemeIcinFatura($record)->getKey(),
                    ])),
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Masraf $record): bool => $record->durum === Masraf::DURUM_AKTIF
                        && $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->modalHeading('Masraf kaydını düzenle')
                    ->form([
                        Forms\Components\DatePicker::make('tarih')->label('Tarih')->required()->native(false),
                        ...$this->kategoriSecimBilesenleri(),
                        Forms\Components\Select::make('isletme_proje_id')
                            ->label('İşletme projesi')->searchable()
                            ->options(fn (): array => $this->projeSecenekleri())
                            ->getSearchResultsUsing(fn (string $search): array => $this->projeSecenekleri($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->projeEtiketi($value))
                            ->native(false),
                        Forms\Components\TextInput::make('aciklama')->label('Kısa açıklama')->required()->maxLength(191),
                        Forms\Components\Textarea::make('notlar')->label('Not')->maxLength(2000),
                        Forms\Components\FileUpload::make('belge_yolu')
                            ->label('Belge / fiş / fatura')
                            ->helperText('Mobilde kamera ile fotoğraf çekebilirsiniz.')
                            ->disk('public')
                            ->directory(fn (): string => 'masraflar/'.($this->aktifFirmaId() ?? 0))
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->extraInputAttributes(['capture' => 'environment'])
            ->maxSize(10240)
            ->validationMessages([
                'mimetypes' => 'Belge yalnızca PDF, JPG veya PNG olabilir.',
                'max' => 'Belge boyutu en fazla 10 MB olabilir.',
            ])
            ->storeFileNamesIn('belge_adi'),
                    ])
                    ->fillForm(fn (Masraf $record): array => [
                        'tarih' => optional($record->tarih)->toDateString(),
                        ...$this->kategoriYolu((int) $record->masraf_kategorisi_id),
                        'masraf_kategorisi_id' => $record->masraf_kategorisi_id,
                        'isletme_proje_id' => $record->isletme_proje_id,
                        'aciklama' => $record->aciklama,
                        'notlar' => $record->notlar,
                        'belge_yolu' => $record->belge_yolu,
                        'belge_adi' => $record->belge_adi,
                    ])
                    ->action(fn (Masraf $record, array $data): mixed => $this->masrafGuncelle((int) $record->getKey(), $data)),
                Tables\Actions\Action::make('iptal')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Masraf $record): bool => $record->durum === Masraf::DURUM_AKTIF && $this->yetkiVarMi(MasrafTakipYetkiSablonlari::SIL))
                    ->requiresConfirmation()
                    ->modalHeading('Masraf kaydını iptal et')
                    ->modalDescription('Kayıt silinmeyecek; iptal edilerek rapor toplamlarından çıkarılacak.')
                    ->form([
                        Forms\Components\Textarea::make('neden')
                            ->label('İptal nedeni')
                            ->maxLength(2000),
                    ])
                    ->action(fn (Masraf $record, array $data): mixed => $this->masrafiIptalEt((int) $record->getKey(), $data['neden'] ?? null)),
            ])
            ->emptyStateHeading('Masraf Yok')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @param array<string, mixed> $data */
    public function masrafGuncelle(int $masrafId, array $data): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null || ! $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE)) {
            $this->uyariGoster('Yetki yok', 'Masraf kaydını düzenlemek için güncelleme yetkisi gerekir.');

            return;
        }

        try {
            app(MasrafKayitServisi::class)->guncelle($firmaId, $masrafId, $data);
        } catch (IsKuraliIstisnasi $exception) {
            Notification::make()->title('Masraf güncellenemedi')->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->resetTable();
        Notification::make()->title('Masraf güncellendi')->success()->send();
    }

    /** @return array<int, array{para_birimi:string, toplam:string, adet:int}> */
    public function ozet(): array
    {
        $rows = $this->aktifFiltreliSorgu()
            ->selectRaw('para_birimi, SUM(tutar) as toplam, COUNT(*) as adet')
            ->groupBy('para_birimi')
            ->orderBy('para_birimi')
            ->get();

        return $rows->map(fn ($row): array => [
            'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
            'toplam' => bcadd((string) ($row->toplam ?? '0'), '0', 2),
            'adet' => (int) $row->adet,
        ])->all();
    }

    /** @return array<int, array{kategori:string, para_birimi:string, toplam:string, adet:int}> */
    public function kategoriOzeti(): array
    {
        return $this->aktifFiltreliSorgu()
            ->join('masraf_kategorileri', 'masraf_kategorileri.id', '=', 'masraflar.masraf_kategorisi_id')
            ->selectRaw('masraf_kategorileri.ad as kategori, masraflar.para_birimi, SUM(masraflar.tutar) as toplam, COUNT(*) as adet')
            ->groupBy('masraf_kategorileri.ad', 'masraflar.para_birimi')
            ->orderByDesc('toplam')
            ->get()
            ->map(fn ($row): array => [
                'kategori' => (string) $row->kategori,
                'para_birimi' => strtoupper((string) ($row->para_birimi ?: 'TRY')),
                'toplam' => bcadd((string) ($row->toplam ?? '0'), '0', 2),
                'adet' => (int) $row->adet,
            ])->all();
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MasrafTakipYetkiSablonlari::GORUNTULE;
    }

    private function masrafSorgusu(bool $faturalarla = false): Builder
    {
        $with = [
            'kategori:id,ad,ust_kategori_id',
            'kategori.ustKategori:id,ad',
            'isletmeProjesi:id,ad,kod',
            'olusturanKullanici:id,name',
        ];

        if ($faturalarla) {
            // HasManyThrough ilişkisi eager-load edilirken closure relation nesnesi alır;
            // Builder tipi vermek canlı panelde TypeError üretir.
            $with['faturalar'] = fn ($query) => $query
                ->whereIn('faturalar.durum', [FaturaDurumu::Onayli->value])
                ->where(function (Builder $inner): void {
                    $inner->where('faturalar.acik_tutar', '>', 0)
                        ->orWhere('faturalar.odenecek_tutar', '>', 0);
                })
                ->select(['faturalar.id', 'faturalar.cari_id', 'faturalar.tur', 'faturalar.fatura_sinifi', 'faturalar.durum', 'faturalar.fatura_no', 'faturalar.odenecek_tutar', 'faturalar.acik_tutar', 'faturalar.para_birimi']);
        }

        $query = Masraf::query()
            ->with($with)
            ->select(['id', 'firma_id', 'masraf_kategorisi_id', 'isletme_proje_id', 'kaynak_turu', 'kaynak_id', 'tarih', 'tutar', 'para_birimi', 'aciklama', 'notlar', 'belge_yolu', 'belge_adi', 'durum', 'olusturan_kullanici_id'])
            ->where('firma_id', $this->aktifFirmaId() ?? 0)
            ->whereBetween('tarih', [$this->filtreler['baslangic'].' 00:00:00', $this->filtreler['bitis'].' 23:59:59']);

        if (($kategori = $this->filtreler['kategori'] ?? '') !== '') {
            $query->where('masraf_kategorisi_id', (int) $kategori);
        }

        if (($proje = $this->filtreler['isletme_proje_id'] ?? '') !== '') {
            $query->where('isletme_proje_id', (int) $proje);
        }

        if (($durum = $this->filtreler['durum'] ?? 'aktif') !== 'tumu') {
            $query->where('durum', $durum);
        }

        return $query;
    }

    private function aktifFiltreliSorgu(): Builder
    {
        $query = Masraf::query()
            ->where('masraflar.firma_id', $this->aktifFirmaId() ?? 0)
            ->where('masraflar.durum', Masraf::DURUM_AKTIF)
            ->whereBetween('masraflar.tarih', [$this->filtreler['baslangic'].' 00:00:00', $this->filtreler['bitis'].' 23:59:59']);

        if (($kategori = $this->filtreler['kategori'] ?? '') !== '') {
            $query->where('masraflar.masraf_kategorisi_id', (int) $kategori);
        }

        if (($proje = $this->filtreler['isletme_proje_id'] ?? '') !== '') {
            $query->where('masraflar.isletme_proje_id', (int) $proje);
        }

        return $query;
    }

    /** @return array<int, Forms\Components\Component> */
    private function kategoriSecimBilesenleri(string $prefix = 'kategori_seviyesi', string $finalField = 'masraf_kategorisi_id'): array
    {
        // Kategori ağacı popup açılırken bir kez yüklenir. Seçimler Alpine tarafında
        // yapılır; kategori seçerken Livewire isteği ve form yeniden çizimi oluşmaz.
        return [
            Forms\Components\ViewField::make($finalField)
                ->label('Masraf kategorisi')
                ->required()
                ->view('filament.forms.masraf-kategori-navigator', [
                    'kategoriler' => $this->kategoriAgaci(),
                    'seviyeSayisi' => $this->kategoriSeviyeSayisi(),
                ])
                ->columnSpan(2),
        ];
    }

    /** @return array<int, array{id:int, ad:string, ust_kategori_id:int|null, secilir_mi:bool}> */
    private function kategoriAgaci(): array
    {
        if ($this->kategoriAgaciCache !== null) {
            return $this->kategoriAgaciCache;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->kategoriAgaciCache = [];
        }

        return $this->kategoriAgaciCache = MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->aktif()
            ->orderBy('sira')
            ->orderBy('ad')
            ->get(['id', 'ad', 'ust_kategori_id', 'secilir_mi'])
            ->mapWithKeys(fn (MasrafKategorisi $kategori): array => [
                (int) $kategori->id => [
                    'id' => (int) $kategori->id,
                    'ad' => (string) $kategori->ad,
                    'ust_kategori_id' => $kategori->ust_kategori_id ? (int) $kategori->ust_kategori_id : null,
                    'secilir_mi' => (bool) $kategori->secilir_mi,
                ],
            ])
            ->all();
    }

    private function kategoriSeviyeSayisi(): int
    {
        if ($this->kategoriSeviyeSayisiCache !== null) {
            return $this->kategoriSeviyeSayisiCache;
        }

        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->kategoriSeviyeSayisiCache = 2;
        }

        $kategoriler = MasrafKategorisi::query()->where('firma_id', $firmaId)->get(['id', 'ust_kategori_id']);
        $harita = $kategoriler->mapWithKeys(fn (MasrafKategorisi $kategori): array => [$kategori->id => $kategori->ust_kategori_id]);
        $enDerin = 1;

        foreach ($harita as $ustId) {
            $derinlik = 1;
            $guard = 0;
            while ($ustId && $guard++ < 12) {
                $derinlik++;
                $ustId = $harita[$ustId] ?? null;
            }
            $enDerin = max($enDerin, $derinlik);
        }

        // Kök ve en az bir alt seviye form şemasında hazır bulunur.
        return $this->kategoriSeviyeSayisiCache = min(max($enDerin, 2), 8);
    }

    /** @return array<int|string, string> */
    private function kategoriAltSecenekleri(?int $ustKategoriId): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        $cacheKey = (string) ($ustKategoriId ?? 'root');
        if (array_key_exists($cacheKey, $this->kategoriAltSecenekleriCache)) {
            return $this->kategoriAltSecenekleriCache[$cacheKey];
        }

        return $this->kategoriAltSecenekleriCache[$cacheKey] = MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->aktif()
            ->where('ust_kategori_id', $ustKategoriId)
            ->orderBy('sira')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }

    /** @return array<string, int> */
    private function kategoriYolu(int $kategoriId): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null || $kategoriId < 1) {
            return [];
        }

        $yol = [];
        $kategori = MasrafKategorisi::query()->where('firma_id', $firmaId)->find($kategoriId);
        $guard = 0;

        while ($kategori && $guard++ < 12) {
            array_unshift($yol, (int) $kategori->id);
            $kategori = $kategori->ust_kategori_id
                ? MasrafKategorisi::query()->where('firma_id', $firmaId)->find($kategori->ust_kategori_id)
                : null;
        }

        return collect($yol)->mapWithKeys(fn (int $id, int $index): array => ['kategori_seviyesi_'.$index => $id])->all();
    }

    /** @return array<int|string, mixed> */
    private function kategoriSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        return Cache::remember(
            MasrafKategorisi::secenekCacheAnahtari($firmaId),
            now()->addMinutes(10),
            fn (): array => MasrafKategorisi::query()
                ->where('firma_id', $firmaId)
                ->aktif()
                ->where('secilir_mi', true)
                ->orderBy('sira')
                ->with('ustKategori:id,ad')
                ->get(['id', 'ad', 'ust_kategori_id'])
                ->groupBy(fn (MasrafKategorisi $kategori): string => $kategori->ustKategori?->ad ?? 'Diğer')
                ->map(fn ($altKategoriler): array => $altKategoriler->mapWithKeys(fn (MasrafKategorisi $kategori): array => [
                    $kategori->id => $kategori->ad,
                ])->all())
                ->all(),
        );
    }

    /** @return array<int|string, string> */
    private function projeSecenekleri(string $arama = '', bool $secilebilir = true): array
    {
        $firmaId = $this->aktifFirmaId();
        $arama = trim($arama);
        if ($firmaId === null) {
            return [];
        }

        return IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->when($secilebilir, fn (Builder $query): Builder => $query->secilebilir())
            ->when($arama !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                $inner->where('kod', 'like', '%'.$arama.'%')
                    ->orWhere('ad', 'like', '%'.$arama.'%');
            }))
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'kod', 'ad'])
            ->mapWithKeys(fn (IsletmeProjesi $proje): array => [$proje->id => $proje->ad])
            ->all();
    }

    private function projeEtiketi(mixed $value): ?string
    {
        $firmaId = $this->aktifFirmaId();
        $id = (int) $value;
        if ($firmaId === null || $id < 1) {
            return null;
        }

        $proje = IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->first(['id', 'kod', 'ad']);

        return $proje?->ad;
    }

    /** @return array<int|string, string> */
    private function kaynakSecenekleri(string $tur, string $arama = ''): array
    {
        $firmaId = $this->aktifFirmaId();
        $arama = trim($arama);
        if ($firmaId === null || $tur === '') {
            return [];
        }

        return match ($tur) {
            MasrafKaynakDogrulamaServisi::PERSONEL => Personel::query()
                ->where('firma_id', $firmaId)
                ->where('durum', Personel::DURUM_AKTIF)
                ->when($arama !== '', fn (Builder $query): Builder => $query->where('ad_soyad', 'like', '%'.$arama.'%'))
                ->orderBy('ad_soyad')->limit(50)->pluck('ad_soyad', 'id')->all(),
            MasrafKaynakDogrulamaServisi::PERSONEL_MAAS => PersonelMaasHareketi::query()
                ->where('firma_id', $firmaId)
                ->with('personel:id,ad_soyad')
                ->when($arama !== '', fn (Builder $query): Builder => $query->whereHas('personel', fn (Builder $personel): Builder => $personel->where('ad_soyad', 'like', '%'.$arama.'%')))
                ->latest('id')->limit(50)->get(['id', 'personel_id', 'net_tutar'])
                ->mapWithKeys(fn (PersonelMaasHareketi $hareket): array => [
                    $hareket->id => '#'.$hareket->id.' / '.($hareket->personel?->ad_soyad ?? 'Personel').' / '.number_format((float) $hareket->net_tutar, 2, ',', '.'),
                ])->all(),
            MasrafKaynakDogrulamaServisi::PERSONEL_AVANS => PersonelAvansi::query()
                ->where('firma_id', $firmaId)
                ->with('personel:id,ad_soyad')
                ->when($arama !== '', fn (Builder $query): Builder => $query->whereHas('personel', fn (Builder $personel): Builder => $personel->where('ad_soyad', 'like', '%'.$arama.'%')))
                ->latest('tarih')->limit(50)->get(['id', 'personel_id', 'tutar', 'tarih'])
                ->mapWithKeys(fn (PersonelAvansi $avans): array => [
                    $avans->id => '#'.$avans->id.' / '.($avans->personel?->ad_soyad ?? 'Personel').' / '.number_format((float) $avans->tutar, 2, ',', '.'),
                ])->all(),
            MasrafKaynakDogrulamaServisi::ARAC => Arac::query()
                ->where('firma_id', $firmaId)->where('aktif_mi', true)
                ->when($arama !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                    $inner->where('plaka', 'like', '%'.$arama.'%')->orWhere('marka', 'like', '%'.$arama.'%')->orWhere('model', 'like', '%'.$arama.'%');
                }))
                ->orderBy('plaka')->limit(50)->get(['id', 'plaka', 'marka', 'model'])
                ->mapWithKeys(fn (Arac $arac): array => [$arac->id => $arac->plaka.' / '.$arac->marka.' '.$arac->model])->all(),
            MasrafKaynakDogrulamaServisi::DUZENLI_FATURA => DuzenliFaturaTanimi::query()
                ->where('firma_id', $firmaId)->where('aktif_mi', true)
                ->with('kategori:id,ad')
                ->when($arama !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                    $inner->where('ad', 'like', '%'.$arama.'%')->orWhere('abone_no', 'like', '%'.$arama.'%')->orWhere('tedarikci', 'like', '%'.$arama.'%');
                }))
                ->orderBy('ad')->limit(50)->get(['id', 'masraf_kategorisi_id', 'ad', 'abone_no'])
                ->mapWithKeys(fn (DuzenliFaturaTanimi $tanim): array => [$tanim->id => ($tanim->kategori?->ad ?? 'Fatura').' / '.$tanim->ad.($tanim->abone_no ? ' / '.$tanim->abone_no : '')])->all(),
            MasrafKaynakDogrulamaServisi::TEKNIK_SERVIS => TeknikServisKaydi::query()
                ->where('firma_id', $firmaId)
                ->when($arama !== '', fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($arama): void {
                    $inner->where('fis_no', 'like', '%'.$arama.'%')->orWhere('musteri_ad_soyad', 'like', '%'.$arama.'%');
                }))
                ->latest('id')->limit(50)->get(['id', 'fis_no', 'musteri_ad_soyad'])
                ->mapWithKeys(fn (TeknikServisKaydi $servis): array => [$servis->id => '#'.$servis->id.' / '.($servis->fis_no ?: 'Fiş yok').' / '.($servis->musteri_ad_soyad ?: 'Müşteri')])->all(),
            default => [],
        };
    }

    private function kaynakEtiketi(string $tur, mixed $value): ?string
    {
        $firmaId = $this->aktifFirmaId();
        $id = (int) $value;
        if ($firmaId === null || $id < 1 || $tur === '') {
            return null;
        }

        return match ($tur) {
            MasrafKaynakDogrulamaServisi::PERSONEL => Personel::query()->where('firma_id', $firmaId)->whereKey($id)->value('ad_soyad'),
            MasrafKaynakDogrulamaServisi::PERSONEL_MAAS => ($hareket = PersonelMaasHareketi::query()->where('firma_id', $firmaId)->with('personel:id,ad_soyad')->whereKey($id)->first(['id', 'personel_id', 'net_tutar']))
                ? '#'.$hareket->id.' / '.($hareket->personel?->ad_soyad ?? 'Personel').' / '.number_format((float) $hareket->net_tutar, 2, ',', '.')
                : null,
            MasrafKaynakDogrulamaServisi::PERSONEL_AVANS => ($avans = PersonelAvansi::query()->where('firma_id', $firmaId)->with('personel:id,ad_soyad')->whereKey($id)->first(['id', 'personel_id', 'tutar']))
                ? '#'.$avans->id.' / '.($avans->personel?->ad_soyad ?? 'Personel').' / '.number_format((float) $avans->tutar, 2, ',', '.')
                : null,
            MasrafKaynakDogrulamaServisi::ARAC => ($arac = Arac::query()->where('firma_id', $firmaId)->whereKey($id)->first(['id', 'plaka', 'marka', 'model']))
                ? $arac->plaka.' / '.$arac->marka.' '.$arac->model
                : null,
            MasrafKaynakDogrulamaServisi::DUZENLI_FATURA => ($tanim = DuzenliFaturaTanimi::query()->where('firma_id', $firmaId)->with('kategori:id,ad')->whereKey($id)->first(['id', 'masraf_kategorisi_id', 'ad', 'abone_no']))
                ? ($tanim->kategori?->ad ?? 'Fatura').' / '.$tanim->ad.($tanim->abone_no ? ' / '.$tanim->abone_no : '')
                : null,
            MasrafKaynakDogrulamaServisi::TEKNIK_SERVIS => ($servis = TeknikServisKaydi::query()->where('firma_id', $firmaId)->whereKey($id)->first(['id', 'fis_no', 'musteri_ad_soyad']))
                ? '#'.$servis->id.' / '.($servis->fis_no ?: 'Fiş yok').' / '.($servis->musteri_ad_soyad ?: 'Müşteri')
                : null,
            default => null,
        };
    }

    /** @return array<int|string, string> */
    private function giderFaturaSecenekleri(string $arama = ''): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        return Fatura::query()
            ->where('firma_id', $firmaId)
            ->where(function (Builder $query): void {
                $query->whereIn('tur', [FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value])
                    ->orWhere('fatura_sinifi', FaturaSinifi::Gider->value);
            })
            ->whereNot('durum', FaturaDurumu::Iptal->value)
            ->when(trim($arama) !== '', function (Builder $query) use ($arama): void {
                $term = '%'.trim($arama).'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('fatura_no', 'like', $term)
                        ->orWhere('aciklama', 'like', $term)
                        ->orWhere('notlar', 'like', $term);
                });
            })
            ->orderByDesc('tarih')
            ->limit(50)
            ->withSum('masrafDagitimlari as masraf_dagitim_toplami', 'tutar')
            ->get(['id', 'fatura_no', 'tarih', 'genel_toplam', 'odenecek_tutar', 'para_birimi', 'aciklama'])
            ->mapWithKeys(fn (Fatura $fatura): array => [
                $fatura->id => $this->faturaEtiketi($fatura),
            ])
            ->all();
    }

    private function giderFaturaEtiketi(mixed $value): ?string
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null || ! $value) {
            return null;
        }

        $fatura = Fatura::query()
            ->where('firma_id', $firmaId)
            ->whereKey((int) $value)
            ->where(function (Builder $query): void {
                $query->whereIn('tur', [FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value])
                    ->orWhere('fatura_sinifi', FaturaSinifi::Gider->value);
            })
            ->whereNot('durum', FaturaDurumu::Iptal->value)
            ->withSum('masrafDagitimlari as masraf_dagitim_toplami', 'tutar')
            ->first(['id', 'fatura_no', 'tarih', 'genel_toplam', 'odenecek_tutar', 'para_birimi', 'aciklama']);

        return $fatura ? $this->faturaEtiketi($fatura) : null;
    }

    private function giderFaturasi(int $firmaId, mixed $value): ?Fatura
    {
        $id = (int) $value;
        if ($firmaId < 1 || $id < 1) {
            return null;
        }

        return Fatura::query()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->where(function (Builder $query): void {
                $query->whereIn('tur', [FaturaTuru::Gider->value, FaturaTuru::GiderFaturasi->value])
                    ->orWhere('fatura_sinifi', FaturaSinifi::Gider->value);
            })
            ->whereNot('durum', FaturaDurumu::Iptal->value)
            ->withSum('masrafDagitimlari as masraf_dagitim_toplami', 'tutar')
            ->first(['id', 'fatura_no', 'tarih', 'genel_toplam', 'odenecek_tutar', 'para_birimi', 'aciklama']);
    }

    private function giderFaturasiPasifMi(mixed $value): bool
    {
        $fatura = $value instanceof Fatura
            ? $value
            : $this->giderFaturasi((int) ($this->aktifFirmaId() ?? 0), $value);

        if (! $fatura) {
            return true;
        }

        return bccomp((string) ($fatura->masraf_dagitim_toplami ?? 0), '0', 2) > 0
            || bccomp($this->faturaTavanTutari($fatura), '0', 2) <= 0;
    }

    private function faturaTavanTutari(Fatura $fatura): string
    {
        $odenecek = bcadd((string) ($fatura->odenecek_tutar ?? 0), '0', 2);

        return bccomp($odenecek, '0', 2) > 0
            ? $odenecek
            : bcadd((string) ($fatura->genel_toplam ?? 0), '0', 2);
    }

    private function giderFaturaTutarEtiketi(mixed $value): string
    {
        $fatura = $this->giderFaturasi((int) ($this->aktifFirmaId() ?? 0), $value);
        if (! $fatura) {
            return 'Fatura seçildiğinde tutar otomatik alınır.';
        }

        $tutar = number_format((float) $this->faturaTavanTutari($fatura), 2, ',', '.');
        $birim = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));

        return $tutar.' '.$birim.' — faturanın tamamı masraf olarak kaydedilir.';
    }

    /** @return array<int|string, string> */
    private function cariSecenekleri(string $arama = ''): array
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return [];
        }

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->when(trim($arama) !== '', fn (Builder $query): Builder => $query->where('ad', 'like', '%'.trim($arama).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    private function cariEtiketi(mixed $value): ?string
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null || ! $value) {
            return null;
        }

        return Cari::query()->where('firma_id', $firmaId)->whereKey((int) $value)->value('ad');
    }

    private function faturaEtiketi(Fatura $fatura): string
    {
        $no = trim((string) ($fatura->fatura_no ?: 'Taslak #'.$fatura->id));
        $tutar = number_format((float) $this->faturaTavanTutari($fatura), 2, ',', '.').' '.strtoupper((string) ($fatura->para_birimi ?: 'TRY'));
        $pasif = $this->giderFaturasiPasifMi($fatura) ? ' | Pasif' : '';

        return $no.' | '.$tutar.' | '.Str::limit(trim((string) $fatura->aciklama), 45).$pasif;
    }

    private function odemeIcinFatura(Masraf $masraf): ?Fatura
    {
        return $masraf->faturalar->first(function (Fatura $fatura): bool {
            $durum = $fatura->durum instanceof FaturaDurumu
                ? $fatura->durum
                : FaturaDurumu::tryFrom((string) $fatura->durum);

            return $durum === FaturaDurumu::Onayli
                && bccomp((string) ($fatura->acik_tutar ?? $fatura->odenecek_tutar ?? 0), '0', 2) === 1;
        });
    }

    private function masrafFormunuDoldur(): void
    {
        $this->kategoriSecimState['kategori_seviyesi'] = [];
        $this->form->fill([
            'tarih' => now()->toDateString(),
            'masraf_kategorisi_id' => null,
            'isletme_proje_id' => null,
            'tutar' => null,
            'para_birimi' => 'TRY',
            'masraf_para_birimi' => 'TRY',
            'fatura_modu' => 'yok',
            'fatura_id' => null,
            'fatura_cari_id' => null,
            'fatura_tarihi' => now()->toDateString(),
            'fatura_vade_tarihi' => null,
            'fatura_aciklama' => null,
            'fatura_notlar' => null,
            'firma_id' => $this->aktifFirmaId(),
            'kalemler' => [],
            'durum' => FaturaDurumu::Taslak->value,
            'doviz_kuru' => 1,
            'kaynak_turu' => '',
            'kaynak_id' => null,
            'yakit_litre' => null,
            'litre_fiyati' => null,
            'kaynak_kilometre' => null,
            'aciklama' => null,
            'notlar' => null,
            'belge_yolu' => null,
            'belge_adi' => null,
        ]);
    }

    private function filtreFormunuDoldur(): void
    {
        $this->filtreForm->fill($this->filtreler);
    }

    private function filtreleriVarsayilanla(): void
    {
        $this->filtreler = [
            'baslangic' => now()->startOfMonth()->toDateString(),
            'bitis' => now()->toDateString(),
            'kategori' => '',
            'isletme_proje_id' => '',
            'durum' => 'aktif',
        ];
    }

    private function idempotencyKeyYenile(): void
    {
        $this->idempotencyKey = 'masraf:'.Str::uuid()->toString();
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $firmaId ? (int) $firmaId : null;
    }

    private function yetkiVarMi(string $yetkiKodu): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi($yetkiKodu);
    }

    private function tutarGoster(mixed $tutar, string $paraBirimi): string
    {
        return number_format((float) $tutar, 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    private function uyariGoster(string $baslik, string $govde): void
    {
        Notification::make()->title($baslik)->body($govde)->warning()->send();
    }
}
