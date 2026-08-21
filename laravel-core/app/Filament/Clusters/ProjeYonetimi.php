<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class ProjeYonetimi extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Proje Yönetimi';

    protected static ?string $slug = 'proje-yonetimi';
}
