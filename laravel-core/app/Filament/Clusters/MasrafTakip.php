<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class MasrafTakip extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Masraf Takibi';

    protected static ?string $slug = 'masraf-takip';
}
