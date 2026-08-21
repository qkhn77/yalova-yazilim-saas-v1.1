<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\Ecommerce\EcommerceBildirimSablonu;
use App\Models\Firma;
use App\Models\User;
use App\Services\FirmaAyarDeposu;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\EcommerceBildirimTanimlari;
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

class BildirimSablonlariSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    private ?int $aktifFirmaIdMemo = null;

    /** @var array<string, bool> */
    private array $yetkiMemo = [];

    protected static ?string $cluster = ETicaretCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    protected static ?string $slug = 'bildirim-yonetimi/sablonlar';

    protected static string $view = 'filament.clusters.e-ticaret.pages.bildirim-sablonlari';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_bildirim.goruntule';

    public function getHeading(): string|Htmlable
    {
        return __('filament.ecommerce.notifications.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.ecommerce.notifications.subheading');
    }

    public function getTitle(): string
    {
        return __('filament.ecommerce.notifications.title');
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
                EcommerceBildirimSablonu::query()
                    ->where('firma_id', $this->aktifFirmaId())
            )
            ->columns([
                Tables\Columns\TextColumn::make('olay')
                    ->label(__('filament.ecommerce.notifications.columns.event'))
                    ->formatStateUsing(fn (?string $state): string => EcommerceBildirimTanimlari::olaylar()[$state] ?? (string) $state)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kanal')
                    ->label(__('filament.ecommerce.notifications.columns.channel'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceBildirimTanimlari::kanallar()[$state] ?? (string) $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label(__('filament.ecommerce.notifications.columns.locale'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('aktif_mi')
                    ->label(__('filament.ecommerce.notifications.columns.active'))
                    ->onColor('success')
                    ->offColor('gray'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.ecommerce.notifications.columns.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('filament.ecommerce.notifications.actions.add'))
                    ->modalHeading(__('filament.ecommerce.notifications.actions.add_heading'))
                    ->modalWidth('6xl')
                    ->form($this->sablonFormu())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['firma_id'] = $this->aktifFirmaId();

                        return $data;
                    })
                    ->action(function (array $data): void {
                        EcommerceBildirimSablonu::query()->create($data);

                        Notification::make()
                            ->title(__('filament.ecommerce.notifications.notifications.added'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_bildirim.guncelle')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('filament.ecommerce.notifications.actions.edit'))
                    ->modalWidth('6xl')
                    ->form($this->sablonFormu())
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_bildirim.guncelle'))
                    ->action(function (EcommerceBildirimSablonu $record, array $data): void {
                        $record->update($data);

                        Notification::make()
                            ->title(__('filament.ecommerce.notifications.notifications.updated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label(__('filament.ecommerce.notifications.actions.delete'))
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_bildirim.guncelle')),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function sablonFormu(): array
    {
        $varsayilanDil = $this->varsayilanDil();

        return [
            Forms\Components\Section::make(__('filament.ecommerce.notifications.sections.template'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('olay')
                        ->label(__('filament.ecommerce.notifications.fields.event'))
                        ->options(EcommerceBildirimTanimlari::olaylar())
                        ->required(),
                    Forms\Components\Select::make('kanal')
                        ->label(__('filament.ecommerce.notifications.fields.channel'))
                        ->options(EcommerceBildirimTanimlari::kanallar())
                        ->required(),
                    Forms\Components\TextInput::make('locale')
                        ->label(__('filament.ecommerce.notifications.fields.locale'))
                        ->default($varsayilanDil)
                        ->helperText(__('filament.ecommerce.notifications.fields.locale_help'))
                        ->required()
                        ->maxLength(12),
                    Forms\Components\Toggle::make('aktif_mi')
                        ->label(__('filament.ecommerce.notifications.fields.active'))
                        ->default(true),
                ]),
            Forms\Components\Section::make(__('filament.ecommerce.notifications.sections.content'))
                ->schema([
                    Forms\Components\TextInput::make('baslik')
                        ->label(__('filament.ecommerce.notifications.fields.title'))
                        ->maxLength(255)
                        ->required(fn (Forms\Get $get): bool => (string) $get('kanal') !== EcommerceBildirimTanimlari::KANAL_SMS),
                    Forms\Components\Textarea::make('icerik')
                        ->label(__('filament.ecommerce.notifications.fields.body'))
                        ->rows(12)
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('degiskenler')
                        ->label(__('filament.ecommerce.notifications.fields.variables'))
                        ->content('{siparis_no}, {musteri_ad}, {musteri_email}, {musteri_telefon}, {genel_toplam}, {para_birimi}, {kargo_firmasi}, {kargo_takip_no}, {kargo_takip_linki}, {odeme_linki}')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ];
    }

    private function aktifFirmaId(): int
    {
        if ($this->aktifFirmaIdMemo !== null) {
            return $this->aktifFirmaIdMemo;
        }

        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        if ($firmaId > 0) {
            return $this->aktifFirmaIdMemo = $firmaId;
        }

        return $this->aktifFirmaIdMemo = (int) Firma::query()->orderBy('id')->value('id');
    }

    private function varsayilanDil(): string
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId <= 0) {
            return 'tr';
        }

        $depo = app(FirmaAyarDeposu::class);
        $dil = (string) $depo->oku($firmaId, 'varsayilan_dil', 'tr');

        return $dil !== '' ? $dil : 'tr';
    }

    private function yetkiVarMi(string $yetkiKodu): bool
    {
        if (array_key_exists($yetkiKodu, $this->yetkiMemo)) {
            return $this->yetkiMemo[$yetkiKodu];
        }

        /** @var User|null $kullanici */
        $kullanici = Auth::user();
        if (! $kullanici) {
            return $this->yetkiMemo[$yetkiKodu] = false;
        }

        $firmaId = $this->aktifFirmaId();

        return $this->yetkiMemo[$yetkiKodu] = app(SidebarService::class)->menuGorunurMu($kullanici, $firmaId, 'e_ticaret', $yetkiKodu);
    }
}
