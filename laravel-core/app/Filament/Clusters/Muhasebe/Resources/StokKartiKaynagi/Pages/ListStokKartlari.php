<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListStokKartlari extends ListRecords
{
    protected static string $resource = StokKartiKaynagi::class;

    protected static ?string $title = 'Stok kartları';

    public function getSubNavigation(): array
    {
        return [];
    }

    public function getTitle(): string|Htmlable
    {
        return static::getResource()::isWebUrunContext() ? 'Ürün listesi' : 'Stok kartları';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(static::getResource()::isWebUrunContext() ? 'Ürün ekle' : 'Stok ekle')
                ->url(fn (): string => static::getResource()::getUrl('create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
