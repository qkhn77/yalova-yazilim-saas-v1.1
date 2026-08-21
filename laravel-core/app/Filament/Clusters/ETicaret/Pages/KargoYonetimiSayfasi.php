<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\Ecommerce\EcommerceKargoYontemi;
use App\Models\Firma;
use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Services\EcommerceKargoServisi;
use App\Support\DenetimYardimcisi;
use App\Support\EcommerceKargoTanimlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class KargoYonetimiSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?array $simulasyonData = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $simulasyonSonuclari = [];

    public bool $simulasyonCalistirildi = false;

    public bool $simulasyonAcik = false;

    private ?int $aktifFirmaIdCache = null;

    /**
     * @var array<string, bool>
     */
    private array $yetkiCache = [];

    protected static ?string $cluster = ETicaretCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    protected static ?string $slug = 'kargo-yonetimi';

    protected static string $view = 'filament.clusters.e-ticaret.pages.kargo-yonetimi';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_kargo.goruntule';

    public function mount(): void
    {
        if ($this->simulasyonAcik) {
            $this->simulasyonForm->fill($this->varsayilanSimulasyonVerisi());
        }
    }

    public function getHeading(): string|Htmlable
    {
        return __('filament.ecommerce.cargo.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.ecommerce.cargo.subheading');
    }

    public function getTitle(): string
    {
        return __('filament.ecommerce.cargo.title');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        $sidebar = app(SidebarService::class);

        return $sidebar->menuGorunurMu($kullanici, $firmaId, 'e_ticaret', static::$gerekenYetkiKodu)
            || $sidebar->menuGorunurMu($kullanici, $firmaId, 'e_ticaret', 'e_ticaret_kargo.guncelle');
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(
                EcommerceKargoYontemi::query()
                    ->where('firma_id', $this->aktifFirmaId())
            )
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label(__('filament.ecommerce.cargo.columns.method'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kapsam')
                    ->label('Kapsam')
                    ->getStateUsing(function (EcommerceKargoYontemi $record): string {
                        $parcalar = [];
                        if ($record->yurt_ici_aktif) {
                            $parcalar[] = 'Yurt içi';
                        }
                        if ($record->yurt_disi_aktif) {
                            $parcalar[] = 'Yurt dışı';
                        }

                        $ulkelerHam = data_get($record->bolge_kurali, 'ulkeler', '');
                        $ulkeler = is_array($ulkelerHam) ? implode(', ', array_filter(array_map('trim', $ulkelerHam))) : trim((string) $ulkelerHam);
                        if ($ulkeler !== '') {
                            $parcalar[] = 'Ülkeler: '.$ulkeler;
                        }

                        return $parcalar !== [] ? implode(' · ', $parcalar) : 'Tanımsız';
                    })
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tip')
                    ->label(__('filament.ecommerce.cargo.columns.fee_type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceKargoTanimlari::ucretTipleri()[(string) $state] ?? (string) $state),
                Tables\Columns\TextColumn::make('hizmet_tipi')
                    ->label('Hizmet')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceKargoTanimlari::hizmetTipleri()[(string) $state] ?? (string) $state)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label(__('filament.ecommerce.cargo.columns.active'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('yurt_ici_aktif')
                    ->label('Yurt içi')
                    ->boolean(),
                Tables\Columns\IconColumn::make('yurt_disi_aktif')
                    ->label('Yurt dışı')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sabit_ucret')
                    ->label('Ücret')
                    ->formatStateUsing(fn ($state, EcommerceKargoYontemi $record): string => $state !== null ? number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')) : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ucretsiz_esik')
                    ->label(__('filament.ecommerce.cargo.columns.free_threshold'))
                    ->formatStateUsing(fn ($state, EcommerceKargoYontemi $record): string => $state ? number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')) : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.ecommerce.cargo.columns.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Henüz kargo yöntemi tanımlı değil')
            ->emptyStateDescription('Yurt içi ve yurt dışı gönderimler için kargo firması, ücret, ülke kapsamı ve entegrasyon kurallarını buradan tanımlayın.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('İlk Kargo Yöntemini Ekle')
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_kargo.guncelle'))
                    ->modalWidth('7xl')
                    ->form($this->kargoFormu())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['firma_id'] = $this->aktifFirmaId();
                        $data['kod'] = $this->normalizeKargoKodu($data);

                        return $data;
                    })
                    ->action(function (array $data): void {
                        $yeni = EcommerceKargoYontemi::query()->create($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.kargo_yontemi.olustur',
                            konuTipi: EcommerceKargoYontemi::class,
                            konuId: (int) $yeni->id,
                            firmaId: (int) $yeni->firma_id,
                            eskiVeri: null,
                            yeniVeri: $yeni->toArray(),
                        );

                        Notification::make()
                            ->title('Kargo yöntemi eklendi.')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('filament.ecommerce.cargo.actions.add'))
                    ->modalHeading(__('filament.ecommerce.cargo.actions.add_heading'))
                    ->modalWidth('6xl')
                    ->form($this->kargoFormu())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['firma_id'] = $this->aktifFirmaId();
                        $data['kod'] = $this->normalizeKargoKodu($data);

                        return $data;
                    })
                    ->action(function (array $data): void {
                        $yeni = EcommerceKargoYontemi::query()->create($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.kargo_yontemi.olustur',
                            konuTipi: EcommerceKargoYontemi::class,
                            konuId: (int) $yeni->id,
                            firmaId: (int) $yeni->firma_id,
                            eskiVeri: null,
                            yeniVeri: $yeni->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.cargo.notifications.added'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_kargo.guncelle')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('filament.ecommerce.cargo.actions.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->modalWidth('6xl')
                    ->form($this->kargoFormu())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['kod'] = $this->normalizeKargoKodu($data);

                        return $data;
                    })
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_kargo.guncelle'))
                    ->action(function (EcommerceKargoYontemi $record, array $data): void {
                        $eski = $record->getOriginal();
                        $record->update($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.kargo_yontemi.guncelle',
                            konuTipi: EcommerceKargoYontemi::class,
                            konuId: (int) $record->id,
                            firmaId: (int) $record->firma_id,
                            eskiVeri: $eski,
                            yeniVeri: $record->fresh()?->toArray() ?? $record->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.cargo.notifications.updated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label(__('filament.ecommerce.cargo.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_kargo.guncelle')),
            ])
            ->recordAction('edit')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    protected function getForms(): array
    {
        return $this->simulasyonAcik ? ['simulasyonForm'] : [];
    }

    public function simulasyonForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Kargo Simülasyonu')
                    ->description('Ülke bazlı kargo kurallarını test ederek hangi yöntemlerin checkout tarafında görüneceğini canlı kontrol edin.')
                    ->columns(5)
                    ->schema([
                        Forms\Components\TextInput::make('teslimat_ulke')
                            ->label('Ülke kodu')
                            ->required()
                            ->default('TR')
                            ->maxLength(2)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase;']),
                        Forms\Components\TextInput::make('teslimat_il')
                            ->label('İl / şehir')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('teslimat_posta_kodu')
                            ->label('Posta kodu')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('siparis_tutari')
                            ->label('Sipariş tutarı')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('desi')
                            ->label('Desi')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                    ]),
            ])
            ->statePath('simulasyonData');
    }

    public function kargoSimulasyonuCalistir(EcommerceKargoServisi $kargoServisi): void
    {
        $this->simulasyonCalistirildi = true;

        $sonuclar = $kargoServisi
            ->simulasyonSecenekleri($this->aktifFirmaId(), $this->simulasyonData ?? [])
            ->map(function (array $secenek): array {
                /** @var EcommerceKargoYontemi $yontem */
                $yontem = $secenek['yontem'];

                return [
                    'ad' => $yontem->ad,
                    'kod' => $yontem->kod,
                    'hizmet_tipi' => EcommerceKargoTanimlari::hizmetTipleri()[(string) ($yontem->hizmet_tipi ?? '')] ?? ((string) ($yontem->hizmet_tipi ?? '-') ?: '-'),
                    'ucret_formatli' => $secenek['ucret_formatli'],
                    'tahmini_teslim' => $secenek['tahmini_teslim'],
                    'kapsam_ozeti' => $secenek['kapsam_ozeti'],
                    'entegrasyon' => $yontem->entegrasyon ?: 'Manuel / Panel yönetimli',
                    'test_modu' => (bool) data_get($yontem->entegrasyon_ayarlar, 'test_modu', false),
                ];
            })
            ->values()
            ->all();

        $this->simulasyonSonuclari = $sonuclar;

        Notification::make()
            ->title($sonuclar === [] ? 'Bu kriterler için uygun kargo yöntemi bulunamadı.' : count($sonuclar).' kargo yöntemi bulundu.')
            ->success()
            ->send();
    }

    public function simulasyonAlaniniDegistir(): void
    {
        $this->simulasyonAcik = ! $this->simulasyonAcik;

        if ($this->simulasyonAcik) {
            $this->simulasyonForm->fill(array_replace(
                $this->varsayilanSimulasyonVerisi(),
                $this->simulasyonData ?? [],
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function varsayilanSimulasyonVerisi(): array
    {
        return [
            'teslimat_ulke' => 'TR',
            'teslimat_il' => 'Yalova',
            'teslimat_posta_kodu' => '',
            'siparis_tutari' => 5000,
            'desi' => 1,
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function kargoFormu(): array
    {
        return [
            Forms\Components\Section::make(__('filament.ecommerce.cargo.sections.general'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label(__('filament.ecommerce.cargo.fields.name'))
                        ->required()
                        ->maxLength(160),
                    Forms\Components\TextInput::make('kod')
                        ->label('Yöntem kodu')
                        ->helperText('Boş bırakılırsa kargo adı ve firma bilgisine göre otomatik oluşturulur.')
                        ->maxLength(80),
                    Forms\Components\Select::make('tip')
                        ->label(__('filament.ecommerce.cargo.fields.fee_type'))
                        ->options(EcommerceKargoTanimlari::ucretTipleri())
                        ->default('sabit')
                        ->required(),
                    Forms\Components\Select::make('hizmet_tipi')
                        ->label('Hizmet tipi')
                        ->options(EcommerceKargoTanimlari::hizmetTipleri())
                        ->default('standart')
                        ->searchable(),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label(__('filament.ecommerce.cargo.fields.active'))
                        ->default(true),
                    Forms\Components\Toggle::make('yurt_ici_aktif')
                        ->label('Yurt içi gönderim')
                        ->helperText('Türkiye adresleri için checkout üzerinde gösterilir.')
                        ->default(true),
                    Forms\Components\Toggle::make('yurt_disi_aktif')
                        ->label('Yurt dışı gönderim')
                        ->helperText('Türkiye dışı ülke seçildiğinde kullanılabilir.')
                        ->default(false),
                    Forms\Components\TextInput::make('sira')
                        ->label('Sıra')
                        ->numeric()
                        ->default(100)
                        ->minValue(0),
                    Forms\Components\TextInput::make('entegrasyon_ayarlar.servis_kodu')
                        ->label('Servis kodu')
                        ->helperText('Kargo firmasındaki servis / servis tipi kodu. Örn: STANDARD, EXPRESS.')
                        ->maxLength(120),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.cargo.sections.fee_rule'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('para_birimi')
                        ->label('Para birimi')
                        ->default('TRY')
                        ->maxLength(3)
                        ->required(),
                    Forms\Components\TextInput::make('sabit_ucret')
                        ->label(__('filament.ecommerce.cargo.fields.flat_fee'))
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => $get('tip') === 'sabit'),
                    Forms\Components\Repeater::make('kural.desi')
                        ->label(__('filament.ecommerce.cargo.fields.desi_ranges'))
                        ->schema([
                            Forms\Components\TextInput::make('min')
                                ->label(__('filament.ecommerce.cargo.fields.min_desi'))
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            Forms\Components\TextInput::make('max')
                                ->label(__('filament.ecommerce.cargo.fields.max_desi'))
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            Forms\Components\TextInput::make('ucret')
                                ->label(__('filament.ecommerce.cargo.fields.fee'))
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->visible(fn (Forms\Get $get): bool => $get('tip') === 'desi')
                        ->columns(3),
                    Forms\Components\Repeater::make('kural.tutar')
                        ->label(__('filament.ecommerce.cargo.fields.amount_ranges'))
                        ->schema([
                            Forms\Components\TextInput::make('min')
                                ->label(__('filament.ecommerce.cargo.fields.min_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            Forms\Components\TextInput::make('max')
                                ->label(__('filament.ecommerce.cargo.fields.max_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            Forms\Components\TextInput::make('ucret')
                                ->label(__('filament.ecommerce.cargo.fields.fee'))
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                        ])
                        ->visible(fn (Forms\Get $get): bool => $get('tip') === 'tutar')
                        ->columns(3),
                    Forms\Components\TextInput::make('ucretsiz_esik')
                        ->label(__('filament.ecommerce.cargo.fields.free_threshold'))
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\TextInput::make('tahmini_teslim_gun')
                        ->label(__('filament.ecommerce.cargo.fields.estimated_days'))
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\TextInput::make('kural.minimum_siparis_tutari')
                        ->label('Minimum sipariş tutarı')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Bu tutarın altındaki siparişlerde yöntem gizlenir.'),
                    Forms\Components\TextInput::make('kural.maksimum_siparis_tutari')
                        ->label('Maksimum sipariş tutarı')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Bu tutarın üzerindeki siparişlerde yöntem gizlenir.'),
                    Forms\Components\TextInput::make('kural.minimum_desi')
                        ->label('Minimum desi')
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\TextInput::make('kural.maksimum_desi')
                        ->label('Maksimum desi')
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\Toggle::make('kural.vergi_dahil_mi')
                        ->label('Kargo ücretine vergi dahil')
                        ->default(true),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.cargo.sections.region_rule'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('bolge_kurali.ulke_kapsami')
                        ->label('Ülke kapsamı')
                        ->options([
                            'domestic_only' => 'Sadece yurt içi',
                            'international_only' => 'Sadece yurt dışı',
                            'selected_countries' => 'Seçili ülkeler',
                            'all_countries_except' => 'Hariç tutulanlar dışındaki tüm ülkeler',
                        ])
                        ->default('domestic_only')
                        ->native(false),
                    Forms\Components\Textarea::make('bolge_kurali.ulkeler')
                        ->label('Desteklenen ülke kodları')
                        ->rows(3)
                        ->helperText('TR, DE, NL, US gibi ISO ülke kodlarını virgül veya satır satır yazın.'),
                    Forms\Components\Textarea::make('bolge_kurali.haric_ulkeler')
                        ->label('Hariç tutulan ülke kodları')
                        ->rows(3)
                        ->helperText('Tüm dünyaya satışta gönderim yapmayacağınız ülkeleri yazın.'),
                    Forms\Components\Textarea::make('bolge_kurali.iller')
                        ->label(__('filament.ecommerce.cargo.fields.cities'))
                        ->rows(3)
                        ->helperText(__('filament.ecommerce.cargo.fields.cities_help')),
                    Forms\Components\Textarea::make('bolge_kurali.posta_kodlari')
                        ->label('Posta kodu aralıkları / listesi')
                        ->rows(2)
                        ->helperText('Örn: 34***, 35*** veya 10115, 60311 gibi değerler.'),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.cargo.sections.integration'))
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('entegrasyon_aktif')
                        ->label(__('filament.ecommerce.cargo.fields.integration_active'))
                        ->default(false),
                    Forms\Components\Select::make('entegrasyon')
                        ->label(__('filament.ecommerce.cargo.fields.carrier'))
                        ->options(EcommerceKargoTanimlari::entegrasyonlar())
                        ->searchable(),
                    Forms\Components\TextInput::make('entegrasyon_ayarlar.musteri_no')
                        ->label(__('filament.ecommerce.cargo.fields.customer_no'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('entegrasyon_ayarlar.api_key')
                        ->label(__('filament.ecommerce.cargo.fields.api_key'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('entegrasyon_ayarlar.api_secret')
                        ->label(__('filament.ecommerce.cargo.fields.api_secret'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('entegrasyon_ayarlar.gonderici_ulke')
                        ->label('Gönderici ülke kodu')
                        ->default('TR')
                        ->maxLength(2),
                    Forms\Components\TextInput::make('entegrasyon_ayarlar.gonderici_posta_kodu')
                        ->label('Gönderici posta kodu')
                        ->maxLength(20),
                    Forms\Components\Toggle::make('entegrasyon_ayarlar.test_modu')
                        ->label('Test modu')
                        ->default(true),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.cargo.sections.return_cargo'))
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('iade_kargo_aktif')
                        ->label(__('filament.ecommerce.cargo.fields.return_active'))
                        ->default(false),
                    Forms\Components\TextInput::make('iade_kargo_ayarlar.kod_sablon')
                        ->label(__('filament.ecommerce.cargo.fields.return_code_template'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('iade_kargo_ayarlar.iade_depo_kodu')
                        ->label('İade depo / şube kodu')
                        ->maxLength(120),
                    Forms\Components\Toggle::make('iade_kargo_ayarlar.otomatik_etiket_uret')
                        ->label('İade etiketi otomatik üret')
                        ->default(false),
                ]),
        ];
    }

    private function aktifFirmaId(): int
    {
        if ($this->aktifFirmaIdCache !== null) {
            return $this->aktifFirmaIdCache;
        }

        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        if ($firmaId > 0) {
            return $this->aktifFirmaIdCache = $firmaId;
        }

        return $this->aktifFirmaIdCache = (int) Cache::remember(
            'e_ticaret:kargo_yonetimi:ilk_firma_id',
            now()->addMinutes(5),
            fn (): int => (int) Firma::query()->orderBy('id')->value('id'),
        );
    }

    private function yetkiVarMi(string $yetkiKodu): bool
    {
        if (array_key_exists($yetkiKodu, $this->yetkiCache)) {
            return $this->yetkiCache[$yetkiKodu];
        }

        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return $this->yetkiCache[$yetkiKodu] = false;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return $this->yetkiCache[$yetkiKodu] = app(SidebarService::class)->menuGorunurMu($kullanici, $firmaId, 'e_ticaret', $yetkiKodu);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normalizeKargoKodu(array $data): string
    {
        $kod = trim((string) ($data['kod'] ?? ''));
        if ($kod === '') {
            $kod = trim((string) ($data['ad'] ?? 'kargo-yontemi'));
        }

        return Str::limit(Str::slug($kod), 80, '');
    }
}
