<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisArizaKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditTeknikServisArizasi extends EditRecord
{
    protected static string $resource = TeknikServisArizaKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-ariza-kaynagi.pages.edit-teknik-servis-arizasi';

    public function form(Form $form): Form
    {
        if (TeknikServisArizaKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (TeknikServisArizaKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif' => (bool) ($this->record->aktif ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (TeknikServisArizaKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $this->record->forceFill([
            'aktif' => (bool) ($this->data['aktif'] ?? false),
        ])->save();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        if (! TeknikServisArizaKaynagi::detayModu()) {
            return [];
        }

        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        if (TeknikServisArizaKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }
}
