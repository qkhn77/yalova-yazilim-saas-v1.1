<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi;
use App\Models\Personel\PersonelMaasDonemi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPersonelMaasDonemi extends EditRecord
{
    protected static string $resource = PersonelMaasDonemiKaynagi::class;

    protected static string $view = 'filament.clusters.personel-takip.resources.personel-maas-donemi-kaynagi.pages.edit-personel-maas-donemi';

    public function form(Form $form): Form
    {
        if (PersonelMaasDonemiKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (PersonelMaasDonemiKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) ($this->record->durum ?? 'taslak'),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (PersonelMaasDonemiKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! in_array($durum, ['taslak', 'hesaplandi', 'onaylandi', 'odendi', 'iptal'], true)) {
            throw ValidationException::withMessages([
                'data.durum' => 'Durum gecersiz.',
            ]);
        }

        $this->handleRecordUpdate($this->record, $this->mutateFormDataBeforeSave([
            'durum' => $durum,
        ]));

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $detayModu = PersonelMaasDonemiKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PersonelMaasDonemiKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (PersonelMaasDonemiKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'ad',
            'donem_yil',
            'donem_ay',
            'baslangic_tarihi',
            'bitis_tarihi',
            'durum',
            'toplam_brut',
            'toplam_kesinti',
            'toplam_net',
            'para_birimi',
            'aciklama',
            'olusturan_id',
            'onaylayan_id',
            'onay_at',
        ];

        $mevcut = PersonelMaasDonemi::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
