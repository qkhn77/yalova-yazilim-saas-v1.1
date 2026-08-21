<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelIzinKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelIzinKaynagi;
use App\Models\Personel\PersonelIzni;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPersonelIzin extends EditRecord
{
    protected static string $resource = PersonelIzinKaynagi::class;

    protected static string $view = 'filament.clusters.personel-takip.resources.personel-izin-kaynagi.pages.edit-personel-izin';

    public function form(Form $form): Form
    {
        if (PersonelIzinKaynagi::detayModu()) {
            return parent::form($form);
        }

        return $form
            ->schema([])
            ->model($this->getRecord())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        if (PersonelIzinKaynagi::detayModu()) {
            parent::fillForm();

            return;
        }

        $this->data = [
            'durum' => (string) ($this->record->durum ?? 'onay_bekliyor'),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (PersonelIzinKaynagi::detayModu()) {
            parent::save($shouldRedirect, $shouldSendSavedNotification);

            return;
        }

        $this->authorizeAccess();

        $durum = (string) ($this->data['durum'] ?? '');
        if (! in_array($durum, ['onay_bekliyor', 'onaylandi', 'reddedildi'], true)) {
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
        $detayModu = PersonelIzinKaynagi::detayModu();

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

    protected function getFormActions(): array
    {
        if (PersonelIzinKaynagi::detayModu()) {
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
        if (PersonelIzinKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'personel_id',
            'izin_turu',
            'baslangic_tarihi',
            'bitis_tarihi',
            'baslangic_at',
            'bitis_at',
            'gun_sayisi',
            'saat_sayisi',
            'durum',
            'onay_durumu',
            'onaylayan_id',
            'onay_at',
            'aciklama',
            'belge_path',
            'belge_yolu',
        ];

        $mevcut = PersonelIzni::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
