<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelGorevKaynagi\Pages;

use App\Filament\Clusters\PersonelTakip\Resources\PersonelGorevKaynagi;
use App\Models\Personel\PersonelGorevi;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonelGorev extends EditRecord
{
    protected static string $resource = PersonelGorevKaynagi::class;

    protected function getHeaderActions(): array
    {
        $detayModu = PersonelGorevKaynagi::detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_form' : 'detaylar')
                ->label($detayModu ? 'Hızlı Form' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PersonelGorevKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])
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
        if (PersonelGorevKaynagi::detayModu()) {
            return $data;
        }

        $alanlar = [
            'firma_id',
            'departman_id',
            'ad',
            'kod',
            'varsayilan_maas_tipi',
            'varsayilan_ucret',
            'aktif_mi',
            'siralama',
        ];

        $mevcut = PersonelGorevi::query()
            ->whereKey($this->record->getKey())
            ->first($alanlar);

        if (! $mevcut) {
            return $data;
        }

        $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));

        return array_replace($mevcutVeri, $data);
    }
}
