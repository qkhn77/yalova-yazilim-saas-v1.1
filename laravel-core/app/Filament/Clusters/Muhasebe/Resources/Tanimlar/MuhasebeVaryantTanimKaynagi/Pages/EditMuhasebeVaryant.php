<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\MuhasebeVaryantTanimKaynagi;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditMuhasebeVaryant extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = MuhasebeVaryantTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.muhasebe-varyant-tanim-kaynagi.pages.edit-muhasebe-varyant';

    protected static ?string $title = 'Varyant düzenle';

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
