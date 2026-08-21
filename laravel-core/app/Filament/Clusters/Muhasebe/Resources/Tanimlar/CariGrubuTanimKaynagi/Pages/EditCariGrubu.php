<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\CariGrubuTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns\MutatesStandartMuhasebeTanimGuncelle;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCariGrubu extends EditRecord
{
    use MutatesStandartMuhasebeTanimGuncelle;

    protected static string $resource = CariGrubuTanimKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.tanimlar.cari-grubu-tanim-kaynagi.pages.edit-cari-grubu';

    protected static ?string $title = 'Cari grubu düzenle';

    public function form(Form $form): Form
    {
        if (CariGrubuTanimKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (CariGrubuTanimKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'aktif_mi' => (bool) ($this->record->aktif_mi ?? true),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (CariGrubuTanimKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        if (! array_key_exists('aktif_mi', $this->data)) {
            throw ValidationException::withMessages([
                'data.aktif_mi' => 'Aktif durumu gecersiz.',
            ]);
        }

        $this->handleRecordUpdate($this->record, $this->mutateFormDataBeforeSave([
            'aktif_mi' => (bool) $this->data['aktif_mi'],
        ]));

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = CariGrubuTanimKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->url(fn (): string => $detayModu
                    ? CariGrubuTanimKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }
    protected function getFormActions(): array
    {
        if (CariGrubuTanimKaynagi::detayModu()) {
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
