<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\Ecommerce\EcommercePazaryeriEntegrasyon;
use App\Models\Firma;
use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Services\EcommercePazaryeriSiparisCekmeServisi;
use App\Support\DenetimYardimcisi;
use App\Support\EcommercePazaryeriTanimlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class PazaryeriEntegrasyonuSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    /** @var array<string, bool> */
    private array $yetkiCache = [];

    private ?int $aktifFirmaIdCache = null;

    protected static ?string $cluster = ETicaretCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    protected static ?string $slug = 'pazaryeri-entegrasyonu';

    protected static string $view = 'filament.clusters.e-ticaret.pages.pazaryeri-entegrasyonu';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_pazaryeri.goruntule';

    public function getHeading(): string|Htmlable
    {
        return __('filament.ecommerce.marketplace.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.ecommerce.marketplace.subheading');
    }

    public function getTitle(): string
    {
        return __('filament.ecommerce.marketplace.title');
    }

    public static function canAccess(): bool
    {
        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu(
            $kullanici,
            $firmaId,
            'e_ticaret',
            static::$gerekenYetkiKodu
        );
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(
                EcommercePazaryeriEntegrasyon::query()
                    ->where('firma_id', $this->aktifFirmaId())
            )
            ->columns([
                Tables\Columns\TextColumn::make('pazaryeri_kodu')
                    ->label(__('filament.ecommerce.marketplace.columns.marketplace'))
                    ->formatStateUsing(fn (?string $state): string => EcommercePazaryeriTanimlari::pazaryeriAdi((string) $state))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label(__('filament.ecommerce.marketplace.columns.active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('senkron_yonu')
                    ->label(__('filament.ecommerce.marketplace.columns.sync'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommercePazaryeriTanimlari::senkronYonleri()[(string) $state] ?? (string) $state),
                Tables\Columns\TextColumn::make('siparis_cekme_periyodu')
                    ->label(__('filament.ecommerce.marketplace.columns.order_pull_minutes'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.ecommerce.marketplace.columns.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('siparisCekiminiCalistir')
                    ->label(__('filament.ecommerce.marketplace.actions.run_pull'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_pazaryeri.guncelle'))
                    ->action(function (): void {
                        $ozet = app(EcommercePazaryeriSiparisCekmeServisi::class)->calistir(
                            firmaId: $this->aktifFirmaId(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.marketplace.notifications.pull_done_title'))
                            ->body(__('filament.ecommerce.marketplace.notifications.pull_done_body', [
                                'processed' => $ozet['islenen'],
                                'success' => $ozet['basarili'],
                                'failed' => $ozet['hatali'],
                            ]))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\CreateAction::make()
                    ->label(__('filament.ecommerce.marketplace.actions.add'))
                    ->modalHeading(__('filament.ecommerce.marketplace.actions.add_heading'))
                    ->modalWidth('6xl')
                    ->form($this->entegrasyonFormu())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['firma_id'] = $this->aktifFirmaId();
                        $data['pazaryeri_adi'] = $this->varsayilanPazaryeriAdi($data);

                        return $data;
                    })
                    ->action(function (array $data): void {
                        $firmaId = (int) Arr::get($data, 'firma_id', 0);
                        $pazaryeri = (string) Arr::get($data, 'pazaryeri_kodu', '');
                        if ($firmaId <= 0 || $pazaryeri === '') {
                            return;
                        }

                        $varMi = EcommercePazaryeriEntegrasyon::query()
                            ->where('firma_id', $firmaId)
                            ->where('pazaryeri_kodu', $pazaryeri)
                            ->exists();

                        if ($varMi) {
                            Notification::make()
                                ->title(__('filament.ecommerce.marketplace.notifications.exists'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $yeni = EcommercePazaryeriEntegrasyon::query()->create($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.pazaryeri_entegrasyonu.olustur',
                            konuTipi: EcommercePazaryeriEntegrasyon::class,
                            konuId: (int) $yeni->id,
                            firmaId: (int) $yeni->firma_id,
                            eskiVeri: null,
                            yeniVeri: $yeni->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.marketplace.notifications.added'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_pazaryeri.guncelle')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('filament.ecommerce.marketplace.actions.edit'))
                    ->modalWidth('6xl')
                    ->form($this->entegrasyonFormu())
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_pazaryeri.guncelle'))
                    ->action(function (EcommercePazaryeriEntegrasyon $record, array $data): void {
                        $eski = $record->getOriginal();
                        $data['pazaryeri_adi'] = $this->varsayilanPazaryeriAdi($data, $record);

                        $record->update($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.pazaryeri_entegrasyonu.guncelle',
                            konuTipi: EcommercePazaryeriEntegrasyon::class,
                            konuId: (int) $record->id,
                            firmaId: (int) $record->firma_id,
                            eskiVeri: $eski,
                            yeniVeri: $record->fresh()?->toArray() ?? $record->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.marketplace.notifications.updated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label(__('filament.ecommerce.marketplace.actions.delete'))
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_pazaryeri.guncelle')),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function entegrasyonFormu(): array
    {
        return [
            Forms\Components\Section::make(__('filament.ecommerce.marketplace.sections.marketplace'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('pazaryeri_kodu')
                        ->label(__('filament.ecommerce.marketplace.fields.marketplace'))
                        ->options(EcommercePazaryeriTanimlari::pazaryerleri())
                        ->required()
                        ->disabled(fn (?EcommercePazaryeriEntegrasyon $record): bool => $record !== null),
                    Forms\Components\TextInput::make('pazaryeri_adi')
                        ->label(__('filament.ecommerce.marketplace.fields.display_name'))
                        ->maxLength(120)
                        ->helperText(__('filament.ecommerce.marketplace.fields.display_name_help')),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label(__('filament.ecommerce.marketplace.fields.active'))
                        ->default(true),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.marketplace.sections.sync'))
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('senkron_yonu')
                        ->label(__('filament.ecommerce.marketplace.fields.sync_direction'))
                        ->options(EcommercePazaryeriTanimlari::senkronYonleri())
                        ->default('tek_yon')
                        ->required(),
                    Forms\Components\TextInput::make('siparis_cekme_periyodu')
                        ->label(__('filament.ecommerce.marketplace.fields.order_pull_period'))
                        ->numeric()
                        ->minValue(5)
                        ->default(30)
                        ->required(),
                    Forms\Components\Toggle::make('siparis_cekme_aktif')
                        ->label(__('filament.ecommerce.marketplace.fields.order_pull_active'))
                        ->default(true),
                    Forms\Components\Toggle::make('stok_senkron_aktif')
                        ->label(__('filament.ecommerce.marketplace.fields.stock_sync'))
                        ->default(true),
                    Forms\Components\Toggle::make('fiyat_senkron_aktif')
                        ->label(__('filament.ecommerce.marketplace.fields.price_sync'))
                        ->default(true),
                    Forms\Components\Toggle::make('hata_uyari_aktif')
                        ->label(__('filament.ecommerce.marketplace.fields.error_alert'))
                        ->default(true),
                    Forms\Components\TextInput::make('max_deneme')
                        ->label(__('filament.ecommerce.marketplace.fields.max_retry'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10)
                        ->default(3),
                    Forms\Components\TextInput::make('ayarlar.siparis_endpoint')
                        ->label(__('filament.ecommerce.marketplace.fields.order_endpoint'))
                        ->columnSpan(2)
                        ->url()
                        ->maxLength(500)
                        ->helperText(__('filament.ecommerce.marketplace.fields.order_endpoint_help')),
                    Forms\Components\Select::make('ayarlar.siparis_http_method')
                        ->label(__('filament.ecommerce.marketplace.fields.http_method'))
                        ->options([
                            'GET' => 'GET',
                            'POST' => 'POST',
                        ])
                        ->default('GET')
                        ->native(false),
                    Forms\Components\TextInput::make('ayarlar.timeout_saniye')
                        ->label(__('filament.ecommerce.marketplace.fields.timeout'))
                        ->numeric()
                        ->default(30)
                        ->minValue(5)
                        ->maxValue(120),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.marketplace.sections.credentials'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('kimlik_bilgileri.satici_id')
                        ->label(__('filament.ecommerce.marketplace.fields.seller_id'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kimlik_bilgileri.magaza_adi')
                        ->label(__('filament.ecommerce.marketplace.fields.store_name'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kimlik_bilgileri.api_key')
                        ->label(__('filament.ecommerce.marketplace.fields.api_key'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kimlik_bilgileri.api_secret')
                        ->label(__('filament.ecommerce.marketplace.fields.api_secret'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kimlik_bilgileri.client_id')
                        ->label(__('filament.ecommerce.marketplace.fields.client_id'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kimlik_bilgileri.client_secret')
                        ->label(__('filament.ecommerce.marketplace.fields.client_secret'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.marketplace.sections.notes'))
                ->schema([
                    Forms\Components\Textarea::make('ayarlar.not')
                        ->label(__('filament.ecommerce.marketplace.fields.note'))
                        ->rows(3)
                        ->maxLength(1000),
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

        return $this->aktifFirmaIdCache = (int) Firma::query()->orderBy('id')->value('id');
    }

    private function varsayilanPazaryeriAdi(array $data, ?EcommercePazaryeriEntegrasyon $record = null): ?string
    {
        $mevcut = (string) Arr::get($data, 'pazaryeri_adi', '');
        if ($mevcut !== '') {
            return $mevcut;
        }

        $kod = (string) Arr::get($data, 'pazaryeri_kodu', $record?->pazaryeri_kodu ?? '');
        if ($kod === '') {
            return null;
        }

        return EcommercePazaryeriTanimlari::pazaryeriAdi($kod);
    }

    private function yetkiVarMi(string $yetkiKodu): bool
    {
        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        $firmaId = $this->aktifFirmaId();
        $cacheKey = ((int) $kullanici->id).'|'.$firmaId.'|'.$yetkiKodu;

        if (array_key_exists($cacheKey, $this->yetkiCache)) {
            return $this->yetkiCache[$cacheKey];
        }

        return $this->yetkiCache[$cacheKey] = app(SidebarService::class)->menuGorunurMu(
            $kullanici,
            $firmaId,
            'e_ticaret',
            $yetkiKodu
        );
    }
}
