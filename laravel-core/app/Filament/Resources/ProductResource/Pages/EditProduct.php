<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Clusters\Web\Resources\UrunKaynagi;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\Page;

class EditProduct extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.pages.redirect-placeholder';

    public function mount(int|string $record): void
    {
        $this->redirect(UrunKaynagi::getUrl('index'));
    }
}
