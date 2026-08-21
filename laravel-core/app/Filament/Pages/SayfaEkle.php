<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PageResource;
use Filament\Pages\Page;

class SayfaEkle extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'Sayfalar';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Sayfa Ekle';
    protected static ?string $title = 'Yeni Sayfa';
    protected static string $view = 'filament.pages.redirect-placeholder';
    protected static string $routePath = 'sayfalar/ekle';

    public function mount(): void
    {
        $this->redirect(PageResource::getUrl('create'));
    }

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }
}
