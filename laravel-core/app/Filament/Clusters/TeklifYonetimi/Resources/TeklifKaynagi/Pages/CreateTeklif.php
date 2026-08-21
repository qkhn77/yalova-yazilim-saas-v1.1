<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
use App\Models\Muhasebe\TeklifKalemi;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\Services\TenantContextService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;

class CreateTeklif extends CreateRecord
{
    protected static string $resource = TeklifKaynagi::class;

    protected static ?string $title = 'Teklif Oluştur';

    protected ?string $kayitSonrasiAksiyon = null;

    public function mount(): void
    {
        parent::mount();

        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();

        $this->form->fill([
            'firma_id' => $firmaId > 0 ? $firmaId : null,
            'teklif_baski_sablonu_id' => TeklifKaynagi::varsayilanTeklifSablonuId($firmaId),
            'durum' => 'taslak',
            'tarih' => now(),
            'gecerlilik_tarihi' => now()->addDays(15),
            'para_birimi' => 'TRY',
            'revizyon_no' => 1,
        ]);
    }

    public function create(bool $another = false): void
    {
        TeklifKalemi::toplamGuncellemeleriniAskidaTut(function () use ($another): void {
            parent::create($another);
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
                        ->body('Teklif kaydedildiğinde ön izleme ve yazdırma için bu şablon kullanılacak.')
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
        ];
    }

    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            Actions\Action::make('kaydet_onizle')
                ->label('Kaydet ve ön izle')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function (): void {
                    $this->kayitSonrasiAksiyon = 'onizleme';
                    $this->create();
                }),
            Actions\Action::make('kaydet_yazdir')
                ->label('Kaydet ve yazdır')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function (): void {
                    $this->kayitSonrasiAksiyon = 'yazdir';
                    $this->create();
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $firmaId = $this->resolveFirmaId($data);

        return TeklifKaynagi::teklifVerisiniHazirla($data, $firmaId);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Teklif kaydedildi.';
    }

    protected function afterCreate(): void
    {
        $this->record->toplamlariniKalemlerdenGuncelle();
    }

    protected function getRedirectUrl(): string
    {
        if (! $this->record || ! in_array($this->kayitSonrasiAksiyon, ['onizleme', 'yazdir'], true)) {
            return parent::getRedirectUrl();
        }

        $url = TeklifKaynagi::getUrl('view', [
            'record' => $this->record,
        ]);
        $query = [];

        $sablonId = (int) Arr::get($this->form->getRawState(), 'teklif_baski_sablonu_id', 0);
        if ($sablonId > 0) {
            $query['preview_template'] = $sablonId;
        }

        if ($this->kayitSonrasiAksiyon === 'yazdir') {
            $query['auto_print'] = 1;
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function resolveFirmaId(array $data): int
    {
        if ((Auth::user()?->is_admin || Auth::user()?->super_admin_mi) && ! empty($data['firma_id'])) {
            return (int) $data['firma_id'];
        }

        return (int) app(TenantContextService::class)->aktifFirmaId();
    }

    /**
     * @return array<int, string>
     */
    private function sablonSecenekleri(): array
    {
        $state = $this->form->getRawState();
        $firmaId = (int) Arr::get($state, 'firma_id', 0);

        if ($firmaId < 1) {
            $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();
        }

        if ($firmaId < 1) {
            return [];
        }

        return TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->all();
    }
}
