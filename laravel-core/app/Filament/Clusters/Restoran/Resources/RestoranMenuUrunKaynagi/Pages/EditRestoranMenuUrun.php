<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranMenuUrunKaynagi\Pages;

use App\Filament\Clusters\Restoran\Resources\RestoranMenuUrunKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;

class EditRestoranMenuUrun extends EditRecord
{
    protected static string $resource = RestoranMenuUrunKaynagi::class;

    protected static string $view = 'filament.clusters.restoran.resources.restoran-menu-urun-kaynagi.pages.edit-restoran-menu-urun';

    public function form(Form $form): Form
    {
        if (RestoranMenuUrunKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (RestoranMenuUrunKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'ad' => (string) ($this->record->ad ?? ''),
            'fiyat' => (float) ($this->record->fiyat ?? 0),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (RestoranMenuUrunKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $this->record->forceFill([
            'ad' => trim((string) ($this->data['ad'] ?? '')),
            'fiyat' => max(0, (float) ($this->data['fiyat'] ?? 0)),
        ])->save();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = RestoranMenuUrunKaynagi::detayModu();

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
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }
}
