<?php

namespace App\Filament\Clusters\Web\Resources\UrunKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages\EditStokKarti;
use App\Filament\Clusters\Web\Resources\UrunKaynagi;
use Filament\Actions;
use Filament\Forms\Form;
use Illuminate\Validation\ValidationException;

class EditUrun extends EditStokKarti
{
    protected static string $resource = UrunKaynagi::class;

    protected static string $view = 'filament.clusters.web.resources.urun-kaynagi.pages.edit-urun';

    public function form(Form $form): Form
    {
        if (UrunKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (UrunKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) (($this->record->durum?->value ?? $this->record->durum ?? 'aktif')),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (UrunKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! in_array($durum, ['aktif', 'pasif'], true)) {
            throw ValidationException::withMessages([
                'data.durum' => 'Durum gecersiz.',
            ]);
        }

        $this->handleRecordUpdate($this->record, [
            'durum' => $durum,
        ]);

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        if (UrunKaynagi::detayModu()) {
            return parent::getHeaderActions();
        }

        return [
            Actions\Action::make('detaylar')
                ->label('Detaylar')
                ->url(fn (): string => UrunKaynagi::getUrl('edit', [
                    'record' => (int) $this->record->getKey(),
                ]).'?detay=1&barkod_detay=1&e_ticaret_detay=1'),
        ];
    }
}
