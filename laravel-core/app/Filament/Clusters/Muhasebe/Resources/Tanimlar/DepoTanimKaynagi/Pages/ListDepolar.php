<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DepoTanimKaynagi;
use App\Filament\Clusters\Muhasebe\Widgets\DepoHareketleriWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDepolar extends ListRecords
{
    protected static string $resource = DepoTanimKaynagi::class;

    protected static ?string $title = 'Depolar';

    public function getSubNavigation(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Depo ekle')];
    }

    protected function getFooterWidgets(): array
    {
        return [DepoHareketleriWidget::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
