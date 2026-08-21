<?php

namespace App\Filament\Clusters\ProjeYonetimi\Pages;

use App\Filament\Clusters\MasrafTakip\Pages\IsletmeProjeleriSayfasi as MasrafTakipIsletmeProjeleriSayfasi;
use App\Filament\Clusters\ProjeYonetimi as ProjeYonetimiCluster;

class IsletmeProjeleriSayfasi extends MasrafTakipIsletmeProjeleriSayfasi
{
    protected static ?string $cluster = ProjeYonetimiCluster::class;

    protected static ?string $slug = 'projeler';

    protected static ?string $title = 'Projeler';
}
