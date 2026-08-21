<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelVardiyaKaynagi;
use App\Models\Personel\PersonelVardiyasi;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPersonelVardiya extends EditRecord
{
    protected static string $resource = PersonelVardiyaKaynagi::class;

    protected static string $view = 'filament.clusters.personel-takip.resources.personel-vardiya-kaynagi.pages.edit-personel-vardiya';

    public function form(Form $form): Form
    {
        if (PersonelVardiyaKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (PersonelVardiyaKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) ($this->record->durum ?? 'planlandi'),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (PersonelVardiyaKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! in_array($durum, ['planlandi', 'onaylandi', 'tamamlandi', 'iptal'], true)) {
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
        $detayModu = PersonelVardiyaKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PersonelVardiyaKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
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
        if (PersonelVardiyaKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'personel_id',
            'vardiya_sablonu_id',
            'tarih',
            'baslangic_at',
            'bitis_at',
            'baslangic_saati',
            'bitis_saati',
            'mola_dakika',
            'durum',
            'notlar',
            'olusturan_id',
            'onaylayan_id',
        ];

        $mevcut = PersonelVardiyasi::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
