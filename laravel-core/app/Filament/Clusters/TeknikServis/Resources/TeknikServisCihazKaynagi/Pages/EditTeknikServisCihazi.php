<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisCihazKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditTeknikServisCihazi extends EditRecord
{
    protected static string $resource = TeknikServisCihazKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-cihaz-kaynagi.pages.edit-teknik-servis-cihazi';

    public function form(Form $form): Form
    {
        if (TeknikServisCihazKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (TeknikServisCihazKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif' => (bool) ($this->record->aktif ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (TeknikServisCihazKaynagi::detayModu()) {
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
        $detayModu = TeknikServisCihazKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? TeknikServisCihazKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [Actions\DeleteAction::make()] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (TeknikServisCihazKaynagi::detayModu()) {
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
