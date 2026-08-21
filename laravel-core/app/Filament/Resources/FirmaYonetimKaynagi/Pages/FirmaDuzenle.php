<?php

namespace App\Filament\Resources\FirmaYonetimKaynagi\Pages;

use App\Filament\Resources\FirmaYonetimKaynagi;
use App\Models\Firma;
use App\Support\DenetimYardimcisi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class FirmaDuzenle extends EditRecord
{
    protected static string $resource = FirmaYonetimKaynagi::class;

    public function form(Form $form): Form
    {
        if (FirmaYonetimKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (FirmaYonetimKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) ($this->record->durum ?? Firma::DURUM_BEKLEMEDE),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (FirmaYonetimKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! array_key_exists($durum, Firma::durumSecenekleri())) {
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
        $detayModu = FirmaYonetimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? FirmaYonetimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey(), 'hizli' => 1])
                    : FirmaYonetimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])),
        ];
    }

    protected function getFormActions(): array
    {
        if (FirmaYonetimKaynagi::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    protected function afterSave(): void
    {
        DenetimYardimcisi::kaydet(
            'firma_guncellendi',
            Firma::class,
            (int) $this->record->id,
            (int) $this->record->id,
            null,
            $this->record->only(['ad', 'durum', 'firma_kodu', 'eposta', 'telefon'])
        );
    }
}
