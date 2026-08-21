<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Clusters\Web\Resources\UrunKategoriKaynagi;
use App\Filament\Resources\ProductCategoryResource;
use Filament\Resources\Pages\Page;

class EditProductCategory extends Page
{
    protected static string $resource = ProductCategoryResource::class;

    protected static string $view = 'filament.pages.redirect-placeholder';

    public function mount(int|string $record): void
    {
        $this->redirect(UrunKategoriKaynagi::getUrl('index'));
    }
}
