<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisMarkaKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditTeknikServisMarkasi extends EditRecord
{
    protected static string $resource = TeknikServisMarkaKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-marka-kaynagi.pages.edit-teknik-servis-markasi';

    public function form(Form $form): Form
    {
        if (TeknikServisMarkaKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (TeknikServisMarkaKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif' => (bool) ($this->record->aktif ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (TeknikServisMarkaKaynagi::detayModu()) {
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
        if (! TeknikServisMarkaKaynagi::detayModu()) {
            return [];
        }

        return [
            Actions\Action::make('hizli_form')
                ->label('Hizli Form')
                ->url(fn (): string => TeknikServisMarkaKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        if (TeknikServisMarkaKaynagi::detayModu()) {
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
