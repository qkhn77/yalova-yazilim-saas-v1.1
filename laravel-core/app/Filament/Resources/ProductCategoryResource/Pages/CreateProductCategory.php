<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi;
use App\Filament\Resources\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    public function mount(): void
    {
        $this->redirect(UrunKategoriKaynagi::getUrl('create'));
    }
}
