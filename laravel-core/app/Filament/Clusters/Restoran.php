<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Restoran extends Cluster
{
    protected static string $view = 'filament.clusters.restoran.root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Restoran';

    protected static ?string $slug = 'restoran';

    public function mount(): void
    {
        // Parent Cluster::mount() builds all sub-navigation before redirecting; the custom
        // sidebar already provides module navigation, so the empty root can stay lightweight.
    }
}
