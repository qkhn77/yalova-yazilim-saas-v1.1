<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\Ecommerce\EcommerceMesajKonu;
use App\Models\Firma;
use App\Models\Muhasebe\StokKarti;
use App\Models\User;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Services\EcommerceMesajServisi;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\DenetimYardimcisi;
use App\Support\EcommerceMesajTanimlari;
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
use Illuminate\Support\Facades\Cache;

abstract class MesajYonetimiBaseSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    private ?int $aktifFirmaIdCache = null;

    protected static ?string $cluster = ETicaretCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.clusters.e-ticaret.pages.mesaj-yonetimi';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_mesaj.goruntule';

    protected static string $konuTipi = EcommerceMesajTanimlari::KONU_TIPI_MUSTERI;

    protected static string $sayfaBaslik = 'filament.ecommerce.messages.title';

    public function getHeading(): string|Htmlable
    {
        return __(static::$sayfaBaslik);
    }

    public function getSubheading(): ?string
    {
        return __('filament.ecommerce.messages.subheading');
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
                EcommerceMesajKonu::query()
                    ->where('firma_id', $this->aktifFirmaId())
                    ->where('konu_tipi', static::$konuTipi)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')->label(__('filament.ecommerce.messages.columns.id'))->sortable(),
                Tables\Columns\TextColumn::make('baslik')
                    ->label(__('filament.ecommerce.messages.columns.subject'))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\ToggleColumn::make('visible_on_product')
                    ->label(__('filament.ecommerce.messages.columns.visible_on_product'))
                    ->visible(fn (): bool => static::$konuTipi === EcommerceMesajTanimlari::KONU_TIPI_URUN)
                    ->disabled(fn (): bool => ! $this->yetkiVarMi('e_ticaret_mesaj.guncelle'))
                    ->onColor('success')
                    ->offColor('gray'),
                Tables\Columns\TextColumn::make('musteri_ad_soyad')
                    ->label(__('filament.ecommerce.messages.columns.customer'))
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('durum')
                    ->label(__('filament.ecommerce.messages.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceMesajTanimlari::durumlar()[(string) $state] ?? (string) $state)
                    ->color(fn (?string $state): string => $this->durumRenk((string) $state)),
                Tables\Columns\TextColumn::make('okunmamis_mesaj_sayisi')
                    ->label(__('filament.ecommerce.messages.columns.unread'))
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) > 0 ? 'warning' : 'gray'),
                Tables\Columns\IconColumn::make('sla_ihlal_mi')
                    ->label(__('filament.ecommerce.messages.columns.sla'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('sla_son_tarih_at')
                    ->label(__('filament.ecommerce.messages.columns.sla_due'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.ecommerce.messages.columns.updated_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('filament.ecommerce.messages.actions.new_topic'))
                    ->modalHeading(__('filament.ecommerce.messages.actions.new_topic_heading'))
                    ->modalWidth('4xl')
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_mesaj.guncelle'))
                    ->form($this->yeniKonuFormu())
                    ->action(function (array $data): void {
                        $konu = app(EcommerceMesajServisi::class)->konuOlustur([
                            'firma_id' => $this->aktifFirmaId(),
                            'konu_tipi' => static::$konuTipi,
                            'baslik' => (string) $data['baslik'],
                            'stok_karti_id' => $data['stok_karti_id'] ?? null,
                            'visible_on_product' => (bool) ($data['visible_on_product'] ?? false),
                            'musteri_ad_soyad' => (string) ($data['musteri_ad_soyad'] ?? ''),
                            'musteri_email' => (string) ($data['musteri_email'] ?? ''),
                            'musteri_telefon' => (string) ($data['musteri_telefon'] ?? ''),
                            'ilk_mesaj' => (string) ($data['ilk_mesaj'] ?? ''),
                            'gonderen_tipi' => EcommerceMesajTanimlari::GONDEREN_MUSTERI,
                        ]);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.mesaj_konusu.olustur',
                            konuTipi: EcommerceMesajKonu::class,
                            konuId: (int) $konu->id,
                            firmaId: (int) $konu->firma_id,
                            eskiVeri: null,
                            yeniVeri: $konu->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.messages.notifications.topic_created'))
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('detay')
                    ->label(__('filament.ecommerce.messages.actions.detail'))
                    ->modalHeading(__('filament.ecommerce.messages.actions.detail_heading'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament.ecommerce.messages.actions.close'))
                    ->modalWidth('7xl')
                    ->modalContent(fn (EcommerceMesajKonu $record) => view('filament.clusters.e-ticaret.pages.partials.mesaj-konu-detay', [
                        'konu' => $record->load('mesajlar'),
                    ])),
                Tables\Actions\Action::make('yanitla')
                    ->label(__('filament.ecommerce.messages.actions.reply'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_mesaj.guncelle'))
                    ->modalWidth('4xl')
                    ->form($this->yanitFormu())
                    ->action(function (EcommerceMesajKonu $record, array $data): void {
                        $eski = $record->getOriginal();
                        $yeni = app(EcommerceMesajServisi::class)->mesajiEkle(
                            $record,
                            EcommerceMesajTanimlari::GONDEREN_ADMIN,
                            (string) ($data['icerik'] ?? ''),
                            (bool) ($data['ic_not_mu'] ?? false),
                            (bool) ($data['tamamlandi_sec'] ?? false),
                            isset($data['manuel_durum']) ? (string) $data['manuel_durum'] : null,
                        );
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.mesaj_konusu.yanitla',
                            konuTipi: EcommerceMesajKonu::class,
                            konuId: (int) $yeni->id,
                            firmaId: (int) $yeni->firma_id,
                            eskiVeri: $eski,
                            yeniVeri: $yeni->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.messages.notifications.reply_saved'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('musteriMesajiEkle')
                    ->label(__('filament.ecommerce.messages.actions.add_customer_message'))
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_mesaj.guncelle'))
                    ->modalWidth('4xl')
                    ->form([
                        Forms\Components\Textarea::make('icerik')
                            ->label(__('filament.ecommerce.messages.fields.customer_message'))
                            ->rows(5)
                            ->required(),
                    ])
                    ->action(function (EcommerceMesajKonu $record, array $data): void {
                        $eski = $record->getOriginal();
                        $yeni = app(EcommerceMesajServisi::class)->mesajiEkle(
                            $record,
                            EcommerceMesajTanimlari::GONDEREN_MUSTERI,
                            (string) ($data['icerik'] ?? ''),
                        );
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.mesaj_konusu.musteri_mesaji',
                            konuTipi: EcommerceMesajKonu::class,
                            konuId: (int) $yeni->id,
                            firmaId: (int) $yeni->firma_id,
                            eskiVeri: $eski,
                            yeniVeri: $yeni->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.messages.notifications.customer_message_added'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('okunduIsaretle')
                    ->label(__('filament.ecommerce.messages.actions.mark_read'))
                    ->color('gray')
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_mesaj.guncelle'))
                    ->action(function (EcommerceMesajKonu $record): void {
                        $eski = $record->getOriginal();
                        $record->update([
                            'okunmamis_mi' => false,
                            'okunmamis_mesaj_sayisi' => 0,
                            'sla_ihlal_mi' => false,
                        ]);
                        DenetimYardimcisi::kaydet(
                            olay: 'ecommerce.mesaj_konusu.okundu',
                            konuTipi: EcommerceMesajKonu::class,
                            konuId: (int) $record->id,
                            firmaId: (int) $record->firma_id,
                            eskiVeri: $eski,
                            yeniVeri: $record->fresh()?->toArray() ?? $record->toArray(),
                        );

                        Notification::make()
                            ->title(__('filament.ecommerce.messages.notifications.marked_read'))
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function yeniKonuFormu(): array
    {
        return [
            Forms\Components\TextInput::make('baslik')
                ->label(__('filament.ecommerce.messages.fields.subject'))
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('stok_karti_id')
                ->label('Ürün')
                ->options(fn (): array => StokKarti::query()
                    ->where('firma_id', $this->aktifFirmaId())
                    ->where('tur', StokKartiTuru::ETicaret->value)
                    ->orderBy('ad')
                    ->limit(500)
                    ->pluck('ad', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->required(fn (): bool => static::$konuTipi === EcommerceMesajTanimlari::KONU_TIPI_URUN)
                ->visible(fn (): bool => static::$konuTipi === EcommerceMesajTanimlari::KONU_TIPI_URUN),
            Forms\Components\Toggle::make('visible_on_product')
                ->label('Ürün sayfasında yayınla')
                ->helperText('Müşteri sorusu ve admin yanıtı ürün detayında yorum/soru-cevap olarak görünür.')
                ->default(false)
                ->visible(fn (): bool => static::$konuTipi === EcommerceMesajTanimlari::KONU_TIPI_URUN),
            Forms\Components\TextInput::make('musteri_ad_soyad')
                ->label(__('filament.ecommerce.messages.fields.customer_name'))
                ->maxLength(160),
            Forms\Components\TextInput::make('musteri_email')
                ->label(__('filament.ecommerce.messages.fields.customer_email'))
                ->email()
                ->maxLength(255),
            Forms\Components\TextInput::make('musteri_telefon')
                ->label(__('filament.ecommerce.messages.fields.customer_phone'))
                ->maxLength(50),
            Forms\Components\Textarea::make('ilk_mesaj')
                ->label(__('filament.ecommerce.messages.fields.first_message'))
                ->rows(5)
                ->required(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function yanitFormu(): array
    {
        return [
            Forms\Components\Textarea::make('icerik')
                ->label(__('filament.ecommerce.messages.fields.reply_text'))
                ->rows(5)
                ->required(),
            Forms\Components\Toggle::make('ic_not_mu')
                ->label(__('filament.ecommerce.messages.fields.internal_note'))
                ->default(false),
            Forms\Components\Toggle::make('tamamlandi_sec')
                ->label(__('filament.ecommerce.messages.fields.close_as_done'))
                ->default(false),
            Forms\Components\Select::make('manuel_durum')
                ->label(__('filament.ecommerce.messages.fields.manual_status'))
                ->options(EcommerceMesajTanimlari::durumlar())
                ->placeholder(__('filament.ecommerce.messages.fields.manual_status_placeholder')),
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
            'e_ticaret:mesaj_yonetimi:ilk_firma_id',
            now()->addMinutes(5),
            fn (): int => (int) Firma::query()->orderBy('id')->value('id'),
        );
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

    private function durumRenk(string $durum): string
    {
        return match ($durum) {
            EcommerceMesajTanimlari::DURUM_TAMAMLANDI => 'success',
            EcommerceMesajTanimlari::DURUM_YANITLANDI => 'info',
            EcommerceMesajTanimlari::DURUM_YENI,
            EcommerceMesajTanimlari::DURUM_OKUNMAMIS,
            EcommerceMesajTanimlari::DURUM_MUSTERI_YANITI_GELDI => 'warning',
            default => 'gray',
        };
    }
}
