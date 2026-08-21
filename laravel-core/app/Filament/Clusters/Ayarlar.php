<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Ayarlar extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Ayarlar';

    protected static ?string $slug = 'ayarlar';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;
}
