<?php

namespace App\Filament\Clusters\Ayarlar\Pages;

use App\Filament\Clusters\Ayarlar;
use Filament\Pages\Page;

class Kullanicilar extends Page
{
    protected static ?string $cluster = Ayarlar::class;
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Kullanıcılar';
    protected static ?string $slug = 'kullanici-ayarlari/kullanicilar';

    protected static string $view = 'filament.clusters.ayarlar.pages.kullanicilar';

    public function getSubNavigation(): array
    {
        return [];
    }
}
