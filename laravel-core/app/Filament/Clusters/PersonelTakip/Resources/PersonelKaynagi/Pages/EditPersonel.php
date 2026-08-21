<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi;
use App\Models\Personel\Personel;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonel extends EditRecord
{
    protected static string $resource = PersonelKaynagi::class;

    protected function getHeaderActions(): array
    {
        $detayModu = request()->boolean('detay');

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizliForm' : 'detayliForm')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PersonelKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : PersonelKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()]).'?detay=1'),
            ...($detayModu ? [
            Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (request()->boolean('detay')) {
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
        if (request()->boolean('detay')) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'kullanici_id',
            'gorev_id',
            'departman_id',
            'personel_no',
            'ad_soyad',
            'telefon',
            'email',
            'tc_kimlik_no',
            'adres',
            'acil_durum_kisi',
            'acil_durum_telefon',
            'calisma_tipi',
            'maas_tipi',
            'maas_tutari',
            'saatlik_ucret',
            'gunluk_ucret',
            'ise_giris_tarihi',
            'isten_cikis_tarihi',
            'pin_kodu',
            'pin_kodu_hash',
            'durum',
            'notlar',
        ];

        $mevcut = Personel::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
