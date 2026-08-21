<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EditTeklifSablonu extends EditRecord
{
    protected static string $resource = TeklifSablonKaynagi::class;

    protected static string $view = 'filament.clusters.teklif-yonetimi.resources.teklif-sablon-kaynagi.pages.edit-teklif-sablonu';

    protected static ?string $title = 'Teklif Şablonunu Düzenle';

    public function form(Form $form): Form
    {
        if (TeklifSablonKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (TeklifSablonKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'ad' => (string) ($this->record->ad ?? ''),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (TeklifSablonKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $ad = trim((string) ($this->data['ad'] ?? ''));
        if ($ad === '') {
            throw ValidationException::withMessages([
                'data.ad' => 'Sablon adi zorunludur.',
            ]);
        }

        $data = $this->mutateFormDataBeforeSave([
            'ad' => $ad,
        ]);

        $this->handleRecordUpdate($this->record, $data);
        $this->afterSave();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = TeklifSablonKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? request()->fullUrlWithQuery(['hizli' => 1])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
            Actions\Action::make('onizleme')
                ->label('Son kullanıcı görünümü')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => TeklifSablonKaynagi::getUrl('preview', ['record' => $this->record])),
            Actions\Action::make('pdf_indir')
                ->label('PDF indir')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->openUrlInNewTab()
                ->url(fn (): string => route('admin.teklif-yonetimi.sablonlar.pdf', ['sablon' => $this->record->getKey(), 'v' => now()->timestamp])),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => TeklifSablonKaynagi::canDelete($this->record)),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (TeklifSablonKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['firma_id'] = (int) $this->record->firma_id;
        if ((bool) ($data['varsayilan_mi'] ?? false)) {
            $data['aktif'] = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        Cache::forget('teklif_sablon_arama|'.((int) $this->record->firma_id).'|');

        if ((bool) $this->record->varsayilan_mi) {
            app(TeklifBaskiSablonuServisi::class)->varsayilanYap($this->record);
        }
    }
}
