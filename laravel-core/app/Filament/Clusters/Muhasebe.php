<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Muhasebe extends Cluster
{
    /**
     * Küme sınıfında $view zorunlu; aksi halde /admin/muhasebe 500 (BasePage::$view).
     */
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Muhasebe';

    protected static ?string $slug = 'muhasebe';
}
