<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Muhasebe\TeklifKalemi;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Services\TenantContextService;
use App\TeklifYonetimi\Servisler\TeklifIsAkisiServisi;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;

class EditTeklif extends EditRecord
{
    protected static string $resource = TeklifKaynagi::class;

    protected static ?string $title = 'Teklif Düzenle';

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        TeklifKalemi::toplamGuncellemeleriniAskidaTut(function () use ($shouldRedirect, $shouldSendSavedNotification): void {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sablon_sec')
                ->label('Şablon seç')
                ->icon('heroicon-o-swatch')
                ->fillForm(fn (): array => [
                    'selected_template_id' => (int) Arr::get($this->form->getRawState(), 'teklif_baski_sablonu_id', 0) ?: null,
                ])
                ->form([
                    Select::make('selected_template_id')
                        ->label('Kayıtlı şablonlar')
                        ->options(fn (): array => $this->sablonSecenekleri())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $this->data['teklif_baski_sablonu_id'] = (int) ($data['selected_template_id'] ?? 0) ?: null;

                    Notification::make()
                        ->title('Şablon seçildi')
                        ->body('Ön izleme ve yazdırma için seçiminiz güncellendi.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('kurlari_guncelle')
                ->label('Kurları güncelle')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $state = TeklifKaynagi::kurDurumunuFormDurumundaYenile($this->form->getRawState(), 'kur_yenile');
                    $this->form->fill($state);

                    Notification::make()
                        ->title(filled($state['kur_hata_mesaji'] ?? null) ? 'Kur bilgileri alınamadı' : 'Kurlar güncellendi')
                        ->body(filled($state['kur_hata_mesaji'] ?? null) ? (string) $state['kur_hata_mesaji'] : 'Teklif kalemleri güncel kurlarla yenilendi.')
                        ->status(filled($state['kur_hata_mesaji'] ?? null) ? 'danger' : 'success')
                        ->send();
                }),
            Actions\Action::make('onizleme')
                ->label('Ön izleme')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => $this->onizlemeUrlOlustur()),
            Actions\Action::make('yazdir')
                ->label('Yazdır')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->openUrlInNewTab()
                ->url(fn (): string => $this->onizlemeUrlOlustur(autoPrint: true)),
            Actions\Action::make('pdf_indir')
                ->label('PDF indir')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (): string => $this->pdfUrlOlustur()),
            Actions\ActionGroup::make([
                Actions\Action::make('durum_gonderildi')
                    ->label('Müşteriye gönderildi')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'gonderildi')
                    ->action(fn (): null => $this->durumDegistir('gonderildi')),
                Actions\Action::make('durum_onaylandi')
                    ->label('Onaylandı')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'onaylandi')
                    ->action(fn (): null => $this->durumDegistir('onaylandi')),
                Actions\Action::make('durum_revizyon')
                    ->label('Revizyon bekliyor')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'revizyon_bekliyor')
                    ->action(fn (): null => $this->durumDegistir('revizyon_bekliyor')),
                Actions\Action::make('durum_reddedildi')
                    ->label('Reddedildi')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => (string) $this->record->durum !== 'reddedildi')
                    ->action(fn (): null => $this->durumDegistir('reddedildi')),
                Actions\Action::make('durum_suresi_doldu')
                    ->label('Süresi doldu')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'suresi_doldu')
                    ->action(fn (): null => $this->durumDegistir('suresi_doldu')),
            ])
                ->label('Durum')
                ->icon('heroicon-o-flag'),
            Actions\Action::make('faturaya_donustur')
                ->label('Faturaya dönüştür')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) $this->record->durum === 'onaylandi' && Gate::allows('create', \App\Models\Muhasebe\Fatura::class))
                ->action(fn (): null => $this->faturayaDonustur()),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => TeklifKaynagi::canDelete($this->record)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $firmaId = $this->resolveFirmaId($data);

        return TeklifKaynagi::teklifVerisiniHazirla($data, $firmaId, filled($data['teklif_no'] ?? null));
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Teklif güncellendi.';
    }

    protected function afterSave(): void
    {
        $this->record->toplamlariniKalemlerdenGuncelle();
    }

    private function resolveFirmaId(array $data): int
    {
        if ((Auth::user()?->is_admin || Auth::user()?->super_admin_mi) && ! empty($data['firma_id'])) {
            return (int) $data['firma_id'];
        }

        return (int) ($this->record->firma_id ?: app(TenantContextService::class)->aktifFirmaId());
    }

    private function onizlemeUrlOlustur(bool $autoPrint = false): string
    {
        $sablonId = (int) Arr::get($this->form->getRawState(), 'teklif_baski_sablonu_id', 0);

        $url = TeklifKaynagi::getUrl('view', [
            'record' => $this->record,
        ]);
        $query = [];

        if ($sablonId > 0) {
            $query['preview_template'] = $sablonId;
        }

        if ($autoPrint) {
            $query['auto_print'] = 1;
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function pdfUrlOlustur(): string
    {
        $sablonId = (int) Arr::get($this->form->getRawState(), 'teklif_baski_sablonu_id', 0);

        $parametreler = [
            'teklif' => $this->record->getKey(),
            'v' => now()->timestamp,
        ];

        if ($sablonId > 0) {
            $parametreler['preview_template'] = $sablonId;
        }

        return route('admin.teklif-yonetimi.teklifler.pdf', $parametreler);
    }

    private function durumDegistir(string $durum): null
    {
        $this->record = app(TeklifIsAkisiServisi::class)->durumDegistir($this->record, $durum);

        Notification::make()
            ->title('Teklif durumu güncellendi')
            ->body('Yeni durum: '.(\App\Models\Muhasebe\Teklif::DURUMLAR[$durum] ?? $durum))
            ->success()
            ->send();

        return null;
    }

    private function faturayaDonustur(): null
    {
        $fatura = app(TeklifIsAkisiServisi::class)->bekleyenFaturaOlustur($this->record);
        $this->record->refresh();

        Notification::make()
            ->title('Bekleyen fatura oluşturuldu')
            ->success()
            ->send();

        $this->redirect(FaturaKaynagi::getUrl('view', ['record' => $fatura]));

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function sablonSecenekleri(): array
    {
        return TeklifBaskiSablonu::query()
            ->where('firma_id', (int) $this->record->firma_id)
            ->where('aktif', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }
}
