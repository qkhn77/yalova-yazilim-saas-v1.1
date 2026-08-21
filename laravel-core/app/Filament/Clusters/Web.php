<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Web extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Web';

    protected static ?string $slug = 'web';

    protected static ?int $navigationSort = 3;
}
