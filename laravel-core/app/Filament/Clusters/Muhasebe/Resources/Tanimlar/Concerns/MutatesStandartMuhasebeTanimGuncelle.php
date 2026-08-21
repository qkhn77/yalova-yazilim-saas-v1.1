<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns;

use App\Muhasebe\Tanimlar\MuhasebeTanimKayitMutator;
use Illuminate\Database\Eloquent\Model;

trait MutatesStandartMuhasebeTanimGuncelle
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var class-string<Model> $sinif */
        $sinif = static::getResource()::getModel();

        $routeName = (string) (request()->route()?->getName() ?? '');
        if (str_ends_with($routeName, '.edit') && ! request()->boolean('detay')) {
            $alanlar = [
                'firma_id',
                'is_sabit',
                'kod',
                'ad',
                'aktif_mi',
                'varsayilan_mi',
            ];
            $mevcutVeri = array_intersect_key($this->record->getAttributes(), array_flip($alanlar));
            if (count($mevcutVeri) < count($alanlar)) {
                $mevcut = $sinif::query()
                    ->whereKey($this->record->getKey())
                    ->first($alanlar);

                if ($mevcut instanceof Model) {
                    $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));
                }
            }

            $data = array_replace($mevcutVeri, $data);
        }

        return MuhasebeTanimKayitMutator::guncelle($data, $sinif, $this->record);
    }
}
