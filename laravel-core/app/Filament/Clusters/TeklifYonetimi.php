<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class TeklifYonetimi extends Cluster
{
    protected static string $view = 'filament.clusters.cluster-root';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationLabel = 'Teklif Yönetimi';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'teklif-yonetimi';
}
