<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class TeknikServis extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Teknik Servis';

    protected static ?string $slug = 'teknik-servis';

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }
}
