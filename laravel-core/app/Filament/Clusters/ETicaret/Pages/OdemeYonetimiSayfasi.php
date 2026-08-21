<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\Ecommerce\EcommerceOdemeYontemi;
use App\Models\Firma;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\EcommerceOdemeTanimlari;
use App\Support\DenetimYardimcisi;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;

class OdemeYonetimiSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = ETicaretCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    protected static ?string $slug = 'odeme-yonetimi';

    protected static string $view = 'filament.clusters.e-ticaret.pages.odeme-yonetimi';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_odeme.goruntule';

    public function getHeading(): string|Htmlable
    {
        return __('filament.ecommerce.payment.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.ecommerce.payment.subheading');
    }

    public function getTitle(): string
    {
        return __('filament.ecommerce.payment.title');
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
                EcommerceOdemeYontemi::query()
                    ->where('firma_id', $this->aktifFirmaId())
            )
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label(__('filament.ecommerce.payment.columns.method'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('saglayici')
                    ->label(__('filament.ecommerce.payment.columns.provider'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceOdemeTanimlari::saglayicilar()[(string) $state] ?? (string) $state),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label(__('filament.ecommerce.payment.columns.active'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('varsayilan_mi')
                    ->label(__('filament.ecommerce.payment.columns.default'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('komisyon_orani')
                    ->label(__('filament.ecommerce.payment.columns.commission'))
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2, ',', '.') : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('saglayici_ayarlar.min_tutar')
                    ->label('Min. Tutar')
                    ->state(fn (EcommerceOdemeYontemi $record): string => $this->limitTutariniFormatla(data_get($record->saglayici_ayarlar, 'min_tutar')))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('saglayici_ayarlar.max_tutar')
                    ->label('Maks. Tutar')
                    ->state(fn (EcommerceOdemeYontemi $record): string => $this->limitTutariniFormatla(
                        data_get($record->saglayici_ayarlar, 'max_tutar')
                            ?: ((string) $record->saglayici === 'iyzico' ? 100000 : null)
                    ))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.ecommerce.payment.columns.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('filament.ecommerce.payment.actions.add'))
                    ->modalHeading(__('filament.ecommerce.payment.actions.add_heading'))
                    ->modalWidth('6xl')
                    ->form($this->odemeFormu())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['firma_id'] = $this->aktifFirmaId();

                        return $data;
                    })
                    ->action(function (array $data): void {
                        $firmaId = $this->aktifFirmaId();
                        if ((bool) ($data['varsayilan_mi'] ?? false)) {
                            EcommerceOdemeYontemi::query()
                                ->where('firma_id', $firmaId)
                                ->update(['varsayilan_mi' => false]);
                        }

                        $yeni = EcommerceOdemeYontemi::query()->create($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.odeme_yontemi.olustur',
                            konuTipi: EcommerceOdemeYontemi::class,
                            konuId: (int) $yeni->id,
                            firmaId: (int) $yeni->firma_id,
                            eskiVeri: null,
                            yeniVeri: $yeni->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.payment.notifications.added'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_odeme.guncelle')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('filament.ecommerce.payment.actions.edit'))
                    ->modalWidth('6xl')
                    ->form($this->odemeFormu())
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_odeme.guncelle'))
                    ->action(function (EcommerceOdemeYontemi $record, array $data): void {
                        $eski = $record->getOriginal();
                        if ((bool) ($data['varsayilan_mi'] ?? false)) {
                            EcommerceOdemeYontemi::query()
                                ->where('firma_id', $this->aktifFirmaId())
                                ->whereKeyNot($record->getKey())
                                ->update(['varsayilan_mi' => false]);
                        }

                        $record->update($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.odeme_yontemi.guncelle',
                            konuTipi: EcommerceOdemeYontemi::class,
                            konuId: (int) $record->id,
                            firmaId: (int) $record->firma_id,
                            eskiVeri: $eski,
                            yeniVeri: $record->fresh()?->toArray() ?? $record->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.payment.notifications.updated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label(__('filament.ecommerce.payment.actions.delete'))
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_odeme.guncelle')),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function odemeFormu(): array
    {
        return [
            Forms\Components\Section::make(__('filament.ecommerce.payment.sections.general'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('kod')
                        ->label(__('filament.ecommerce.payment.fields.code'))
                        ->required()
                        ->maxLength(64)
                        ->regex('/^[a-z0-9_\-]+$/')
                        ->helperText(__('filament.ecommerce.payment.fields.code_help')),
                    Forms\Components\TextInput::make('ad')
                        ->label(__('filament.ecommerce.payment.fields.display_name'))
                        ->required()
                        ->maxLength(160),
                    Forms\Components\Select::make('saglayici')
                        ->label(__('filament.ecommerce.payment.fields.provider'))
                        ->options(EcommerceOdemeTanimlari::saglayicilar())
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                            if ((string) $state === 'iyzico' && blank($get('saglayici_ayarlar.max_tutar'))) {
                                $set('saglayici_ayarlar.max_tutar', 100000);
                            }
                        }),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label(__('filament.ecommerce.payment.fields.active'))
                        ->default(true),
                    Forms\Components\Toggle::make('varsayilan_mi')
                        ->label(__('filament.ecommerce.payment.fields.default_method'))
                        ->default(false),
                    Forms\Components\CheckboxList::make('para_birimleri')
                        ->label(__('filament.ecommerce.payment.fields.currencies'))
                        ->options(EcommerceOdemeTanimlari::paraBirimleri())
                        ->columns(4),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.payment.sections.rules'))
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('uc_d_secure_zorunlu')
                        ->label(__('filament.ecommerce.payment.fields.secure3d_required'))
                        ->default(false),
                    Forms\Components\Toggle::make('taksit_aktif')
                        ->label(__('filament.ecommerce.payment.fields.installment_active'))
                        ->default(false),
                    Forms\Components\TextInput::make('max_taksit')
                        ->label(__('filament.ecommerce.payment.fields.max_installment'))
                        ->numeric()
                        ->minValue(2)
                        ->maxValue(12)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('taksit_aktif')),
                    Forms\Components\TextInput::make('komisyon_orani')
                        ->label(__('filament.ecommerce.payment.fields.commission_rate'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                    Forms\Components\Toggle::make('yeniden_deneme_aktif')
                        ->label(__('filament.ecommerce.payment.fields.retry_active'))
                        ->default(true),
                    Forms\Components\TextInput::make('max_yeniden_deneme')
                        ->label(__('filament.ecommerce.payment.fields.max_retry'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10)
                        ->default(3),
                    Forms\Components\Toggle::make('iade_api_aktif')
                        ->label(__('filament.ecommerce.payment.fields.refund_api_active'))
                        ->default(true),
                ]),
            Forms\Components\Section::make('Tutar Limitleri')
                ->description('Bu ödeme yönteminin hangi sipariş tutarlarında kullanılabileceğini belirleyin. Limitler sipariş para birimi üzerinden değerlendirilir.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('saglayici_ayarlar.min_tutar')
                        ->label('Minimum sipariş tutarı')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Boş veya 0 bırakılırsa alt limit uygulanmaz.'),
                    Forms\Components\TextInput::make('saglayici_ayarlar.max_tutar')
                        ->label('Maksimum sipariş tutarı')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('iyzico için önerilen üst limit 100.000 altıdır. Örn: 100000 girilirse 100.000 ve üzeri siparişlerde bu yöntem gösterilmez.'),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.payment.sections.credentials'))
                ->columns(2)
                ->description(function (Forms\Get $get): Htmlable|string|null {
                    $provider = (string) $get('saglayici');
                    if ($provider === EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT) {
                        return new HtmlString('<strong>Havale / EFT:</strong> Müşteriye gösterilecek banka hesabını, alıcı unvanını ve ödeme notunu burada tanımlayın.');
                    }
                    if ($provider === 'iyzico') {
                        return new HtmlString('<strong>iyzico:</strong> API Key ve Secret Key gerekir. Diğer alanlar kullanılmaz. <a href="https://docs.iyzico.com" target="_blank" rel="noopener">Dokümantasyon</a>');
                    }
                    if ($provider === 'paytr') {
                        return new HtmlString('<strong>PayTR:</strong> Merchant ID + API Key + Secret Key kullanılır. Webhook anahtarı önerilir. <a href="https://www.paytr.com/api" target="_blank" rel="noopener">Dokümantasyon</a>');
                    }
                    if ($provider === 'stripe') {
                        return new HtmlString('<strong>Stripe:</strong> Client ID + API Key + Secret Key kullanılır. Webhook anahtarı önerilir. <a href="https://stripe.com/docs/api" target="_blank" rel="noopener">Dokümantasyon</a>');
                    }
                    if ($provider === 'payoneer') {
                        return new HtmlString('<strong>Payoneer:</strong> Client ID + API Key + Secret Key kullanılır. Webhook anahtarı önerilir. <a href="https://developer.payoneer.com" target="_blank" rel="noopener">Dokümantasyon</a>');
                    }

                    return null;
                })
                ->schema([
                    Forms\Components\Select::make('saglayici_ayarlar.banka_hesap_id')
                        ->label('Banka hesabı')
                        ->options(function (): array {
                            return BankaHesabi::query()
                                ->where('firma_id', $this->aktifFirmaId())
                                ->orderBy('banka_adi')
                                ->orderBy('ad')
                                ->get()
                                ->mapWithKeys(fn (BankaHesabi $hesap): array => [
                                    (string) $hesap->id => trim(implode(' | ', array_filter([
                                        (string) ($hesap->banka_adi ?? ''),
                                        (string) $hesap->ad,
                                        (string) ($hesap->hesap_sahibi_unvan ?? ''),
                                        (string) ($hesap->iban ?? ''),
                                    ]))),
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->visible(fn (Forms\Get $get): bool => (string) $get('saglayici') === EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT)
                        ->required(fn (Forms\Get $get): bool => (string) $get('saglayici') === EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT),
                    Forms\Components\Textarea::make('saglayici_ayarlar.odeme_notu')
                        ->label('Ödeme notu / açıklama')
                        ->rows(3)
                        ->helperText('İsterseniz {siparis_no} ve {musteri_ad_soyad} yer tutucularını kullanabilirsiniz.')
                        ->columnSpanFull()
                        ->visible(fn (Forms\Get $get): bool => (string) $get('saglayici') === EcommerceOdemeTanimlari::SAGLAYICI_HAVALE_EFT),
                    Forms\Components\TextInput::make('saglayici_ayarlar.merchant_id')
                        ->label(__('filament.ecommerce.payment.fields.merchant_id'))
                        ->maxLength(255)
                        ->helperText('PayTR Merchant ID')
                        ->visible(fn (Forms\Get $get): bool => (string) $get('saglayici') === 'paytr'),
                    Forms\Components\TextInput::make('saglayici_ayarlar.client_id')
                        ->label(__('filament.ecommerce.payment.fields.client_id'))
                        ->maxLength(255)
                        ->helperText('Stripe / Payoneer Client ID')
                        ->visible(fn (Forms\Get $get): bool => in_array((string) $get('saglayici'), ['stripe', 'payoneer'], true)),
                    Forms\Components\TextInput::make('saglayici_ayarlar.api_key')
                        ->label(__('filament.ecommerce.payment.fields.api_key'))
                        ->maxLength(255)
                        ->helperText('API Key')
                        ->visible(fn (Forms\Get $get): bool => in_array((string) $get('saglayici'), ['iyzico', 'paytr', 'stripe', 'payoneer'], true)),
                    Forms\Components\TextInput::make('saglayici_ayarlar.secret_key')
                        ->label(__('filament.ecommerce.payment.fields.secret_key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText('Secret Key')
                        ->visible(fn (Forms\Get $get): bool => in_array((string) $get('saglayici'), ['iyzico', 'paytr', 'stripe', 'payoneer'], true)),
                    Forms\Components\TextInput::make('webhook_dogrulama_anahtari')
                        ->label(__('filament.ecommerce.payment.fields.webhook_key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->helperText('Webhook doğrulama anahtarı (opsiyonel)')
                        ->visible(fn (Forms\Get $get): bool => in_array((string) $get('saglayici'), ['paytr', 'stripe', 'payoneer'], true)),
                ]),
            Forms\Components\Section::make('Muhasebe Entegrasyonu')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('saglayici_ayarlar.muhasebe_tahsilat_kanali')
                        ->label('Tahsilat hedef tipi')
                        ->options([
                            'kasa' => 'Kasa',
                            'banka' => 'Banka',
                            'pos' => 'POS',
                        ])
                        ->helperText('Sipariş muhasebeye aktarılırken tahsilatın yazılacağı hesap tipini seçin.')
                        ->reactive(),
                    Forms\Components\Select::make('saglayici_ayarlar.muhasebe_kasa_hesap_id')
                        ->label('Hedef kasa hesabı')
                        ->options(function (): array {
                            return KasaHesabi::query()
                                ->where('firma_id', $this->aktifFirmaId())
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->required(fn (Forms\Get $get): bool => (string) $get('saglayici_ayarlar.muhasebe_tahsilat_kanali') === 'kasa')
                        ->visible(fn (Forms\Get $get): bool => (string) $get('saglayici_ayarlar.muhasebe_tahsilat_kanali') === 'kasa'),
                    Forms\Components\Select::make('saglayici_ayarlar.muhasebe_banka_hesap_id')
                        ->label('Hedef banka hesabı')
                        ->options(function (): array {
                            return BankaHesabi::query()
                                ->where('firma_id', $this->aktifFirmaId())
                                ->orderBy('banka_adi')
                                ->orderBy('ad')
                                ->get()
                                ->mapWithKeys(fn (BankaHesabi $hesap): array => [
                                    (string) $hesap->id => trim(implode(' | ', array_filter([
                                        (string) ($hesap->banka_adi ?? ''),
                                        (string) $hesap->ad,
                                        (string) ($hesap->hesap_sahibi_unvan ?? ''),
                                        (string) ($hesap->iban ?? ''),
                                    ]))),
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->required(fn (Forms\Get $get): bool => (string) $get('saglayici_ayarlar.muhasebe_tahsilat_kanali') === 'banka')
                        ->visible(fn (Forms\Get $get): bool => (string) $get('saglayici_ayarlar.muhasebe_tahsilat_kanali') === 'banka'),
                    Forms\Components\Select::make('saglayici_ayarlar.muhasebe_pos_hesap_id')
                        ->label('Hedef POS hesabı')
                        ->options(function (): array {
                            return PosHesabi::query()
                                ->where('firma_id', $this->aktifFirmaId())
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->required(fn (Forms\Get $get): bool => (string) $get('saglayici_ayarlar.muhasebe_tahsilat_kanali') === 'pos')
                        ->visible(fn (Forms\Get $get): bool => (string) $get('saglayici_ayarlar.muhasebe_tahsilat_kanali') === 'pos'),
                ]),
        ];
    }

    private function aktifFirmaId(): int
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        if ($firmaId > 0) {
            return $firmaId;
        }

        return (int) Firma::query()->orderBy('id')->value('id');
    }

    private function yetkiVarMi(string $yetkiKodu): bool
    {
        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return false;
        }

        $firmaId = app(TenantContextService::class)->aktifFirmaId();

        return app(SidebarService::class)->menuGorunurMu($kullanici, $firmaId, 'e_ticaret', $yetkiKodu);
    }

    private function limitTutariniFormatla(mixed $tutar): string
    {
        if ($tutar === null || $tutar === '' || (float) $tutar <= 0) {
            return '-';
        }

        return number_format((float) $tutar, 2, ',', '.');
    }
}
