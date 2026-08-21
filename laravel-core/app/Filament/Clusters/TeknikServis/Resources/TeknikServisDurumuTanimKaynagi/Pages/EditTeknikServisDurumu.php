<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisDurumuTanimKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditTeknikServisDurumu extends EditRecord
{
    protected static string $resource = TeknikServisDurumuTanimKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-durumu-tanim-kaynagi.pages.edit-teknik-servis-durumu';

    public function form(Form $form): Form
    {
        if (TeknikServisDurumuTanimKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (TeknikServisDurumuTanimKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif' => (bool) ($this->record->aktif ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (TeknikServisDurumuTanimKaynagi::detayModu()) {
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
        $detayModu = TeknikServisDurumuTanimKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? TeknikServisDurumuTanimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [Actions\DeleteAction::make()] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (TeknikServisDurumuTanimKaynagi::detayModu()) {
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
