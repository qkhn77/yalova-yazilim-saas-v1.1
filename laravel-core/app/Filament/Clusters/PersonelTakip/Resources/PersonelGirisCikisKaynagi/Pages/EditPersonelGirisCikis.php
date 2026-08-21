<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelGirisCikisKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelGirisCikisKaynagi;
use App\Models\Personel\PersonelGirisCikisi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonelGirisCikis extends EditRecord
{
    protected static string $resource = PersonelGirisCikisKaynagi::class;

    protected static string $view = 'filament.clusters.personel-takip.resources.personel-giris-cikis-kaynagi.pages.edit-personel-giris-cikis';

    protected function getHeaderActions(): array
    {
        $detayModu = PersonelGirisCikisKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PersonelGirisCikisKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
            Actions\DeleteAction::make(),
            ] : []),
        ];
    }

    protected function getFormActions(): array
    {
        if (PersonelGirisCikisKaynagi::detayModu()) {
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
        if (PersonelGirisCikisKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'sube_id',
            'personel_id',
            'vardiya_id',
            'tarih',
            'giris_at',
            'cikis_at',
            'giris_tipi',
            'cikis_tipi',
            'kaynak',
            'giris_ip',
            'cikis_ip',
            'cihaz_bilgisi',
            'konum_lat',
            'konum_lng',
            'gec_kalma_dakika',
            'erken_cikis_dakika',
            'fazla_mesai_dakika',
            'eksik_calisma_dakika',
            'onay_durumu',
            'onaylayan_id',
            'aciklama',
        ];

        $mevcut = PersonelGirisCikisi::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
