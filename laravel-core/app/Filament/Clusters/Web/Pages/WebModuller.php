<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use Filament\Pages\Page;

class WebModuller extends Page
{
    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Modüller';

    protected static string $view = 'filament.clusters.web.pages.web-moduller';
}
