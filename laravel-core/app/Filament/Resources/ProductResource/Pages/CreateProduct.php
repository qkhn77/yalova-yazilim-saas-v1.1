<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Clusters\Web\Resources\UrunKaynagi;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    public function mount(): void
    {
        $this->redirect(UrunKaynagi::getUrl('create'));
    }
}
