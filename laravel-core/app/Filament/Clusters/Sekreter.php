<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Sekreter extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Ajanda ve Görevler';
    protected static ?string $clusterBreadcrumb = 'Ajanda ve Görevler';
    protected static ?string $slug = 'sekreter';
}
