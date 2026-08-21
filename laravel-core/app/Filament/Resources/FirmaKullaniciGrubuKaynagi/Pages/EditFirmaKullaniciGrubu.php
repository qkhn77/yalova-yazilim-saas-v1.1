<?php

namespace App\Filament\Resources\FirmaKullaniciGrubuKaynagi\Pages;

use App\Filament\Resources\FirmaKullaniciGrubuKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditFirmaKullaniciGrubu extends EditRecord
{
    protected static string $resource = FirmaKullaniciGrubuKaynagi::class;

    protected static string $view = 'filament.resources.firma-kullanici-grubu-kaynagi.pages.edit-firma-kullanici-grubu';

    public function form(Form $form): Form
    {
        if (FirmaKullaniciGrubuKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (FirmaKullaniciGrubuKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'ad' => (string) ($this->record->ad ?? ''),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (FirmaKullaniciGrubuKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $ad = trim((string) ($this->data['ad'] ?? ''));
        if ($ad === '') {
            throw ValidationException::withMessages([
                'data.ad' => 'Grup adi zorunludur.',
            ]);
        }

        $this->record->forceFill([
            'ad' => $ad,
        ])->save();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = FirmaKullaniciGrubuKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? request()->fullUrlWithoutQuery('detay')
                    : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['kod'], $data['sistem_rolu_mu']);

        return $data;
    }
}
