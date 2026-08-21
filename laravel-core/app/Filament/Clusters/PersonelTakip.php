<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class PersonelTakip extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Personel Takip';

    protected static ?string $slug = 'personel-takip';
}
