<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\Concerns;

use App\Muhasebe\Tanimlar\MuhasebeTanimKayitMutator;
use Illuminate\Database\Eloquent\Model;

trait MutatesStandartMuhasebeTanimOlustur
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var class-string<Model> $sinif */
        $sinif = static::getResource()::getModel();

        return MuhasebeTanimKayitMutator::olustur($data, $sinif);
    }
}
