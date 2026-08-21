<?php

namespace App\Filament\Clusters\ETicaret\Pages;

use App\Filament\Clusters\ETicaret as ETicaretCluster;
use App\Models\Ecommerce\EcommerceBildirimLog;
use App\Models\Firma;
use App\Models\User;
use App\Services\EcommerceBildirimServisi;
use App\Services\SidebarService;
use App\Services\TenantContextService;
use App\Support\EcommerceBildirimTanimlari;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class BildirimLoglariSayfasi extends Page implements HasTable
{
    use InteractsWithTable;

    private ?int $aktifFirmaIdCache = null;

    /** @var array<string, bool> */
    private array $yetkiCache = [];

    protected static ?string $cluster = ETicaretCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    protected static ?string $slug = 'bildirim-yonetimi/loglar';

    protected static string $view = 'filament.clusters.e-ticaret.pages.bildirim-loglari';

    protected static ?string $gerekenYetkiKodu = 'e_ticaret_bildirim.goruntule';

    public function getHeading(): string|Htmlable
    {
        return __('filament.ecommerce.notification_logs.title');
    }

    public function getSubheading(): ?string
    {
        return __('filament.ecommerce.notification_logs.subheading');
    }

    public function getTitle(): string
    {
        return __('filament.ecommerce.notification_logs.title');
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
                EcommerceBildirimLog::query()
                    ->select([
                        'id',
                        'firma_id',
                        'siparis_id',
                        'olay',
                        'kanal',
                        'hedef',
                        'baslik',
                        'icerik',
                        'durum',
                        'deneme_sayisi',
                        'created_at',
                    ])
                    ->with(['siparis:id,siparis_no'])
                    ->where('firma_id', $this->aktifFirmaId())
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.ecommerce.notification_logs.columns.date'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('siparis.siparis_no')
                    ->label(__('filament.ecommerce.notification_logs.columns.order_no'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('olay')
                    ->label(__('filament.ecommerce.notification_logs.columns.event'))
                    ->formatStateUsing(fn (?string $state): string => EcommerceBildirimTanimlari::olaylar()[$state] ?? (string) $state)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kanal')
                    ->label(__('filament.ecommerce.notification_logs.columns.channel'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EcommerceBildirimTanimlari::kanallar()[$state] ?? (string) $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label(__('filament.ecommerce.notification_logs.columns.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        EcommerceBildirimLog::DURUM_GONDERILDI => 'success',
                        EcommerceBildirimLog::DURUM_BASARISIZ => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('hedef')
                    ->label(__('filament.ecommerce.notification_logs.columns.target'))
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('deneme_sayisi')
                    ->label(__('filament.ecommerce.notification_logs.columns.attempts'))
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('olay')
                    ->label(__('filament.ecommerce.notification_logs.filters.event'))
                    ->options(EcommerceBildirimTanimlari::olaylar()),
                Tables\Filters\SelectFilter::make('kanal')
                    ->label(__('filament.ecommerce.notification_logs.filters.channel'))
                    ->options(EcommerceBildirimTanimlari::kanallar()),
                Tables\Filters\SelectFilter::make('durum')
                    ->label(__('filament.ecommerce.notification_logs.filters.status'))
                    ->options([
                        EcommerceBildirimLog::DURUM_KUYRUKTA => __('filament.ecommerce.notification_logs.status.in_queue'),
                        EcommerceBildirimLog::DURUM_GONDERILDI => __('filament.ecommerce.notification_logs.status.sent'),
                        EcommerceBildirimLog::DURUM_BASARISIZ => __('filament.ecommerce.notification_logs.status.failed'),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('detay')
                    ->label(__('filament.ecommerce.notification_logs.actions.detail'))
                    ->icon('heroicon-o-document-text')
                    ->modalHeading(__('filament.ecommerce.notification_logs.actions.detail_heading'))
                    ->modalWidth('4xl')
                    ->modalContent(function (EcommerceBildirimLog $record): HtmlString {
                        $icerik = e((string) ($record->icerik ?? ''));
                        $icerik = nl2br($icerik);

                        $html = '<div class="space-y-2">';
                        $html .= '<div><strong>'.e(__('filament.ecommerce.notification_logs.detail.title')).':</strong> '.e((string) ($record->baslik ?? '')).'</div>';
                        $html .= '<div><strong>'.e(__('filament.ecommerce.notification_logs.detail.target')).':</strong> '.e((string) ($record->hedef ?? '')).'</div>';
                        $html .= '<div><strong>'.e(__('filament.ecommerce.notification_logs.detail.body')).':</strong><div class="mt-2 p-3 bg-gray-50 rounded">'.$icerik.'</div></div>';
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
                Tables\Actions\Action::make('tekrar')
                    ->label(__('filament.ecommerce.notification_logs.actions.resend'))
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->yetkiVarMi('e_ticaret_bildirim.guncelle'))
                    ->action(function (EcommerceBildirimLog $record): void {
                        app(EcommerceBildirimServisi::class)->logKaydiTekrarGonder($record);

                        Notification::make()
                            ->title(__('filament.ecommerce.notification_logs.notifications.resent'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
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

        return $this->yetkiCache[$yetkiKodu] = app(SidebarService::class)->menuGorunurMu(
            $kullanici,
            $this->aktifFirmaId(),
            'e_ticaret',
            $yetkiKodu
        );
    }
}
