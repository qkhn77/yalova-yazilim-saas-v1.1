<?php

namespace App\Filament\Clusters\Web\Resources\UrunKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages\ViewStokKarti;
use App\Filament\Clusters\Web\Resources\UrunKaynagi;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;

class ViewUrun extends ViewStokKarti
{
    protected static string $resource = UrunKaynagi::class;

    protected static string $view = 'filament.clusters.web.resources.urun-kaynagi.pages.view-urun';

    protected function fillForm(): void
    {
        if (request()->boolean('detay')) {
            parent::fillForm();
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        if (request()->boolean('detay')) {
            return parent::infolist($infolist);
        }

        return $infolist
            ->schema([
                TextEntry::make('ad')
                    ->label('Urun'),
            ])
            ->columns(1);
    }

    protected function getHeaderActions(): array
    {
        if (request()->boolean('detay')) {
            return parent::getHeaderActions();
        }

        return [];
    }
}
