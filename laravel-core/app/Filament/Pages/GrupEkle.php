<?php

namespace App\Filament\Pages;

use App\Filament\Resources\RolResource;
use Filament\Pages\Page;

class GrupEkle extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Kullanıcılar';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Grup Ekle';

    protected static ?string $title = 'Yeni Grup / Yetki';

    protected static string $view = 'filament.pages.redirect-placeholder';

    protected static string $routePath = 'kullanicilar/grup/ekle';

    public function mount(): void
    {
        $this->redirect(RolResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
