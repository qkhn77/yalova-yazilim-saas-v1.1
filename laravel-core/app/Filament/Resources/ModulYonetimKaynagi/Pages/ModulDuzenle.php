<?php

namespace App\Filament\Resources\ModulYonetimKaynagi\Pages;

use App\Filament\Resources\ModulYonetimKaynagi;
use App\Models\Modul;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class ModulDuzenle extends EditRecord
{
    protected static string $resource = ModulYonetimKaynagi::class;

    protected static string $view = 'filament.resources.modul-yonetim-kaynagi.pages.modul-duzenle';

    public function form(Form $form): Form
    {
        if (ModulYonetimKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (ModulYonetimKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif_mi' => (bool) $this->record->aktif_mi,
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (ModulYonetimKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $this->record->forceFill([
            'aktif_mi' => (bool) ($this->data['aktif_mi'] ?? false),
        ])->save();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = ModulYonetimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hizli Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? request()->fullUrlWithoutQuery('detay')
                : request()->fullUrlWithQuery(['detay' => 1])),
        ];
    }

    protected function getFormActions(): array
    {
        if (ModulYonetimKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (ModulYonetimKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'ad',
            'kod',
            'aciklama',
            'aktif_mi',
        ];

        $mevcut = Modul::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
