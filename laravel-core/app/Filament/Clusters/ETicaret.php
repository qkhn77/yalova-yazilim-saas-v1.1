<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class ETicaret extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'e-ticaret';

    public static function getNavigationLabel(): string
    {
        return __('filament.ecommerce.cluster_label');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }
}
