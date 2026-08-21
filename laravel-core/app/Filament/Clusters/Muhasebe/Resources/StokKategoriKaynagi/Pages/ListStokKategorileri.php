<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKategoriKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStokKategorileri extends ListRecords
{
    protected static string $resource = StokKategoriKaynagi::class;

    protected static ?string $title = 'Stok kategorileri';

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Kategori ekle'),
        ];
    }
}
