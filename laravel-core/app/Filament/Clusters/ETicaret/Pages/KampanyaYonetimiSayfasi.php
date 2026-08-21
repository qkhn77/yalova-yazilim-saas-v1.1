<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\Ecommerce\EcommerceKampanya;
use App\Models\Firma;
use App\Models\User;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\EcommerceKampanyaTanimlari;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KampanyaYonetimiSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = ETicaretCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    protected static ?string $slug = 'kampanya-yonetimi';

    protected static string $view = 'filament.clusters.e-ticaret.pages.kampanya-yonetimi';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_kampanya.goruntule';

    public function getHeading(): string|Htmlable
    {
        return __('filament.ecommerce.campaign.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.ecommerce.campaign.subheading');
    }

    public function getTitle(): string
    {
        return __('filament.ecommerce.campaign.title');
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
                EcommerceKampanya::query()
                    ->where('firma_id', $this->aktifFirmaId())
            )
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label(__('filament.ecommerce.campaign.columns.campaign'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tip')
                    ->label(__('filament.ecommerce.campaign.columns.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceKampanyaTanimlari::tipler()[(string) $state] ?? (string) $state),
                Tables\Columns\TextColumn::make('hedef_tipi')
                    ->label(__('filament.ecommerce.campaign.columns.target'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceKampanyaTanimlari::hedefTipleri()[(string) $state] ?? (string) $state),
                Tables\Columns\TextColumn::make('kupon_kodu')
                    ->label(__('filament.ecommerce.campaign.columns.coupon'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('aktif_mi')
                    ->label(__('filament.ecommerce.campaign.columns.active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('baslangic_tarihi')
                    ->label(__('filament.ecommerce.campaign.columns.start'))
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bitis_tarihi')
                    ->label(__('filament.ecommerce.campaign.columns.end'))
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('filament.ecommerce.campaign.actions.add'))
                    ->modalHeading(__('filament.ecommerce.campaign.actions.add_heading'))
                    ->modalWidth('7xl')
                    ->form($this->kampanyaFormu())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['firma_id'] = $this->aktifFirmaId();
                        $data['kupon_kodu'] = $this->normalizeKupon($data['kupon_kodu'] ?? null);

                        return $data;
                    })
                    ->action(function (array $data): void {
                        $yeni = EcommerceKampanya::query()->create($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.kampanya.olustur',
                            konuTipi: EcommerceKampanya::class,
                            konuId: (int) $yeni->id,
                            firmaId: (int) $yeni->firma_id,
                            eskiVeri: null,
                            yeniVeri: $yeni->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.campaign.notifications.added'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_kampanya.guncelle')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('filament.ecommerce.campaign.actions.edit'))
                    ->modalWidth('7xl')
                    ->form($this->kampanyaFormu())
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_kampanya.guncelle'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['kupon_kodu'] = $this->normalizeKupon($data['kupon_kodu'] ?? null);

                        return $data;
                    })
                    ->action(function (EcommerceKampanya $record, array $data): void {
                        $eski = $record->getOriginal();
                        $record->update($data);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.kampanya.guncelle',
                            konuTipi: EcommerceKampanya::class,
                            konuId: (int) $record->id,
                            firmaId: (int) $record->firma_id,
                            eskiVeri: $eski,
                            yeniVeri: $record->fresh()?->toArray() ?? $record->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.campaign.notifications.updated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label(__('filament.ecommerce.campaign.actions.delete'))
                    ->visible(fn (EcommerceKampanya $record): bool => $this->yetkiVarMi('e_ticaret_kampanya.guncelle')
                        && ! DB::table('ecommerce_kampanya_kullanimlari')->where('kampanya_id', (int) $record->getKey())->exists()),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function kampanyaFormu(): array
    {
        return [
            Forms\Components\Section::make(__('filament.ecommerce.campaign.sections.general'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label(__('filament.ecommerce.campaign.fields.name'))
                        ->required()
                        ->maxLength(180),
                    Forms\Components\Select::make('tip')
                        ->label(__('filament.ecommerce.campaign.fields.discount_type'))
                        ->options(EcommerceKampanyaTanimlari::tipler())
                        ->required(),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label(__('filament.ecommerce.campaign.fields.active'))
                        ->default(true),
                    Forms\Components\Select::make('hedef_tipi')
                        ->label(__('filament.ecommerce.campaign.fields.target_type'))
                        ->options(EcommerceKampanyaTanimlari::hedefTipleri())
                        ->default('genel')
                        ->required(),
                    Forms\Components\TagsInput::make('hedef_idler')
                        ->label(__('filament.ecommerce.campaign.fields.target_ids'))
                        ->helperText(__('filament.ecommerce.campaign.fields.target_ids_help'))
                        ->visible(fn (Forms\Get $get): bool => in_array((string) $get('hedef_tipi'), ['kategori', 'urun', 'kullanici'], true)),
                    Forms\Components\TextInput::make('oncelik')
                        ->label(__('filament.ecommerce.campaign.fields.priority'))
                        ->numeric()
                        ->default(100)
                        ->minValue(1),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.campaign.sections.coupon'))
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('kupon_gerekli')
                        ->label(__('filament.ecommerce.campaign.fields.coupon_required'))
                        ->default(false),
                    Forms\Components\TextInput::make('kupon_kodu')
                        ->label(__('filament.ecommerce.campaign.fields.coupon_code'))
                        ->maxLength(64)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('kupon_gerekli')),
                    Forms\Components\Toggle::make('birlesebilir_mi')
                        ->label(__('filament.ecommerce.campaign.fields.combinable'))
                        ->default(false),
                    Forms\Components\TextInput::make('kullanici_basi_limit')
                        ->label(__('filament.ecommerce.campaign.fields.per_user_limit'))
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\TextInput::make('sistem_geneli_limit')
                        ->label(__('filament.ecommerce.campaign.fields.global_limit'))
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\TextInput::make('kullanilan_adet')
                        ->label(__('filament.ecommerce.campaign.fields.used_count'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.campaign.sections.amount'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('indirim_orani')
                        ->label(__('filament.ecommerce.campaign.fields.discount_rate'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->visible(fn (Forms\Get $get): bool => (string) $get('tip') === 'yuzde'),
                    Forms\Components\TextInput::make('indirim_tutari')
                        ->label(__('filament.ecommerce.campaign.fields.discount_amount'))
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get): bool => (string) $get('tip') === 'sabit_tutar'),
                    Forms\Components\TextInput::make('x_adet')
                        ->label(__('filament.ecommerce.campaign.fields.x_qty'))
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Forms\Get $get): bool => (string) $get('tip') === 'x_al_y_ode'),
                    Forms\Components\TextInput::make('y_adet')
                        ->label(__('filament.ecommerce.campaign.fields.y_qty'))
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Forms\Get $get): bool => (string) $get('tip') === 'x_al_y_ode'),
                    Forms\Components\TextInput::make('min_sepet_tutari')
                        ->label(__('filament.ecommerce.campaign.fields.min_cart'))
                        ->numeric()
                        ->minValue(0),
                    Forms\Components\Select::make('para_birimi')
                        ->label(__('filament.ecommerce.campaign.fields.currency'))
                        ->options(EcommerceKampanyaTanimlari::paraBirimleri())
                        ->searchable(),
                    Forms\Components\Toggle::make('ucretsiz_kargo')
                        ->label(__('filament.ecommerce.campaign.fields.free_shipping'))
                        ->default(false),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.campaign.sections.date'))
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('suresiz_mi')
                        ->label(__('filament.ecommerce.campaign.fields.unlimited'))
                        ->default(false),
                    Forms\Components\DatePicker::make('baslangic_tarihi')
                        ->label(__('filament.ecommerce.campaign.fields.start_date'))
                        ->native(false),
                    Forms\Components\DatePicker::make('bitis_tarihi')
                        ->label(__('filament.ecommerce.campaign.fields.end_date'))
                        ->native(false)
                        ->visible(fn (Forms\Get $get): bool => ! (bool) $get('suresiz_mi')),
                    Forms\Components\Textarea::make('aciklama')
                        ->label(__('filament.ecommerce.campaign.fields.description'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ];
    }

    private function normalizeKupon(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return $value !== '' ? $value : null;
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
}
