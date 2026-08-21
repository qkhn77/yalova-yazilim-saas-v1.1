<?php

namespace App\Filament\Clusters\Ayarlar\Pages;

use App\Filament\Clusters\Ayarlar;
use Filament\Pages\Page;

class KullaniciGruplari extends Page
{
    protected static ?string $cluster = Ayarlar::class;
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Kullanıcı Grupları';
    protected static ?string $slug = 'kullanici-ayarlari/kullanici-gruplari';

    protected static string $view = 'filament.clusters.ayarlar.pages.kullanici-gruplari';

    public function getSubNavigation(): array
    {
        return [];
    }
}
