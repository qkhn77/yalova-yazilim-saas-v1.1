<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\BirimTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditBirim extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = BirimTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.birim-tanim-kaynagi.pages.edit-birim';

    protected static ?string $title = 'Birim düzenle';

    public function form(Form $form): Form
    {
        if (request()->boolean('detay')) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (request()->boolean('detay')) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'ad' => (string) ($this->record->ad ?? ''),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (request()->boolean('detay')) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $ad = trim((string) ($this->data['ad'] ?? ''));
        if ($ad === '') {
            throw ValidationException::withMessages([
                'data.ad' => 'Ad zorunludur.',
            ]);
        }

        $data = $this->mutateFormDataBeforeSave([
            'ad' => $ad,
        ]);

        $this->handleRecordUpdate($this->record, $data);

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }
}
